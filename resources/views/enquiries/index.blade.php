@extends('layouts.app')

@section('title', 'Enquiries')

@section('page_content')

@if(session('success'))
@endif

<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Complaints</div>
    <h1 class="page-title">Enquiries</h1>
    <p class="page-subtitle">Manage complaint enquiries &nbsp;·&nbsp; CCRC-LHR</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <a href="{{ route('enquiries.create') }}" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
      Create New
    </a>
  </div>
</div>

<div class="mini-stats-row animate-fade-up animate-delay-1">
  <div class="mini-stat">
    <div class="mini-stat-value">{{ $enquiries->total() }}</div>
    <div class="mini-stat-label">Total Enquiries</div>
  </div>
  <div class="mini-stat">
    <div class="mini-stat-value">{{ $stats['pending'] }}</div>
    <div class="mini-stat-label">Pending Assignment</div>
  </div>
  <div class="mini-stat">
    <div class="mini-stat-value">{{ $stats['progress'] }}</div>
    <div class="mini-stat-label">In Progress</div>
  </div>
  <div class="mini-stat">
    <div class="mini-stat-value">{{ $stats['approved'] }}</div>
    <div class="mini-stat-label">Completed</div>
  </div>
</div>

<div class="filters-bar animate-fade-up animate-delay-2">
  <span class="filter-label">Filter</span>
  <input type="text" id="searchInput" class="filter-select" style="height:34px;padding:0 12px;width:260px;" placeholder="Search by Enquiry No or complainant name...">
  <select class="filter-select" id="statusFilter">
    <option value="">All Statuses</option>
    <option value="registered">Registered</option>
    <option value="assigned">Assigned</option>
    <option value="in_progress">In Progress</option>
    <option value="cfr_submitted">CFR Submitted</option>
    <option value="approved">Approved</option>
  </select>
  <div class="filter-spacer"></div>
</div>

<div class="card animate-fade-up animate-delay-3">
  <div class="card-header">
    <div class="card-title">
      <div class="card-icon">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      Enquiry Records
    </div>
    <div class="section-actions">
      <span style="font-size:11.5px;color:var(--text-muted);">Total: <strong style="color:var(--text-body);">{{ $enquiries->total() }}</strong></span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="enqTable" style="display:table;width:100%;">
      <thead>
        <tr>
          <th>#</th>
          <th>Enquiry No</th>
          <th>Complainant</th>
          <th>Assigned Officer</th>
          <th>Status</th>
          <th>Registered Date</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($enquiries as $e)
        @php
          $statusColors = [
            'registered'=>'badge-pending',
            'assigned'=>'badge-active', 
            'in_progress'=>'badge-info', 
            'cfr_submitted'=>'badge-pending', 
            'approved'=>'badge-finalized'
            ];
        @endphp
        <tr>
          <td><span style="font-size:12px;color:var(--text-muted);">{{ $enquiries->firstItem() + $loop->index }}</span></td>
          <td><span class="table-id">{{ $e->enquiry_number ?? 'N/A' }}</span></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(38,64,120,0.12);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#2B2B2B;">
                @php $parts = explode(' ', $e->complaint?->complainant_name ?? '?'); $init = strtoupper(($parts[0][0] ?? '?') . ($parts[1][0] ?? $parts[0][1] ?? '')); @endphp
                {{ $init }}
              </div>
              <span style="font-size:13px;font-weight:500;">{{ $e->complaint?->complainant_name ?? 'N/A' }}</span>
            </div>
          </td>
          <td><span style="font-size:12.5px;">{{ $e->officer?->name ?? 'Not Assigned' }}</span></td>
          <td><span class="badge {{ $statusColors[$e->status] ?? 'badge-active' }}">{{ ucfirst(str_replace('_', ' ', $e->status)) }}</span></td>
          <td><span style="font-size:12px;color:var(--text-muted);">{{ $e->created_at?->format('d M Y') ?? '—' }}</span></td>
          <td style="text-align:center;">
            <div style="display:flex;gap:6px;justify-content:center;">
              {{-- <a href="{{ route('enquiries.show', $e) }}" class="btn btn-outline btn-sm btn-icon" title="View">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8 11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </a> --}}
              <a href="{{ route('enquiries.edit', $e) }}" class="btn btn-outline btn-sm btn-icon" title="Edit">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              <form action="{{ route('enquiries.destroy', $e) }}" method="POST" class="delete-form" style="display:inline;">
                @csrf @method('DELETE')
                <button type="button" class="btn btn-sm delete-btn" style="background:rgba(229,62,62,0.15);color:#e53e3e;border:none;border-radius:8px;width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;" title="Delete">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" style="text-align:center;padding:48px 20px;color:var(--text-muted);font-size:14px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><br>
            No enquiries found. Click <strong>Create New</strong> to create one.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($enquiries->hasPages())
  <div class="pagination">
    <div class="pagination-info">
      Showing <strong>{{ $enquiries->firstItem() }}</strong>–<strong>{{ $enquiries->lastItem() }}</strong> of <strong>{{ $enquiries->total() }}</strong> enquiries
    </div>
    <div class="pagination-btns">
      @if($enquiries->onFirstPage())
        <button class="page-btn" disabled><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
      @else
        <a href="{{ $enquiries->previousPageUrl() }}" class="page-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></a>
      @endif
      @foreach($enquiries->getUrlRange(1, $enquiries->lastPage()) as $page => $url)
        <a href="{{ $url }}" class="page-btn{{ $page == $enquiries->currentPage() ? ' active' : '' }}">{{ $page }}</a>
      @endforeach
      @if($enquiries->hasMorePages())
        <a href="{{ $enquiries->nextPageUrl() }}" class="page-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></a>
      @else
        <button class="page-btn" disabled><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></button>
      @endif
    </div>
  </div>
  @endif
