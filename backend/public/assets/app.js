// 应用入口：哈希路由 + 顶部导航 + 视图挂载
import { store } from '/assets/store.js';
import { api } from '/assets/api.js';
import { esc, toast } from '/assets/ui.js';

import authView from '/assets/views/auth.js';
import wordbooksView from '/assets/views/wordbooks.js';
import wordsView from '/assets/views/words.js';
import quizView from '/assets/views/quiz.js';
import marketView from '/assets/views/market.js';
import statsView from '/assets/views/stats.js';
import adminView from '/assets/views/admin.js';

const appEl = document.getElementById('app');
const topbarEl = document.getElementById('topbar');

const routes = [
  { seg: ['login'], view: authView, mode: 'login', public: true },
  { seg: ['register'], view: authView, mode: 'register', public: true },
  { seg: ['wordbooks'], view: wordbooksView },
  { seg: ['wordbooks', ':id', 'words'], view: wordsView },
  { seg: ['quiz'], view: quizView },
  { seg: ['market'], view: marketView },
  { seg: ['stats'], view: statsView },
  { seg: ['admin'], view: adminView },
];

let seq = 0;

window.addEventListener('hashchange', render);
render();

async function render() {
  const my = ++seq;
  const { path, query } = parseHash();

  // 公开页：登录 / 注册
  if (path === '/login' || path === '/register') {
    hideTopbar();
    const route = routes.find((r) => r.seg[0] === path.slice(1));
    if (route) route.view({ container: appEl, mode: route.mode });
    return;
  }

  // 受保护页：需要登录
  if (!store.getToken()) {
    location.hash = '#/login';
    return;
  }

  // 有 token 但本地无用户信息时，向后端校验一次
  if (!store.getUser()) {
    try {
      const user = await api.me();
      if (my !== seq) return;
      store.setUser(user);
    } catch (e) {
      return; // api.js 已在 401 时清除并跳转登录
    }
  }

  const match = matchRoute(path);
  if (!match) {
    location.hash = '#/wordbooks';
    return;
  }

  if (match.route.seg[0] === 'admin' && !store.isAdmin()) {
    toast('无权限访问管理后台', 'warn');
    location.hash = '#/wordbooks';
    return;
  }

  renderTopbar();
  const args = { container: appEl, params: match.params, query };
  if (match.route.mode) args.mode = match.route.mode;
  match.route.view(args);
}

function parseHash() {
  const raw = location.hash ? location.hash.slice(1) : '/';
  const qi = raw.indexOf('?');
  const pathPart = qi === -1 ? raw : raw.slice(0, qi);
  const queryPart = qi === -1 ? '' : raw.slice(qi + 1);
  const path = pathPart === '' ? '/' : pathPart;
  const query = {};
  if (queryPart) {
    for (const seg of queryPart.split('&')) {
      if (!seg) continue;
      const eq = seg.indexOf('=');
      const k = eq === -1 ? seg : seg.slice(0, eq);
      const v = eq === -1 ? '' : seg.slice(eq + 1);
      try {
        query[decodeURIComponent(k)] = decodeURIComponent(v);
      } catch (e) { /* 忽略非法编码 */ }
    }
  }
  return { path, query };
}

function matchRoute(path) {
  const parts = path.split('/').filter(Boolean);
  for (const r of routes) {
    if (r.public) continue;
    if (r.seg.length !== parts.length) continue;
    const params = {};
    let ok = true;
    for (let i = 0; i < r.seg.length; i++) {
      const s = r.seg[i];
      if (s.startsWith(':')) params[s.slice(1)] = decodeURIComponent(parts[i]);
      else if (s !== parts[i]) { ok = false; break; }
    }
    if (ok) return { route: r, params };
  }
  return null;
}

function renderTopbar() {
  const user = store.getUser();
  const isAdmin = store.isAdmin();
  const { path } = parseHash();

  const links = [
    { href: '#/wordbooks', label: '词库', seg: 'wordbooks' },
    { href: '#/market', label: '广场', seg: 'market' },
    { href: '#/quiz', label: '抽考', seg: 'quiz' },
    { href: '#/stats', label: '统计', seg: 'stats' },
  ];
  if (isAdmin) links.push({ href: '#/admin', label: '管理', seg: 'admin' });

  const isActive = (seg) => (seg === 'wordbooks' ? path.startsWith('/wordbooks') : path === '/' + seg);

  topbarEl.hidden = false;
  topbarEl.innerHTML = `
    <div class="topbar-inner">
      <a class="brand" href="#/wordbooks">LinguaLux</a>
      <nav class="nav">
        ${links.map((l) => `<a href="${l.href}" class="${isActive(l.seg) ? 'active' : ''}">${l.label}</a>`).join('')}
      </nav>
      <div class="user">
        <span class="username">${esc(user ? user.username : '')}</span>
        <button class="btn btn-ghost btn-sm" id="logout-btn">退出</button>
      </div>
    </div>
  `;
  topbarEl.querySelector('#logout-btn').addEventListener('click', logout);
}

function hideTopbar() {
  topbarEl.hidden = true;
  topbarEl.innerHTML = '';
}

async function logout() {
  try { await api.logout(); } catch (e) { /* 忽略 */ }
  store.clear();
  location.hash = '#/login';
}
