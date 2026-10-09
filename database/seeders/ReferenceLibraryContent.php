<?php

namespace Database\Seeders;

/**
 * Full reference-library text. Re-run: php artisan reference:ensure
 */
class ReferenceLibraryContent
{
    public static function laws(): array
    {
        return [
            [
                'title' => 'Prevention of Electronic Crimes Act 2016',
                'act_name' => 'PECA 2016',
                'year' => '2016',
                'description' => <<<'TXT'
PRIMARY LEGISLATION — Cyber offences in Pakistan.

KEY OFFENCES (selected)
• Sec. 3 — Unauthorized access to information system or data
• Sec. 4 — Unauthorized copying or transmission of data
• Sec. 5 — Interference with information system or data
• Sec. 6 — Unauthorized use of identity information
• Sec. 7 — Electronic forgery
• Sec. 8 — Electronic fraud
• Sec. 9 — Making, obtaining, or supplying device for use in offence
• Sec. 10 — Unauthorized issuance of SIM, etc.
• Sec. 11 — Cyber terrorism
• Sec. 12 — Hate speech (electronic)
• Sec. 13 — Offences against dignity of natural person
• Sec. 14 — Offences against modesty of natural person and minor
• Sec. 21 — Cyber stalking
• Sec. 22 — Spamming
• Sec. 29 — Power to investigate, search and arrest
• Sec. 30 — Power to require production of data
• Sec. 41 — Admissibility of electronic evidence

INVESTIGATION POWERS
NCCIA / authorised officers may investigate, seize electronic evidence, require data production from service providers, and arrest without warrant where offence is cognizable.

PENALTIES
Ranges from fine to imprisonment up to 14 years depending on offence (e.g. cyber terrorism).

NCCIA APPLICATION
Used for FIR drafting, charge framing, verification reports, and court submissions. Always cite specific PECA section with facts in case diary.
TXT,
            ],
            [
                'title' => 'Pakistan Penal Code (Cyber Offences)',
                'act_name' => 'PPC 1860',
                'year' => '1860',
                'description' => <<<'TXT'
SUPPLEMENTARY PENAL PROVISIONS — Often charged alongside PECA.

COMMONLY APPLICABLE SECTIONS
• Sec. 403–409 — Criminal misappropriation / breach of trust (online financial fraud)
• Sec. 415–420 — Cheating and dishonest inducement (phishing, OTP fraud)
• Sec. 463–477 — Forgery and forged documents (fake profiles, forged e-documents)
• Sec. 499–502 — Defamation (when not exclusively under PECA Sec. 20)
• Sec. 503–510 — Criminal intimidation (extortion via electronic means)
• Sec. 378–382 — Theft (device theft, wallet hacking)

JOINDER WITH PECA
Investigation Officer may add PPC sections where conduct falls outside exclusive PECA wording but facts support classical penal offences.

EVIDENCE
Electronic records admitted per QSO 1984 read with PECA Sec. 41; maintain chain of custody per NCCIA forensic SOP.
TXT,
            ],
            [
                'title' => 'Electronic Transactions Ordinance 2002',
                'act_name' => 'ETO 2002',
                'year' => '2002',
                'description' => <<<'TXT'
LEGAL RECOGNITION OF ELECTRONIC RECORDS

KEY PROVISIONS
• Sec. 2(1) — Definitions: electronic record, digital signature, information system
• Sec. 5 — Legal recognition of electronic forms (writing, signature, record)
• Sec. 6 — Use of electronic records in government and commerce
• Sec. 7–10 — Digital signature accreditation and presumption of integrity
• Sec. 36 — Attribution of communication to originator

NCCIA USE
• Validates portal-generated acknowledgments, SMS logs, and system audit trails as records
• Supports admissibility of CMS exports, email headers, and server logs in court
• Basis for requesting certified electronic records from banks and telcos

LIMITATION
Does not create substantive cyber offences — use with PECA for criminal matters.
TXT,
            ],
            [
                'title' => 'Investigation for Fair Trial Act 2013',
                'act_name' => 'IFTA 2013',
                'year' => '2013',
                'description' => <<<'TXT'
FAIR TRIAL & INVESTIGATION TIMELINES

KEY REQUIREMENTS
• Investigation to be completed without unnecessary delay
• Accused rights: legal counsel, medical examination, production before magistrate within 24 hours of arrest
• Search and seizure subject to legal safeguards
• Special provisions for electronic surveillance and data collection (with judicial oversight where required)

NCCIA COMPLIANCE
• Record date/time of arrest, seizure memo, and 173 report submission in CMS case file
• Enquiry Officer diary and IO activities must show continuous investigation steps
• Delays beyond statutory limits require written reasons in case diary and supervisor endorsement

DOCUMENTATION IN CMS
Arrest memo, recovery memo, bail status, challan/discharge — all linked to live case timeline (read-only history after handover).
TXT,
            ],
            [
                'title' => 'Qanoon-e-Shahadat Order 1984 (Electronic Evidence)',
                'act_name' => 'QSO 1984',
                'year' => '1984',
                'description' => <<<'TXT'
RULES OF EVIDENCE — Including electronic records.

RELEVANT ARTICLES
• Art. 2(1)(c) — Document includes electronic record
• Art. 73 — Proof of electronic record (certificate, integrity, manner of production)
• Art. 164 — Court may inspect electronic evidence
• Art. 165 — General power to call for documents including digital media

ADMISSIBILITY CHECKLIST (NCCIA)
1. Forensic image with verified hash (SHA-256)
2. Chain of custody form from seizure to lab to court
3. Expert certificate under forensic report SOP
4. Printout or certified copy where original device not produced
5. Service provider certificate for CDR/IPDR where applicable

REJECTION RISKS
Tampered media, missing hash, broken custody chain, or unsigned forensic report — legal branch must review before challan.
TXT,
            ],
        ];
    }

