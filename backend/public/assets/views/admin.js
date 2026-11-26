// 管理员后台：举报 / 用户 / 设置 / 公告 / 捐款 / 邮件
import { api } from '/assets/api.js';
import { esc, toast, confirmDialog, openModal, spinner, emptyState, fmtDate } from '/assets/ui.js';

const TABS = [
  { key: 'reports', label: '举报处理' },
  { key: 'users', label: '用户管理' },
  { key: 'settings', label: '站点设置' },
  { key: 'announcements', label: '公告管理' },
  { key: 'donations', label: '捐款渠道' },
  { key: 'email', label: '邮件设置' },
];

const GROUP_LABELS = { limits: '用户限制', session: '会话', email: '邮件', home: '首页', donation: '捐款' };
const STATUS = {
  pending: { label: '待处理', cls: 'badge-pending' },
  resolved: { label: '已下架', cls: 'badge-resolved' },
  rejected: { label: '已驳回', cls: 'badge-rejected' },
};

let userState = { page: 1, q: '' };

export default function adminView({ container }) {
  container.innerHTML = `
    <div class="admin-layout">
      <aside class="admin-side">
        ${TABS.map((t) => `<button class="admin-tab" data-tab="${t.key}">${t.label}</button>`).join('')}
      </aside>
      <div class="admin-main" id="admin-main"></div>
    </div>
  `;

  container.querySelector('.admin-side').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-tab]');
    if (!btn) return;
    switchTab(btn.dataset.tab);
  });

  switchTab('reports');

  function switchTab(key) {
    container.querySelectorAll('.admin-tab').forEach((b) => b.classList.toggle('active', b.dataset.tab === key));
    const main = container.querySelector('#admin-main');
    main.innerHTML = spinner();
    const loaders = {
      reports: loadReports,
      users: loadUsers,
      settings: loadSettings,
      announcements: loadAnnouncements,
      donations: loadDonations,
      email: loadEmail,
    };
    loaders[key](main);
  }
}

/* ---------- 举报 ---------- */

async function loadReports(main) {
  try {
    const reports = await api.adminReports();
    renderReports(main, reports);
  } catch (e) {
    main.innerHTML = emptyState('加载失败：' + e.message);
  }
}

function renderReports(main, reports) {
  main.innerHTML = `<div class="page-head"><h2 class="page-title">举报处理</h2></div>`;
  if (!reports.length) {
    main.innerHTML += emptyState('暂无举报');
    return;
  }
  const box = document.createElement('div');
  box.className = 'card';
  box.innerHTML = reports.map(reportItem).join('');
  main.appendChild(box);
  box.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const id = btn.dataset.id;
    if (btn.dataset.act === 'takedown') handleReport(main, id, 'takedown', '确认下架该词库？');
    else if (btn.dataset.act === 'reject') handleReport(main, id, 'reject', '确认驳回该举报？');
  });
}

