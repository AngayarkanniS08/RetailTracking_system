// ── Backup & Restore ─────────────────────────────────────────────────────────

let _backupPollTimer = null;
let _restorePollTimer = null;

function loadBackupPage() {
    loadBackupConfig();
    checkLastJob();
    attachCloseHandlers();
}

function attachCloseHandlers() {
    document.getElementById('backupCloseBtn')?.addEventListener('click', () => stopPolling('backup'));
    document.getElementById('restoreCloseBtn')?.addEventListener('click', () => stopPolling('restore'));
    document.querySelector('#backupProgressModal')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) stopPolling('backup');
    });
    document.querySelector('#restoreProgressModal')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) stopPolling('restore');
    });
}

async function loadBackupConfig() {
    try {
        const data = await apiRequest('/api/backup/config');
        document.getElementById('gdriveFolderId').value = data.gdrive_backup_folder_id || '';
        document.getElementById('scheduleTime').value = data.schedule_time || '22:00';
        document.getElementById('scheduleEnabled').checked = data.schedule_enabled || false;
        document.getElementById('retentionDaily').value = data.retention_daily || 7;
        document.getElementById('retentionWeekly').value = data.retention_weekly || 4;

        const driveStatus = document.getElementById('driveStatusText');
        if (data.gdrive_connected) {
            driveStatus.textContent = '✓ Google Drive connected' + (data.gdrive_auth_email ? ' (' + data.gdrive_auth_email + ')' : '');
            driveStatus.style.color = 'var(--ok)';
            document.getElementById('connectDriveBtn').textContent = 'Reconnect Google Drive';
        } else {
            driveStatus.textContent = '✗ Google Drive not connected';
            driveStatus.style.color = 'var(--muted)';
            document.getElementById('connectDriveBtn').textContent = 'Connect Google Drive';
        }

        if (data.last_backup_at) {
            const bar = document.getElementById('backupStatusBar');
            bar.style.display = 'block';
            document.getElementById('lastBackupInfo').textContent = formatDate(data.last_backup_at) + ' - ' + (data.last_backup_file || 'Unknown');
            const badge = document.getElementById('lastBackupStatusBadge');
            badge.textContent = data.last_backup_status === 'completed' ? '✓ Success' : '✗ Failed';
            badge.style.background = data.last_backup_status === 'completed' ? 'rgba(34,197,94,0.1)' : 'rgba(220,38,38,0.1)';
            badge.style.color = data.last_backup_status === 'completed' ? 'var(--ok)' : 'var(--danger)';
        }
    } catch (e) {
        console.error('Failed to load backup config:', e);
    }
}

async function checkLastJob() {
    // Check if there's an in-progress job from a previous page load
    // We can skip this for now - polling will pick it up
}

async function startBackup() {
    const btn = document.getElementById('backupNowBtn');
    btn.disabled = true;
    btn.textContent = 'Starting...';

    try {
        const result = await apiRequest('/api/backup/start', { method: 'POST' });
        if (result.success && result.job_id) {
            document.getElementById('backupCloseBtn').style.display = 'block';
            openModal('backupProgressModal');
            pollBackupStatus(result.job_id);
        }
    } catch (e) {
        alert('Failed to start backup: ' + e.message);
        btn.disabled = false;
        btn.textContent = 'Start Backup';
    }
}

