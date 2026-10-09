@extends('layouts.app')

@section('title', 'Edit Complaint #'.$complaint->tracking_no)

@section('page_content')

<div class="complaint-page">

<!-- ── Page Header ── -->
<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Complaints</div>
    <h1 class="page-title">Edit Complaint #{{ $complaint->tracking_no }}</h1>
    <p class="page-subtitle">Modify complaint details &nbsp;·&nbsp; CCRC-LHR Circle</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <a href="{{ route('all.complaints') }}" class="btn btn-outline btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
      Back to All
    </a>
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
<form id="complaintForm" method="post" action="{{ route('complaints.update', $complaint) }}" novalidate>
  @csrf
  @method('PUT')

  <!-- ── SECTION 1: Complainant Details ── -->
  <div class="cf-section animate-fade-up animate-delay-2" id="section1">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#2B2B2B;">
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
            <input type="text" class="cf-input" name="complainant_name" id="complainantName" value="{{ old('complainant_name', $complaint->complainant_name) }}" required>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">CNIC Number</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </span>
            <input type="text" class="cf-input font-mono" name="cnic" id="cnicInput" value="{{ old('cnic', $complaint->cnic) }}" placeholder="XXXXX-XXXXXXX-X" maxlength="15" required>
          </div>
          <span class="cf-hint">Format: 42101-1234567-8</span>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Contact Number</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 9.7 19.79 19.79 0 0 1 1.61 1.1 2 2 0 0 1 3.6.12h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 7.91a16 16 0 0 0 6.13 6.13l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </span>
            <input type="tel" class="cf-input" name="contact_no" id="contactInput" value="{{ old('contact_no', $complaint->contact_no) }}" placeholder="03XX-XXXXXXX" maxlength="12" required>
          </div>
        </div>
      </div>

      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label required">Address</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon" style="top:14px;align-items:flex-start;padding-top:2px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </span>
            <textarea class="cf-input cf-textarea" name="address" rows="2" required>{{ old('address', $complaint->address) }}</textarea>
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
              <option {{ $complaint->profession == 'Government Employee' ? 'selected' : '' }}>Government Employee</option>
              <option {{ $complaint->profession == 'Private Sector Employee' ? 'selected' : '' }}>Private Sector Employee</option>
              <option {{ $complaint->profession == 'Business Owner' ? 'selected' : '' }}>Business Owner</option>
              <option {{ $complaint->profession == 'Student' ? 'selected' : '' }}>Student</option>
              <option {{ $complaint->profession == 'Lawyer / Legal Professional' ? 'selected' : '' }}>Lawyer / Legal Professional</option>
              <option {{ $complaint->profession == 'Journalist / Media' ? 'selected' : '' }}>Journalist / Media</option>
              <option {{ $complaint->profession == 'Bank Employee' ? 'selected' : '' }}>Bank Employee</option>
              <option {{ $complaint->profession == 'Teacher / Academician' ? 'selected' : '' }}>Teacher / Academician</option>
              <option {{ $complaint->profession == 'Retired' ? 'selected' : '' }}>Retired</option>
              <option {{ $complaint->profession == 'Other' ? 'selected' : '' }}>Other</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── SECTION 2: Complaint Details ── -->
  <div class="cf-section animate-fade-up animate-delay-3" id="section2">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#264078;">
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
            <input type="date" class="cf-input" name="report_date" value="{{ old('report_date', $complaint->report_date?->format('Y-m-d')) }}" required>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Diary Number</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </span>
            <input type="text" class="cf-input font-mono" name="diary_no" value="{{ old('diary_no', $complaint->diary_no) }}" placeholder="e.g. LHR-D-0001/25" required>
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
              <option {{ $complaint->received_via == 'Email' ? 'selected' : '' }}>Email</option>
              <option {{ $complaint->received_via == 'Telephone' ? 'selected' : '' }}>Telephone</option>
              <option {{ $complaint->received_via == 'Postal Service' ? 'selected' : '' }}>Postal Service</option>
              <option {{ $complaint->received_via == 'Individually (Walk-in)' ? 'selected' : '' }}>Individually (Walk-in)</option>
              <option {{ $complaint->received_via == 'Mobile App' ? 'selected' : '' }}>Mobile App</option>
              <option {{ $complaint->received_via == 'Online Form / Portal' ? 'selected' : '' }}>Online Form / Portal</option>
              <option {{ $complaint->received_via == 'Tipline (National)' ? 'selected' : '' }}>Tipline (National)</option>
              <option {{ $complaint->received_via == 'Tipline (International)' ? 'selected' : '' }}>Tipline (International)</option>
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
                <option {{ $complaint->received_from == 'President Office' ? 'selected' : '' }}>President Office</option>
                <option {{ $complaint->received_from == 'PM Office' ? 'selected' : '' }}>PM Office</option>
                <option {{ $complaint->received_from == 'Apex Courts' ? 'selected' : '' }}>Apex Courts</option>
                <option {{ $complaint->received_from == 'Ministry / Department' ? 'selected' : '' }}>Ministry / Department</option>
                <option {{ $complaint->received_from == 'Other Government Department' ? 'selected' : '' }}>Other Government Department</option>
              </optgroup>
              <optgroup label="Private Sector">
                <option {{ $complaint->received_from == 'Bank / Financial Institution' ? 'selected' : '' }}>Bank / Financial Institution</option>
                <option {{ $complaint->received_from == 'Organization / Company' ? 'selected' : '' }}>Organization / Company</option>
                <option {{ $complaint->received_from == 'University / Educational Institute' ? 'selected' : '' }}>University / Educational Institute</option>
                <option {{ $complaint->received_from == 'NGO' ? 'selected' : '' }}>NGO</option>
                <option {{ $complaint->received_from == 'Other Private Office' ? 'selected' : '' }}>Other Private Office</option>
              </optgroup>
              <optgroup label="Individual">
                <option {{ $complaint->received_from == 'General Public' ? 'selected' : '' }}>General Public</option>
                <option {{ $complaint->received_from == 'Anonymous' ? 'selected' : '' }}>Anonymous</option>
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
              <option {{ $complaint->cmu == 'NCCIA - HQs' ? 'selected' : '' }}>NCCIA - HQs</option>
              <option {{ $complaint->cmu == 'Zonal Directorate - Lahore' ? 'selected' : '' }}>Zonal Directorate - Lahore</option>
              <option {{ $complaint->cmu == 'Zonal Directorate - Karachi' ? 'selected' : '' }}>Zonal Directorate - Karachi</option>
              <option {{ $complaint->cmu == 'Zonal Directorate - Islamabad' ? 'selected' : '' }}>Zonal Directorate - Islamabad</option>
              <option {{ $complaint->cmu == 'CCRC - LHR' ? 'selected' : '' }}>CCRC - LHR</option>
              <option {{ $complaint->cmu == 'CCRC - KHI' ? 'selected' : '' }}>CCRC - KHI</option>
              <option {{ $complaint->cmu == 'CCRC - ISB' ? 'selected' : '' }}>CCRC - ISB</option>
            </select>
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Complaint Priority Type</label>
        <div class="cf-check-group">
          <label class="cf-check-item">
            <input type="radio" name="priority_type" value="regular" {{ $complaint->priority_type == 'regular' ? 'checked' : '' }}>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title">Regular Process</span>
              <span class="cf-check-desc">Standard complaint queue</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="priority_type" value="court" {{ $complaint->priority_type == 'court' ? 'checked' : '' }}>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title">Court Direction</span>
              <span class="cf-check-desc">As directed by court order</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="priority_type" value="anti_state" {{ $complaint->priority_type == 'anti_state' ? 'checked' : '' }}>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title cf-check-urgent">Anti-State</span>
              <span class="cf-check-desc">National security concern</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="priority_type" value="higher_authority" {{ $complaint->priority_type == 'higher_authority' ? 'checked' : '' }}>
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
      <div class="cf-section-icon" style="background:#2B2B2B;">
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
                <option value="{{ $t->value }}" {{ $complaint->offence_type == $t->value ? 'selected' : '' }}>{{ $t->name }}</option>
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
            <input type="number" class="cf-input" name="amount_involved" value="{{ old('amount_involved', $complaint->amount_involved) }}" placeholder="0.00" min="0" step="0.01" style="padding-left: 48px;">
          </div>
          <span class="cf-hint">Leave blank if not applicable</span>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Date of Occurrence</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </span>
            <input type="date" class="cf-input" name="occurrence_date" value="{{ old('occurrence_date', $complaint->occurrence_date?->format('Y-m-d')) }}" required>
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Applicable Laws / Sections</label>
        <div class="cf-check-group cf-check-group-wrap">
          @php $laws = is_array($complaint->laws) ? $complaint->laws : []; @endphp
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="peca_26A" {{ in_array('peca_26A', $laws) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PECA S.26-A (Cyberstalking)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="peca_24" {{ in_array('peca_24', $laws) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PECA S.24 (Cyber Terrorism)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="peca_20" {{ in_array('peca_20', $laws) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PECA S.20 (Defamation)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="peca_3" {{ in_array('peca_3', $laws) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PECA S.3 (Unauthorized Access)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="ppc_420" {{ in_array('ppc_420', $laws) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PPC S.420 (Cheating/Fraud)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="ppc_506" {{ in_array('ppc_506', $laws) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">PPC S.506 (Criminal Intimidation)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="ata" {{ in_array('ata', $laws) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">ATA (Anti-Terrorism Act)</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="laws[]" value="other_law" {{ in_array('other_law', $laws) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Other (specify in remarks)</span>
          </label>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label required">Crime Description</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea cf-textarea-lg" name="description" rows="5" required>{{ old('description', $complaint->description) }}</textarea>
        </div>
        <div class="cf-char-count"><span id="descCount">{{ strlen($complaint->description) }}</span> / 2000 characters</div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Evidence Available</label>
        <div class="cf-check-group cf-check-group-wrap">
          @php $evidence = is_array($complaint->evidence) ? $complaint->evidence : []; @endphp
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="screenshots" {{ in_array('screenshots', $evidence) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Screenshots / Screen recordings</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="chat_logs" {{ in_array('chat_logs', $evidence) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Chat / Message logs</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="bank_records" {{ in_array('bank_records', $evidence) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Bank transaction records</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="call_records" {{ in_array('call_records', $evidence) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Call records / Recordings</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="device" {{ in_array('device', $evidence) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Device / SIM / Hardware</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="witness" {{ in_array('witness', $evidence) ? 'checked' : '' }}>
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Witness(es) available</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="evidence[]" value="none" {{ in_array('none', $evidence) ? 'checked' : '' }}>
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
      <div class="cf-section-icon" style="background:#264078;">
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
            <input type="text" class="cf-input" name="operator_name" value="{{ old('operator_name', $complaint->operator_name) }}" readonly>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Designation</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </span>
            <input type="text" class="cf-input" name="operator_designation" value="{{ old('operator_designation', $complaint->operator_designation) }}" readonly>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Entry Time</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </span>
            <input type="datetime-local" class="cf-input" name="entry_time" id="entryTime" value="{{ old('entry_time', $complaint->entry_time?->format('Y-m-d\TH:i')) }}" readonly>
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label required">Scrutiny Result</label>
        <div class="cf-check-group">
          <label class="cf-check-item">
            <input type="radio" name="scrutiny_result" value="complete" {{ $complaint->scrutiny_result == 'complete' ? 'checked' : '' }} required>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#2B2B2B;">✓ Complete</span>
              <span class="cf-check-desc">All information verified and complete</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="scrutiny_result" value="incomplete" {{ $complaint->scrutiny_result == 'incomplete' ? 'checked' : '' }}>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">⚠ Incomplete</span>
              <span class="cf-check-desc">Missing information, follow-up required</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="scrutiny_result" value="invalid" {{ $complaint->scrutiny_result == 'invalid' ? 'checked' : '' }}>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#2B2B2B;">✗ Invalid</span>
              <span class="cf-check-desc">Does not fall within jurisdiction/scope</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="scrutiny_result" value="irrelevant" {{ $complaint->scrutiny_result == 'irrelevant' ? 'checked' : '' }}>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">— Irrelevant</span>
              <span class="cf-check-desc">Not a cyber crime matter</span>
            </div>
          </label>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Operator Remarks</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea" name="operator_remarks" rows="3">{{ old('operator_remarks', $complaint->operator_remarks) }}</textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Form Actions ── -->
  <div class="cf-form-actions animate-fade-up animate-delay-7">
    <a href="{{ route('all.complaints') }}" class="btn btn-outline">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
      Cancel
    </a>
    <div style="display:flex;gap:10px;">
      <button type="submit" class="btn cf-submit-btn" id="submitBtn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Update Complaint
      </button>
    </div>
  </div>

</form>
</div>
@endsection

@push('extra_js')
<script>
(function () {
  'use strict';

  const cnicInput = document.getElementById('cnicInput');
  if (cnicInput) {
    cnicInput.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '');
      if (v.length > 5)  v = v.slice(0,5)  + '-' + v.slice(5);
      if (v.length > 13) v = v.slice(0,13) + '-' + v.slice(13);
      this.value = v.slice(0, 15);
    });
  }

  const contactInput = document.getElementById('contactInput');
  if (contactInput) {
    contactInput.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').slice(0, 11);
      if (v.length > 4) v = v.slice(0, 4) + '-' + v.slice(4);
      this.value = v;
    });
  }

  const descEl = document.querySelector('[name="description"]');
  const countEl = document.getElementById('descCount');
  if (descEl && countEl) {
    descEl.addEventListener('input', function () {
      countEl.textContent = this.value.length;
      countEl.style.color = this.value.length > 1800 ? '#2B2B2B' : 'var(--text-muted)';
    });
  }

  const steps = document.querySelectorAll('.step');
  function updateSteps() {
    const s1 = document.getElementById('complainantName').value.trim() &&
                document.getElementById('cnicInput').value.trim();
    if (s1) { steps[0].classList.add('done'); steps[1].classList.add('active'); }
  }
  document.getElementById('section1').addEventListener('input', updateSteps);

  function showToast(msg, type) {
    const toast = document.createElement('div');
    const isErr = type === 'error';
    toast.style.cssText = `
      position:fixed;bottom:28px;right:28px;z-index:9999;
      padding:14px 24px;border-radius:12px;font-size:13px;font-weight:500;
      box-shadow:0 8px 32px rgba(0,0,0,0.18);
      display:flex;align-items:center;gap:10px;
      animation:slideInToast 0.35s cubic-bezier(0.22,1,0.36,1) both;
      background:${isErr ? '#e53e3e' : '#2B2B2B'};
      color:#fff;line-height:1.4;
      border-left:4px solid ${isErr ? '#c53030' : '#015C94'};
    `;
    toast.innerHTML = `
      <span style="font-size:16px;flex-shrink:0;">${isErr ? '✕' : '✓'}</span>
      <span>${msg}</span>
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(12px)';
      toast.style.transition = 'all 0.35s';
      setTimeout(() => toast.remove(), 350);
    }, 4000);
  }

  @if($errors->any())
  setTimeout(() => {
    @foreach($errors->all() as $err)
    showToast('{{ str_replace(["'", "\n"], ["\'", " "], $err) }}', 'error');
    @endforeach
  }, 300);
  @endif

})();
</script>
@endpush
