import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import ProgressBar from '../components/ProgressBar';
import { getRoleDuties, canView } from '../utils/permissions';

const ROLE_PORTALS = {
  admin:               { icon: '🛠️', title: 'Admin Command Portal', desc: 'Full system administration — manage users, circles, offence types & all modules.' },
  circle_incharge:     { icon: '✅', title: 'Circle Incharge Portal', desc: 'Assign officers, approve verification/enquiry/case reports per flowchart.' },
  verification_officer:{ icon: '📋', title: 'Verification Officer Portal', desc: 'Verification stage only — notify complainant, verify, VO comments, submit recommendations.' },
  enquiry_officer:     { icon: '🔍', title: 'Enquiry Officer Portal', desc: 'Enquiry stage — activities, evidence, CFR.' },
  investigation_officer:{ icon: '🕵️', title: 'Investigation Officer Portal', desc: 'Case/Court stage — investigation, challan, court reports.' },
  moharrar:            { icon: '📝', title: 'Moharrar Portal', desc: 'FIR number & case registry (Case stage).' },
  reader_branch:       { icon: '📂', title: 'Reader Branch Portal', desc: 'Enquiry number registration (Enquiry stage).' },
  operator:            { icon: '💻', title: 'Front Desk Officer / CMU Portal', desc: 'Complete Registration, apni complaints aur unki progress track karein.' },
  ad_legal:            { icon: '⚖️', title: 'AD Legal Portal', desc: 'Legal Directorate — opinions on enquiry, case & court reports.' },
  dd_legal:            { icon: '⚖️', title: 'DD Legal Portal', desc: 'Legal Directorate — opinions on enquiry, case & court reports.' },
  additional_director: { icon: '🏛️', title: 'Additional Director Portal', desc: 'Executive Oversight & approvals on legal / operational reports.' },
  director_general:    { icon: '🎯', title: 'Director General Command Portal', desc: 'Executive command, oversight & analytics.' },
};

