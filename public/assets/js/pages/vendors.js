/**
 * vendors.js — Vendor management page controller module
 */

import { fetchVendorsApi } from '../services/vendor.service.js';
import { logger } from '../core/logger.js';

export async function initVendorsPage() {
  try {
    const vendors = await fetchVendorsApi();
    logger.debug('vendors', 'Vendors loaded:', vendors?.length ?? 0);
  } catch (err) {
    logger.error('vendors', err);
  }
}
