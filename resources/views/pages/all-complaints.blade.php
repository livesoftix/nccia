@extends('layouts.app')

@section('title', 'All Complaints')

@section('page_content')

<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Complaints</div>
    <h1 class="page-title">All Complaints</h1>
    <p class="page-subtitle">Complete list of registered complaints &nbsp;·&nbsp; CCRC-LHR Circle</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <a href="{{ route('newcomplaint') }}" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
      New Complaint
    </a>
  </div>
</div>

<div class="card animate-fade-up animate-delay-2">
  <div class="card-header">
    <div class="card-title">
      <div class="card-icon">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      Complaints
    </div>
    <div class="section-actions">
      <span style="font-size:11.5px;color:var(--text-muted);">Total: <strong style="color:var(--text-body);">{{ $complaints->total() }}</strong></span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="data-table" style="display:table;width:100%;" aria-label="All Complaints">
      <thead>
        <tr>
          <th>Case ID</th>
          <th>Complainant</th>
          <th>Diary No.</th>
          <th>Report Date</th>
          <th>Priority</th>
          <th>Offence Type</th>
          <th>Status</th>
          <th>Created</th>
          <th style="text-align:center;">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($complaints as $c)
        @php
          $names = explode(' ', $c->complainant_name);
          $initials = strtoupper(($names[0][0] ?? '') . ($names[1][0] ?? $names[0][1] ?? ''));
          $dotCount = ['high' => 3, 'medium' => 2, 'low' => 1][$c->priority_type] ?? 1;
          $priorityClass = in_array($c->priority_type, ['high','medium','low']) ? $c->priority_type : 'low';
        @endphp
        <tr>
          <td><a href="{{ route('complaints.edit', $c) }}" class="table-id" style="text-decoration:none;">#{{ $c->tracking_no }}</a></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(38,64,120,0.12);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#2B2B2B;">{{ $initials }}</div>
              <span style="font-size:13px;font-weight:500;">{{ $c->complainant_name }}</span>
            </div>
          </td>
          <td><span style="font-size:12.5px;">{{ $c->diary_no }}</span></td>
          <td><span style="font-size:12.5px;">{{ $c->report_date?->format('d M Y') }}</span></td>
          <td>
            <div class="priority-dots">
              @for($i = 0; $i < 3; $i++)
                <div class="priority-dot{{ $i < $dotCount ? ' filled ' . $priorityClass : '' }}"></div>
              @endfor
            </div>
          </td>
          <td><span style="font-size:12.5px;">{{ $c->offence_type }}</span></td>
          <td><span class="badge badge-pending">Pending</span></td>
          <td><span style="font-size:12px;color:var(--text-muted);">{{ $c->created_at->format('d M Y') }}</span></td>
          <td style="text-align:center;">
            <div style="display:flex;gap:6px;justify-content:center;">
              <a href="{{ route('complaints.edit', $c) }}" class="btn btn-outline btn-sm btn-icon" title="Edit">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              <form action="{{ route('complaints.destroy', $c) }}" method="POST" class="delete-form" style="display:inline;">
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
          <td colspan="9" style="text-align:center;padding:24px;color:var(--text-muted);">No complaints registered yet</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($complaints->hasPages())
  <div class="pagination">
    <div class="pagination-info">
      Showing <strong>{{ $complaints->firstItem() }}</strong>–<strong>{{ $complaints->lastItem() }}</strong> of <strong>{{ $complaints->total() }}</strong> complaints
    </div>
    <div class="pagination-btns">
      @if($complaints->onFirstPage())
        <button class="page-btn" disabled>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
      @else
        <a href="{{ $complaints->previousPageUrl() }}" class="page-btn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
      @endif

      @foreach($complaints->getUrlRange(1, $complaints->lastPage()) as $page => $url)
        <a href="{{ $url }}" class="page-btn{{ $page == $complaints->currentPage() ? ' active' : '' }}">{{ $page }}</a>
      @endforeach

      @if($complaints->hasMorePages())
        <a href="{{ $complaints->nextPageUrl() }}" class="page-btn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
      @else
        <button class="page-btn" disabled>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
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
      showConfirm('Delete this complaint?', () => form.submit());
    });
  });

  @if(session('success'))
  setTimeout(() => { showToast('{{ str_replace(["'", "\n"], ["\'", " "], session("success")) }}', 'success'); }, 300);
  @endif
})();
</script>
@endpush
