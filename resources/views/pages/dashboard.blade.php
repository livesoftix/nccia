@extends('layouts.app')

@section('title', 'Dashboard')

@section('page_content')
<div class="dashboard_page">
@if(session('success'))
@endif
{{-- <div class="alert-banner info animate-fade-up" id="welcomeBanner" role="alert">
  <svg class="alert-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="8"/><line x1="12" y1="12" x2="12" y2="16"/>
  </svg>
  Welcome back, <strong>Umar Ilyas</strong>. You have <strong>1,337 pending verifications</strong> and <strong>5 new notifications</strong> today.
  <button class="alert-close" onclick="document.getElementById('welcomeBanner').remove()" aria-label="Dismiss">✕</button>
</div> --}}

<div class="page-header animate-fade-up">
  <div class="page-title-group">
    <div class="page-label">Overview</div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">{{ now()->format('l, d F Y') }} &nbsp;·&nbsp; CCRC-LHR Circle</p>
    <div class="title-underline"></div>
  </div>
  <div class="page-actions">
    <div class="filters-bar" style="margin:0;padding:10px 14px;">
      <span class="filter-label">Period</span>
      <div class="filter-date-range">
        <input type="date" id="dateFrom" value="{{ now()->startOfYear()->format('Y-m-d') }}" aria-label="From date">
        <span class="date-sep">to</span>
        <input type="date" id="dateTo" value="{{ now()->format('Y-m-d') }}" aria-label="To date">
      </div>
      <select class="filter-select" id="accountFilter" aria-label="Account Level">
        <option>User Level Account</option>
        <option>Circle Level Account</option>
        <option>Zone Level Account</option>
        <option>HQ Level Account</option>
      </select>
      <button class="btn btn-primary btn-sm" id="applyFilterBtn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg> Apply
      </button>
      <button class="btn btn-outline btn-sm" id="exportBtn" title="Export Report">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export
      </button>
    </div>
  </div>
</div>

<div class="stats-grid animate-fade-up animate-delay-2">
  <div class="stat-card green">
    <div class="stat-card-top">
      <div class="stat-icon green">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 11l3 3L22 4"/>
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
      </div>
      <div class="stat-trend up">↑ 12%</div>
    </div>
    <div class="stat-value">1,527</div>
    <div class="stat-label">Total Verifications</div>
    <div class="stat-footer"><span>Pending</span><span class="stat-footer-value" style="color:var(--status-pending);">1,337</span></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-card-top">
      <div class="stat-icon orange">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
      </div>
      <div class="stat-trend neutral">— 0%</div>
    </div>
    <div class="stat-value">284</div>
    <div class="stat-label">Pending Review</div>
    <div class="stat-footer"><span>Avg Wait</span><span class="stat-footer-value">4.2 days</span></div>
  </div>
  <div class="stat-card teal">
    <div class="stat-card-top">
      <div class="stat-icon teal">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <div class="stat-trend up">↑ 8%</div>
    </div>
    <div class="stat-value">392</div>
    <div class="stat-label">Finalized Cases</div>
    <div class="stat-footer"><span>This Month</span><span class="stat-footer-value">47</span></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-card-top">
      <div class="stat-icon blue">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
      </div>
      <div class="stat-trend up">↑ 5%</div>
    </div>
    <div class="stat-value">156</div>
    <div class="stat-label">Converted to Enquiry</div>
    <div class="stat-footer"><span>Active Enquiries</span><span class="stat-footer-value">48</span></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-card-top">
      <div class="stat-icon gold">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
      </div>
      <div class="stat-trend down">↓ 2%</div>
    </div>
    <div class="stat-value">73</div>
    <div class="stat-label">Recommended Closure</div>
    <div class="stat-footer"><span>Awaiting Approval</span><span class="stat-footer-value">29</span></div>
  </div>
  <div class="stat-card gray">
    <div class="stat-card-top">
      <div class="stat-icon gray">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="15" y1="9" x2="9" y2="15"/>
          <line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
      </div>
      <div class="stat-trend neutral">— 0%</div>
    </div>
    <div class="stat-value">118</div>
    <div class="stat-label">Closed (Non-Pursuance)</div>
    <div class="stat-footer"><span>Last 30 days</span><span class="stat-footer-value">14</span></div>
  </div>
  <div class="stat-card red">
    <div class="stat-card-top">
      <div class="stat-icon red">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <div class="stat-trend up">↑ 3%</div>
    </div>
    <div class="stat-value">89</div>
    <div class="stat-label">Closed (Non-Evidence)</div>
    <div class="stat-footer"><span>Under Review</span><span class="stat-footer-value">11</span></div>
  </div>
  <div class="stat-card purple">
    <div class="stat-card-top">
      <div class="stat-icon purple">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
      </div>
      <div class="stat-trend down">↓ 11%</div>
    </div>
    <div class="stat-value">6.8</div>
    <div class="stat-label">Avg Processing Days</div>
    <div class="stat-footer"><span>Target</span><span class="stat-footer-value" style="color:var(--status-success);">≤ 7 days ✓</span></div>
  </div>
