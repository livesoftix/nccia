# Organization normative framework and application security process

Revision 0.1, 2026-10-08. **Proposed NCCIA framework inspired by ISO/IEC 27034; normative conformance and owner approval pending.**

## Organization normative framework (ONF)

NCCIA CMS is treated as a sensitive case/evidence application. The ONF comprises the governance/risk method, approved roles, application data flows, security requirements, ASC catalogue, lifecycle gates, verification evidence and exception process in this pack. The application-specific framework selects controls based on assets and risks, records any additional controls and retains release verification decisions. Organization adoption and review against licensed ISO/IEC 27034-1/-2/-3/-5/-7 text are still required.

Normative sources and precedence: applicable law and approved organizational obligations (privacy/legal owner review pending); approved organization policy; approved application risk treatment; version-pinned ASVS requirements; project procedures. Resolve conflicting requirements through the security and business owners; retain the decision. Third-party controls or cloud service assurances are not assumed effective without scope-matched evidence.

Proposed assurance target: verify all adopted L1/L2 ASVS requirements and risk-selected L3 requirements for the exact release. Each ASC specifies security activity, verification activity, responsible roles, evidence and acceptance condition. A release has an *actual verified scope* distinct from its *target scope*. Do not claim that a risk score predicts ISO assurance.

## Application security management process (ASMP)

| Stage / owner role | Required output and exit criterion |
| --- | --- |
| Identify context / application + privacy owners | Asset/data-flow inventory; threat actors; scope; classification/retention; approved requirements and assurance target |
| Evaluate risks / security + business owners | Review `risk-register.json`; prioritize threats; approve treatments and time-bounded exceptions |
| Select and tailor / security reviewer | Select ASCs and ASVS IDs; record applicability/exclusions; assign verification and deployment responsibilities |
| Design and implement / developer | Review data flow/architecture; implement controls; independent review; tests/scans on exact change |
| Verify / independent verifier | Execute verification plan; record result, evidence, limitations and environment; retest fixes |
| Release / sponsor + operations | No unaccepted blockers; approved deploy/rollback/backup; deployment verification; immutable release record |
| Operate and improve / operations + ISMS owners | Alerts, access reviews, vulnerability fixes, incident lessons, restore exercises and reassessment |

## Data flow and trust boundaries

Observed application components: Laravel API/session service (`routes/api.php`, authentication/controllers), React client (`react-app/src/api.js`), mobile Capacitor client (`mobile-app`), database/models, server storage/download/report generation, OCR helper processes and outbound SMS/email integrations. Actual reverse proxy, database/storage encryption, cloud topology and monitoring are not established by source inspection.

Boundary B1: browser/mobile to public ingress; B2: authenticated user to role/circle/object authorization; B3: upload/metadata to filesystem, parser, OCR/PDF engine; B4: application to database and audit trail; B5: application to mail/SMS and other external providers; B6: developer/CI to deployment artifacts and production administration; B7: production data to backup/DR. Restricted data must not cross a boundary solely on a client-provided role, circle, filename or MIME header.

## Threat model and initial treatments

| ID / boundary | Abuse scenario | Control and required verification |
| --- | --- | --- |
| T01 / B1 | Credential stuffing, recovery abuse, stolen session | ASC-AUTH; verify rate limits, MFA/recovery, cookie flags, rotation, expiry and logout/revocation |
| T02 / B2 | Guess IDs to read/modify another circle's records | ASC-AUTHZ; positive/negative role × circle × object tests on every resource/action |
| T03 / B2 | Crafted designation/role/assignment elevates privileges | ASC-AUTHZ; server allowlists, administrative authorization and escalation regression tests |
| T04 / B3 | Executable/polyglot upload, spoofed MIME, traversal | ASC-FILE; file signature/extension/size checks, private storage, unsafe-name tests and controlled download |
| T05 / B3 | Malicious document exploits parser or exhausts CPU/storage | ASC-FILE/ASC-IMPORT; quarantine/scanner, parser isolation, time/resource limits, concurrency and failure tests |
| T06 / B1-B3 | HTML/SQL/OS injection via case fields or exports | ASC-INPUT; parameterized queries/commands, context escaping, hostile payload review and browser/API tests |
| T07 / B4 | Alter/delete audit evidence or rebuild its chain | ASC-AUDIT; chain verification, restricted writer/admin access, independent checkpoint/retention and recovery tests |
| T08 / B2-B4 | Unauthorized edit after handoff or stale overwrite | ASC-INTEGRITY; lifecycle locks, approved correction trail, concurrency tests and transactional integrity |
| T09 / B5 | Sensitive data sent to wrong external recipient or logged | ASC-DATA; provider/recipient validation, minimal disclosure, scrubbed logs and supplier/privacy review |
| T10 / B6 | Compromised dependency, secret in Git, unsafe build/update | ASC-SUPPLY; pinned lockfiles, SCA/secret scans, review, artifact provenance and supported runtime |
| T11 / B7 | Ransomware/loss; backups cannot restore consistently | ASC-RECOVERY; access-separated encrypted backups, isolated restore exercise and approved RTO/RPO |
| T12 / B1-B7 | Host misconfiguration, public DB/storage, insecure TLS | ASC-DEPLOY; exact-host benchmarks, segmentation, TLS/cloud configuration and independent assessment |

All threats remain open until scoped evidence is reviewed. Local tests cover parts of T02/T03/T04/T07/T08; that does not close all variants or deployment threats.

## Application security control catalogue

