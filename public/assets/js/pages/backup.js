/**
 * backup.js — Database Backup & Restore page controller module
 */

import { fetchBackupsApi, createBackupApi } from '../services/backup.service.js';
import { logger } from '../core/logger.js';

export async function initBackupPage() {
  try {
    const backups = await fetchBackupsApi();
    logger.debug('backup', 'Backups loaded:', backups);
  } catch (err) {
    logger.error('backup', err);
  }
}
