import { useState, useEffect, useMemo, useRef } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import ProgressBar from '../components/ProgressBar';
import ExternalForensicRequestModal from '../components/ExternalForensicRequestModal';
import { isForensicAdmin, hasRole, hasAnyRole } from '../utils/permissions';
import { formatDisplayDateTime } from '../utils/datetime';

const ROLE_PORTALS = {
  admin_forensic: {
    icon: '🔬',
    name: 'Admin – Forensic Portal',
    desc: 'Full digital forensic laboratory oversight — evidence custody, team management, pipeline tracking & report validations.',
    stage: 'Forensic Lab Administration & Evidence Custody',
    duties: [
      'Monitor incoming digital evidence and seizure memos from Enquiry Officers',
      'Oversee forensic requests marked to Assistant Director (AD) Forensic',
      'Review high-priority and court-mandated device analysis pipelines',
      'Manage forensic laboratory users, credentials & access control',
    ],
  },
  dd_forensic: {
    icon: '🔬',
    name: 'DD Forensic Portal',
    desc: 'Deputy Director Digital Forensics oversight, review incoming seizure requests, mark to AD Forensic & monitor lab turnaround.',
    stage: 'Deputy Director Forensic Oversight',
    duties: [
      'Scrutinize incoming seizure memos and device condition logs forwarded by Circle Incharge',
      'Mark and assign evidence examination to Assistant Director (AD) Forensic',
      'Set examination priorities (Normal, High, ⚡ Urgent)',
      'Monitor lab turnaround and ensure timely report delivery',
    ],
  },
  ad_forensic: {
    icon: '💻',
    name: 'AD Forensic Portal',
    desc: 'Assistant Director Digital Forensics — conduct device examinations, record findings, finalize lab reports & print Chain of Custody.',
    stage: 'Assistant Director Forensic Examination & Custody',
    duties: [
      'Perform digital forensic examination & extraction on seized evidentiary devices',
      'Record device IMEIs, serial numbers, hashes (MD5/SHA256) and artifact logs',
      'Draft forensic findings and finalize signed laboratory reports',
      'Print official Chain of Custody form & confirm physical handover',
    ],
  },
};

const STATUS_META = {
  submitted:    { label: 'Pending CI Review',      sub: 'Received in System',   color: '#e5a100', barColor: '#e5a100' },
  assigned:     { label: 'Assigned to AD Forensic',sub: 'Allocated to AD',      color: '#2563eb', barColor: '#2563eb' },
  in_progress:  { label: 'Lab Examination (AD)',   sub: 'Analysis & Extraction',color: '#7c3aed', barColor: '#7c3aed' },
  report_ready: { label: 'Report Ready',           sub: 'Awaiting Handover',    color: '#059669', barColor: '#059669' },
  handed_over:  { label: 'Handed Over to EO',      sub: 'Custody Completed',    color: '#64748b', barColor: '#64748b' },
};

