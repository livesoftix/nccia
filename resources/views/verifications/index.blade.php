@extends('layouts.app')

@section('title', 'Verifications')

@section('page_content')

@if(session('success'))
@endif

<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Complaints</div>
    <h1 class="page-title">Verifications</h1>
    <p class="page-subtitle">Manage complaint verifications &nbsp;·&nbsp; CCRC-LHR</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <a href="{{ route('verifications.create') }}" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
      Assign New
    </a>
  </div>
</div>

<div class="tab-nav animate-fade-up animate-delay-1">
  <a href="{{ route('verifications.index') }}" class="tab-nav-item active">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    Verifications
  </a>
  <a href="{{ route('verifications.reports-list') }}" class="tab-nav-item">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    Reports
  </a>
</div>

<div class="mini-stats-row animate-fade-up animate-delay-1">
  <div class="mini-stat">
    <div class="mini-stat-value">{{ $verifications->total() }}</div>
    <div class="mini-stat-label">Total Verifications</div>
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
  <input type="text" id="searchInput" class="filter-select" style="height:34px;padding:0 12px;width:260px;" placeholder="Search by Case ID or complainant name...">
  <select class="filter-select" id="statusFilter">
    <option value="">All Statuses</option>
    <option value="pending_assignment">Pending Assignment</option>
    <option value="assigned">Assigned</option>
    <option value="in_progress">In Progress</option>
    <option value="submitted">Submitted</option>
    <option value="approved">Approved</option>
    <option value="sent_back">Sent Back</option>
  </select>
  <div class="filter-spacer"></div>
</div>

<div class="card animate-fade-up animate-delay-3">
  <div class="card-header">
    <div class="card-title">
      <div class="card-icon">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      Verification Records
    </div>
    <div class="section-actions">
      <span style="font-size:11.5px;color:var(--text-muted);">Total: <strong style="color:var(--text-body);">{{ $verifications->total() }}</strong></span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="verifTable" style="display:table;width:100%;">
      <thead>
        <tr>
          <th>#</th>
          <th>Case ID</th>
          <th>Complainant</th>
          <th>Assigned Officer</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Assigned Date</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($verifications as $v)
        @php
          $priorityColors = ['regular'=>'badge-finalized', 'court_directions'=>'badge-urgent', 'anti_state'=>'badge-urgent', 'higher_authority'=>'badge-pending'];
          $statusColors = ['pending_assignment'=>'badge-pending', 'assigned'=>'badge-active', 'in_progress'=>'badge-info', 'submitted'=>'badge-pending', 'approved'=>'badge-finalized', 'sent_back'=>'badge-urgent'];
        @endphp
        <tr>
          <td><span style="font-size:12px;color:var(--text-muted);">{{ $verifications->firstItem() + $loop->index }}</span></td>
          <td><span class="table-id">{{ $v->complaint?->tracking_no ?? 'N/A' }}</span></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(38,64,120,0.12);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#2B2B2B;">
                @php $parts = explode(' ', $v->complaint?->complainant_name ?? '?'); $init = strtoupper(($parts[0][0] ?? '?') . ($parts[1][0] ?? $parts[0][1] ?? '')); @endphp
                {{ $init }}
              </div>
              <span style="font-size:13px;font-weight:500;">{{ $v->complaint?->complainant_name ?? 'N/A' }}</span>
            </div>
          </td>
          <td><span style="font-size:12.5px;">{{ $v->officer?->name ?? 'Not Assigned' }}</span></td>
          <td><span class="badge {{ $priorityColors[$v->priority_type] ?? 'badge-active' }}">{{ ucfirst(str_replace('_', ' ', $v->priority_type ?? 'regular')) }}</span></td>
          <td><span class="badge {{ $statusColors[$v->status] ?? 'badge-active' }}">{{ ucfirst(str_replace('_', ' ', $v->status)) }}</span></td>
          <td><span style="font-size:12px;color:var(--text-muted);">{{ $v->assigned_at?->format('d M Y') ?? '—' }}</span></td>
          <td style="text-align:center;">
            <div style="display:flex;gap:6px;justify-content:center;">
              <a href="{{ route('verifications.edit', $v) }}" class="btn btn-outline btn-sm btn-icon" title="Edit">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              <form action="{{ route('verifications.destroy', $v) }}" method="POST" class="delete-form" style="display:inline;">
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
          <td colspan="8" style="text-align:center;padding:48px 20px;color:var(--text-muted);font-size:14px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><br>
            No verifications found. Click <strong>Assign New</strong> to create one.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($verifications->hasPages())
  <div class="pagination">
    <div class="pagination-info">
      Showing <strong>{{ $verifications->firstItem() }}</strong>–<strong>{{ $verifications->lastItem() }}</strong> of <strong>{{ $verifications->total() }}</strong> verifications
    </div>
    <div class="pagination-btns">
      @if($verifications->onFirstPage())
        <button class="page-btn" disabled><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
      @else
        <a href="{{ $verifications->previousPageUrl() }}" class="page-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></a>
      @endif
      @foreach($verifications->getUrlRange(1, $verifications->lastPage()) as $page => $url)
        <a href="{{ $url }}" class="page-btn{{ $page == $verifications->currentPage() ? ' active' : '' }}">{{ $page }}</a>
      @endforeach
      @if($verifications->hasMorePages())
        <a href="{{ $verifications->nextPageUrl() }}" class="page-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></a>
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

.tab-nav {
  display:flex;
  gap:4px;
  margin-bottom:20px;
  background:var(--bg-card);
  padding:4px;
  border-radius:var(--border-radius);
  border:1.5px solid var(--border-light);
  width:fit-content;
}
.tab-nav-item {
  display:flex;
  align-items:center;
  gap:8px;
  padding:10px 18px;
  border-radius:var(--border-radius-sm);
  font-size:13px;
  font-weight:500;
  color:var(--text-muted);
  text-decoration:none;
  transition:all 0.2s;
}
.tab-nav-item:hover {
  color:var(--text-body);
  background:rgba(2,132,199,0.06);
}
.tab-nav-item.active {
  color:var(--text-heading);
  background:rgba(2,132,199,0.1);
  font-weight:600;
}
.tab-nav-item svg { flex-shrink:0; }
</style>
<script>
(function() {
  const searchInput = document.getElementById('searchInput');
  const statusFilter = document.getElementById('statusFilter');
  const table = document.getElementById('verifTable');
  if (!table) return;

  function filterRows() {
    const q = (searchInput?.value || '').toLowerCase();
    const status = statusFilter?.value || '';
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
      const caseId = row.cells[1]?.textContent?.toLowerCase() || '';
      const name = row.cells[2]?.textContent?.toLowerCase() || '';
      const rowStatus = row.cells[5]?.textContent?.trim().toLowerCase().replace(/\s+/g, '_') || '';
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
      showConfirm('Delete this verification record?', () => form.submit());
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
