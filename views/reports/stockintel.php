        <section id="stockintel" class="view-section">
          <div class="card-header" style="margin-bottom: 1.5rem;">
            <div style="display:flex; align-items:center; gap:12px;">
              <button class="btn btn-outline btn-sm" onclick="switchTab('dashboard')"
                style="display:flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
                Back to Dashboard
              </button>
              <span id="siTitle" style="font-size:1.2rem; font-weight:700; color:var(--text-strong);">Stock
                Intelligence</span>
            </div>
          </div>
          <div class="cat-filters" id="siFilters">
            <button class="cat-btn active" onclick="setStockIntelFilter('all')">All</button>
            <button class="cat-btn" onclick="setStockIntelFilter('high')">🔥 High Selling</button>
            <button class="cat-btn" onclick="setStockIntelFilter('normal')">⚖️ Normal</button>
            <button class="cat-btn" onclick="setStockIntelFilter('low')">📉 Low Selling</button>
            <button class="cat-btn" onclick="setStockIntelFilter('old')">📦 Old Stock</button>
            <button class="cat-btn" onclick="setStockIntelFilter('new')">🆕 New</button>
            <button class="cat-btn" onclick="setStockIntelFilter('critical')">🚨 Critical</button>
          </div>
          <div id="siCardsContainer">
            <!-- Rendered via JS -->
          </div>
        </section>
