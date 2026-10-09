@extends('layouts.app')

@section('title', 'Investigation Officers')

@section('page_content')

@if(session('success'))
<div class="cf-banner cf-banner-success animate-fade-up">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
  <span>{{ session('success') }}</span>
</div>
@endif

<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Records</div>
    <h1 class="page-title">Investigation Officers</h1>
    <p class="page-subtitle">Manage IO records &amp; details</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <a href="{{ route('investigation-officers.create') }}" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
      Add IO
    </a>
  </div>
</div>

<div class="mini-stats-row animate-fade-up animate-delay-1">
  <div class="mini-stat">
    <div class="mini-stat-value">{{ $stats['total'] }}</div>
    <div class="mini-stat-label">Total Officers</div>
  </div>
  <div class="mini-stat">
    <div class="mini-stat-value">{{ $stats['active'] }}</div>
    <div class="mini-stat-label">Active</div>
  </div>
</div>

<div class="card animate-fade-up animate-delay-2">
  <div class="card-header">
    <div class="card-title">
      <div class="card-icon">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      Officer Records
    </div>
    <div class="section-actions">
      <span style="font-size:11.5px;color:var(--text-muted);">Total: <strong style="color:var(--text-body);">{{ $officers->total() }}</strong></span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="data-table" style="display:table;width:100%;">
      <thead>
        <tr>
          <th>#</th>
          <th>Badge No.</th>
          <th>Name</th>
          <th>Designation</th>
          <th>Circle</th>
          <th>Contact</th>
          <th>Status</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($officers as $o)
        <tr>
          <td><span style="font-size:12px;color:var(--text-muted);">{{ $officers->firstItem() + $loop->index }}</span></td>
          <td><span class="table-id">{{ $o->badge_no }}</span></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(38,64,120,0.12);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#2B2B2B;">
                @php $parts = explode(' ', $o->name); $init = strtoupper(($parts[0][0] ?? '?') . ($parts[1][0] ?? $parts[0][1] ?? '')); @endphp
                {{ $init }}
              </div>
              <span style="font-size:13px;font-weight:500;">{{ $o->name }}</span>
            </div>
          </td>
          <td><span style="font-size:12.5px;">{{ $o->designation ?? '—' }}</span></td>
          <td><span style="font-size:12.5px;">{{ $o->circle ?? '—' }}</span></td>
          <td><span style="font-size:12px;color:var(--text-muted);">{{ $o->contact_no ?? '—' }}</span></td>
          <td>
            <span class="badge {{ $o->status === 'active' ? 'badge-finalized' : 'badge-urgent' }}">
              {{ ucfirst($o->status) }}
            </span>
          </td>
          <td style="text-align:center;">
            <div style="display:flex;gap:6px;justify-content:center;">
              <a href="{{ route('investigation-officers.edit', $o) }}" class="btn btn-outline btn-sm btn-icon" title="Edit">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              <form action="{{ route('investigation-officers.destroy', $o) }}" method="POST" class="delete-form" style="display:inline;">
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
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><br>
            No officers added yet. Click <strong>Add IO</strong> to create one.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($officers->hasPages())
  <div class="pagination">
    <div class="pagination-info">
      Showing <strong>{{ $officers->firstItem() }}</strong>–<strong>{{ $officers->lastItem() }}</strong> of <strong>{{ $officers->total() }}</strong> officers
    </div>
    <div class="pagination-btns">
      @if($officers->onFirstPage())
        <button class="page-btn" disabled><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
      @else
        <a href="{{ $officers->previousPageUrl() }}" class="page-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></a>
      @endif
      @foreach($officers->getUrlRange(1, $officers->lastPage()) as $page => $url)
        <a href="{{ $url }}" class="page-btn{{ $page == $officers->currentPage() ? ' active' : '' }}">{{ $page }}</a>
      @endforeach
      @if($officers->hasMorePages())
        <a href="{{ $officers->nextPageUrl() }}" class="page-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></a>
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
.cf-banner {
  display:flex;align-items:center;gap:12px;
  padding:14px 18px;border-radius:var(--border-radius);
  font-size:13.5px;font-weight:600;margin-bottom:20px;
}
.cf-banner-success {
  background:linear-gradient(135deg, rgba(23,166,96,0.12), rgba(34,197,94,0.08));
  border:1.5px solid rgba(23,166,96,0.35);color:#15803d;
}
.btn-icon { width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;padding:0; }
</style>
<script>
(function() {
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
      showConfirm('Delete this officer record?', () => form.submit());
    });
  });

  @if(session('success'))
  setTimeout(() => { showToast('{{ str_replace(["'", "\n"], ["\'", " "], session("success")) }}', 'success'); }, 300);
  @endif
})();
</script>
@endpush
