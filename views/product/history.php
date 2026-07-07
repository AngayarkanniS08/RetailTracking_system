        <!-- PRODUCT HISTORY / VELOCITY DETAIL -->
        <section id="product_history" class="view-section">
          <div class="card-header" style="margin-bottom: 1rem;">
            <div style="display:flex; align-items:center; gap:12px; flex:1; flex-wrap:wrap;">
              <button class="btn btn-outline btn-sm" onclick="switchTab('dashboard')"
                style="display:flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back
              </button>
              <span style="font-size:1.2rem; font-weight:700; color:var(--text-strong);">Product Analysis</span>
              <select id="phProductSelect" style="min-width:220px;padding:6px 10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text);font-size:0.9rem;"
                onchange="openProductHistory(this.value, this.options[this.selectedIndex].text)">
                <option value="">-- Select a product --</option>
              </select>
              <span id="phSubtitle" style="font-size:0.85rem; color:var(--muted);"></span>
            </div>
          </div>

          <!-- Product Analytics Card -->
          <div class="si-card" id="phProductCard">
            <div class="si-header">
              <div class="si-product">
                <div class="si-icon" style="background: var(--accent-subtle);" id="phIcon"></div>
                <div>
                  <div class="si-name" id="phProductName">Product Name</div>
                  <div class="si-meta" id="phProductMeta">Product ID &middot; Category</div>
                </div>
              </div>
              <div id="phBadges"></div>
            </div>

            <!-- Section label: Sales -->
            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--muted-strong); margin:0.75rem 0 0.25rem; padding:0 0.25rem;">Sales Performance</div>

            <!-- Row 1: Sold qty across periods -->
            <div class="si-metrics">
              <div class="si-metric">
                <div class="si-metric-label">Sold (7d)</div>
                <div class="si-metric-value" id="phSold7d">0</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Sold (30d)</div>
                <div class="si-metric-value" style="color:var(--ok)" id="phSold30d">0</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Sold (90d)</div>
                <div class="si-metric-value" id="phSold90d">0</div>
              </div>
            </div>

            <!-- Row 2: Avg daily across periods -->
            <div class="si-metrics">
              <div class="si-metric">
                <div class="si-metric-label">Avg Daily (7d)</div>
                <div class="si-metric-value" id="phAvgDaily7d">0</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Avg Daily (30d)</div>
                <div class="si-metric-value" style="color:var(--info)" id="phAvgDaily30d">0</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Avg Daily (90d)</div>
                <div class="si-metric-value" id="phAvgDaily90d">0</div>
              </div>
            </div>

            <!-- Row 3: Revenue, velocity, trend -->
            <div class="si-metrics">
              <div class="si-metric">
                <div class="si-metric-label">Revenue (30d)</div>
                <div class="si-metric-value" id="phRevenue">&#x20b9;0</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Velocity</div>
                <div class="si-metric-value" style="color:var(--info)" id="phVelocity">0 /day</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Trend</div>
                <div class="si-metric-value" id="phTrend">--</div>
              </div>
            </div>

            <!-- Section label: Stock -->
            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--muted-strong); margin:1rem 0 0.25rem; padding:0 0.25rem;">Stock &amp; Supply</div>

            <!-- Row 4: Stock basics -->
            <div class="si-metrics">
              <div class="si-metric">
                <div class="si-metric-label">Stock Left</div>
                <div class="si-metric-value" id="phStockLeft">0</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Days of Supply</div>
                <div class="si-metric-value" id="phDaysOfSupply">&infin;</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Batches</div>
                <div class="si-metric-value" id="phBatchCount">0</div>
              </div>
            </div>

            <!-- Row 5: Stock value, margin, age -->
            <div class="si-metrics">
              <div class="si-metric">
                <div class="si-metric-label">Stock Value</div>
                <div class="si-metric-value" id="phStockValue">&#x20b9;0</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Margin</div>
                <div class="si-metric-value" id="phMargin">0%</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Oldest Batch</div>
                <div class="si-metric-value" id="phMaxBatchAge">0d</div>
              </div>
            </div>

            <!-- Row 6: Dates and reorder -->
            <div class="si-metrics">
              <div class="si-metric">
                <div class="si-metric-label">Last Sale</div>
                <div class="si-metric-value" id="phLastSale">--</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">First Sale</div>
                <div class="si-metric-value" id="phFirstSale">--</div>
              </div>
              <div class="si-metric">
                <div class="si-metric-label">Reorder</div>
                <div class="si-metric-value" id="phReorderStatus">--</div>
              </div>
            </div>

            <!-- Stock Level Bar -->
            <div class="si-stock-bar">
              <div class="si-stock-label">
                <span>Stock level</span>
                <span id="phStockPct" style="color:var(--ok)">100%</span>
              </div>
              <div class="si-bar-track">
                <div class="si-bar-fill" id="phStockBar" style="width:100%; background:var(--ok)"></div>
              </div>
            </div>

            <!-- Alert -->
            <div id="phAlert"></div>
          </div>
        </section>
