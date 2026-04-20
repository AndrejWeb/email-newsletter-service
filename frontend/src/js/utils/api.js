import $ from 'jquery';

const API_BASE = '/api';
let authToken = localStorage.getItem('newsletter_token');
let currentUser = null;

function getHeaders(isJson = true) {
  const headers = {};
  if (authToken) headers['Authorization'] = `Bearer ${authToken}`;
  if (isJson) headers['Content-Type'] = 'application/json';
  return headers;
}

async function request(method, url, data = null, isFormData = false) {
  const opts = {
    method,
    headers: getHeaders(!isFormData),
  };

  if (data && !isFormData) {
    opts.body = JSON.stringify(data);
  } else if (data && isFormData) {
    opts.body = data;
    delete opts.headers['Content-Type'];
  }

  const resp = await fetch(`${API_BASE}${url}`, opts);

  if (resp.status === 204) return null;

  const contentType = resp.headers.get('content-type') || '';
  if (contentType.includes('text/csv')) {
    return { csv: await resp.text(), headers: resp.headers };
  }

  if (contentType.includes('text/html') && !url.includes('/auth/')) {
    return { html: await resp.text() };
  }

  let json;
  try {
    json = await resp.json();
  } catch {
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return null;
  }

  if (!resp.ok) {
    throw new Error(json?.error || json?.message || `HTTP ${resp.status}`);
  }

  return json;
}

const api = {
  get: (url) => request('GET', url),
  post: (url, data) => request('POST', url, data),
  put: (url, data) => request('PUT', url, data),
  delete: (url) => request('DELETE', url),
  upload: (url, formData) => request('POST', url, formData, true),

  setToken(token) {
    authToken = token;
    if (token) {
      localStorage.setItem('newsletter_token', token);
    } else {
      localStorage.removeItem('newsletter_token');
    }
  },

  getToken() {
    return authToken;
  },

  setUser(user) {
    currentUser = user;
  },

  getUser() {
    return currentUser;
  },

  isAuthenticated() {
    return !!authToken;
  },

  logout() {
    authToken = null;
    currentUser = null;
    localStorage.removeItem('newsletter_token');
  }
};

export default api;
