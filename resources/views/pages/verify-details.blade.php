@extends('layouts.app')

@section('title', 'Verify Details')

@push('extra_css')
<style>
:root {
  --verify-primary: #015C94;
  --verify-gold: #FDDF00;
  --verify-bg: #f8f9fc;
}

.verify-wrapper {
  max-width: 840px;
  margin: 0 auto;
  padding: 24px 0;
}

.verify-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 24px rgba(1,92,148,0.08);
  overflow: hidden;
}

.verify-header {
  background: linear-gradient(135deg, #015C94 0%, #013b5e 100%);
  padding: 32px 40px;
  color: #fff;
}

.verify-header-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255,255,255,0.15);
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  margin-bottom: 12px;
}

.verify-header h1 {
  font-size: 22px;
  font-weight: 700;
  margin: 0 0 6px;
}

.verify-header p {
  margin: 0;
  font-size: 14px;
  opacity: 0.85;
}

.verify-body {
  padding: 32px 40px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: #2b2b2b;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.form-group label .required {
  color: #dc3545;
}

.form-group input {
  padding: 10px 14px;
  border: 1.5px solid #e0e0e0;
  border-radius: 10px;
  font-size: 14px;
  transition: border-color 0.2s, box-shadow 0.2s;
  background: #fff;
}

.form-group input:focus {
  outline: none;
  border-color: #015C94;
  box-shadow: 0 0 0 3px rgba(1,92,148,0.1);
}

.form-group input.error {
  border-color: #dc3545;
}

.field-error {
  font-size: 12px;
  color: #dc3545;
  margin-top: 4px;
  display: none;
}

.field-error.visible {
  display: block;
}

