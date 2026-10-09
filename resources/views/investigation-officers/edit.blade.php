@extends('layouts.app')

@section('title', 'Edit Investigation Officer')

@section('page_content')

<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Records</div>
    <h1 class="page-title">Edit Investigation Officer</h1>
    <p class="page-subtitle">{{ $investigationOfficer->name }} ({{ $investigationOfficer->badge_no }})</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <a href="{{ route('investigation-officers.index') }}" class="btn btn-outline btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Back
    </a>
  </div>
</div>

<form method="POST" action="{{ route('investigation-officers.update', $investigationOfficer) }}" class="form-standard">
  @csrf @method('PUT')

  <div class="cf-section animate-fade-up animate-delay-1">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#2B2B2B;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Personal Information</div>
        <div class="cf-section-sub">Basic details of the officer</div>
      </div>
    </div>
    <div class="cf-body">
      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label required">Full Name</label>
          <div class="cf-input-wrap">
            <input type="text" class="cf-input" name="name" value="{{ old('name', $investigationOfficer->name) }}" required>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Badge No.</label>
          <div class="cf-input-wrap">
            <input type="text" class="cf-input" name="badge_no" value="{{ old('badge_no', $investigationOfficer->badge_no) }}" required>
          </div>
        </div>
      </div>

      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label">Designation</label>
          <div class="cf-input-wrap">
            <input type="text" class="cf-input" name="designation" value="{{ old('designation', $investigationOfficer->designation) }}">
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Date of Joining</label>
          <div class="cf-input-wrap">
            <input type="date" class="cf-input" name="date_of_joining" value="{{ old('date_of_joining', $investigationOfficer->date_of_joining?->format('Y-m-d')) }}">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="cf-section animate-fade-up animate-delay-2">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#264078;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Posting &amp; Contact</div>
        <div class="cf-section-sub">Circle, zone and contact details</div>
      </div>
    </div>
    <div class="cf-body">
      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label">Circle</label>
          <div class="cf-input-wrap">
            <input type="text" class="cf-input" name="circle" value="{{ old('circle', $investigationOfficer->circle) }}">
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Zone</label>
          <div class="cf-input-wrap">
            <input type="text" class="cf-input" name="zone" value="{{ old('zone', $investigationOfficer->zone) }}">
          </div>
        </div>
      </div>

      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label">Contact No.</label>
          <div class="cf-input-wrap">
            <input type="text" class="cf-input" name="contact_no" value="{{ old('contact_no', $investigationOfficer->contact_no) }}">
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label">Email</label>
          <div class="cf-input-wrap">
            <input type="email" class="cf-input" name="email" value="{{ old('email', $investigationOfficer->email) }}">
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Address</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea" name="address" rows="2">{{ old('address', $investigationOfficer->address) }}</textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="cf-section animate-fade-up animate-delay-3">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#015C94;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Status &amp; Remarks</div>
        <div class="cf-section-sub">Current status and additional notes</div>
      </div>
    </div>
    <div class="cf-body">
      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label">Status</label>
          <div class="eq-select-wrap" id="sel_status">
            <button type="button" class="eq-select-btn" data-placeholder="— Select Status —">
              <span class="eq-select-val chosen">{{ ucfirst($investigationOfficer->status) }}</span>
              <svg class="eq-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="eq-select-dropdown">
              <div class="eq-options">
                <div class="eq-option {{ $investigationOfficer->status === 'active' ? 'active' : '' }}" data-value="active">Active</div>
                <div class="eq-option {{ $investigationOfficer->status === 'inactive' ? 'active' : '' }}" data-value="inactive">Inactive</div>
              </div>
            </div>
            <input type="hidden" name="status" value="{{ $investigationOfficer->status }}">
          </div>
        </div>
        <div class="cf-field"></div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Remarks</label>
        <div class="cf-input-wrap">
          <textarea class="cf-input cf-textarea" name="remarks" rows="3">{{ old('remarks', $investigationOfficer->remarks) }}</textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="cf-form-actions animate-fade-up">
    <div></div>
    <div style="display:flex;gap:10px;">
      <a href="{{ route('investigation-officers.index') }}" class="btn btn-outline">Cancel</a>
      <button type="submit" class="btn cf-submit-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        Update Officer
      </button>
    </div>
  </div>

</form>

@endsection

@push('extra_css')
<style>
.form-standard { max-width: 820px; margin: 0 auto; }
.cf-section { margin-bottom: 14px; }
.cf-body { padding: 18px 20px; }
.cf-form-actions { padding: 14px 0 4px; max-width: 820px; margin: 0 auto; }

.eq-select-wrap { position: relative; }
.eq-select-btn {
  width: 100%; height: 42px; padding: 0 14px; border: 1.5px solid var(--border);
  border-radius: var(--border-radius-sm); background: var(--bg-body);
  font-size: 13px; color: var(--text-muted);
  display: flex; align-items: center; justify-content: space-between; cursor: pointer;
  text-align: left; font-family: inherit;
}
.eq-select-btn:hover, .eq-select-btn.open {
  border-color: #7dd3fc; box-shadow: 0 0 0 3px rgba(2,132,199,0.12); color: var(--text-body);
}
.eq-select-val.chosen { color: var(--text-heading); font-weight: 500; }
.eq-arrow { transition: transform 0.25s; }
.eq-select-btn.open .eq-arrow { transform: rotate(180deg); }
.eq-select-dropdown {
  position: absolute; top: calc(100% + 6px); left: 0; right: 0;
  background: var(--bg-card); border: 1.5px solid var(--border);
  border-radius: var(--border-radius); box-shadow: 0 8px 32px rgba(2,132,199,0.18);
  z-index: 600; overflow: hidden; display: none;
}
.eq-select-dropdown.open { display: block; }
.eq-options { max-height: 150px; overflow-y: auto; padding: 4px 0; }
.eq-option {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 14px; font-size: 13px; color: var(--text-body);
  cursor: pointer; transition: background 0.15s;
}
.eq-option:hover { background: rgba(2,132,199,0.07); color: #0284c7; }
.eq-option.active { background: rgba(2,132,199,0.12); color: #0284c7; font-weight: 600; }
</style>
@endpush

@push('extra_js')
<script>
(function () {
  function initCustomSelect(wrapId) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    const btn = wrap.querySelector('.eq-select-btn');
    const valSpan = wrap.querySelector('.eq-select-val');
    const dropdown = wrap.querySelector('.eq-select-dropdown');
    const hidden = wrap.querySelector('input[type="hidden"]');
    const optEls = Array.from(wrap.querySelectorAll('.eq-option'));
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = dropdown.classList.contains('open');
      document.querySelectorAll('.eq-select-dropdown').forEach(d => d.classList.remove('open'));
      document.querySelectorAll('.eq-select-btn').forEach(b => b.classList.remove('open'));
      if (!isOpen) { dropdown.classList.add('open'); btn.classList.add('open'); }
    });
    optEls.forEach(opt => {
      opt.addEventListener('click', function () {
        optEls.forEach(o => o.classList.remove('active'));
        this.classList.add('active');
        valSpan.textContent = this.textContent.trim();
        valSpan.classList.add('chosen');
        if (hidden) hidden.value = this.dataset.value;
        dropdown.classList.remove('open');
        btn.classList.remove('open');
      });
    });
  }
  document.addEventListener('click', function () {
    document.querySelectorAll('.eq-select-dropdown').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.eq-select-btn').forEach(b => b.classList.remove('open'));
  });
  initCustomSelect('sel_status');
})();
</script>
@endpush
