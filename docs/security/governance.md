# ISMS governance, risk treatment and security policy

Revision 0.1, 2026-10-08. **Proposed policy; organization approval pending.**

## Scope and accountability

Proposed ISMS scope: the people, processes, source code, build/deployment systems, web/API service, React interface, Android client, authentication, evidence uploads, OCR, database, audit history, reports, messaging/SMS providers and backup/recovery services used to operate NCCIA CMS. Hosting locations, managed-provider boundaries, circle offices, endpoints and remote administration must be identified by the sponsor before approving scope. Operational evidence is currently unknown; scope exclusions require a documented rationale.

Protected assets include complainant/accused identity data, case/enquiry records, forensic evidence, judicial documents, authentication factors, keys used for application encryption and document signing, audit records and availability of investigation workflows. Proposed classification: public approved verification responses; internal administrative information; restricted personal/case data; highly restricted forensic evidence and credentials. Business/legal owners must approve classification, retention and permitted disclosures.

| Role | Responsibility | Named assignee / approval |
| --- | --- | --- |
| Accountable sponsor | Approve scope, resources, residual risk and release acceptance | Pending assignment |
| ISMS owner | Maintain risks, SoA, policies, internal audit and management review | Pending assignment |
| Application owner | Deliver secure changes, inventory data flows and patch findings | Pending assignment |
| Security reviewer | Threat model, verification, exception review, incident coordination | Pending assignment |
| Operations owner | Actual server/cloud hardening, backups, monitoring and restore evidence | Pending assignment |
| Privacy/legal owner | Lawful processing, retention, disclosure, supplier/privacy requirements | Pending assignment |
| Independent assessor | Assess controls separately from implementation author | Pending appointment |

Document approval register: sponsor `pending`; ISMS owner `pending`; revision approved `null`; approval date `null`; next review `pending`. Existing developer IP/authorization rules remain separate from organizational security accountability.

## Proposed policy requirements

- Assign unique user accounts and server-enforced roles/circle scope. Approve joiner/mover/leaver changes, revoke promptly on departure, and review privileged access. Record the reason for exceptional global access and any emergency use.
- Require appropriate MFA for privileged and sensitive workflows, protect authentication/recovery secrets, use approved password hashing and regenerate/revoke sessions at relevant authentication/security events. Track requirements through the ASVS checklist rather than assuming a single MFA feature satisfies the chapter.
- Store restricted evidence outside direct web access. Enforce object-level authorization, file-type/content/size limits, malware handling and controlled download. Do not include personal case data or credentials in diagnostic messages.
- Encrypt traffic and approved restricted storage/backup media. Manage keys with separate access, rotation/revocation, recovery and inventory. Production algorithms, TLS settings and key custody require operations evidence.
- Review security-sensitive changes independently when staffing permits. Use protected branches, reproducible lockfiles, dependency/secret scans, security regression tests and vulnerability triage. Emergency changes require retrospective review.
- Collect security/audit events with accurate time, minimum needed detail, access separation, integrity checks, approved retention and alert review. A hash chain without independent anchoring/storage controls does not protect against a database administrator rebuilding the whole chain.
- Maintain tested backups, recovery plans and incident response. Test restoration using an isolated environment and synthetic/redacted records. Do not mark a backup successful based solely on a scheduled job being configured.
- Assess suppliers and hosting responsibilities, keep inventories, patch supported software and train relevant staff. Personnel, physical, legal and procurement controls require organizational evidence.

## Risk method and acceptance

`risk-register.json` is an initial analyst assessment for owner review, not an accepted organizational assessment. Likelihood and impact each use 1–5: likelihood ranges from rare to expected; impact ranges from negligible to severe harm to confidentiality, evidence integrity, availability or legal obligations. Score = likelihood × impact. Proposed thresholds: 1–4 low; 5–9 moderate; 10–14 high; 15–25 critical. Scores are ordinal prioritization, not a probability of breach.

Record asset/data flow, threat, observed condition, existing controls, initial score, treatment, owner, target date, validation evidence and residual score. Initial scores can be estimated; residual scores stay `null` until effectiveness is verified. Critical/high risks block sensitive-data production acceptance unless the sponsor signs a time-bounded exception with compensating controls. Acceptance requires the business risk owner and security reviewer; the implementer cannot approve their own exception.

Proposed finding response targets: critical triage within one day and containment immediately where required; high triage within two working days and fix target within seven days; moderate fix target within thirty days; low in planned maintenance. Confirm capacity/SLAs with the sponsor. A dependency exception records advisory/package/version, exploitability, mitigation, owner, expiry and review; never silently suppress all audit results.

## Statement of Applicability and evidence

`soa-register.json` contains 93 Annex A identifiers as a complete applicability worksheet. Control titles and requirements must be reviewed from an authorized licensed standard; this pack does not reproduce the normative text. For each record, set inclusion/exclusion and justification, link assessed risks, mark implementation state, identify owner and retained evidence. Also evaluate controls outside Annex A required by scope/risk. The worksheet itself is not an approved SoA, and Annex A checks do not replace clauses 4–10.

Clause evidence checklist: organizational context/interested parties/scope (4); leadership/policy/responsibilities (5); risks, treatments and objectives (6); competence, awareness, communication and controlled documents (7); operational planning and risk assessment/treatment (8); monitoring, internal audit and management review (9); corrective action and improvement (10). All formal organizational evidence is pending.

Evidence record minimum: ID, control/requirement, exact scope, commit/build, environment, test/assessment method, result and limitations, timestamp/timezone, verifier, evidence integrity hash or protected repository identifier, retention decision and reviewer. Never commit actual case evidence, production credentials or unredacted incident reports.

Proposed objectives after approval: 100% privileged accounts reviewed each quarter; no unaccepted critical/high findings on release; all approved ASVS requirements verified for release scope; quarterly isolated restoration meets approved RTO/RPO; 100% security events requiring action triaged within the approved target. No baseline result is claimed here.

## Review, audit and improvement

Internal audit programme: independent reviewer samples account provisioning/revocation, representative restricted records and downloads, change approval, scan triage, logging, suppliers and recovery; records evidence, findings and corrective-action owners. Management review considers objectives, incidents, audit findings, risk changes, resources and improvement decisions. Retain signed minutes outside Git with a redacted reference here. Reassess after material deployment changes or incidents. Certification requires a separate competent certification assessment.

Source: [ISO/IEC 27001:2022 official catalogue](https://www.iso.org/standard/27001).
