<header class="header">
  <div class="header-left">
    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar" aria-label="Toggle Sidebar">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <line x1="3" y1="6"  x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
    <nav class="breadcrumb" aria-label="breadcrumb">
      <span style="color: #fff;" class="separator">›</span>
      <span style="color: #fff;" id="breadcrumbSection">NCCIA</span>
      <span style="color: #fff;" class="separator" id="breadcrumbSep2">›</span>
      <span style="color: #fff;" class="current" id="breadcrumbPage">Case Management System</span>
    </nav>
  </div>
  <div class="header-search" role="search">
    <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="11" cy="11" r="8"/>
      <path d="m21 21-4.35-4.35"/>
    </svg>
    <input type="search" placeholder="Search complaints, cases, officers…" id="globalSearch" autocomplete="off" aria-label="Global Search" />
    <kbd style="font-size:10px;color:white;border:1px solid var(--border);border-radius:4px;padding:1px 5px;">Ctrl K</kbd>
  </div>
  <div class="header-right">
    <button class="header-icon-btn" id="refreshBtn" title="Refresh Data" aria-label="Refresh">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
      </svg>
    </button>
    <button class="header-icon-btn" title="Settings" aria-label="Settings" onclick="window.location.href='#settings'">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="3"/>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
      </svg>
    </button>
    <div class="header-divider"></div>
    <div style="position:relative;">
      <div class="header-user" id="userMenuBtn" title="Account Menu" aria-haspopup="true" aria-expanded="false">
        <div class="header-avatar" aria-hidden="true">{{ collect(explode(' ', auth()->user()->name))->map(fn($w) => substr($w, 0, 1))->take(2)->join('') }}</div>
        <div class="header-user-info">
          <div class="header-user-name">{{ auth()->user()->name }}</div>
          <div class="header-user-role">{{ auth()->user()->designation ?? auth()->user()->role ?? auth()->user()->getRoleNames()->first() }} · {{ optional(auth()->user()->circle)->code ?? 'NCCIA' }}</div>
        </div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="userMenuArrow" style="color:var(--text-muted);margin-left:2px;transition:transform 0.25s;">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>
      <div class="user-dropdown" id="userDropdown" role="menu">
        <div class="user-dropdown-header">
          <div class="user-dropdown-avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn($w) => substr($w, 0, 1))->take(2)->join('') }}</div>
          <div>
            <div class="user-dropdown-name">{{ auth()->user()->name }}</div>
            <div class="user-dropdown-role">{{ auth()->user()->designation ?? auth()->user()->role ?? auth()->user()->getRoleNames()->first() }}</div>
            <div class="user-dropdown-circle">
              <span class="user-online-dot"></span>
              {{ optional(auth()->user()->circle)->name ?? optional(auth()->user()->circle)->code ?? 'NCCIA' }} Circle
            </div>
          </div>
        </div>
        <div class="user-dropdown-body">
          <a href="#profile" class="user-dropdown-item" role="menuitem">
            <div class="user-dropdown-item-icon" style="background:#015C94;color:#fff;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg></div>
            <div class="user-dropdown-item-text"><span>My Profile</span><small>View &amp; edit your profile</small></div>
          </a>
          <a href="#my-cases" class="user-dropdown-item" role="menuitem">
            <div class="user-dropdown-item-icon" style="background:#015C94;color:#fff;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
            <div class="user-dropdown-item-text"><span>My Assigned Cases</span><small>284 pending · 953 processed</small></div>
          </a>
          <a href="#activity" class="user-dropdown-item" role="menuitem">
            <div class="user-dropdown-item-icon" style="background:#015C94;color:#fff;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
            <div class="user-dropdown-item-text"><span>Activity Log</span><small>Your recent actions</small></div>
          </a>
          <a href="#settings" class="user-dropdown-item" role="menuitem">
            <div class="user-dropdown-item-icon" style="background:#015C94;color:#fff;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33"/></svg></div>
            <div class="user-dropdown-item-text"><span>Account Settings</span><small>Password, preferences</small></div>
          </a>
          <a href="#help" class="user-dropdown-item" role="menuitem">
            <div class="user-dropdown-item-icon" style="background:#015C94;color:#fff;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
            <div class="user-dropdown-item-text"><span>Help &amp; Support</span><small>Docs, FAQs, contact</small></div>
          </a>
        </div>
        <div class="user-dropdown-footer">
          <button class="user-dropdown-logout btn btn-primary btn-sm" id="applyFilterBtn" role="menuitem" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
          </button>
          <span class="user-dropdown-version">v2.4.1 · NCCIA CMS</span>
        </div>
      </div>
    </div>
  </div>
</header>

