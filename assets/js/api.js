/**
 * API utility module
 * Wraps fetch with auth headers, error handling, and JSON parsing.
 * Requirements: 13.1-13.5
 */

const API_BASE = '/api';

/** In-memory session token (never persisted to localStorage for security) */
let _token = null;

const Api = {
  setToken(token) { _token = token; },
  clearToken()    { _token = null; },
  hasToken()      { return !!_token; },

  /**
   * Core fetch wrapper
   * @param {string} path  - e.g. '/auth/login'
   * @param {object} opts  - fetch options override
   */
  async request(path, opts = {}) {
    const headers = { 'Content-Type': 'application/json', ...(opts.headers || {}) };
    if (_token) headers['Authorization'] = `Bearer ${_token}`;

    const res = await fetch(`${API_BASE}${path}`, {
      ...opts,
      headers,
    });

    let body;
    try { body = await res.json(); } catch { body = {}; }

    if (!res.ok) {
      const msg = body?.error?.message || `Request failed (${res.status})`;
      const err = new Error(msg);
      err.code   = body?.error?.code || 'API_ERROR';
      err.status = res.status;
      throw err;
    }

    return body;
  },

  // Session check (page load)
  checkSession: () => Api.request('/auth/session.php'),

  // Auth
  register: (email, password, passwordConfirm) =>
    Api.request('/auth/register.php', {
      method: 'POST',
      body: JSON.stringify({ email, password, password_confirm: passwordConfirm }),
    }),

  login: (email, password) =>
    Api.request('/auth/login.php', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),

  logout: () =>
    Api.request('/auth/logout.php', { method: 'POST' }),

  // Notes
  getNotes: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return Api.request(`/notes.php${qs ? '?' + qs : ''}`);
  },

  getNote: (id) => Api.request(`/notes.php/${id}`),

  createNote: (title, content, tags) =>
    Api.request('/notes.php', {
      method: 'POST',
      body: JSON.stringify({ title, content, tags }),
    }),

  updateNote: (id, title, content, tags) =>
    Api.request(`/notes.php/${id}`, {
      method: 'PUT',
      body: JSON.stringify({ title, content, tags }),
    }),

  deleteNote: (id) =>
    Api.request(`/notes.php/${id}`, { method: 'DELETE' }),

  // Search
  search: (q) => Api.request(`/search.php?q=${encodeURIComponent(q)}`),

  // Tags
  getTags: () => Api.request('/tags.php'),
};
