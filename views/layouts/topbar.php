    <header class="top-bar">
      <div class="logo-area" style="margin:0;">
        <div class="logo-icon"
          style="background: white; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; padding: 2px;">
          <img src="public/assets/images/logo.png" alt="Logo" style="width: 20px; height: 20px; object-fit: contain;">
        </div>
        <div class="logo-text">Pudheera Retail <span
            style="font-size: 0.8rem; color:var(--muted); font-family:var(--font-body)">v1.0</span></div>
      </div>
      <div class="user-menu">
        <div class="user-menu">
        <!-- 🔔 Notification Icon -->
        <div class="topbar-alert-icon" id="topbarAlertIcon" onclick="openActiveAlertsModal()" style="cursor: pointer; position: relative; margin-right: 15px; display: flex; align-items: center; padding: 8px; border-radius: 50%; transition: background 0.2s;" onmouseover="this.style.background='var(--bg-elevated)'" onmouseout="this.style.background='transparent'">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--text-muted);">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <span id="topbarAlertBadge" style="position: absolute; top: 6px; right: 6px; background: var(--danger); border-radius: 50%; width: 8px; height: 8px; border: 1px solid var(--bg-card); display: none;"></span>
        </div>
        <div class="avatar">A</div>
        <a href="index.php?action=logout" class="btn btn-outline">Logout</a>
      </div>
      
    </header>