<style>
.user-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 290px;
  background: #ffffff !important;
  border-radius: 12px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.22), 0 2px 8px rgba(0, 0, 0, 0.06);
  z-index: 9999;
  overflow: hidden;
  border: 1px solid rgba(1, 92, 148, 0.25);
  display: none;
  animation: dropdownFade 0.15s ease-out;
}
.user-dropdown.open {
  display: block !important;
}
@keyframes dropdownFade {
  from { opacity: 0; transform: translateY(-6px); }
  to { opacity: 1; transform: translateY(0); }
}
.user-dropdown-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #1e293b;
  color: #fff;
}
.user-dropdown-avatar {
  width: 44px;
  height: 44px;
  min-width: 44px;
  border-radius: 50%;
  background: #015C94;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  border: 2px solid rgba(255, 255, 255, 0.3);
  flex-shrink: 0;
}
.user-dropdown-name {
  font-size: 13.5px;
  font-weight: 700;
  color: #fff;
  line-height: 1.3;
}
.user-dropdown-role {
  font-size: 11.5px;
  color: rgba(255, 255, 255, 0.75);
  margin-top: 1px;
}
.user-dropdown-circle {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-top: 4px;
  font-size: 10.5px;
  color: rgba(255, 255, 255, 0.85);
  background: rgba(255, 255, 255, 0.15);
  padding: 2px 8px;
  border-radius: 20px;
}
.user-online-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
  flex-shrink: 0;
}
.user-dropdown-body {
  padding: 6px 0;
  border-bottom: 1px solid rgba(1, 92, 148, 0.12);
  background: #fff;
}
.user-dropdown-item {
  display: flex !important;
  align-items: center !important;
  gap: 12px !important;
  padding: 9px 16px !important;
  color: #2b2b2b !important;
  text-decoration: none !important;
  transition: background 0.15s ease;
  cursor: pointer;
  background: #fff !important;
  border: none !important;
  width: 100% !important;
  box-sizing: border-box !important;
}
.user-dropdown-item:hover {
  background: #f0f7ff !important;
}
.user-dropdown-item-icon {
  width: 32px;
  height: 32px;
  min-width: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #015C94;
  color: #fff;
  flex-shrink: 0;
}
.user-dropdown-item-text {
  display: flex !important;
  flex-direction: column !important;
  gap: 2px !important;
  text-align: left !important;
}
.user-dropdown-item-text span {
  font-size: 13px !important;
  font-weight: 600 !important;
  color: #1e293b !important;
  line-height: 1.3 !important;
  display: block !important;
}
.user-dropdown-item:hover .user-dropdown-item-text span {
  color: #015C94 !important;
}
.user-dropdown-item-text small {
  font-size: 11px !important;
  color: #64748b !important;
  font-weight: 400 !important;
  display: block !important;
}
.user-dropdown-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  background: #f8fafc;
}
.user-dropdown-logout {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 14px;
  border-radius: 7px;
  font-size: 12.5px;
  font-weight: 600;
  text-decoration: none;
  border: 1px solid rgba(43,43,43,0.15);
  background: #015C94;
  color: #fff;
  cursor: pointer;
}
.user-dropdown-logout:hover {
  background: #024168;
}
.user-dropdown-version {
  font-size: 11px;
  color: #64748b;
}
.header-user.open #userMenuArrow,
#userMenuArrow.rotated {
  transform: rotate(180deg);
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}
</style>

<script>
(function () {
  'use strict';
  (function() {
  var toggleBtn   = document.getElementById('sidebarToggle');
  var sidebar     = document.getElementById('appSidebar');
  var overlay     = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar.classList.remove('collapsed');
    document.body.classList.remove('sidebar-collapsed');
    sidebar.setAttribute('aria-hidden', 'false');
  }

  function closeSidebar() {
    sidebar.classList.add('collapsed');
    document.body.classList.add('sidebar-collapsed');
    sidebar.setAttribute('aria-hidden', 'false');
  }

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function () {
      if (sidebar.classList.contains('collapsed')) {
        openSidebar();
      } else {
        closeSidebar();
      }
    });
  }

  })();
  const notifBtn  = document.getElementById('notifBtn');
  const notifDrop = document.getElementById('notifDropdown');
  if (notifBtn && notifDrop) {
    notifBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      document.getElementById('userDropdown').classList.remove('open');
      document.getElementById('userMenuArrow').style.transform = '';
      notifDrop.classList.toggle('open');
    });
    notifDrop.addEventListener('click', function (e) { e.stopPropagation(); });
  }
  const userBtn    = document.getElementById('userMenuBtn');
  const userDrop   = document.getElementById('userDropdown');
  const userArrow  = document.getElementById('userMenuArrow');
  if (userBtn && userDrop) {
    userBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (notifDrop) notifDrop.classList.remove('open');
      const isOpen = userDrop.classList.toggle('open');
      userArrow.style.transform = isOpen ? 'rotate(180deg)' : '';
      userBtn.setAttribute('aria-expanded', isOpen);
    });
    userDrop.addEventListener('click', function (e) { e.stopPropagation(); });
  }
  document.addEventListener('click', function () {
    if (notifDrop) notifDrop.classList.remove('open');
    if (userDrop)  {
      userDrop.classList.remove('open');
      if (userArrow) userArrow.style.transform = '';
      if (userBtn)   userBtn.setAttribute('aria-expanded', 'false');
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (notifDrop) notifDrop.classList.remove('open');
      if (userDrop)  {
        userDrop.classList.remove('open');
        if (userArrow) userArrow.style.transform = '';
      }
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      const s = document.getElementById('globalSearch');
      if (s) s.focus();
    }
  });
  const refreshBtn = document.getElementById('refreshBtn');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function () {
      this.style.animation = 'spin 0.6s linear';
      setTimeout(() => location.reload(), 200);
    });
  }
})();
</script>