function pollBackupStatus(jobId) {
    const steps = {
        'pending': 'Queued',
        'dump': 'Dumping database...',
        'uploading': 'Uploading to Google Drive...',
        'completed': 'Completed',
        'failed': 'Failed'
    };

    function updateUI(status, progress) {
        const stepOrder = ['pending', 'dump', 'uploading', 'completed'];
        const stepsElements = document.querySelectorAll('#backupStepDisplay .backup-step');

        stepsElements.forEach(el => {
            const step = el.getAttribute('data-step');
            el.classList.remove('active', 'done', 'failed');

            const idx = stepOrder.indexOf(step);
            const currentIdx = stepOrder.indexOf(status);

            if (idx < currentIdx) {
                el.classList.add('done');
                el.querySelector('.step-icon').textContent = '✓';
            } else if (idx === currentIdx) {
                el.classList.add('active');
                el.querySelector('.step-icon').textContent = '⏳';
            }
        });

        if (status === 'completed') {
            document.getElementById('backupCloseBtn').style.display = 'block';
            document.getElementById('backupStepDisplay').style.display = 'none';
            const msg = document.getElementById('backupResultMessage');
            msg.style.display = 'block';
            msg.style.background = 'rgba(34,197,94,0.1)';
            msg.style.color = 'var(--ok)';
            msg.textContent = '✓ Backup completed successfully' + (progress && progress !== 'Backup completed successfully' ? ': ' + progress : '');
            stopPolling('backup');
            document.getElementById('backupNowBtn').disabled = false;
            document.getElementById('backupNowBtn').textContent = 'Start Backup';
            loadBackupPage();
        } else if (status === 'failed') {
            document.getElementById('backupCloseBtn').style.display = 'block';
            const msg = document.getElementById('backupResultMessage');
            msg.style.display = 'block';
            msg.style.background = 'rgba(220,38,38,0.1)';
            msg.style.color = 'var(--danger)';
            msg.textContent = '✗ Backup failed: ' + (progress || 'Unknown error');
            stopPolling('backup');
            document.getElementById('backupNowBtn').disabled = false;
            document.getElementById('backupNowBtn').textContent = 'Start Backup';
        }
    }

    _backupPollTimer = setInterval(async () => {
        try {
            const data = await apiRequest('/api/backup/status/' + jobId);
            updateUI(data.status, data.progress);
            if (data.status === 'completed' || data.status === 'failed') {
                clearInterval(_backupPollTimer);
                _backupPollTimer = null;
            }
        } catch (e) {
            console.error('Backup status poll failed:', e);
        }
    }, 2000);
}

async function loadRestoreFiles() {
    const list = document.getElementById('restoreFileList');
    list.style.display = 'block';
    list.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--muted);">Loading...</div>';

    try {
        const data = await apiRequest('/api/backup/files');
        const files = data.files || [];
        if (files.length === 0) {
            list.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--muted);">No backup files found in Google Drive</div>';
            return;
        }

        list.innerHTML = '<div style="padding: 0.5rem 0.8rem; font-size: 0.8rem; font-weight: 600; color: var(--muted); border-bottom: 1px solid var(--border);">Select a backup file to restore:</div>';

        files.forEach(f => {
            const row = document.createElement('div');
            row.className = 'backup-file-row';
            row.style.cursor = 'pointer';
            row.innerHTML = `
                <div>
                    <div style="font-weight: 600; font-size: 0.9rem;">${f.fileName}</div>
                    <div style="font-size: 0.8rem; color: var(--muted);">${formatDate(f.createdTime)} · ${formatFileSize(f.fileSize)}</div>
                </div>
                <button class="btn btn-sm btn-outline" style="color: var(--danger); border-color: var(--danger);">Restore</button>
            `;
            row.querySelector('button').onclick = function(e) {
                e.stopPropagation();
                openRestoreConfirm(f);
            };
            row.onclick = function() {
                openRestoreConfirm(f);
            };
            list.appendChild(row);
        });
    } catch (e) {
        list.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--danger);">Failed to load backup files: ' + e.message + '</div>';
    }
}

function openRestoreConfirm(file) {
    document.getElementById('restoreFileInfo').innerHTML = `
        <div style="font-weight: 600;">${file.fileName}</div>
        <div style="font-size: 0.85rem; color: var(--muted);">${formatDate(file.createdTime)} · ${formatFileSize(file.fileSize)}</div>
    `;
    document.getElementById('restorePassword').value = '';
    document.getElementById('restoreFileInfo').dataset.fileId = file.driveFileId;
    document.getElementById('executeRestoreBtn').disabled = false;
    openModal('restoreConfirmModal');
}

async function executeRestore() {
    const fileId = document.getElementById('restoreFileInfo').dataset.fileId;

    const btn = document.getElementById('executeRestoreBtn');
    btn.disabled = true;
    btn.textContent = 'Restoring...';

    try {
        const result = await apiRequest('/api/backup/restore', {
            method: 'POST',
            body: JSON.stringify({ drive_file_id: fileId })
        });

        closeModal('restoreConfirmModal');

        if (result.success) {
            openModal('restoreProgressModal');
            document.getElementById('restoreStepDisplay').style.display = 'none';
            document.getElementById('restoreCloseBtn').style.display = 'block';
            const msg = document.getElementById('restoreResultMessage');
            msg.style.display = 'block';
            msg.style.background = 'rgba(34,197,94,0.1)';
            msg.style.color = 'var(--ok)';
            msg.textContent = '✓ Restore completed successfully. Data has been restored.';
        }
    } catch (e) {
        alert('Restore failed: ' + e.message);
        btn.disabled = false;
        btn.textContent = 'Restore Data';
    }
}