    public static function rules(): array
    {
        return [
            [
                'title' => 'NCCIA Case Registration Rules 2024',
                'category' => 'Procedural',
                'effective_date' => '2024-01-15',
                'description' => <<<'TXT'
RULE 1 — INTAKE CHANNELS
Complaints accepted via: (a) CMS online portal, (b) walk-in at circle office, (c) official email, (d) referral from FIA/other agencies, (e) helpline escalation.

RULE 2 — MANDATORY FIELDS
Tracking number, complainant CNIC/contact, offence category, incident date, jurisdiction circle, and narrative minimum 100 characters.

RULE 3 — ACKNOWLEDGMENT
SMS acknowledgment within 2 hours of registration; tracking number on all printouts.

RULE 4 — SCRUTINY
Circle Incharge or Moharrar completes scrutiny within 48 hours: accept, return for correction, or reject with reason.

RULE 5 — ASSIGNMENT
Accepted complaints routed to Verification or Enquiry within 24 hours of scrutiny approval.

RULE 6 — AUDIT
All status changes logged in system; manual register entries prohibited except backup outage.
TXT,
            ],
            [
                'title' => 'Complaint Processing & Triage Guidelines',
                'category' => 'Operational',
                'effective_date' => '2024-02-01',
                'description' => <<<'TXT'
TRIAGE LEVELS
• P1 CRITICAL — Child abuse material, live threat to life, ongoing financial drain (immediate duty officer alert)
• P2 HIGH — Significant financial loss (> PKR 500,000), identity takeover, corporate breach
• P3 STANDARD — General fraud, harassment, defamation
• P4 LOW — Information requests, resolved disputes, duplicate entries

PROCESS
1. Auto-suggest level from offence type and keywords
2. Supervisor may upgrade/downgrade with written reason
3. P1/P2 appear on dashboard red queue
4. Duplicate detection: match CNIC + platform + date window

ROUTING
Verification → VO wing | Investigation-ready → EO | Legal opinion → Legal branch | Out of mandate → referral letter
TXT,
            ],
            [
                'title' => 'Digital Evidence Handling & Chain of Custody',
                'category' => 'Technical',
                'effective_date' => '2024-03-10',
                'description' => <<<'TXT'
SEIZURE
• Use seizure memo (CMS template) listing device, serial, condition, seals
• Photograph scene and device labels; two independent witnesses where possible
• Power off mobile devices using Faraday bag when live data risk exists

CUSTODY
• Every transfer logged: from IO → Malkhana → Forensic Lab → Court
• Signatures on physical chain-of-custody form + CMS evidence module entry
• No personal copies on USB; work only on forensic workstation

STORAGE
• Malkhana climate-controlled locker; evidence tag matches CMS ID
• Retention until case disposed + appeal period or court order

COURT PRODUCTION
Produce forensic image + expert report; original device only if magistrate orders
TXT,
            ],
            [
                'title' => 'Forensic Examination & Laboratory Procedures',
                'category' => 'Technical',
                'effective_date' => '2024-04-05',
                'description' => <<<'TXT'
LAB INTAKE
1. Receive request from IO with FIR/enquiry reference
2. Verify custody form and hash of source media
3. Assign examiner; target turnaround: 15 working days (complex: 30 with AD approval)

EXAMINATION TYPES
• Disk / mobile imaging (FTK / Cellebrite per licence)
• Memory and live capture (documented exception to power-off rule)
• Network logs, email parsing, cryptocurrency tracing (specialist queue)

REPORT
Structured report: scope, tools, findings, expert opinion, annex hashes
Peer review by senior forensic officer before release

QUALITY
Annual tool validation; examiner certification records on file
TXT,
            ],
            [
                'title' => 'Enquiry & Investigation Timelines',
                'category' => 'Procedural',
                'effective_date' => '2024-02-20',
                'description' => <<<'TXT'
PRELIMINARY ENQUIRY — 14 days (extendable once by Circle Incharge, 14 days)
FULL INVESTIGATION — Per IFTA / magistrate orders; internal target 90 days for standard cyber fraud

MILESTONES (CMS)
• Day 0 — Assignment to EO/IO
• Day 7 — First diary entry or status update mandatory
• Day 14 — Enquiry report or progress note
• Day 30 — Supervisor review for open investigations
• Day 60 — Warning flag on dashboard if no challan/discharge plan

ESCALATION
Overdue cases appear on Circle Incharge and DG analytics; monthly DO letter includes delay statistics
TXT,
            ],
            [
                'title' => 'Verification Report Guidelines',
                'category' => 'Operational',
                'effective_date' => '2024-03-01',
                'description' => <<<'TXT'
REPORT STRUCTURE
1. Reference & subject
2. Documents/accounts verified
3. Methodology (OSINT, field visit, telco/bank letter)
4. Findings (factual, no legal conclusion unless qualified)
5. Annexes (screenshots, certificates)
6. VO signature + supervisor approval

STANDARDS
• Neutral language; cite sources with date/time
• Redact victim PII in copies sent to third parties
• Upload PDF to CMS within 24 hours of approval

WORKFLOW
Draft → Peer review (optional) → Circle Incharge approve → Release to requesting authority + SMS to complainant if policy applies
TXT,
            ],
            [
                'title' => 'IO Access & Data Confidentiality Rules',
                'category' => 'Administrative',
                'effective_date' => '2024-04-15',
                'description' => <<<'TXT'
ACCESS LEVELS
• IO sees only assigned cases and inherited process history (complaint → VO → EO)
• Cannot edit/delete inherited records; may add arrests, activities, diary entries
• Circle Incharge / Admin may unlock saved diary with audit reason

CONFIDENTIALITY
• No screenshots to personal devices
• No disclosure of investigation data on social media
• VPN and 2FA mandatory for remote access

BREACH
Report to admin within 1 hour; account suspension pending inquiry
TXT,
            ],
            [
                'title' => 'Court Case Management & Hearing Tracking',
                'category' => 'Legal',
                'effective_date' => '2024-05-01',
                'description' => <<<'TXT'
REGISTRATION
Legal branch opens court case linked to FIR/DAC file; enter court name, case number, judge, offences charged.

HEARINGS
Before each date: assign attending officer, prepare case bundle
After hearing: outcome (adjourned, evidence recorded, charge framed, acquittal, conviction), next date, remarks

DOCUMENTS
Upload orders, judgments, legal opinions; QR-verified exports where available

REPORTS
Monthly pending trials report to DG; integration with DO letter statistics
TXT,
            ],
            [
                'title' => 'Social Media Monitoring & Cyber Patrolling',
                'category' => 'Operational',
                'effective_date' => '2024-06-01',
                'description' => <<<'TXT'
SCOPE
Proactive monitoring for PECA-violative content, impersonation of NCCIA/government, and coordinated fraud campaigns.

PROTOCOL
1. OSINT tools only on authorised workstations
2. Screenshot with URL, timestamp, account metadata
3. Triage panel daily at 10:00; escalate P1 content immediately
4. Content preservation notice to platform where applicable
5. Convert actionable intelligence to formal complaint/FIR

RECORDS
Patrol log in CMS; no informal Excel trackers
TXT,
            ],
        ];
    }

