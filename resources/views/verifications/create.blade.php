@extends('layouts.app')

@section('title', 'Assign Verification')

@section('page_content')

<div class="complaint-page">

<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Verifications</div>
    <h1 class="page-title">Assign Verification</h1>
    <p class="page-subtitle">Assign a complaint to a verification officer</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <a href="{{ route('verifications.index') }}" class="btn btn-outline btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Back
    </a>
  </div>
</div>

<div class="cf-section animate-fade-up animate-delay-1">
  <div class="cf-section-header">
    <div class="cf-section-icon" style="background:#27adff;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    </div>
    <div>
      <div class="cf-section-title">New Verification Assignment</div>
      <div class="cf-section-sub">Select complaint, officer and priority level</div>
    </div>
    <div class="cf-section-badge">CREATE</div>
  </div>
  <div class="cf-body">
    <form method="POST" action="{{ route('verifications.store') }}" novalidate>
      @csrf

      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label required">Select Complaint</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </span>
            <select name="complaint_id" class="cf-input cf-select" required>
              <option value="">— Select Complaint —</option>
              @foreach($complaints as $c)
              <option value="{{ $c->id }}" {{ old('complaint_id') == $c->id ? 'selected' : '' }}>
                {{ $c->tracking_no }} — {{ $c->complainant_name }}
              </option>
              @endforeach
            </select>
          </div>
          <span class="cf-hint">Only complete complaints without an existing verification are shown</span>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Assign Verification Officer</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
            </span>
            <select name="verification_officer_id" class="cf-input cf-select" required>
              <option value="">— Select Officer —</option>
              @foreach($officers as $o)
              <option value="{{ $o->id }}" {{ old('verification_officer_id') == $o->id ? 'selected' : '' }}>
                {{ $o->name }} ({{ $o->designation ?? $o->getRoleNames()->first() }})
              </option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label required">Priority Type</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </span>
            <select name="priority_type" id="priorityType" class="cf-input cf-select" required>
              <option value="regular" {{ old('priority_type') == 'regular' ? 'selected' : '' }}>Regular Process</option>
              <option value="court_directions" {{ old('priority_type') == 'court_directions' ? 'selected' : '' }}>Court Directions</option>
              <option value="anti_state" {{ old('priority_type') == 'anti_state' ? 'selected' : '' }}>Anti State</option>
              <option value="higher_authority" {{ old('priority_type') == 'higher_authority' ? 'selected' : '' }}>Directions of Higher Authority</option>
            </select>
          </div>
          <div id="dgWarning" style="display:none;margin-top:8px;padding:8px 12px;background:rgba(229,62,62,0.1);border:1px solid rgba(229,62,62,0.3);border-radius:8px;font-size:12px;color:#e53e3e;">
            ⚠ Director General will be notified of this priority case
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Notes / Initial Remarks</label>
          <div class="cf-input-wrap">
            <span class="cf-input-icon" style="align-items:flex-start;top:12px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </span>
            <textarea name="report_text" class="cf-input cf-textarea" placeholder="Add any initial remarks or notes..." style="padding-left:38px;min-height:80px;">{{ old('report_text') }}</textarea>
          </div>
        </div>
      </div>

      <div class="cf-form-actions">
        <div></div>
        <div style="display:flex;gap:10px;">
          <a href="{{ route('verifications.index') }}" class="btn btn-outline">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Assign Verification
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

</div>
@endsection

@push('extra_js')
<script>
(function() {
  const priorityEl = document.getElementById('priorityType');
  const warningEl = document.getElementById('dgWarning');
  if (priorityEl && warningEl) {
    function toggleWarning() {
      warningEl.style.display = priorityEl.value !== 'regular' ? 'block' : 'none';
    }
    priorityEl.addEventListener('change', toggleWarning);
    toggleWarning();
  }

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