function reportItem(r) {
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

async function handleReport(main, id, action, msg) {
  const ok = await confirmDialog(msg, { title: '处理举报', danger: action === 'takedown', okText: action === 'takedown' ? '下架' : '驳回' });
  if (!ok) return;
  try {
    await api.adminHandle(id, action);
    toast('已处理', 'success');
    loadReports(main);
  } catch (e) {
    toast(e.message, 'error');
  }
}

/* ---------- 用户 ---------- */

async function loadUsers(main) {
  try {
    const data = await api.adminUsers({ page: userState.page, size: 20, q: userState.q });
    renderUsers(main, data);
  } catch (e) {
    main.innerHTML = emptyState('加载失败：' + e.message);
  }
}

function renderUsers(main, data) {
  const list = data.list || [];
  main.innerHTML = `
    <div class="page-head"><h2 class="page-title">用户管理</h2></div>
    <div class="market-search">
      <input class="form-input" id="user-q" placeholder="搜索用户名 / 邮箱" value="${esc(userState.q)}">
      <button class="btn" id="user-search">搜索</button>
    </div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>ID</th><th>用户名</th><th>邮箱</th><th>角色</th><th>词本数</th><th>填充用量</th><th>词本上限</th><th>填充上限</th><th>操作</th></tr></thead>
      <tbody id="users-body">
        ${list.map(userRow).join('') || '<tr><td colspan="9" style="text-align:center;color:var(--text-muted);">暂无用户</td></tr>'}
      </tbody>
    </table></div>
    ${pagination(data.total, data.page, data.size)}
  `;

  const doSearch = () => { userState.q = main.querySelector('#user-q').value.trim(); userState.page = 1; loadUsers(main); };
  main.querySelector('#user-search').addEventListener('click', doSearch);
  main.querySelector('#user-q').addEventListener('keydown', (e) => { if (e.key === 'Enter') doSearch(); });
  main.querySelector('#users-body').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const u = list.find((x) => String(x.id) === btn.dataset.id);
    if (!u) return;
    if (btn.dataset.act === 'edit') openUserForm(main, u);
    else if (btn.dataset.act === 'admin') toggleRole(main, u);
  });
  const pg = main.querySelector('#users-pagination');
  if (pg) pg.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-page]');
    if (!btn) return;
    userState.page = Number(btn.dataset.page);
    loadUsers(main);
  });
}

function userRow(u) {
  const wb = (u.wordbook_limit_override === null || u.wordbook_limit_override === undefined) ? '默认' : u.wordbook_limit_override;
  const af = (u.autofill_limit_override === null || u.autofill_limit_override === undefined) ? '默认' : u.autofill_limit_override;
  return `<tr>
    <td>${esc(u.id)}</td>
    <td>${esc(u.username)}</td>
    <td>${esc(u.email || '—')}${Number(u.email_verified) ? ' <span class="badge badge-public">已验</span>' : ''}</td>
    <td>${u.role === 'admin' ? '<span class="badge badge-resolved">管理员</span>' : '<span class="badge badge-private">用户</span>'}</td>
    <td>${esc(u.wordbook_count)}</td>
    <td>${esc(u.autofill_used)}</td>
    <td>${esc(wb)}</td>
    <td>${esc(af)}</td>
    <td><div style="display:flex;gap:6px;">
      <button class="btn btn-sm" data-act="edit" data-id="${u.id}">编辑</button>
      <button class="btn btn-sm ${u.role === 'admin' ? 'btn-danger' : ''}" data-act="admin" data-id="${u.id}">${u.role === 'admin' ? '降为用户' : '设为管理员'}</button>
    </div></td>
  </tr>`;
}

function openUserForm(main, u) {
  const m = openModal({
    title: `编辑用户 ${u.username}`,
    body: `
      <div class="form-group"><label class="form-label">词本上限（留空 = 跟随站点默认）</label>
        <input class="form-input" type="number" id="u-wb" value="${u.wordbook_limit_override === null || u.wordbook_limit_override === undefined ? '' : esc(u.wordbook_limit_override)}" placeholder="默认"></div>
      <div class="form-group"><label class="form-label">一键填充周期上限（留空 = 跟随站点默认）</label>
        <input class="form-input" type="number" id="u-af" value="${u.autofill_limit_override === null || u.autofill_limit_override === undefined ? '' : esc(u.autofill_limit_override)}" placeholder="默认"></div>
      <div class="form-hint">0 表示不限制；留空表示跟随站点默认设置。</div>
    `,
    footer: `<button class="btn" data-act="cancel">取消</button><button class="btn btn-primary" data-act="save">保存</button>`,
  });
  m.overlay.querySelector('[data-act="cancel"]').addEventListener('click', () => m.close());
  m.overlay.querySelector('[data-act="save"]').addEventListener('click', async () => {
    const wb = m.body.querySelector('#u-wb').value.trim();
    const af = m.body.querySelector('#u-af').value.trim();
    try {
      await api.adminUpdateUser(u.id, {
        wordbook_limit_override: wb === '' ? null : Number(wb),
        autofill_limit_override: af === '' ? null : Number(af),
      });
      toast('已保存', 'success');
      m.close();
      loadUsers(main);
    } catch (e) { toast(e.message, 'error'); }
  });
}

