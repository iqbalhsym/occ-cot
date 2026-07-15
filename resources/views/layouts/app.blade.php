<!doctype html>
<html lang="id" class="dashboard-html">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Hospital Action Interface Care — COT Operation Command Center')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}" />
    <style>
      .autocomplete-wrapper { position: relative; display: inline-block; width: 100%; }
      .autocomplete-menu {
        position: absolute; top: 100%; left: 0; right: 0;
        background: var(--white); border: 1px solid var(--slate-200);
        border-radius: 4px; z-index: 100; max-height: 200px;
        overflow-y: auto; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: none;
      }
      .autocomplete-item { padding: 8px 12px; cursor: pointer; font-size: 13px; }
      .autocomplete-item:hover { background: var(--primary-50); color: var(--primary-700); }
      .toast { border-left: 4px solid var(--primary-400); }
      .toast.error { border-left-color: var(--red-500); }
      .toast.success { border-left-color: #4ADE80; }
    </style>
    @yield('styles')
  </head>
  <body class="dashboard-body">
    @php
      $currentUser = Auth::user();
      $activeRole  = session('role', $currentUser ? $currentUser->role : 'Viewer');
      $isViewer    = ($activeRole === 'Viewer');

      $rolesList = [
        ['id' => 'Nurse',       'label' => 'Nurse (Entry Point)'],
        ['id' => 'VA',          'label' => 'VA (Asuransi)'],
        ['id' => 'Kasir',       'label' => 'Kasir (Umum)'],
        ['id' => 'ADRUCOT',     'label' => 'ADRU COT (Umum)'],
        ['id' => 'Farmasi',     'label' => 'Farmasi'],
        ['id' => 'AdminCOT',    'label' => 'Admin COT'],
        ['id' => 'CaseManager', 'label' => 'Case Manager'],
        ['id' => 'CS',          'label' => 'Customer Service'],
        ['id' => 'Viewer',      'label' => 'Viewer (Hanya Lihat)'],
      ];

      // Queue count per active role
      $queueCount = 0;
      if ($activeRole !== 'Viewer') {
          $queueCount = \App\Models\OperationCase::getQueueQueryForRole($activeRole)->count();
      }
    @endphp

    <div id="app">
      {{-- ════════════════════════════════════
           SIDEBAR
      ════════════════════════════════════ --}}
      <aside class="sidebar">

        {{-- Brand & Logo --}}
        <div class="brand">
          <img src="{{ asset('logo-rsui-big.png') }}" alt="Logo RSUI" class="brand-logo">
          <div class="brand-text">
            <h1>Hospital Action Interface Care</h1>
            <p>COT Operation Command Center</p>
          </div>
        </div>

        {{-- Role selector (sembunyikan untuk Viewer) --}}
        @if($currentUser && !$isViewer)
          <div class="role-box">
            <label class="sidebar-label">Aktif sebagai role</label>
            <select id="roleSelect">
              @foreach($rolesList as $role)
                <option value="{{ $role['id'] }}" {{ $activeRole === $role['id'] ? 'selected' : '' }}>{{ $role['label'] }}</option>
              @endforeach
            </select>
          </div>
        @endif

        {{-- Navigation --}}
        <nav id="navList">
          {{-- Dashboard — semua user --}}
          <a href="{{ route('dashboard') }}" class="nav-item {{ Route::is('dashboard') ? 'active' : '' }}">
            <span>◆</span><span class="sidebar-label">Dashboard</span>
          </a>

          {{-- Buat Case Baru — semua user kecuali Viewer --}}
          @if(!$isViewer)
            <a href="{{ route('cases.create') }}" class="nav-item {{ Route::is('cases.create') ? 'active' : '' }}">
              <span>＋</span><span class="sidebar-label">Buat Case Baru</span>
            </a>
          @endif

          {{-- Antrian Saya — semua user kecuali Viewer --}}
          @if(!$isViewer)
            <a href="{{ route('cases.index') }}?queue=mine" class="nav-item {{ request()->query('queue') === 'mine' ? 'active' : '' }}">
              <span>➜</span><span class="sidebar-label">Antrian Saya</span>
              @if($queueCount > 0)
                <span class="badge">{{ $queueCount }}</span>
              @endif
            </a>
          @endif

          {{-- Semua Case — semua user --}}
          <a href="{{ route('cases.index') }}" class="nav-item {{ Route::is('cases.index') && request()->query('queue') !== 'mine' ? 'active' : '' }}">
            <span>≡</span><span class="sidebar-label">Semua Case</span>
          </a>

          {{-- User & Doctor Management — SuperAdmin & Administrator --}}
          @if($currentUser && in_array($currentUser->role, ['SuperAdmin','Administrator']))
            <a href="{{ route('admin.users') }}" class="nav-item {{ Route::is('admin.users') ? 'active' : '' }}">
              <span>◆</span><span class="sidebar-label">User Management</span>
            </a>
            <a href="{{ route('admin.doctors') }}" class="nav-item {{ Route::is('admin.doctors') ? 'active' : '' }}">
              <span>🩺</span><span class="sidebar-label">Dokter Management</span>
            </a>
          @endif

          {{-- Role & Status Reference — semua user --}}
          <a href="{{ route('roles.reference') }}" class="nav-item {{ Route::is('roles.reference') ? 'active' : '' }}">
            <span>⚙</span><span class="sidebar-label">Role & Status</span>
          </a>
        </nav>

        {{-- Sidebar Footer: profil + logout --}}
        <div class="sidebar-foot">
          @if($currentUser)
            <div class="sidebar-foot-text" style="margin-bottom:10px; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:8px;">
              <div style="font-size:10px; color:rgba(255,255,255,0.45); margin-bottom:2px;">Logged in as</div>
              <div style="font-weight:700; font-size:12.5px; color:var(--white);">{{ $currentUser->name }}</div>
              <div style="font-size:10px; color:rgba(255,255,255,0.50);">{{ $currentUser->username }} &middot; {{ $currentUser->role }}</div>
            </div>
            <div class="sidebar-foot-text">
              <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" style="width:100%; background:rgba(220,38,38,0.15); border:1px solid rgba(220,38,38,0.3); color:#FCA5A5; border-radius:4px; padding:6px; font-size:11px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;">
                  Logout
                </button>
              </form>
            </div>
          @endif
          <div class="sidebar-foot-text" style="margin-top:8px; font-size:10px; color:rgba(255,255,255,0.30); line-height:1.4;">
            COT OCC RSUI &copy; {{ date('Y') }}
          </div>
        </div>
      </aside>

      {{-- ════════════════════════════════════
           MAIN AREA
      ════════════════════════════════════ --}}
      <main>
        <div class="topbar">
          <div style="display:flex; align-items:center; gap:10px; min-width:0;">
            <button id="sidebarToggle" title="Buka/Tutup Sidebar" style="flex-shrink:0; width:34px; height:34px; display:flex; align-items:center; justify-content:center; background:var(--slate-50); border:1.5px solid var(--slate-200); border-radius:6px; cursor:pointer; transition:background 0.2s, border-color 0.2s; color:var(--primary-800);">
              <!-- Ikon sidebar toggle: diganti via JS -->
              <svg id="toggleIconOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                <rect x="3" y="3" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/>
                <line x1="9" y1="3" x2="9" y2="21" stroke="currentColor" stroke-width="1.8"/>
                <polyline points="13,9 17,12 13,15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <svg id="toggleIconClose" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="display:none;">
                <rect x="3" y="3" width="18" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/>
                <line x1="9" y1="3" x2="9" y2="21" stroke="currentColor" stroke-width="1.8"/>
                <polyline points="16,9 12,12 16,15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <h2 id="pageTitle">@yield('page_title', 'Dashboard')</h2>
          </div>
          <div class="topbar-right">
            @if($currentUser && $isViewer)
              <span style="background:var(--accent-100); color:var(--accent-600); border:1px solid var(--accent-300); font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px;">
                Mode Viewer
              </span>
            @endif
            <span class="clock" id="clock"></span>
          </div>
        </div>

        {{-- Flash messages --}}
        @if(session('success'))
          <div style="background:var(--green-100); border-left:4px solid var(--green-600); color:var(--green-600); padding:10px 16px; font-size:13px; display:flex; align-items:center; gap:8px;">
            ✓ {{ session('success') }}
          </div>
        @endif
        @if(session('error'))
          <div style="background:var(--red-100); border-left:4px solid var(--red-500); color:var(--red-500); padding:10px 16px; font-size:13px; display:flex; align-items:center; gap:8px;">
            ✗ {{ session('error') }}
          </div>
        @endif

        <div class="content" id="content">
          @yield('content')
        </div>
      </main>
    </div>

    <div class="toast-wrap" id="toastWrap"></div>

    <script src="{{ asset('js/cotdb.js') }}"></script>
    <script>
      // ── Live Clock ──
      function updateClock() {
        const el = document.getElementById("clock");
        if (!el) return;
        const now   = new Date();
        const days  = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
        const months= ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
        const hrs   = String(now.getHours()).padStart(2,'0');
        const mins  = String(now.getMinutes()).padStart(2,'0');
        const secs  = String(now.getSeconds()).padStart(2,'0');
        el.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} ${hrs}:${mins}:${secs}`;
      }
      setInterval(updateClock, 1000);
      updateClock();

      // ── Toast Notifications ──
      function toast(msg, type = "success") {
        const wrap = document.getElementById("toastWrap");
        if (!wrap) return;
        const el = document.createElement("div");
        el.className = `toast ${type === 'error' ? 'warn' : 'ok'}`;
        el.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red-500)' : '#4ADE80'}`;
        el.innerHTML = `<span>${type === 'error' ? '✗' : '✓'}</span> <span>${msg}</span>`;
        wrap.appendChild(el);
        setTimeout(() => { el.style.opacity = "0"; el.style.transform = "translateY(10px)"; setTimeout(() => el.remove(), 300); }, 3500);
      }

      // ── CSRF ──
      const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

      // ── Sidebar Toggle ──
      const app         = document.getElementById('app');
      const toggleBtn   = document.getElementById('sidebarToggle');
      const iconOpen    = document.getElementById('toggleIconOpen');
      const iconClose   = document.getElementById('toggleIconClose');
      const SIDEBAR_KEY = 'occ_sidebar_open';

      function applySidebarState(open) {
        if (open) {
          app.classList.remove('sidebar-collapsed');
          if (iconOpen)  iconOpen.style.display  = 'none';
          if (iconClose) iconClose.style.display = 'block';
          if (toggleBtn) toggleBtn.title = 'Tutup Sidebar';
        } else {
          app.classList.add('sidebar-collapsed');
          if (iconOpen)  iconOpen.style.display  = 'block';
          if (iconClose) iconClose.style.display = 'none';
          if (toggleBtn) toggleBtn.title = 'Buka Sidebar';
        }
      }

      // Restore saved state (default: open)
      const savedOpen = localStorage.getItem(SIDEBAR_KEY);
      applySidebarState(savedOpen === null ? true : savedOpen === '1');

      if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
          const isCollapsed = app.classList.contains('sidebar-collapsed');
          const newOpen = isCollapsed; // toggling: if collapsed, now open
          localStorage.setItem(SIDEBAR_KEY, newOpen ? '1' : '0');
          applySidebarState(newOpen);
        });
      }

      // ── Role Select ──
      const roleSelectEl = document.getElementById('roleSelect');
      if (roleSelectEl) {
        roleSelectEl.addEventListener('change', function() {
          fetch('{{ url("/set-role") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ role: this.value })
          })
          .then(res => res.json())
          .then(data => { if (data.success) window.location.reload(); });
        });
      }

      // ── Autocomplete helper ──
      function makeAutocomplete(inputEl, suggestions, onPick = null) {
        let activeIdx = -1;
        const wrapper = document.createElement("div");
        wrapper.className = "autocomplete-wrapper";
        inputEl.parentNode.insertBefore(wrapper, inputEl);
        wrapper.appendChild(inputEl);
        const menu = document.createElement("div");
        menu.className = "autocomplete-menu";
        wrapper.appendChild(menu);

        function closeMenu() { menu.style.display = "none"; activeIdx = -1; }

        inputEl.addEventListener("input", function() {
          const val = this.value.trim().toLowerCase();
          if (!val) { closeMenu(); return; }
          const filtered = suggestions.filter(x => x.toLowerCase().includes(val)).slice(0, 15);
          if (!filtered.length) { closeMenu(); return; }
          menu.innerHTML = "";
          filtered.forEach(item => {
            const itemEl = document.createElement("div");
            itemEl.className = "autocomplete-item";
            itemEl.textContent = item;
            itemEl.addEventListener("click", function() { inputEl.value = item; closeMenu(); if (onPick) onPick(item); });
            menu.appendChild(itemEl);
          });
          menu.style.display = "block";
        });
        document.addEventListener("click", function(e) { if (e.target !== inputEl && e.target !== menu) closeMenu(); });
      }
    </script>
    @yield('scripts')
  </body>
</html>