    public static function sops(): array
    {
        return [
            [
                'title' => 'Complaint Receipt & Acknowledgment',
                'department' => 'Front Desk / Operations',
                'version' => '1.3',
                'effective_date' => '2024-01-15',
                'description' => <<<'TXT'
PURPOSE
Standard procedure for receiving cyber crime complaints and issuing official acknowledgment via CMS.

SCOPE
Moharrar, operators, front desk — all intake channels (walk-in, portal, email, referral).

PROCEDURE
1. Verify complainant identity (CNIC / passport) and contact number
2. Confirm matter falls within NCCIA jurisdiction; if not, issue referral template
3. CMS → Complaints → Register New Complaint
4. Complete mandatory fields: offence type, incident date, platform/URL, loss amount, circle
5. Attach available evidence (screenshots, FIR copy, bank statements)
6. Submit — system generates Tracking Number (NCCIA-YYYY-XXXXX)
7. Print acknowledgment slip with tracking number and helpline
8. System sends bilingual SMS acknowledgment automatically (check SMS Log)
9. Set source channel and priority flag if P1/P2 triage criteria met
10. Forward to Scrutiny queue for Circle Incharge / Moharrar review within 24 hours

URGENT (P1)
Child safety, live threat, active account drain — mark PRIORITY and notify duty Circle Incharge immediately by phone + CMS note.

RECORDS
CMS complaint record (audit log), SMS log entry, optional scanned acknowledgment scan.

REFERENCES
NCCIA Case Registration Rules 2024 | PECA Sec. 29–30
TXT,
            ],
            [
                'title' => 'Digital Evidence Acquisition & Imaging',
                'department' => 'Forensic Lab',
                'version' => '2.0',
                'effective_date' => '2024-03-10',
                'description' => <<<'TXT'
PURPOSE
Forensically sound acquisition of digital evidence for investigation and court.

EQUIPMENT
Write-blocker, forensic workstation, Faraday bag, labels, seals, photography kit.

PROCEDURE — STORAGE DEVICES
1. Document device state (on/off, damage) in seizure memo
2. Connect via hardware write-blocker only
3. Capture BIOS/serial visible in photo
4. Create bit-for-bit image (E01/ AFF) using approved tool
5. Generate SHA-256 hash of source and image; both must match verification
6. Store image on WORM or access-controlled server; original sealed in Malkhana

PROCEDURE — MOBILE
1. If live extraction required, document justification; else power off in Faraday bag
2. Use licensed mobile forensic tool; record tool version in case notes
3. Export logical/full file system per device capability
4. Hash exports; link to CMS evidence ID

PACKAGING
Tamper-evident seal, evidence tag, custody form signed at each handover.

DELIVERABLES
Image file, hash certificate, acquisition report attached to forensic request in CMS.
TXT,
            ],
            [
                'title' => 'Enquiry Officer Case Assignment',
                'department' => 'Operations',
                'version' => '1.1',
                'effective_date' => '2024-02-01',
                'description' => <<<'TXT'
PURPOSE
Fair and timely assignment of enquiries to Enquiry Officers (EO).

CRITERIA
• Workload balance (open enquiries per officer)
• Expertise (financial fraud, social media, dark web)
• Jurisdiction (circle / zone)
• Conflict check — EO not related to parties

PROCEDURE
1. Circle Incharge reviews triaged queue daily
2. CMS → Enquiries → Assign Officer; select EO and set target date
3. EO receives dashboard notification
4. EO must acknowledge assignment within 24 hours (open case record)
5. First diary entry within 7 days
6. Reassignment requires Circle Incharge approval + reason in audit log

REPORTING
Weekly open enquiry report to AD; delays > 14 days flagged red on analytics
TXT,
            ],
            [
                'title' => 'Verification Report Submission & Approval',
                'department' => 'Verification Wing',
                'version' => '1.3',
                'effective_date' => '2024-04-01',
                'description' => <<<'TXT'
PURPOSE
End-to-end verification report workflow in CMS.

PROCEDURE
1. VO accepts assignment from verification queue
2. Conduct verification per plan (documents, field visit, third-party letters)
3. CMS → Verifications → Report → Draft sections per template
4. Attach annexures (PDF/images); redact victim PII where required
5. Submit for review — status: Pending Approval
6. Circle Incharge or designated reviewer approves or returns with comments
7. On approval: PDF locked; requesting authority and complainant notified per policy
8. SMS trigger logged in SMS Log module

TIMELINE
Standard: 7 working days | Complex: 14 days with extension request

QUALITY
Factual findings only; legal conclusions reserved for legal branch unless standard template clause used.
TXT,
            ],
            [
                'title' => 'Court Hearing Attendance & Reporting',
                'department' => 'Legal Wing',
                'version' => '1.0',
                'effective_date' => '2024-05-15',
                'description' => <<<'TXT'
PURPOSE
Consistent court representation and hearing documentation.

BEFORE HEARING
1. Legal officer reviews case file, forensic report, and witness list
2. Prepare bundle: FIR, challan, certificates, expert summary
3. CMS → Court Cases → Upcoming Hearings — confirm attendance

AT COURT
4. Record proceedings: witnesses examined, exhibits marked, orders passed
5. Obtain signed copies of orders same day where possible

AFTER HEARING
6. CMS entry within 24 hours: outcome, next date, remarks
7. Upload order/judgment scan
8. Notify IO/Circle Incharge if further investigation ordered

ESCALATION
Contempt, stay, or acquittal — immediate briefing note to Circle Incharge and Legal AD
TXT,
            ],
            [
                'title' => 'Forensic Report Generation',
                'department' => 'Forensic Lab',
                'version' => '2.1',
                'effective_date' => '2024-06-01',
                'description' => <<<'TXT'
PURPOSE
Standard format for forensic examination reports admissible in court.

REPORT SECTIONS
1. Case reference & request date
2. Items received (with custody IDs)
3. Tools & versions used
4. Examination steps (reproducible)
5. Findings (artifacts, chats, files recovered)
6. Expert opinion (limited to technical conclusions)
7. Hash values annex
8. Examiner name, designation, signature
9. Peer reviewer signature (mandatory for major cases)

RELEASE
Upload signed PDF to CMS; IO notified; original available for court production

TURNAROUND
Target 15 working days; delay requires written reason to Circle Incharge
TXT,
            ],
            [
                'title' => 'Inter-Agency Coordination & Information Sharing',
                'department' => 'Administration',
                'version' => '1.0',
                'effective_date' => '2024-07-01',
                'description' => <<<'TXT'
PURPOSE
Secure coordination with banks, PTA, FIA, INTERPOL, and social platforms.

PROCEDURE
1. Request initiated in CMS with legal basis (PECA Sec. 30, court order, MoU)
2. Admin / Legal drafts official letter on letterhead
3. DG / authorised signatory approval for external disclosure
4. Transmit via official email or registered post; log reference number
5. Response scanned and attached to case — restrict access to assigned officers
6. International: mutual legal assistance channel only — no informal leaks

PROHIBITED
Personal WhatsApp sharing of case data; unofficial middlemen for CDR/IPDR

AUDIT
Quarterly review of outbound requests by Compliance cell
TXT,
            ],
            [
                'title' => 'DSR & D.O. Letter Compilation (ADA)',
                'department' => 'Administration',
                'version' => '1.1',
                'effective_date' => '2026-08-01',
                'description' => <<<'TXT'
PURPOSE
Daily Situation Report (DSR) and monthly D.O. Letter from live CMS statistics.

DSR — DAILY
1. ADA / authorised role → DSR Reports → Create
2. Select date and circle; click Auto-Compile (pulls live: lockups, bail, PO/CA, raids, recovery, challan, transfers)
3. Review highlights; edit narrative fields if needed
4. Submit for Circle Incharge review → Forward to HQ
5. Print official PDF with QR verification code

D.O. LETTER — MONTHLY
1. Select month/year; Auto-Compile from aggregated CMS data
2. Excel export matches official template (do-letter-template.xlsx)
3. Approval workflow same as DSR
4. Archive in system; print for record room

DATA INTEGRITY
Figures sourced from database — do not manually override compiled counts without admin note and reason.
TXT,
            ],
        ];
    }