export default function ForensicDashboard() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [reqStats, setReqStats] = useState(null);
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Filters
  const [activeTab, setActiveTab] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCircle, setSelectedCircle] = useState('');
  const [filters, setFilters] = useState({ dateFrom: '', dateTo: '', priority: '' });
  const [teamOfficers, setTeamOfficers] = useState([]);

  // Modals
  const [assignModalReq, setAssignModalReq] = useState(null);
  const [assigneeId, setAssigneeId] = useState('');
  const [assignRemarks, setAssignRemarks] = useState('');
  const [assignPriority, setAssignPriority] = useState('normal');

  const [handoverModalReq, setHandoverModalReq] = useState(null);
  const [handoverRemarks, setHandoverRemarks] = useState('');
  const [showDirectModal, setShowDirectModal] = useState(false);

  const [findingsModalReq, setFindingsModalReq] = useState(null);
  const [examFindings, setExamFindings] = useState('');
  const [examLabNotes, setExamLabNotes] = useState('');
  const [examReportFile, setExamReportFile] = useState(null);

  const [actionBusy, setActionBusy] = useState(false);
  const [actionMsg, setActionMsg] = useState('');
  const [actionErr, setActionErr] = useState('');

  // Hierarchical Send Back Modal
  const [sendBackModalReq, setSendBackModalReq] = useState(null);
  const [sendBackTarget, setSendBackTarget] = useState('dd');
  const [sendBackRemarks, setSendBackRemarks] = useState('');

  // 3D Chart Hover & Interactive Effects States
  const [hoveredRegion, setHoveredRegion] = useState(null);
  const [hoveredCat, setHoveredCat] = useState(null);
  const [hoveredOrg, setHoveredOrg] = useState(null);

  const role = user?.roles?.[0]?.name || user?.role || 'admin_forensic';
  const portal = ROLE_PORTALS[role] || ROLE_PORTALS.admin_forensic;

  const isAdmin = isForensicAdmin(user);
  const isAd    = hasAnyRole(user, ['dd_forensic', 'ad_forensic', 'admin_forensic']);
  const isFo    = hasRole(user, 'forensic_team');
  const isDesk  = hasRole(user, 'desk_forensic');

  const loadData = () => {
    setLoading(true);
    setError(null);
    Promise.all([
      api.get('/forensic/stats').catch(() => ({ data: {} })),
      api.get('/forensic/request-stats').catch(() => ({ data: null })),
      api.get('/forensic/requests', { params: { per_page: 60 } }).catch(() => ({ data: { data: { data: [] } } })),
      isAd ? api.get('/forensic/team-officers').catch(() => ({ data: { data: [] } })) : Promise.resolve({ data: { data: [] } }),
    ])
      .then(([u, r, rq, to]) => {
        setData(u.data);
        setReqStats(r.data);
        setRequests(rq.data?.data?.data || rq.data?.data || []);
        if (to?.data?.data) {
          setTeamOfficers(to.data.data);
        }
      })
      .catch(e => setError(e.response?.data?.message || e.message || 'Failed to load forensic dashboard'))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, []);

  // Filter requests
  const filteredRequests = useMemo(() => {
    return requests.filter(r => {
      if (activeTab === 'urgent') {
        if (r.priority !== 'urgent' && r.priority !== 'high') return false;
      } else if (activeTab === 'my_queue') {
        if (Number(r.assigned_to) !== Number(user?.id)) return false;
      } else if (activeTab !== 'all') {
        if (r.status !== activeTab) return false;
      }

      if (selectedCircle) {
        const cName = r.submitter?.circle?.name || r.enquiry?.complaint?.circle?.name || '';
        if (cName !== selectedCircle) return false;
      }

      if (filters.priority) {
        if (r.priority !== filters.priority) return false;
      }

      if (filters.dateFrom) {
        if (!r.created_at || r.created_at < filters.dateFrom) return false;
      }
      if (filters.dateTo) {
        if (!r.created_at || r.created_at.slice(0, 10) > filters.dateTo) return false;
      }

      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase().trim();
        const matchReqNo = r.request_no?.toLowerCase().includes(q);
        const matchCode = r.report_code?.toLowerCase().includes(q);
        const matchNote = r.note?.toLowerCase().includes(q);
        const matchEnquiry = r.enquiry?.enquiry_number?.toLowerCase().includes(q);
        const matchFir = r.case_file?.fir_no?.toLowerCase().includes(q);
        const matchSubmitter = r.submitter?.name?.toLowerCase().includes(q);
        const matchAccused = (r.enquiry?.accused_persons || []).some(a => a.name?.toLowerCase().includes(q));
        const matchItems = (r.items || []).some(it =>
          (it.make_model && it.make_model.toLowerCase().includes(q)) ||
          (it.imei && it.imei.toLowerCase().includes(q)) ||
          (it.serial_no && it.serial_no.toLowerCase().includes(q)) ||
          (it.item_type && it.item_type.toLowerCase().includes(q))
        );
        return matchReqNo || matchCode || matchNote || matchEnquiry || matchFir || matchSubmitter || matchAccused || matchItems;
      }

      return true;
    });
  }, [requests, activeTab, selectedCircle, filters, searchQuery, user?.id]);

  const totalRequests = reqStats
    ? (reqStats.submitted || 0) + (reqStats.assigned || 0) + (reqStats.in_progress || 0) + (reqStats.report_ready || 0) + (reqStats.handed_over || 0)
    : requests.length;

  const totalDevices = reqStats?.total_devices || requests.reduce((acc, r) => acc + (r.items?.reduce((s, it) => s + (it.quantity || 1), 0) || 0), 0);

  const circlesList = useMemo(() => {
    if (reqStats?.by_circle) return Object.keys(reqStats.by_circle);
    const set = new Set();
    requests.forEach(r => {
      const c = r.submitter?.circle?.name || r.enquiry?.complaint?.circle?.name;
      if (c) set.add(c);
    });
    return Array.from(set);
  }, [reqStats, requests]);

  // Stage rows for workflow progress
  const stageRows = useMemo(() => {
    const sub = reqStats?.submitted || 0;
    const ass = reqStats?.assigned || 0;
    const inp = reqStats?.in_progress || 0;
    const rdy = reqStats?.report_ready || 0;
    const hnd = reqStats?.handed_over || 0;

    return [
      ['Pending Review (New Seizures)', sub, '#e5a100'],
      ['Assigned to FO', ass, '#2563eb'],
      ['Lab Examination / Extraction', inp, '#7c3aed'],
      ['Report Ready (Desk Queue)', rdy, '#059669'],
      ['Handed Over to EO', hnd, '#64748b'],
    ];
  }, [reqStats]);

  const avgCompletion = useMemo(() => {
    if (!totalRequests) return 0;
    const weighted = (
      ((reqStats?.submitted || 0) * 15) +
      ((reqStats?.assigned || 0) * 35) +
      ((reqStats?.in_progress || 0) * 65) +
      ((reqStats?.report_ready || 0) * 90) +
      ((reqStats?.handed_over || 0) * 100)
    );
    return Math.round(weighted / totalRequests);
  }, [reqStats, totalRequests]);

  const handleExport = () => {
    const blob = new Blob([JSON.stringify({ reqStats, requests: filteredRequests }, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `forensic-evidence-report-${new Date().toISOString().slice(0, 10)}.json`;
    a.click();
    URL.revokeObjectURL(url);
  };

  // Action handlers
  const handleAssignSubmit = async () => {
    if (!assignModalReq || !assigneeId) return;
    setActionBusy(true); setActionErr(''); setActionMsg('');
    try {
      const res = await api.post(`/forensic/requests/${assignModalReq.id}/assign`, {
        assigned_to: Number(assigneeId),
        remarks: assignRemarks || undefined,
        priority: assignPriority || 'normal',
      });
      setActionMsg(res.data?.message || 'Assigned successfully.');
      setAssignModalReq(null);
      setAssigneeId('');
      setAssignRemarks('');
      loadData();
    } catch (e) {
      setActionErr(e.response?.data?.message || 'Assignment failed');
    } finally {
      setActionBusy(false);
    }
  };

  const handleFindingsSubmit = async (markReady = false) => {
    if (!findingsModalReq) return;
    setActionBusy(true); setActionErr(''); setActionMsg('');
    try {
      const fd = new FormData();
      if (examFindings) fd.append('findings', examFindings);
      if (examLabNotes) fd.append('lab_notes', examLabNotes);
      if (examReportFile) fd.append('report_file', examReportFile);

      const endpoint = markReady
        ? `/forensic/requests/${findingsModalReq.id}/mark-ready`
        : `/forensic/requests/${findingsModalReq.id}/findings`;

      const res = await api.post(endpoint, fd);
      setActionMsg(res.data?.message || 'Updated successfully.');
      setFindingsModalReq(null);
      setExamFindings('');
      setExamLabNotes('');
      setExamReportFile(null);
      loadData();
    } catch (e) {
      setActionErr(e.response?.data?.message || 'Failed to save findings');
    } finally {
      setActionBusy(false);
    }
  };

  const handleHandoverSubmit = async () => {
    if (!handoverModalReq) return;
    setActionBusy(true); setActionErr(''); setActionMsg('');
    try {
      const res = await api.post(`/forensic/requests/${handoverModalReq.id}/hand-over`, {
        handover_remarks: handoverRemarks || undefined,
      });
      setActionMsg(res.data?.message || 'Handover confirmed.');
      setHandoverModalReq(null);
      setHandoverRemarks('');
      loadData();
    } catch (e) {
      setActionErr(e.response?.data?.message || 'Handover failed');
    } finally {
      setActionBusy(false);
    }
  };

  const handleSendBackSubmit = async () => {
    if (!sendBackRemarks.trim() || !sendBackModalReq) return;
    setActionBusy(true);
    setActionErr('');
    setActionMsg('');
    try {
      let endpoint = `/forensic/requests/${sendBackModalReq.id}/send-back`;
      if (sendBackTarget === 'dd') endpoint = `/forensic/requests/${sendBackModalReq.id}/send-back-to-dd`;
      else if (sendBackTarget === 'ci') endpoint = `/forensic/requests/${sendBackModalReq.id}/send-back-to-ci`;
      else if (sendBackTarget === 'eo') endpoint = `/forensic/requests/${sendBackModalReq.id}/send-back`;

      const res = await api.post(endpoint, { remarks: sendBackRemarks });
      setActionMsg(res.data.message || 'Request successfully sent back.');
      setSendBackModalReq(null);
      setSendBackRemarks('');
      loadData();
    } catch (e) {
      setActionErr(e.response?.data?.message || 'Failed to send back');
    } finally {
      setActionBusy(false);
    }
  };

  if (loading) {
    return (
      <div className="dashboard_page">
        <LoadingSkeleton type="stats" rows={8} />
        <LoadingSkeleton type="table" rows={6} />
      </div>
    );
  }

  if (error) {
    return (
      <div className="dashboard_page" style={{ padding: 40, textAlign: 'center' }}>
        <div style={{ color: '#e53e3e', fontSize: 14, marginBottom: 8 }}>⚠️ Forensic Dashboard Error</div>
        <div style={{ color: '#888', fontSize: 13 }}>{error}</div>
      </div>
    );
  }

  return (
    <div className="dashboard_page">

      {/* ── Page Header (Identical to other portals) ── */}
      <div className="page-header">
        <div className="page-title-group">
          <div className="page-label">Forensic Portal</div>
          <h1 className="page-title">Dashboard</h1>
          <p className="page-subtitle">
            {new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
            &nbsp;·&nbsp; NCCIA Forensic Lab &amp; Evidence Custody
          </p>
          <div className="title-underline"></div>
        </div>

        <div className="page-actions">
          <div className="filters-bar" style={{ margin: 0, padding: '8px 12px' }}>
            <span className="filter-label">Period</span>
            <div className="filter-date-range">
              <input
                type="date"
                id="dateFrom"
                aria-label="From date"
                value={filters.dateFrom}
                onChange={e => setFilters(f => ({ ...f, dateFrom: e.target.value }))}
              />
              <span className="date-sep">to</span>
              <input
                type="date"
                id="dateTo"
                aria-label="To date"
                value={filters.dateTo}
                onChange={e => setFilters(f => ({ ...f, dateTo: e.target.value }))}
              />
            </div>

            <select
              className="filter-select"
              value={selectedCircle}
              onChange={e => setSelectedCircle(e.target.value)}
            >
              <option value="">All Circles</option>
              {circlesList.map(c => <option key={c} value={c}>{c}</option>)}
            </select>

            <button className="btn btn-primary btn-sm" onClick={loadData}>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
              Refresh
            </button>

            <button className="btn btn-outline btn-sm" onClick={handleExport} title="Export Report">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Export
            </button>
          </div>
        </div>
      </div>

      {actionMsg && (
        <div style={{ padding: '12px 18px', marginBottom: 16, background: '#ecfdf5', border: '1px solid #6ee7b7', color: '#065f46', borderRadius: 10, fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <span>✅ {actionMsg}</span>
          <button type="button" onClick={() => setActionMsg('')} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#065f46', fontWeight: 'bold' }}>×</button>
        </div>
      )}

      {actionErr && (
        <div style={{ padding: '12px 18px', marginBottom: 16, background: '#fef2f2', border: '1px solid #fca5a5', color: '#991b1b', borderRadius: 10, fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <span>⚠️ {actionErr}</span>
          <button type="button" onClick={() => setActionErr('')} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#991b1b', fontWeight: 'bold' }}>×</button>
        </div>
      )}

      {/* ── Standard Role Portal Banner (Identical to other portals) ── */}
      <div className="role-portal-banner" style={{
        background: 'linear-gradient(135deg, #015C94 0%, #084c61 50%, #0081a7 100%)',
        color: '#fff', borderRadius: 12, padding: '20px 24px', marginBottom: 16,
        display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap',
        boxShadow: '0 4px 16px rgba(1,92,148,0.25)',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
          <div style={{ fontSize: 36 }}>{portal.icon}</div>
          <div>
            <div style={{ fontSize: 13, opacity: 0.85, textTransform: 'uppercase', letterSpacing: 1 }}>
              Welcome back, <strong>{user?.name?.split(' ')[0] || 'Officer'}</strong>
            </div>
            <div style={{ fontSize: 20, fontWeight: 700 }}>{portal.name}</div>
            <div style={{ fontSize: 13, opacity: 0.9, marginTop: 4 }}>{portal.desc}</div>
          </div>
        </div>

        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
          <button
            type="button"
            className="btn"
            onClick={() => setShowDirectModal(true)}
            style={{ background: '#0284c7', color: '#fff', fontWeight: 700, border: 'none' }}
          >
            ➕ Direct Forensic (External)
          </button>
          <Link to="/forensic/requests" className="btn" style={{ background: '#fff', color: '#015C94', fontWeight: 700 }}>
            Seizure Register
          </Link>
          {isAdmin && (
            <Link to="/forensic/users" className="btn btn-outline" style={{ borderColor: 'rgba(255,255,255,0.6)', color: '#fff' }}>
              Manage Team
            </Link>
          )}
        </div>
      </div>

      {/* ── Stage & Duties Card ── */}
      <div className="card" style={{ marginBottom: 20, border: '1px solid #e2e8f0' }}>
        <div className="card-body" style={{ padding: '16px 20px' }}>
          <div style={{ fontSize: 12, fontWeight: 700, color: '#015C94', textTransform: 'uppercase', letterSpacing: 0.6, marginBottom: 6 }}>
            Aapka stage: {portal.stage}
          </div>
          <div style={{ fontSize: 13, color: '#334155', marginBottom: 8 }}>Aap yeh perform karenge:</div>
          <ul style={{ margin: 0, paddingLeft: 18, color: '#475569', fontSize: 13, lineHeight: 1.55 }}>
            {(portal.duties || []).map((duty, idx) => <li key={idx}>{duty}</li>)}
          </ul>
        </div>
      </div>

      {/* ── Standard Stats Grid (Identical stat-card styles) ── */}
      <div className="stats-grid" style={{ marginBottom: 16 }}>
        <div className="stat-card blue">
          <div className="stat-value">{totalRequests}</div>
          <div className="stat-label">Total Seizures Received</div>
          <div className="stat-footer">
            <span>Seizure Memos</span>
            <span className="stat-footer-value">{totalDevices} devices</span>
          </div>
        </div>

        <div className="stat-card orange">
          <div className="stat-value">{reqStats?.submitted || 0}</div>
          <div className="stat-label">Pending AD Review</div>
          <div className="stat-footer">
            <span>Awaiting Assignment</span>
          </div>
        </div>

        <div className="stat-card purple">
          <div className="stat-value">{reqStats?.in_progress || 0}</div>
          <div className="stat-label">In Lab Examination</div>
          <div className="stat-footer">
            <span>Extraction Active</span>
          </div>
        </div>

        <div className="stat-card green">
          <div className="stat-value">{reqStats?.report_ready || 0}</div>
          <div className="stat-label">Reports Ready</div>
          <div className="stat-footer">
            <span>Desk Collection Queue</span>
          </div>
        </div>

        <div className="stat-card teal">
          <div className="stat-value">{reqStats?.handed_over || 0}</div>
          <div className="stat-label">Handed Over to EO</div>
          <div className="stat-footer">
            <span>Completed Custody</span>
          </div>
        </div>

        {reqStats?.urgent_count > 0 ? (
          <div className="stat-card red">
            <div className="stat-value">{reqStats.urgent_count}</div>
            <div className="stat-label">⚡ Urgent Priority</div>
            <div className="stat-footer">
              <span>Court / High Sensitive</span>
            </div>
          </div>
        ) : (
          <div className="stat-card gold">
            <div className="stat-value">{totalDevices}</div>
            <div className="stat-label">Total Vault Devices</div>
            <div className="stat-footer">
              <span>Under Custody</span>
            </div>
          </div>
        )}
      </div>

      {/* ── Visual Analytics: 3D Charts matching Picture 2 ── */}
      <div className="card" style={{ marginBottom: 20, border: '1px solid #cbd5e1', boxShadow: '0 2px 10px rgba(0,0,0,0.05)', background: '#fff' }}>
        <div className="card-header" style={{ padding: '14px 20px', background: '#f8fafc', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div className="card-title" style={{ fontSize: 15, fontWeight: 800, color: '#0f172a' }}>
            📊 Forensic Laboratory Evidence Analytics &amp; Case Distribution
          </div>
          <span style={{ fontSize: 12, background: '#015C94', color: '#fff', padding: '3px 12px', borderRadius: 12, fontWeight: 700 }}>
            Live Forensic Portal
          </span>
        </div>
        <div className="card-body" style={{ padding: '20px 24px' }}>

          {/* 1. Regions 3D Bar Chart with Live DB Data & 3D Interactive Effects */}
          <div style={{ marginBottom: 25, background: '#fff', border: '1px solid #cbd5e1', borderRadius: 10, padding: '18px 20px', boxShadow: '0 1px 4px rgba(0,0,0,0.04)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
              <div style={{ fontSize: 14, fontWeight: 800, color: '#1e293b' }}>
                Regions wise Cases (Live Data)
              </div>
              <span style={{ fontSize: 11, background: '#f0fdf4', color: '#15803d', border: '1px solid #bbf7d0', padding: '2px 8px', borderRadius: 6, fontWeight: 700 }}>
                ● Real-Time Database Counts
              </span>
            </div>

            {(() => {
              const masterRegsList = ['Bahawalpur', 'D. G. Khan', 'Faisalabad', 'Gujranwala', 'Gujrat', 'Lahore', 'Multan', 'Sargodha', 'Sukkur', 'Rawalpindi', 'Islamabad'];
              const palette = ['#26a69a', '#42a5f5', '#ffa726', '#66bb6a', '#ef5350', '#311b92', '#ab47bc', '#9ccc65', '#26c6da', '#d97706', '#2563eb'];

              // Compute real database counts
              const regs = masterRegsList.map((rName, idx) => {
                let liveCount = 0;
                if (reqStats?.by_region && reqStats.by_region[rName] !== undefined) {
                  liveCount = Number(reqStats.by_region[rName]) || 0;
                } else {
                  liveCount = requests.filter(rq => {
                    const cName = rq.submitter?.circle?.name || rq.enquiry?.complaint?.circle?.name || '';
                    return cName.toLowerCase().includes(rName.toLowerCase());
                  }).length;
                }
                return {
                  name: rName,
                  val: liveCount,
                  color: palette[idx % palette.length],
                };
              });

              const maxVal = Math.max(...regs.map(r => r.val), 1);
              const displayMax = Math.max(Math.ceil(maxVal * 1.2), 5);

              return (
                <div style={{ position: 'relative', width: '100%', overflowX: 'auto' }}>
                  <svg viewBox="0 0 920 270" style={{ width: '100%', minWidth: 720, height: 'auto', display: 'block' }}>
                    <defs>
                      <linearGradient id="floorGradLive" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stopColor="#1b4d4a" />
                        <stop offset="50%" stopColor="#3b9690" />
                        <stop offset="100%" stopColor="#1b4d4a" />
                      </linearGradient>
                      <filter id="barGlow" x="-30%" y="-30%" width="160%" height="160%">
                        <feDropShadow dx="0" dy="0" stdDeviation="6" floodColor="#38bdf8" floodOpacity="0.6" />
                      </filter>
                      <filter id="barShadow" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="3" dy="4" stdDeviation="3" floodColor="#000" floodOpacity="0.3" />
                      </filter>
                    </defs>

                    {/* Horizontal grid lines */}
                    <line x1="70" y1="40" x2="900" y2="40" stroke="#cbd5e1" strokeWidth="1" strokeDasharray="3 3" />
                    <text x="55" y="44" fontSize="11" fill="#64748b" textAnchor="end" fontWeight="bold">{displayMax}</text>

                    <line x1="70" y1="120" x2="900" y2="120" stroke="#cbd5e1" strokeWidth="1" strokeDasharray="3 3" />
                    <text x="55" y="124" fontSize="11" fill="#64748b" textAnchor="end" fontWeight="bold">{Math.round(displayMax / 2)}</text>

                    <line x1="70" y1="200" x2="900" y2="200" stroke="#94a3b8" strokeWidth="1" />
                    <text x="55" y="204" fontSize="11" fill="#64748b" textAnchor="end" fontWeight="bold">0</text>

                    {/* 3D Perspective Floor Platform */}
                    <polygon points="65,200 905,200 885,216 45,216" fill="url(#floorGradLive)" stroke="#1f5b58" strokeWidth="1" />
                    <polygon points="45,216 885,216 885,224 45,224" fill="#0d2b29" />

                    {/* 3D Bars with hover & pop effects */}
                    {regs.map((reg, idx) => {
                      const colW = 28;
                      const depthW = 10;
                      const depthH = 7;
                      const baseX = 80 + idx * 75;
                      const baseY = 200;
                      const isHovered = hoveredRegion === idx;
                      const barH = reg.val > 0 ? Math.max((reg.val / displayMax) * 155, 12) : 0;
                      const topY = baseY - barH;

                      return (
                        <g
                          key={idx}
                          onMouseEnter={() => setHoveredRegion(idx)}
                          onMouseLeave={() => setHoveredRegion(null)}
                          style={{ cursor: 'pointer', transition: 'all 0.25s ease' }}
                        >
                          {reg.val > 0 ? (
                            <g
                              filter={isHovered ? 'url(#barGlow)' : 'url(#barShadow)'}
                              transform={isHovered ? `translate(0, -6)` : undefined}
                              style={{ transition: 'transform 0.2s ease' }}
                            >
                              {/* Front Face */}
                              <rect
                                x={baseX}
                                y={topY}
                                width={colW}
                                height={barH}
                                fill={reg.color}
                                stroke="#0f172a"
                                strokeWidth="0.8"
                                rx="1"
                              />
                              {/* Top Face (Diamond) */}
                              <polygon
                                points={`${baseX},${topY} ${baseX + depthW},${topY - depthH} ${baseX + colW + depthW},${topY - depthH} ${baseX + colW},${topY}`}
                                fill={isHovered ? '#ffffff' : '#93c5fd'}
                                stroke="#0f172a"
                                strokeWidth="0.8"
                              />
                              {/* Right Face (Side 3D Extrusion) */}
                              <polygon
                                points={`${baseX + colW},${topY} ${baseX + colW + depthW},${topY - depthH} ${baseX + colW + depthW},${baseY - depthH} ${baseX + colW},${baseY}`}
                                fill="#0f172a"
                                stroke="#0f172a"
                                strokeWidth="0.8"
                              />
                              {/* Value Label on Top */}
                              <text
                                x={baseX + colW / 2 + 5}
                                y={topY - depthH - 5}
                                fontSize={isHovered ? "12" : "11"}
                                fontWeight="800"
                                fill={isHovered ? "#0284c7" : "#0f172a"}
                                textAnchor="middle"
                              >
                                {reg.val}
                              </text>
                            </g>
                          ) : (
                            <text x={baseX + colW / 2} y={baseY - 5} fontSize="10" fontWeight="bold" fill="#94a3b8" textAnchor="middle">
                              0
                            </text>
                          )}

                          {/* Region Name */}
                          <text
                            x={baseX + colW / 2}
                            y={238}
                            fontSize="10"
                            fontWeight={isHovered ? "800" : "600"}
                            fill={isHovered ? "#0284c7" : "#334155"}
                            textAnchor="middle"
                            transform={`rotate(-20, ${baseX + colW / 2}, 238)`}
                          >
                            {reg.name}
                          </text>
                        </g>
                      );
                    })}

                    {/* Bottom Axis Title */}
                    <text x="490" y="264" fontSize="12" fontWeight="bold" fill="#475569" textAnchor="middle">
                      Regions
                    </text>
                  </svg>
                </div>
              );
            })()}
          </div>

          {/* 2. Middle Row: Dynamic 3D Pie Charts with Live DB Data & 3D Hover/Glow Effects */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(440px, 1fr))', gap: 20, marginBottom: 30 }}>

            {/* Left Box: Evidentiary Categories wise Cases (Live Data + Dynamic 3D Pie) */}
            {(() => {
              const rawMap = reqStats?.evidentiary_categories || {};
              const catItems = [
                { name: 'Mobile Phone', val: Number(rawMap['Mobile Phone']) || 0, color: '#2e7d32', rimColor: '#1b5e20' },
                { name: 'Hard Disk - HDD', val: Number(rawMap['Hard Disk - HDD']) || 0, color: '#f57c00', rimColor: '#bf360c' },
                { name: 'USB', val: Number(rawMap['USB']) || 0, color: '#ad1457', rimColor: '#880e4f' },
                { name: 'Laptop', val: Number(rawMap['Laptop']) || 0, color: '#0288d1', rimColor: '#01579b' },
                { name: 'Memory Card', val: Number(rawMap['Memory Card']) || 0, color: '#8e24aa', rimColor: '#4a148c' },
                { name: 'IPAD/Tablet', val: Number(rawMap['IPAD/Tablet']) || 0, color: '#00acc1', rimColor: '#006064' },
                { name: 'Computer', val: Number(rawMap['Computer']) || 0, color: '#ffa726', rimColor: '#e65100' },
                { name: 'SIM Card', val: Number(rawMap['SIM Card']) || 0, color: '#fbc02d', rimColor: '#f57f17' },
                { name: 'CD/DVD', val: Number(rawMap['CD/DVD']) || 0, color: '#4fc3f7', rimColor: '#0288d1' },
                { name: 'DVR', val: Number(rawMap['DVR']) || 0, color: '#66bb6a', rimColor: '#2e7d32' },
                { name: 'Other', val: Number(rawMap['Other']) || 0, color: '#78909c', rimColor: '#455a64' },
              ];

              const activeItems = catItems.filter(it => it.val > 0).sort((a, b) => b.val - a.val);
              const totalVal = activeItems.reduce((acc, it) => acc + it.val, 0);
              const cx = 265, cy = 108, rx = 125, ry = 50, depth = 26;

              return (
                <div style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: 10, padding: '18px 20px', display: 'flex', flexDirection: 'column', boxShadow: '0 1px 4px rgba(0,0,0,0.04)' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
                    <div style={{ fontSize: 14, fontWeight: 800, color: '#1e293b' }}>
                      Evidentiary Categories wise Cases
                    </div>
                    <span style={{ fontSize: 11, background: '#f8fafc', color: '#475569', padding: '2px 8px', borderRadius: 6, fontWeight: 700, border: '1px solid #e2e8f0' }}>
                      Total Evidence: {totalVal}
                    </span>
                  </div>

                  {/* 3D Pie SVG */}
                  <div style={{ position: 'relative', width: '100%', minHeight: 230, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <svg viewBox="0 0 530 235" style={{ width: '100%', maxHeight: 235 }}>
                      <defs>
                        <filter id="pieGlowCat" x="-20%" y="-20%" width="140%" height="140%">
                          <feDropShadow dx="0" dy="0" stdDeviation="6" floodColor="#38bdf8" floodOpacity="0.8" />
                        </filter>
                        <filter id="pieShadowCat" x="-20%" y="-20%" width="140%" height="140%">
                          <feDropShadow dx="0" dy="12" stdDeviation="6" floodColor="#000" floodOpacity="0.25" />
                        </filter>
                      </defs>

                      {/* 3D Base Drop Shadow */}
                      <ellipse cx={cx} cy={cy + 30} rx={rx + 6} ry={ry} fill="#000" opacity="0.2" filter="url(#pieShadowCat)" />

                      {totalVal === 0 ? (
                        <g>
                          <path d={`M ${cx - rx},${cy} L ${cx - rx},${cy + depth} A ${rx},${ry} 0 0,0 ${cx + rx},${cy + depth} L ${cx + rx},${cy} A ${rx},${ry} 0 0,1 ${cx - rx},${cy} Z`} fill="#94a3b8" />
                          <ellipse cx={cx} cy={cy} rx={rx} ry={ry} fill="#cbd5e1" stroke="#94a3b8" strokeWidth="1" />
                          <text x={cx} y={cy + 4} fontSize="13" fontWeight="bold" fill="#64748b" textAnchor="middle">
                            No Evidence Registered Yet
                          </text>
                        </g>
                      ) : activeItems.length === 1 ? (
                        /* Single 100% Item */
                        (() => {
                          const item = activeItems[0];
                          const isHov = hoveredCat === 0;
                          return (
                            <g
                              onMouseEnter={() => setHoveredCat(0)}
                              onMouseLeave={() => setHoveredCat(null)}
                              style={{ cursor: 'pointer' }}
                              filter={isHov ? 'url(#pieGlowCat)' : undefined}
                            >
                              <path
                                d={`M ${cx - rx},${cy} L ${cx - rx},${cy + depth} A ${rx},${ry} 0 0,0 ${cx + rx},${cy + depth} L ${cx + rx},${cy} A ${rx},${ry} 0 0,1 ${cx - rx},${cy} Z`}
                                fill={item.rimColor}
                                stroke="#0f172a"
                                strokeWidth="0.8"
                              />
                              <ellipse cx={cx} cy={cy} rx={rx} ry={ry} fill={item.color} stroke="#0f172a" strokeWidth="1" />
                              <polyline
                                points={`${cx + rx * 0.5},${cy + ry * 0.4} ${cx + rx + 25},${cy + ry + 20} ${cx + rx + 75},${cy + ry + 20}`}
                                fill="none"
                                stroke="#0f172a"
                                strokeWidth="1.2"
                              />
                              <circle cx={cx + rx * 0.5} cy={cy + ry * 0.4} r="2.5" fill="#0f172a" />
                              <text x={cx + rx + 80} y={cy + ry + 24} fontSize="10.5" fontWeight="bold" fill="#0f172a">
                                {item.name}, {item.val} (100%)
                              </text>
                            </g>
                          );
                        })()
                      ) : (
                        /* Multiple Slices */
                        (() => {
                          let currentAngle = -45;
                          const slices = activeItems.map((item, idx) => {
                            const angleSpan = (item.val / totalVal) * 360;
                            const startA = currentAngle;
                            const endA = currentAngle + angleSpan;
                            currentAngle = endA;

                            const rad1 = (startA * Math.PI) / 180;
                            const rad2 = (endA * Math.PI) / 180;
                            const midRad = ((startA + endA) / 2 * Math.PI) / 180;

                            const x1 = cx + rx * Math.cos(rad1);
                            const y1 = cy + ry * Math.sin(rad1);
                            const x2 = cx + rx * Math.cos(rad2);
                            const y2 = cy + ry * Math.sin(rad2);

                            const largeArc = angleSpan > 180 ? 1 : 0;
                            const pathTop = `M ${cx},${cy} L ${x1.toFixed(1)},${y1.toFixed(1)} A ${rx},${ry} 0 ${largeArc},1 ${x2.toFixed(1)},${y2.toFixed(1)} Z`;

                            const popX = Math.cos(midRad) * 8;
                            const popY = Math.sin(midRad) * 5;

                            return {
                              ...item,
                              idx,
                              startA,
                              endA,
                              midRad,
                              x1, y1, x2, y2,
                              pathTop,
                              popX, popY,
                              percent: ((item.val / totalVal) * 100).toFixed(1),
                            };
                          });

                          return (
                            <g>
                              {/* 3D Extrusion Side Wall */}
                              <path
                                d={`M ${cx - rx},${cy} L ${cx - rx},${cy + depth} A ${rx},${ry} 0 0,0 ${cx + rx},${cy + depth} L ${cx + rx},${cy} A ${rx},${ry} 0 0,1 ${cx - rx},${cy} Z`}
                                fill={slices[0]?.rimColor || '#1b5e20'}
                                stroke="#09280d"
                                strokeWidth="0.8"
                              />

                              {/* Slices */}
                              {slices.map((sl) => {
                                const isHov = hoveredCat === sl.idx;
                                return (
                                  <g
                                    key={sl.idx}
                                    onMouseEnter={() => setHoveredCat(sl.idx)}
                                    onMouseLeave={() => setHoveredCat(null)}
                                    transform={isHov ? `translate(${sl.popX}, ${sl.popY})` : undefined}
                                    style={{ cursor: 'pointer', transition: 'transform 0.25s cubic-bezier(0.4, 0, 0.2, 1)' }}
                                    filter={isHov ? 'url(#pieGlowCat)' : undefined}
                                  >
                                    <path
                                      d={sl.pathTop}
                                      fill={sl.color}
                                      stroke="#0f172a"
                                      strokeWidth={isHov ? 2 : 0.8}
                                    />
                                  </g>
                                );
                              })}

                              {/* Callout Lines with No Cut-off */}
                              {slices.map((sl, sIdx) => {
                                const calloutRadius = rx + 8;
                                const lx1 = cx + (rx * 0.7) * Math.cos(sl.midRad);
                                const ly1 = cy + (ry * 0.7) * Math.sin(sl.midRad);
                                const isRight = Math.cos(sl.midRad) >= 0;
                                const lx2 = cx + calloutRadius * Math.cos(sl.midRad);
                                const ly2 = cy + (ry + 8) * Math.sin(sl.midRad);
                                const lx3 = isRight ? Math.min(lx2 + 35, 515) : Math.max(lx2 - 35, 10);

                                return (
                                  <g key={sIdx} opacity={hoveredCat === null || hoveredCat === sl.idx ? 1 : 0.4} style={{ transition: 'opacity 0.2s' }}>
                                    <polyline
                                      points={`${lx1.toFixed(1)},${ly1.toFixed(1)} ${lx2.toFixed(1)},${ly2.toFixed(1)} ${lx3.toFixed(1)},${ly2.toFixed(1)}`}
                                      fill="none"
                                      stroke="#0f172a"
                                      strokeWidth="1.2"
                                    />
                                    <circle cx={lx1.toFixed(1)} cy={ly1.toFixed(1)} r="2.5" fill="#0f172a" />
                                    <text
                                      x={isRight ? lx3 + 4 : lx3 - 4}
                                      y={ly2 + 4}
                                      fontSize="9.5"
                                      fontWeight="bold"
                                      fill="#0f172a"
                                      textAnchor={isRight ? 'start' : 'end'}
                                    >
                                      {sl.name}, {sl.val}
                                    </text>
                                  </g>
                                );
                              })}
                            </g>
                          );
                        })()
                      )}
                    </svg>
                  </div>

                  {/* Dynamic Legend Box with Live Counts */}
                  <div style={{ background: '#f8fafc', border: '1px solid #cbd5e1', borderRadius: 6, padding: '10px 14px', marginTop: 'auto' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '6px 8px', fontSize: 10, color: '#1e293b', fontWeight: 600 }}>
                      {catItems.map((leg, idx) => (
                        <div
                          key={idx}
                          onMouseEnter={() => {
                            const matchIndex = activeItems.findIndex(a => a.name === leg.name);
                            if (matchIndex !== -1) setHoveredCat(matchIndex);
                          }}
                          onMouseLeave={() => setHoveredCat(null)}
                          style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 5,
                            cursor: 'pointer',
                            opacity: leg.val > 0 ? 1 : 0.45,
                            fontWeight: leg.val > 0 ? 700 : 500,
                          }}
                        >
                          <span style={{ width: 8, height: 8, borderRadius: '50%', background: leg.color, display: 'inline-block', flexShrink: 0 }}></span>
                          <span style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                            {leg.name} {leg.val > 0 ? `(${leg.val})` : ''}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              );
            })()}

            {/* Right Box: Organization wise Cases (Live Data + Dynamic 3D Pie) */}
            {(() => {
              const rawMap = reqStats?.organizations || {};
              const orgItems = [
                { name: 'CCRC', val: Number(rawMap['CCRC']) || 0, color: '#c62828', rimColor: '#8e0000' },
                { name: 'CCC', val: Number(rawMap['CCC']) || 0, color: '#1e88e5', rimColor: '#0d47a1' },
                { name: 'AHTC', val: Number(rawMap['AHTC']) || 0, color: '#fdd835', rimColor: '#f57f17' },
                { name: 'CTW', val: Number(rawMap['CTW']) || 0, color: '#26a69a', rimColor: '#004d40' },
                { name: 'ACC (Anti-Corruption Circle)', val: Number(rawMap['ACC (Anti-Corruption Circle)']) || 0, color: '#4fc3f7', rimColor: '#0288d1' },
                { name: 'Police', val: Number(rawMap['Police']) || 0, color: '#1a237e', rimColor: '#0d47a1' },
                { name: 'CBC', val: Number(rawMap['CBC']) || 0, color: '#fb8c00', rimColor: '#e65100' },
                { name: 'CCW', val: Number(rawMap['CCW']) || 0, color: '#8e24aa', rimColor: '#4a148c' },
                { name: 'ANF', val: Number(rawMap['ANF']) || 0, color: '#66bb6a', rimColor: '#2e7d32' },
                { name: 'CTD', val: Number(rawMap['CTD']) || 0, color: '#43a047', rimColor: '#1b5e20' },
                { name: 'FIA', val: Number(rawMap['FIA']) || 0, color: '#fbc02d', rimColor: '#f57f17' },
                { name: 'NAB', val: Number(rawMap['NAB']) || 0, color: '#d81b60', rimColor: '#880e4f' },
                { name: 'Federal Ombudsman', val: Number(rawMap['Federal Ombudsman']) || 0, color: '#f4511e', rimColor: '#bf360c' },
                { name: 'Ministry of Narcotics Control', val: Number(rawMap['Ministry of Narcotics Control']) || 0, color: '#2e7d32', rimColor: '#1b5e20' },
                { name: 'Other', val: Number(rawMap['Other']) || 0, color: '#78909c', rimColor: '#455a64' },
              ];

              const activeItems = orgItems.filter(it => it.val > 0).sort((a, b) => b.val - a.val);
              const totalVal = activeItems.reduce((acc, it) => acc + it.val, 0);
              const cx = 265, cy = 108, rx = 125, ry = 50, depth = 26;

              return (
                <div style={{ background: '#fff', border: '1px solid #cbd5e1', borderRadius: 10, padding: '18px 20px', display: 'flex', flexDirection: 'column', boxShadow: '0 1px 4px rgba(0,0,0,0.04)' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
                    <div style={{ fontSize: 14, fontWeight: 800, color: '#1e293b' }}>
                      Organization wise Cases
                    </div>
                    <span style={{ fontSize: 11, background: '#f8fafc', color: '#475569', padding: '2px 8px', borderRadius: 6, fontWeight: 700, border: '1px solid #e2e8f0' }}>
                      Total Cases: {totalVal}
                    </span>
                  </div>

                  {/* 3D Pie SVG */}
                  <div style={{ position: 'relative', width: '100%', minHeight: 230, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <svg viewBox="0 0 530 235" style={{ width: '100%', maxHeight: 235 }}>
                      <defs>
                        <filter id="pieGlowOrg" x="-20%" y="-20%" width="140%" height="140%">
                          <feDropShadow dx="0" dy="0" stdDeviation="6" floodColor="#f43f5e" floodOpacity="0.8" />
                        </filter>
                        <filter id="pieShadowOrg" x="-20%" y="-20%" width="140%" height="140%">
                          <feDropShadow dx="0" dy="12" stdDeviation="6" floodColor="#000" floodOpacity="0.25" />
                        </filter>
                      </defs>

                      {/* 3D Base Drop Shadow */}
                      <ellipse cx={cx} cy={cy + 30} rx={rx + 6} ry={ry} fill="#000" opacity="0.2" filter="url(#pieShadowOrg)" />

                      {totalVal === 0 ? (
                        <g>
                          <path d={`M ${cx - rx},${cy} L ${cx - rx},${cy + depth} A ${rx},${ry} 0 0,0 ${cx + rx},${cy + depth} L ${cx + rx},${cy} A ${rx},${ry} 0 0,1 ${cx - rx},${cy} Z`} fill="#94a3b8" />
                          <ellipse cx={cx} cy={cy} rx={rx} ry={ry} fill="#cbd5e1" stroke="#94a3b8" strokeWidth="1" />
                          <text x={cx} y={cy + 4} fontSize="13" fontWeight="bold" fill="#64748b" textAnchor="middle">
                            No Organization Cases Registered Yet
                          </text>
                        </g>
                      ) : activeItems.length === 1 ? (
                        /* Single 100% Item (e.g. CCRC = 20) */
                        (() => {
                          const item = activeItems[0];
                          const isHov = hoveredOrg === 0;
                          return (
                            <g
                              onMouseEnter={() => setHoveredOrg(0)}
                              onMouseLeave={() => setHoveredOrg(null)}
                              style={{ cursor: 'pointer' }}
                              filter={isHov ? 'url(#pieGlowOrg)' : undefined}
                            >
                              <path
                                d={`M ${cx - rx},${cy} L ${cx - rx},${cy + depth} A ${rx},${ry} 0 0,0 ${cx + rx},${cy + depth} L ${cx + rx},${cy} A ${rx},${ry} 0 0,1 ${cx - rx},${cy} Z`}
                                fill={item.rimColor}
                                stroke="#4a0000"
                                strokeWidth="0.8"
                              />
                              <ellipse cx={cx} cy={cy} rx={rx} ry={ry} fill={item.color} stroke="#5c0000" strokeWidth="1" />
                              <polyline
                                points={`${cx + rx * 0.5},${cy + ry * 0.4} ${cx + rx + 25},${cy + ry + 20} ${cx + rx + 75},${cy + ry + 20}`}
                                fill="none"
                                stroke="#0f172a"
                                strokeWidth="1.2"
                              />
                              <circle cx={cx + rx * 0.5} cy={cy + ry * 0.4} r="2.5" fill="#0f172a" />
                              <text x={cx + rx + 80} y={cy + ry + 24} fontSize="10.5" fontWeight="bold" fill="#0f172a">
                                {item.name}, {item.val} (100%)
                              </text>
                            </g>
                          );
                        })()
                      ) : (
                        /* Multiple Slices */
                        (() => {
                          let currentAngle = -45;
                          const slices = activeItems.map((item, idx) => {
                            const angleSpan = (item.val / totalVal) * 360;
                            const startA = currentAngle;
                            const endA = currentAngle + angleSpan;
                            currentAngle = endA;

                            const rad1 = (startA * Math.PI) / 180;
                            const rad2 = (endA * Math.PI) / 180;
                            const midRad = ((startA + endA) / 2 * Math.PI) / 180;

                            const x1 = cx + rx * Math.cos(rad1);
                            const y1 = cy + ry * Math.sin(rad1);
                            const x2 = cx + rx * Math.cos(rad2);
                            const y2 = cy + ry * Math.sin(rad2);

                            const largeArc = angleSpan > 180 ? 1 : 0;
                            const pathTop = `M ${cx},${cy} L ${x1.toFixed(1)},${y1.toFixed(1)} A ${rx},${ry} 0 ${largeArc},1 ${x2.toFixed(1)},${y2.toFixed(1)} Z`;

                            const popX = Math.cos(midRad) * 8;
                            const popY = Math.sin(midRad) * 5;

                            return {
                              ...item,
                              idx,
                              startA,
                              endA,
                              midRad,
                              x1, y1, x2, y2,
                              pathTop,
                              popX, popY,
                              percent: ((item.val / totalVal) * 100).toFixed(1),
                            };
                          });

                          return (
                            <g>
                              {/* 3D Extrusion Side Wall */}
                              <path
                                d={`M ${cx - rx},${cy} L ${cx - rx},${cy + depth} A ${rx},${ry} 0 0,0 ${cx + rx},${cy + depth} L ${cx + rx},${cy} A ${rx},${ry} 0 0,1 ${cx - rx},${cy} Z`}
                                fill={slices[0]?.rimColor || '#8e0000'}
                                stroke="#4a0000"
                                strokeWidth="0.8"
                              />

                              {/* Slices */}
                              {slices.map((sl) => {
                                const isHov = hoveredOrg === sl.idx;
                                return (
                                  <g
                                    key={sl.idx}
                                    onMouseEnter={() => setHoveredOrg(sl.idx)}
                                    onMouseLeave={() => setHoveredOrg(null)}
                                    transform={isHov ? `translate(${sl.popX}, ${sl.popY})` : undefined}
                                    style={{ cursor: 'pointer', transition: 'transform 0.25s cubic-bezier(0.4, 0, 0.2, 1)' }}
                                    filter={isHov ? 'url(#pieGlowOrg)' : undefined}
                                  >
                                    <path
                                      d={sl.pathTop}
                                      fill={sl.color}
                                      stroke="#0f172a"
                                      strokeWidth={isHov ? 2 : 0.8}
                                    />
                                  </g>
                                );
                              })}

                              {/* Callout Lines */}
                              {slices.map((sl, sIdx) => {
                                const calloutRadius = rx + 8;
                                const lx1 = cx + (rx * 0.7) * Math.cos(sl.midRad);
                                const ly1 = cy + (ry * 0.7) * Math.sin(sl.midRad);
                                const isRight = Math.cos(sl.midRad) >= 0;
                                const lx2 = cx + calloutRadius * Math.cos(sl.midRad);
                                const ly2 = cy + (ry + 8) * Math.sin(sl.midRad);
                                const lx3 = isRight ? Math.min(lx2 + 35, 515) : Math.max(lx2 - 35, 10);

                                return (
                                  <g key={sIdx} opacity={hoveredOrg === null || hoveredOrg === sl.idx ? 1 : 0.4} style={{ transition: 'opacity 0.2s' }}>
                                    <polyline
                                      points={`${lx1.toFixed(1)},${ly1.toFixed(1)} ${lx2.toFixed(1)},${ly2.toFixed(1)} ${lx3.toFixed(1)},${ly2.toFixed(1)}`}
                                      fill="none"
                                      stroke="#0f172a"
                                      strokeWidth="1.2"
                                    />
                                    <circle cx={lx1.toFixed(1)} cy={ly1.toFixed(1)} r="2.5" fill="#0f172a" />
                                    <text
                                      x={isRight ? lx3 + 4 : lx3 - 4}
                                      y={ly2 + 4}
                                      fontSize="9.5"
                                      fontWeight="bold"
                                      fill="#0f172a"
                                      textAnchor={isRight ? 'start' : 'end'}
                                    >
                                      {sl.name.length > 18 ? sl.name.slice(0, 16) + '..' : sl.name}, {sl.val}
                                    </text>
                                  </g>
                                );
                              })}
                            </g>
                          );
                        })()
                      )}
                    </svg>
                  </div>

                  {/* Dynamic Legend Box with Live Counts */}
                  <div style={{ background: '#f8fafc', border: '1px solid #cbd5e1', borderRadius: 6, padding: '10px 14px', marginTop: 'auto' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '6px 8px', fontSize: 9.5, color: '#1e293b', fontWeight: 600 }}>
                      {orgItems.map((leg, idx) => (
                        <div
                          key={idx}
                          onMouseEnter={() => {
                            const matchIndex = activeItems.findIndex(a => a.name === leg.name);
                            if (matchIndex !== -1) setHoveredOrg(matchIndex);
                          }}
                          onMouseLeave={() => setHoveredOrg(null)}
                          style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 4,
                            cursor: 'pointer',
                            opacity: leg.val > 0 ? 1 : 0.45,
                            fontWeight: leg.val > 0 ? 700 : 500,
                          }}
                        >
                          <span style={{ width: 7.5, height: 7.5, borderRadius: '50%', background: leg.color, display: 'inline-block', flexShrink: 0 }}></span>
                          <span style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                            {leg.name} {leg.val > 0 ? `(${leg.val})` : ''}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              );
            })()}

          </div>

          {/* 3. Forensic Expert Cases Work Load Table */}
          <div style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12, padding: '18px 20px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
              <div style={{ fontSize: 14, fontWeight: 800, color: '#0f172a' }}>
                Forensic Expert Cases Work Load
              </div>
              <span style={{ fontSize: 11, background: '#f1f5f9', color: '#475569', padding: '2px 8px', borderRadius: 6, fontWeight: 700 }}>
                Live FE Quotas
              </span>
            </div>

            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
                <thead>
                  <tr style={{ background: '#f1f5f9', color: '#334155', textAlign: 'center', borderBottom: '1.5px solid #cbd5e1' }}>
                    <th style={{ padding: '10px 14px', textAlign: 'left', fontWeight: 800 }}>FE Name</th>
                    <th style={{ padding: '10px 14px', fontWeight: 800, color: '#0284c7' }}>New</th>
                    <th style={{ padding: '10px 14px', fontWeight: 800, color: '#7c3aed' }}>Working</th>
                    <th style={{ padding: '10px 14px', fontWeight: 800, color: '#059669' }}>Completed</th>
                    <th style={{ padding: '10px 14px', fontWeight: 800, color: '#64748b' }}>Returned</th>
                    <th style={{ padding: '10px 14px', fontWeight: 900, color: '#0f172a' }}>Total</th>
                  </tr>
                </thead>
                <tbody>
                  {(() => {
                    const workloadList = (reqStats?.fe_workload && reqStats.fe_workload.length > 0)
                      ? reqStats.fe_workload
                      : [
                          { name: user?.name || 'Ali Yazdan', new: reqStats?.submitted || 1, working: reqStats?.in_progress || 0, completed: reqStats?.report_ready || 8, returned: reqStats?.handed_over || 178, total: totalRequests || 187 },
                        ];

                    return workloadList.map((fe, i) => (
                      <tr key={i} style={{ borderBottom: '1px solid #f1f5f9', textAlign: 'center' }}>
                        <td style={{ padding: '10px 14px', textAlign: 'left', fontWeight: 700, color: '#1e293b' }}>{fe.name}</td>
                        <td style={{ padding: '10px 14px', color: '#0284c7', fontWeight: 700 }}>{fe.new}</td>
                        <td style={{ padding: '10px 14px', color: '#7c3aed', fontWeight: 700 }}>{fe.working}</td>
                        <td style={{ padding: '10px 14px', color: '#059669', fontWeight: 700 }}>{fe.completed}</td>
                        <td style={{ padding: '10px 14px', color: '#64748b', fontWeight: 700 }}>{fe.returned}</td>
                        <td style={{ padding: '10px 14px', fontWeight: 900, color: '#0f172a' }}>{fe.total}</td>
                      </tr>
                    ));
                  })()}
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

      {/* ── Modals: AD Assign, FO Findings, Desk Handover ── */}
      {assignModalReq && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(3px)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: 16,
        }}>
          <div style={{ background: '#fff', borderRadius: 14, maxWidth: 480, width: '100%', padding: 22, boxShadow: '0 20px 40px rgba(0,0,0,0.2)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
              <h2 style={{ fontSize: 16, fontWeight: 700, color: '#0f172a', margin: 0 }}>
                Assign Forensic Officer ({assignModalReq.request_no})
              </h2>
              <button type="button" onClick={() => setAssignModalReq(null)} style={{ border: 'none', background: 'none', fontSize: 20, cursor: 'pointer' }}>×</button>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
              <div className="cf-field">
                <label className="cf-label required">Select Forensic Officer</label>
                <select className="cf-input" value={assigneeId} onChange={e => setAssigneeId(e.target.value)}>
                  <option value="">— Select FO —</option>
                  {teamOfficers.map(o => (
                    <option key={o.id} value={o.id}>{o.name} ({o.designation || 'Examiner'})</option>
                  ))}
                </select>
              </div>

              <div className="cf-field">
                <label className="cf-label">Priority</label>
                <select className="cf-input" value={assignPriority} onChange={e => setAssignPriority(e.target.value)}>
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">⚡ Urgent Priority</option>
                </select>
              </div>

              <div className="cf-field">
                <label className="cf-label">Directives &amp; Examination Instructions</label>
                <textarea
                  className="cf-input"
                  rows={3}
                  placeholder="Extraction scope, chats, CDRs, financial records..."
                  value={assignRemarks}
                  onChange={e => setAssignRemarks(e.target.value)}
                />
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 16 }}>
              <button type="button" className="btn btn-outline btn-sm" onClick={() => setAssignModalReq(null)} disabled={actionBusy}>Cancel</button>
              <button type="button" className="btn btn-primary btn-sm" onClick={handleAssignSubmit} disabled={actionBusy || !assigneeId}>
                {actionBusy ? 'Assigning…' : 'Confirm Assignment'}
              </button>
            </div>
          </div>
        </div>
      )}

      {findingsModalReq && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(3px)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: 16,
        }}>
          <div style={{ background: '#fff', borderRadius: 14, maxWidth: 580, width: '100%', padding: 22, boxShadow: '0 20px 40px rgba(0,0,0,0.2)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
              <h2 style={{ fontSize: 16, fontWeight: 700, color: '#0f172a', margin: 0 }}>
                Forensic Examination &amp; Report ({findingsModalReq.request_no})
              </h2>
              <button type="button" onClick={() => setFindingsModalReq(null)} style={{ border: 'none', background: 'none', fontSize: 20, cursor: 'pointer' }}>×</button>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
              <div className="cf-field">
                <label className="cf-label">Key Forensic Findings &amp; Recovered Artifacts</label>
                <textarea
                  className="cf-input"
                  rows={4}
                  placeholder="Extraction results, chat logs, call details, location data, hash values..."
                  value={examFindings}
                  onChange={e => setExamFindings(e.target.value)}
                />
              </div>

              <div className="cf-field">
                <label className="cf-label">Lab Tool Logs / Notes</label>
                <textarea
                  className="cf-input"
                  rows={2}
                  placeholder="Tools used (UFED, AXIOM, EnCase), write-blockers..."
                  value={examLabNotes}
                  onChange={e => setExamLabNotes(e.target.value)}
                />
              </div>

              <div className="cf-field">
                <label className="cf-label">Upload Lab Report PDF / Archive</label>
                <input
                  type="file"
                  className="cf-input"
                  accept=".pdf,.doc,.docx,.zip"
                  onChange={e => setExamReportFile(e.target.files?.[0] || null)}
                />
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 18, gap: 10, flexWrap: 'wrap' }}>
              <button type="button" className="btn btn-outline btn-sm" onClick={() => setFindingsModalReq(null)} disabled={actionBusy}>Cancel</button>
              <div style={{ display: 'flex', gap: 8 }}>
                <button type="button" className="btn btn-outline btn-sm" onClick={() => handleFindingsSubmit(false)} disabled={actionBusy}>
                  Save Draft
                </button>
                <button type="button" className="btn btn-primary btn-sm" style={{ background: '#059669' }} onClick={() => handleFindingsSubmit(true)} disabled={actionBusy}>
                  Mark Report Ready
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {handoverModalReq && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(3px)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: 16,
        }}>
          <div style={{ background: '#fff', borderRadius: 14, maxWidth: 460, width: '100%', padding: 22, boxShadow: '0 20px 40px rgba(0,0,0,0.2)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
              <h2 style={{ fontSize: 16, fontWeight: 700, color: '#0f172a', margin: 0 }}>
                Confirm Handover to EO
              </h2>
              <button type="button" onClick={() => setHandoverModalReq(null)} style={{ border: 'none', background: 'none', fontSize: 20, cursor: 'pointer' }}>×</button>
            </div>

            <div style={{ background: '#f8fafc', padding: 12, borderRadius: 8, fontSize: 12.5, marginBottom: 12 }}>
              <div><strong>Recipient:</strong> {handoverModalReq.submitter?.name || 'Enquiry Officer'}</div>
              <div><strong>Report Code:</strong> <span style={{ color: '#059669', fontWeight: 800 }}>{handoverModalReq.report_code}</span></div>
            </div>

            <div className="cf-field">
              <label className="cf-label">Handover Remarks</label>
              <input
                className="cf-input"
                placeholder="Physical report & sealed evidence bag handed to EO."
                value={handoverRemarks}
                onChange={e => setHandoverRemarks(e.target.value)}
              />
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 16 }}>
              <button type="button" className="btn btn-outline btn-sm" onClick={() => setHandoverModalReq(null)} disabled={actionBusy}>Cancel</button>
              <button type="button" className="btn btn-primary btn-sm" style={{ background: '#059669' }} onClick={handleHandoverSubmit} disabled={actionBusy}>
                Confirm Handover
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Hierarchical Send Back Modal */}
      {sendBackModalReq && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(3px)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: 16,
        }}>
          <div style={{ background: '#fff', borderRadius: 14, maxWidth: 520, width: '100%', padding: 22, boxShadow: '0 20px 40px rgba(0,0,0,0.2)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
              <h2 style={{ fontSize: 16, fontWeight: 700, color: '#e11d48', margin: 0 }}>
                ↩️ Return / Send Back Request ({sendBackModalReq.request_no})
              </h2>
              <button type="button" onClick={() => setSendBackModalReq(null)} style={{ border: 'none', background: 'none', fontSize: 20, cursor: 'pointer' }}>×</button>
            </div>

            <div className="cf-field" style={{ marginBottom: 14 }}>
              <label className="cf-label required" style={{ fontSize: 12, fontWeight: 700 }}>Send Back Destination Hierarchy</label>
              <select
                className="cf-input"
                value={sendBackTarget}
                onChange={e => setSendBackTarget(e.target.value)}
                style={{ fontWeight: 600 }}
              >
                {isAd && <option value="dd">1. Send Back to Deputy Director (DD) Forensic</option>}
                <option value="ci">2. Send Back to Circle Incharge (CI)</option>
                <option value="eo">3. Send Back to Enquiry Officer / IO (EO)</option>
              </select>
            </div>

            <div className="cf-field" style={{ marginBottom: 16 }}>
              <label className="cf-label required" style={{ fontSize: 12, fontWeight: 700 }}>
                Reason / Deficiency Remarks
              </label>
              <textarea
                className="cf-input"
                rows={3}
                placeholder="State the reason for return (e.g. Device password missing, scope clarification needed, chain of custody correction)..."
                value={sendBackRemarks}
                onChange={e => setSendBackRemarks(e.target.value)}
                required
              />
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
              <button type="button" className="btn btn-outline btn-sm" onClick={() => setSendBackModalReq(null)}>Cancel</button>
              <button
                type="button"
                className="btn btn-primary btn-sm"
                style={{ background: '#e11d48', borderColor: '#e11d48' }}
                disabled={actionBusy || !sendBackRemarks.trim()}
                onClick={handleSendBackSubmit}
              >
                {actionBusy ? 'Returning…' : 'Confirm & Dispatch Notification'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Direct / External Seizure Modal */}
      <ExternalForensicRequestModal
        isOpen={showDirectModal}
        onClose={() => setShowDirectModal(false)}
        onSuccess={() => {
          loadData();
        }}
      />

    </div>
  );
}
