<!-- 
  FEATURE DOCUMENTATION: Day to Day Selling
  - Investment & Sales Insights: Provides dynamic investment statistics.
  - Timelines: Chronological grouping of sales and purchase activities.
  - Recommendations: "Stock Buying Recommendations" to assist with purchasing decisions.
-->
        <section id="day_to_day_selling" class="view-section">
          <h2 style="margin: 0 0 1.2rem; font-size: 1.4rem; font-weight: 700; color: var(--text-strong);">Day to Day
            Selling</h2>

          <!-- Stock Performance Comparison -->
          <div class="stats-grid" id="analyticsComparisonCards">
            <!-- Rendered via JS -->
          </div>

          <div class="pos-grid" style="grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
            <!-- Stock Recommendation Table -->
            <div class="card-panel">
              <div class="card-header" style="color: var(--info);">💡 Stock Buying Recommendations</div>
              <div class="table-container">
                <table class="data-table" id="recommendationTable">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Purchased Cost</th>
                      <th>Sold (at Cost)</th>
                      <th>Recovery %</th>
                      <th>Recommendation</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

            <!-- Summary Insights -->
            <div class="card-panel">
              <div class="card-header">📊 Investment Insights</div>
              <div id="analyticsInsights" style="padding: 1rem; line-height: 1.6;">
                <!-- Rendered via JS -->
              </div>
            </div>
          </div>

          <!-- Sales Timeline -->
          <div class="card-panel" style="margin-top: 1.5rem;">
            <div class="card-header">📋 Sales Timeline</div>
            <div class="table-container">
              <table class="data-table" style="font-size: 0.9rem;" id="salesTimelineTable">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Bills</th>
                    <th>Revenue</th>
                    <th>Avg Bill</th>
                    <th>Products Sold</th>
                    <th style="text-align:right">Action</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

          <!-- Purchase Timeline -->
          <div class="card-panel" style="margin-top: 1.5rem;">
            <div class="card-header">📦 Purchase Timeline</div>
            <div class="table-container">
              <table class="data-table" style="font-size: 0.9rem;" id="purchaseTimelineTable">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Purchases</th>
                    <th>Amount Purchased</th>
                    <th>Amount Paid</th>
                    <th>Balance Due</th>
                    <th>Vendors</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </section>
