<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="NCCIA Case Management System — National Cyber Crime Investigation Agency, Pakistan">
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%230a1c5c'/><text x='16' y='22' text-anchor='middle' font-size='13' font-weight='bold' fill='%23c9a84c' font-family='serif'>NC</text></svg>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <title>@yield('title', 'Dashboard') — NCCIA Case Management System</title>

  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%230a3d2e'/><text x='16' y='22' text-anchor='middle' font-size='14' font-weight='bold' fill='%23c9a84c' font-family='serif'>NC</text></svg>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/form.css') }}">
  <style>@keyframes slideInToast{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}</style>
  <script>
    (function() {
      document.addEventListener('contextmenu', function(e) { e.preventDefault(); return false; }, { capture: true });
      document.addEventListener('keydown', function(e) {
        if (e.keyCode === 123 || e.key === 'F12') { e.preventDefault(); e.stopPropagation(); return false; }
        if (e.ctrlKey && e.shiftKey && ['I', 'i', 'J', 'j', 'C', 'c'].indexOf(e.key) !== -1) { e.preventDefault(); e.stopPropagation(); return false; }
        if (e.ctrlKey && (e.key === 'U' || e.key === 'u' || e.key === 'S' || e.key === 's')) { e.preventDefault(); e.stopPropagation(); return false; }
      }, { capture: true });
    })();
  </script>
  @stack('extra_css')
</head>
<body>
  <div class="app-wrapper">
    @include('partials.sidebar')
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="main-content" id="mainContent">
      @include('partials.header')
      <main class="page-content" id="pageContent" role="main">
        @yield('page_content')
      </main>
      @include('partials.footer')
    </div>
  </div>

  <script src="{{ asset('js/main.js') }}"></script>
  <script>
  (function() {
    'use strict';
    function setActivePage() {
      const hash = window.location.hash.replace('#', '') || 'dashboard';
      document.querySelectorAll('.nav-link[data-page]').forEach(link => {
        link.classList.toggle('active', link.dataset.page === hash);
      });
      const page = document.getElementById('breadcrumbPage');
      if (page) {
        const label = hash.replace(/-/g,' ').replace(/\b\w/g, c => c.toUpperCase());
        page.textContent = label;
      }
    }
    window.addEventListener('hashchange', setActivePage);
    setActivePage();
    const today = new Date().toISOString().split('T')[0];
    document.querySelectorAll('input[type="date"]').forEach(el => {
      if (!el.getAttribute('max')) el.setAttribute('max', today);
    });
    function animateCounters() {
      document.querySelectorAll('.stat-value').forEach(el => {
        const raw = el.textContent.replace(/,/g,'');
        const target = parseFloat(raw);
        if (isNaN(target)) return;
        const isFloat = raw.includes('.');
        const decimals = isFloat ? (raw.split('.')[1] || '').length : 0;
        const duration = 800;
        const steps = 40;
        const increment = target / steps;
        let current = 0;
        let step = 0;
        const timer = setInterval(() => {
          step++;
          current += increment;
          if (step >= steps) {
            current = target;
            clearInterval(timer);
          }
          el.textContent = isFloat
            ? current.toFixed(decimals)
            : Math.round(current).toLocaleString();
        }, duration / steps);
      });
    }
    setTimeout(animateCounters, 200);
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        exportBtn.textContent = '⏳ Generating…';
        exportBtn.disabled = true;
        setTimeout(() => {
          exportBtn.textContent = '✓ Exported!';
          setTimeout(() => {
            exportBtn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export`;
            exportBtn.disabled = false;
          }, 2000);
        }, 1500);
      });
    }
    const applyBtn = document.getElementById('applyFilterBtn');
    if (applyBtn) {
      applyBtn.addEventListener('click', () => {
        applyBtn.textContent = 'Loading…';
        applyBtn.disabled = true;
        setTimeout(() => {
          applyBtn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg> Apply`;
          applyBtn.disabled = false;
        }, 900);
      });
    }
    document.querySelectorAll('.priority-dots').forEach(el => {
      const filled = el.querySelectorAll('.priority-dot.filled').length;
      const map = { 3: 'High Priority', 2: 'Medium Priority', 1: 'Low Priority' };
      el.setAttribute('title', map[filled] || 'No Priority');
    });
  })();
  </script>
  @stack('extra_js')
</body>
</html>
