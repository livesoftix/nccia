@extends('layouts.app')

@section('title', 'Verification Reports')

@section('page_content')

@if(session('success'))
<div class="cf-banner cf-banner-success animate-fade-up">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
  <span>{{ session('success') }}</span>
</div>
@endif

<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Verifications</div>
    <h1 class="page-title">Verification Reports</h1>
    <p class="page-subtitle">Saved victim verification report records</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <a href="{{ route('verifications.reports') }}" class="btn btn-primary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
      New Report
    </a>
  </div>
</div>

<div class="tab-nav animate-fade-up animate-delay-1">
  <a href="{{ route('verifications.index') }}" class="tab-nav-item">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    Verifications
  </a>
  <a href="{{ route('verifications.reports-list') }}" class="tab-nav-item active">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    Reports
  </a>
</div>

<div class="card animate-fade-up animate-delay-2">
  <div class="card-header">
    <div class="card-title">
      <div class="card-icon">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </div>
      Saved Reports
    </div>
    <div class="section-actions">
      <span style="font-size:11.5px;color:var(--text-muted);">Total: <strong style="color:var(--text-body);">{{ $reports->total() }}</strong></span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="data-table" style="display:table;width:100%;">
      <thead>
        <tr>
          <th>#</th>
          <th>Tracking No.</th>
          <th>Victim Name</th>
          <th>CNIC</th>
          <th>Crime Category</th>
          <th>City</th>
          <th>Created By</th>
          <th>Signature</th>
          <th>Date</th>
          <th style="text-align:center;">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($reports as $r)
        <tr>
          <td><span style="font-size:12px;color:var(--text-muted);">{{ $reports->firstItem() + $loop->index }}</span></td>
          <td><span class="table-id">{{ $r->tracking_no }}</span></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(38,64,120,0.12);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#2B2B2B;">
                @php $parts = explode(' ', $r->victim_name ?? '?'); $init = strtoupper(($parts[0][0] ?? '?') . ($parts[1][0] ?? $parts[0][1] ?? '')); @endphp
                {{ $init }}
              </div>
              <span style="font-size:13px;font-weight:500;">{{ $r->victim_name }}</span>
            </div>
          </td>
          <td><span class="font-mono" style="font-size:12px;color:var(--text-muted);">{{ $r->victim_cnic }}</span></td>
          <td><span class="badge badge-active">{{ $r->crime_category }}</span></td>
          <td><span style="font-size:12.5px;">{{ $r->city }}</span></td>
          <td><span style="font-size:12.5px;">{{ $r->creator?->name ?? 'N/A' }}</span></td>
          <td>
            @if($r->signature)
              <img src="{{ Storage::url($r->signature) }}" alt="Signature" style="height:30px;border:1px solid var(--border-light);border-radius:4px;background:#fff;cursor:pointer;" onclick="sigPreview(this)">
            @else
              <span style="font-size:11px;color:var(--text-muted);">—</span>
            @endif
          </td>
          <td><span style="font-size:12px;color:var(--text-muted);">{{ $r->created_at->format('d M Y') }}</span></td>
          <td style="text-align:center;">
            <a href="{{ route('verifications.reports.pdf', $r) }}" class="btn btn-outline btn-sm btn-icon" title="Download PDF" target="_blank">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="12 10 12 18 15 15"/></svg>
            </a>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="10" style="text-align:center;padding:48px 20px;color:var(--text-muted);font-size:14px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.4;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><br>
            No reports saved yet. Click <strong>New Report</strong> to create one.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($reports->hasPages())
  <div class="pagination">
    <div class="pagination-info">
      Showing <strong>{{ $reports->firstItem() }}</strong>–<strong>{{ $reports->lastItem() }}</strong> of <strong>{{ $reports->total() }}</strong> reports
    </div>
    <div class="pagination-btns">
      @if($reports->onFirstPage())
        <button class="page-btn" disabled><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
      @else
        <a href="{{ $reports->previousPageUrl() }}" class="page-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></a>
      @endif
      @foreach($reports->getUrlRange(1, $reports->lastPage()) as $page => $url)
        <a href="{{ $url }}" class="page-btn{{ $page == $reports->currentPage() ? ' active' : '' }}">{{ $page }}</a>
      @endforeach
      @if($reports->hasMorePages())
        <a href="{{ $reports->nextPageUrl() }}" class="page-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></a>
      @else
        <button class="page-btn" disabled><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></button>
      @endif
    </div>
  </div>
  @endif
</div>

@endsection

@push('extra_css')
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
.btn-icon { width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;padding:0; }
</style>
@endpush

@push('extra_js')
<script>
function sigPreview(img) {
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
  overlay.addEventListener('click', () => overlay.remove());
  const big = document.createElement('img');
  big.src = img.src;
  big.style.cssText = 'max-width:80%;max-height:80%;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.5);background:#fff;padding:20px;';
  overlay.appendChild(big);
  document.body.appendChild(overlay);
}
</script>
@endpush
