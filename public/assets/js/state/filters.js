/**
 * filters.js — Global search & filter state
 */

let _productFilter = { query: '', category: '', page: 1 };

export function getProductFilter() {
  return { ..._productFilter };
}

export function setProductFilter(newFilters) {
  _productFilter = { ..._productFilter, ...newFilters };
}

export function resetProductFilter() {
  _productFilter = { query: '', category: '', page: 1 };
}