async function toggleRole(main, u) {
  const newRole = u.role === 'admin' ? 'user' : 'admin';
  const ok = await confirmDialog(`确认将 ${u.username} ${newRole === 'admin' ? '设为管理员' : '降为普通用户'}？`, { danger: newRole !== 'admin', okText: '确认' });
  if (!ok) return;
  try {
    await api.adminUpdateUser(u.id, { role: newRole });
    toast('已更新', 'success');
    loadUsers(main);
  } catch (e) { toast(e.message, 'error'); }
}

function pagination(total, page, size) {
  const pages = Math.max(1, Math.ceil((total || 0) / (size || 20)));
  if (pages <= 1) return '';
  return `<div class="pagination" id="users-pagination">
    <button class="btn btn-sm" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>上一页</button>
    <span>第 ${page} / ${pages} 页</span>
    <button class="btn btn-sm" data-page="${page + 1}" ${page >= pages ? 'disabled' : ''}>下一页</button>
  </div>`;
}

/* ---------- 设置 ---------- */

async function loadSettings(main) {
  try {
    const grouped = await api.adminSettings();
    renderSettings(main, grouped);
  } catch (e) {
    main.innerHTML = emptyState('加载失败：' + e.message);
  }
}

function renderSettings(main, grouped) {
  const sections = Object.keys(grouped).map((g) => {
    const fields = Object.keys(grouped[g]).map((key) => renderSettingField(key, grouped[g][key])).join('');
    return `<div class="card" style="padding:20px;margin-bottom:16px;">
      <h3 style="margin:0 0 14px;">${esc(GROUP_LABELS[g] || g)}</h3>
      <div>${fields}</div>
    </div>`;
  }).join('');
  main.innerHTML = `
    <div class="page-head">
      <h2 class="page-title">站点设置</h2>
      <button class="btn btn-primary" id="save-settings">保存设置</button>
    </div>
    ${sections}
  `;
  main.querySelector('#save-settings').addEventListener('click', async () => {
    const payload = {};
    main.querySelectorAll('[data-key]').forEach((el) => {
      const key = el.dataset.key;
      const type = el.dataset.type;
      if (type === 'bool') payload[key] = el.checked;
      else if (type === 'int') payload[key] = el.value === '' ? 0 : Number(el.value);
      else payload[key] = el.value;
    });
    try {
      await api.adminSaveSettings(payload);
      toast('设置已保存', 'success');
    } catch (e) { toast(e.message, 'error'); }
  });
}

function renderSettingField(key, it) {
  const val = it.value;
  let control = '';
  if (it.type === 'bool') {
    control = `<label class="switch-row"><input type="checkbox" data-key="${key}" data-type="bool" ${val ? 'checked' : ''}><span>${esc(it.label)}</span></label>`;
  } else if (it.type === 'int') {
    control = `<div class="form-group"><label class="form-label">${esc(it.label)}</label>
      <input type="number" class="form-input" data-key="${key}" data-type="int" value="${esc(val)}"></div>`;
  } else if (it.type === 'select') {
    const opts = Object.entries(it.options || {}).map(([v, l]) => `<option value="${esc(v)}" ${String(val) === String(v) ? 'selected' : ''}>${esc(l)}</option>`).join('');
    control = `<div class="form-group"><label class="form-label">${esc(it.label)}</label>
      <select class="form-select" data-key="${key}" data-type="select">${opts}</select></div>`;
  } else {
    const isJson = typeof val === 'string' && (val.trim().startsWith('[') || val.trim().startsWith('{'));
    control = `<div class="form-group"><label class="form-label">${esc(it.label)}</label>
      <textarea class="form-textarea" data-key="${key}" data-type="text" style="min-height:${isJson ? '96px' : '56px'};">${esc(val)}</textarea></div>`;
  }
  const desc = it.desc ? `<div class="form-hint">${esc(it.desc)}</div>` : '';
  return control + desc;
}

