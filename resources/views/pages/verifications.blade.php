@extends('layouts.app')

@section('title', 'Verifications')

@section('page_content')

<!-- Page Header -->
<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Complaints</div>
    <h1 class="page-title">Verifications</h1>
    <p class="page-subtitle">Assigned verifications queue &nbsp;·&nbsp; CCRC-LHR Circle</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm" id="exportVerifBtn">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export
    </button>
    <a href="{{ route('verify.details') }}" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
      New Entry
    </a>
  </div>
</div>

<!-- Stats mini row -->
<div class="mini-stats-row animate-fade-up animate-delay-1">
  <div class="mini-stat">
    <div class="mini-stat-value" style="white">0</div>
    <div class="mini-stat-label">Total Assigned</div>
  </div>
  <div class="mini-stat">
    <div class="mini-stat-value" style="white">0</div>
    <div class="mini-stat-label">Pending Review</div>
  </div>
  <div class="mini-stat">
    <div class="mini-stat-value" style="white">0</div>
    <div class="mini-stat-label">Processed</div>
  </div>
  <div class="mini-stat">
    <div class="mini-stat-value" style="white">0</div>
    <div class="mini-stat-label">Due Tomorrow</div>
  </div>
</div>

<!-- Filters Bar -->
<div class="filters-bar animate-fade-up animate-delay-2">
  <span class="filter-label">Filter</span>
  <div class="filter-date-range">
    <input type="date" id="dateFrom" value="{{ now()->startOfYear()->format('Y-m-d') }}" aria-label="From date">
    <span class="date-sep">to</span>
    <input type="date" id="dateTo" value="{{ now()->format('Y-m-d') }}" aria-label="To date">
  </div>
  <select class="filter-select" id="statusFilter" aria-label="Status Filter">
    <option value="">All Status</option>
    <option value="pending">Pending</option>
    <option value="processed">Processed</option>
    <option value="notice">Summon Sent</option>
  </select>
  <div class="filter-spacer"></div>
  <button class="btn btn-primary btn-sm" id="applyFilterBtn">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
    Apply
  </button>
</div>

<!-- Verifications Table Card -->
<div class="card animate-fade-up animate-delay-3">
  <div class="card-header">
    <div class="card-title">
      <div class="card-icon">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      Assigned Verifications
    </div>
    <div class="section-actions">
      <span style="font-size:11.5px;color:var(--text-muted);">Last refreshed: <strong style="color:var(--text-body);">just now</strong></span>
      <button class="btn btn-outline btn-sm btn-icon" id="refreshTableBtn" title="Refresh table">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
      </button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="verifTable" aria-label="Verifications Table">
      <thead>
        <tr>
          <th style="width:40px;padding-left:20px;">
            <input type="checkbox" id="selectAll" style="cursor:pointer;accent-color:var(--accent-gold);">
          </th>
          <th>#</th>
          <th class="sortable" data-col="tracking">
            Tracking No.
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:4px;opacity:0.5;"><polyline points="6 9 12 15 18 9"/></svg>
          </th>
          <th class="sortable verif-table-cnic" data-col="cnic">CNIC</th>
          <th class="sortable" data-col="name">Complainant Name</th>
          <th class="sortable verif-table-date-col" data-col="date">Registration Date</th>
          <th>Status</th>
          <th style="text-align:center;">Process</th>
          <th style="text-align:center;">Add Summon</th>
          <th style="text-align:center;">View</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="10" style="text-align:center;padding:48px 20px;color:var(--text-muted);font-size:14px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><br>
            No verifications yet. Click <strong>New Entry</strong> to create one.
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

@endsection

@push('extra_css')
<style>
@keyframes spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
@keyframes slideInToast { from { opacity:0;transform:translateY(20px); } to { opacity:1;transform:translateY(0); } }
.sortable { cursor:pointer; user-select:none; }
</style>
@endpush

@push('extra_js')
<script>
(function(){
  'use strict';
  ['analytics','dac'].forEach(key => {
    const toggle  = document.getElementById(key+'Toggle');
    const submenu = document.getElementById(key+'Submenu');
    const arrow   = document.getElementById(key+'Arrow');
    if (!toggle || !submenu) return;
    toggle.addEventListener('click', e => {
      e.preventDefault();
      const open = submenu.classList.toggle('open');
      if (arrow) arrow.style.transform = open ? 'rotate(180deg)' : '';
    });
  });

  const notifBtn  = document.getElementById('notifBtn');
  const notifDrop = document.getElementById('notifDropdown');
  if (notifBtn && notifDrop) {
    notifBtn.addEventListener('click', e => { e.stopPropagation(); notifDrop.classList.toggle('open'); });
    document.addEventListener('click', () => notifDrop.classList.remove('open'));
    notifDrop.addEventListener('click', e => e.stopPropagation());
  }

  document.getElementById('refreshBtn')?.addEventListener('click', () => {
    document.getElementById('refreshBtn').style.animation = 'spin 0.7s linear';
    setTimeout(() => document.getElementById('refreshBtn').style.animation = '', 700);
  });

  document.getElementById('refreshTableBtn')?.addEventListener('click', () => {
    location.reload();
  });

  document.addEventListener('keydown', e => {
    if ((e.ctrlKey||e.metaKey) && e.key==='k') {
      e.preventDefault();
      document.getElementById('globalSearch')?.focus();
    }
  });
})();
</script>
@endpush
