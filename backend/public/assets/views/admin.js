// 管理员后台：举报处理
import { api } from '/assets/api.js';
import { esc, toast, confirmDialog, spinner, emptyState, fmtDate } from '/assets/ui.js';

const STATUS = {
  pending: { label: '待处理', cls: 'badge-pending' },
  resolved: { label: '已下架', cls: 'badge-resolved' },
  rejected: { label: '已驳回', cls: 'badge-rejected' },
};

export default function adminView({ container }) {
  container.innerHTML = `
    <div class="page-head">
      <h1 class="page-title">举报管理</h1>
    </div>
    <div id="admin-body">${spinner()}</div>
  `;

  container.querySelector('#admin-body').addEventListener('click', onBodyClick);

  load();

  async function load() {
    const body = container.querySelector('#admin-body');
    body.innerHTML = spinner();
    try {
      const reports = await api.adminReports();
      render(reports);
    } catch (e) {
      body.innerHTML = emptyState('加载失败：' + e.message);
    }
  }

  function render(reports) {
    const body = container.querySelector('#admin-body');
    if (!reports.length) {
      body.innerHTML = emptyState('暂无举报');
      return;
    }
    body.innerHTML = `<div class="card">${reports.map(item).join('')}</div>`;
  }

  function onBodyClick(e) {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const id = btn.dataset.id;
    const action = btn.dataset.act;
    if (action === 'takedown') handle(id, 'takedown', '确认下架该词库？');
    else if (action === 'reject') handle(id, 'reject', '确认驳回该举报？');
  }

  function item(r) {
    const st = STATUS[r.status] || STATUS.pending;
    return `
      <div class="report-item">
        <div class="row">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <strong>${esc(r.wordbook_name || '—')}</strong>
            <span class="badge ${st.cls}">${st.label}</span>
          </div>
          ${r.status === 'pending' ? `
            <div style="display:flex;gap:8px;">
              <button class="btn btn-sm btn-danger" data-act="takedown" data-id="${r.id}">下架并处理</button>
              <button class="btn btn-sm" data-act="reject" data-id="${r.id}">驳回</button>
            </div>` : ''}
        </div>
        <div class="reason">举报原因：${esc(r.reason || '—')}</div>
        <div class="meta">举报人：${esc(r.reporter_username || '—')} · 时间：${fmtDate(r.created_at)}</div>
      </div>`;
  }

  async function handle(id, action, msg) {
    const ok = await confirmDialog(msg, { title: '处理举报', danger: action === 'takedown', okText: action === 'takedown' ? '下架' : '驳回' });
    if (!ok) return;
    try {
      await api.adminHandle(id, action);
      toast('已处理', 'success');
      load();
    } catch (e) { toast(e.message, 'error'); }
  }
}
