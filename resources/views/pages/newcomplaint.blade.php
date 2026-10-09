@extends('layouts.app')

@section('title', 'New Complaint')

@push('extra_css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js@11.0.1/public/assets/styles/choices.min.css">
@endpush

@section('page_content')

<div class="complaint-page">

<!-- ── Page Header ── -->
<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Complaints</div>
    <h1 class="page-title">Register New Complaint</h1>
    <p class="page-subtitle">Complete all sections below &nbsp;·&nbsp; CCRC-LHR Circle</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm" onclick="resetForm()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
      Reset
    </button>
    <button class="btn btn-primary btn-sm" onclick="saveDraft()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Save Draft
    </button>
  </div>
</div>

<!-- ── Progress Steps ── -->
<div class="complaint-steps animate-fade-up animate-delay-1">
  <div class="step active" data-step="1">
    <div class="step-circle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
    </div>
    <div class="step-info">
      <span class="step-num">01</span>
      <span class="step-label">Complainant</span>
    </div>
  </div>
  <div class="step-line"></div>
  <div class="step" data-step="2">
    <div class="step-circle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    </div>
    <div class="step-info">
      <span class="step-num">02</span>
      <span class="step-label">Complaint</span>
    </div>
  </div>
  <div class="step-line"></div>
  <div class="step" data-step="3">
    <div class="step-circle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    </div>
    <div class="step-info">
      <span class="step-num">03</span>
      <span class="step-label">Crime Details</span>
    </div>
  </div>
  <div class="step-line"></div>
  <div class="step" data-step="4">
    <div class="step-circle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9"/></svg>
    </div>
    <div class="step-info">
      <span class="step-num">04</span>
      <span class="step-label">Operator</span>
    </div>
  </div>
</div>

<!-- ══ MAIN FORM ══ -->
<form id="complaintForm" method="post" action="{{ route('complaints.store') }}" novalidate>
  @csrf

  <!-- ── SECTION 1: Complainant Details ── -->
  <div class="cf-section animate-fade-up animate-delay-2" id="section1">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#27adff;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Complainant Details</div>
        <div class="cf-section-sub">Personal information of the complainant</div>
      </div>
      <div class="cf-section-badge">Step 01</div>
    </div>

    <div class="cf-body">
      <div class="cf-row-3">
        <div class="cf-field">
          <label class="cf-label required">Full Name</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
            </span>
            <input type="text" class="cf-input" name="complainant_name" id="complainantName" placeholder="e.g. Muhammad Ali Khan" required>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">CNIC Number</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </span>
            <input type="text" class="cf-input font-mono" name="cnic" id="cnicInput" placeholder="XXXXX-XXXXXXX-X" maxlength="15" required>
          </div>
          <span class="cf-hint">Format: 42101-1234567-8</span>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Contact Number</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 9.7 19.79 19.79 0 0 1 1.61 1.1 2 2 0 0 1 3.6.12h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 7.91a16 16 0 0 0 6.13 6.13l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </span>
            <input type="tel" class="cf-input" name="contact_no" id="contactInput" placeholder="03XX-XXXXXXX" maxlength="12" required>
          </div>
          <span class="cf-hint">Format: 0320-0000000</span>
        </div>
      </div>

      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label required">Address</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon" style="top:14px;align-items:flex-start;padding-top:2px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </span>
            <textarea class="cf-input cf-textarea" name="address" rows="2" placeholder="House No., Street, Mohalla, City, Province" required></textarea>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Profession / Occupation</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </span>
            <select class="cf-input cf-select" name="profession">
              <option value="">— Select Profession —</option>
              <option>Government Employee</option>
              <option>Private Sector Employee</option>
              <option>Business Owner</option>
              <option>Student</option>
              <option>Lawyer / Legal Professional</option>
              <option>Journalist / Media</option>
              <option>Bank Employee</option>
              <option>Teacher / Academician</option>
              <option>Retired</option>
              <option>Other</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── SECTION 2: Complaint Details ── -->
  <div class="cf-section animate-fade-up animate-delay-3" id="section2">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#27adff;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Complaint Details</div>
        <div class="cf-section-sub">Source, channel and routing information</div>
      </div>
      <div class="cf-section-badge">Step 02</div>
    </div>

    <div class="cf-body">
      <div class="cf-row-3">
        <div class="cf-field">
          <label class="cf-label required">Report Date</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <input type="date" class="cf-input" name="report_date" required>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Diary Number</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </span>
            <input type="text" class="cf-input font-mono" name="diary_no" placeholder="e.g. LHR-D-0001/25" required>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Received Via</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </span>
            <select class="cf-input cf-select" name="received_via" required>
              <option value="">— Select Channel —</option>
              <option>Email</option>
              <option>Telephone</option>
              <option>Postal Service</option>
              <option>Individually (Walk-in)</option>
              <option>Mobile App</option>
              <option>Online Form / Portal</option>
              <option>Tipline (National)</option>
              <option>Tipline (International)</option>
            </select>
          </div>
        </div>
      </div>

      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label required">Received From (Source)</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <select class="cf-input cf-select" name="received_from" required>
              <option value="">— Select Source —</option>
              <optgroup label="Government Sector">
                <option>President Office</option>
                <option>PM Office</option>
                <option>Apex Courts</option>
                <option>Ministry / Department</option>
                <option>Other Government Department</option>
              </optgroup>
              <optgroup label="Private Sector">
                <option>Bank / Financial Institution</option>
                <option>Organization / Company</option>
                <option>University / Educational Institute</option>
                <option>NGO</option>
                <option>Other Private Office</option>
              </optgroup>
              <optgroup label="Individual">
                <option>General Public</option>
                <option>Anonymous</option>
              </optgroup>
            </select>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Complaint Management Unit</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </span>
            <select class="cf-input cf-select" name="cmu">
              <option value="">— Select Unit —</option>
              <option>NCCIA - HQs</option>
              <option>Zonal Directorate - Lahore</option>
              <option>Zonal Directorate - Karachi</option>
              <option>Zonal Directorate - Islamabad</option>
              <option>CCRC - LHR</option>
              <option>CCRC - KHI</option>
              <option>CCRC - ISB</option>
            </select>
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Complaint Priority Type</label>
        <div class="cf-check-group">
          <label class="cf-check-item">
            <input type="radio" name="priority_type" value="regular" checked>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title">Regular Process</span>
              <span class="cf-check-desc">Standard complaint queue</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="priority_type" value="court">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title">Court Direction</span>
              <span class="cf-check-desc">As directed by court order</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="priority_type" value="anti_state">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title cf-check-urgent">Anti-State</span>
              <span class="cf-check-desc">National security concern</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="priority_type" value="higher_authority">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title">Higher Authority</span>
              <span class="cf-check-desc">DG / Director directions</span>
            </div>
          </label>
        </div>
      </div>
    </div>
  </div>

  <!-- ── SECTION 3: Crime Details ── -->
  <div class="cf-section animate-fade-up animate-delay-4" id="section3">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#27adff;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Crime Details</div>
        <div class="cf-section-sub">Nature, description and specifics of the offence</div>
      </div>
      <div class="cf-section-badge">Step 03</div>
    </div>

    <div class="cf-body">
      <div class="cf-row-3">
        <div class="cf-field">
          <label class="cf-label required">Offence Type</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </span>
            <select class="cf-input cf-select" name="offence_type" id="offenceType" required>
              <option value="">— Select Offence —</option>
              @php $groups = $offenceTypes->groupBy('group'); @endphp
              @foreach($groups as $groupName => $types)
              <optgroup label="{{ $groupName ?: 'Other' }}">
                @foreach($types as $t)
                <option value="{{ $t->value }}">{{ $t->name }}</option>
                @endforeach
              </optgroup>
              @endforeach
            </select>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Amount Involved (PKR)</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon" style="display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--text-muted);width:30px;">PKR</span>
            <input type="number" class="cf-input" name="amount_involved" placeholder="0.00" min="0" step="0.01" style="padding-left: 48px;">
          </div>
          <span class="cf-hint">Leave blank if not applicable</span>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Date of Occurrence</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </span>
            <input type="date" class="cf-input" name="occurrence_date" required>
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Applicable Laws / Sections</label>
        <div class="cf-check-group cf-check-group-wrap">
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="peca_26A">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PECA S.26-A (Cyberstalking)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="peca_24">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PECA S.24 (Cyber Terrorism)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="peca_20">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PECA S.20 (Defamation)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="peca_3">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PECA S.3 (Unauthorized Access)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="ppc_420">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PPC S.420 (Cheating/Fraud)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="ppc_506">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PPC S.506 (Criminal Intimidation)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="ata">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">ATA (Anti-Terrorism Act)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="other_law">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Other (specify in remarks)</span>
          </label>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label required">Crime Description</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea cf-textarea-lg" name="description" rows="5"
            placeholder="Provide a detailed description of the crime/offence — include platform used, method of fraud, timeline of events, accused details if known, evidence available, etc."
            required></textarea>
        </div>
        <div class="cf-char-count"><span id="descCount">0</span> / 2000 characters</div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Evidence Available</label>
        <div class="cf-check-group cf-check-group-wrap">
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="screenshots">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Screenshots / Screen recordings</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="chat_logs">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Chat / Message logs</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="bank_records">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Bank transaction records</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="call_records">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Call records / Recordings</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="device">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Device / SIM / Hardware</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="witness">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Witness(es) available</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="none">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">No evidence available</span>
          </label>
        </div>
      </div>
    </div>
  </div>

  <!-- ── SECTION 4: Operator Details ── -->
  <div class="cf-section animate-fade-up animate-delay-5" id="section4">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#27adff;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Operator / Entry Details</div>
        <div class="cf-section-sub">Information about the officer entering this complaint</div>
      </div>
      <div class="cf-section-badge">Step 04</div>
    </div>

    <div class="cf-body">
      <div class="cf-row-3">
        <div class="cf-field">
          <label class="cf-label required">Operator Name</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
            </span>
            <input type="text" class="cf-input" name="operator_name" value="Muhammad Umar Ilyas" readonly>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Designation</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </span>
            <input type="text" class="cf-input" name="operator_designation" value="Asst. Sub Inspector" readonly>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Entry Time</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </span>
            <input type="datetime-local" class="cf-input" name="entry_time" id="entryTime" readonly>
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label required">Scrutiny Result</label>
        <div class="cf-check-group">
          <label class="cf-check-item">
            <input type="radio" name="scrutiny_result" value="complete" required>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#27adff;">✓ Complete</span>
              <span class="cf-check-desc">All information verified and complete</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="scrutiny_result" value="incomplete">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#27adff;">⚠ Incomplete</span>
              <span class="cf-check-desc">Missing information, follow-up required</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="scrutiny_result" value="invalid">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#27adff;">✗ Invalid</span>
              <span class="cf-check-desc">Does not fall within jurisdiction/scope</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="scrutiny_result" value="irrelevant">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#27adff;">— Irrelevant</span>
              <span class="cf-check-desc">Not a cyber crime matter</span>
            </div>
          </label>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Operator Remarks</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea" name="operator_remarks" rows="3" placeholder="Any additional remarks, observations or notes by the operator…"></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Tracking Number Preview ── -->
  <div class="cf-tracking-preview animate-fade-up animate-delay-6">
    <div class="cf-tracking-left">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <div>
        <div class="cf-tracking-label">Tracking Number (Auto-Generated on Submit)</div>
        <div class="cf-tracking-no" id="trackingPreview">CCW-C-—/25</div>
      </div>
    </div>
    <div class="cf-tracking-note">
      Format: <strong>CIRCLE-C-SERIAL/YEAR</strong> &nbsp;·&nbsp; e.g. CCW-C-1932/25
    </div>
  </div>

  <!-- ── Form Actions ── -->
  <div class="cf-form-actions animate-fade-up animate-delay-7">
    <button type="button" class="btn btn-outline" onclick="resetForm()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
      Reset Form
    </button>
    <div style="display:flex;gap:10px;">
      <button type="button" class="btn btn-outline" onclick="saveDraft()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
        Save Draft
      </button>
      <button type="submit" class="btn cf-submit-btn" id="submitBtn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Register Complaint
      </button>
    </div>
  </div>

