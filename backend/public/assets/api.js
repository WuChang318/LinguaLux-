// fetch 封装 + 全部 API 函数
import { store } from '/assets/store.js';

const BASE = '/api';

export class ApiError extends Error {
  constructor(message, code) {
    super(message);
    this.name = 'ApiError';
    this.code = code;
  }
}

async function request(path, { method = 'GET', body } = {}) {
  const opts = { method, headers: {} };
  const token = store.getToken();
  if (token) opts.headers['Authorization'] = 'Bearer ' + token;
  if (body !== undefined) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(BASE + path, opts);
  } catch (e) {
    throw new ApiError('网络错误，请检查网络连接', 0);
  }

  let json = null;
  try {
    json = await res.json();
  } catch (e) {
    /* 非 JSON 响应 */
  }

  const code = json && typeof json.code === 'number' ? json.code : res.status;
  if (!res.ok || !json || json.code !== 0) {
    const message = (json && json.msg) || '请求失败，请稍后再试';
    if (res.status === 401 || code === 401) {
      store.clear();
      if (!location.hash.startsWith('#/login')) location.hash = '#/login';
    }
    throw new ApiError(message, code);
  }
  return json.data;
}

function qs(params = {}) {
  const seg = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
  return seg.length ? '?' + seg.join('&') : '';
}

export const api = {
  // 认证
  register(username, password) { return request('/auth/register', { method: 'POST', body: { username, password } }); },
  login(username, password) { return request('/auth/login', { method: 'POST', body: { username, password } }); },
  logout() { return request('/auth/logout', { method: 'POST' }); },
  me() { return request('/auth/me'); },

  // 词库
  listWordbooks() { return request('/wordbooks'); },
  createWordbook(data) { return request('/wordbooks', { method: 'POST', body: data }); },
  updateWordbook(id, data) { return request(`/wordbooks/${id}`, { method: 'PUT', body: data }); },
  deleteWordbook(id) { return request(`/wordbooks/${id}`, { method: 'DELETE' }); },
  publishWordbook(id) { return request(`/wordbooks/${id}/publish`, { method: 'POST' }); },
  unpublishWordbook(id) { return request(`/wordbooks/${id}/unpublish`, { method: 'POST' }); },

  // 单词
  listWords(wordbookId) { return request(`/wordbooks/${wordbookId}/words`); },
  createWord(wordbookId, data) { return request(`/wordbooks/${wordbookId}/words`, { method: 'POST', body: data }); },
  importWords(wordbookId, words) { return request(`/wordbooks/${wordbookId}/words/import`, { method: 'POST', body: { words } }); },
  autofillWordbook(wordbookId, wordIds) { return request(`/wordbooks/${wordbookId}/autofill`, { method: 'POST', body: (wordIds && wordIds.length) ? { word_ids: wordIds } : {} }); },
  updateWord(id, data) { return request(`/words/${id}`, { method: 'PUT', body: data }); },
  deleteWord(id) { return request(`/words/${id}`, { method: 'DELETE' }); },

  // 自动填充
  dict(word) { return request('/dict/' + encodeURIComponent(word)); },

  // 抽考
  generateQuiz(data) { return request('/quiz/generate', { method: 'POST', body: data }); },
  submitQuiz(data) { return request('/quiz/submit', { method: 'POST', body: data }); },
  stats() { return request('/stats'); },

  // 广场
  market(params) { return request('/market' + qs(params)); },
  marketDetail(id) { return request(`/market/${id}`); },
  marketUse(id) { return request(`/market/${id}/use`, { method: 'POST' }); },
  marketReport(id, reason) { return request(`/market/${id}/report`, { method: 'POST', body: { reason } }); },

  // 管理
  adminReports() { return request('/admin/reports'); },
  adminHandle(id, action) { return request(`/admin/reports/${id}/handle`, { method: 'POST', body: { action } }); },
};
