# Deployment evidence, recovery and incident runbooks

Revision 0.1, 2026-10-08. **Proposed procedures; deployment and exercise results unknown.**

The infrastructure DOCXs describe recommendations. Do not promote them into deployed-control evidence. The operations owner must inventory the actual OS/version, DBMS/version, web/application server/version, PHP/runtime, hosting provider, regions, network/storage, administrators, data flows and managed-provider responsibilities before tailoring these checks.

## Production hardening and CIS Benchmarks

Select the exact [CIS Benchmarks](https://www.cisecurity.org/cis-benchmarks) applicable to the deployed OS, DBMS and web/application server. Record benchmark title/version, Level/profile, host scope, date, tool/manual method, result, exclusions and approved exceptions. Ubuntu/Nginx/MySQL in the proposal are not proof of the actual platform. Assess staging first; configuration changes that may affect access/availability require a tested rollback and operations approval.

Minimum local deployment checks: supported patched packages; unique least-privilege administration with MFA; disable unnecessary services/accounts; restrict SSH/admin/database ingress; configure time synchronization and host/security logs; set file permissions separating deploy/app/log/storage ownership; use a non-public private upload directory with no script execution; verify HTTPS and approved TLS/ciphers/certificate renewal; production debug off; secret/config files and backups inaccessible from web; cookies/session settings verified behind the real proxy; database least privileges and audited admin access; encryption/key custody for restricted database/files/backups; endpoint protection/scanner capability; resource/rate limits; egress constraints for OCR/PDF/remote-fetch features; health/alerting; evidence-retention policy; independent audit checkpoints. Record each check's actual result instead of accepting framework defaults.

### Direct evidence URL denial at the web server

`public/.htaccess` denies direct `/storage/` access to verification, forensic, import, court, case-attachment and enquiry-attachment directories, and denies `/uploads`. These are Apache source rules; Apache syntax/configuration and deployed behavior have not been executed locally. Nginx does not read `.htaccess`. Its operations owner must install equivalent rules in the active server configuration and verify location precedence and any storage aliases. Example for review in staging:

```nginx
# Custom server block example; adapt to the actual deployment.
# Put this before general regex static-file/PHP locations. An existing
# broader ^~ /storage/ alias can bypass it and must be reviewed/adjusted.
location ~* ^/storage/(verification-reports|verifications|forensic-requests|forensic-audio|forensic-reports|imports|court-reports|case-attachments|enquiry-attachments)/ {
    return 404;
}
location = /uploads { return 404; }
location ^~ /uploads/ { return 404; }
```

Source for precedence review: [Nginx location documentation](https://nginx.org/en/docs/http/ngx_http_core_module.html#location). Test the final server configuration with its supported syntax checker before a controlled reload; neither this example nor source inspection proves deployment protection.

Required staging smoke tests use synthetic restricted files: direct unauthenticated and authenticated GET/HEAD requests for every listed storage prefix, especially `case-attachments` and `enquiry-attachments`, and `/uploads`, must return 403 or 404 without file bytes. Check extension/case/encoded-path variants against the actual server aliases and confirm uploads cannot execute. A correctly authenticated, signed and object-authorized `/api/secure-file` request must still return the expected file; missing/expired signatures and unauthorized roles/circles/objects must fail. Record server/configuration revision, synthetic paths, statuses, verifier and date. No staging/deployment result is claimed here.

## CIS Controls v8.1 programme checklist

Use the official [v8.1 safeguards and implementation groups](https://www.cisecurity.org/controls/v8-1), select the appropriate group by risk and assess every applicable safeguard. The following is a project action checklist spanning all 18 control areas; it is not the complete safeguard catalogue.

| Control | Local deliverable / owner role |
| --- | --- |
| 1 | Inventory servers, endpoints, network/cloud assets and accountable owners / operations |
| 2 | Inventory software/dependencies, supported versions and approved install/update process / developer + operations |
| 3 | Classify data, inventory locations, approve access/retention/disposal and encryption / privacy + operations |
| 4 | Versioned hardening baselines and verified deviations for every asset class / operations |
| 5 | Unique account inventory, privileged/emergency accounts and joiner/mover/leaver reviews / operations + application owner |
| 6 | MFA, least-privilege role/circle enforcement and periodic access recertification / security + application owner |
| 7 | Vulnerability scanning/triage/remediation with dates and approved exceptions / security + operations |
| 8 | Event inventory, time sync, retention, tamper protection, centralized review/alerts / operations |
| 9 | Approved browser/email protections and review of external links/attachments / endpoint owner |
| 10 | Endpoint/server malware defence and evidence-upload handling with health monitoring / operations |
| 11 | Encrypted access-separated backups and successful isolated recovery evidence / operations |
| 12 | Network asset management, secure configuration, segmentation and administration / operations |
| 13 | Network/security telemetry, alert response and verified monitoring coverage / operations |
| 14 | Staff awareness and role-specific secure-development/incident training / ISMS owner |
| 15 | Supplier inventory, contracts, responsibilities and assurance review / procurement + security |
| 16 | Secure development, requirements/reviews/testing and vulnerability intake / application owner |
| 17 | Named incident team, escalation, evidence handling and exercises / security + sponsor |
| 18 | Scoped independent penetration testing, remediation and retests / independent assessor |

## NIST SP 800-53 Rev.5 system security plan starter

Select an organization-approved baseline, sensitivity/categorization, organization-defined parameters, enhancements, tailoring and inherited controls. Pin the adopted catalogue release (official page identifies release 5.2.0). This is a starter family checklist, not a complete System Security Plan or full catalogue implementation. Use [SP 800-53](https://csrc.nist.gov/pubs/sp/800/53/r5/upd1/final), [SP 800-53B](https://csrc.nist.gov/pubs/sp/800/53/b/final) and [SP 800-53A](https://csrc.nist.gov/pubs/sp/800/53/a/r5/final) for baseline/assessment selection.

| Families | Required local evidence |
| --- | --- |
| AC, IA | Accounts/MFA/session/object access, authorization matrix and reviews; consider AC-2/3/6 and IA-2/5 |
| AU | Event content, retention, tamper protection, time synchronization, review and independent checkpoint controls; consider AU-2/3/6/8/9/11 |
| AT, PS | Role-specific training, personnel screening/termination/access records |
| CA, PL, PM | Approved plan/scope/assessment/monitoring and organizational programme decisions |
| CM, MA | Baseline configuration/change inventory, approved remote maintenance and actual host hardening |
| CP | Backups/recovery plan and exercises; consider CP-2/4/9/10 |
| IR | Incident response, reporting, exercises and evidence custody; consider IR-4/6/8 |
| MP, PE | Media sanitization/custody and data-centre/office physical controls; provider evidence where inherited |
| PT | Personal-data purpose, legal basis/notice, retention and disclosure decisions by privacy/legal owner |
| RA | Risk/vulnerability assessments, threat model and findings; consider RA-3/5 |
| SA, SR | Development/supplier security requirements, assessment, provenance and component supply-chain risk |
| SC, SI | TLS/key/storage protection, integrity/patch/scanner/input controls and monitoring; consider SC-8/12/13/28 and SI-2/3/4/7/10 |

Every family remains pending detailed control/enhancement selection and effectiveness evidence. Proposed sample IDs do not assert equivalence with ASVS or ISO requirements.

## CSA CCM cloud applicability and responsibilities

Adopt [CSA CCM v4.1](https://cloudsecurityalliance.org/artifacts/cloud-controls-matrix-v4-1) only after the actual provider/service model is known. The official release contains 207 controls across 17 domains. Obtain its full matrix and record each control's customer/provider/shared responsibility, service/region scope, evidence and result. Provider certification alone does not establish customer control effectiveness; non-cloud exclusions need approved applicability rationale.

| Domain | NCCIA/customer/provider evidence needed |
| --- | --- |
| A&A | Independent assessments and finding closure with precise service scope |
| AIS | Application/interface requirements, secure reviews and verification |
| BCR | Backup/DR service commitments and tested application recovery |
| CCC | Approved application/provider configuration and change control |
| CEK | Transport/storage encryption, key ownership, rotation and recovery |
| DCS | Data-centre physical/environmental controls and provider scope |
| DSP | Personal/evidence-data location, classification, retention and deletion |
| GRC | Approved policies, risk ownership and applicable obligations |
| HRS | Staff/provider access, training and termination processes |
| IAM | User/admin/service identity, MFA and privileged access review |
| IPY | Export/exit capability, interoperability and provider portability plan |
| IVS | Network/compute/container isolation, hardened hosts and tenant boundaries |
| LOG | Application/provider event collection, retention, integrity and alert review |
| SEF | Incident escalation, forensics/evidence handling and provider notification |
| STA | Supplier/service inventory, contracts and shared-responsibility review |
| TVM | Vulnerability/patch management, testing, remediation and exceptions |
| UEM | Managed officer/admin endpoints, supported clients and endpoint protection |

Domain abbreviations identify review areas; the full official control text and per-control responsibilities are still to be assessed.

## Backup and isolated restore procedure

Proposed objectives: RPO and RTO **pending business approval**, retention/location/key custody **pending privacy/operations approval**. Do not infer that the proposed daily DB/hourly file backup meets an approved objective. Keep database, private evidence, audit checkpoint and required configuration consistent; protect secret/key recovery separately and restrict access.

1. Operations inventories backup scope and dependencies, chooses tested full/incremental mechanisms and records restore order, key recovery and immutable/offline copy capability.
2. Execute backups with monitored job results, protected credentials, encryption, integrity checks and access-separated off-site copies. Record failures and corrective actions.
3. For a restore exercise, create a separately authorized isolated environment with egress/mail/SMS disabled; use redacted or approved protected data and do not overwrite production.
4. Restore database, files and configuration in a consistent recovery point. Recover approved keys using the documented custody process. Verify counts/relations, representative case/evidence hashes, permissions, audit/checkpoints and synthetic authentication/critical workflow behavior.
5. Measure elapsed recovery time and last recoverable point against approved RTO/RPO. Record backup IDs, hashes, environment, operator/witness, results, missing data and failure details.
6. Record owner acceptance/remediation; securely dispose of exercise data per the approved policy. A failed or unexecuted exercise cannot be marked verified.

Exercise record: ID `pending`; date/operator/witness `null`; backup/restore target `null`; RTO/RPO `null`; actual times `null`; integrity/workflow evidence `[]`; result `pending-verification`; business owner decision `pending`.

## Incident response procedure

Named incident commander, alternate, operations/security contacts, privacy/legal owner, approved communications channel and provider escalation contacts are **pending assignment**. Use approved internal channels; this runbook authorizes no external messages by itself. Determine reporting obligations with the designated privacy/legal authority.

1. **Detect and triage:** record alert/time/source, affected service/data, initial scope and severity. Preserve original logs and event references. Treat credential theft, cross-circle disclosure, evidence tampering and service compromise as high-priority cases.
2. **Contain:** commander authorizes proportionate access revocation/session invalidation, isolation or temporary disablement of affected feature. Preserve evidence and access decisions; avoid unapproved destructive cleanup.
3. **Investigate:** trained personnel collect protected evidence with hashes, UTC/local timestamps, source/custody, collectors and access records. Inspect related tenants/accounts/variants. Store unredacted material outside Git with restricted access.
4. **Eradicate and recover:** patch verified cause, rotate/revoke exposed secrets/factors, rebuild affected assets from trusted artifacts where required, restore consistently and validate integrity/security before returning service.
5. **Communicate:** sponsor/privacy/legal owner approves recipient/content and required notifications through established channels; track sent decisions/results separately.
6. **Learn and improve:** record root cause, impact, timeline, response effectiveness, corrective-action owner/due date, regression tests and risk/ONF updates. Independently verify closure.

Tabletop scenarios: compromised privileged account/MFA recovery; malicious evidence upload/parser compromise; audit tampering; ransomware/failed backup; external provider disclosure. Record scenario, participants, decisions, time objectives, deficiencies and improvement actions. No exercise has been claimed executed.

## Release/deployment evidence checklist

Record exact commit/artifact/SBOM, scan/test reports, approved residual risks, named approvers, backup and rollback readiness, deployment/configuration hashes, benchmark/manual results, TLS/storage/network evidence, monitoring/alert health, smoke verification and post-release review. Keep credentials and restricted reports in secure storage; commit only redacted evidence references. Revalidate after environment or security-sensitive application changes.

## Security release migration and account enrollment

The current source work includes a Laravel 12 dependency upgrade, account security fields, encrypted audit `properties` and authenticated audit metadata/head. Other metadata columns such as `user_name`, `description`, IP and user agent remain plaintext in the database; actual database/disk encryption, access controls and privacy review are still required. Review the actual resolved lockfile/runtime support and scan results before deployment. No production migration, user enrollment, key rotation or historical-audit conversion is claimed executed by this documentation.

Before deployment, operations must back up and verify rollback/recovery; review/apply the existing `2026_10_08_000001_create_audit_ledger_table.php` and new `2026_10_08_000002_add_account_security_to_users.php`, `2026_10_08_000003_protect_audit_ledger_payloads.php` migrations in the approved environment. Inspect the final migration content and compatibility with the actual database; these filenames are not a command to run blindly against production.

Configure a separately generated strong `AUDIT_HMAC_KEY` through protected secret management. Keep it stable and recoverable across `APP_KEY` rotations; do not regenerate it on every deployment. Rotating an application encryption key requires a tested process to preserve decryption of MFA/audit data and retained recovery keys. Never commit key values. The audit v2 MAC covers metadata and an authenticated singleton head detects deletion of the tail; privileged database compromise can still require independent immutable external checkpoints and backups.

Legacy v1 records retain their original, limited hash coverage and may contain plaintext personal information. V2 controls do not retroactively authenticate v1 metadata. Preview `php artisan security:audit-encrypt` reports counts after verifying the chain; run `php artisan security:audit-encrypt --apply` only through an approved tested operational change with backup/recovery evidence. Record counts before/after, chain result, key custody, redacted command outcome and reviewer. Legacy conversion does not retroactively establish v2 provenance or replace actual database/disk encryption.

Proposed mandatory MFA roles are configurable and include administrators, DG and forensic administrators. Source implements encrypted RFC 6238 TOTP secrets, hashed recovery codes, factor/recovery login, replay protection, account-version session invalidation and idle/absolute expiry. Treat these as code-observed until `AccountSecurityTest`/`TotpServiceTest` and deployment/browser results are attached. Arrange an approved enrollment/recovery process before enabling protected data access, avoid permanent administrator lockout and verify emergency account handling. Users must keep recovery codes offline in approved protected custody; support must not store or send plaintext factors/codes in Git, logs or ordinary chat.

The history scan's SQL matches were strictly decoded as encrypted Laravel session payloads, with no actual JWT established in those candidates. This classification does not establish that historical session exposure was harmless or that no other credentials exist. The database dump contains sensitive historical data; removing it from the current Git index does not remove copies in history, clones or backups. The responsible owner must review restricted historical-data/session exposure and choose an authorized retention/removal/response plan. Rotate or revoke only when a credential exposure is separately confirmed, and retain redacted response evidence. Candidate strings, session payloads and actual credentials must not appear in this pack.

Audit migration rollback after v2 entries requires restoration of a paired application/database backup; do not discard encrypted evidence or integrity metadata. Validate rollback/recovery in isolation before an approved deployment. Current test results used an isolated database; production migrations and data conversion remain unexecuted.
