/**
 * user.js — User session state holder
 */

import { getUser, setUser as setStorageUser, clearUser } from '../core/storage.js';

let _currentUser = getUser();

export function getCurrentUser() {
  return _currentUser;
}

export function setCurrentUser(user) {
  _currentUser = user;
  setStorageUser(user);
}

export function clearCurrentUser() {
  _currentUser = null;
  clearUser();
}

export function isAdmin() {
  return _currentUser?.role === 'admin';
}
