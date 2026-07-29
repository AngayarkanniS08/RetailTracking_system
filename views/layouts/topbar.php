    <header class="top-bar">
      <div class="logo-area" style="margin:0; display:flex; align-items:center; gap:10px;">
        <label for="sidebarToggle" class="mobile-hamburger-btn" title="Toggle Navigation Menu">☰</label>
        <div class="logo-icon" style="background: white; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; padding: 2px; width: 28px; height: 28px; flex-shrink:0;">
          <img src="/public/assets/images/logo.png" alt="Logo" style="width: 20px; height: 20px; object-fit: contain;">
        </div>
        <div class="logo-text" style="font-weight: 700; font-size: 1rem; color: var(--text-strong);">Pudheera Retail <span style="font-size: 0.75rem; color:var(--muted); font-weight: 400; font-family:var(--font-body); background: var(--surface-container-low); padding: 2px 6px; border-radius: 4px; margin-left: 4px;">v1.0</span></div>
      </div>


      <div class="user-menu" style="display:flex; align-items:center; gap: 12px;">
        <!-- Topbar Theme Switcher Pill -->
        <div class="topbar-theme-pill">
          <button class="topbar-theme-btn" data-theme="light" title="Switch to Light Mode">
            <span>☀️</span> Light
          </button>
          <button class="topbar-theme-btn active" data-theme="dark" title="Switch to Dark Mode">
            <span>🌙</span> Dark
          </button>
        </div>

        <!-- 🔔 Notification Icon with Badge -->
        <div class="topbar-alert-icon" id="topbarAlertIcon" onclick="openActiveAlertsModal()" title="Alerts & Notifications">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span id="topbarAlertBadge" class="topbar-alert-badge">3</span>
        </div>

        <!-- User Avatar -->
        <div class="avatar" title="Admin Account">A</div>

        <!-- Logout Button -->
        <a href="javascript:void(0)" onclick="logoutUser()" class="btn btn-outline btn-sm" style="border-color: var(--border-strong); padding: 6px 14px; font-weight: 500;">Logout</a>
      </div>
    </header>