.btn-verify {
  width: 100%;
  padding: 12px;
  background: linear-gradient(135deg, #015C94 0%, #013b5e 100%);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-verify:hover { opacity: 0.92; }
.btn-verify:active { transform: scale(0.98); }
.btn-verify:disabled { opacity: 0.6; cursor: not-allowed; }

.spinner {
  display: none;
  width: 18px;
  height: 18px;
  border: 2.5px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

.btn-verify.loading .spinner { display: inline-block; }
.btn-verify.loading .btn-text { display: none; }

@keyframes spin { to { transform: rotate(360deg); } }

.result-section {
  margin-top: 24px;
  display: none;
}

.result-section.visible {
  display: block;
  animation: slideUp 0.5s ease;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

.verify-result-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 24px rgba(1,92,148,0.08);
  overflow: hidden;
  border: 1.5px solid #e8ecef;
}

.result-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 28px;
  border-bottom: 1px solid #e8ecef;
}

.result-status {
  display: flex;
  align-items: center;
  gap: 10px;
}

.status-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

.status-icon.success {
  background: #d4edda;
  color: #155724;
}

.status-icon.failure {
  background: #f8d7da;
  color: #721c24;
}

.status-text h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.status-text p {
  margin: 2px 0 0;
  font-size: 12px;
  color: #6c757d;
}

.result-seal {
  text-align: center;
}

.seal-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  border: 2.5px solid #015C94;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 800;
  color: #015C94;
  letter-spacing: 1px;
  text-transform: uppercase;
  line-height: 1.2;
  text-align: center;
}

.result-body {
  padding: 24px 28px;
}

.result-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.result-field {
  padding: 12px 16px;
  background: #f8f9fc;
  border-radius: 10px;
}

.result-field.full-width {
  grid-column: 1 / -1;
}

.result-field-label {
  font-size: 11px;
  font-weight: 600;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.result-field-value {
  font-size: 15px;
  font-weight: 600;
  color: #2b2b2b;
  word-break: break-word;
}

.result-footer {
  padding: 16px 28px;
  border-top: 1px solid #e8ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  color: #6c757d;
}

.result-footer strong {
  color: #2b2b2b;
}

@media (max-width: 640px) {
  .verify-header { padding: 24px 20px; }
  .verify-body { padding: 24px 20px; }
  .form-grid { grid-template-columns: 1fr; }
  .result-grid { grid-template-columns: 1fr; }
  .result-header { flex-direction: column; gap: 12px; }
}
</style>
@endpush

@section('page_content')
<div class="verify-wrapper">
  <div class="verify-card">
    <div class="verify-header">
      <div class="verify-header-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        NCCIA Secure Portal
      </div>
      <h1>Identity Verification</h1>
      <p>Enter your details below to verify your identity record.</p>
    </div>
    <div class="verify-body">
      <form id="verifyForm" method="POST" action="{{ route('verify.details') }}" novalidate>
        @csrf
        <div class="form-grid">
          <div class="form-group">
            <label for="name">Full Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Muhammad Umar Ilyas" required maxlength="100">
            <div class="field-error" id="nameError">Please enter a valid name.</div>
            @error('name') <div class="field-error visible">{{ $message }}</div> @enderror
          </div>
          <div class="form-group">
            <label for="email">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="e.g. umar@nccia.gov.pk" required maxlength="255">
            <div class="field-error" id="emailError">Please enter a valid email address.</div>
            @error('email') <div class="field-error visible">{{ $message }}</div> @enderror
          </div>
          <div class="form-group">
            <label for="phone">Phone Number <span class="required">*</span></label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="e.g. +92 300 1234567" required maxlength="20">
            <div class="field-error" id="phoneError">Please enter a valid phone number.</div>
            @error('phone') <div class="field-error visible">{{ $message }}</div> @enderror
          </div>
          <div class="form-group">
            <label for="verification_id">Verification ID <span class="required">*</span></label>
            <input type="text" id="verification_id" name="verification_id" value="{{ old('verification_id') }}" placeholder="e.g. NCCIA-VRF-001" required maxlength="50">
            <div class="field-error" id="verificationIdError">Please enter a valid verification ID.</div>
            @error('verification_id') <div class="field-error visible">{{ $message }}</div> @enderror
          </div>
          <div class="form-group full-width">
            <button type="submit" class="btn-verify" id="verifyBtn">
              <span class="spinner"></span>
              <span class="btn-text">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Verify Identity
              </span>
            </button>
          </div>
        </div>
      </form>

      @if ($data)
      <div class="result-section visible" id="resultSection">
        <div class="verify-result-card">
          <div class="result-header">
            <div class="result-status">
              <div class="status-icon success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="status-text">
                <h3>Verification Successful</h3>
                <p>Identity record matched and confirmed.</p>
              </div>
            </div>
            <div class="result-seal">
              <div class="seal-circle">VERIFIED</div>
            </div>
          </div>
          <div class="result-body">
            <div class="result-grid">
              <div class="result-field">
                <div class="result-field-label">Full Name</div>
                <div class="result-field-value">{{ $data['name'] }}</div>
              </div>
              <div class="result-field">
                <div class="result-field-label">Email Address</div>
                <div class="result-field-value">{{ $data['email'] }}</div>
              </div>
              <div class="result-field">
                <div class="result-field-label">Phone Number</div>
                <div class="result-field-value">{{ $data['phone'] }}</div>
              </div>
              <div class="result-field">
                <div class="result-field-label">Verification ID</div>
                <div class="result-field-value">{{ $data['verification_id'] }}</div>
              </div>
              <div class="result-field full-width">
                <div class="result-field-label">Verified By</div>
                <div class="result-field-value">{{ auth()->user()->name }} — {{ auth()->user()->designation ?? auth()->user()->getRoleNames()->first() ?? 'Officer' }}</div>
              </div>
            </div>
          </div>
          <div class="result-footer">
            <span>Verification Timestamp: <strong>{{ now()->format('d F Y, h:i A') }}</strong></span>
            <span>Ref: <strong>NCCIA/VRF/{{ now()->format('Ymd') }}/{{ substr(str_shuffle('0123456789'), 0, 4) }}</strong></span>
          </div>
        </div>
      </div>
      @endif
    </div>
  </div>
</div>
@endsection

@push('extra_js')
<script>
(function() {
  'use strict';

  const form = document.getElementById('verifyForm');
  const btn = document.getElementById('verifyBtn');
  const resultSection = document.getElementById('resultSection');

  if (!form) return;

  function showError(inputId, errorId) {
    const input = document.getElementById(inputId);
    const error = document.getElementById(errorId);
    if (input) input.classList.add('error');
    if (error) error.classList.add('visible');
  }

  function clearErrors() {
    document.querySelectorAll('.form-group input').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('.field-error').forEach(el => el.classList.remove('visible'));
  }

  function validateField(id, errorId, test) {
    const el = document.getElementById(id);
    if (!el) return true;
    if (!test(el.value)) {
      showError(id, errorId);
      return false;
    }
    return true;
  }

  form.addEventListener('submit', function(e) {
    clearErrors();

    const nameOk = validateField('name', 'nameError', v => /^[a-zA-Z\s\.'-]+$/.test(v.trim()) && v.trim().length >= 2);
    const emailOk = validateField('email', 'emailError', v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()));
    const phoneOk = validateField('phone', 'phoneError', v => /^[\d\+\-\(\)\s]+$/.test(v.trim()) && v.trim().length >= 7);
    const idOk = validateField('verification_id', 'verificationIdError', v => /^[a-zA-Z0-9\-]+$/.test(v.trim()) && v.trim().length >= 3);

    if (!nameOk || !emailOk || !phoneOk || !idOk) {
      e.preventDefault();
      return;
    }

    btn.classList.add('loading');
    btn.disabled = true;
  });

  if (resultSection) {
    setTimeout(function() {
      resultSection.classList.add('visible');
    }, 100);
  }
})();
</script>
@endpush