/* ---------- 公告 ---------- */

async function loadAnnouncements(main) {
  try {
    const list = await api.adminAnnouncements();
    renderAnnouncements(main, list);
  } catch (e) {
    main.innerHTML = emptyState('加载失败：' + e.message);
  }
}

function renderAnnouncements(main, list) {
  main.innerHTML = `
    <div class="page-head"><h2 class="page-title">公告管理</h2>
      <button class="btn btn-primary" id="new-announcement">新增公告</button></div>
    <div id="ann-list"></div>
  `;
  main.querySelector('#new-announcement').addEventListener('click', () => openAnnouncementForm(main, null));
  const box = main.querySelector('#ann-list');
  if (!list.length) { box.innerHTML = emptyState('暂无公告'); return; }
  box.innerHTML = list.map(announcementItem).join('');
  box.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const a = list.find((x) => String(x.id) === btn.dataset.id);
    if (btn.dataset.act === 'edit' && a) openAnnouncementForm(main, a);
    else if (btn.dataset.act === 'del') deleteAnnouncement(main, btn.dataset.id);
  });
}

function announcementItem(a) {
  return `<div class="card" style="padding:16px 20px;margin-bottom:12px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <strong>${esc(a.title)}</strong>
        ${Number(a.is_pinned) ? '<span class="badge badge-pending">置顶</span>' : ''}
        ${Number(a.is_active) ? '<span class="badge badge-public">显示中</span>' : '<span class="badge badge-rejected">已隐藏</span>'}
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-sm" data-act="edit" data-id="${a.id}">编辑</button>
        <button class="btn btn-sm btn-danger" data-act="del" data-id="${a.id}">删除</button>
      </div>
    </div>
    <div style="color:var(--text-muted);font-size:13px;margin-top:6px;word-break:break-word;">${a.content}</div>
    <div style="color:var(--text-muted);font-size:12px;margin-top:4px;">排序 ${esc(a.sort_order)} · ${fmtDate(a.created_at)}</div>
  </div>`;
}

function openAnnouncementForm(main, a) {
  const isEdit = !!a;
  const m = openModal({
    title: isEdit ? '编辑公告' : '新增公告',
    body: `
      <div class="form-group"><label class="form-label">标题</label>
        <input class="form-input" id="ann-title" value="${esc(a ? a.title : '')}"></div>
      <div class="form-group"><label class="form-label">内容（支持简单 HTML）</label>
        <textarea class="form-textarea" id="ann-content" style="min-height:120px;">${esc(a ? a.content : '')}</textarea></div>
      <div class="form-row">
        <label class="switch-row"><input type="checkbox" id="ann-pinned" ${a && Number(a.is_pinned) ? 'checked' : ''}><span>置顶</span></label>
        <label class="switch-row"><input type="checkbox" id="ann-active" ${!a || Number(a.is_active) ? 'checked' : ''}><span>显示</span></label>
        <div class="form-group grow"><label class="form-label">排序</label>
          <input class="form-input" type="number" id="ann-sort" value="${esc(a ? a.sort_order : 0)}"></div>
      </div>
    `,
    footer: `<button class="btn" data-act="cancel">取消</button><button class="btn btn-primary" data-act="save">保存</button>`,
  });
  m.overlay.querySelector('[data-act="cancel"]').addEventListener('click', () => m.close());
  m.overlay.querySelector('[data-act="save"]').addEventListener('click', async () => {
    const data = {
      title: m.body.querySelector('#ann-title').value.trim(),
      content: m.body.querySelector('#ann-content').value,
      is_pinned: m.body.querySelector('#ann-pinned').checked,
      is_active: m.body.querySelector('#ann-active').checked,
      sort_order: Number(m.body.querySelector('#ann-sort').value) || 0,
    };
    try {
      if (isEdit) await api.adminUpdateAnnouncement(a.id, data);
      else await api.adminCreateAnnouncement(data);
      toast('已保存', 'success');
      m.close();
      loadAnnouncements(main);
    } catch (e) { toast(e.message, 'error'); }
  });
}

