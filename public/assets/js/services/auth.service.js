/**
 * auth.service.js — Auth API calls
 */

import { apiRequest } from '../core/api.js';

export async function loginApi(username, password) {
  return apiRequest('/api/auth/login', {
    method: 'POST',
    body: JSON.stringify({ username, password }),
  });
}

export async function forgotPasswordApi(username, securityAnswer, newPassword) {
  return apiRequest('/api/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify({ username, security_answer: securityAnswer, new_password: newPassword }),
  });
}

export async function resetPasswordApi(token, newPassword) {
  return apiRequest('/api/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify({ token, new_password: newPassword }),
  });
}
