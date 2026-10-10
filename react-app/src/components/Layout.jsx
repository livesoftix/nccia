import { useState, useEffect, useRef } from 'react';
import { Outlet, NavLink, useNavigate, useLocation } from 'react-router-dom';
import PageErrorBoundary from './PageErrorBoundary';
import { useAuth } from '../contexts/AuthContext';
import api from '../api';
import { canView, hasRole, canCreateComplaint, canSeeDirectVerification, canSeeDirectEnquiry, canSeeDirectFir, canViewApprovalSettings, canUseWarrantRequests, canUseProclamations } from '../utils/permissions';

function getBreadcrumb(pathname) {
  const map = {
    '/': 'Dashboard',
    '/department-progress': 'Department Progress',
    '/complaints': 'Complaints',
    '/verifications': 'Verifications',
    '/verifications/create': 'New Verification',
    '/verifications/reports': 'Verification Reports',
    '/enquiries': 'Enquiries',
    '/enquiries/create': 'New Enquiry',
    '/messages': 'Messages',
    '/investigation-officers': 'IO Records',
    '/offence-types': 'Crime Categories',
    '/cases': 'DAC Cases',
    '/cases/create': 'New Case / FIR',
    '/users': 'Users',
    '/circles': 'Circles',
  };
  return map[pathname] || 'Dashboard';
}

function timeAgo(iso) {
  if (!iso) return '';
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'Just now';
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  return `${Math.floor(hrs / 24)}d ago`;
}