    public static function manuals(): array
    {
        return [
            [
                'title' => 'Software Guide Video (Animated Tour)',
                'audience' => 'All Users',
                'version' => '1.0',
                'description' => <<<'TXT'
SIDEBAR → Software Guide

Animated tour with Cyber Officer Sara explaining NCCIA CMS benefits and workflow.

FEATURES
• Auto-play video-style scenes (10 chapters)
• Roman Urdu / English voice narration
• Complaint → Verification → Enquiry → Case → Court flow
• DSR, SMS, Reference Library overview

CONTROLS
Play, Pause, Prev/Next, chapter jump, Replay voice
TXT,
            ],
            [
                'title' => 'NCCIA Portal User Guide – Getting Started',
                'audience' => 'All Users',
                'version' => '1.1',
                'description' => <<<'TXT'
LOGIN
1. Open official NCCIA CMS URL (bookmark only official domain)
2. Enter username and password — password field is masked
3. On failure, contact circle admin; do not share credentials

DASHBOARD
• Role-based widgets: your assignments, pending tasks, alerts
• Sidebar shows only modules your role may access

NAVIGATION
• Breadcrumb at top: NCCIA → current module
• Global search: tracking / FIR number where enabled

PROFILE
Account → My Profile: update contact, change password

REFERENCE LIBRARY
Laws, Rules, SOPs, Manuals — View for all users; edit only Admin / Circle Incharge / DG

LOGOUT
Always logout on shared PCs; session timeout after inactivity
TXT,
            ],
            [
                'title' => 'Complaint Registration & Management',
                'audience' => 'Operators / Moharrar',
                'version' => '1.2',
                'description' => <<<'TXT'
REGISTER COMPLAINT
Complaints → New → fill form → Save → note Tracking Number → print acknowledgment

SCRUTINY
Open pending queue → Review → Accept / Return / Reject with comments

UPDATE
Edit allowed before assignment; after assignment, changes need supervisor role

ATTACHMENTS
PDF/JPG/PNG; max size per system policy; virus scan automatic

STATUS TRACKING
Filter by status: Registered, Under Scrutiny, Assigned, Closed

SMS
Complainant receives auto SMS — verify in SMS Log module
TXT,
            ],
            [
                'title' => 'Verification Module – Officer Guide',
                'audience' => 'Verification Officers',
                'version' => '1.1',
                'description' => <<<'TXT'
1. Dashboard → Pending Verifications → Accept case
2. Read complaint summary and attachments
3. Plan verification steps; record in case notes
4. Verifications → Report → complete all template sections
5. Upload annexures; Save Draft frequently
6. Submit for Approval when complete
7. If returned, address reviewer comments and resubmit
8. Approved report is locked — request admin for correction if critical error

TIPS
Use neutral language; date-stamp all OSINT screenshots; never contact accused without IO authorisation
TXT,
            ],
            [
                'title' => 'Enquiry & Investigation Workflow',
                'audience' => 'Enquiry Officers / IOs',
                'version' => '1.1',
                'description' => <<<'TXT'
ENQUIRY (EO)
1. Accept enquiry assignment
2. Record diary entries (dates, steps, persons met)
3. Submit enquiry report or recommend FIR

INVESTIGATION (IO)
1. DAC / Case File opened from approved enquiry
2. Process History tab: read-only complaint, VO report, EO enquiry, accused, witnesses
3. Add new arrests and activities — cannot edit inherited records
4. Request forensic examination from case file
5. Submit CFR / challan when complete

CASE FILE TABS
Overview | Accused | Witnesses | Arrests | Activities | Process History | Documents

LOCKING
Saved diary rows locked for IO; Circle Incharge can unlock with reason
TXT,
            ],
            [
                'title' => 'Digital Evidence Submission & Tracking',
                'audience' => 'Investigation Officers / Forensic Staff',
                'version' => '1.0',
                'description' => <<<'TXT'
IO — SUBMIT REQUEST
Case File → Forensic Request → describe items, offence relevance, urgency → Submit

MAL KHANA
Receive physical item → scan tag → link to CMS evidence ID → assign locker

FORENSIC LAB
Accept request → update status: In Progress → Complete → upload report PDF

IO — TRACK
Dashboard and case file show status; download report when released

CUSTODY
Every handover signed in CMS; matches physical chain-of-custody form
TXT,
            ],
            [
                'title' => 'Court Case Management System',
                'audience' => 'Legal Officers',
                'version' => '1.1',
                'description' => <<<'TXT'
CREATE COURT CASE
Link to FIR/DAC → enter court, case number, sections, first hearing date

HEARINGS
Add hearing record after each date; upload orders

LEGAL OPINION
Attach internal opinion memos for prosecution strategy

VERDICTS
Record outcome; notify IO for compliance (e.g. property disposal)

REPORTS
Filter pending trials; export for monthly legal statistics
TXT,
            ],
            [
                'title' => 'User & Permission Administration',
                'audience' => 'Administrators',
                'version' => '1.0',
                'description' => <<<'TXT'
CREATE USER
Users → Add → name, email, role, circle, temporary password → user must change on first login

ROLES
admin, circle_incharge, operator, verification_officer, enquiry_officer, investigation_officer, moharrar, legal roles, ad_administration, director_general

PERMISSIONS
Role defines sidebar access; direct permissions only for exceptions

RESET PASSWORD
Users → Select → Reset; never send password via personal WhatsApp

CIRCLES / ZONES
Circles module — map officers to jurisdiction

AUDIT
Login History for security review
TXT,
            ],
            [
                'title' => 'Analytics Dashboard – Reports & Insights',
                'audience' => 'Administrators / DG / AD',
                'version' => '1.0',
                'description' => <<<'TXT'
ACCESS
Analytics menu — Admin, DG, Circle Incharge (circle-scoped), AD as configured

METRICS
• Complaints by month and offence type
• Verification turnaround
• Open investigations and ageing
• Court outcomes
• Officer workload

FILTERS
Date range, circle, offence category

EXPORT
Use browser print or provided export buttons; official briefings should cite CMS generation date
TXT,
            ],
            [
                'title' => 'Offence Types & Legal Reference Library',
                'audience' => 'All Users',
                'version' => '1.0',
                'description' => <<<'TXT'
OFFENCE TYPES
Administration → Offence Types: categories used in complaint and FIR forms; align with PECA sections

REFERENCE LIBRARY
• Laws — PECA, PPC, ETO, IFTA, QSO summaries
• Rules — operational and procedural rules
• SOPs — step-by-step workflows
• User Manuals — this guide set

USAGE
Open Reference → View full document; cite in reports and charge sheets

UPDATES
Admin runs reference:ensure on server after updates; version numbers on SOPs/manuals
TXT,
            ],
            [
                'title' => 'DSR & D.O. Letter (ADA Administration)',
                'audience' => 'ADA / Circle Incharge',
                'version' => '1.1',
                'description' => <<<'TXT'
ROLES
ad_administration: compile DSR/DO | circle_incharge: review and forward

DSR STEPS
1. DSR Reports → Create
2. Pick date + circle → Auto-Compile
3. Review live stats (lockup, bail, PO/CA, raids, recovery, challan, transferred)
4. Save → Submit for review → Approve → Print (portrait PDF + QR)

DO LETTER STEPS
1. DO Letters → Create → month/year
2. Auto-Compile monthly aggregates
3. Excel export for official template
4. Same approval chain as DSR

TIPS
Compile after daily data entry cut-off; QR on print verifies document authenticity online
TXT,
            ],
        ];
    }
}