| ASC | Activity / responsible role | Verification and acceptance | Initial source evidence |
| --- | --- | --- | --- |
| ASC-AUTH | Developer implements authentication/MFA/session/recovery requirements | Security reviewer tests enrollment, recovery, expiry, throttling and bypasses; ASVS chapter 6/7 requirements evaluated individually | `app/Http/Controllers/Auth/LoginController.php`; `app/Http/Controllers/Auth/MfaController.php`; `app/Http/Middleware/EnforceAccountSecurity.php`; `app/Services/TotpService.php`; `app/Services/MfaService.php`; `tests/Feature/AccountSecurityTest.php` and `tests/Unit/TotpServiceTest.php` |
| ASC-AUTHZ | Developer enforces role, circle and object authorization server-side | Authorized access succeeds; every forbidden role/circle/action fails, including export/file paths | `app/Policies`; `tests/Feature/CircleIsolationTest.php`; `tests/Feature/SecurityRegressionTest.php` |
| ASC-FILE | Developer + operations define and enforce upload/download safety | Allowed files work; executable/spoofed/traversal/oversized inputs fail; deployment denies direct storage execution/access | `app/Http/Requests`; `app/Services/SecureFileService.php`; `app/Http/Controllers/SecureFileController.php` |
| ASC-INPUT | Developer parameterizes queries/commands and escapes contexts | Review every query/command/rendering sink; hostile payloads cannot alter execution or rendered context | Laravel validation/controllers and React views; comprehensive review pending |
| ASC-IMPORT | Developer + operations constrain OCR/PDF processing | Time/memory/output limits and quarantine; malformed files fail without data leak/service loss | `scripts/nccia_pdf_extract.py`; import controller; runtime validation pending |
| ASC-AUDIT | Developer + operations retain protected audit evidence | Detect modified/deleted/reordered records; verify independent checkpoints, writer separation and monitoring | `app/Services/AuditLedgerService.php`; v2 encrypted details/metadata MAC/authenticated head; security regression tests; legacy conversion and external anchoring pending |
| ASC-INTEGRITY | Developer enforces handoff/concurrency/business constraints | Locks and approved corrections tested; stale updates rejected; no partial transactions | Model policies/locking traits; security regression tests |
| ASC-DATA | Privacy + developer + operations minimize/protect restricted data | Review classifications, output fields, logs, retention, encryption/key custody and recipient disclosures | Documentation framework; actual retention/storage evidence pending |
| ASC-SUPPLY | Developer + CI owner controls build/dependencies/secrets | No unaccepted findings; exact-lockfile build, retained SBOM/scan/provenance and signed release decision | `.github/workflows`; package/composer lockfiles; CI results pending |
| ASC-RECOVERY | Operations maintains recovery capability | Isolated restore succeeds against approved RTO/RPO with consistency/integrity evidence | Infrastructure proposal; execution pending |
| ASC-DEPLOY | Operations hardens host, database, web ingress and cloud | Approved benchmark/profile scans and manual checks have no unaccepted findings | `operations-runbook.md`; actual production evidence pending |

Catalogue mappings are proposed local crosswalks. Code-observed means implementation exists for investigation, not that all ASC acceptance criteria are met.

## Case studies for ISO 27034-6 guidance

Case CS-01: cross-circle ID guessing. Context: two circles with separate officers. Activity: query another circle's complaint, related file and QR/export endpoints. Expected: denial for every scoped object and successful legitimate access. Partial evidence: `CircleIsolationTest.php` and IDOR regression tests. Missing: full endpoint inventory and independent execution record.

Case CS-02: evidence upload. Context: an authenticated operator uploads an image/document. Activity: submit permitted image, renamed executable, misleading MIME and traversal/oversize inputs; attempt direct/public download. Expected: safe acceptance or rejection, private authorization-controlled storage, no execution. Partial evidence: regression tests. Missing: scanner/parser operational results and OS/web-server verification.

Case CS-03: evidential audit tampering. Context: a record changes and a malicious administrator modifies audit storage. Activity: test chain corruption/deletion, independent checkpoint mismatch and recovery. Expected: detection and alert with retained evidence. Partial evidence: audit ledger regression. Missing: independently anchored checkpoints and operations exercise record.

These are NCCIA-specific proposed case studies; they do not reproduce or claim to implement the official ISO case-study text.

## XML exchange and assurance prediction boundary

`controls.xml` validates against `controls.xsd` in namespace `urn:nccia:security:asc:1`. It captures the project's control objective, security/verification activities, accountable roles, evidence and status. It is deliberately labelled a **custom project format**. Interoperability/conformance with ISO/IEC TS 27034-5-1:2018 cannot be claimed until the licensed normative schema is obtained, a mapping is reviewed and instances validate against it. Do not rename the namespace to imply ISO endorsement.

`assurance-model.json` records measurement definitions, uncollected baseline and release gates. It does not substitute predicted assurance for required verification. Any future Prediction Application Security Rationale (PASR) needs owner-approved scope, comparable historical evidence, explicit uncertainty, independent validation and review against ISO/IEC 27034-7 before replacing an ASC activity.

Sources: [ISO 27034-3 official browsing platform](https://www.iso.org/obp/ui#iso:std:iso-iec:27034:-3:ed-1:v1:en), [TS 27034-5-1](https://www.iso.org/standard/67741.html), [ISO 27034-7](https://www.iso.org/standard/66229.html). Confirm licensed clause-level mappings during adoption.
