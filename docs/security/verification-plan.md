# Secure development, review and verification plan

Revision 0.1, 2026-10-08. **Adoption and scoped execution pending.**

This document specifies work and evidence; a checklist entry is not a test result. Use a dedicated test environment and synthetic identities/cases. Active scanning, volume tests and restore exercises require the target owner's authorization and a controlled test window. Scope production testing separately.

## Requirements and development rules

Use the full `asvs-5-checklist.json` inventory. Proposed baseline is all L1/L2 requirements plus risk-selected L3; approve applicability one requirement at a time. OAuth/OIDC, self-contained tokens and WebRTC are not assumed applicable or excluded until the feature inventory is checked. Document endpoint/data/file inventories and accepted sizes/types; update requirements and threats with feature changes.

Developers must enforce server-side object/circle authorization, validate inputs at boundaries, use parameterized queries and structured process arguments, escape output by context, keep restricted storage private, limit parser resources, fail safely and avoid sensitive log content. Security defaults and supported components must be documented. User interfaces are convenience controls; authoritative decisions belong on the server. Add regression tests for actual abuse/fixes, not tests that merely reproduce implementation details.

## Secure code review record

Record PR/commit, affected requirements/ASCs/threats, author, independent reviewer, findings, evidence and disposition. Review must cover:

| Review surface | Questions and evidence |
| --- | --- |
| Routes, controllers, policies | Are authentication, role/circle/object/field permissions applied to read/write/export/file paths? Can query/body fields bypass scope? |
| Authentication/session/recovery | Are throttling, MFA state, factor recovery, session rotation, idle/absolute expiry and revocation enforced server-side? Are secrets hashed/encrypted correctly? |
| Queries/processes/parsers | Does untrusted input reach raw SQL, shell commands, filesystem paths, URL fetches or complex document parsers? Are arguments/limits/network access constrained? |
| Uploads and downloads | Is content checked beyond browser MIME? Are names generated, storage private, scanner failures handled and every retrieval authorized? |
| HTML/PDF/report/mail rendering | Is output encoded for its context? Are active content/remote fetches disabled where needed? Can errors expose private data? |
| Business transactions | Are handoff locks, concurrency, approval and correction paths enforced atomically? Is the audit record correlated to the committed operation? |
| Dependencies/build/configuration | Are lockfiles used and findings triaged? Are actions/components trusted and version-pinned? Are secrets excluded? Are insecure debug/cleartext defaults rejected for release? |
| Logs/privacy/operations | Are events sufficient, access-separated and scrubbed? Does the release have rollback, monitoring, recovery and privacy evidence? |

