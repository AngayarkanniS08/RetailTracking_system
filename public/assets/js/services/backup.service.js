/**
 * backup.service.js — Database backup & restore API service
 */

import { apiRequest } from '../core/api.js';

export async function fetchBackupsApi() {
  return apiRequest('/api/backup/list');
}

export async function createBackupApi() {
  return apiRequest('/api/backup/create', { method: 'POST' });
}

export async function restoreBackupApi(filename) {
  return apiRequest('/api/backup/restore', {
    method: 'POST',
    body: JSON.stringify({ filename }),
  });
}
