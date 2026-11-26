// 通用 UI 组件：toast / 确认框 / modal / 转义 / 发音 / 格式化

/** HTML 转义，防止 XSS */
export function esc(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/** 轻提示 */
export function toast(message, type = 'info') {
  const root = document.getElementById('toast-root');
  if (!root) return;
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.textContent = message;
  root.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  setTimeout(() => {
    el.classList.remove('show');
    setTimeout(() => el.remove(), 300);
  }, 2600);
}

/** 通用模态框 */
export function openModal({ title, body = '', footer = '', onMount, wide = false }) {
  const root = document.getElementById('modal-root');
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.innerHTML = `
    <div class="modal${wide ? ' modal-wide' : ''}">
      <div class="modal-head">
        <div class="modal-title">${esc(title)}</div>
        <button class="modal-close" type="button" aria-label="关闭">&times;</button>
      </div>
      <div class="modal-body">${body}</div>
      ${footer ? `<div class="modal-foot">${footer}</div>` : ''}
    </div>
  `;
  root.appendChild(overlay);

  const bodyEl = overlay.querySelector('.modal-body');
  const onKey = (e) => { if (e.key === 'Escape') close(); };
  const close = () => {
    overlay.remove();
    document.removeEventListener('keydown', onKey);
  };

  overlay.querySelector('.modal-close').addEventListener('click', close);
  overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
  document.addEventListener('keydown', onKey);

  if (onMount) onMount({ body: bodyEl, close, overlay });
  return { close, overlay, body: bodyEl };
}

/** 确认框，返回 Promise<boolean> */
export function confirmDialog(message, opts = {}) {
  const {
    title = '确认操作',
    danger = false,
    okText = '确定',
    cancelText = '取消',
  } = opts;
  return new Promise((resolve) => {
    const m = openModal({
      title,
      body: `<p class="confirm-msg">${esc(message)}</p>`,
      footer: `
        <button class="btn" type="button" data-act="cancel">${esc(cancelText)}</button>
        <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" type="button" data-act="ok">${esc(okText)}</button>
      `,
    });
    m.overlay.querySelector('[data-act="ok"]').addEventListener('click', () => { m.close(); resolve(true); });
    m.overlay.querySelector('[data-act="cancel"]').addEventListener('click', () => { m.close(); resolve(false); });
  });
}

/** 加载中占位 */
export function spinner(label = '加载中…') {
  return `<div class="loading"><span class="spinner"></span>${esc(label)}</div>`;
}

/** 空态占位 */
export function emptyState(text, actionHtml = '') {
  return `<div class="empty">${esc(text)}${actionHtml ? `<div class="empty-action">${actionHtml}</div>` : ''}</div>`;
}

/** 格式化 MySQL 日期时间 */
export function fmtDate(value) {
  if (!value) return '';
  const s = String(value);
  const d = new Date(s.includes('T') ? s : s.replace(' ', 'T'));
  if (isNaN(d.getTime())) return s;
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

const TYPE_LABELS = { choice: '选择题', spelling: '拼写题', dictation: '听写题', matching: '配对题' };
export function typeLabel(type) {
  return TYPE_LABELS[type] || type;
}

/** 数字兜底：后端可能返回字符串 */
export function num(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}

/** 发音：有音频链接用 audio，否则用浏览器 speechSynthesis */
export function speak(text, audioUrl) {
  if (audioUrl) {
    const audio = new Audio(audioUrl);
    audio.play().catch(() => fallbackSpeech(text));
  } else {
    fallbackSpeech(text);
  }
}

function fallbackSpeech(text) {
  if (!('speechSynthesis' in window) || !text) return;
  try {
    window.speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'en-US';
    u.rate = 0.9;
    window.speechSynthesis.speak(u);
  } catch (e) {
    /* 忽略浏览器不支持的情况 */
  }
}
