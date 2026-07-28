/**
 * inventory.js — Inventory page controller module
 */

import { fetchInventoryItemsApi } from '../services/inventory.service.js';
import { logger } from '../core/logger.js';

export async function initInventoryPage() {
  try {
    const items = await fetchInventoryItemsApi();
    logger.debug('inventory', 'Items loaded:', items?.length ?? 0);
  } catch (err) {
    logger.error('inventory', err);
  }
}