async function deleteAnnouncement(main, id) {
  const ok = await confirmDialog('确认删除该公告？', { danger: true, okText: '删除' });
  if (!ok) return;
  try {
    await api.adminDeleteAnnouncement(id);
    toast('已删除', 'success');
    loadAnnouncements(main);
  } catch (e) { toast(e.message, 'error'); }
}

/* ---------- 捐款 ---------- */

async function loadDonations(main) {
  try {
    const list = await api.adminDonations();
    renderDonations(main, list);
  } catch (e) {
    main.innerHTML = emptyState('加载失败：' + e.message);
  }
}

function renderDonations(main, list) {
  main.innerHTML = `
    <div class="page-head"><h2 class="page-title">捐款渠道</h2>
      <button class="btn btn-primary" id="new-donation">新增渠道</button></div>
    <div id="don-list"></div>
  `;
  main.querySelector('#new-donation').addEventListener('click', () => openDonationForm(main, null));
  const box = main.querySelector('#don-list');
  if (!list.length) { box.innerHTML = emptyState('暂无捐款渠道'); return; }
  box.innerHTML = list.map(donationItem).join('');
  box.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const d = list.find((x) => String(x.id) === btn.dataset.id);
    if (btn.dataset.act === 'edit' && d) openDonationForm(main, d);
    else if (btn.dataset.act === 'del') deleteDonation(main, btn.dataset.id);
  });
}

function donationItem(d) {
  return `<div class="card" style="padding:16px 20px;margin-bottom:12px;display:flex;gap:14px;align-items:center;">
    ${d.image_url ? `<img src="${esc(d.image_url)}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">` : ''}
    <div style="flex:1;min-width:0;">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <strong>${esc(d.name)}</strong>
        ${Number(d.is_active) ? '<span class="badge badge-public">显示中</span>' : '<span class="badge badge-rejected">已隐藏</span>'}
      </div>
      ${d.description ? `<div style="color:var(--text-muted);font-size:13px;margin-top:4px;">${esc(d.description)}</div>` : ''}
      <div style="color:var(--text-muted);font-size:12px;margin-top:2px;">排序 ${esc(d.sort_order)}</div>
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-sm" data-act="edit" data-id="${d.id}">编辑</button>
      <button class="btn btn-sm btn-danger" data-act="del" data-id="${d.id}">删除</button>
    </div>
  </div>`;
}

