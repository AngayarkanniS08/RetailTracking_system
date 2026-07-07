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

            <!-- Header: icon + name + badges -->
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

            <!-- CLASSIFICATION VERDICT — first, most prominent -->
            <div class="ph-class-block" id="phClassBlock">
              <div class="ph-class-title">Classification — based on velocity</div>
              <div class="ph-class-pills" id="phClassPills"></div>
              <div class="ph-class-reason" id="phClassReason"></div>
            </div>

            <!-- HERO: The one number that matters most -->
            <div class="ph-hero-row">
              <div class="ph-hero-card accent">
                <div class="ph-hero-badge" id="phHeroBadge"></div>
                <div class="ph-hero-lbl">Daily velocity (30d avg)</div>
                <div class="ph-hero-val" style="color:var(--info)" id="phHeroVelocity">--</div>
                <div class="ph-hero-sub" id="phHeroVelUnit">units per day</div>
              </div>
              <div class="ph-hero-card">
                <div class="ph-hero-lbl">Days of supply left</div>
                <div class="ph-hero-val" id="phHeroDos">&infin;</div>
                <div class="ph-hero-sub">days before reorder needed</div>
              </div>
              <div class="ph-hero-card">
                <div class="ph-hero-lbl">Revenue (30d)</div>
                <div class="ph-hero-val" id="phHeroRevenue">&#x20b9;0</div>
                <div class="ph-hero-sub">from <span id="phHeroSoldUnits">0</span> units sold</div>
              </div>
            </div>

            <!-- VELOCITY DETAIL — drives everything -->
            <div class="ph-vel-block">
              <div class="ph-vel-title">Velocity detail — how fast is it selling?</div>
              <div class="ph-vel-row">
                <div class="ph-vel-cell">
                  <div class="ph-vel-lbl">Last 7 days</div>
                  <div class="ph-vel-val" style="color:var(--ok)" id="phVel7">-- /day</div>
                  <div class="ph-vel-desc"><span id="phVel7Sold">0</span> units sold</div>
                </div>
                <div class="ph-vel-cell">
                  <div class="ph-vel-lbl">Last 30 days</div>
                  <div class="ph-vel-val" style="color:var(--info)" id="phVel30">-- /day</div>
                  <div class="ph-vel-desc"><span id="phVel30Sold">0</span> units sold</div>
                </div>
                <div class="ph-vel-cell">
                  <div class="ph-vel-lbl">Catalog average</div>
                  <div class="ph-vel-val" style="color:var(--muted-strong)" id="phVelCat">-- /day</div>
                  <div class="ph-vel-desc">all products avg</div>
                </div>
              </div>

            </div>

            <!-- STOCK — compact -->
            <div class="ph-stock-block">
              <div class="ph-stock-title">Stock status</div>
              <div class="ph-stock-row">
                <div class="ph-sc">
                  <div class="ph-sc-lbl">Stock left</div>
                  <div class="ph-sc-val" id="phStockLeftVal">--</div>
                </div>
                <div class="ph-sc">
                  <div class="ph-sc-lbl">Oldest batch</div>
                  <div class="ph-sc-val" id="phOldestBatchVal">--</div>
                </div>
                <div class="ph-sc">
                  <div class="ph-sc-lbl">Margin</div>
                  <div class="ph-sc-val" id="phMarginVal">--</div>
                </div>
              </div>
              <div class="ph-bar-track"><div class="ph-bar-fill" id="phStockBarFill" style="width:100%"></div></div>
              <div class="ph-bar-meta"><span id="phStockRemaining">--</span><span id="phStockPctLabel">--</span></div>
              <div class="ph-alert-box" id="phAlertBox"></div>
            </div>

          </div>
        </section>
