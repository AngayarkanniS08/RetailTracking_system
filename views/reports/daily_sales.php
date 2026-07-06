        <section id="day_to_day_selling" class="view-section">
          <h2 style="margin: 0 0 1.2rem; font-size: 1.4rem; font-weight: 700; color: var(--text-strong);">Day to Day
            Selling</h2>

          <!-- Sales Timeline -->
          <div class="card-panel" style="margin-top: 1.5rem;">
            <div class="card-header">📋 Sales Timeline</div>
            <div class="input-group" style="margin-bottom: 1rem;">
              <input type="text" id="salesSearch" class="input-field" placeholder="Search by invoice number or customer name..." oninput="onSalesSearchInput()">
            </div>
            <div class="table-container">
              <table class="data-table" style="font-size: 0.9rem;" id="salesTimelineTable">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Bills</th>
                    <th>Revenue</th>
                    <th>Avg Bill</th>
                    <th style="text-align:right">Action</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div id="salesTimelinePagination" style="display:flex; justify-content:center; align-items:center; gap:12px; margin-top:1rem;"></div>
          </div>
        </section>