export default function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const isOperatorOnly = hasRole(user, 'operator')
    && !hasRole(user, 'admin')
    && !hasRole(user, 'circle_incharge')
    && !hasRole(user, 'director_general');
  const circleCode = user?.circle?.code || user?.circle_code;
  const circleName = user?.circle?.name || user?.circle_name;
  const isZonalHead = user?.is_zonal_head || user?.role === 'director_punjab' || user?.role === 'zonal_director' || (user?.designation && user.designation.toLowerCase().includes('director punjab'));
  const isHqUser = !circleCode && !isZonalHead && (user?.role === 'admin' || user?.role === 'director_general' || user?.role === 'additional_director' || user?.role === 'dd_legal' || user?.role === 'ad_legal');
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [notifOpen, setNotifOpen] = useState(false);
  const [analyticsOpen, setAnalyticsOpen] = useState(false);
  const [dacOpen, setDacOpen] = useState(false);
  const [vipOpen, setVipOpen] = useState(false);
  const [counts, setCounts] = useState({ verifications: 0, reports: 0, enquiries: 0, messages: 0 });
  const [notifications, setNotifications] = useState({ unread_count: 0, notifications: [] });
  const [pendingTasks, setPendingTasks] = useState({ tasks: [], count: 0 });
  const [toast, setToast] = useState(null);
  const [appUpdateReady, setAppUpdateReady] = useState(() => !!sessionStorage.getItem('nccia_pending_update'));
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState([]);
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchLoading, setSearchLoading] = useState(false);
  const prevNotifIds = useRef([]);
  const toastTimer = useRef(null);
  const userMenuRef = useRef(null);
  const notifRef = useRef(null);
  const searchRef = useRef(null);
  const searchInputRef = useRef(null);
  const searchTimer = useRef(null);

  useEffect(() => {
    const fetchCounts = () => api.get('/sidebar-counts').then(r => {
      const d = r.data || {};
      setCounts({
        verifications: Number(d.verifications) || 0,
        reports: Number(d.reports) || 0,
        enquiries: Number(d.enquiries) || 0,
        messages: Number(d.messages) || 0,
      });
      if (d.security_alert) {
        playNotificationSound();
        showToast(d.security_alert);
        alert(d.security_alert);
      }
    }).catch(() => {});
    fetchCounts();
    const timer = setInterval(fetchCounts, 45000);
    return () => clearInterval(timer);
  }, [location.pathname]);

  const playNotificationSound = () => {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, ctx.currentTime);
      osc.frequency.setValueAtTime(880, ctx.currentTime + 0.08);
      gain.gain.setValueAtTime(0.12, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.3);
    } catch {
      // Ignored if sound blocked by browser policy
    }
  };

  const fetchNotifications = () => {
    api.get('/notifications').then(r => {
      const data = r.data || { unread_count: 0, notifications: [] };
      const unread = (data.notifications || []).filter(n => !n.read_at);
      const unreadIds = new Set(unread.map(n => n.id));
      const knownIds = new Set(prevNotifIds.current);

      setNotifications(data);

      // Only toast genuinely NEW unread notifications (skip the very first load)
      if (prevNotifIds.current.length > 0) {
        const brandNew = unread.filter(n => !knownIds.has(n.id));
        if (brandNew.length > 0) {
          const n = brandNew[0];
          playNotificationSound();
          showToast(n.data?.message || n.type || 'New notification', n.data?.url);
        }
      }
      prevNotifIds.current = Array.from(unreadIds);
    }).catch(() => {});
    api.get('/notifications/pending-tasks').then(r => setPendingTasks(r.data || { tasks: [], count: 0 })).catch(() => {});
  };

  const showToast = (message, url) => {
    setToast({ message, url, id: Date.now() });
    if (toastTimer.current) clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast(null), 5000);
  };

  useEffect(() => {
    fetchNotifications();
    const timer = setInterval(fetchNotifications, 15000);
    const onRefresh = () => fetchNotifications();
    window.addEventListener('nccia:refresh-notifications', onRefresh);
    return () => {
      clearInterval(timer);
      window.removeEventListener('nccia:refresh-notifications', onRefresh);
    };
  }, []);

  useEffect(() => {
    const checkAlerts = () => {
      api.get('/security-alerts').then(r => {
        if (r.data && r.data.alert) {
          playNotificationSound();
          showToast(r.data.alert);
          alert(r.data.alert);
        }
      }).catch(() => {});
    };
    const timer = setInterval(checkAlerts, 2500); // Check every 2.5s for instant feel
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    const onUpdate = () => setAppUpdateReady(true);
    window.addEventListener('nccia:app-update', onUpdate);
    if (sessionStorage.getItem('nccia_pending_update')) setAppUpdateReady(true);
    return () => window.removeEventListener('nccia:app-update', onUpdate);
  }, []);

  const applyAppUpdate = () => {
    const next = sessionStorage.getItem('nccia_pending_update');
    if (next) localStorage.setItem('nccia_app_v', next);
    sessionStorage.removeItem('nccia_pending_update');
    window.location.reload();
  };

  const markNotificationRead = (n) => {
    if (n.read_at) return;
    api.post(`/notifications/${n.id}/read`).then(() => {
      setNotifications(prev => ({
        ...prev,
        unread_count: Math.max(0, prev.unread_count - 1),
        notifications: prev.notifications.map(x => x.id === n.id ? { ...x, read_at: new Date().toISOString() } : x),
      }));
    }).catch(() => {});
  };

  const markAllNotificationsRead = () => {
    api.post('/notifications/read-all').then(() => {
      setNotifications(prev => ({
        ...prev,
        unread_count: 0,
        notifications: prev.notifications.map(x => ({ ...x, read_at: new Date().toISOString() })),
      }));
      prevNotifIds.current = [];
    }).catch(() => {});
  };

  const openNotification = (url) => {
    setNotifOpen(false);
    if (url && url.startsWith('/')) navigate(url);
  };

  const toggleSidebar = () => {
    if (window.innerWidth <= 768) {
      setMobileOpen(!mobileOpen);
    } else {
      setCollapsed(!collapsed);
    }
  };
  const closeMobile = () => setMobileOpen(false);
  const go = (path) => { setUserMenuOpen(false); navigate(path); };

  useEffect(() => {
    setMobileOpen(false);
    setUserMenuOpen(false);
    setNotifOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    document.body.classList.toggle('sidebar-collapsed', collapsed);
  }, [collapsed]);

  useEffect(() => {
    const isDirect = location.search.includes('direct=1') ||
      (location.pathname.includes('/create') && location.search.includes('direct=1'));
    if (isDirect) {
      setVipOpen(true);
      setDacOpen(false);
    } else if (location.pathname.startsWith('/cases') || location.pathname.startsWith('/court-cases')) {
      setDacOpen(true);
      setVipOpen(false);
    }
  }, [location.pathname, location.search]);

  useEffect(() => {
    const handler = (e) => {
      if (userMenuRef.current && !userMenuRef.current.contains(e.target)) {
        setUserMenuOpen(false);
      }
      if (notifRef.current && !notifRef.current.contains(e.target)) {
        setNotifOpen(false);
      }
      if (searchRef.current && !searchRef.current.contains(e.target)) {
        setSearchOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  useEffect(() => {
    const onKey = (e) => {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        searchInputRef.current?.focus();
        setSearchOpen(true);
      }
      if (e.key === 'Escape') setSearchOpen(false);
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, []);

  useEffect(() => {
    if (searchTimer.current) clearTimeout(searchTimer.current);
    const q = searchQuery.trim();
    if (!q) {
      setSearchResults([]);
      setSearchLoading(false);
      return;
    }
    setSearchLoading(true);
    searchTimer.current = setTimeout(() => {
      api.get('/search', { params: { q } })
        .then(r => {
          setSearchResults(r.data.data || []);
          setSearchOpen(true);
        })
        .catch(() => setSearchResults([]))
        .finally(() => setSearchLoading(false));
    }, 250);
    return () => { if (searchTimer.current) clearTimeout(searchTimer.current); };
  }, [searchQuery]);

  const openSearchResult = (item) => {
    setSearchOpen(false);
    setSearchQuery('');
    setSearchResults([]);
    if (item?.process_url) navigate(item.process_url);
  };

  // Complaint quick actions from global top-nav search (Open/Close/Merge/Transfer/Proceed)
  const [layoutComplaintAction, setLayoutComplaintAction] = useState(null);
  const [layoutActionForm, setLayoutActionForm] = useState({ closure_reason: '', merge_complaint_id: '', transfer_department: '', transfer_circle_id: '' });
  const [layoutActionCircles, setLayoutActionCircles] = useState([]);
  const [layoutActionSaving, setLayoutActionSaving] = useState(false);

  const CLOSURE_REASONS = [
    { value: 'non_pursuance', label: 'Non-Pursuance by Complainant' },
    { value: 'irrelevant', label: 'Irrelevant' },
    { value: 'invalid', label: 'Invalid' },
    { value: 'lack_of_evidence', label: 'Lack of Evidence' },
  ];

  const openLayoutAction = (item, action) => {
    if (action === 'proceed') {
      setSearchOpen(false);
      setSearchQuery('');
      setSearchResults([]);
      navigate(`/verifications/reports/create?tracking=${encodeURIComponent(item.tracking_no)}`);
      return;
    }
    api.get('/lookup/circles').then(r => setLayoutActionCircles(r.data || [])).catch(() => {});
    setLayoutComplaintAction({ complaint: item, action });
    setLayoutActionForm({ closure_reason: '', merge_complaint_id: '', transfer_department: '', transfer_circle_id: '' });
  };

  const handleLayoutAction = async () => {
    if (!layoutComplaintAction) return;
    const { complaint, action } = layoutComplaintAction;
    if (action === 'closure' && !layoutActionForm.closure_reason) {
      alert('Closure reason select karein.');
      return;
    }
    if (action === 'merge' && !layoutActionForm.merge_complaint_id) {
      alert('Merge wali complaint ID likhein.');
      return;
    }
    if (action === 'transfer' && !layoutActionForm.transfer_department) {
      alert('Transfer department likhein.');
      return;
    }
    setLayoutActionSaving(true);
    try {
      await api.post('/complaints/bulk-action', {
        ids: [complaint.id],
        action,
        closure_reason: layoutActionForm.closure_reason || null,
        merge_complaint_id: layoutActionForm.merge_complaint_id ? Number(layoutActionForm.merge_complaint_id) : null,
        transfer_department: layoutActionForm.transfer_department || null,
        transfer_circle_id: layoutActionForm.transfer_circle_id ? Number(layoutActionForm.transfer_circle_id) : null,
      });
      setLayoutComplaintAction(null);
      setSearchOpen(false);
      setSearchQuery('');
      setSearchResults([]);
      alert('Complaint #' + (complaint.tracking_no || complaint.id) + ' ' + (action === 'closure' ? 'closed' : action === 'merge' ? 'merged' : 'transferred') + ' ho gayi.');
    } catch (e) {
      alert(e.response?.data?.message || 'Action failed');
    } finally {
      setLayoutActionSaving(false);
    }
  };

  const canBulkActions = () => hasRole(user, 'admin') || hasRole(user, 'circle_incharge');

  const initials = user?.name
    ? user.name.split(' ').map(w => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase()
    : 'U';

  const breadcrumb = getBreadcrumb(location.pathname);

  return (
    <div className="app-wrapper">
      {appUpdateReady && (
        <div style={{
          position: 'sticky', top: 0, zIndex: 9999, background: '#015C94', color: '#fff',
          padding: '10px 16px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 12, flexWrap: 'wrap',
          fontSize: 13, fontWeight: 600,
        }}>
          <span>New app version available — your form work is safe. Reload when ready.</span>
          <button type="button" onClick={applyAppUpdate} style={{
            background: '#fff', color: '#015C94', border: 'none', borderRadius: 6, padding: '6px 12px', fontWeight: 700, cursor: 'pointer',
          }}>
            Reload now
          </button>
          <button type="button" onClick={() => setAppUpdateReady(false)} style={{
            background: 'transparent', color: '#fff', border: '1px solid rgba(255,255,255,0.5)', borderRadius: 6, padding: '6px 12px', cursor: 'pointer',
          }}>
            Later
          </button>
        </div>
      )}
      {toast && (
        <div style={{
          position: 'fixed', top: 16, right: 16, zIndex: 9999,
          display: 'flex', alignItems: 'center', gap: 12,
          background: '#ffffff', color: '#111827',
          borderRadius: 12, padding: '12px 16px',
          boxShadow: '0 8px 30px rgba(0,0,0,0.18)',
          border: '1px solid #e5e7eb', borderLeft: '4px solid #015C94',
          maxWidth: 360, animation: 'slideIn 0.3s ease',
          cursor: toast.url ? 'pointer' : 'default',
        }} onClick={() => {
          if (toast.url) openNotification(toast.url);
          setToast(null);
        }}>
          <div style={{
            width: 38, height: 38, borderRadius: '50%', flexShrink: 0,
            background: '#e8f1f8', color: '#015C94',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
          }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
          </div>
          <div style={{ minWidth: 0 }}>
            <div style={{ fontWeight: 700, fontSize: 13, color: '#374151' }}>New Notification</div>
            <div style={{ fontSize: 13, color: '#6b7280', marginTop: 2, overflow: 'hidden', textOverflow: 'ellipsis', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical' }}>
              {toast.message}
            </div>
          </div>
          <button type="button" onClick={(e) => { e.stopPropagation(); setToast(null); }}
            style={{ marginLeft: 4, background: 'transparent', border: 'none', cursor: 'pointer', color: '#9ca3af', padding: 4, display: 'flex' }}
            aria-label="Dismiss">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      )}
      {mobileOpen && <div className="sidebar-overlay active" onClick={closeMobile}></div>}
      <aside className={`sidebar${collapsed ? ' collapsed' : ''}${mobileOpen ? ' mobile-open' : ''}`} id="appSidebar" role="navigation" aria-label="Main Navigation">
        <div className="sidebar-logo-area">
          <img src="/images/images.jpg" alt="NCCIA Logo" className="sidebar-logo-circle" />
        </div>
        <div className="sidebar-brand-text">
          <div className="brand-name"><b>NCCIA</b></div>
          <div className="brand-sub"><b>National Cyber Crime Investigation Agency</b></div>
        </div>
        <nav className="sidebar-nav" aria-label="Sidebar Navigation">
          <div className="nav-section-label">Main</div>
          <div className="nav-item">
            <NavLink to="/" end className="nav-link" data-page="dashboard">
              <span className="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M3 12h2l3-9 3 9h2M3 12v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6"/>
                  <circle cx="12" cy="16" r="1"/>
                </svg>
              </span>
              <span>Dashboard</span>
            </NavLink>
          </div>
          {canView('department_progress', user) && (
            <div className="nav-item">
              <NavLink to="/department-progress" className="nav-link" data-page="department-progress">
                <span className="nav-icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M3 3v18h18"/>
                    <path d="M18 17V9"/>
                    <path d="M13 17V5"/>
                    <path d="M8 17v-3"/>
                  </svg>
                </span>
                <span>{user?.role === 'circle_incharge' || user?.designation?.toLowerCase().includes('incharge') ? 'Station Command Portal' : 'Department Progress'}</span>
              </NavLink>
            </div>
          )}
          {canView('analytics', user) && <div className="nav-section-label">Analytics</div>}
          {canView('analytics', user) && <div className="nav-item">
            <NavLink to="/analytics" className="nav-link" data-page="analytics">
              <span className="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M21 12v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-3"/>
                  <path d="M12 2v8l3-3-3-3-3 3 3 3z"/>
                </svg>
              </span>
              <span>Analytics</span>
            </NavLink>
          </div>}
          {isOperatorOnly ? (
            <>
              <div className="nav-section-label">Registration</div>
              <div className="nav-item">
                <NavLink to="/complaints/create" className="nav-link" data-page="complete-registration">
                  <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span>
                  <span>Complete Registration</span>
                </NavLink>
              </div>
              <div className="nav-item">
                <NavLink to="/complaints" className="nav-link" data-page="complaints">
                  <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                  <span>My Complaints</span>
                </NavLink>
              </div>
              <div className="nav-item">
                <NavLink to="/complaints/ocr-imports" className="nav-link" data-page="ocr-imports">
                  <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><circle cx="11" cy="14" r="3"/><line x1="13.2" y1="16.2" x2="16" y2="19"/></svg></span>
                  <span>OCR Import (Bulk)</span>
                </NavLink>
              </div>
            </>
          ) : (
            <>
              {(canView('complaints', user) || canView('verifications', user) || canView('reports', user) || canView('enquiries', user)) && (
                <div className="nav-section-label">Complaints</div>
              )}
              {canView('complaints', user) && (
                <div className="nav-item">
                  <NavLink to="/complaints" className="nav-link" data-page="complaints">
                    <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                    <span>Complaints</span>
                  </NavLink>
                </div>
              )}
              {canCreateComplaint(user) && (
                <>
                <div className="nav-item">
                  <NavLink to="/complaints/create" className="nav-link" data-page="complete-registration">
                    <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span>
                    <span>Complete Registration</span>
                  </NavLink>
                </div>
                <div className="nav-item">
                  <NavLink to="/complaints/import-pdf" className="nav-link" data-page="complaint-pdf-import">
                    <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span>
                    <span>Import PDF</span>
                  </NavLink>
                </div>
                <div className="nav-item">
                  <NavLink to="/complaints/ocr-imports" className="nav-link" data-page="ocr-imports">
                    <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><circle cx="11" cy="14" r="3"/><line x1="13.2" y1="16.2" x2="16" y2="19"/></svg></span>
                    <span>OCR Import (Bulk)</span>
                  </NavLink>
                </div>
                <div className="nav-item">
                  <NavLink to="/complaints/adp" className="nav-link" data-page="adp-launcher">
                    <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span>
                    <span>ADP Extract</span>
                  </NavLink>
                </div>
                </>
              )}
              {canView('verifications', user) && (
                <div className="nav-item">
                  <NavLink to="/verifications" end className="nav-link" data-page="verifications">
                    <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                    <span>Verifications</span>
                    {counts.verifications > 0 && <span className="nav-badge urgent">{counts.verifications}</span>}
                  </NavLink>
                </div>
              )}
              {canView('reports', user) && (
                <div className="nav-item">
                  <NavLink to="/verifications/reports" className="nav-link" data-page="verifications-reports">
                    <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                    <span>Verification Reports</span>
                    {counts.reports > 0 && <span className="nav-badge urgent">{counts.reports}</span>}
                  </NavLink>
                </div>
              )}
              {canView('enquiries', user) && (
                <div className="nav-item">
                  <NavLink to="/enquiries" className="nav-link" data-page="enquiries">
                    <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                    <span>Enquiries</span>
                    {counts.enquiries > 0 && <span className="nav-badge">{counts.enquiries}</span>}
                  </NavLink>
                </div>
              )}
              <div className="nav-item">
                <NavLink to="/messages" className="nav-link" data-page="messages">
                  <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                  <span>Messages</span>
                  {counts.messages > 0 && <span className="nav-badge urgent">{counts.messages}</span>}
                </NavLink>
              </div>
            </>
          )}
          {(canSeeDirectVerification(user) || canSeeDirectEnquiry(user) || canSeeDirectFir(user)) && (
            <div className="nav-item">
              <a
                href="#vip-direct"
                className={`nav-link parent-link${vipOpen ? ' open' : ''}`}
                onClick={(e) => { e.preventDefault(); setVipOpen(!vipOpen); }}
              >
                <span className="nav-icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <polygon points="12 2 15 8.5 22 9.3 17 14.1 18.2 21 12 17.8 5.8 21 7 14.1 2 9.3 9 8.5 12 2"/>
                  </svg>
                </span>
                <span>VIP / Direct Cases</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{marginLeft:'auto',transition:'transform 0.25s',transform:vipOpen?'rotate(180deg)':''}}>
                  <polyline points="6 9 12 15 18 9"/>
                </svg>
              </a>
              <div className={`nav-submenu${vipOpen ? ' open' : ''}`}>
                {canSeeDirectVerification(user) && (
                  <div className="nav-item">
                    <NavLink
                      to="/verifications/create?direct=1"
                      className={({ isActive }) => `nav-link${isActive && location.search.includes('direct=1') ? ' active' : ''}`}
                      data-page="direct-verification"
                    >
                      <span className="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                      <span>VIP Verification</span>
                    </NavLink>
                  </div>
                )}
                {canSeeDirectEnquiry(user) && (
                  <div className="nav-item">
                    <NavLink
                      to="/enquiries/create?direct=1"
                      className={({ isActive }) => `nav-link${isActive && location.search.includes('direct=1') ? ' active' : ''}`}
                      data-page="direct-enquiry"
                    >
                      <span className="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                      <span>VIP Enquiry</span>
                    </NavLink>
                  </div>
                )}
                {canSeeDirectFir(user) && (
                  <div className="nav-item">
                    <NavLink
                      to="/cases/create?direct=1"
                      className={({ isActive }) => `nav-link${isActive && location.search.includes('direct=1') ? ' active' : ''}`}
                      data-page="direct-fir"
                    >
                      <span className="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
                      <span>VIP FIR / DAC</span>
                    </NavLink>
                  </div>
                )}
              </div>
            </div>
          )}
          {canView('io_records', user) && <div className="nav-item">
            <NavLink to="/investigation-officers" className="nav-link" data-page="io-records">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
              <span>IO Records</span>
            </NavLink>
          </div>}
          {canView('dac_cases', user) && <div className="nav-item">
            <a href="#dac" className={`nav-link parent-link${dacOpen ? ' open' : ''}`} onClick={(e) => { e.preventDefault(); setDacOpen(!dacOpen); }}>
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
              <span>DAC Cases</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{marginLeft:'auto',transition:'transform 0.25s',transform:dacOpen?'rotate(180deg)':''}}>
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </a>
            <div className={`nav-submenu${dacOpen ? ' open' : ''}`}>
              <div className="nav-item">
                <NavLink
                  to="/cases"
                  end
                  className={({ isActive }) => `nav-link${isActive && !location.search.includes('direct=1') ? ' active' : ''}`}
                  data-page="dac-new"
                >
                  <span className="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12h14"/></svg></span>
                  <span>All Cases</span>
                </NavLink>
              </div>
              <div className="nav-item">
                <NavLink
                  to="/court-cases"
                  end
                  className={({ isActive }) => `nav-link${isActive && !location.search.includes('direct=1') ? ' active' : ''}`}
                  data-page="court-cases"
                >
                  <span className="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
                  <span>Court Cases</span>
                </NavLink>
              </div>
            </div>
          </div>}
          {(canUseWarrantRequests(user) || canUseProclamations(user)) && <div className="nav-section-label">Warrants & Orders</div>}
          {canUseWarrantRequests(user) && <div className="nav-item">
            <NavLink to="/warrant-requests" className="nav-link" data-page="warrant-requests">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 12l2 2 4-4"/><path d="M12 3l7 4v5c0 4-3 7-7 9-4-2-7-5-7-9V7z"/></svg></span>
              <span>Warrant Requests</span>
            </NavLink>
          </div>}
          {canUseProclamations(user) && <div className="nav-item">
            <NavLink to="/proclamations" className="nav-link" data-page="proclamations">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg></span>
              <span>Proclamation 87/88</span>
            </NavLink>
          </div>}
          {canView('reference', user) && <div className="nav-section-label">Reference</div>}
          {(canView('dsr_reports', user) || canView('do_letters', user)) && <div className="nav-section-label">Administration</div>}
          {canView('dsr_reports', user) && <div className="nav-item">
            <NavLink to="/dsr-reports" className="nav-link" data-page="dsr-reports">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
              <span>DSR (Daily)</span>
            </NavLink>
          </div>}
          {canView('do_letters', user) && <div className="nav-item">
            <NavLink to="/do-letters" className="nav-link" data-page="do-letters">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
              <span>D.O. Letter (Monthly)</span>
            </NavLink>
          </div>}
          {(canView('users', user) || canView('login_history', user) || canView('circles', user)) && <div className="nav-item">
            {canView('users', user) && <NavLink to="/users" className="nav-link" data-page="users">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
              <span>Users</span>
            </NavLink>}
            {canView('login_history', user) && <NavLink to="/login-history" className="nav-link" data-page="login-history">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></span>
              <span>Login History</span>
            </NavLink>}
            {canView('circles', user) && <NavLink to="/circles" className="nav-link" data-page="circles">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg></span>
              <span>Circles</span>
            </NavLink>}
          </div>}
          {canView('offence_types', user) && <div className="nav-item">
            <NavLink to="/offence-types" className="nav-link" data-page="offence-types">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
              <span>Crime Categories</span>
            </NavLink>
          </div>}
          {canViewApprovalSettings(user) && <div className="nav-item">
            <NavLink to="/approval-settings" className="nav-link" data-page="approval-settings">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
              <span>Approval Settings</span>
            </NavLink>
          </div>}
          {canView('reference', user) && <div className="nav-section-label">Reference Library</div>}
          {canView('reference', user) && <div className="nav-item">
            <NavLink to="/laws" className="nav-link" data-page="laws">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 4h16v16H4z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/></svg></span>
              <span>Laws</span>
            </NavLink>
          </div>}
          {canView('reference', user) && <div className="nav-item">
            <NavLink to="/rules" className="nav-link" data-page="rules">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg></span>
              <span>Rules & Regulations</span>
            </NavLink>
          </div>}
          {canView('reference', user) && <div className="nav-item">
            <NavLink to="/sops" className="nav-link" data-page="sop">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
              <span>SOP</span>
            </NavLink>
          </div>}
          {canView('reference', user) && <div className="nav-item">
            <NavLink to="/user-manuals" className="nav-link" data-page="user-manual">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></span>
              <span>User Manual</span>
            </NavLink>
          </div>}
          {canView('reference', user) && <div className="nav-item">
            <NavLink to="/software-guide" className="nav-link" data-page="software-guide">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polygon points="5 3 19 12 5 21 5 3"/></svg></span>
              <span>Software Guide</span>
            </NavLink>
          </div>}
          {canView('sms_logs', user) && <div className="nav-item">
            <NavLink to="/sms" className="nav-link" data-page="sms">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg></span>
              <span>SMS Log</span>
            </NavLink>
          </div>}
          <div className="nav-section-label">Account</div>
          <div className="nav-item">
            <NavLink to="/profile" className="nav-link" data-page="profile">
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg></span>
              <span>My Profile</span>
            </NavLink>
          </div>
          <div className="nav-item">
            <a href="#" className="nav-link" style={{color:'#fff'}} onClick={(e) => { e.preventDefault(); logout(); }}>
              <span className="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
              <span>Logout</span>
            </a>
          </div>
        </nav>
         <div className="sidebar-footer">
           <NavLink to="/software-guide" className="help-btn">
             <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
             <span>Help &amp; Support</span>
           </NavLink>
         </div>
      </aside>
      <div className={`main-content${collapsed ? ' collapsed' : ''}`}>
        <header className="header">
          <div className="header-left">
            <button className="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar" aria-label="Toggle Sidebar" onClick={toggleSidebar}>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
              </svg>
            </button>
            <nav className="breadcrumb" aria-label="breadcrumb">
              <span style={{color:'#fff'}} className="separator">›</span>
              <span style={{color:'#fff'}}>NCCIA</span>
              <span style={{color:'#fff'}} className="separator">›</span>
              <span style={{color:'#fff'}} className="current">{breadcrumb}</span>
            </nav>
            <div className="header-station-badge" style={{
              marginLeft: '14px',
              padding: '4px 12px',
              borderRadius: '20px',
              background: 'rgba(255, 255, 255, 0.15)',
              border: '1px solid rgba(255, 255, 255, 0.3)',
              color: '#ffffff',
              fontSize: '12px',
              fontWeight: 600,
              display: 'flex',
              alignItems: 'center',
              gap: '7px',
              whiteSpace: 'nowrap'
            }}>
              <span style={{ display: 'inline-block', width: 8, height: 8, borderRadius: '50%', background: '#48bb78', boxShadow: '0 0 6px #48bb78' }}></span>
              {circleName || circleCode
                ? `Station: NCCIA ${circleName || circleCode}`
                : (isZonalHead ? '🏢 Punjab Regional Directorate' : (isHqUser ? '🏛️ National HQ (Islamabad)' : '🏢 Station Command'))}
            </div>
          </div>
          <div className="header-search" role="search" ref={searchRef} style={{position:'relative', flex: '0 1 320px', minWidth: 180, maxWidth: 360}}>
            <svg className="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="11" cy="11" r="8"/>
              <path d="m21 21-4.35-4.35"/>
            </svg>
            <input
              ref={searchInputRef}
              type="search"
              placeholder="Search tracking no e.g. 0001/26…"
              id="globalSearch"
              autoComplete="off"
              aria-label="Global Search"
              value={searchQuery}
              onChange={(e) => { setSearchQuery(e.target.value); setSearchOpen(true); }}
              onFocus={() => { if (searchQuery.trim() || searchResults.length) setSearchOpen(true); }}
            />
            <kbd style={{fontSize:'10px',color:'white',border:'1px solid rgba(255,255,255,0.3)',borderRadius:'4px',padding:'1px 5px'}}>Ctrl K</kbd>
            {searchOpen && searchQuery.trim() && (
              <div className="global-search-dropdown" style={{
                position:'absolute', top:'calc(100% + 8px)', left:0, right:0, zIndex:1200,
                background:'#fff', borderRadius:10, border:'1px solid #d8dee6',
                boxShadow:'0 12px 32px rgba(0,0,0,0.18)', overflow:'hidden', maxHeight:360, overflowY:'auto'
              }}>
                {searchLoading && (
                  <div style={{padding:'14px 16px', fontSize:13, color:'#6c757d'}}>Searching…</div>
                )}
                {!searchLoading && searchResults.length === 0 && (
                  <div style={{padding:'14px 16px', fontSize:13, color:'#6c757d'}}>No matching complaints found</div>
                )}
                {!searchLoading && searchResults.map((item) => {
                  const isEnquiry = item.type === 'enquiry';
                  const isComplaint = item.type === 'complaint';
                  return (
                    <div
                      key={`${item.type}-${item.id}-${item.verification_id || 0}`}
                      onClick={() => openSearchResult(item)}
                      style={{
                        display:'block', width:'100%', textAlign:'left', border:'none', background:'transparent',
                        padding:'12px 14px', cursor:'pointer', borderBottom:'1px solid #f0f2f5'
                      }}
                      onMouseEnter={(e) => { e.currentTarget.style.background = '#f5f8fb'; }}
                      onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; }}
                    >
                      <div style={{display:'flex', justifyContent:'space-between', gap:8, alignItems:'baseline'}}>
                        <strong style={{fontSize:13, color: isEnquiry ? '#7c3aed' : '#015C94'}}>
                          {isEnquiry
                            ? (item.enquiry_number ? `Enquiry #${item.enquiry_number}` : `Enquiry #${item.enquiry_id}`)
                            : (item.tracking_no || `Complaint #${item.id}`)
                          }
                        </strong>
                        <div style={{display:'flex', gap:5, alignItems:'center'}}>
                          {isEnquiry && (
                            <span style={{fontSize:11, fontWeight:700, color:'#7c3aed', background:'rgba(124,58,237,0.1)', padding:'2px 8px', borderRadius:999}}>
                              Enquiry
                            </span>
                          )}
                          {item.verification_no && (
                            <span style={{fontSize:11, fontWeight:700, color:'#264078', background:'rgba(38,64,120,0.1)', padding:'2px 8px', borderRadius:999}}>
                              {item.verification_no}
                            </span>
                          )}
                          {item.tracking_no && isEnquiry && (
                            <span style={{fontSize:11, color:'#94a3b8', fontWeight:400}}>
                              {item.tracking_no}
                            </span>
                          )}
                        </div>
                      </div>
                      <div style={{fontSize:12.5, color:'#2b2b2b', marginTop:3}}>
                        {item.complainant_name}
                        {item.accused_name && (
                          <span style={{color:'#64748b'}}> · Accused: {item.accused_name}</span>
                        )}
                      </div>
                      <div style={{fontSize:11.5, color:'#6c757d', marginTop:2}}>
                        {isEnquiry
                          ? `Status: ${(item.status || 'open').replace(/_/g, ' ')}`
                          : (item.verification_status
                              ? `Verification: ${item.verification_status.replace(/_/g, ' ')}`
                              : (item.enquiry_number ? `Enquiry: #${item.enquiry_number}` : 'No verification assigned'))
                        }
                        {item.officer_name ? ` · ${item.officer_name}` : ''}
                      </div>
                      {isComplaint && (
                        <div style={{display:'flex', gap:5, marginTop:8, flexWrap:'wrap'}}>
                          <button type="button" onClick={(e) => { e.stopPropagation(); openSearchResult(item); }} style={{background:'rgba(1,92,148,0.1)', color:'#015C94', border:'none', borderRadius:6, padding:'4px 9px', cursor:'pointer', fontSize:11, fontWeight:600}}>Open</button>
                          {canBulkActions() && (
                            <>
                              <button type="button" onClick={(e) => { e.stopPropagation(); openLayoutAction(item, 'closure'); }} style={{background:'#fff', color:'#015C94', border:'1.5px solid rgba(1,92,148,0.35)', borderRadius:6, padding:'4px 9px', cursor:'pointer', fontSize:11, fontWeight:600}}>Close</button>
                              <button type="button" onClick={(e) => { e.stopPropagation(); openLayoutAction(item, 'merge'); }} style={{background:'#fff', color:'#ea580c', border:'1.5px solid rgba(234,88,12,0.45)', borderRadius:6, padding:'4px 9px', cursor:'pointer', fontSize:11, fontWeight:600}}>Merge</button>
                              <button type="button" onClick={(e) => { e.stopPropagation(); openLayoutAction(item, 'transfer'); }} style={{background:'#fff', color:'#7c3aed', border:'1.5px solid rgba(124,58,237,0.45)', borderRadius:6, padding:'4px 9px', cursor:'pointer', fontSize:11, fontWeight:600}}>Transfer</button>
                              <button type="button" onClick={(e) => { e.stopPropagation(); openLayoutAction(item, 'proceed'); }} style={{background:'#fff', color:'#38a169', border:'1.5px solid rgba(56,161,105,0.5)', borderRadius:6, padding:'4px 9px', cursor:'pointer', fontSize:11, fontWeight:600}}>Proceed to Verification</button>
                            </>
                          )}
                        </div>
                      )}
                      {isEnquiry && (
                        <div style={{display:'flex', gap:5, marginTop:8}}>
                          <button type="button" onClick={(e) => { e.stopPropagation(); openSearchResult(item); }} style={{background:'rgba(124,58,237,0.1)', color:'#7c3aed', border:'none', borderRadius:6, padding:'4px 9px', cursor:'pointer', fontSize:11, fontWeight:600}}>
                            🔍 Open Enquiry
                          </button>
                        </div>
                      )}
                    </div>
                  );
                })}

              </div>
            )}
          </div>
          <div className="header-right">
            <button className="header-icon-btn" title="Refresh Data" aria-label="Refresh" onClick={() => window.location.reload()}>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
              </svg>
            </button>
            <div className="header-divider"></div>
            <div ref={notifRef} style={{position:'relative', flexShrink:0}}>
              <button className="header-icon-btn notif-btn" title="Notifications" aria-label="Notifications" onClick={() => { setNotifOpen(!notifOpen); setUserMenuOpen(false); }}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                  <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                {((notifications.unread_count || 0) + (pendingTasks.count || 0)) > 0 && (
                  <span className="notif-badge">{(notifications.unread_count || 0) + (pendingTasks.count || 0)}</span>
                )}
              </button>
              <div className={`notif-dropdown ${notifOpen ? 'open' : ''}`} role="menu">
                <div className="notif-dropdown-header">
                  <span>Notifications</span>
                  <button type="button" onClick={markAllNotificationsRead}>Mark all read</button>
                </div>
                <div className="notif-dropdown-body">
                  {pendingTasks.count > 0 && (
                    <>
                      <div className="notif-section-title">Pending Tasks ({pendingTasks.count})</div>
                      {pendingTasks.tasks.map((t, i) => (
                        <a key={`task-${i}`} href={t.url} className="notif-item pending" onClick={(e) => { e.preventDefault(); setNotifOpen(false); openNotification(t.url); }}>
                          <span className="notif-item-dot"></span>
                          <div className="notif-item-text">
                            <span>{t.title}</span>
                            <small>{t.status.replace(/_/g, ' ')}</small>
                          </div>
                        </a>
                      ))}
                    </>
                  )}
                  <div className="notif-section-title">Notifications</div>
                  {notifications.notifications.length === 0 && (
                    <div className="notif-empty">No notifications yet</div>
                  )}
                  {notifications.notifications.map(n => (
                    <a key={n.id} href={n.data?.url || '/dashboard'} className={`notif-item${n.read_at ? '' : ' unread'}`}
                       onClick={(e) => { e.preventDefault(); setNotifOpen(false); markNotificationRead(n); openNotification(n.data?.url); }}>
                      <span className="notif-item-dot"></span>
                      <div className="notif-item-text">
                        <span>{n.data?.message || 'Notification'}</span>
                        <small>{timeAgo(n.created_at)}</small>
                      </div>
                    </a>
                  ))}
                </div>
                <div className="notif-dropdown-footer">
                  <a href="#tasks" onClick={(e) => { e.preventDefault(); setNotifOpen(false); navigate(isOperatorOnly ? '/complaints/create' : '/verifications'); }}>View my tasks</a>
                </div>
              </div>
            </div>
            <div ref={userMenuRef} style={{position:'relative', flexShrink:0}}>
              <div className={`header-user${userMenuOpen ? ' open' : ''}`} onClick={() => { setUserMenuOpen(!userMenuOpen); setNotifOpen(false); }} style={{cursor:'pointer'}}>
                <div className="header-avatar">{initials}</div>
                <div className="header-user-info">
                  <div className="header-user-name">{user?.name || 'User'}</div>
                  <div className="header-user-role">
                    {user?.designation || (user?.role === 'operator' ? 'Front Desk Officer (FDO)' : user?.role?.replace(/_/g, ' ')) || 'Officer'} · {circleCode ? `${circleCode} Circle` : (isZonalHead ? 'Punjab Directorate' : 'National HQ')}
                  </div>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{marginLeft:'2px',color:'rgba(255,255,255,0.7)',transition:'transform 0.25s',transform:userMenuOpen?'rotate(180deg)':''}}>
                  <polyline points="6 9 12 15 18 9"/>
                </svg>
              </div>
              {userMenuOpen && (
                <div className="user-dropdown" role="menu">
                  <div className="user-dropdown-header">
                    <div className="user-dropdown-avatar">{initials}</div>
                    <div>
                      <div className="user-dropdown-name">{user?.name || 'User'}</div>
                      <div className="user-dropdown-role">{user?.designation || (user?.role === 'operator' ? 'Front Desk Officer (FDO)' : user?.role?.replace(/_/g, ' ')) || 'Officer'}</div>
                      <div className="user-dropdown-circle">
                        <span className="user-online-dot"></span>
                        {user?.circle?.name || (user?.circle_code ? `${user.circle_code} Circle Station` : 'National Headquarters (Islamabad)')}
                      </div>
                    </div>
                  </div>
                  <div className="user-dropdown-body">
                    <a href="#profile" className="user-dropdown-item" role="menuitem" onClick={(e) => { e.preventDefault(); setUserMenuOpen(false); go('/profile'); }}>
                      <div className="user-dropdown-item-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg></div>
                      <div className="user-dropdown-item-text"><span>My Profile</span><small>View & edit your profile</small></div>
                    </a>
                    <a href="#my-cases" className="user-dropdown-item" role="menuitem" onClick={(e) => { e.preventDefault(); setUserMenuOpen(false); go('/cases'); }}>
                      <div className="user-dropdown-item-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
                      <div className="user-dropdown-item-text"><span>My Assigned Cases</span><small>284 pending · 953 processed</small></div>
                    </a>
                    <a href="#activity" className="user-dropdown-item" role="menuitem" onClick={(e) => { e.preventDefault(); setUserMenuOpen(false); go('/profile'); }}>
                      <div className="user-dropdown-item-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
                      <div className="user-dropdown-item-text"><span>Activity Log</span><small>Your recent actions</small></div>
                    </a>
                    <a href="#settings" className="user-dropdown-item" role="menuitem" onClick={(e) => { e.preventDefault(); setUserMenuOpen(false); go('/profile'); }}>
                      <div className="user-dropdown-item-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33"/></svg></div>
                      <div className="user-dropdown-item-text"><span>Account Settings</span><small>Password, preferences</small></div>
                    </a>
                    <a href="#help" className="user-dropdown-item" role="menuitem" onClick={(e) => { e.preventDefault(); setUserMenuOpen(false); go('/software-guide'); }}>
                      <div className="user-dropdown-item-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                      <div className="user-dropdown-item-text"><span>Help & Support</span><small>Developer contact, docs, guide</small></div>
                    </a>
                  </div>
                  <div className="user-dropdown-footer">
                    <button className="user-dropdown-logout" onClick={() => { setUserMenuOpen(false); logout(); }}>
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                      Sign Out
                    </button>
                    <span className="user-dropdown-version">v2.4.1 · NCCIA CMS</span>
                  </div>
                </div>
              )}
            </div>
          </div>
        </header>
        <main className="page-content" id="pageContent" role="main">
          <PageErrorBoundary key={location.pathname}><Outlet /></PageErrorBoundary>
        </main>
      </div>

      {layoutComplaintAction && (
        <div className="modal-overlay" onClick={() => setLayoutComplaintAction(null)}>
          <div className="modal-container" style={{maxWidth:'480px'}} onClick={e => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Complaint {layoutComplaintAction.action === 'closure' ? 'Closure' : layoutComplaintAction.action === 'merge' ? 'Merge' : 'Transfer'}</h3>
              <button className="modal-close" onClick={() => setLayoutComplaintAction(null)}>&times;</button>
            </div>
            <div className="modal-body">
              <p style={{marginBottom:12,fontSize:13,color:'#334155',background:'#eff6ff',border:'1px solid #bfdbfe',borderRadius:8,padding:'10px 12px'}}>
                Complaint <strong>#{layoutComplaintAction.complaint.tracking_no || layoutComplaintAction.complaint.id}</strong>
                {layoutComplaintAction.complaint.complainant_name ? ` · ${layoutComplaintAction.complaint.complainant_name}` : ''}
              </p>

              {layoutComplaintAction.action === 'closure' && (
                <div className="cf-group">
                  <label className="cf-label">Closure Reason <span className="required">*</span></label>
                  <select className="cf-input" value={layoutActionForm.closure_reason} onChange={e => setLayoutActionForm(f => ({...f, closure_reason: e.target.value}))} required>
                    <option value="">Select closure reason...</option>
                    {CLOSURE_REASONS.map(o => (
                      <option key={o.value} value={o.value}>{o.label}</option>
                    ))}
                  </select>
                </div>
              )}

              {layoutComplaintAction.action === 'merge' && (
                <div className="cf-group">
                  <label className="cf-label">Merge with Complaint ID <span className="required">*</span></label>
                  <input type="number" className="cf-input" value={layoutActionForm.merge_complaint_id} onChange={e => setLayoutActionForm(f => ({...f, merge_complaint_id: e.target.value}))} placeholder="Complaint ID to merge with" required />
                  <p style={{fontSize:12,color:'#6c757d',marginTop:4}}>Jis complaint se merge karni hai us ka ID likhein.</p>
                </div>
              )}

              {layoutComplaintAction.action === 'transfer' && (
                <>
                  <div className="cf-group">
                    <label className="cf-label">Transfer Department <span className="required">*</span></label>
                    <input type="text" className="cf-input" value={layoutActionForm.transfer_department} onChange={e => setLayoutActionForm(f => ({...f, transfer_department: e.target.value}))} placeholder="e.g. NCCIA, Police" required />
                  </div>
                  <div className="cf-group">
                    <label className="cf-label">Transfer Circle</label>
                    <select className="cf-input" value={layoutActionForm.transfer_circle_id} onChange={e => setLayoutActionForm(f => ({...f, transfer_circle_id: e.target.value}))}>
                      <option value="">Select circle (optional)</option>
                      {layoutActionCircles.map(c => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </div>
                </>
              )}
            </div>
            <div className="modal-footer">
              <button className="btn btn-outline" onClick={() => setLayoutComplaintAction(null)}>Cancel</button>
              <button className="btn btn-primary" onClick={handleLayoutAction} disabled={layoutActionSaving}>
                {layoutActionSaving ? 'Processing…' : 'Confirm'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