</div>

<div class="mini-stats-row animate-fade-up animate-delay-3">
  <div class="mini-stat"><div class="mini-stat-value">34</div><div class="mini-stat-label">Transfer (Circles)</div></div>
  <div class="mini-stat"><div class="mini-stat-value">19</div><div class="mini-stat-label">Merge (Other Complaint)</div></div>
  <div class="mini-stat"><div class="mini-stat-value">62</div><div class="mini-stat-label">Jurisdiction Wanted</div></div>
  <div class="mini-stat"><div class="mini-stat-value">91%</div><div class="mini-stat-label">Overall Performance</div></div>
</div>

<div class="dashboard-grid animate-fade-up animate-delay-4">
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <div class="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
        Monthly Complaint Trends
      </div>
      <div class="section-actions">
        <select class="filter-select" style="height:30px;font-size:12px;" aria-label="Year"><option>2026</option><option>2025</option><option>2024</option></select>
        <button class="btn btn-outline btn-sm btn-icon" title="Expand chart"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg></button>
      </div>
    </div>
    <div class="card-body">
      <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted);"><span style="width:12px;height:12px;border-radius:2px;background:var(--primary);display:inline-block;"></span> Complaints Received</div>
        <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted);"><span style="width:12px;height:12px;border-radius:2px;background:var(--accent-gold);display:inline-block;"></span> Resolved</div>
        <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted);"><span style="width:12px;height:12px;border-radius:2px;background:#264078;display:inline-block;"></span> Converted to Enquiry</div>
      </div>
      <div class="chart-container">
        <div class="chart-bar-group">
          <div class="chart-bar-item"><div class="chart-bar primary" style="height:65%;" title="Received: 98"></div></div>
          <div class="chart-bar-item"><div class="chart-bar gold" style="height:42%;" title="Resolved: 63"></div></div>
          <div class="chart-bar-item"><div class="chart-bar blue" style="height:20%;" title="Enquiry: 30"></div></div>
          <div class="chart-bar-item" style="margin-left:8px;"><div class="chart-bar primary" style="height:78%;" title="Received: 117"></div></div>
          <div class="chart-bar-item"><div class="chart-bar gold" style="height:55%;" title="Resolved: 82"></div></div>
          <div class="chart-bar-item"><div class="chart-bar blue" style="height:25%;" title="Enquiry: 37"></div></div>
          <div class="chart-bar-item" style="margin-left:8px;"><div class="chart-bar primary" style="height:55%;" title="Received: 82"></div></div>
          <div class="chart-bar-item"><div class="chart-bar gold" style="height:48%;" title="Resolved: 72"></div></div>
          <div class="chart-bar-item"><div class="chart-bar blue" style="height:18%;" title="Enquiry: 27"></div></div>
          <div class="chart-bar-item" style="margin-left:8px;"><div class="chart-bar primary" style="height:90%;" title="Received: 135"></div></div>
          <div class="chart-bar-item"><div class="chart-bar gold" style="height:62%;" title="Resolved: 93"></div></div>
          <div class="chart-bar-item"><div class="chart-bar blue" style="height:30%;" title="Enquiry: 45"></div></div>
          <div class="chart-bar-item" style="margin-left:8px;"><div class="chart-bar primary" style="height:70%;" title="Received: 105"></div></div>
          <div class="chart-bar-item"><div class="chart-bar gold" style="height:58%;" title="Resolved: 87"></div></div>
          <div class="chart-bar-item"><div class="chart-bar blue" style="height:22%;" title="Enquiry: 33"></div></div>
          <div class="chart-bar-item" style="margin-left:8px;"><div class="chart-bar primary" style="height:100%;" title="Received: 150"></div></div>
          <div class="chart-bar-item"><div class="chart-bar gold" style="height:72%;" title="Resolved: 108"></div></div>
          <div class="chart-bar-item"><div class="chart-bar blue" style="height:38%;" title="Enquiry: 57"></div></div>
        </div>
        <div class="chart-x-axis">
          <span class="chart-x-label" style="flex:3;">Jan</span><span class="chart-x-label" style="flex:3;">Feb</span><span class="chart-x-label" style="flex:3;">Mar</span><span class="chart-x-label" style="flex:3;">Apr</span><span class="chart-x-label" style="flex:3;">May</span><span class="chart-x-label" style="flex:3;">Jun</span>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <div class="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg></div>
        Complaint Categories
      </div>
    </div>
    <div class="card-body">
      <div class="donut-wrap" style="flex-direction:column;gap:16px;">
        <svg class="donut-svg" viewBox="0 0 120 120" width="120" height="120" style="margin:0 auto;display:block;">
          <circle cx="60" cy="60" r="50" fill="none" stroke="var(--bg-body)" stroke-width="20"/>
          <circle cx="60" cy="60" r="50" fill="none" stroke="#FDDF00" stroke-width="20" stroke-dasharray="109.96 204.2" stroke-dashoffset="0" transform="rotate(-90 60 60)"/>
          <circle cx="60" cy="60" r="50" fill="none" stroke="#264078" stroke-width="20" stroke-dasharray="78.54 235.62" stroke-dashoffset="-109.96" transform="rotate(-90 60 60)"/>
          <circle cx="60" cy="60" r="50" fill="none" stroke="#267859" stroke-width="20" stroke-dasharray="62.83 251.33" stroke-dashoffset="-188.5" transform="rotate(-90 60 60)"/>
          <circle cx="60" cy="60" r="50" fill="none" stroke="#9b3232" stroke-width="20" stroke-dasharray="37.7 276.46" stroke-dashoffset="-251.33" transform="rotate(-90 60 60)"/>
          <circle cx="60" cy="60" r="50" fill="none" stroke="#1950c7" stroke-width="20" stroke-dasharray="25.13 289.03" stroke-dashoffset="-289.03" transform="rotate(-90 60 60)"/>
          <text x="60" y="55" text-anchor="middle" font-size="13" font-weight="700" fill="#000" font-family="Cinzel">1,527</text>
          <text x="60" y="68" text-anchor="middle" font-size="8" fill="var(--text-muted)">Total Cases</text>
        </svg>
        <div class="donut-legend">
          <div class="donut-legend-item"><div class="donut-dot" style="background:#FDDF00;"></div><span class="donut-label">Financial Fraud</span><span class="donut-val">535 <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(35%)</span></span></div>
          <div class="donut-legend-item"><div class="donut-dot" style="background:#264078;"></div><span class="donut-label">Cyberstalking</span><span class="donut-val">382 <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(25%)</span></span></div>
          <div class="donut-legend-item"><div class="donut-dot" style="background:#267859;"></div><span class="donut-label">Hacking / Intrusion</span><span class="donut-val">305 <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(20%)</span></span></div>
          <div class="donut-legend-item"><div class="donut-dot" style="background:#9b3232;"></div><span class="donut-label">Impersonation</span><span class="donut-val">183 <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(12%)</span></span></div>
          <div class="donut-legend-item"><div class="donut-dot" style="background:#1950c7;"></div><span class="donut-label">Other Offences</span><span class="donut-val">122 <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(8%)</span></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="dashboard-grid animate-fade-up animate-delay-5">
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <div class="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
        Recent Complaints
      </div>
      <div class="section-actions">
        <a href="{{ route('all.complaints') }}" class="btn btn-outline btn-sm">View All</a>
        <a href="{{ route('newcomplaint') }}" class="btn btn-primary btn-sm"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> New</a>
      </div>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-responsive">
        <table class="data-table" style="display: table; width: 100%;" aria-label="Recent Complaints">
          <thead>
            <tr>
            <th>Case ID</th>
            <th>Complainant</th>
            <th>Category</th>
            <th>Priority</th>
            <th>Officer</th>
            <th>Status</th>
            <th>Date</th>
            <th style="text-align:center;">Action</th>
          </tr>
          </thead>
          <tbody>
            @forelse($recentComplaints as $c)
            @php
              $names = explode(' ', $c->complainant_name);
              $initials = strtoupper(($names[0][0] ?? '') . ($names[1][0] ?? $names[0][1] ?? ''));
              $dotCount = ['high' => 3, 'medium' => 2, 'low' => 1][$c->priority_type] ?? 1;
              $priorityClass = in_array($c->priority_type, ['high','medium','low']) ? $c->priority_type : 'low';
            @endphp
            <tr>
              <td><span class="table-id">#{{ $c->tracking_no }}</span></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="width:28px;height:28px;border-radius:50%;background:rgba(38,64,120,0.12);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#2B2B2B;">{{ $initials }}</div>
                  <span style="font-size:13px;font-weight:500;">{{ $c->complainant_name }}</span>
                </div>
              </td>
              <td><span style="font-size:12.5px;">{{ $c->offence_type }}</span></td>
              <td>
                <div class="priority-dots">
                  @for($i = 0; $i < 3; $i++)
                    <div class="priority-dot{{ $i < $dotCount ? ' filled ' . $priorityClass : '' }}"></div>
                  @endfor
                </div>
              </td>
              <td><span style="font-size:12.5px;color:var(--text-muted);">{{ $c->operator_name }}</span></td>
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
              <td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted);">No complaints found</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px;">
    <div class="card">
      <div class="card-header">
        <div class="card-title"><div class="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>Recent Activity</div>
        <a href="#all-activity" style="font-size:12px;color:var(--primary);font-weight:600;">See all →</a>
      </div>
      <div class="card-body">
        <div class="activity-feed">
          <div class="activity-item">
            <div class="activity-dot-wrap"><div class="activity-dot green">✓</div><div class="activity-line"></div></div>
            <div class="activity-content"><div class="activity-title">Case #1891 escalated to urgent</div><div class="activity-desc">Action taken by ASI Umar Ilyas</div></div>
            <div class="activity-time">2m ago</div>
          </div>
          <div class="activity-item">
            <div class="activity-dot-wrap"><div class="activity-dot blue">📄</div><div class="activity-line"></div></div>
            <div class="activity-content"><div class="activity-title">New complaint registered</div><div class="activity-desc">#CMS-2024-1892 — Financial Fraud</div></div>
            <div class="activity-time">15m ago</div>
          </div>
          <div class="activity-item">
            <div class="activity-dot-wrap"><div class="activity-dot gold">★</div><div class="activity-line"></div></div>
            <div class="activity-content"><div class="activity-title">Enquiry #1780 finalized</div><div class="activity-desc">Closure approved by Circle Head</div></div>
            <div class="activity-time">1h ago</div>
          </div>
          <div class="activity-item">
            <div class="activity-dot-wrap"><div class="activity-dot red">!</div></div>
            <div class="activity-content"><div class="activity-title">Verification deadline approaching</div><div class="activity-desc">32 verifications due tomorrow</div></div>
            <div class="activity-time">3h ago</div>
          </div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-header">
        <div class="card-title"><div class="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>Pendency by Category</div>
      </div>
      <div class="card-body">
        <div class="progress-list">
          <div class="progress-item"><div class="progress-header"><span class="progress-name">Financial Fraud</span><span class="progress-count">535</span></div><div class="progress-bar-bg"><div class="progress-bar-fill" style="width:35%;background:linear-gradient(90deg,var(--primary),var(--primary-light));"></div></div></div>
          <div class="progress-item"><div class="progress-header"><span class="progress-name">Cyberstalking</span><span class="progress-count">382</span></div><div class="progress-bar-bg"><div class="progress-bar-fill" style="width:25%;background:#2B2B2B;"></div></div></div>
          <div class="progress-item"><div class="progress-header"><span class="progress-name">Hacking / Intrusion</span><span class="progress-count">305</span></div><div class="progress-bar-bg"><div class="progress-bar-fill" style="width:20%;background:#264078;"></div></div></div>
          <div class="progress-item"><div class="progress-header"><span class="progress-name">Impersonation</span><span class="progress-count">183</span></div><div class="progress-bar-bg"><div class="progress-bar-fill" style="width:12%;background:#2B2B2B;"></div></div></div>
          <div class="progress-item"><div class="progress-header"><span class="progress-name">Other Offences</span><span class="progress-count">122</span></div><div class="progress-bar-bg"><div class="progress-bar-fill" style="width:8%;background:#264078;"></div></div></div>
        </div>
      </div>
    </div>
  </div>
</div>
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

  setTimeout(() => {
    const banner = document.getElementById('welcomeBanner');
    if (banner) { banner.style.transition = 'opacity 0.5s'; banner.style.opacity = '0'; setTimeout(() => banner.remove(), 500); }
  }, 10000);
})();
</script>
@endpush
