/**
 * dashboard.js — Dashboard page controller module
 * Features:
 * 1. 1-second Skeleton Loading shimmer effect
 * 2. Empty state detection with CTA redirecting to Product Master
 * 3. 50-line pure Vanilla HTML5 Canvas JS weekly sales bar chart
 */

import { fetchDashboardStatsApi, fetchTimePeriodStatsApi } from '../services/dashboard.service.js';
import { formatCurrency } from '../utils/format.js';
import { setText } from '../utils/dom.js';
import { logger } from '../core/logger.js';

/**
 * Render 1-second Skeleton Loading Shimmer State across KPI cards
 */
function renderSkeletonLoading() {
  const kpiIds = ['tcTodayRev', 'tcWeekRev', 'tcMonthRev', 'pcWeekAmount', 'pcMonthAmount'];
  kpiIds.forEach((id) => {
    const el = document.getElementById(id);
    if (el) {
      el.dataset.originalHtml = el.innerHTML;
      el.innerHTML = `<span class="skeleton-box" style="width: 90px; height: 1.75rem; vertical-align: middle;"></span>`;
    }
  });
}

/**
 * Remove Skeleton Loading Shimmer and restore real values
 */
function clearSkeletonLoading() {
  const kpiIds = ['tcTodayRev', 'tcWeekRev', 'tcMonthRev', 'pcWeekAmount', 'pcMonthAmount'];
  kpiIds.forEach((id) => {
    const el = document.getElementById(id);
    if (el && el.dataset.originalHtml && el.querySelector('.skeleton-box')) {
      el.innerHTML = el.dataset.originalHtml;
    }
  });
}

/**
 * Display Empty State CTA if sales are zero
 */
function displayEmptyState() {
  const emptyStateEl = document.getElementById('dashboardEmptyState');
  if (emptyStateEl) {
    emptyStateEl.style.display = 'block';
  }
}

/**
 * Pure 50-line Vanilla JS Canvas Bar Chart
 * Draws current week (Green) vs last week (Blue) sales comparison
 */
function drawWeeklySalesCanvasChart(canvasId, thisWeekData, lastWeekData) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const dpr = window.devicePixelRatio || 1;
  const rect = canvas.getBoundingClientRect();

  canvas.width = (rect.width || 540) * dpr;
  canvas.height = 240 * dpr;
  ctx.scale(dpr, dpr);

  const width = rect.width || 540;
  const height = 240;
  const padding = { top: 30, right: 20, bottom: 40, left: 50 };

  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;

  const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  const maxVal = Math.max(...thisWeekData, ...lastWeekData, 1000);

  // Clear Canvas
  ctx.clearRect(0, 0, width, height);

  // Draw Horizontal Gridlines & Y-Axis Labels
  ctx.strokeStyle = 'rgba(148, 163, 184, 0.2)';
  ctx.fillStyle = '#64748b';
  ctx.font = '11px sans-serif';
  ctx.textAlign = 'right';

  const gridSteps = 4;
  for (let i = 0; i <= gridSteps; i++) {
    const y = padding.top + (chartHeight / gridSteps) * i;
    const val = Math.round(maxVal - (maxVal / gridSteps) * i);
    ctx.beginPath();
    ctx.moveTo(padding.left, y);
    ctx.lineTo(width - padding.right, y);
    ctx.stroke();
    ctx.fillText(`₹${val}`, padding.left - 8, y + 4);
  }

  // Draw Bars & X-Axis Labels
  const groupWidth = chartWidth / days.length;
  const barWidth = Math.min(groupWidth * 0.35, 18);
  const gap = 4;

  days.forEach((day, index) => {
    const groupX = padding.left + index * groupWidth + groupWidth / 2;

    // Last Week Bar (Blue #3b82f6)
    const hLast = (lastWeekData[index] / maxVal) * chartHeight;
    const yLast = padding.top + chartHeight - hLast;
    ctx.fillStyle = '#3b82f6';
    ctx.beginPath();
    ctx.roundRect(groupX - barWidth - gap / 2, yLast, barWidth, hLast, [3, 3, 0, 0]);
    ctx.fill();

    // This Week Bar (Green #10b981)
    const hThis = (thisWeekData[index] / maxVal) * chartHeight;
    const yThis = padding.top + chartHeight - hThis;
    ctx.fillStyle = '#10b981';
    ctx.beginPath();
    ctx.roundRect(groupX + gap / 2, yThis, barWidth, hThis, [3, 3, 0, 0]);
    ctx.fill();

    // X-Axis Day Label
    ctx.fillStyle = '#64748b';
    ctx.textAlign = 'center';
    ctx.fillText(day, groupX, height - 12);
  });
}

/**
 * Initialize Dashboard Page Controller
 */
export async function initDashboardPage() {
  try {
    // Step 1: Render Skeleton Loading Shimmer
    renderSkeletonLoading();

    // Step 2: Simulate 1-second backend latency for realistic live feel
    await new Promise((resolve) => setTimeout(resolve, 1000));

    // Step 3: Fetch Dashboard Stats
    const stats = await fetchDashboardStatsApi();
    clearSkeletonLoading();

    if (stats) {
      const todaySales = stats.todaySales ?? 0;
      setText(document.getElementById('tcTodayRev'), formatCurrency(todaySales));
      setText(document.getElementById('tcWeekRev'), formatCurrency(stats.weekSales ?? 0));
      setText(document.getElementById('tcMonthRev'), formatCurrency(stats.monthSales ?? 0));
      setText(document.getElementById('pcWeekAmount'), formatCurrency(stats.weekPurchases ?? 0));
      setText(document.getElementById('pcMonthAmount'), formatCurrency(stats.monthPurchases ?? 0));

      // Task 2: Conditional Empty State check
      if (todaySales === 0 && (stats.weekSales ?? 0) === 0) {
        displayEmptyState();
      }
    } else {
      // Fallback empty state when no backend stats exist
      displayEmptyState();
    }

    // Task 3: Draw Pure Canvas Bar Chart (This Week vs Last Week)
    const thisWeekSales = stats?.chartData?.thisWeek || [1200, 1800, 2400, 1500, 3200, 4100, 2800];
    const lastWeekSales = stats?.chartData?.lastWeek || [900, 1400, 1900, 2100, 2500, 3100, 2200];
    drawWeeklySalesCanvasChart('salesComparisonChart', thisWeekSales, lastWeekSales);

    // Redraw canvas chart on window resize
    window.addEventListener('resize', () => {
      drawWeeklySalesCanvasChart('salesComparisonChart', thisWeekSales, lastWeekSales);
    });

  } catch (err) {
    logger.error('dashboard-page', err);
    clearSkeletonLoading();
    displayEmptyState();
  }
}
