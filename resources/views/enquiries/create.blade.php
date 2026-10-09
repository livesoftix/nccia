@extends('layouts.app')

@section('title', 'New Enquiry')

@section('page_content')

<div class="enquiry-page">
<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Complaints</div>
    <h1 class="page-title">Register New Enquiry</h1>
    <p class="page-subtitle">Step-by-step enquiry registration &nbsp;·&nbsp; CCRC-LHR Circle</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm" onclick="resetEnquiry()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
      Reset
    </button>
    <button class="btn btn-primary btn-sm" onclick="saveDraft()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Save Draft
    </button>
  </div>
</div>

<!-- ══ PROGRESS STEPS ══ -->
<div class="complaint-steps animate-fade-up animate-delay-1" id="stepsBar">

  <div class="step active" data-step="1">
    <div class="step-circle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
    </div>
    <div class="step-info"><span class="step-num">01</span><span class="step-label">Reader Branch</span></div>
  </div>

  <div class="step-line"></div>

  <div class="step" data-step="2">
    <div class="step-circle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
    </div>
    <div class="step-info"><span class="step-num">02</span><span class="step-label">Enquiry Officer</span></div>
  </div>

  <div class="step-line"></div>

  <div class="step" data-step="3">
    <div class="step-circle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
    </div>
    <div class="step-info"><span class="step-num">03</span><span class="step-label">Circle Incharge</span></div>
  </div>

  <div class="step-line"></div>

  <div class="step" data-step="4">
    <div class="step-circle">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    </div>
    <div class="step-info"><span class="step-num">04</span><span class="step-label">Legal &amp; Outcome</span></div>
  </div>

</div>