function pollRestoreStatus(jobId) {
    const steps = {
        'downloading': 'Downloading backup...',
        'restoring': 'Restoring database...',
        'completed': 'Completed',
        'failed': 'Failed'
    };

    function updateUI(status, progress) {
        const stepOrder = ['downloading', 'restoring', 'completed'];
        const stepsElements = document.querySelectorAll('#restoreStepDisplay .backup-step');

        stepsElements.forEach(el => {
            const step = el.getAttribute('data-step');
            el.classList.remove('active', 'done', 'failed');

            const idx = stepOrder.indexOf(step);
            const currentIdx = stepOrder.indexOf(status);

            if (idx < currentIdx) {
                el.classList.add('done');
                el.querySelector('.step-icon').textContent = '✓';
            } else if (idx === currentIdx) {
                el.classList.add('active');
                el.querySelector('.step-icon').textContent = '⏳';
            }
        });

        if (status === 'completed') {
            document.getElementById('restoreCloseBtn').style.display = 'block';
            document.getElementById('restoreStepDisplay').style.display = 'none';
            const msg = document.getElementById('restoreResultMessage');
            msg.style.display = 'block';
            msg.style.background = 'rgba(34,197,94,0.1)';
            msg.style.color = 'var(--ok)';
            msg.textContent = '✓ Restore completed successfully. Data has been restored.';
            clearInterval(_restorePollTimer);
            _restorePollTimer = null;
        } else if (status === 'failed') {
            document.getElementById('restoreCloseBtn').style.display = 'block';
            const msg = document.getElementById('restoreResultMessage');
            msg.style.display = 'block';
            msg.style.background = 'rgba(220,38,38,0.1)';
            msg.style.color = 'var(--danger)';
            msg.textContent = '✗ Restore failed: ' + (progress || 'Unknown error');
            clearInterval(_restorePollTimer);
            _restorePollTimer = null;
        }
    }

    _restorePollTimer = setInterval(async () => {
        try {
            const data = await apiRequest('/api/backup/status/' + jobId);
            updateUI(data.status, data.progress);
            if (data.status === 'completed' || data.status === 'failed') {
                clearInterval(_restorePollTimer);
                _restorePollTimer = null;
            }
        } catch (e) {
            console.error('Restore status poll failed:', e);
        }
    }, 2000);
}

async function saveBackupConfig() {
    const payload = {
        gdrive_backup_folder_id: document.getElementById('gdriveFolderId').value.trim(),
        schedule_enabled: document.getElementById('scheduleEnabled').checked,
        schedule_time: document.getElementById('scheduleTime').value,
        retention_daily: parseInt(document.getElementById('retentionDaily').value) || 7,
        retention_weekly: parseInt(document.getElementById('retentionWeekly').value) || 4,
        retention_monthly: 12
    };

    try {
        await apiRequest('/api/backup/config', {
            method: 'PUT',
            body: JSON.stringify(payload)
        });
        alert('Backup settings saved.');
        loadBackupConfig();
    } catch (e) {
        alert('Failed to save settings: ' + e.message);
    }
}

async function connectGoogleDrive() {
    try {
        const data = await apiRequest('/api/backup/auth-url');
        if (data.auth_url) {
            const width = 600, height = 700;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            const win = window.open(data.auth_url, 'google-auth',
                'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top);

            const pollTimer = setInterval(async () => {
                if (win.closed) {
                    clearInterval(pollTimer);
                    // After close, check if token was saved
                    loadBackupConfig();
                }
            }, 1000);
        }
    } catch (e) {
        alert('Failed to connect: ' + e.message);
    }
}

// Called by the OAuth popup after auth
async function handleAuthCode(code) {
    try {
        await apiRequest('/api/backup/auth-code', {
            method: 'POST',
            body: JSON.stringify({ code: code })
        });
        loadBackupConfig();
    } catch (e) {
        alert('Failed to exchange auth code: ' + e.message);
    }
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let size = bytes;
    while (size >= 1024 && i < units.length - 1) { size /= 1024; i++; }
    return size.toFixed(1) + ' ' + units[i];
}

function formatDate(iso) {
    if (!iso) return '-';
    const d = new Date(iso);
    return d.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function stopPolling(type) {
    const timer = type === 'backup' ? '_backupPollTimer' : '_restorePollTimer';
    if (window[timer]) {
        clearInterval(window[timer]);
        window[timer] = null;
    }
}