</div>

@endsection

@push('extra_js')
<style>
@keyframes fadeIn { from { opacity:0; transform:translate(-50%,-50%) scale(0.92); } to { opacity:1; transform:translate(-50%,-50%) scale(1); } }
</style>
<script>
(function() {
  const searchInput = document.getElementById('searchInput');
  const statusFilter = document.getElementById('statusFilter');
  const table = document.getElementById('enqTable');
  if (!table) return;

  function filterRows() {
    const q = (searchInput?.value || '').toLowerCase();
    const status = statusFilter?.value || '';
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
      const caseId = row.cells[1]?.textContent?.toLowerCase() || '';
      const name = row.cells[2]?.textContent?.toLowerCase() || '';
      const rowStatus = row.cells[4]?.textContent?.trim().toLowerCase().replace(/\s+/g, '_') || '';
      const matchSearch = !q || caseId.includes(q) || name.includes(q);
      const matchStatus = !status || rowStatus === status;
      row.style.display = matchSearch && matchStatus ? '' : 'none';
    });
  }

  searchInput?.addEventListener('input', filterRows);
  statusFilter?.addEventListener('change', filterRows);

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

  function showConfirm(msg, onConfirm) {
    const el = document.createElement('div');
    el.style.cssText = `
      position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;
      padding:24px 28px;border-radius:14px;font-size:14px;font-weight:500;
      box-shadow:0 16px 48px rgba(0,0,0,0.35);
      display:flex;flex-direction:column;gap:16px;
      animation:fadeIn 0.25s ease both;
      background:#2B2B2B;color:#fff;line-height:1.4;
      min-width:320px;border-left:4px solid #e53e3e;
    `;
    el.innerHTML = `
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:16px;flex-shrink:0;">⚠</span>
        <span>${msg}</span>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button class="cnf-cancel" style="padding:7px 16px;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:transparent;color:#fff;font-size:12px;cursor:pointer;">Cancel</button>
        <button class="cnf-ok" style="padding:7px 16px;border-radius:8px;border:none;background:#e53e3e;color:#fff;font-size:12px;font-weight:600;cursor:pointer;">Delete</button>
      </div>
    `;
    document.body.appendChild(el);
    const close = () => { el.style.opacity = '0'; el.style.transition = 'opacity 0.2s'; setTimeout(() => el.remove(), 200); };
    el.querySelector('.cnf-cancel').addEventListener('click', close);
    el.querySelector('.cnf-ok').addEventListener('click', () => { close(); onConfirm(); });
  }

  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const form = this.closest('.delete-form');
      showConfirm('Delete this enquiry record?', () => form.submit());
    });
  });

  @if(session('success'))
  setTimeout(() => { showToast('{{ str_replace(["'", "\n"], ["\'", " "], session("success")) }}', 'success'); }, 300);
  @endif

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