Disposition states: accepted with evidence; change required; documented scoped exception with risk owner/expiry. No silent waiver. Re-review when security-relevant changes invalidate earlier evidence. Source guidance: [OWASP Developer Guide](https://owasp.org/projects/developer-guide), [OWASP Code Review Guide](https://owasp.org/projects/code-review-guide).

## WSTG v4.2 execution plan

Pin [WSTG v4.2](https://owasp.org/www-project-web-security-testing-guide/v42/) as the initial published testing reference. The project is developing v5.0; do not cite development content as a completed stable assessment. The table selects NCCIA scenarios; the verifier must review the full guide and document additional/excluded cases. IDs below are references to v4.2 testing groups; this table is not a claim that every WSTG scenario has run.

| Group / target | Required test work | Initial evidence candidates / remaining work |
| --- | --- | --- |
| INFO / ingress and assets | Enumerate entry points, roles, transports, versions and data flows; reconcile API/mobile/static/document endpoints | Full endpoint and production inventory pending |
| CONF / host, proxy, storage | Verify TLS/headers/methods/debug configuration, directory listing, direct upload execution, admin/DB exposure and backup files | `SecurityHeaders.php`, `public/.htaccess`; actual host tests pending |
| IDNT / accounts and provisioning | Verify unique identity, role assignment, suspension/revocation and enumeration differences | Suspension regression; complete provisioning/recovery tests pending |
| ATHN / login, MFA, recovery | Rate-limit guessing, MFA bypass/enrollment/recovery, account lockout and transport; inspect factor secret storage | Login code; controlled authentication suite pending |
| ATHZ / every API/file/export | Test unauthorized roles, another circle, guessed IDs, mass assignment, field-level/privilege escalation and direct files | `CircleIsolationTest.php`, `SecurityRegressionTest.php`; remaining endpoints pending |
| SESS / cookies, CSRF and logout | Verify cookie flags, session fixation, CSRF, rotation, idle/absolute timeout, concurrent sessions and password/role change invalidation | Middleware/auth code; browser and API execution pending |
| INPV / forms/imports/query/export | Test SQL/OS/HTML/template injection, traversal, untrusted URLs, file-type spoofing, malformed documents and parser limits | Upload/traversal regression; comprehensive sink review and payload tests pending |
| ERRH / failed requests and services | Ensure safe errors, no stack/config/PII disclosure, scanner/provider/parser failures handled safely | Fault-injection execution pending |
| CRYP / transport/storage/secrets | Evaluate TLS and algorithms, key handling, password hashing, MFA/recovery secrets and restricted backups | Configuration inspection and production cryptographic evidence pending |
| BUSL / case workflows | Verify approvals, handoff locks, repeated/replayed requests, stale updates and atomic audit/business transactions | Security regression business/concurrency tests; full role/process matrix pending |
| CLNT / browser/mobile | Verify XSS/DOM sinks, framing/CSP, mixed content, browser storage, CORS and downloaded active content | Browser/mobile assessment pending |
| API / service interfaces | Assess exposed API-specific features, inventory schemas and GraphQL if present; justify applicability | Feature inventory pending; no automatic exclusions |

For each case record exact scenario ID/version, request/response evidence, role/circle, synthetic dataset, environment/build, expected/actual outcome, verifier/time, issue and retest link. Retain redacted artifacts in restricted storage. Existing tests are partial evidence only; their latest successful run must be attached separately.

## NIST SSDF 1.1 practice mapping

Source: [NIST SSDF publications](https://csrc.nist.gov/Projects/ssdf/publications). Version 1.1 is the final baseline; version 1.2 is listed as an initial public draft at preparation. Track a deliberate draft delta review separately; this table does not assert 1.2 conformance or complete task-level coverage.

| Practice | NCCIA procedure / retained evidence required |
| --- | --- |
| PO.1 | Adopt policy, scope and versioned ASVS/ASC requirements; sponsor approval pending |
| PO.2 | Assign named developer/security/operations/assurance owners; role/training records pending |
| PO.3 | Maintain approved CI/security tools and configuration; review scan quality and coverage; results pending |
| PO.4 | Apply explicit release criteria and time-bounded exceptions in governance/assurance model |
| PO.5 | Separate development/test/production and protect build/admin credentials; environment evidence pending |
| PS.1 | Enforce repository/access protections and code review; verify actual host/repository protections, not just CODEOWNERS text |
| PS.2 | Produce and verify artifact hashes/signatures/provenance; exact release evidence pending |
| PS.3 | Archive build/source/lockfiles/SBOM/scan evidence and rollback artifacts with access/integrity controls |
| PW.1 | Maintain application threat model/data flows and select risk-based controls |
| PW.2 | Independent architecture/requirements review before material security changes |
| PW.4 | Approve reused dependencies, supported versions, suppliers and exception rationale |
| PW.5 | Apply developer/code review rules and regression tests to security-sensitive changes |
| PW.6 | Review build/runtime configuration and remove insecure release/debug defaults; inspect APK release behavior |
| PW.7 | Combine manual sink/authorization review with code/secret/dependency scans; retain findings |
| PW.8 | Execute ASVS/WSTG regression, browser/API and independent application tests; retain scoped results |
| PW.9 | Verify security defaults, deployment baseline, private storage, headers and account setup |
| RV.1 | Maintain vulnerability intake and recurring dependency/host monitoring with owners |
| RV.2 | Prioritize and remediate findings against approved response targets; verify fixes and exceptions |
| RV.3 | Record root cause, affected variants, regression coverage and prevention action |

SSDF 1.2 delta register: owner `pending`; authoritative draft/version `SP 800-218 Rev.1 initial public draft`; review result `null`; changed tasks/control mappings `pending`; adoption decision `pending`. Review changes with the publisher's current text before claiming coverage.

## OWASP SAMM v2 preliminary baseline

Use [SAMM model and assessment](https://owaspsamm.org/model/) with organization interviews and evidence. All maturity scores are **unassessed (`null`)**; source review alone is insufficient to assign a maturity level. Proposed first target is assess/achieve Level 1 practices, with risk-based higher targets approved by the sponsor.

| Function | Practices | Existing starting point / missing organizational evidence |
| --- | --- | --- |
| Governance | Strategy & Metrics; Policy & Compliance; Education & Guidance | Proposed governance/model; approved strategy, compliance obligations, staff training and sustained metrics pending |
| Design | Threat Assessment; Security Requirements; Secure Architecture | Initial threats/ASVS catalogue; owner review, design decisions and recurring use pending |
| Implementation | Secure Build; Secure Deployment; Defect Management | Build scripts/repository controls; verified secure pipeline, deployment and finding lifecycle pending |
| Verification | Architecture Assessment; Requirements-driven Testing; Security Testing | Regression tests and plan; independent design review, requirement coverage and execution results pending |
| Operations | Incident Management; Environment Management; Operational Management | Proposed runbooks; actual monitoring, environment baselines, exercises and service operations records pending |

Assessment record minimum: practice/stream/question/version, interviewee/assessor, evidence and date, actual score rationale, target/owner/due date. A documentation pack does not establish organizational maturity.

## Release verification record

Before release, record commit and artifact hashes, actual deployment environment, approved ASVS target/applicability, executed tests/scans, benchmark/manual findings, backup/rollback evidence, open issues and accepted exceptions, independent reviewer and sponsor decision. Attach CI job URL/results separately. Result remains `pending-verification` until these fields are complete.
