@extends('layouts.app')

@section('title', 'Edit Crime Category')

@section('page_content')

<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Reference</div>
    <h1 class="page-title">Edit Crime Category</h1>
    <p class="page-subtitle">{{ $offenceType->name }}</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <a href="{{ route('offence-types.index') }}" class="btn btn-outline btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Back
    </a>
  </div>
</div>

<form method="POST" action="{{ route('offence-types.update', $offenceType) }}" class="form-standard">
  @csrf @method('PUT')

  <div class="cf-section animate-fade-up animate-delay-1">
    <div class="cf-section-header">
      <div class="cf-section-icon" style="background:#2B2B2B;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      </div>
      <div>
        <div class="cf-section-title">Category Details</div>
        <div class="cf-section-sub">Update the crime category information</div>
      </div>
    </div>
    <div class="cf-body">
      <div class="cf-row-2">
        <div class="cf-field">
          <label class="cf-label required">Name</label>
          <div class="cf-input-wrap">
            <input type="text" class="cf-input" name="name" value="{{ old('name', $offenceType->name) }}" required>
          </div>
        </div>
        <div class="cf-field">
          <label class="cf-label required">Value</label>
          <div class="cf-input-wrap">
            <input type="text" class="cf-input" name="value" value="{{ old('value', $offenceType->value) }}" required>
          </div>
        </div>
      </div>

      <div class="cf-field">
        <label class="cf-label">Group</label>
        <div class="cf-input-wrap">
          <input type="text" class="cf-input" name="group" value="{{ old('group', $offenceType->group) }}" placeholder="e.g. Financial Crimes, Privacy & Harassment, Technical Crimes">
        </div>
      </div>
    </div>
  </div>

  <div class="cf-form-actions animate-fade-up">
    <div></div>
    <div style="display:flex;gap:10px;">
      <a href="{{ route('offence-types.index') }}" class="btn btn-outline">Cancel</a>
      <button type="submit" class="btn cf-submit-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        Update Category
      </button>
    </div>
  </div>

</form>

@endsection

@push('extra_css')
<style>
.form-standard { max-width: 620px; margin: 0 auto; }
.cf-section { margin-bottom: 14px; }
.cf-body { padding: 18px 20px; }
.cf-form-actions { padding: 14px 0 4px; max-width: 620px; margin: 0 auto; }
</style>
@endpush
