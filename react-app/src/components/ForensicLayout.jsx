import { useState, useEffect, useRef } from 'react';
import { Outlet, NavLink, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { isForensicAdmin } from '../utils/permissions';
import api from '../api';

function getBreadcrumb(pathname) {
  if (pathname.startsWith('/forensic/requests/')) return 'Request Detail';
  const map = {
    '/forensic': 'Dashboard',
    '/forensic/requests': 'Seizure Register',
    '/forensic/users': 'Forensic Users',
    '/forensic/profile': 'My Profile',
  };
  return map[pathname] || 'Dashboard';
}

export default function ForensicLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const isAdmin = isForensicAdmin(user);
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [counts, setCounts] = useState({ submitted: 0, assigned: 0 });
  const userMenuRef = useRef(null);

  const initials = user?.name
    ? user.name.split(' ').map(w => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase()
    : 'U';

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
    document.body.classList.toggle('sidebar-collapsed', collapsed);
  }, [collapsed]);

  useEffect(() => {
    setMobileOpen(false);
    setUserMenuOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    const handler = (e) => {
      if (userMenuRef.current && !userMenuRef.current.contains(e.target)) {
        setUserMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  useEffect(() => {
    api.get('/forensic/request-stats').then(r => {
      if (r.data) {
        setCounts({
          submitted: r.data.submitted || 0,
          assigned: r.data.assigned || 0,
        });
      }
    }).catch(() => {});
  }, [location.pathname]);

  useEffect(() => {
    const checkAlerts = () => {
      api.get('/security-alerts').then(r => {
        if (r.data && r.data.alert) {
          alert(r.data.alert);
        }
      }).catch(() => {});
    };
    const timer = setInterval(checkAlerts, 2500);
    return () => clearInterval(timer);
  }, []);

  const breadcrumb = getBreadcrumb(location.pathname);

  return (
    <div className="app-wrapper">
      {mobileOpen && <div className="sidebar-overlay active" onClick={closeMobile}></div>}
      <aside className={`sidebar${collapsed ? ' collapsed' : ''}${mobileOpen ? ' mobile-open' : ''}`} id="sidebar" role="navigation" aria-label="Forensic Navigation">
        <div className="sidebar-logo-area">
          <img src="/images/NCCIA.webp" alt="NCCIA Logo" className="sidebar-logo-circle" style={{ width: 42, height: 42, objectFit: 'contain', borderRadius: '50%', background: '#fff', padding: 2 }} />
        </div>
        <div className="sidebar-brand-text">
          <div className="brand-name"><b>NCCIA</b></div>
          <div className="brand-sub"><b>Forensic Lab</b></div>
        </div>

        <nav className="sidebar-nav" aria-label="Forensic Sidebar Navigation">
          <div className="nav-section-label">Main</div>
          <div className="nav-item">
            <NavLink to="/forensic" end className="nav-link" data-page="dashboard">
              <span className="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M3 12h2l3-9 3 9h2M3 12v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6"/>
                  <circle cx="12" cy="16" r="1"/>
                </svg>
              </span>
              <span>Dashboard</span>
            </NavLink>
          </div>

          <div className="nav-section-label">Evidence &amp; Seizures</div>
          <div className="nav-item">
            <NavLink to="/forensic/requests" className="nav-link" data-page="forensic-requests">
              <span className="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                  <rect x="9" y="3" width="6" height="4" rx="1"/>
                  <path d="M9 12h6M9 16h4"/>
                </svg>
              </span>
              <span>Seizure Register</span>
              {counts.submitted > 0 && <span className="nav-badge urgent">{counts.submitted}</span>}
            </NavLink>
          </div>

          {isAdmin && (
            <>
              <div className="nav-section-label">Administration</div>
              <div className="nav-item">
                <NavLink to="/forensic/users" className="nav-link" data-page="forensic-users">
                  <span className="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                      <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                  </span>
                  <span>Forensic Users</span>
                </NavLink>
              </div>
            </>
          )}

          <div className="nav-section-label">Account</div>
          <div className="nav-item">
            <NavLink to="/forensic/profile" className="nav-link" data-page="profile">
              <span className="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                </svg>
              </span>
              <span>My Profile</span>
            </NavLink>
          </div>

          <div className="nav-item">
            <NavLink to="/forensic/help-support" className="nav-link" data-page="help-support">
              <span className="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </span>
              <span>Help &amp; Support</span>
            </NavLink>
          </div>

          <div className="nav-item">
            <a href="#" className="nav-link" onClick={(e) => { e.preventDefault(); logout(); }}>
              <span className="nav-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
              </span>
              <span>Logout</span>
            </a>
          </div>
        </nav>
      </aside>

      <div className={`main-content${collapsed ? ' collapsed' : ''}`}>
        <header className="header">
          <div className="header-left">
            <button className="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar" aria-label="Toggle Sidebar" onClick={toggleSidebar}>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
              </svg>
            </button>
            <nav className="breadcrumb" aria-label="breadcrumb">
              <span className="separator">›</span>
              <span>Forensic Lab</span>
              <span className="separator">›</span>
              <span className="current">{breadcrumb}</span>
            </nav>
          </div>

          <div className="header-right">
            <button className="header-icon-btn" title="Refresh Data" aria-label="Refresh" onClick={() => window.location.reload()}>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
              </svg>
            </button>

            <div className="header-divider"></div>

            <div ref={userMenuRef} style={{ position: 'relative', flexShrink: 0 }}>
              <div className={`header-user${userMenuOpen ? ' open' : ''}`} onClick={() => setUserMenuOpen(!userMenuOpen)} style={{ cursor: 'pointer' }}>
                <div className="header-avatar">{initials}</div>
                <div className="header-user-info">
                  <div className="header-user-name">{user?.name || 'User'}</div>
                  <div className="header-user-role">{user?.designation || user?.role || 'Forensic Officer'}</div>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ marginLeft: '2px', color: 'rgba(255,255,255,0.7)', transition: 'transform 0.25s', transform: userMenuOpen ? 'rotate(180deg)' : '' }}>
                  <polyline points="6 9 12 15 18 9"/>
                </svg>
              </div>

              {userMenuOpen && (
                <div className="user-dropdown" role="menu">
                  <div className="user-dropdown-header">
                    <div className="user-dropdown-avatar">{initials}</div>
                    <div>
                      <div className="user-dropdown-name">{user?.name || 'User'}</div>
                      <div className="user-dropdown-role">{user?.designation || user?.role || 'Forensic Officer'}</div>
                      <div className="user-dropdown-circle">
                        <span className="user-online-dot"></span>
                        NCCIA Forensic Lab
                      </div>
                    </div>
                  </div>

                  <div className="user-dropdown-body">
                    <a href="#profile" className="user-dropdown-item" role="menuitem" onClick={(e) => { e.preventDefault(); setUserMenuOpen(false); go('/forensic/profile'); }}>
                      <div className="user-dropdown-item-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
                      </div>
                      <div className="user-dropdown-item-text"><span>My Profile</span><small>View & edit your profile</small></div>
                    </a>

                    {isAdmin && (
                      <a href="#users" className="user-dropdown-item" role="menuitem" onClick={(e) => { e.preventDefault(); setUserMenuOpen(false); go('/forensic/users'); }}>
                        <div className="user-dropdown-item-icon">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <div className="user-dropdown-item-text"><span>Forensic Users</span><small>Manage users & credentials</small></div>
                      </a>
                    )}

                    <a href="#help" className="user-dropdown-item" role="menuitem" onClick={(e) => { e.preventDefault(); setUserMenuOpen(false); go('/forensic/help-support'); }}>
                      <div className="user-dropdown-item-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                      </div>
                      <div className="user-dropdown-item-text"><span>Help &amp; Support</span><small>Developer Desk &amp; Guide</small></div>
                    </a>
                  </div>

                  <div className="user-dropdown-footer">
                    <button className="user-dropdown-logout" onClick={() => { setUserMenuOpen(false); logout(); }}>
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                      Sign Out
                    </button>
                    <span className="user-dropdown-version">Forensic Portal · NCCIA</span>
                  </div>
                </div>
              )}
            </div>
          </div>
        </header>

        <main className="page-content" id="pageContent" role="main">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
