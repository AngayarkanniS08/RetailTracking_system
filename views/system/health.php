<section id="system_health" class="view-section">
  <h2 style="margin-bottom:1.5rem;">System Health</h2>

  <div id="healthOverallStatus" class="card-panel" style="padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;">
    <span id="healthOverallIcon" style="font-size:1.8rem;">⚪</span>
    <div>
      <div id="healthOverallLabel" style="font-weight:600;font-size:1.1rem;">Checking...</div>
      <div id="healthOverallTime" style="font-size:0.8rem;color:var(--muted);"></div>
    </div>
  </div>

  <div class="health-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">

    <div class="card-panel" data-component="api">
      <div class="card-header" style="display:flex;align-items:center;gap:0.75rem;">
        <span class="health-indicator" style="font-size:1.4rem;">⚪</span>
        <span style="font-weight:600;">API</span>
      </div>
      <div class="health-detail" style="font-size:0.85rem;color:var(--muted);margin-top:0.5rem;">
        <span class="health-status-text">Checking...</span>
      </div>
    </div>

    <div class="card-panel" data-component="database">
      <div class="card-header" style="display:flex;align-items:center;gap:0.75rem;">
        <span class="health-indicator" style="font-size:1.4rem;">⚪</span>
        <span style="font-weight:600;">Database</span>
      </div>
      <div class="health-detail" style="font-size:0.85rem;color:var(--muted);margin-top:0.5rem;">
        <span class="health-status-text">Checking...</span>
      </div>
    </div>

    <div class="card-panel" data-component="valkey">
      <div class="card-header" style="display:flex;align-items:center;gap:0.75rem;">
        <span class="health-indicator" style="font-size:1.4rem;">⚪</span>
        <span style="font-weight:600;">Valkey</span>
      </div>
      <div class="health-detail" style="font-size:0.85rem;color:var(--muted);margin-top:0.5rem;">
        <span class="health-status-text">Checking...</span>
      </div>
    </div>

    <div class="card-panel" data-component="backup">
      <div class="card-header" style="display:flex;align-items:center;gap:0.75rem;">
        <span class="health-indicator" style="font-size:1.4rem;">⚪</span>
        <span style="font-weight:600;">Backup</span>
      </div>
      <div class="health-detail" style="font-size:0.85rem;color:var(--muted);margin-top:0.5rem;">
        <span class="health-status-text">Checking...</span>
      </div>
    </div>

    <div class="card-panel" data-component="disk">
      <div class="card-header" style="display:flex;align-items:center;gap:0.75rem;">
        <span class="health-indicator" style="font-size:1.4rem;">⚪</span>
        <span style="font-weight:600;">Disk</span>
      </div>
      <div class="health-detail" style="font-size:0.85rem;color:var(--muted);margin-top:0.5rem;">
        <span class="health-status-text">Checking...</span>
      </div>
    </div>

  </div>
</section>
