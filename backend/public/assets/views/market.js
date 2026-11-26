// 词库广场视图
import { api } from '/assets/api.js';
import { esc, toast, openModal, spinner, emptyState, num } from '/assets/ui.js';

export default function marketView({ container }) {
  let q = '';
  let page = 1;
  const size = 12;

  container.innerHTML = `
    <div class="page-head">
      <div>
        <h1 class="page-title">词库广场</h1>
        <p class="page-sub">发现大家公开分享的词库，一键复制到自己的词库</p>
      </div>
    </div>
    <div class="market-search">
      <input class="form-input" id="search-input" placeholder="搜索词库名称或描述">
      <button class="btn btn-primary" id="search-btn">搜索</button>
    </div>
    <div id="market-body">${spinner()}</div>
  `;

  container.querySelector('#search-btn').addEventListener('click', () => {
    q = container.querySelector('#search-input').value.trim();
    page = 1;
    load();
  });
  container.querySelector('#search-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      q = e.target.value.trim();
      page = 1;
      load();
    }
  });

  load();

  async function load() {
    const body = container.querySelector('#market-body');
    body.innerHTML = spinner();
    try {
      const data = await api.market({ q, page, size });
      render(data);
    } catch (e) {
      body.innerHTML = emptyState('加载失败：' + e.message);
    }
  }

  function render(data) {
    const body = container.querySelector('#market-body');
    const list = data.list || [];
    if (!list.length) {
      body.innerHTML = emptyState(q ? '没有找到相关词库' : '广场暂时没有公开词库，去「我的词库」发布一个吧');
      return;
    }
    const totalPages = Math.max(1, Math.ceil(num(data.total) / size));
    body.innerHTML = `
      <div class="card-grid">${list.map(card).join('')}</div>
      <div class="pagination">
        <button class="btn btn-sm" id="prev" ${page <= 1 ? 'disabled' : ''}>上一页</button>
        <span>第 ${page} / ${totalPages} 页 · 共 ${num(data.total)} 个词库</span>
        <button class="btn btn-sm" id="next" ${page >= totalPages ? 'disabled' : ''}>下一页</button>
      </div>`;

    body.querySelector('#prev').addEventListener('click', () => { if (page > 1) { page--; load(); } });
    body.querySelector('#next').addEventListener('click', () => { if (page < totalPages) { page++; load(); } });
    body.querySelector('.card-grid').addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-act="detail"]');
      if (btn) showDetail(btn.dataset.id);
    });
  }

  function card(item) {
    return `
      <div class="card market-card">
        <div class="head">
          <h3>${esc(item.name)}</h3>
          <span class="badge badge-private">${num(item.word_count)} 词</span>
        </div>
        <div class="desc">${esc(item.description || '暂无描述')}</div>
        <div class="meta">
          <span class="badge badge-private">作者：${esc(item.owner_username || '—')}</span>
          <span class="badge badge-private">被复制 ${num(item.copied_count)} 次</span>
        </div>
        <div class="actions">
          <button class="btn btn-sm btn-primary" data-act="detail" data-id="${item.id}">查看详情</button>
        </div>
      </div>`;
  }

  async function showDetail(id) {
    let detail;
    try {
      detail = await api.marketDetail(id);
    } catch (e) {
      toast(e.message, 'error');
      return;
    }
    const words = detail.words || [];
    const m = openModal({
      title: detail.name,
      wide: true,
      body: `
        <div class="meta" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <span class="badge badge-public">${num(detail.word_count)} 词</span>
          <span class="badge badge-private">作者：${esc(detail.owner_username || '—')}</span>
          <span class="badge badge-private">被复制 ${num(detail.copied_count)} 次</span>
        </div>
        <div class="desc" style="margin-bottom:12px;">${esc(detail.description || '暂无描述')}</div>
        ${words.length ? `
          <div class="word-preview">
            <h4 style="margin:0 0 6px;">单词预览</h4>
            <ul style="margin:0;padding-left:18px;">
              ${words.slice(0, 50).map((w) => `<li><strong>${esc(w.word)}</strong>${w.meaning_zh ? ' — ' + esc(w.meaning_zh) : ''}${w.phonetic ? ' <span class="sub">' + esc(w.phonetic) + '</span>' : ''}</li>`).join('')}
            </ul>
            ${words.length > 50 ? '<div class="form-hint">仅显示前 50 个单词</div>' : ''}
          </div>` : '<div class="form-hint">该词库暂无单词</div>'}
      `,
      footer: `
        <button class="btn" data-act="report">举报</button>
        <button class="btn btn-primary" data-act="use">使用（复制）</button>`,
    });

    m.overlay.querySelector('[data-act="use"]').addEventListener('click', async () => {
      try {
        const r = await api.marketUse(id);
        toast('已复制到我的词库', 'success');
        m.close();
        location.hash = `#/wordbooks/${r.wordbook_id}/words`;
      } catch (e) { toast(e.message, 'error'); }
    });

    m.overlay.querySelector('[data-act="report"]').addEventListener('click', () => {
      reportForm(id);
    });
  }

  function reportForm(id) {
    const m = openModal({
      title: '举报词库',
      body: `
        <form id="report-form">
          <div class="form-group">
            <label class="form-label">举报原因 *</label>
            <textarea class="form-textarea" name="reason" placeholder="请描述该词库存在的问题"></textarea>
          </div>
          <div class="form-actions">
            <button type="button" class="btn" data-close>取消</button>
            <button type="submit" class="btn btn-danger">提交举报</button>
          </div>
        </form>`,
    });
    m.body.querySelector('[data-close]').addEventListener('click', m.close);
    m.body.querySelector('#report-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const reason = e.target.elements.reason.value.trim();
      if (!reason) { toast('请填写举报原因', 'warn'); return; }
      try {
        await api.marketReport(id, reason);
        toast('举报已提交', 'success');
        m.close();
      } catch (err) { toast(err.message, 'error'); }
    });
  }
}
