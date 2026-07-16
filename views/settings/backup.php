<div class="view-section active" id="backup">
  <div style="margin-bottom: 2rem;">
    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem;">Backup & Restore</h2>
    <p style="color: var(--muted); font-size: 0.9rem;">Manage database backups with Google Drive storage</p>
  </div>

  <!-- Status Bar -->
  <div id="backupStatusBar" style="margin-bottom: 1.5rem; display:none;">
    <div style="padding: 1rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-elevated);">
      <div style="display:flex; align-items:center; justify-content:space-between;">
        <div>
          <div style="font-size: 0.8rem; color: var(--muted);">Last Backup</div>
          <div id="lastBackupInfo" style="font-weight: 600;">Never</div>
        </div>
        <div id="lastBackupStatusBadge" style="padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600;"></div>
      </div>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <!-- LEFT: Backup -->
    <div class="card-panel">
      <div style="display:flex; align-items:center; gap:12px; margin-bottom: 1.5rem;">
        <div style="font-size: 2rem;">💾</div>
        <div>
          <div style="font-weight: 700; font-size: 1.1rem;">Backup Now</div>
          <div style="font-size: 0.8rem; color: var(--muted);">Dump, compress, and upload to Google Drive</div>
        </div>
      </div>

      <button class="btn btn-primary btn-block" id="backupNowBtn" onclick="startBackup()" style="padding: 1rem; font-size: 1.05rem;">
        Start Backup
      </button>

      <div id="driveStatus" style="margin-top: 1rem; padding: 0.8rem; border-radius: 6px; background: var(--bg-100); font-size: 0.85rem;">
        <span id="driveStatusText">Checking Google Drive connection...</span>
      </div>
    </div>

    <!-- RIGHT: Restore -->
    <div class="card-panel">
      <div style="display:flex; align-items:center; gap:12px; margin-bottom: 1.5rem;">
        <div style="font-size: 2rem;">🔄</div>
        <div>
          <div style="font-weight: 700; font-size: 1.1rem;">Restore</div>
          <div style="font-size: 0.8rem; color: var(--muted);">Restore from a previous backup</div>
        </div>
      </div>

      <button class="btn btn-outline btn-block" id="restoreBtn" onclick="loadRestoreFiles()" style="padding: 1rem; font-size: 1.05rem;">
        Browse Backup Files
      </button>

      <div id="restoreFileList" style="margin-top: 1rem; display:none; max-height: 250px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px;">
      </div>
    </div>
  </div>

  <!-- Settings Panel -->
  <div class="card-panel" style="margin-top: 1.5rem;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom: 1.5rem;">
      <div style="font-size: 2rem;">⚙️</div>
      <div>
        <div style="font-weight: 700; font-size: 1.1rem;">Backup Settings</div>
        <div style="font-size: 0.8rem; color: var(--muted);">Google Drive and schedule configuration</div>
      </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
      <div class="input-group">
        <label class="input-label">Google Drive Folder ID</label>
        <input type="text" id="gdriveFolderId" class="input-field" placeholder="Folder ID from Google Drive">
      </div>
      <div class="input-group">
        <label class="input-label">Schedule Time</label>
        <input type="time" id="scheduleTime" class="input-field" value="22:00">
      </div>
      <div class="input-group">
        <label class="input-label">Keep Daily Backups</label>
        <input type="number" id="retentionDaily" class="input-field" value="7" min="1" max="30">
      </div>
      <div class="input-group">
        <label class="input-label">Keep Weekly Backups</label>
        <input type="number" id="retentionWeekly" class="input-field" value="4" min="0" max="12">
      </div>
    </div>

    <div style="display:flex; align-items:center; gap:12px; margin: 1rem 0;">
      <input type="checkbox" id="scheduleEnabled" style="width:18px;height:18px;">
      <label for="scheduleEnabled" style="font-weight:600;">Enable scheduled backup</label>
    </div>

    <div style="display:flex; gap:10px;">
      <button class="btn btn-primary" id="connectDriveBtn" onclick="connectGoogleDrive()">Connect Google Drive</button>
      <button class="btn btn-outline" onclick="saveBackupConfig()">Save Settings</button>
    </div>
    <div id="scheduleNote" style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--muted);">Scheduled backups run on the server even when you're not logged in.</div>
  </div>
</div>
