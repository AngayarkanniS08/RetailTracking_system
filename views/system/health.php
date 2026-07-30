<section id="system_health" class="view-section active">

  <!-- 1. Executive Topbar Header -->
  <div class="dash-topbar" style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div>
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-strong); margin: 0; letter-spacing: -0.02em;">System Telemetry & Health</h1>
        <span class="store-badge" style="background: var(--surface-container-high); border: 1px solid var(--border); padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; color: var(--text-muted);">🏢 Production Cluster</span>
      </div>
      <div style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: var(--muted);">
        <span id="healthLiveDot" class="live-dot" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--ok);"></span>
        <span>Auto-refresh active (10s)</span>
        <span>•</span>
        <span id="healthOverallTime">Last checked: Just now</span>
      </div>
    </div>

    <div class="topbar-actions" style="display: flex; align-items: center; gap: 12px;">
      <button class="btn btn-outline btn-sm" onclick="refreshSystemHealth()" style="height: 38px; padding: 0 16px; border-radius: var(--radius-md); font-weight: 600; font-size: 0.85rem;">
        🔄 Refresh Telemetry
      </button>
      <button class="btn btn-primary btn-sm" onclick="triggerManualBackup()" style="height: 38px; padding: 0 16px; border-radius: var(--radius-md); font-weight: 600; font-size: 0.85rem;">
        💾 Run Backup Now
      </button>
    </div>
  </div>

  <!-- 2. Executive 4-Hero KPI Grid -->
  <div class="health-kpi-grid" style="margin-bottom: 1.5rem;">
    
    <!-- Health Score Hero Card -->
    <div class="card-panel kpi-card" style="padding: 1.25rem; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); border-left: 4px solid var(--ok); box-shadow: var(--shadow-sm);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Overall Health Score</span>
        <span id="healthStatusBadge" class="badge-emerald">🟢 100% Operational</span>
      </div>
      <div style="font-size: 1.8rem; font-weight: 700; color: var(--text-strong);" class="tabular-nums" id="healthOverallScore">98%</div>
      <div style="font-size: 0.78rem; color: var(--muted); margin-top: 6px;" id="healthScoreDesc">All core microservices & datastores responding normally</div>
    </div>

    <!-- CPU Utilization -->
    <div class="card-panel kpi-card" style="padding: 1.25rem; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); border-left: 4px solid var(--accent, #3b82f6); box-shadow: var(--shadow-sm);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">CPU Utilization</span>
        <span style="font-size: 0.85rem;">⚡</span>
      </div>
      <div style="font-size: 1.8rem; font-weight: 700; color: var(--text-strong);" class="tabular-nums" id="healthCpuVal">14%</div>
      <div style="margin-top: 8px; width: 100%; background: var(--surface-container-low); height: 6px; border-radius: 3px; overflow: hidden;">
        <div id="healthCpuBar" style="width: 14%; height: 100%; background: var(--accent, #3b82f6); border-radius: 3px; transition: width 0.3s ease;"></div>
      </div>
    </div>

    <!-- RAM Usage -->
    <div class="card-panel kpi-card" style="padding: 1.25rem; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); border-left: 4px solid #8b5cf6; box-shadow: var(--shadow-sm);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">RAM Memory</span>
        <span style="font-size: 0.85rem;">🧠</span>
      </div>
      <div style="font-size: 1.8rem; font-weight: 700; color: var(--text-strong);" class="tabular-nums" id="healthRamVal">38%</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;" class="tabular-nums" id="healthRamSub">1.5 GB / 4.0 GB Allocation</div>
    </div>

    <!-- Disk Free Space -->
    <div class="card-panel kpi-card" style="padding: 1.25rem; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); border-left: 4px solid #06b6d4; box-shadow: var(--shadow-sm);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <span style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Disk Free Space</span>
        <span style="font-size: 0.85rem;">💾</span>
      </div>
      <div style="font-size: 1.8rem; font-weight: 700; color: var(--text-strong);" class="tabular-nums" id="healthDiskVal">55% Free</div>
      <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;" class="tabular-nums" id="healthDiskSub">140.8 GB Available</div>
    </div>

  </div>

  <!-- 3. Real-time Telemetry Trend Charts Row -->
  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
    
    <!-- Chart 1: Latency Trends -->
    <div class="card-panel" style="padding: 1.5rem; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span>📈</span>
          <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-strong); margin: 0;">Service Latency Trends (ms)</h3>
        </div>
        <div style="display: flex; gap: 12px; font-size: 0.75rem;">
          <span style="color: var(--ok); display: flex; align-items: center; gap: 4px;">● Database Latency</span>
          <span style="color: var(--accent, #3b82f6); display: flex; align-items: center; gap: 4px;">● Valkey Cache Latency</span>
        </div>
      </div>
      <div style="width: 100%; height: 210px; position: relative;">
        <canvas id="healthLatencyChart" height="210" style="width: 100%; height: 100%;"></canvas>
      </div>
    </div>

    <!-- Chart 2: System Load Trends -->
    <div class="card-panel" style="padding: 1.5rem; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span>📊</span>
          <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-strong); margin: 0;">Resource Load & Connection Pool</h3>
        </div>
        <div style="display: flex; gap: 12px; font-size: 0.75rem;">
          <span style="color: #8b5cf6; display: flex; align-items: center; gap: 4px;">● CPU Load %</span>
          <span style="color: #06b6d4; display: flex; align-items: center; gap: 4px;">● DB Active Connections</span>
        </div>
      </div>
      <div style="width: 100%; height: 210px; position: relative;">
        <canvas id="healthResourceChart" height="210" style="width: 100%; height: 100%;"></canvas>
      </div>
    </div>

  </div>

  <!-- 4. Consolidated Service Status Monitoring Table -->
  <div class="card-panel" style="padding: 1.25rem; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span>🖥️</span>
        <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-strong); margin: 0;">Core Microservice & Infrastructure Status</h3>
      </div>
      <span style="font-size: 0.75rem; color: var(--muted);">Clean Line Layout</span>
    </div>

    <div style="overflow-x: auto;">
      <table class="health-matrix-table">
        <colgroup>
          <col style="width: 26%;">
          <col style="width: 16%;">
          <col style="width: 20%;">
          <col style="width: 14%;">
          <col style="width: 14%;">
          <col style="width: 10%;">
        </colgroup>
        <thead>
          <tr>
            <th>Service / Layer</th>
            <th>Status</th>
            <th>Latency / Capacity</th>
            <th>24h Uptime</th>
            <th>Last Check</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody id="healthServiceTableBody">
          
          <!-- API Row -->
          <tr>
            <td style="font-weight: 600; color: var(--text-strong);">
              <span style="margin-right: 6px;">🌐</span> API Router Gateway
            </td>
            <td><span class="badge-emerald" id="badgeApi">🟢 Operational</span></td>
            <td class="tabular-nums" id="latencyApi">120 ms</td>
            <td class="tabular-nums" style="color: var(--ok);">99.99%</td>
            <td style="color: var(--muted);" id="timeApi">2s ago</td>
            <td style="text-align: right;">
              <button class="btn btn-xs btn-outline" onclick="pingService('api')">Ping</button>
            </td>
          </tr>

          <!-- Database Row -->
          <tr>
            <td style="font-weight: 600; color: var(--text-strong);">
              <span style="margin-right: 6px;">🗄️</span> PostgreSQL Database
            </td>
            <td><span class="badge-emerald" id="badgeDb">🟢 Operational</span></td>
            <td class="tabular-nums" id="latencyDb">4.2 ms</td>
            <td class="tabular-nums" style="color: var(--ok);">100.00%</td>
            <td style="color: var(--muted);" id="timeDb">1s ago</td>
            <td style="text-align: right;">
              <button class="btn btn-xs btn-outline" onclick="pingService('database')">Stats</button>
            </td>
          </tr>

          <!-- Valkey Cache Row -->
          <tr>
            <td style="font-weight: 600; color: var(--text-strong);">
              <span style="margin-right: 6px;">⚡</span> Valkey / Redis Datastore
            </td>
            <td><span class="badge-emerald" id="badgeValkey">🟢 Operational</span></td>
            <td class="tabular-nums" id="latencyValkey">1.1 ms</td>
            <td class="tabular-nums" style="color: var(--ok);">99.98%</td>
            <td style="color: var(--muted);" id="timeValkey">1s ago</td>
            <td style="text-align: right;">
              <button class="btn btn-xs btn-outline" onclick="pingService('valkey')">Flush</button>
            </td>
          </tr>

          <!-- Disk Storage Row -->
          <tr>
            <td style="font-weight: 600; color: var(--text-strong);">
              <span style="margin-right: 6px;">💾</span> System Storage Volume
            </td>
            <td><span class="badge-emerald" id="badgeDisk">🟢 Operational</span></td>
            <td class="tabular-nums" id="capacityDisk">55% Free Space</td>
            <td class="tabular-nums" style="color: var(--ok);">100.00%</td>
            <td style="color: var(--muted);" id="timeDisk">5s ago</td>
            <td style="text-align: right;">
              <button class="btn btn-xs btn-outline" onclick="pingService('disk')">Inspect</button>
            </td>
          </tr>

          <!-- Backup Service Row -->
          <tr>
            <td style="font-weight: 600; color: var(--text-strong);">
              <span style="margin-right: 6px;">🛡️</span> Automated Backup Daemon
            </td>
            <td><span class="badge-emerald" id="badgeBackup">🟢 Operational</span></td>
            <td class="tabular-nums" id="stateBackup">Last run: 3h ago</td>
            <td class="tabular-nums" style="color: var(--ok);">98.50%</td>
            <td style="color: var(--muted);" id="timeBackup">10m ago</td>
            <td style="text-align: right;">
              <button class="btn btn-xs btn-primary" onclick="triggerManualBackup()">Backup</button>
            </td>
          </tr>

        </tbody>
      </table>
    </div>
  </div>

  <!-- 5. Operational Event Log & Diagnostic Audit Feed -->
  <div class="card-panel" style="padding: 1.25rem; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span>📋</span>
        <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-strong); margin: 0;">Diagnostic Event Stream</h3>
      </div>
      <span style="font-size: 0.75rem; color: var(--muted);">Real-time Telemetry Audit</span>
    </div>

    <div id="healthEventTimeline" style="display: flex; flex-direction: column; gap: 10px;">
      <div class="event-item" style="padding: 10px 14px; border-radius: var(--radius-md); background: var(--surface-container-low); border: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; font-size: 0.83rem;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="color: var(--ok);">🟢</span>
          <span style="font-weight: 600; color: var(--text-strong);">Full Health Check Passed</span>
          <span style="color: var(--muted);">— All microservices responded under threshold.</span>
        </div>
        <span class="tabular-nums" style="font-size: 0.75rem; color: var(--muted);" id="eventTimeNow">Just now</span>
      </div>
    </div>
  </div>

</section>