function openDonationForm(main, d) {
  const isEdit = !!d;
  const m = openModal({
    title: isEdit ? '编辑捐款渠道' : '新增捐款渠道',
    wide: true,
    body: `
      <div class="form-group"><label class="form-label">渠道名称</label>
        <input class="form-input" id="don-name" value="${esc(d ? d.name : '')}"></div>
      <div class="form-group"><label class="form-label">描述</label>
        <textarea class="form-textarea" id="don-desc">${esc(d ? d.description : '')}</textarea></div>
      <div class="form-group"><label class="form-label">收款码图片</label>
        <input type="file" accept="image/*" id="don-file" class="form-input">
        <div class="form-hint">上传新图后自动替换（≤2MB）；不上传则保留原图</div>
        <div id="don-preview"></div>
      </div>
      <div class="form-row">
        <label class="switch-row"><input type="checkbox" id="don-active" ${!d || Number(d.is_active) ? 'checked' : ''}><span>显示</span></label>
        <div class="form-group grow"><label class="form-label">排序</label>
          <input class="form-input" type="number" id="don-sort" value="${esc(d ? d.sort_order : 0)}"></div>
      </div>
    `,
    footer: `<button class="btn" data-act="cancel">取消</button><button class="btn btn-primary" data-act="save">保存</button>`,
  });

  const preview = m.body.querySelector('#don-preview');
  if (d && d.image_url) preview.innerHTML = `<img src="${esc(d.image_url)}" style="max-width:160px;max-height:160px;margin-top:8px;border-radius:8px;border:1px solid var(--border);">`;

  const fileInput = m.body.querySelector('#don-file');
  fileInput.addEventListener('change', () => {
    const file = fileInput.files && fileInput.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { toast('图片不能超过 2MB', 'error'); fileInput.value = ''; return; }
    const reader = new FileReader();
    reader.onload = () => {
      preview.innerHTML = `<img src="${reader.result}" style="max-width:160px;max-height:160px;margin-top:8px;border-radius:8px;border:1px solid var(--border);">`;
    };
    reader.readAsDataURL(file);
  });

  m.overlay.querySelector('[data-act="cancel"]').addEventListener('click', () => m.close());
  m.overlay.querySelector('[data-act="save"]').addEventListener('click', async () => {
    const file = fileInput.files && fileInput.files[0];
    const data = {
      name: m.body.querySelector('#don-name').value.trim(),
      description: m.body.querySelector('#don-desc').value,
      is_active: m.body.querySelector('#don-active').checked,
      sort_order: Number(m.body.querySelector('#don-sort').value) || 0,
    };
    if (file) data.image = await readFileAsDataURL(file);
    try {
      if (isEdit) await api.adminUpdateDonation(d.id, data);
      else await api.adminCreateDonation(data);
      toast('已保存', 'success');
      m.close();
      loadDonations(main);
    } catch (e) { toast(e.message, 'error'); }
  });
}

async function deleteDonation(main, id) {
  const ok = await confirmDialog('确认删除该捐款渠道？', { danger: true, okText: '删除' });
  if (!ok) return;
  try {
    await api.adminDeleteDonation(id);
    toast('已删除', 'success');
    loadDonations(main);
  } catch (e) { toast(e.message, 'error'); }
}

function readFileAsDataURL(file) {
  return new Promise((resolve, reject) => {
    const r = new FileReader();
    r.onload = () => resolve(r.result);
    r.onerror = reject;
    r.readAsDataURL(file);
  });
}

/* ---------- 邮件 ---------- */

async function loadEmail(main) {
  main.innerHTML = `
    <div class="page-head"><h2 class="page-title">邮件设置</h2></div>
    <div class="card" style="padding:20px;max-width:560px;">
      <p style="margin:0 0 8px;">SMTP 服务器配置位于服务器 <code>.env</code> 文件：SMTP_HOST / SMTP_PORT / SMTP_ENCRYPTION / SMTP_USERNAME / SMTP_PASSWORD（QQ 邮箱请填授权码）。</p>
      <p style="margin:0 0 16px;color:var(--text-muted);font-size:13px;">验证码有效期与发送间隔等参数请在「站点设置」的「邮件」分组中调整。</p>
      <div class="form-group"><label class="form-label">发送测试邮件到</label>
        <div class="form-row">
          <input class="form-input grow" id="test-email" type="email" placeholder="you@example.com">
          <button class="btn btn-primary" id="send-test">发送测试邮件</button>
        </div>
      </div>
    </div>
  `;
  main.querySelector('#send-test').addEventListener('click', async () => {
    const email = main.querySelector('#test-email').value.trim();
    if (!email) { toast('请输入邮箱', 'warn'); return; }
    try {
      await api.adminEmailTest(email);
      toast('测试邮件已发送', 'success');
    } catch (e) { toast(e.message, 'error'); }
  });
}
