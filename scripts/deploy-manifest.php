<?php
/**
 * Deployment parity manifest — read-only, standalone (does not boot Laravel).
 *
 *   On the server:   php deploy-manifest.php /path/to/app/root > server-manifest.txt
 *   Locally:         php scripts/deploy-manifest.php . > local-manifest.txt
 *   Compare:         php scripts/deploy-manifest.php --compare server-manifest.txt local-manifest.txt
 *
 * Prints "sha256  relative/path" for deployable code only. File contents, .env,
 * storage, vendor and uploads are never read or printed. Text files are hashed
 * with CRLF normalised to LF so Windows and Linux checkouts compare equal.
 */

const INCLUDE_DIRS = ['app', 'bootstrap/app.php', 'config', 'routes', 'database/migrations', 'database/seeders', 'resources/views', 'public/react', 'public/.htaccess', 'public/index.php', 'composer.lock'];
const TEXT_EXT = ['php', 'js', 'css', 'html', 'json', 'htaccess', 'lock', 'blade', 'txt', 'xml', 'svg'];

if (($argv[1] ?? '') === '--compare') {
    $load = function (string $file): array {
        $map = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^([0-9a-f]{64})  (.+)$/', $line, $m)) {
                $map[$m[2]] = $m[1];
            }
        }
        return $map;
    };
    $a = $load($argv[2]);
    $b = $load($argv[3]);
    $onlyA = array_diff_key($a, $b);
    $onlyB = array_diff_key($b, $a);
    $changed = array_keys(array_filter(array_intersect_key($a, $b), fn ($h, $p) => $b[$p] !== $h, ARRAY_FILTER_USE_BOTH));
    printf("identical: %d\nchanged: %d\nonly in %s: %d\nonly in %s: %d\n", count($a) - count($onlyA) - count($changed), count($changed), $argv[2], count($onlyA), $argv[3], count($onlyB));
    foreach ($changed as $p) echo "  CHANGED  $p\n";
    foreach (array_keys($onlyA) as $p) echo "  ONLY-1   $p\n";
    foreach (array_keys($onlyB) as $p) echo "  ONLY-2   $p\n";
    exit(count($changed) + count($onlyA) + count($onlyB) > 0 ? 1 : 0);
}

$root = rtrim(realpath($argv[1] ?? '.') ?: '.', DIRECTORY_SEPARATOR);
$lines = [];
foreach (INCLUDE_DIRS as $entry) {
    $path = $root . DIRECTORY_SEPARATOR . $entry;
    $files = is_dir($path)
        ? iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)))
        : (is_file($path) ? [new SplFileInfo($path)] : []);
    foreach ($files as $f) {
        if (!$f->isFile()) continue;
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        $ext = strtolower($f->getExtension() ?: ltrim($f->getFilename(), '.'));
        $data = file_get_contents($f->getPathname());
        if (in_array($ext, TEXT_EXT, true)) {
            $data = str_replace("\r\n", "\n", $data);
        }
        $lines[$rel] = hash('sha256', $data) . '  ' . $rel;
    }
}
ksort($lines);
echo implode("\n", $lines), "\n";