<!-- ══ FORM ══ -->
<form id="enquiryForm" method="post" action="{{ route('enquiries.store') }}" novalidate>
  @csrf

  <!-- ════ STEP 1 — Reader Branch ════ -->
  <div class="cf-section animate-fade-up animate-delay-2 eq-step-panel" id="step1">

    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#2B2B2B;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Reader Branch</div>
        <div class="cf-section-sub">Enquiry registration from complaint record</div>
      </div>
      <div class="cf-section-badge">Step 01</div>
    </div>

    <div class="cf-body">

      <div class="cf-row-3">

        <div class="cf-field">
          <label class="cf-label required">Enquiry Number</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </span>
            <input type="text" class="cf-input font-mono" name="enquiry_no" id="enquiryNo" placeholder="e.g. LHR-E-0001/25" required>
          </div>
          <span class="cf-hint">Auto-generated — confirm or override</span>
        </div>

        <div class="cf-field">
          <label class="cf-label required">Linked Complaint No.</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </span>
            <input type="text" class="cf-input font-mono" name="linked_complaint" placeholder="e.g. CCW-C-1932/25" required>
          </div>
        </div>

        <div class="cf-field">
          <label class="cf-label required">Registration Date</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <input type="date" class="cf-input" name="reg_date" required>
          </div>
        </div>

      </div>

      <div class="cf-row-2">

        <div class="cf-field">
          <label class="cf-label required">Circle Incharge</label>
          <div class="eq-select-wrap" id="sel_circle_incharge">
            <button type="button" class="eq-select-btn" data-placeholder="— Assign Circle Incharge —">
              <span class="eq-select-val">— Assign Circle Incharge —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-search-wrap"><input type="text" class="eq-search" placeholder="Search…"></div>
              <div class="eq-options">
                <div class="eq-option" data-value="insp_ali">Inspector Muhammad Ali</div>
                <div class="eq-option" data-value="insp_umar">Inspector Umar Farooq</div>
                <div class="eq-option" data-value="si_tariq">Sub Inspector Tariq Mahmood</div>
                <div class="eq-option" data-value="si_bilal">Sub Inspector Bilal Ahmed</div>
                <div class="eq-option" data-value="si_faisal">Sub Inspector Faisal Rafiq</div>
              </div>
            </div>
            <input type="hidden" name="circle_incharge" required>
          </div>
        </div>

        <div class="cf-field">
          <label class="cf-label required">Enquiry Type</label>
          <div class="eq-select-wrap" id="sel_enquiry_type">
            <button type="button" class="eq-select-btn" data-placeholder="— Select Type —">
              <span class="eq-select-val">— Select Type —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-options">
                <div class="eq-option" data-value="regular" data-icon="🟢" data-desc="Standard priority queue">Regular Process</div>
                <div class="eq-option" data-value="court" data-icon="⚖️" data-desc="Directed by court order">Court Direction</div>
                <div class="eq-option" data-value="anti_state" data-icon="🔴" data-desc="National security concern" data-urgent="1">Anti-State</div>
                <div class="eq-option" data-value="higher" data-icon="🔵" data-desc="DG / Director directive">Higher Authority</div>
              </div>
            </div>
            <input type="hidden" name="enquiry_type" required>
          </div>
        </div>

      </div>

      <div class="eq-linked-card" id="linkedCard" style="display:none;">
        <div class="eq-linked-header">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
          Linked Complaint Details
        </div>
        <div class="cf-row-3" style="margin-bottom:0;">
          <div class="form-info-row"><span class="form-info-label">Complainant</span><span class="form-info-value" id="lc_name">Muhammad Ali Khan</span></div>
          <div class="form-info-row"><span class="form-info-label">CNIC</span><span class="form-info-value" id="lc_cnic">35202-3014071-1</span></div>
          <div class="form-info-row"><span class="form-info-label">Offence Type</span><span class="form-info-value" id="lc_offence">Financial Fraud</span></div>
        </div>
      </div>

    </div>

  </div>

  <!-- ════ STEP 2 — Enquiry Officer ════ -->
  <div class="cf-section animate-fade-up eq-step-panel" id="step2">

    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#264078;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Enquiry Officer — Investigation Actions</div>
        <div class="cf-section-sub">Assigned officer's work and findings during enquiry</div>
      </div>
      <div class="cf-section-badge">Step 02</div>
    </div>

    <div class="cf-body">

      <div class="cf-row-2">

        <div class="cf-field">
          <label class="cf-label required">Assigned Enquiry Officer</label>
          <div class="eq-select-wrap" id="sel_enq_officer">
            <button type="button" class="eq-select-btn" data-placeholder="— Select Officer —">
              <span class="eq-select-val">— Select Officer —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-search-wrap"><input type="text" class="eq-search" placeholder="Search officer…"></div>
              <div class="eq-options">
                <div class="eq-option" data-value="asi_umar"   data-desc="ASI · CCRC-LHR">ASI Muhammad Umar Ilyas</div>
                <div class="eq-option" data-value="asi_tariq"  data-desc="ASI · CCRC-LHR">ASI Tariq Mahmood</div>
                <div class="eq-option" data-value="asi_bilal"  data-desc="ASI · CCRC-LHR">ASI Bilal Ahmed</div>
                <div class="eq-option" data-value="asi_faisal" data-desc="ASI · CCRC-LHR">ASI Faisal Rafiq</div>
                <div class="eq-option" data-value="asi_kamran" data-desc="ASI · CCRC-LHR">ASI Kamran Iqbal</div>
              </div>
            </div>
            <input type="hidden" name="enquiry_officer" required>
          </div>
        </div>

        <div class="cf-field">
          <label class="cf-label required">Assignment Date</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <input type="date" class="cf-input" name="assignment_date" required>
          </div>
        </div>

      </div>

      <div class="cf-field">
        <label class="cf-label required">Actions Taken by Enquiry Officer</label>
        <div class="cf-check-group cf-check-group-wrap">
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="actions[]" value="dac_request">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">DAC Request</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="actions[]" value="bank_record">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Obtains Bank Record</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="actions[]" value="search_seize">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Search &amp; Seize</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="actions[]" value="notices">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Summons Issued</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="actions[]" value="diaries">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Diaries Maintained</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="actions[]" value="seizures">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Seizures Made</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="actions[]" value="recoveries">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">Recoveries Effected</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="checkbox" name="actions[]" value="cfr">
            <span class="cf-check-box"></span>
            <span class="cf-check-title">CFR Submitted</span>
          </label>
        </div>
      </div>

      <div class="cf-field" id="noticeDetailsWrap" style="display:none;">
        <label class="cf-label">Summon Details</label>
        <div class="cf-row-2">
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            </span>
            <input type="text" class="cf-input" name="notice_type" placeholder="Summon type (e.g. S.160 CrPC, PECA)">
          </div>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <input type="date" class="cf-input" name="notice_date">
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label required">Officer Recommendation</label>
        <div class="cf-check-group">
          <label class="cf-check-item">
            <input type="radio" name="officer_recommendation" value="case_registration" required>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#2B2B2B;">Case Registration</span>
              <span class="cf-check-desc">Sufficient evidence for FIR</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="officer_recommendation" value="closure">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">Closure</span>
              <span class="cf-check-desc">Non-pursuance / lack of evidence</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="officer_recommendation" value="transfer">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">Transfer</span>
              <span class="cf-check-desc">Forward to another circle/dept</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="officer_recommendation" value="convert_case">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#2B2B2B;">Convert to Case</span>
              <span class="cf-check-desc">Escalate to full FIR/case</span>
            </div>
          </label>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">CFR / Investigation Summary</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea cf-textarea-lg" name="cfr_summary" rows="4"
            placeholder="Summarize the findings — evidence obtained, persons examined, DAC results, financial records reviewed, digital forensics outcome, etc."></textarea>
        </div>
        <div class="cf-char-count"><span id="cfrCount">0</span> / 2000 characters</div>
      </div>

    </div>

  </div>

  <!-- ════ STEP 3 — Circle Incharge ════ -->
  <div class="cf-section animate-fade-up eq-step-panel" id="step3">

    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#264078;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Circle Incharge — Enquiry Report Approval</div>
        <div class="cf-section-sub">Incharge review, approval and final recommendations</div>
      </div>
      <div class="cf-section-badge">Step 03</div>
    </div>

    <div class="cf-body">

      <div class="cf-row-2">

        <div class="cf-field">
          <label class="cf-label required">Approval Decision</label>
          <div class="cf-check-group" style="flex-direction:column;gap:8px;">
            <label class="cf-check-item">
              <input type="radio" name="incharge_approval" value="agree" required>
              <span class="cf-check-box cf-radio-box"></span>
              <div class="cf-check-content">
                <span class="cf-check-title" style="color:#2B2B2B;">✓ Agree</span>
                <span class="cf-check-desc">Approve officer's recommendation</span>
              </div>
            </label>
            <label class="cf-check-item">
              <input type="radio" name="incharge_approval" value="review">
              <span class="cf-check-box cf-radio-box"></span>
              <div class="cf-check-content">
                <span class="cf-check-title" style="color:#264078;">↺ Review</span>
                <span class="cf-check-desc">Send back for further investigation</span>
              </div>
            </label>
          </div>
        </div>

        <div class="cf-field">
          <label class="cf-label">Change Enquiry Officer (if Review)</label>
          <div class="eq-select-wrap" id="sel_new_officer">
            <button type="button" class="eq-select-btn" data-placeholder="— Keep Current Officer —">
              <span class="eq-select-val">— Keep Current Officer —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-search-wrap"><input type="text" class="eq-search" placeholder="Search officer…"></div>
              <div class="eq-options">
                <div class="eq-option" data-value=""          data-desc="No change">Keep Current Officer</div>
                <div class="eq-option" data-value="asi_umar"  data-desc="ASI · CCRC-LHR">ASI Muhammad Umar Ilyas</div>
                <div class="eq-option" data-value="asi_tariq" data-desc="ASI · CCRC-LHR">ASI Tariq Mahmood</div>
                <div class="eq-option" data-value="asi_bilal" data-desc="ASI · CCRC-LHR">ASI Bilal Ahmed</div>
                <div class="eq-option" data-value="asi_faisal"data-desc="ASI · CCRC-LHR">ASI Faisal Rafiq</div>
              </div>
            </div>
            <input type="hidden" name="new_officer">
          </div>
        </div>

      </div>

      <div class="cf-field">
        <label class="cf-label required">Incharge Recommendation</label>
        <div class="cf-check-group">
          <label class="cf-check-item">
            <input type="radio" name="incharge_recommendation" value="case_registration" required>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#2B2B2B;">Case Registration</span>
              <span class="cf-check-desc">Register FIR / Case</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="incharge_recommendation" value="closure">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">Closure</span>
              <span class="cf-check-desc">Close enquiry</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="incharge_recommendation" value="merge">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">Merge</span>
              <span class="cf-check-desc">Merge with existing complaint</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="incharge_recommendation" value="transfer">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">Transfer</span>
              <span class="cf-check-desc">Transfer to dept / circle</span>
            </div>
          </label>
        </div>
      </div>

      <div class="cf-field eq-conditional" id="closureReasonWrap" style="display:none;">
        <label class="cf-label required">Closure Reason</label>
        <div class="cf-check-group cf-check-group-wrap">
          <label class="cf-check-item cf-check-sm">
            <input type="radio" name="closure_reason" value="non_pursuance">
            <span class="cf-check-box cf-radio-box"></span>
            <span class="cf-check-title">Non Pursuance</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="radio" name="closure_reason" value="irrelevant">
            <span class="cf-check-box cf-radio-box"></span>
            <span class="cf-check-title">Irrelevant</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="radio" name="closure_reason" value="invalid">
            <span class="cf-check-box cf-radio-box"></span>
            <span class="cf-check-title">Invalid</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="radio" name="closure_reason" value="lack_evidence">
            <span class="cf-check-box cf-radio-box"></span>
            <span class="cf-check-title">Lack of Evidence</span>
          </label>
          <label class="cf-check-item cf-check-sm">
            <input type="radio" name="closure_reason" value="compromise">
            <span class="cf-check-box cf-radio-box"></span>
            <span class="cf-check-title">Compromise</span>
          </label>
        </div>
      </div>

      <div class="cf-field eq-conditional" id="mergeRefWrap" style="display:none;">
        <label class="cf-label required">Reference Complaint No. (Merge)</label>
        <div class="cf-input-wrap">
          <span class="cf-input-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
          </span>
          <input type="text" class="cf-input font-mono" name="merge_complaint_ref" placeholder="e.g. CCW-C-1800/25">
        </div>
      </div>

      <div class="cf-field eq-conditional" id="transferTargetWrap" style="display:none;">
        <label class="cf-label required">Transfer To</label>
        <div class="cf-row-2">
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </span>
            <input type="text" class="cf-input" name="transfer_dept" placeholder="Department Name">
          </div>
          <div class="eq-select-wrap" id="sel_transfer_circle">
            <button type="button" class="eq-select-btn" data-placeholder="— Select Circle —">
              <span class="eq-select-val">— Select Circle —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-options">
                <div class="eq-option" data-value="ccrc_lhr">CCRC - Lahore</div>
                <div class="eq-option" data-value="ccrc_khi">CCRC - Karachi</div>
                <div class="eq-option" data-value="ccrc_isb">CCRC - Islamabad</div>
                <div class="eq-option" data-value="ccrc_psh">CCRC - Peshawar</div>
                <div class="eq-option" data-value="ccrc_qta">CCRC - Quetta</div>
                <div class="eq-option" data-value="zd_lhr">Zonal Directorate - LHR</div>
                <div class="eq-option" data-value="hq">NCCIA HQs</div>
              </div>
            </div>
            <input type="hidden" name="transfer_circle">
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Incharge Remarks</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea" name="incharge_remarks" rows="3"
            placeholder="Additional observations, directions or notes from Circle Incharge…"></textarea>
        </div>
      </div>

    </div>

  </div>

  <!-- ════ STEP 4 — Legal & Outcome ════ -->
  <div class="cf-section animate-fade-up eq-step-panel" id="step4">

    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#2B2B2B;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Legal Branch &amp; Final Outcome</div>
        <div class="cf-section-sub">AD Legal, Additional Director and DD Legal review</div>
      </div>
      <div class="cf-section-badge">Step 04</div>
    </div>

    <div class="cf-body">

      <div class="cf-row-3">

        <div class="cf-field">
          <label class="cf-label required">AD Legal Opinion</label>
          <div class="eq-select-wrap" id="sel_ad_legal">
            <button type="button" class="eq-select-btn" data-placeholder="— Select Opinion —">
              <span class="eq-select-val">— Select Opinion —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-options">
                <div class="eq-option" data-value="register" data-icon="✅" data-desc="Legally sound for registration">Recommend Case Registration</div>
                <div class="eq-option" data-value="close"    data-icon="🔴" data-desc="Insufficient legal grounds">Recommend Closure</div>
                <div class="eq-option" data-value="further"  data-icon="🔄" data-desc="More investigation needed">Further Investigation Required</div>
                <div class="eq-option" data-value="refer"    data-icon="📋" data-desc="Refer to DD Legal">Refer to DD Legal</div>
              </div>
            </div>
            <input type="hidden" name="ad_legal_opinion" required>
          </div>
        </div>

        <div class="cf-field">
          <label class="cf-label required">Additional Director Decision</label>
          <div class="eq-select-wrap" id="sel_add_director">
            <button type="button" class="eq-select-btn" data-placeholder="— Select Decision —">
              <span class="eq-select-val">— Select Decision —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-options">
                <div class="eq-option" data-value="approve" data-icon="✅" data-desc="Approved for final action">Approve &amp; Forward</div>
                <div class="eq-option" data-value="review"  data-icon="🔄" data-desc="Sent back for review">Review Required</div>
                <div class="eq-option" data-value="dd_legal" data-icon="📋" data-desc="DD Legal opinion needed">Refer to DD Legal</div>
              </div>
            </div>
            <input type="hidden" name="add_director_decision" required>
          </div>
        </div>

        <div class="cf-field">
          <label class="cf-label">DD Legal Opinion (if referred)</label>
          <div class="eq-select-wrap" id="sel_dd_legal">
            <button type="button" class="eq-select-btn" data-placeholder="— Select Opinion —">
              <span class="eq-select-val">— Select Opinion —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-options">
                <div class="eq-option" data-value="na"       data-desc="Not applicable">N/A — Not Referred</div>
                <div class="eq-option" data-value="register" data-icon="✅" data-desc="Supports case registration">Support Registration</div>
                <div class="eq-option" data-value="close"    data-icon="🔴" data-desc="Supports closure">Support Closure</div>
                <div class="eq-option" data-value="further"  data-icon="🔄" data-desc="More work needed">Further Work Needed</div>
              </div>
            </div>
            <input type="hidden" name="dd_legal_opinion">
          </div>
        </div>

      </div>

      <div class="cf-field">
        <label class="cf-label required">Final Outcome</label>
        <div class="cf-check-group">
          <label class="cf-check-item">
            <input type="radio" name="final_outcome" value="case_registration" required>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#2B2B2B;">✓ Case Registration</span>
              <span class="cf-check-desc">FIR to be registered</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="final_outcome" value="closure">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">✗ Closure</span>
              <span class="cf-check-desc">Enquiry closed</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="final_outcome" value="merge">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">⊕ Merge</span>
              <span class="cf-check-desc">Merged with existing</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="final_outcome" value="transfer">
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">→ Transfer</span>
              <span class="cf-check-desc">Transferred out</span>
            </div>
          </label>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Legal Branch Notes</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea" name="legal_notes" rows="3"
            placeholder="Combined legal observations, precedents referenced, statutory provisions applied…"></textarea>
        </div>
      </div>

      <div class="cf-row-3">
        <div class="cf-field">
          <label class="cf-label">Submitted By</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg></span>
            <input type="text" class="cf-input" value="Muhammad Umar Ilyas" readonly>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Designation</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
            <input type="text" class="cf-input" value="Asst. Sub Inspector" readonly>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Submission Time</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
            <input type="datetime-local" class="cf-input" id="submitTime" readonly>
          </div>
        </div>
      </div>

    </div>

  </div>

  <!-- ══ Tracking Number Preview ══ -->
  <div class="cf-tracking-preview animate-fade-up">
    <div class="cf-tracking-left">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <div>
        <div class="cf-tracking-label">Enquiry Number (Auto-Generated on Submit)</div>
        <div class="cf-tracking-no" id="eqTrackingNo">LHR-E-—/25</div>
      </div>
    </div>
    <div class="cf-tracking-note">Format: <strong>ZONE-E-SERIAL/YEAR</strong> &nbsp;·&nbsp; e.g. LHR-E-0001/25</div>
  </div>

  <!-- ══ Form Actions ══ -->
  <div class="cf-form-actions animate-fade-up">
    <button type="button" class="btn btn-outline" onclick="resetEnquiry()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
      Reset Form
    </button>
    <div style="display:flex;gap:10px;">
      <button type="button" class="btn btn-outline" onclick="saveDraft()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Draft
      </button>
      <button type="submit" class="btn cf-submit-btn" id="eqSubmitBtn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Register Enquiry
      </button>
    </div>
  </div>

