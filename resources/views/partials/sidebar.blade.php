
<aside class="sidebar" id="appSidebar" role="navigation" aria-label="Main Navigation" aria-hidden="true">
  <div class="sidebar-logo-area">
    <img src="{{ asset('images/images.jpg') }}"
         alt="NCCIA Logo"
         class="sidebar-logo-circle">
  </div>
  <div class="sidebar-brand-text">
    <div class="brand-name"><b>NCCIA</b></div>
    <div class="brand-sub"><b>National Cyber Crime Investigation Agency</b></div>
  </div>
  <nav class="sidebar-nav" aria-label="Sidebar Navigation">
    <div class="nav-section-label">Main</div>
    <div class="nav-item">
      <a href="{{ route('dashboard') }}" class="nav-link active" data-page="dashboard" data-tooltip="Dashboard">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 12h2l3-9 3 9h2M3 12v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6"/>
            <circle cx="12" cy="16" r="1"/>
          </svg>
        </span>
        <span>Dashboard</span>
      </a>
    </div>
    <div class="nav-section-label">Analytics</div>
    <div class="nav-item">
      <a href="#analytics" class="nav-link parent-link" id="analyticsToggle">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 12v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-3"/>
            <path d="M12 2v8l3-3-3-3-3 3 3 3z"/>
          </svg>
        </span>
        <span>Analytics</span>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:auto;transition:transform 0.25s;" id="analyticsArrow">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </a>
      <div class="nav-submenu" id="analyticsSubmenu">
        <div class="nav-item">
          <a href="#pendency" class="nav-link" data-page="pendency">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12h4l3-9 3 9h4"/><path d="M2 13v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5"/><circle cx="12" cy="17" r="1"/></svg></span>
            <span>Pendency Analysis</span>
          </a>
        </div>
        <div class="nav-item">
          <a href="#performance" class="nav-link" data-page="performance">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
            <span>Performance Report</span>
          </a>
        </div>
        <div class="nav-item">
          <a href="#category-report" class="nav-link" data-page="category-report">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
            <span>Category Report</span>
          </a>
        </div>
      </div>
    </div>
    <div class="nav-section-label">Complaints</div>

    <div class="nav-item">
      <a href="{{ route('verifications.index') }}" class="nav-link" data-page="verifications" data-tooltip="Verifications">
        <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
        <span>Verifications</span>
        <span class="nav-badge urgent">1,337</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="{{ route('verifications.reports') }}" class="nav-link" data-page="verifications" data-tooltip="Verifications">
        <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
        <span>Verifications Reports</span>
        <span class="nav-badge urgent">1,337</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="{{ route('newenquiry') }}" class="nav-link" data-page="enquiries" data-tooltip="Enquiries">
        <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
        <span>Enquiries</span>
        <span class="nav-badge">48</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="{{ route('investigation-officers.index') }}" class="nav-link" data-page="io-records" data-tooltip="IO Records">
        <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
        <span>IO Records</span>
      </a>
    </div>
    <div class="nav-item">
      <a href="#dac" class="nav-link parent-link" id="dacToggle">
        <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
        <span>DAC Cases</span>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:auto;transition:transform 0.25s;" id="dacArrow">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </a>
      <div class="nav-submenu" id="dacSubmenu">
        <div class="nav-item"><a href="#dac-new" class="nav-link" data-page="dac-new"><span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></span><span>New Case</span></a></div>
        <div class="nav-item"><a href="#dac-pending" class="nav-link" data-page="dac-pending"><span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span><span>Pending Cases</span></a></div>
        <div class="nav-item"><a href="#dac-closed" class="nav-link" data-page="dac-closed"><span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg></span><span>Closed Cases</span></a></div>
      </div>
    </div>
    <div class="nav-section-label">Reference</div>
    <div class="nav-item"><a href="{{ route('offence-types.index') }}" class="nav-link" data-page="offence-types"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span><span>Crime Categories</span></a></div>
    <div class="nav-item"><a href="#laws" class="nav-link" data-page="laws"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/></svg></span><span>Laws</span></a></div>
    <div class="nav-item"><a href="#rules" class="nav-link" data-page="rules"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg></span><span>Rules & Regulations</span></a></div>
    <div class="nav-item"><a href="#sop" class="nav-link" data-page="sop"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span><span>SOP</span></a></div>
    <div class="nav-item"><a href="#user-manual" class="nav-link" data-page="user-manual"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></span><span>User Manual</span></a></div>
    <div class="nav-section-label">Account</div>
    <div class="nav-item"><a href="#profile" class="nav-link" data-page="profile"><span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg></span><span>My Profile</span></a></div>
    <div class="nav-item">
      <a href="#" class="nav-link" style="color:#fff;" id="logoutBtn" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
        <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
        <span>Logout</span>
      </a>
      <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">@csrf</form>
    </div>
  </nav>
  <div class="sidebar-footer">
    <a href="#help" class="help-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>Help &amp; Support</span>
    </a>
  </div>
</aside>

<script>
(function() {
  function makeSubmenuToggle(toggleId, submenuId, arrowId) {
    const toggle = document.getElementById(toggleId);
    const submenu = document.getElementById(submenuId);
    const arrow = document.getElementById(arrowId);
    if (!toggle || !submenu) return;
    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      const open = submenu.classList.toggle('open');
      if (arrow) arrow.style.transform = open ? 'rotate(180deg)' : '';
    });
  }
  makeSubmenuToggle('analyticsToggle', 'analyticsSubmenu', 'analyticsArrow');
  makeSubmenuToggle('dacToggle', 'dacSubmenu', 'dacArrow');

  // Start open by default; only toggle button closes it
  document.getElementById('appSidebar')?.classList.add('open');

  const navLinks = document.querySelectorAll('.sidebar-nav .nav-link[data-page]');
  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      document.querySelectorAll('.sidebar-nav .nav-link[data-page]').forEach(l => l.classList.remove('active'));
      link.classList.add('active');
    });
  });
})();
</script>
