@extends('layouts.app')

@section('title', 'Verification Report')

@section('page_content')

<div class="vr-page">

@if(session('success'))
  <div class="cf-banner cf-banner-success animate-fade-up">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <span>{{ session('success') }}</span>
  </div>
@endif

<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Verifications</div>
    <h1 class="page-title">Victim Verification Report</h1>
    <p class="page-subtitle">Record victim appearance &amp; verification findings</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <a href="{{ route('verifications.index') }}" class="btn btn-outline btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Back
    </a>
  </div>
</div>

<form id="vrForm" method="POST" action="{{ route('verifications.report-store') }}" enctype="multipart/form-data" novalidate>
  @csrf

  <!-- ════ META: Tracking / Dates ════ -->
  <div class="cf-section animate-fade-up animate-delay-1">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#015C94;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Tracking &amp; Dates</div>
        <div class="cf-section-sub">Pull complaint from system, then record key dates</div>
      </div>
      <div class="cf-section-badge">STEP 01</div>
    </div>
    <div class="cf-body">

      <div class="cf-row-3">
        <div class="cf-field">
          <label class="cf-label required">Tracking No.</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </span>
            <select name="tracking_no" id="trackingNo" class="cf-input cf-select" required>
              <option value="">— Select Tracking No. —</option>
              @foreach($complaints as $c)
                <option value="{{ $c->tracking_no }}"
                        data-complaint="{{ $c->id }}"
                        data-cnic="{{ $c->cnic }}"
                        data-phone="{{ $c->contact_no }}"
                        {{ old('tracking_no') == $c->tracking_no ? 'selected' : '' }}>
                  {{ $c->tracking_no }} — {{ $c->complainant_name }}
                </option>
              @endforeach
            </select>
          </div>
          <input type="hidden" name="complaint_id" id="complaintId" value="{{ old('complaint_id') }}">
          <span class="cf-hint">Auto-suggested from complaint record</span>
        </div>

        <div class="cf-field">
          <label class="cf-label">Assignment Date</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <input type="date" class="cf-input" name="assignment_date" value="{{ old('assignment_date') }}">
          </div>
          <span class="cf-hint">Date victim submitted on online portal</span>
        </div>

        <div class="cf-field">
          <label class="cf-label">Verification Date</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </span>
            <input type="date" class="cf-input" name="verification_date" value="{{ old('verification_date') }}">
          </div>
          <span class="cf-hint">Date victim appeared for verification</span>
        </div>
      </div>

    </div>
  </div>

  <!-- ════ VICTIM DATA ════ -->
  <div class="cf-section animate-fade-up animate-delay-2">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#2B2B2B;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Victim Data Form</div>
        <div class="cf-section-sub">Personal details of the complainant / victim</div>
      </div>
      <div class="cf-section-badge">STEP 02</div>
    </div>
    <div class="cf-body">

      <div class="cf-row-3">
        <div class="cf-field">
          <label class="cf-label required">Name</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <input type="text" class="cf-input" name="victim_name" id="victimName" placeholder="Full name" value="{{ old('victim_name') }}" required>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Father Name</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <input type="text" class="cf-input" name="victim_father_name" placeholder="Father's name" value="{{ old('victim_father_name') }}">
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Occupation</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
            <input type="text" class="cf-input" name="victim_occupation" placeholder="e.g. Business" value="{{ old('victim_occupation') }}">
          </div>
        </div>
      </div>

      <div class="cf-row-3">
        <div class="cf-field">
          <label class="cf-label">Gender</label>
          <div class="eq-select-wrap" id="sel_gender">
            <button type="button" class="eq-select-btn" data-placeholder="— Select Gender —">
              <span class="eq-select-val">— Select Gender —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-options">
                <div class="eq-option" data-value="male">Male</div>
                <div class="eq-option" data-value="female">Female</div>
                <div class="eq-option" data-value="other">Other</div>
              </div>
            </div>
            <input type="hidden" name="victim_gender" value="{{ old('victim_gender') }}">
          </div>
        </div>

        <div class="cf-field">
          <label class="cf-label required">CNIC</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h4M15 12h4M15 16h4M6 16h6"/></svg></span>
            <input type="text" class="cf-input font-mono" name="victim_cnic" id="victimCnic" inputmode="numeric" maxlength="15" placeholder="00000-0000000-0" value="{{ old('victim_cnic') }}" required>
          </div>
          <span class="cf-hint">13 digits with dashes (auto-formatted)</span>
        </div>

        <div class="cf-field">
          <label class="cf-label required">Phone No.</label>
          <div class="cf-phone-group">
            <div class="eq-select-wrap cf-phone-code" id="sel_country_code">
              <button type="button" class="eq-select-btn" data-placeholder="+92">
                <span class="eq-select-val chosen">+92</span>
                <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
              <div class="eq-select-dropdown">
                <div class="eq-search-wrap"><input type="text" class="eq-search" placeholder="Search country…"></div>
                <div class="eq-options" id="countryCodeOptions"></div>
              </div>
              <input type="hidden" name="victim_country_code" id="victimCountryCode" value="+92">
            </div>
            <div class="cf-input-wrap cf-phone-num">
              <input type="text" class="cf-input font-mono" name="victim_phone" id="victimPhone" inputmode="numeric" placeholder="3XX XXXXXXX" value="{{ old('victim_phone') }}" required>
            </div>
          </div>
          <span class="cf-hint" id="phoneHint">Pakistan: 10 digits after code</span>
        </div>
      </div>

    </div>
  </div>

  <!-- ════ CRIME ════ -->
  <div class="cf-section animate-fade-up animate-delay-2">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#264078;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Crime Details</div>
        <div class="cf-section-sub">Category, description &amp; location</div>
      </div>
      <div class="cf-section-badge">STEP 03</div>
    </div>
    <div class="cf-body">

      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label required">Crime Category</label>
          <div class="eq-select-wrap" id="sel_crime_category">
            <button type="button" class="eq-select-btn" data-placeholder="— Select Category —">
              <span class="eq-select-val">— Select Category —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-search-wrap"><input type="text" class="eq-search" placeholder="Search category…"></div>
              <div class="eq-options">
                @foreach($crimeCategories as $cat)
                  <div class="eq-option" data-value="{{ $cat->value ?? $cat->name }}">{{ $cat->name }}</div>
                @endforeach
              </div>
            </div>
            <input type="hidden" name="crime_category" value="{{ old('crime_category') }}" required>
          </div>
        </div>

        <div class="cf-field">
          <label class="cf-label required">City</label>
          <div class="eq-select-wrap" id="sel_city">
            <button type="button" class="eq-select-btn" data-placeholder="— Select City —">
              <span class="eq-select-val">— Select City —</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-search-wrap"><input type="text" class="eq-search" placeholder="Search city (Pakistan)…"></div>
              <div class="eq-options" id="cityOptions"></div>
            </div>
            <input type="hidden" name="city" value="{{ old('city') }}" required>
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Crime Description</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea cf-textarea-lg" name="crime_description" rows="4"
            placeholder="Provide complete description of the crime…">{{ old('crime_description') }}</textarea>
        </div>
        <div class="cf-char-count"><span id="crimeCount">0</span> / 5000 characters</div>
      </div>

    </div>
  </div>

  <!-- ════ ACCUSED ════ -->
  <div class="cf-section animate-fade-up animate-delay-2">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#015C94;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Accused Details</div>
        <div class="cf-section-sub">Known or unknown — add multiple accused if known</div>
      </div>
      <div class="cf-section-badge">STEP 04</div>
    </div>
    <div class="cf-body">

      <div class="cf-field">
        <div class="cf-check-group">
          <label class="cf-check-item">
            <input type="radio" name="accused_known" value="0" {{ old('accused_known', '0') == '0' ? 'checked' : '' }} checked>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#2B2B2B;">Unknown</span>
              <span class="cf-check-desc">Accused identity not known</span>
            </div>
          </label>
          <label class="cf-check-item">
            <input type="radio" name="accused_known" value="1" {{ old('accused_known') == '1' ? 'checked' : '' }}>
            <span class="cf-check-box cf-radio-box"></span>
            <div class="cf-check-content">
              <span class="cf-check-title" style="color:#264078;">Known</span>
              <span class="cf-check-desc">Provide accused information</span>
            </div>
          </label>
        </div>
      </div>

      <div class="cf-repeater" id="accusedRepeater" style="display:none;">
        <div id="accusedList"></div>
        <button type="button" class="btn btn-outline btn-sm" id="addAccusedBtn">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Accused
        </button>
      </div>

    </div>
  </div>

  <!-- ════ RECOMMENDATIONS ════ -->
  <div class="cf-section animate-fade-up animate-delay-2">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#2B2B2B;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Recommendations</div>
        <div class="cf-section-sub">Officer's recommendation on the case</div>
      </div>
      <div class="cf-section-badge">STEP 05</div>
    </div>
    <div class="cf-body">

      <div class="cf-field">
        <label class="cf-label">Short Description</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea" name="recommendation_short" rows="2"
            placeholder="Brief recommendation…">{{ old('recommendation_short') }}</textarea>
        </div>
        <div class="cf-char-count"><span id="recShortCount">0</span> / 2000 characters</div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Full Description</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea cf-textarea-lg" name="recommendation_full" rows="4"
            placeholder="Detailed recommendation &amp; rationale…">{{ old('recommendation_full') }}</textarea>
        </div>
        <div class="cf-char-count"><span id="recFullCount">0</span> / 10000 characters</div>
      </div>

    </div>
  </div>

  <!-- ════ EVIDENCE ════ -->
  <div class="cf-section animate-fade-up animate-delay-2">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#264078;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Evidence</div>
        <div class="cf-section-sub">Upload media files — add a short description for each</div>
      </div>
      <div class="cf-section-badge">STEP 06</div>
    </div>
    <div class="cf-body">

      <div class="cf-repeater" id="evidenceRepeater">
        <div id="evidenceList"></div>
        <button type="button" class="btn btn-outline btn-sm" id="addEvidenceBtn">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Evidence
        </button>
      </div>

    </div>
  </div>

  <!-- ════ DIGITAL SIGNATURE ════ -->
  <div class="cf-section animate-fade-up animate-delay-2">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#2B2B2B;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Digital Signature</div>
        <div class="cf-section-sub">Investigation Officer — upload your scanned signature image</div>
      </div>
      <div class="cf-section-badge">STEP 07</div>
    </div>
    <div class="cf-body">
      <div class="sig-upload-wrap" style="max-width:400px;">
        <label class="cf-file-wrap" id="sigFileLabel">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6"/><path d="M9 15l3-3 3 3"/></svg>
          <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">Click to upload signature image (PNG, JPG)</div>
          <input type="file" name="signature_file" id="sigFileInput" accept="image/png,image/jpeg,image/jpg">
          <div class="cf-file-name" id="sigFileName"></div>
        </label>
        <div id="sigPreview" style="display:none;margin-top:12px;padding:12px;border:1.5px solid var(--border-light);border-radius:var(--border-radius-sm);text-align:center;background:#fff;">
          <img id="sigPreviewImg" src="" alt="Signature preview" style="max-height:80px;">
        </div>
      </div>
    </div>
  </div>

  <!-- ════ INQUIRY / CASE ════ -->
  <div class="cf-section animate-fade-up animate-delay-2">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#015C94;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M4 4h16v16H4z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Reference Numbers</div>
        <div class="cf-section-sub">Inquiry &amp; case references</div>
      </div>
      <div class="cf-section-badge">STEP 08</div>
    </div>
    <div class="cf-body">

      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label">Inquiry No.</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span>
            <input type="text" class="cf-input font-mono" name="inquiry_no" placeholder="e.g. INQ-0001/25" value="{{ old('inquiry_no') }}">
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Case No.</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
            <input type="text" class="cf-input font-mono" name="case_no" placeholder="e.g. CCW-C-0001/25" value="{{ old('case_no') }}">
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="cf-form-actions animate-fade-up">
    <div></div>
    <div style="display:flex;gap:10px;">
      <a href="{{ route('verifications.reports') }}" class="btn btn-outline">Reset</a>
      <button type="submit" class="btn cf-submit-btn" id="vrSubmitBtn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Save Verification Report
      </button>
    </div>
  </div>

