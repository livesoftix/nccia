<?php

namespace App\Console\Commands;

use App\Services\AuditLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Configuration-only by default; --database explicitly opts into read-only DB checks. */
class SecurityPreflight extends Command
{
    protected $signature = 'security:preflight {--database : Inspect schema, current MySQL grants and audit integrity without modifying data} {--json : Print a redacted machine-readable report}';
    protected $description = 'Check production security readiness; exit 2 means evidence is incomplete';
    private array $checks = [];

    public function handle(): int
    {
        $this->checks = [];
        $this->check('production_environment', config('app.env') === 'production', 'Production environment is required.');
        $this->check('debug_disabled', config('app.debug') === false, 'Debug output must be disabled.');
        $appKey = (string) config('app.key');
        $this->check('application_key', str_starts_with($appKey, 'base64:') && $this->strongKey(substr($appKey, 7), true), 'A generated 32-byte application key is required.');
        $auditKey = (string) config('security.audit_hmac_key');
        $this->check('dedicated_audit_key', $this->strongKey($auditKey, false) && !hash_equals($this->normalizedKey($appKey), $this->normalizedKey($auditKey)), 'A strong, separate audit authentication key is required.');
        $this->check('audit_writes_required', config('security.audit.require_writes') === true, 'Audited mutations must fail closed when the audit ledger is unavailable.');
        $this->check('curl_available', extension_loaded('curl'), 'Verified outbound processing requires the curl extension.');
        $this->check('https_origin', $this->httpsUrl((string) config('app.url')), 'Application origin must use HTTPS without URL credentials.');
        $this->check('secure_sessions', config('session.secure') === true && config('session.http_only') === true && config('session.encrypt') === true
            && in_array(config('session.same_site'), ['strict', 'lax'], true) && in_array(config('session.driver'), ['database', 'redis'], true), 'Encrypted server-side cookies require Secure, HttpOnly and SameSite.');
        $this->check('bounded_sessions', (int) config('session.lifetime') > 0 && (int) config('session.lifetime') <= 120
            && (int) config('session.idle_timeout') > 0 && (int) config('session.idle_timeout') <= 15
            && (int) config('security.session_absolute_minutes') > 0 && (int) config('security.session_absolute_minutes') <= 480, 'Idle and absolute session lifetimes must be bounded.');
        $required = ['admin', 'superadmin', 'director_general', 'admin_forensic'];
        $this->check('mandatory_mfa', config('security.mfa.require_all') === true || !array_diff($required, (array) config('security.mfa.required_roles')), 'All privileged roles must require MFA.');
        $this->check('argon2id_passwords', defined('PASSWORD_ARGON2ID') && config('hashing.driver') === 'argon2id'
            && (int) config('hashing.argon.memory') >= 65536 && (int) config('hashing.argon.time') >= 3
            && config('hashing.rehash_on_login') === true, 'Argon2id support and production work factors are required.');
        $this->check('upload_scan_required', config('security.uploads.scan_required') === true, 'Uploads must fail closed when malware scanning is unavailable.');
        $this->smtp();
        $this->outbound('sms', (bool) config('services.sms.enabled'), (string) config('services.sms.gateway_url'));
        $this->outbound('adp', (bool) config('services.adp.approved_disclosure'), (string) config('services.adp.url'));
        if ($this->option('database')) {
            $this->database();
        } else {
            $this->checks[] = ['id' => 'database_schema_grants_audit', 'status' => 'unverified', 'detail' => 'Explicit --database is required for read-only database evidence.'];
        }
        // These controls cannot be established by application configuration.
        $this->checks[] = ['id' => 'host_tls_acl_backup_external_anchor', 'status' => 'unverified', 'detail' => 'Server TLS, OS permissions, restored backup and independent audit checkpoint need deployment evidence.'];
        $counts = array_count_values(array_column($this->checks, 'status'));
        $exit = ($counts['fail'] ?? 0) ? 1 : (($counts['unverified'] ?? 0) ? 2 : 0);
        $report = ['schema_version' => 1, 'checked_at' => gmdate(DATE_ATOM), 'database_inspected' => (bool) $this->option('database'),
            'status' => $exit === 1 ? 'failed' : ($exit === 2 ? 'incomplete' : 'passed'), 'checks' => $this->checks];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Result', 'Action'], array_map(fn ($item) => array_values($item), $this->checks));
        }
        return $exit;
    }

    private function check(string $id, bool $ok, string $detail): void
    {
        $this->checks[] = ['id' => $id, 'status' => $ok ? 'pass' : 'fail', 'detail' => $ok ? 'Verified.' : $detail];
    }

    private function strongKey(string $key, bool $encoded): bool
    {
        if ($encoded) {
            $key = base64_decode($key, true) ?: '';
        } elseif (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }
        return strlen($key) >= 32 && (!$encoded || strlen($key) === 32) && count(array_unique(str_split($key))) >= 8
            && !preg_match('/change.?me|example|placeholder/i', $key);
    }

    private function normalizedKey(string $key): string
    {
        return str_starts_with($key, 'base64:') ? (base64_decode(substr($key, 7), true) ?: '') : $key;
    }

    private function httpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
    }

    private function smtp(): void
    {
        if (config('mail.default') !== 'smtp') {
            $this->checks[] = ['id' => 'smtp_transport', 'status' => 'unverified', 'detail' => 'Review the selected mail transport and its disclosure policy before deployment.'];
            return;
        }
        $smtp = (array) config('mail.mailers.smtp');
        $ssl = $smtp['stream']['ssl'] ?? [];
        $implicit = ($smtp['scheme'] ?? '') === 'smtps' || (($smtp['encryption'] ?? '') === 'ssl' && (int) ($smtp['port'] ?? 0) === 465);
        $requiredTls = ($smtp['require_tls'] ?? false) === true;
        $this->check('smtp_transport', ($implicit || $requiredTls) && ($smtp['verify_peer'] ?? true) === true && ($ssl['verify_peer'] ?? true) === true
            && ($ssl['verify_peer_name'] ?? true) === true && ($ssl['allow_self_signed'] ?? false) === false
            && empty($smtp['url']) && !empty($smtp['host']) && !empty($smtp['username']) && !empty($smtp['password']), 'SMTP must enforce verified TLS and configured credentials; URL overrides need separate review.');
    }

    private function outbound(string $service, bool $enabled, string $url): void
    {
        if (!$enabled) {
            $this->check($service.'_outbound', true, '');
            return;
        }
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $ports = (array) config("services.$service.allowed_ports");
        $hosts = array_map('strtolower', (array) config("services.$service.allowed_hosts"));
        $ok = $this->httpsUrl($url) && in_array($host, $hosts, true) && in_array((int) ($parts['port'] ?? 443), $ports, true)
            && !(bool) config("services.$service.allow_local_http", false);
        if ($service === 'sms') {
            $ok = $ok && config('services.sms.http_method') === 'post';
        }
        $this->check($service.'_outbound', $ok, 'Enabled external processing requires HTTPS, exact allowed host/port and approved policy.');
    }

    private function database(): void
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $schema = Schema::connection($connection->getName());
            $schemaOk = $schema->hasColumns('users', ['mfa_secret', 'mfa_recovery_codes', 'mfa_confirmed_at', 'mfa_last_counter', 'security_version'])
                && $schema->hasColumns('audit_ledger', ['hash_version', 'properties_ciphertext', 'hash', 'prev_hash', 'data_hash'])
                && $schema->hasColumns('audit_ledger_head', ['last_id', 'entries', 'hash', 'mac']);
            $this->check('database_security_schema', $schemaOk, 'Account and audit security migrations must be present.');
            if ($schemaOk) {
                $this->check('audit_integrity', AuditLedgerService::verify()['ok'] === true, 'Audit chain or authenticated head failed verification.');
            }
            if ($driver === 'mysql') {
                $username = strtolower((string) $connection->getConfig('username'));
                $this->check('database_nonadmin_account', !in_array($username, ['', 'root', 'admin', 'administrator', 'sa'], true), 'Use a dedicated non-administrator runtime database account.');
                $grants = $connection->select('SHOW GRANTS FOR CURRENT_USER');
                $scope = '`'.str_replace('`', '``', (string) $connection->getDatabaseName()).'`.*';
                $safe = true;
                foreach ($grants as $row) {
                    $grant = (string) array_values((array) $row)[0];
                    if (preg_match('/^GRANT USAGE ON \*\.\* TO /i', $grant)) {
                        continue;
                    }
                    if (!preg_match('/^GRANT ([A-Z, ]+) ON (.+?) TO /i', $grant, $match) || $match[2] !== $scope
                        || preg_match('/WITH GRANT OPTION|WITH ADMIN OPTION/i', $grant)) {
                        $safe = false;
                        break;
                    }
                    foreach (explode(',', strtoupper($match[1])) as $privilege) {
                        if (!in_array(trim($privilege), ['SELECT', 'INSERT', 'UPDATE', 'DELETE'], true)) {
                            $safe = false;
                        }
                    }
                }
                $this->check('database_runtime_grants', $safe && count($grants) > 0, 'Runtime grants must be restricted to application CRUD; roles and other scopes require explicit review.');
                $tls = $connection->select("SHOW SESSION STATUS LIKE 'Ssl_cipher'");
                $this->check('database_transport_tls', !empty(((array) ($tls[0] ?? []))['Value']), 'Database connection must negotiate TLS.');
            } else {
                $this->checks[] = ['id' => 'database_runtime_grants_tls', 'status' => 'unverified', 'detail' => 'Automatic grant and TLS verification currently supports MySQL only.'];
            }
        } catch (\Throwable) {
            // Connection errors and SQL text can contain credentials or private data.
            $this->check('database_readonly_checks', false, 'Database checks could not complete; inspect protected server logs.');
        }
    }
}
