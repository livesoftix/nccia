# NCCIA CMS security adoption and evidence pack

Prepared: 2026-10-08 (Asia/Karachi). Status: **draft for organization adoption; verification incomplete**.

This pack turns the requested standards into a reviewable security programme. Written policies, source changes and passing local tests are different evidence types. None of them establishes ISO certification or full conformance by itself. Organization approval, actual production configuration and independent assessments must be recorded before any compliance claim.

## Working documents

| File | Purpose |
| --- | --- |
| [standards-register.json](standards-register.json) | All 18 requested items, corrected references, sources and acceptance evidence |
| [governance.md](governance.md) | Proposed ISMS scope, responsibilities, policy, risk treatment and evidence rules |
| [risk-register.json](risk-register.json) | Initial application-specific risks, treatment tasks and pending owner acceptance |
| [soa-register.json](soa-register.json) | All 93 Annex A reference identifiers; applicability and effectiveness require licensed-standard review |
| [application-security-framework.md](application-security-framework.md) | ISO 27034-inspired ONF, application process, threats, control catalogue and case studies |
| [controls.xml](controls.xml), [controls.xsd](controls.xsd) | Validatable project-specific security-control exchange; **not** the official ISO schema |
| [verification-plan.md](verification-plan.md) | Secure development/review rules, WSTG plan, SSDF mapping and SAMM baseline |
| [asvs-5-checklist.json](asvs-5-checklist.json) | Complete official ASVS 5.0.0 requirement inventory with local verification fields |
| [operations-runbook.md](operations-runbook.md) | CIS, CSA, NIST and production hardening evidence, backup/restore and incident procedures |
| [assurance-model.json](assurance-model.json) | Recorded evidence measurements and release decisions; no unvalidated assurance prediction |
| [validation-evidence.json](validation-evidence.json) | Actual local test/build/scan and focused-review results, with execution scope and limits |

ASVS requirement text is sourced from OWASP, licensed CC BY-SA 4.0; see [NOTICE.md](NOTICE.md). Other local documents are original project procedures and provisional mappings. The local crosswalks are not publisher-endorsed or exhaustive equivalence mappings.

## Adoption and evidence workflow

1. The accountable sponsor appoints named ISMS, security, application, operations, privacy and independent assurance owners. Record scope, approval date and document revision in `governance.md`.
2. Owners review the risk register and every Annex A applicability decision using a licensed copy of ISO/IEC 27001:2022. Approve the risk treatment plan and Statement of Applicability. No item is excluded simply because evidence is unavailable.
3. Select and approve ASVS 5.0.0 L2 as the initial proposed target, plus L3 controls required by risk assessment for privileged access, case evidence and forensic workflows. Level selection is pending sponsor approval; no level has been achieved.
4. Developers link each implementation to an ASC, requirement and threat. Verifiers attach results for the exact commit, build, environment and date. Code references start investigation; they do not automatically satisfy requirements.
5. Operations validate deployment controls on the actual OS, DBMS, application server and cloud provider. The existing infrastructure specification remains a proposed design until implemented configuration and checks are attached.
6. An independent reviewer records findings, retest results and residual risks. The sponsor records release authorization separately from any certification decision.

Status vocabulary: `pending-owner-approval`, `pending-verification`, `code-observed`, `verified`, `failed`, `not-applicable-approved`. A `verified` record requires evidence, verifier and date. `not-applicable-approved` requires a reason and approver. Empty results and unknown applicability count as unverified. Store sensitive reports in an access-controlled evidence repository, not Git; these files contain only redacted identifiers/links.

Local validation recorded so far: isolated PHPUnit suite passed 66 tests / 214 assertions on PHP 8.3.33 and Laravel 12.69.3; React production build passed; frontend lint exited 0 with existing warnings. See the validation record for dependency/static/secret checks and their limits. These results do not complete the 345 ASVS requirements or establish production/organizational control effectiveness.

Proposed operating cadence: review risks quarterly and after material incidents/architecture changes; review privileged access quarterly; review security alerts daily; run dependency/secret/code checks on every pull request; perform an independent application assessment before initial sensitive-data production use and after major security changes; conduct annual ISMS internal audit and management review. Owners must adopt or revise these proposals.

## What remains external to the repository

Named-owner signatures, organization-wide asset inventory, legal/privacy obligations, personnel and physical-security records, operational SLAs, supplier contracts, production network/TLS/backup evidence, licensed ISO clause/schema review and independent audit/certification have not been supplied. These are tracked explicitly instead of marked complete.