</form>
</div>
@endsection

@push('extra_css')
<style>
  .vr-page { max-width: 1120px; margin: 0 auto; }

  /* ── Tighter spacing + fix dropdown stacking ── */
  .cf-section { position: relative; margin-bottom: 14px; }
  .cf-body { padding: 18px 20px; }
  .vr-page .page-header { margin-bottom: 18px; }
  .cf-banner { margin-bottom: 14px; }
  .cf-form-actions { padding: 14px 0 4px; }
  .cf-repeater { gap: 12px; }

  /* ════ CUSTOM SEARCHABLE DROPDOWN ════ */
  .eq-select-wrap { position: relative; }

  .eq-select-btn {
    width: 100%;
    height: 42px;
    padding: 0 14px 0 14px;
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
    border-color: #7dd3fc;
    box-shadow: 0 0 0 3px rgba(2,132,199,0.12);
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
    box-shadow: 0 8px 32px rgba(2,132,199,0.18);
    z-index: 600;
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
  .eq-search:focus { border-color: #0284c7; }

  .eq-options { max-height: 230px; overflow-y: auto; padding: 4px 0; }

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
  .eq-option:hover  { background: rgba(2,132,199,0.07); color: #0284c7; }
  .eq-option.active {
    background: rgba(2,132,199,0.12);
    color: #0284c7;
    font-weight: 600;
  }

  .eq-option-icon { font-size: 14px; flex-shrink: 0; }
  .eq-option-body { display: flex; flex-direction: column; gap: 1px; }
  .eq-option-label { font-size: 13px; font-weight: 500; }
  .eq-option-desc  { font-size: 11px; color: var(--text-muted); font-weight: 400; }
  .eq-option.active .eq-option-desc { color: rgba(2,132,199,0.7); }

  @media (max-width: 768px) {
    .eq-select-dropdown { position: fixed; left: 12px; right: 12px; top: auto; max-height: 60vh; }
  }

  /* country-code dropdown is narrow — open wider to the right */
  #sel_country_code .eq-select-dropdown { width: 240px; right: 0; left: auto; }

  /* ── Section polish ── */
  .cf-section { transition: box-shadow .25s ease, transform .25s ease; }
  .cf-section:hover { box-shadow: var(--shadow-md, 0 10px 30px rgba(2,132,199,0.08)); }

  .cf-section-badge {
    background: linear-gradient(135deg, #0284c7, #7dd3fc) !important;
    box-shadow: 0 3px 10px rgba(2,132,199,0.3);
  }

  /* ── Success banner ── */
  .cf-banner {
    display:flex;align-items:center;gap:12px;
    padding:14px 18px;border-radius:var(--border-radius);
    font-size:13.5px;font-weight:600;margin-bottom:20px;
    animation: vrSlideDown .4s cubic-bezier(.22,1,.36,1) both;
  }
  .cf-banner svg { flex-shrink:0; }
  .cf-banner-success {
    background:linear-gradient(135deg, rgba(23,166,96,0.12), rgba(34,197,94,0.08));
    border:1.5px solid rgba(23,166,96,0.35);color:#15803d;
  }
  @keyframes vrSlideDown { from{opacity:0;transform:translateY(-10px);} to{opacity:1;transform:translateY(0);} }

  /* ── Phone group ── */
  .cf-phone-group { display:flex; gap:10px; align-items:stretch; }
  .cf-phone-code { flex:0 0 120px; }
  .cf-phone-num { flex:1; }
  .cf-phone-num .cf-input { padding-left:14px; } /* no icon here */

  /* ── Repeater cards ── */
  .cf-repeater { display:flex;flex-direction:column;gap:16px; }
  .cf-rep-card {
    border:1.5px solid var(--border-light);
    border-left:4px solid #7dd3fc;
    border-radius:var(--border-radius-sm);
    padding:16px 18px;
    background:var(--bg-body);
    box-shadow: var(--shadow-sm, 0 2px 10px rgba(43,43,43,0.05));
    position:relative;
    animation: vrCardIn .3s cubic-bezier(.22,1,.36,1) both;
  }
  @keyframes vrCardIn { from{opacity:0;transform:translateY(8px) scale(.99);} to{opacity:1;transform:translateY(0) scale(1);} }
  .cf-rep-card-header {
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:14px;
    padding-bottom:10px;
    border-bottom:1px dashed var(--border-light);
  }
  .cf-rep-title {
    font-size:12px;font-weight:700;color:var(--text-heading);
    text-transform:uppercase;letter-spacing:.6px;
    display:flex;align-items:center;gap:8px;
  }
  .cf-rep-title::before {
    content:"";width:8px;height:8px;border-radius:50%;
    background:linear-gradient(135deg,#0284c7,#7dd3fc);
    box-shadow:0 0 0 3px rgba(2,132,199,0.12);
  }
  .cf-rep-remove {
    border:none;background:rgba(229,62,62,0.1);color:#e53e3e;
    width:30px;height:30px;border-radius:8px;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    transition:all .2s;
  }
  .cf-rep-remove:hover { background:rgba(229,62,62,0.2); transform:rotate(90deg); }

  /* ── File upload ── */
  .cf-rep-file-row { display:flex;flex-direction:column;gap:12px; }
  .cf-file-wrap {
    border:1.5px dashed var(--border);
    border-radius:var(--border-radius-sm);
    padding:18px;text-align:center;cursor:pointer;
    transition:all .2s;background:rgba(2,132,199,0.02);
    display:flex;flex-direction:column;align-items:center;gap:6px;
  }
  .cf-file-wrap:hover,
  .cf-file-wrap.is-drag {
    border-color:#0284c7;background:rgba(2,132,199,0.05);transform:translateY(-1px);
  }
  .cf-file-wrap svg { color:#0284c7; }
  .cf-file-wrap input[type=file] { display:none; }
  .cf-file-name {
    font-size:12px;color:var(--text-body);margin-top:2px;word-break:break-all;
    background:rgba(2,132,199,0.08);padding:4px 10px;border-radius:20px;display:none;
  }
  .cf-file-name.has-file { display:inline-block; }

  /* ── Hide number spinners for CNIC / phone ── */
  input[type=text].font-mono::-webkit-outer-spin-button,
  input[type=text].font-mono::-webkit-inner-spin-button { -webkit-appearance:none;margin:0; }

  /* ── Add buttons (repeater triggers) ── */
  #addAccusedBtn, #addEvidenceBtn {
    align-self:flex-start;
    border-style:dashed !important;
    border-color:var(--border) !important;
    color:var(--primary,#0284c7) !important;
    transition:all .2s;
  }
  #addAccusedBtn:hover, #addEvidenceBtn:hover {
    border-color:#0284c7 !important;
    background:rgba(2,132,199,0.06) !important;
  }

  @media (max-width: 600px) {
    .cf-phone-group { flex-direction:column; }
    .cf-phone-code { flex:1; }
  }
</style>
@endpush

@push('extra_js')
<script>
(function () {
  'use strict';

  /* ════════ CNIC auto-format & validate ════════ */
  function bindCnic(el) {
    if (!el) return;
    el.addEventListener('input', function () {
      let digits = el.value.replace(/\D/g, '').slice(0, 13);
      let formatted = digits;
      if (digits.length > 5) formatted = digits.slice(0, 5) + '-' + digits.slice(5);
      if (digits.length > 12) formatted = digits.slice(0, 5) + '-' + digits.slice(5, 12) + '-' + digits.slice(12);
      el.value = formatted;
    });
  }
  bindCnic(document.getElementById('victimCnic'));

  /* ════════ COUNTRY CODES (phone validation) ════════ */
  const countryCodes = [
    { code:'+92', country:'Pakistan', digits:10 },
    { code:'+91', country:'India', digits:10 },
    { code:'+971', country:'UAE', digits:9 },
    { code:'+966', country:'Saudi Arabia', digits:9 },
    { code:'+44', country:'United Kingdom', digits:10 },
    { code:'+1', country:'USA / Canada', digits:10 },
    { code:'+880', country:'Bangladesh', digits:10 },
    { code:'+977', country:'Nepal', digits:10 },
    { code:'+94', country:'Sri Lanka', digits:9 },
    { code:'+61', country:'Australia', digits:9 },
    { code:'+60', country:'Malaysia', digits:9 },
    { code:'+65', country:'Singapore', digits:8 },
    { code:'+92', country:'Pakistan (alt)', digits:10 }
  ];
  const codeOptionsEl = document.getElementById('countryCodeOptions');
  const phoneHint = document.getElementById('phoneHint');
  countryCodes.forEach(c => {
    const o = document.createElement('div');
    o.className = 'eq-option';
    o.dataset.value = c.code;
    o.dataset.digits = c.digits;
    o.innerHTML = '<span class="eq-option-label">' + c.code + ' · ' + c.country + '</span><span class="eq-option-desc">' + c.digits + ' digits</span>';
    codeOptionsEl.appendChild(o);
  });
  function currentCodeDigits() {
    const sel = countryCodes.find(c => c.code === document.getElementById('victimCountryCode').value);
    return sel ? sel.digits : 10;
  }
  const victimPhone = document.getElementById('victimPhone');
  victimPhone.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, currentCodeDigits());
    phoneHint.textContent = 'Enter ' + currentCodeDigits() + ' digits after country code';
  });

  /* ════════ PAKISTAN CITIES (searchable dropdown) ════════ */
  const pakistanCities = [
    'Abbottabad','Adezai','Adilpur','Ahmadpur East','Ahmadpur West','Ajman','Akora Khattak','Alamdar Road','Alik Ghund','Alipur','Arifwala','Attock','Awaran','Baden','Bahawalnagar','Bahawalpur','Bannu','Barkhan','Batkhela','Bazdar','Bhag','Bhakkar','Bhalwal','Bhimber','Burewala','Chagai','Chakdara','Chakwal','Chaman','Charsadda','Chattar','Chhor','Chilas','Chiniot','Chishtian','Cho ki Mallah','Dadu','Dera Bugti','Dera Ghazi Khan','Dera Ismail Khan','Depalpur','Dera Murad Jamali','Dera Nawab','Daska','Dera Ismail Khan','Digri','Dina','Dinga','Diplo','Drosh','Duki','Faisalabad','Fateh Jang','Gadar','Gandava','Garhi Khairo','Ghakhar','Ghanghra','Ghotki','Gilgit','Gulistan','Gwadar','Hafizabad','Hala','Haripur','Haveli Lakha','Hub','Hyderabad','Islamabad','Jacobabad','Jaffarabad','Jahanian','Jalalpur Pirwala','Jamshoro','Jhang','Jhelum','Kabal','Kahuta','Kalat','Kamalia','Kambar','Kandiari','Kandiaro','Karachi','Kashmore','Kasur','Khairpur','Khanewal','Kharian','Kharpur','Khushab','Khuzdar','Killa Abdullah','Killa Saifullah','Kohat','Kohlu','Korangi','Kotri','Kundian','Kunri','Lahore','Lakki Marwat','Larkana','Lasbela','Layyah','Lodhran','Loralai','Mailsi','Malakand','Mandi Bahauddin','Mardan','Mastung','Matiari','Mehar','Mian Channu','Mianwali','Mirpur','Mirpur Khas','Mithi','Mohmand','Multan','Musa Qala','Muzaffarabad','Muzaffargarh','Nankana Sahib','Narowal','Nasirabad','Naukundi','Nawabshah','Noshki','Nowshera','Okara','Pad Idan','Panjgur','Pano Aqil','Parachinar','Pasni','Peshawar','Phalia','Pind Dadan Khan','Pishin','Qambar','Qila Didar Singh','Qurbanpur','Quetta','Rahim Yar Khan','Raiwind','Rajanpur','Ranjha','Rawalpindi','Razmak','Rojhan','Sadiqabad','Safdarabad','Sahiwal','Sakrand','Sanghar','Sargodha','Shahdadkot','Shahdadpur','Shahpur','Sheikhupura','Shikarpur','Shorkot','Sialkot','Sibi','Sohawa','Sukkur','Surab','Swabi','Swat','Tando Adam','Tando Allahyar','Tando Jam','Tank','Taunsa','Thall','Thatta','Toba Tek Singh','Turbat','Umerkot','Usta Muhammad','Vehari','Wah Cantt','Wazirabad','Wadh','Zhob','Ziarat'
  ];
  const cityOptionsEl = document.getElementById('cityOptions');
  pakistanCities.forEach(city => {
    const o = document.createElement('div');
    o.className = 'eq-option';
    o.dataset.value = city;
    o.innerHTML = '<span class="eq-option-label">' + city + '</span>';
    cityOptionsEl.appendChild(o);
  });

  /* ════════ CUSTOM SELECT ENGINE ════════ */
  function initCustomSelect(wrapId) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    const btn = wrap.querySelector('.eq-select-btn');
    const valSpan = wrap.querySelector('.eq-select-val');
    const dropdown = wrap.querySelector('.eq-select-dropdown');
    const hidden = wrap.querySelector('input[type="hidden"]');
    const searchEl = wrap.querySelector('.eq-search');
    const optEls = Array.from(wrap.querySelectorAll('.eq-option'));
    const placeholder = btn.dataset.placeholder;

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = dropdown.classList.contains('open');
      closeAll();
      if (!isOpen) {
        dropdown.classList.add('open');
        btn.classList.add('open');
        const sec = wrap.closest('.cf-section');
        if (sec) sec.style.zIndex = '100';
        if (searchEl) { searchEl.value = ''; filterOptions(''); searchEl.focus(); }
      }
    });
    if (searchEl) {
      searchEl.addEventListener('input', function () { filterOptions(this.value.toLowerCase()); });
      searchEl.addEventListener('click', e => e.stopPropagation());
    }
    function filterOptions(q) {
      optEls.forEach(opt => {
        opt.style.display = opt.textContent.toLowerCase().includes(q) ? '' : 'none';
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
        if (hidden) hidden.value = this.dataset.value;
        closeAll();
      });
    });
  }
  function closeAll() {
    document.querySelectorAll('.eq-select-dropdown').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.eq-select-btn').forEach(b => b.classList.remove('open'));
    document.querySelectorAll('.cf-section').forEach(s => s.style.zIndex = '');
  }
  document.addEventListener('click', closeAll);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

  ['sel_gender','sel_country_code','sel_crime_category','sel_city'].forEach(initCustomSelect);

  /* ════════ TRACKING → auto prefill ════════ */
  document.getElementById('trackingNo').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    document.getElementById('complaintId').value = opt.dataset.complaint || '';
    if (opt.dataset.cnic) document.getElementById('victimCnic').value = opt.dataset.cnic;
    if (opt.dataset.phone) document.getElementById('victimPhone').value = opt.dataset.phone.replace(/\D/g,'').slice(0,10);
  });

  /* ════════ ACCUSED REPEATER ════════ */
  const accusedList = document.getElementById('accusedList');
  const accusedRepeater = document.getElementById('accusedRepeater');
  let accusedCount = 0;
  function addAccused() {
    accusedCount++;
    const idx = accusedCount;
    const card = document.createElement('div');
    card.className = 'cf-rep-card';
    card.innerHTML = `
      <div class="cf-rep-card-header">
        <span class="cf-rep-title">Accused #${idx}</span>
        <button type="button" class="cf-rep-remove" title="Remove">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label">Name</label>
          <div class="cf-input-wrap"><span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <input type="text" class="cf-input" name="accused[${idx}][name]" placeholder="Accused name"></div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Father Name</label>
          <div class="cf-input-wrap"><span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <input type="text" class="cf-input" name="accused[${idx}][father_name]" placeholder="Father's name"></div>
        </div>
      </div>
      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label">Phone</label>
          <div class="cf-input-wrap"><span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
            <input type="text" class="cf-input font-mono" name="accused[${idx}][phone]" placeholder="Phone no."></div>
        </div>
        <div class="cf-field">
          <label class="cf-label">CNIC</label>
          <div class="cf-input-wrap"><span class="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h4M15 12h4M15 16h4M6 16h6"/></svg></span>
            <input type="text" class="cf-input font-mono accused-cnic" name="accused[${idx}][cnic]" maxlength="15" inputmode="numeric" placeholder="00000-0000000-0"></div>
        </div>
      </div>
      <div class="cf-field">
        <label class="cf-label">Address</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea" name="accused[${idx}][address]" rows="2" placeholder="Residential / address"></textarea>
        </div>
      </div>
    `;
    card.querySelector('.cf-rep-remove').addEventListener('click', function () {
      card.remove();
      renumberAccused();
    });
    bindCnic(card.querySelector('.accused-cnic'));
    accusedList.appendChild(card);
  }
  function renumberAccused() {
    Array.from(accusedList.children).forEach((card, i) => {
      card.querySelector('.cf-rep-title').textContent = 'Accused #' + (i + 1);
    });
  }
  document.getElementById('addAccusedBtn').addEventListener('click', addAccused);
  document.querySelectorAll('input[name="accused_known"]').forEach(r => {
    r.addEventListener('change', function () {
      accusedRepeater.style.display = this.value === '1' ? 'block' : 'none';
      if (this.value === '1' && accusedList.children.length === 0) addAccused();
    });
  });
  if (document.querySelector('input[name="accused_known"]:checked').value === '1') {
    accusedRepeater.style.display = 'block';
    addAccused();
  }

  /* ════════ EVIDENCE REPEATER ════════ */
  const evidenceList = document.getElementById('evidenceList');
  let evidenceCount = 0;
  function addEvidence(name) {
    evidenceCount++;
    const idx = evidenceCount;
    const card = document.createElement('div');
    card.className = 'cf-rep-card';
    card.innerHTML = `
      <div class="cf-rep-card-header">
        <span class="cf-rep-title">Evidence #${idx}</span>
        <button type="button" class="cf-rep-remove" title="Remove">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="cf-rep-file-row">
        <label class="cf-file-wrap">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Click to upload media file</div>
          <input type="file" name="evidence_file[]" accept="image/*,video/*,application/pdf,.doc,.docx">
          <div class="cf-file-name"></div>
        </label>
        <div class="cf-input-wrap">
          <input type="text" class="cf-input" name="evidence_desc[]" placeholder="Short description of this evidence">
        </div>
      </div>
    `;
    const fileInput = card.querySelector('input[type="file"]');
    const nameEl = card.querySelector('.cf-file-name');
    const fileWrap = card.querySelector('.cf-file-wrap');
    fileInput.addEventListener('change', function () {
      if (this.files.length) {
        nameEl.textContent = this.files[0].name;
        nameEl.classList.add('has-file');
      } else {
        nameEl.textContent = '';
        nameEl.classList.remove('has-file');
      }
    });
    ['dragover','dragenter'].forEach(ev => fileWrap.addEventListener(ev, e => { e.preventDefault(); fileWrap.classList.add('is-drag'); }));
    ['dragleave','drop'].forEach(ev => fileWrap.addEventListener(ev, e => { e.preventDefault(); fileWrap.classList.remove('is-drag'); }));
    card.querySelector('.cf-rep-remove').addEventListener('click', function () {
      card.remove();
      renumberEvidence();
    });
    evidenceList.appendChild(card);
  }
  function renumberEvidence() {
    Array.from(evidenceList.children).forEach((card, i) => {
      card.querySelector('.cf-rep-title').textContent = 'Evidence #' + (i + 1);
    });
  }
  document.getElementById('addEvidenceBtn').addEventListener('click', function () { addEvidence(); });
  addEvidence(); // start with one

  /* ════════ CHAR COUNTS ════════ */
  const crimeEl = document.querySelector('[name="crime_description"]');
  if (crimeEl) crimeEl.addEventListener('input', () => document.getElementById('crimeCount').textContent = crimeEl.value.length);
  const recShort = document.querySelector('[name="recommendation_short"]');
  if (recShort) recShort.addEventListener('input', () => document.getElementById('recShortCount').textContent = recShort.value.length);
  const recFull = document.querySelector('[name="recommendation_full"]');
  if (recFull) recFull.addEventListener('input', () => document.getElementById('recFullCount').textContent = recFull.value.length);

  /* ════════ SUBMIT VALIDATION ════════ */
  document.getElementById('vrForm').addEventListener('submit', function (e) {
    let ok = true;
    const msgs = [];
    const cnic = document.getElementById('victimCnic');
    if (cnic.value && !/^\d{5}-\d{7}-\d{1}$/.test(cnic.value)) {
      ok = false; msgs.push('Victim CNIC must be 13 digits (00000-0000000-0).');
    }
    const phoneDigits = document.getElementById('victimPhone').value.replace(/\D/g,'').length;
    if (phoneDigits !== currentCodeDigits()) {
      ok = false; msgs.push('Phone number must be ' + currentCodeDigits() + ' digits for the selected country code.');
    }
    document.querySelectorAll('.accused-cnic').forEach(el => {
      if (el.value && !/^\d{5}-\d{7}-\d{1}$/.test(el.value)) {
        ok = false; msgs.push('Each accused CNIC must be 13 digits (00000-0000000-0).');
      }
    });
    if (!ok) {
      e.preventDefault();
      msgs.forEach(m => showToast(m, 'error'));
      return;
    }
    const btn = document.getElementById('vrSubmitBtn');
    btn.innerHTML = '⏳ Saving…';
    btn.disabled = true;
  });

  function showToast(msg, type) {
    const t = document.createElement('div');
    t.style.cssText = `
      position:fixed;bottom:28px;right:28px;z-index:9999;padding:13px 22px;
      border-radius:10px;font-size:13px;font-weight:600;max-width:360px;
      box-shadow:0 8px 30px rgba(43,43,43,0.15);
      display:flex;align-items:center;gap:8px;color:#fff;
      animation:slideInToast 0.3s cubic-bezier(0.22,1,0.36,1) both;
      background:${type === 'success' ? '#2B2B2B' : '#e53e3e'};
    `;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => {
      t.style.opacity = '0'; t.style.transform = 'translateY(10px)';
      t.style.transition = 'all 0.3s';
      setTimeout(() => t.remove(), 300);
    }, 4500);
  }

  @if($errors->any())
  setTimeout(() => {
    @foreach($errors->all() as $err)
    showToast('{{ str_replace(["'", "\n"], ["\\'", " "], $err) }}', 'error');
    @endforeach
  }, 300);
  @endif

  /* ════════ SIGNATURE FILE UPLOAD PREVIEW ════════ */
  const sigFileInput = document.getElementById('sigFileInput');
  const sigFileLabel = document.getElementById('sigFileLabel');
  const sigFileName = document.getElementById('sigFileName');
  const sigPreview = document.getElementById('sigPreview');
  const sigPreviewImg = document.getElementById('sigPreviewImg');

  sigFileInput.addEventListener('change', function () {
    if (this.files.length) {
      sigFileName.textContent = this.files[0].name;
      sigFileName.classList.add('has-file');

      const reader = new FileReader();
      reader.onload = function (e) {
        sigPreviewImg.src = e.target.result;
        sigPreview.style.display = 'block';
      };
      reader.readAsDataURL(this.files[0]);
    } else {
      sigFileName.textContent = '';
      sigFileName.classList.remove('has-file');
      sigPreview.style.display = 'none';
    }
  });
})();
</script>
@endpush
