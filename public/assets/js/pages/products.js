/**
 * products.js — Products Master page controller module
 */

import { fetchProductsApi } from '../services/product.service.js';
import { logger } from '../core/logger.js';

export async function initProductsPage() {
  try {
    const products = await fetchProductsApi();
    logger.debug('products', 'Products loaded:', products?.length ?? 0);
  } catch (err) {
    logger.error('products', err);
  }
}
