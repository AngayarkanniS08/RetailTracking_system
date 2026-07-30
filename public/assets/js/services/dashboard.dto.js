export function mapDashboardStats(raw) {
  if (!raw || typeof raw !== 'object') return null;

  return {
    period: raw.period ?? null,
    executive_kpis: {
      revenue: {
        value: toFloat(raw.executive_kpis?.revenue?.value),
        growth_pct: toFloat(raw.executive_kpis?.revenue?.growth_pct),
        prev_value: toFloat(raw.executive_kpis?.revenue?.prev_value),
      },
      bills: {
        count: toInt(raw.executive_kpis?.bills?.count),
        avg_ticket: toFloat(raw.executive_kpis?.bills?.avg_ticket),
      },
      profit: {
        value: toNullableFloat(raw.executive_kpis?.profit?.value),
        margin_pct: toNullableFloat(raw.executive_kpis?.profit?.margin_pct),
      },
      outstanding_credit: toNullableFloat(raw.executive_kpis?.outstanding_credit),
    },
    sales_summary: {
      revenue: toFloat(raw.sales_summary?.revenue),
      bills: toInt(raw.sales_summary?.bills),
      avg_ticket: toFloat(raw.sales_summary?.avg_ticket),
      growth_pct: toFloat(raw.sales_summary?.growth_pct),
    },
    purchase_summary: {
      amount: toNullableFloat(raw.purchase_summary?.amount),
      count: toInt(raw.purchase_summary?.count),
      paid: toNullableFloat(raw.purchase_summary?.paid),
      pending: toNullableFloat(raw.purchase_summary?.pending),
      avg_purchase: toNullableFloat(raw.purchase_summary?.avg_purchase),
    },
    today: mapDay(raw.today),
    week: mapDay(raw.week),
    month: mapDay(raw.month),
    purchase_week: mapPurchasePeriod(raw.purchase_week),
    purchase_month: mapPurchasePeriod(raw.purchase_month),
    total_bills: toInt(raw.total_bills),
    outstanding_credit: toNullableFloat(raw.outstanding_credit),
    stock_value: toNullableFloat(raw.stock_value),
    chartData: {
      labels: Array.isArray(raw.chartData?.labels) ? raw.chartData.labels : null,
      thisWeek: Array.isArray(raw.chartData?.thisWeek) ? raw.chartData.thisWeek.map(toFloat) : null,
      lastWeek: Array.isArray(raw.chartData?.lastWeek) ? raw.chartData.lastWeek.map(toFloat) : null,
    },
  };
}

export function mapStockIntel(raw) {
  if (!raw || typeof raw !== 'object') return null;

  return {
    high_selling: mapProductList(raw.high_selling),
    low_selling: mapProductList(raw.low_selling),
    normal_selling: mapProductList(raw.normal_selling),
    new_products: mapProductList(raw.new_products),
    old_stock: Array.isArray(raw.old_stock) ? raw.old_stock.map(mapOldStockItem) : [],
    avg_velocity: toFloat(raw.avg_velocity),
    inventory_health: {
      total_products: toInt(raw.inventory_health?.total_products),
      out_of_stock: toInt(raw.inventory_health?.out_of_stock),
      low_stock: toInt(raw.inventory_health?.low_stock),
      healthy_count: toInt(raw.inventory_health?.healthy_count),
    },
    alert_summary: {
      status: raw.alert_summary?.status ?? 'no_data',
    },
  };
}

function mapDay(d) {
  if (!d) return { revenue: null, bills: null, avg: null };
  return {
    revenue: toFloat(d.revenue),
    bills: toInt(d.bills),
    avg: toFloat(d.avg),
  };
}

function mapPurchasePeriod(p) {
  if (!p) return { amount: null, count: null, paid: null };
  return {
    amount: toFloat(p.amount),
    count: toInt(p.count),
    paid: toFloat(p.paid),
  };
}

function mapProductList(list) {
  if (!Array.isArray(list)) return [];
  return list.map(p => ({
    product_id: p.product_id ?? null,
    name: p.name ?? 'Unknown',
    qty_sold: toNullableInt(p.qty_sold),
    revenue: toNullableFloat(p.revenue),
    velocity: toFloat(p.velocity),
    stock_status: p.stock_status ?? 'unknown',
  }));
}

function mapOldStockItem(o) {
  return {
    product_id: o.product_id ?? null,
    name: o.name ?? 'Unknown',
    batch: o.batch ?? '',
    age_days: toInt(o.age_days),
    qty: toInt(o.qty),
    remaining_pct: toFloat(o.remaining_pct),
    velocity: toFloat(o.velocity),
  };
}

function toFloat(v) {
  if (v === null || v === undefined || v === false) return 0;
  const n = parseFloat(v);
  return isNaN(n) ? 0 : n;
}

function toInt(v) {
  if (v === null || v === undefined || v === false) return 0;
  const n = parseInt(v, 10);
  return isNaN(n) ? 0 : n;
}

function toNullableFloat(v) {
  if (v === null || v === undefined || v === false) return null;
  const n = parseFloat(v);
  return isNaN(n) ? null : n;
}

function toNullableInt(v) {
  if (v === null || v === undefined || v === false) return null;
  const n = parseInt(v, 10);
  return isNaN(n) ? null : n;
}
