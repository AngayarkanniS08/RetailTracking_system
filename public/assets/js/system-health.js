// ── System Health Dashboard ────────────────────────────────────────────────

let _healthPollTimer = null;

function initSystemHealth() {
    fetchHealth();
    if (_healthPollTimer) clearInterval(_healthPollTimer);
    _healthPollTimer = setInterval(fetchHealth, 30000);
}

function fetchHealth() {
    apiRequest('/health').then(function(data) {
        updateHealthUI(data);
    }).catch(function(err) {
        const overallEl = document.getElementById('healthOverallStatus');
        if (overallEl) {
            document.getElementById('healthOverallIcon').textContent = '🔴';
            document.getElementById('healthOverallLabel').textContent = 'Unable to determine health';
        }
        document.querySelectorAll('.health-indicator').forEach(function(el) { el.textContent = '⚪'; });
        document.querySelectorAll('.health-status-text').forEach(function(el) { el.textContent = 'Unreachable'; });
    });
}

function updateHealthUI(data) {
    const now = new Date();
    document.getElementById('healthOverallTime').textContent = 'Last updated: ' + now.toLocaleTimeString();

    const overallStatus = data.status || 'unknown';
    const overallIcon = statusIcon(overallStatus);
    document.getElementById('healthOverallIcon').textContent = overallIcon;
    document.getElementById('healthOverallLabel').textContent = 'System: ' + statusLabel(overallStatus);

    var overallClass = 'var(--ok)';
    if (overallStatus === 'degraded') overallClass = 'var(--warn)';
    else if (overallStatus === 'unhealthy' || overallStatus === 'unknown') overallClass = 'var(--danger)';
    document.getElementById('healthOverallStatus').style.borderLeft = '4px solid ' + overallClass;

    var components = data.components || {};
    var compNames = ['api', 'database', 'valkey', 'backup', 'disk'];

    compNames.forEach(function(name) {
        var card = document.querySelector('[data-component="' + name + '"]');
        if (!card) return;

        var indicator = card.querySelector('.health-indicator');
        var statusText = card.querySelector('.health-status-text');
        var detail = card.querySelector('.health-detail');

        var comp = components[name] || { status: 'unknown' };
        var compStatus = comp.status || 'unknown';

        indicator.textContent = statusIcon(compStatus);
        card.style.borderLeft = '4px solid ' + statusColor(compStatus);

        var text = statusLabel(compStatus);
        var extras = [];

        if (comp.latency_ms !== undefined) extras.push(comp.latency_ms + 'ms');
        if (comp.free_percent !== undefined) extras.push(comp.free_percent + '% free');
        if (comp.last_successful_backup) {
            var d = new Date(comp.last_successful_backup);
            extras.push('Last: ' + d.toLocaleDateString() + ' ' + d.toLocaleTimeString());
        }
        if (comp.state === 'never_run') extras.push('No backup yet');

        if (extras.length > 0) {
            text += ' — ' + extras.join(' | ');
        }

        statusText.textContent = text;
    });
}

function statusIcon(status) {
    if (status === 'healthy') return '🟢';
    if (status === 'degraded') return '🟠';
    if (status === 'unhealthy') return '🔴';
    return '⚪';
}

function statusColor(status) {
    if (status === 'healthy') return 'var(--ok)';
    if (status === 'degraded') return 'var(--warn)';
    if (status === 'unhealthy') return 'var(--danger)';
    return 'var(--muted)';
}

function statusLabel(status) {
    if (status === 'healthy') return 'Healthy';
    if (status === 'degraded') return 'Degraded';
    if (status === 'unhealthy') return 'Unhealthy';
    return 'Unknown';
}

window.initSystemHealth = initSystemHealth;