export default function Dashboard() {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const role = user?.roles?.[0]?.name || user?.role || 'operator';

  const circleName = user?.circle?.name || user?.circle_name || '';
  const circleCode = user?.circle?.code || user?.circle_code || '';
  const isHq = !user?.circle_id || circleCode === 'ISB' || circleCode === 'HQ' || circleName.toLowerCase().includes('islamabad');
  const stationLabel = isHq ? 'Islamabad HQ' : (circleName || 'Regional Circle');

  const basePortal = ROLE_PORTALS[role] || ROLE_PORTALS.operator;
  const portal = {
    icon: basePortal.icon,
    name: `${stationLabel} — ${basePortal.title}`,
    desc: isHq
      ? `NCCIA Headquarters Islamabad — Nationwide Command & Oversight · ${basePortal.desc}`
      : `${stationLabel} Directorate / Field Station — Regional Operational Jurisdiction · ${basePortal.desc}`
  };

  const duties = getRoleDuties(user);

  const [error, setError] = useState(null);
  const [chartExpanded, setChartExpanded] = useState(false);
  const [filters, setFilters] = useState({ dateFrom: '', dateTo: '', account: '' });
  const filtersRef = useRef(filters);
  useEffect(() => { filtersRef.current = filters; }, [filters]);

  const fetchDashboard = (filterParams = {}, silent = false) => {
    if (!silent) {
      setLoading(true);
      setError(null);
    }
    const params = new URLSearchParams();
    if (filterParams.dateFrom) params.set('date_from', filterParams.dateFrom);
    if (filterParams.dateTo) params.set('date_to', filterParams.dateTo);
    if (filterParams.account && filterParams.account !== 'User Level Account') params.set('account', filterParams.account);
    api.get(`/dashboard?${params.toString()}`).then(r => setData(r.data)).catch(e => {
      if (!silent) setError(e.response?.data?.message || e.message || 'Failed to load dashboard');
    }).finally(() => { if (!silent) setLoading(false); });
  };

  useEffect(() => { fetchDashboard(); }, []);

  useEffect(() => {
    // Soft refresh only — heavy dashboard is cached server-side
    const timer = setInterval(() => fetchDashboard(filtersRef.current, true), 120000);
    return () => clearInterval(timer);
  }, []);

  const handleApplyFilter = () => { fetchDashboard(filters); };
  const handleExport = () => {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = `dashboard-report-${new Date().toISOString().slice(0,10)}.json`;
    a.click(); URL.revokeObjectURL(url);
  };

  const isOperatorOnly = role === 'operator';

  if (loading) return <div className="dashboard_page"><LoadingSkeleton type="stats" rows={8} /><LoadingSkeleton type="table" rows={5} /></div>;
  if (error) return <div style={{ padding: 40, textAlign: 'center' }}><div style={{ color: '#e53e3e', fontSize: 14, marginBottom: 8 }}>⚠️ Dashboard Error</div><div style={{ color: '#888', fontSize: 13 }}>{error}</div></div>;
  if (!data) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>No data available</div>;

  const { stats, recentComplaints, monthlyTrends, categoryBreakdown, recentActivity } = data;
  const recent = recentComplaints?.length ? recentComplaints : [];
  const activities = Array.isArray(recentActivity) ? recentActivity : [];
  const ws = stats?.workflow_stages || {};

  if (isOperatorOnly) {
    const stageRows = [
      ['Incomplete / Registered', ws.registered || 0, '#e53e3e'],
      ['Scrutiny Complete', ws.scrutiny_complete || 0, '#e5a100'],
      ['Under Verification', ws.verification || 0, '#d69e2e'],
      ['Verification Submitted', ws.verification_submitted || 0, '#267859'],
      ['Enquiry Stage', ws.enquiry || 0, '#264078'],
      ['Case Filed', ws.case_filed || 0, '#1950c7'],
      ['Resolved', ws.resolved || 0, '#38a169'],
    ];
    const totalCmp = stats.total_complaints || 0;

    return (
      <div className="dashboard_page">
        <div className="page-header">
          <div className="page-title-group">
            <div className="page-label">Front Desk Officer / CMU</div>
            <h1 className="page-title">Dashboard</h1>
            <p className="page-subtitle">{new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })} &nbsp;·&nbsp; Aap ki complaints & progress</p>
            <div className="title-underline"></div>
          </div>
          <div className="page-actions">
            <Link to="/complaints/create" className="btn btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
              Complete Registration
            </Link>
          </div>
        </div>

        <div className="role-portal-banner" style={{background:'linear-gradient(135deg,#015C94,#0b3d5c)',color:'#fff',borderRadius:12,padding:'20px 24px',marginBottom:20,display:'flex',alignItems:'center',justifyContent:'space-between',gap:16,flexWrap:'wrap'}}>
          <div style={{display:'flex',alignItems:'center',gap:16}}>
            <div style={{fontSize:36}}>{portal.icon}</div>
            <div>
              <div style={{fontSize:13,opacity:0.85}}>Welcome, <strong>{user?.name?.split(' ')[0] || 'Front Desk Officer'}</strong></div>
              <div style={{fontSize:20,fontWeight:700}}>{portal.name}</div>
              <div style={{fontSize:13,opacity:0.9,marginTop:4}}>{portal.desc}</div>
            </div>
          </div>
          <Link to="/complaints/create" className="btn" style={{background:'#fff',color:'#015C94',fontWeight:700}}>
            + New Registration
          </Link>
        </div>

        <div className="stats-grid" style={{gridTemplateColumns:'repeat(auto-fit,minmax(180px,1fr))'}}>
          <div className="stat-card blue">
            <div className="stat-value">{stats.total_complaints || 0}</div>
            <div className="stat-label">Total Complaints</div>
            <div className="stat-footer"><span>Aap ki entries</span></div>
          </div>
          <div className="stat-card green">
            <div className="stat-value">{stats.complete_registrations || 0}</div>
            <div className="stat-label">Complete (Tracking No)</div>
            <div className="stat-footer"><span>Registered</span></div>
          </div>
          <div className="stat-card orange">
            <div className="stat-value">{stats.incomplete_registrations || 0}</div>
            <div className="stat-label">Incomplete / Draft</div>
            <div className="stat-footer"><span>Scrutiny baqi</span></div>
          </div>
        </div>

        <div className="dashboard-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', marginTop: 8 }}>

            <div className="card" style={{border:'1px solid #bfdbfe'}}>
              <div className="card-body" style={{padding:18}}>
                <div style={{fontSize:12,fontWeight:700,color:'#015C94',textTransform:'uppercase',letterSpacing:0.5,marginBottom:8}}>Aapka kaam</div>
                <ul style={{margin:0,paddingLeft:18,color:'#475569',fontSize:13,lineHeight:1.55}}>
                  {(duties.fills || []).map((item) => <li key={item}>{item}</li>)}
                </ul>
                <Link to="/complaints/create" className="btn btn-primary btn-sm" style={{marginTop:14,width:'100%',justifyContent:'center'}}>
                  Complete Registration
                </Link>
              </div>
            </div>

            {activities.length > 0 && (
              <div className="card">
                <div className="card-header">
                  <div className="card-title">
                    <div className="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                    Live Activity Feed
                  </div>
                </div>
                <div className="card-body" style={{ padding: '12px 16px' }}>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    {activities.slice(0, 5).map((act, i) => (
                      <div key={i} style={{ display: 'flex', gap: 10, alignItems: 'flex-start', fontSize: 12, borderBottom: i < 4 ? '1px solid #f1f5f9' : 'none', paddingBottom: 8 }}>
                        <span style={{ fontSize: 14 }}>⚡</span>
                        <div style={{ flex: 1 }}>
                          <div style={{ fontWeight: 600, color: '#1e293b' }}>{act.description}</div>
                          <div style={{ color: '#64748b', fontSize: 11, marginTop: 2 }}>{act.causer_name} · {act.created_at ? new Date(act.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}</div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}
        </div>
      </div>
    );
  }

  const maxReceived = monthlyTrends?.length ? Math.max(...monthlyTrends.map(t => t.received), 1) : 1;

  const trends = monthlyTrends?.length ? monthlyTrends : [];
  const categories = categoryBreakdown?.length ? categoryBreakdown : [];

  const totalCat = categories.reduce((s, c) => s + (c.count || c.total || 0), 0) || 1;

  const donutColors = ['#FDDF00', '#264078', '#267859', '#9b3232', '#1950c7'];
  let cumOffset = 0;
  const donutSegments = categories.map((c, i) => {
    const pct = ((c.count || c.total || 0) / totalCat) * 100;
    const circumference = 2 * Math.PI * 50;
    const length = (pct / 100) * circumference;
    const offset = cumOffset;
    cumOffset += length;
    return { ...c, pct, length, offset, color: donutColors[i % donutColors.length] };
  });

  return (
    <div className="dashboard_page">
      <div className="page-header">
        <div className="page-title-group">
          <div className="page-label">Overview</div>
          <h1 className="page-title">Dashboard</h1>
          <p className="page-subtitle">{new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })} &nbsp;·&nbsp; {stationLabel} &nbsp;·&nbsp; Multi-Role Oversight</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          <div className="filters-bar" style={{ margin: 0, padding: '10px 14px' }}>
             <span className="filter-label">Period</span>
             <div className="filter-date-range">
               <input type="date" id="dateFrom" aria-label="From date" value={filters.dateFrom} onChange={e => setFilters(f => ({ ...f, dateFrom: e.target.value }))} />
               <span className="date-sep">to</span>
               <input type="date" id="dateTo" aria-label="To date" value={filters.dateTo} onChange={e => setFilters(f => ({ ...f, dateTo: e.target.value }))} />
             </div>
             <select className="filter-select" id="accountFilter" aria-label="Account Level" value={filters.account} onChange={e => setFilters(f => ({ ...f, account: e.target.value }))}>
               <option>User Level Account</option>
               <option>Circle Level Account</option>
               <option>Zone Level Account</option>
               <option>HQ Level Account</option>
             </select>
             <button className="btn btn-primary btn-sm" id="applyFilterBtn" onClick={handleApplyFilter}>
               <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg> Apply
             </button>
             <button className="btn btn-outline btn-sm" id="exportBtn" title="Export Report" onClick={handleExport}>
               <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export
             </button>
          </div>
        </div>
      </div>

      <div className="role-portal-banner" style={{background:'linear-gradient(135deg,#2563eb,#1e40af)',color:'#fff',borderRadius:12,padding:'20px 24px',marginBottom:16,display:'flex',alignItems:'center',justifyContent:'space-between',flexWrap:'wrap',gap:16,boxShadow:'0 4px 16px rgba(37,99,235,0.25)'}}>
        <div style={{display:'flex',alignItems:'center',gap:16}}>
          <div style={{fontSize:36}}>{portal.icon}</div>
          <div>
            <div style={{fontSize:13,opacity:0.85,textTransform:'uppercase',letterSpacing:1}}>Welcome back, <strong>{user?.name || 'User'}</strong> &nbsp;·&nbsp; {stationLabel}</div>
            <div style={{fontSize:20,fontWeight:700}}>{portal.name}</div>
            <div style={{fontSize:13,opacity:0.9,marginTop:4}}>{portal.desc}</div>
          </div>
        </div>
        {canView('department_progress', user) && (
          <Link to="/department-progress" className="btn" style={{background:'#fff',color:'#1e40af',fontWeight:700,display:'flex',alignItems:'center',gap:6,boxShadow:'0 2px 8px rgba(0,0,0,0.15)'}}>
            📊 Department Progress & Monitoring
          </Link>
        )}
      </div>

      <div className="card" style={{marginBottom:24, border:'1px solid #e2e8f0'}}>
        <div className="card-body" style={{padding:'16px 20px'}}>
          <div style={{fontSize:12, fontWeight:700, color:'#015C94', textTransform:'uppercase', letterSpacing:0.6, marginBottom:6}}>
            Aapka stage: {duties.stage}
          </div>
          <div style={{fontSize:13, color:'#334155', marginBottom:8}}>Aap yeh fill / perform karenge:</div>
          <ul style={{margin:0, paddingLeft:18, color:'#475569', fontSize:13, lineHeight:1.55}}>
            {(duties.fills || []).map((item) => <li key={item}>{item}</li>)}
          </ul>
        </div>
      </div>

      {/* Role-Specific Summary Highlights */}
      <div className="stats-grid" style={{ marginBottom: 16 }}>
        <div className="stat-card blue">
          <div className="stat-value">{stats.assigned_enquiries || 0}</div>
          <div className="stat-label">Assigned Enquiries</div>
          <div className="stat-footer"><span>Active Investigations</span><span className="stat-footer-value">{stats.active_investigations || 0}</span></div>
        </div>
        <div className="stat-card orange">
          <div className="stat-value">{stats.pending_cfrs || 0}</div>
          <div className="stat-label">Pending CFRs / Scrutiny</div>
          <div className="stat-footer"><span>Awaiting CFR Submission</span></div>
        </div>
        <div className="stat-card red">
          <div className="stat-value">{stats.unserved_summons || 0}</div>
          <div className="stat-label">Unserved Summons</div>
          <div className="stat-footer"><span>Non-Appearance Check</span></div>
        </div>
        <div className="stat-card green">
          <div className="stat-value">{stats.pending_approvals || 0}</div>
          <div className="stat-label">Pending Approvals</div>
          <div className="stat-footer"><span>CFR & Reports</span></div>
        </div>
      </div>

      <div className="stats-grid">
        <div className="stat-card green">
          <div className="stat-card-top">
            <div className="stat-icon green">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div className="stat-trend up">↑ {stats.total_verifications || 0}</div>
          </div>
          <div className="stat-value">{stats.total_verifications || 0}</div>
          <div className="stat-label">Total Verifications</div>
          <div className="stat-footer"><span>Pending</span><span className="stat-footer-value" style={{ color: '#e5a100' }}>{stats.pending_verifications || 0}</span></div>
        </div>
        <div className="stat-card orange">
          <div className="stat-card-top">
            <div className="stat-icon orange">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div className="stat-trend neutral">{stats.pending_review || 0}</div>
          </div>
          <div className="stat-value">{stats.pending_review || 0}</div>
          <div className="stat-label">Pending Review</div>
          <div className="stat-footer"><span>Avg Wait</span><span className="stat-footer-value">{stats.avg_wait_days || 0}d</span></div>
        </div>
        <div className="stat-card teal">
          <div className="stat-card-top">
            <div className="stat-icon teal">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div className="stat-trend up">{stats.finalized_this_month || 0}</div>
          </div>
          <div className="stat-value">{stats.finalized_cases || 0}</div>
          <div className="stat-label">Finalized Cases</div>
          <div className="stat-footer"><span>This Month</span><span className="stat-footer-value">{stats.finalized_this_month || 0}</span></div>
        </div>
        <div className="stat-card blue">
          <div className="stat-card-top">
            <div className="stat-icon blue">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <div className="stat-trend up">{stats.active_enquiries || 0}</div>
          </div>
          <div className="stat-value">{stats.converted_to_enquiry || 0}</div>
          <div className="stat-label">Converted to Enquiry</div>
          <div className="stat-footer"><span>Active Enquiries</span><span className="stat-footer-value">{stats.active_enquiries || 0}</span></div>
        </div>
        <div className="stat-card gold">
          <div className="stat-card-top">
            <div className="stat-icon gold">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div className="stat-trend down">{stats.awaiting_approval || 0}</div>
          </div>
          <div className="stat-value">{stats.recommended_closure || 0}</div>
          <div className="stat-label">Recommended Closure</div>
          <div className="stat-footer"><span>Awaiting Approval</span><span className="stat-footer-value">{stats.awaiting_approval || 0}</span></div>
        </div>
        <div className="stat-card gray">
          <div className="stat-card-top">
            <div className="stat-icon gray">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <div className="stat-trend neutral">{stats.closed_np_last_30 || 0}</div>
          </div>
          <div className="stat-value">{stats.closed_non_pursuance || 0}</div>
          <div className="stat-label">Closed (Non-Pursuance)</div>
          <div className="stat-footer"><span>Last 30 days</span><span className="stat-footer-value">{stats.closed_np_last_30 || 0}</span></div>
        </div>
        <div className="stat-card red">
          <div className="stat-card-top">
            <div className="stat-icon red">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div className="stat-trend up">{stats.ne_under_review || 0}</div>
          </div>
          <div className="stat-value">{stats.closed_non_evidence || 0}</div>
          <div className="stat-label">Closed (Non-Evidence)</div>
          <div className="stat-footer"><span>Under Review</span><span className="stat-footer-value">{stats.ne_under_review || 0}</span></div>
        </div>
        <div className="stat-card purple">
          <div className="stat-card-top">
            <div className="stat-icon purple">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div className="stat-trend down">{stats.avg_processing_days || 0}</div>
          </div>
          <div className="stat-value">{stats.avg_processing_days || 0}</div>
          <div className="stat-label">Avg Processing Days</div>
          <div className="stat-footer"><span>Target</span><span className="stat-footer-value" style={{ color: '#38a169' }}>≤ 7 days ✓</span></div>
        </div>
      </div>

      <div className="mini-stats-row">
        <div className="mini-stat"><div className="mini-stat-value">{stats.transfer_circles || 0}</div><div className="mini-stat-label">Transfer (Circles)</div></div>
        <div className="mini-stat"><div className="mini-stat-value">{stats.merge_other || 0}</div><div className="mini-stat-label">Merge (Other Complaint)</div></div>
        <div className="mini-stat"><div className="mini-stat-value">{stats.jurisdiction_wanted || 0}</div><div className="mini-stat-label">Jurisdiction Wanted</div></div>
        <div className="mini-stat"><div className="mini-stat-value">{stats.overall_performance || 0}%</div><div className="mini-stat-label">Overall Performance</div></div>
      </div>

      <div className="dashboard-grid">
        <div className="card">
          <div className="card-header">
            <div className="card-title">
              <div className="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
              Monthly Complaint Trends ({new Date().getFullYear()})
            </div>
            <div className="section-actions">
              <button className="btn btn-outline btn-sm btn-icon" title="Expand chart" onClick={() => setChartExpanded(!chartExpanded)}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
              </button>
            </div>
          </div>
          <div className="card-body">
            <div style={{ display: 'flex', gap: 16, marginBottom: 16, flexWrap: 'wrap' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: '#6c757d' }}><span style={{ width: 12, height: 12, borderRadius: 2, background: '#015C94', display: 'inline-block' }}></span> Complaints Received</div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: '#6c757d' }}><span style={{ width: 12, height: 12, borderRadius: 2, background: '#e5a100', display: 'inline-block' }}></span> Resolved</div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: '#6c757d' }}><span style={{ width: 12, height: 12, borderRadius: 2, background: '#264078', display: 'inline-block' }}></span> Converted to Enquiry</div>
            </div>
            <div className="chart-container" style={chartExpanded ? { position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, zIndex: 9999, background: 'white', padding: 24 } : {}}>
              <div className="chart-bar-group">
                {trends.map((t, i) => (
                  <div key={i} style={{ display: 'flex', gap: 2, alignItems: 'flex-end' }}>
                    <div className="chart-bar-item">
                      <div className="chart-bar primary" style={{ height: `${(t.received / maxReceived) * 100}%`, minHeight: t.received > 0 ? 4 : 0 }} title={`Received: ${t.received}`}></div>
                    </div>
                    <div className="chart-bar-item">
                      <div className="chart-bar gold" style={{ height: `${(t.resolved / maxReceived) * 100}%`, minHeight: t.resolved > 0 ? 4 : 0 }} title={`Resolved: ${t.resolved}`}></div>
                    </div>
                    <div className="chart-bar-item">
                      <div className="chart-bar blue" style={{ height: `${(t.converted / maxReceived) * 100}%`, minHeight: t.converted > 0 ? 4 : 0 }} title={`Enquiry: ${t.converted}`}></div>
                    </div>
                  </div>
                ))}
              </div>
              <div className="chart-x-axis">
                {trends.map((t, i) => (
                  <span key={i} className="chart-x-label" style={{ flex: 3 }}>{t.month}</span>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <div className="card-title">
              <div className="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg></div>
              Complaint Categories
            </div>
          </div>
          <div className="card-body">
            <div className="donut-wrap" style={{ flexDirection: 'column', gap: 16 }}>
              <svg className="donut-svg" viewBox="0 0 120 120" width="120" height="120" style={{ margin: '0 auto', display: 'block' }}>
                <circle cx="60" cy="60" r="50" fill="none" stroke="#f3f0f0" strokeWidth="20"/>
                {donutSegments.map((seg, i) => {
                  const circumference = 2 * Math.PI * 50;
                  return (
                    <circle key={i} cx="60" cy="60" r="50" fill="none" stroke={seg.color} strokeWidth="20"
                      strokeDasharray={`${seg.length} ${circumference - seg.length}`}
                      strokeDashoffset={-seg.offset}
                      transform="rotate(-90 60 60)" />
                  );
                })}
                <text x="60" y="55" textAnchor="middle" fontSize="13" fontWeight="700" fill="#000" fontFamily="Cinzel">{totalCat}</text>
                <text x="60" y="68" textAnchor="middle" fontSize="8" fill="#6c757d">Total Cases</text>
              </svg>
              <div className="donut-legend">
                {categories.map((c, i) => (
                  <div key={i} className="donut-legend-item">
                    <div className="donut-dot" style={{ background: donutColors[i % donutColors.length] }}></div>
                    <span className="donut-label">{c.name}</span>
                    <span className="donut-val">{c.count || c.total || 0} <span style={{ fontSize: 11, color: '#6c757d', fontWeight: 400 }}>({Math.round(((c.count || c.total || 0) / totalCat) * 100)}%)</span></span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="dashboard-grid" style={{ gridTemplateColumns: '2fr 1fr' }}>
        <div className="card">
          <div className="card-header">
            <div className="card-title">
              <div className="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
              Recent Complaints
            </div>
            <div className="section-actions">
              <Link to="/complaints" className="btn btn-outline btn-sm">View All</Link>
              <Link to="/complaints/create" className="btn btn-primary btn-sm"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> New</Link>
            </div>
          </div>
          <div className="card-body" style={{ padding: 0 }}>
            <div className="table-responsive">
              <table className="data-table" style={{ display: 'table', width: '100%' }} aria-label="Recent Complaints">
                <thead>
                  <tr>
                    <th>Case ID</th>
                    <th>Complainant</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Officer</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style={{ textAlign: 'center' }}>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {recent.length === 0 ? (
                    <tr><td colSpan={8} style={{ textAlign: 'center', padding: 24, color: '#6c757d' }}>No complaints found</td></tr>
                  ) : recent.map(c => {
                    const dotCount = c.priority_type === 'critical' ? 3 : c.priority_type === 'high' ? 2 : 1;
                    const initials = (c.complainant_name || '').split(' ').map(w => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';
                    const statusLabel = c.status === 'complete' ? 'Finalized' : c.status === 'in_progress' ? 'Pending' : c.status || 'Pending';
                    const badgeClass = c.status === 'complete' ? 'badge-finalized' : 'badge-pending';
                    return (
                      <tr key={c.id}>
                        <td><span className="table-id">#{c.tracking_no || c.id}</span></td>
                        <td>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <div style={{ width: 28, height: 28, borderRadius: '50%', background: 'rgba(38,64,120,0.12)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 600, color: '#2B2B2B' }}>{initials}</div>
                            <span style={{ fontSize: 13, fontWeight: 500 }}>{c.complainant_name}</span>
                          </div>
                        </td>
                        <td><span style={{ fontSize: 12.5 }}>{c.offence_type}</span></td>
                        <td>
                          <div className="priority-dots" title={['', 'Normal', 'High', 'Critical'][dotCount]}>
                            {[0, 1, 2].map(i => (
                              <div key={i} className={`priority-dot${i < dotCount ? ' filled ' + (c.priority_type || 'low') : ''}`}></div>
                            ))}
                          </div>
                        </td>
                        <td><span style={{ fontSize: 12.5, color: '#6c757d' }}>{c.operator_name || '-'}</span></td>
                        <td><span className={`badge ${badgeClass}`}>{statusLabel}</span></td>
                        <td><span style={{ fontSize: 12, color: '#6c757d' }}>{new Date(c.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}</span></td>
                        <td style={{ textAlign: 'center' }}>
                          <div style={{ display: 'flex', gap: 6, justifyContent: 'center' }}>
                            <Link to={`/complaints/${c.id}/edit`} className="btn btn-outline btn-sm btn-icon" title="Edit">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </Link>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
          <div className="card">
            <div className="card-header">
              <div className="card-title"><div className="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>Quick Stats</div>
            </div>
            <div className="card-body">
              <div className="progress-list">
                <div className="progress-item"><div className="progress-header"><span className="progress-name">Total Complaints</span><span className="progress-count">{stats.total_complaints || 0}</span></div><div className="progress-bar-bg"><div className="progress-bar-fill" style={{ width: '100%', background: 'linear-gradient(90deg,#015C94,#27adff)' }}></div></div></div>
                <div className="progress-item"><div className="progress-header"><span className="progress-name">Avg Workflow Completion</span><span className="progress-count">{stats.avg_completion || 0}%</span></div><ProgressBar value={stats.avg_completion} color="#264078" /></div>
                {(() => {
                  const ws = stats.workflow_stages || {};
                  const total = (stats.total_complaints || 0) || 1;
                  return [
                    ['Registered', ws.registered, '#e53e3e'],
                    ['Scrutiny Complete', ws.scrutiny_complete, '#e5a100'],
                    ['Under Verification', ws.verification, '#d69e2e'],
                    ['Verification Submitted', ws.verification_submitted, '#267859'],
                    ['Enquiry Stage', ws.enquiry, '#264078'],
                    ['Case Filed', ws.case_filed, '#1950c7'],
                    ['Resolved', ws.resolved, '#38a169'],
                  ].filter(([, n]) => n > 0).map(([name, n, color]) => (
                    <div className="progress-item" key={name}>
                      <div className="progress-header">
                        <span className="progress-name">{name}</span>
                        <span className="progress-count">{n} · {Math.round((n / total) * 100)}%</span>
                      </div>
                      <ProgressBar value={(n / total) * 100} color={color} />
                    </div>
                  ));
                })()}
                <div className="progress-item"><div className="progress-header"><span className="progress-name">Performance (Resolved/Total)</span><span className="progress-count">{stats.overall_performance || 0}%</span></div><ProgressBar value={stats.overall_performance} color="#015C94" /></div>
              </div>
            </div>
          </div>

          {/* Live Activity Feed */}
          <div className="card">
            <div className="card-header">
              <div className="card-title">
                <div className="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
                Live Activity Feed
              </div>
            </div>
            <div className="card-body" style={{ padding: '12px 16px' }}>
              {activities.length === 0 ? (
                <div style={{ color: '#94a3b8', fontSize: 13, textAlign: 'center', padding: 12 }}>No recent activity recorded yet</div>
              ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                  {activities.slice(0, 7).map((act, i) => (
                    <div key={i} style={{ display: 'flex', gap: 10, alignItems: 'flex-start', fontSize: 12, borderBottom: i < 6 ? '1px solid #f1f5f9' : 'none', paddingBottom: 8 }}>
                      <span style={{ fontSize: 15 }}>
                        {act.type === 'complaint' ? '📝' : act.type === 'verification' ? '📋' : act.type === 'enquiry' ? '🔍' : '📁'}
                      </span>
                      <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 600, color: '#1e293b' }}>{act.description}</div>
                        <div style={{ color: '#64748b', fontSize: 11, marginTop: 2 }}>{act.causer_name} · {act.created_at ? new Date(act.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}</div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
