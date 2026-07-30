<div class="view-section active" id="backup">

  <!-- ═══════════════════════════════════════════════════════
       1. EXECUTIVE TOPBAR
       ═══════════════════════════════════════════════════════ -->
  <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
    <div>
      <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
        <h1 style="font-size:1.45rem; font-weight:700; color:var(--text-strong); margin:0; letter-spacing:-0.02em;">Backup Operations Center</h1>
        <span class="badge-emerald-sm" id="bkpHealthBadge">🟢 Protection Active</span>
      </div>
      <div style="display:flex; align-items:center; gap:8px; font-size:0.8rem; color:var(--muted);">
        <span class="bkp-live-dot"></span>
        <span>Monitoring active</span>
        <span>•</span>
        <span id="bkpLastRefreshed">Just now</span>
      </div>
    </div>
    <div style="display:flex; gap:10px;">
      <button class="btn btn-outline btn-sm" onclick="connectGoogleDrive()" style="height:38px; padding:0 14px; font-size:0.84rem; font-weight:600;">
        ☁️ Google Drive
      </button>
      <button class="bkp-op-btn primary" id="backupNowBtn" onclick="startBackup()" style="grid-column:auto; padding:0 20px; height:38px; border-radius:var(--radius-md); font-size:0.88rem; flex-direction:row; gap:8px;">
        💾 Backup Now
      </button>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════
       2. ROW 1 — 4 KPI STATUS CARDS (Status, Last, Next, Storage)
       ═══════════════════════════════════════════════════════ -->
  <div class="bkp-grid" style="margin-bottom:1.25rem;">

    <!-- Protection Status -->
    <div class="bkp-kpi bkp-col-3" style="border-left-color:#10b981;" id="kpiProtectionCard">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
        <div class="bkp-kpi-label">Protection Status</div>
        <span style="font-size:1.1rem;">🛡️</span>
      </div>
      <div class="bkp-kpi-val" id="kpiProtectionVal">Healthy</div>
      <div class="bkp-kpi-sub" id="kpiProtectionSub">All backup systems operational</div>
    </div>

    <!-- Last Backup -->
    <div class="bkp-kpi bkp-col-3" style="border-left-color:#3b82f6;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
        <div class="bkp-kpi-label">Last Backup</div>
        <span id="kpiLastBadge" class="badge-emerald-sm">✓ Success</span>
      </div>
      <div class="bkp-kpi-val" id="kpiLastTime" style="font-size:1.15rem;">Never</div>
      <div class="bkp-kpi-sub" id="kpiLastFile">No backups recorded yet</div>
    </div>

    <!-- Next Scheduled -->
    <div class="bkp-kpi bkp-col-3" style="border-left-color:#8b5cf6;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
        <div class="bkp-kpi-label">Next Scheduled</div>
        <span style="font-size:1.1rem;">⏰</span>
      </div>
      <div class="bkp-kpi-val" id="kpiNextTime">22:00 Daily</div>
      <div class="bkp-kpi-sub" id="kpiScheduleSub">Schedule enabled</div>
    </div>

    <!-- Google Drive Storage -->
    <div class="bkp-kpi bkp-col-3" style="border-left-color:#06b6d4;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
        <div class="bkp-kpi-label">Cloud Storage</div>
        <span style="font-size:1.1rem;">☁️</span>
      </div>
      <div class="bkp-kpi-val" id="kpiDriveStatus" style="font-size:1.1rem;">Not Connected</div>
      <div class="bkp-kpi-sub" id="kpiDriveSub">Click ☁️ to connect Google Drive</div>
    </div>

  </div>

  <!-- ═══════════════════════════════════════════════════════
       3. ROW 2 — Operations (6-col) + Recent Activity (6-col)
       ═══════════════════════════════════════════════════════ -->
  <div class="bkp-grid" style="margin-bottom:1.25rem;">

    <!-- Operations Panel -->
    <div class="bkp-card bkp-col-6">
      <div class="bkp-card-header">
        <div class="bkp-card-title">⚡ Backup Operations</div>
        <span class="bkp-card-meta">One-click actions</span>
      </div>

      <!-- Primary CTA -->
      <button class="bkp-op-btn primary" id="backupNowBtn2" onclick="startBackup()" style="width:100%; margin-bottom:0.75rem;">
        <span class="op-icon">💾</span>
        <span>Backup Now — Dump, compress &amp; upload to Google Drive</span>
      </button>

      <div class="bkp-ops-grid">
        <button class="bkp-op-btn" onclick="loadRestoreFiles()">
          <span class="op-icon">🔄</span>
          <span>Restore Database</span>
        </button>
        <button class="bkp-op-btn" onclick="verifyLatestBackup()">
          <span class="op-icon">✅</span>
          <span>Verify Backup</span>
        </button>
        <button class="bkp-op-btn" onclick="downloadLatestBackup()">
          <span class="op-icon">⬇️</span>
          <span>Download Latest</span>
        </button>
        <button class="bkp-op-btn" onclick="openConfigPanel()">
          <span class="op-icon">⚙️</span>
          <span>Configure Schedule</span>
        </button>
      </div>

      <!-- Google Drive Status -->
      <div id="bkpDrivePanel" class="bkp-drive-card disconnected" style="margin-top:0.85rem;">
        <div class="bkp-drive-icon">☁️</div>
        <div style="flex:1; min-width:0;">
          <div style="font-weight:700; font-size:0.85rem; color:var(--text-strong);" id="driveStatusTitle">Google Drive</div>
          <div style="font-size:0.78rem; color:var(--muted); margin-top:2px;" id="driveStatusText">Checking connection...</div>
        </div>
        <button class="btn btn-xs btn-outline" onclick="connectGoogleDrive()" id="connectDriveBtn" style="flex-shrink:0;">Connect</button>
      </div>
    </div>

    <!-- Recent Activity / Job Stream -->
    <div class="bkp-card bkp-col-6">
      <div class="bkp-card-header">
        <div class="bkp-card-title">📋 Recent Activity</div>
        <span class="bkp-card-meta">Last 7 events</span>
      </div>
      <div id="bkpActivityFeed" style="display:flex; flex-direction:column; gap:6px; max-height:260px; overflow-y:auto;">
        <div style="text-align:center; padding:2rem; color:var(--muted); font-size:0.83rem;">
          Loading activity stream...
        </div>
      </div>
    </div>

  </div>

  <!-- ═══════════════════════════════════════════════════════
       4. ROW 3 — Backup History Table (8-col) + Stats (4-col)
       ═══════════════════════════════════════════════════════ -->
  <div class="bkp-grid" style="margin-bottom:1.25rem;">

    <!-- Backup History Table -->
    <div class="bkp-card bkp-col-8">
      <div class="bkp-card-header">
        <div class="bkp-card-title">📂 Backup File History</div>
        <span class="bkp-card-meta" id="bkpFileCount">Loading...</span>
      </div>
      <div style="overflow-x:auto;">
        <table class="bkp-history-table">
          <colgroup>
            <col style="width:36%;">
            <col style="width:18%;">
            <col style="width:16%;">
            <col style="width:16%;">
            <col style="width:14%;">
          </colgroup>
          <thead>
            <tr>
              <th>Filename</th>
              <th>Date</th>
              <th>Size</th>
              <th>Status</th>
              <th style="text-align:right;">Action</th>
            </tr>
          </thead>
          <tbody id="bkpHistoryTableBody">
            <tr>
              <td colspan="5" style="text-align:center; padding:2rem; color:var(--muted); font-size:0.83rem;">
                Loading backup history...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Statistics Panel -->
    <div class="bkp-card bkp-col-4">
      <div class="bkp-card-header">
        <div class="bkp-card-title">📊 Statistics</div>
      </div>

      <!-- 4 mini stats -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
        <div style="padding:0.75rem; border-radius:var(--radius-md); border:1px solid var(--border); background:var(--surface-container-low);">
          <div class="bkp-kpi-label">Total Backups</div>
          <div style="font-size:1.4rem; font-weight:700; color:var(--text-strong); font-variant-numeric:tabular-nums;" id="statTotal">—</div>
        </div>
        <div style="padding:0.75rem; border-radius:var(--radius-md); border:1px solid var(--border); background:var(--surface-container-low);">
          <div class="bkp-kpi-label">Success Rate</div>
          <div style="font-size:1.4rem; font-weight:700; color:#10b981; font-variant-numeric:tabular-nums;" id="statRate">—</div>
        </div>
        <div style="padding:0.75rem; border-radius:var(--radius-md); border:1px solid var(--border); background:var(--surface-container-low);">
          <div class="bkp-kpi-label">Avg Size</div>
          <div style="font-size:1.2rem; font-weight:700; color:var(--text-strong); font-variant-numeric:tabular-nums;" id="statAvgSize">—</div>
        </div>
        <div style="padding:0.75rem; border-radius:var(--radius-md); border:1px solid var(--border); background:var(--surface-container-low);">
          <div class="bkp-kpi-label">Latest Size</div>
          <div style="font-size:1.2rem; font-weight:700; color:var(--text-strong); font-variant-numeric:tabular-nums;" id="statLatestSize">—</div>
        </div>
      </div>

      <!-- 7-Day Success Heatmap -->
      <div style="font-size:0.72rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">7-Day Backup Calendar</div>
      <div id="bkpWeekHeatmap" style="display:flex; gap:5px; flex-wrap:wrap;">
        <!-- Populated by JS -->
      </div>
    </div>

  </div>

  <!-- ═══════════════════════════════════════════════════════
       5. ROW 4 — Schedule (6-col) + Retention Policy (6-col)
       ═══════════════════════════════════════════════════════ -->
  <div class="bkp-grid" id="bkpConfigRow" style="margin-bottom:1.25rem; display:none;">

    <!-- Schedule Configuration -->
    <div class="bkp-card bkp-col-6">
      <div class="bkp-card-header">
        <div class="bkp-card-title">⏰ Schedule Configuration</div>
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.83rem; font-weight:600; color:var(--text-strong);">
          <input type="checkbox" id="scheduleEnabled" style="width:16px; height:16px; cursor:pointer;">
          Enable Auto-Backup
        </label>
      </div>

      <div class="bkp-settings-grid" style="margin-bottom:0.85rem;">
        <div class="bkp-setting-item">
          <label class="bkp-setting-label" for="scheduleTime">Backup Time</label>
          <input type="time" id="scheduleTime" class="input-field" value="22:00" style="font-size:0.9rem; padding:0.55rem 0.75rem;">
        </div>
        <div class="bkp-setting-item">
          <label class="bkp-setting-label" for="gdriveFolderId">Drive Folder ID</label>
          <input type="text" id="gdriveFolderId" class="input-field" placeholder="From Google Drive URL" style="font-size:0.85rem; padding:0.55rem 0.75rem;">
        </div>
      </div>

      <div style="font-size:0.75rem; color:var(--muted); margin-bottom:0.85rem; padding:0.6rem 0.8rem; border-radius:var(--radius-sm); background:var(--surface-container-low); border:1px solid var(--border);">
        ℹ️ Scheduled backups run on the server even when you're not logged in.
      </div>

      <div style="display:flex; gap:8px;">
        <button class="btn btn-primary btn-sm" onclick="saveBackupConfig()">Save Schedule</button>
        <button class="btn btn-outline btn-sm" onclick="connectGoogleDrive()">Reconnect Drive</button>
      </div>
    </div>

    <!-- Retention Policy -->
    <div class="bkp-card bkp-col-6">
      <div class="bkp-card-header">
        <div class="bkp-card-title">🗂️ Retention Policy</div>
        <span class="bkp-card-meta">Automatic pruning</span>
      </div>

      <div class="bkp-retention-row">
        <div>
          <div style="font-weight:600; font-size:0.85rem; color:var(--text-strong);">Daily Backups</div>
          <div style="font-size:0.75rem; color:var(--muted);">Keep last N daily backups</div>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
          <input type="number" id="retentionDaily" class="input-field" value="7" min="1" max="30"
                 style="width:70px; text-align:center; font-size:0.9rem; padding:0.4rem 0.5rem; font-weight:700;">
          <span style="font-size:0.78rem; color:var(--muted);">days</span>
        </div>
      </div>

      <div class="bkp-retention-row">
        <div>
          <div style="font-weight:600; font-size:0.85rem; color:var(--text-strong);">Weekly Backups</div>
          <div style="font-size:0.75rem; color:var(--muted);">Keep last N weekly backups</div>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
          <input type="number" id="retentionWeekly" class="input-field" value="4" min="0" max="12"
                 style="width:70px; text-align:center; font-size:0.9rem; padding:0.4rem 0.5rem; font-weight:700;">
          <span style="font-size:0.78rem; color:var(--muted);">weeks</span>
        </div>
      </div>

      <div class="bkp-retention-row">
        <div>
          <div style="font-weight:600; font-size:0.85rem; color:var(--text-strong);">Monthly Backups</div>
          <div style="font-size:0.75rem; color:var(--muted);">Keep last N monthly backups</div>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
          <input type="number" id="retentionMonthly" class="input-field" value="12" min="0" max="24"
                 style="width:70px; text-align:center; font-size:0.9rem; padding:0.4rem 0.5rem; font-weight:700;">
          <span style="font-size:0.78rem; color:var(--muted);">months</span>
        </div>
      </div>

      <div style="margin-top:0.85rem; padding-top:0.75rem; border-top:1px solid var(--border);">
        <button class="btn btn-outline btn-sm" onclick="saveBackupConfig()" style="font-weight:600;">
          Save Retention Policy
        </button>
      </div>
    </div>

  </div>

  <!-- ═══════════════════════════════════════════════════════
       6. Config toggle link
       ═══════════════════════════════════════════════════════ -->
  <div style="text-align:center; margin-bottom:1rem;">
    <button class="btn btn-ghost btn-sm" id="bkpConfigToggleBtn" onclick="toggleConfigPanel()" style="font-size:0.8rem; color:var(--muted);">
      ⚙️ Show Advanced Configuration
    </button>
  </div>

</div>

<script src="/public/assets/js/backup/backup-constants.js?v=<?= time(); ?>"></script>
<script src="/public/assets/js/backup/backup-events.js?v=<?= time(); ?>"></script>
<script src="/public/assets/js/backup/backup-logger.js?v=<?= time(); ?>"></script>
<script src="/public/assets/js/backup/backup-repository.js?v=<?= time(); ?>"></script>
<script src="/public/assets/js/backup/backup-service.js?v=<?= time(); ?>"></script>
<script src="/public/assets/js/backup/backup-renderer.js?v=<?= time(); ?>"></script>
<script src="/public/assets/js/backup/backup-poller.js?v=<?= time(); ?>"></script>
<script src="/public/assets/js/backup/backup-controller.js?v=<?= time(); ?>"></script>
<script src="/public/assets/js/backup.js?v=<?= time(); ?>"></script>