</form>
</div>
@endsection

@push('extra_css')
<style>

/* ── Custom Select ── */
.eq-select-wrap { position: relative; }

.eq-select-btn {
  width: 100%;
  height: 42px;
  padding: 0 14px 0 38px;
  border: 1.5px solid var(--border);
  border-radius: var(--border-radius-sm);
  background: var(--bg-body);
  font-size: 13px;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: left;
  font-family: inherit;
}
.eq-select-btn:hover,
.eq-select-btn.open {
  border-color: var(--border);
  /* background: #fff; */
  box-shadow: 0 0 0 3px rgba(43,43,43,0.12);
  color: var(--text-body);
}
.eq-select-val {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.eq-select-val.chosen { color: var(--text-heading); font-weight: 500; }
.eq-arrow { flex-shrink: 0; transition: transform 0.25s; color: var(--text-muted); margin-left: 8px; }
.eq-select-btn.open .eq-arrow { transform: rotate(180deg); }

.eq-select-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 0; right: 0;
  background: var(--bg-card);
  border: 1.5px solid var(--border);
  border-radius: var(--border-radius);
  box-shadow: 0 8px 32px rgba(43,43,43,0.16);
  z-index: 500;
  overflow: hidden;
  display: none;
  animation: eqDropIn 0.18s cubic-bezier(0.22,1,0.36,1) both;
}
.eq-select-dropdown.open { display: block; }