</form>
</div>
@endsection

@push('extra_css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js@11.0.1/public/assets/styles/choices.min.css">
@endpush

@push('extra_js')
<script src="https://cdn.jsdelivr.net/npm/choices.js@11.0.1/public/assets/scripts/choices.min.js"></script>
<script>
(function () {
  'use strict';
  document.querySelectorAll('.cf-select').forEach(function (sel) {
    new Choices(sel, {
      searchEnabled: true,
      searchPlaceholderValue: 'Search...',
      shouldSort: false,
      itemSelectText: '',
      allowHTML: true,
    });
  });

  const steps = document.querySelectorAll('.step');
  const sections = ['section1', 'section2', 'section3', 'section4'];

  function getRequiredFields(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return [];
    return Array.from(section.querySelectorAll('[required]')).filter(el => {
      if (el.type === 'radio') return true;
      if (el.type === 'checkbox') return true;
      return true;
    });
  }

  function isSectionComplete(sectionId) {
    const fields = getRequiredFields(sectionId);
    if (fields.length === 0) return false;

    if (sectionId === 'section1') {
      const name = document.getElementById('complainantName').value.trim();
      const cnic = document.getElementById('cnicInput').value.trim();
      const contact = document.getElementById('contactInput').value.trim();
      return name && cnic && contact;
    }
    if (sectionId === 'section2') {
      const reportDate = section.querySelector('[name="report_date"]').value;
      const diaryNo = section.querySelector('[name="diary_no"]').value.trim();
      const receivedVia = section.querySelector('[name="received_via"]').value;
      const receivedFrom = section.querySelector('[name="received_from"]').value;
      return reportDate && diaryNo && receivedVia && receivedFrom;
    }
    if (sectionId === 'section3') {
      const offenceType = document.getElementById('offenceType').value;
      const occurrenceDate = section.querySelector('[name="occurrence_date"]').value;
      const description = section.querySelector('[name="description"]').value.trim();
      return offenceType && occurrenceDate && description;
    }
    if (sectionId === 'section4') {
      const scrutinyResult = section.querySelector('[name="scrutiny_result"]:checked');
      return scrutinyResult !== null;
    }
    return false;
  }

  function updateSteps() {
    sections.forEach((sectionId, index) => {
      const step = steps[index];
      if (!step) return;
      const complete = isSectionComplete(sectionId);
      step.classList.toggle('done', complete);
      step.classList.toggle('active', !complete && sections.slice(0, index).every(s => isSectionComplete(s)));
      if (complete && index < sections.length - 1) {
        steps[index + 1].classList.add('active');
      }
    });
  }

  sections.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', updateSteps);
      el.addEventListener('change', updateSteps);
    }
  });

  updateSteps();
})();
</script>
@endpush