@keyframes eqDropIn {
  from { opacity: 0; transform: translateY(-6px) scale(0.98); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.eq-search-wrap { padding: 10px 10px 6px; border-bottom: 1px solid var(--border-light); }
.eq-search {
  width: 100%;
  height: 34px;
  padding: 0 12px;
  border: 1.5px solid var(--border);
  border-radius: 7px;
  font-size: 12.5px;
  font-family: inherit;
  outline: none;
  transition: border-color 0.2s;
}
.eq-search:focus { border-color: #2B2B2B; }

.eq-options { max-height: 220px; overflow-y: auto; padding: 4px 0; }

.eq-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 14px;
  font-size: 13px;
  color: var(--text-body);
  cursor: pointer;
  transition: background 0.15s;
  position: relative;
}
.eq-option:hover  { background: rgba(43,43,43,0.07); color: #2B2B2B; }
.eq-option.active { background: rgba(43,43,43,0.1); color: #2B2B2B; font-weight: 600; }
.eq-option[data-urgent="1"] { color: #2B2B2B; }
.eq-option[data-urgent="1"]:hover { background: rgba(43,43,43,0.07); }

.eq-option-icon { font-size: 14px; flex-shrink: 0; }
.eq-option-body { display: flex; flex-direction: column; gap: 1px; }
.eq-option-label { font-size: 13px; font-weight: 500; }
.eq-option-desc  { font-size: 11px; color: var(--text-muted); font-weight: 400; }
.eq-option.active .eq-option-desc { color: rgba(43,43,43,0.7); }

/* ── Linked Complaint Card ── */
.eq-linked-card {
  background: rgba(38,64,120,0.04);
  border: 1.5px solid rgba(38,64,120,0.2);
  border-radius: var(--border-radius-sm);
  padding: 14px 16px;
  margin-top: 4px;
}
.eq-linked-header {
  display: flex; align-items: center; gap: 7px;
  font-size: 11px; font-weight: 700;
  color: #264078;
  text-transform: uppercase; letter-spacing: 0.8px;
  margin-bottom: 12px;
}

/* ── Submit btn ── */
.cf-submit-btn {
  background: #2B2B2B;
  color: #fff;
  padding: 10px 24px;
  font-size: 13px;
  box-shadow: 0 4px 18px rgba(43,43,43,0.38);
  border: none;
  display: inline-flex; align-items: center; gap: 8px;
}
.cf-submit-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(43,43,43,0.5);
  background: #264078;
  color: #fff;
}

@media (max-width: 768px) {
  .eq-select-dropdown { position: fixed; left: 12px; right: 12px; top: auto; }
}

</style>
@endpush

@push('extra_js')
<script>
(function () {
  'use strict';

  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  const iso = now.toISOString().slice(0, 16);
  ['submitTime'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = iso;
  });

  document.querySelectorAll('input[type="date"]').forEach(el => {
    if (!el.value) el.value = now.toISOString().slice(0, 10);
  });

  /* ════════════════════════════════════
     CUSTOM DROPDOWN ENGINE
  ════════════════════════════════════ */
  function initCustomSelect(wrapId) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;

    const btn      = wrap.querySelector('.eq-select-btn');
    const valSpan  = wrap.querySelector('.eq-select-val');
    const dropdown = wrap.querySelector('.eq-select-dropdown');
    const hidden   = wrap.querySelector('input[type="hidden"]');
    const searchEl = wrap.querySelector('.eq-search');
    const optEls   = wrap.querySelectorAll('.eq-option');
    const placeholder = btn.dataset.placeholder;

    optEls.forEach(opt => {
      const icon = opt.dataset.icon;
      const desc = opt.dataset.desc;
      const label = opt.textContent.trim();
      if (icon || desc) {
        opt.innerHTML = '';
        if (icon) {
          const iconEl = document.createElement('span');
          iconEl.className = 'eq-option-icon';
          iconEl.textContent = icon;
          opt.appendChild(iconEl);
        }
        const body = document.createElement('div');
        body.className = 'eq-option-body';
        const labelEl = document.createElement('span');
        labelEl.className = 'eq-option-label';
        labelEl.textContent = label;
        body.appendChild(labelEl);
        if (desc) {
          const descEl = document.createElement('span');
          descEl.className = 'eq-option-desc';
          descEl.textContent = desc;
          body.appendChild(descEl);
        }
        opt.appendChild(body);
      }
    });

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = dropdown.classList.contains('open');
      closeAll();
      if (!isOpen) {
        dropdown.classList.add('open');
        btn.classList.add('open');
        if (searchEl) { searchEl.value = ''; filterOptions(''); searchEl.focus(); }
      }
    });

    if (searchEl) {
      searchEl.addEventListener('input', function () { filterOptions(this.value.toLowerCase()); });
      searchEl.addEventListener('click', e => e.stopPropagation());
    }

    function filterOptions(q) {
      optEls.forEach(opt => {
        const text = opt.textContent.toLowerCase();
        opt.style.display = text.includes(q) ? '' : 'none';
      });
    }

    optEls.forEach(opt => {
      opt.addEventListener('click', function (e) {
        e.stopPropagation();
        optEls.forEach(o => o.classList.remove('active'));
        this.classList.add('active');

        const labelEl = this.querySelector('.eq-option-label');
        const displayText = labelEl ? labelEl.textContent : this.textContent.trim();
        valSpan.textContent = displayText;
        valSpan.classList.add('chosen');
        hidden.value = this.dataset.value;

        dropdown.classList.remove('open');
        btn.classList.remove('open');

        wrap.dispatchEvent(new CustomEvent('eq:change', { detail: { value: this.dataset.value } }));
      });
    });
  }

  function closeAll() {
    document.querySelectorAll('.eq-select-dropdown').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.eq-select-btn').forEach(b => b.classList.remove('open'));
  }
  document.addEventListener('click', closeAll);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

  ['sel_circle_incharge','sel_enquiry_type','sel_enq_officer','sel_new_officer',
   'sel_ad_legal','sel_add_director','sel_dd_legal','sel_transfer_circle'].forEach(initCustomSelect);

  /* ════════════════════════════════════
     CONDITIONAL FIELDS
  ════════════════════════════════════ */
  document.querySelector('[name="linked_complaint"]').addEventListener('input', function () {
    const card = document.getElementById('linkedCard');
    if (this.value.length > 5) {
      card.style.display = 'block';
      document.getElementById('eqTrackingNo').textContent = 'LHR-E-' + Math.floor(Math.random()*900+100) + '/25';
    } else {
      card.style.display = 'none';
    }
  });

  document.querySelector('[value="notices"]').addEventListener('change', function () {
    document.getElementById('noticeDetailsWrap').style.display = this.checked ? 'block' : 'none';
  });

  document.querySelectorAll('[name="incharge_recommendation"]').forEach(r => {
    r.addEventListener('change', function () {
      document.getElementById('closureReasonWrap').style.display  = this.value === 'closure'  ? 'block' : 'none';
      document.getElementById('mergeRefWrap').style.display       = this.value === 'merge'    ? 'block' : 'none';
      document.getElementById('transferTargetWrap').style.display = this.value === 'transfer' ? 'block' : 'none';
      if (this.value === 'transfer') initCustomSelect('sel_transfer_circle');
    });
  });

  const cfrEl  = document.querySelector('[name="cfr_summary"]');
  const cfrCnt = document.getElementById('cfrCount');
  if (cfrEl && cfrCnt) {
    cfrEl.addEventListener('input', () => {
      cfrCnt.textContent = cfrEl.value.length;
      cfrCnt.style.color = cfrEl.value.length > 1800 ? '#2B2B2B' : 'var(--text-muted)';
    });
  }

  /* ── Submit loading state ── */
  document.getElementById('enquiryForm').addEventListener('submit', function () {
    const btn = document.getElementById('eqSubmitBtn');
    btn.innerHTML = '⏳ Registering…';
    btn.disabled = true;
  });

  window.resetEnquiry = function () {
    if (confirm('Reset all fields? Unsaved data will be lost.')) {
      document.getElementById('enquiryForm').reset();
      document.querySelectorAll('.eq-select-val').forEach(v => {
        const btn = v.closest('.eq-select-btn');
        v.textContent = btn ? btn.dataset.placeholder : '—';
        v.classList.remove('chosen');
      });
      document.querySelectorAll('input[type="hidden"]').forEach(h => h.value = '');
      document.querySelectorAll('.eq-conditional').forEach(c => c.style.display = 'none');
      document.getElementById('linkedCard').style.display = 'none';
      document.getElementById('step1').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  window.saveDraft = function () {
    showToast('✓ Draft saved successfully', 'success');
  };

  function showToast(msg, type) {
    const t = document.createElement('div');
    t.style.cssText = `
      position:fixed;bottom:28px;right:28px;z-index:9999;padding:13px 22px;
      border-radius:10px;font-size:13px;font-weight:600;
      box-shadow:0 8px 30px rgba(43,43,43,0.15);
      display:flex;align-items:center;gap:8px;color:#fff;
      animation:slideInToast 0.3s cubic-bezier(0.22,1,0.36,1) both;
      background:${type === 'success' ? '#2B2B2B' : '#264078'};
    `;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => {
      t.style.opacity = '0'; t.style.transform = 'translateY(10px)';
      t.style.transition = 'all 0.3s';
      setTimeout(() => t.remove(), 300);
    }, 3500);
  }

})();
</script>
@endpush
