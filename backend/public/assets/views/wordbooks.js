// 我的词库列表
import { api } from '/assets/api.js';
import { esc, toast, confirmDialog, openModal, spinner, emptyState, num } from '/assets/ui.js';

export default function wordbooksView({ container }) {
  let books = [];

  container.innerHTML = `
    <div class="page-head">
      <div>
        <h1 class="page-title">我的词库</h1>
        <p class="page-sub">创建词库、添加单词，然后开始练习</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" id="new-book">＋ 新建词库</button>
      </div>
    </div>
    <div id="books-list">${spinner()}</div>
  `;

  container.querySelector('#new-book').addEventListener('click', () => bookForm());
  container.querySelector('#books-list').addEventListener('click', onListClick);

  load();

  async function load() {
    const listEl = container.querySelector('#books-list');
    try {
      books = await api.listWordbooks();
      render();
    } catch (e) {
      listEl.innerHTML = emptyState('加载失败：' + e.message);
    }
  }

  function render() {
    const listEl = container.querySelector('#books-list');
    if (!books.length) {
      listEl.innerHTML = emptyState('还没有词库，点击右上角「新建词库」开始', '<a class="btn btn-primary" href="#/market">去广场逛逛</a>');
      return;
    }
    listEl.innerHTML = `<div class="card-grid">${books.map(card).join('')}</div>`;
  }

  function card(wb) {
    const isPublic = num(wb.is_public) === 1;
    return `
      <div class="card wordbook-card" data-id="${wb.id}">
        <div class="meta">
          <span class="badge ${isPublic ? 'badge-public' : 'badge-private'}">${isPublic ? '已公开' : '私有'}</span>
          <span class="badge badge-private">${num(wb.word_count)} 词</span>
        </div>
        <h3>${esc(wb.name)}</h3>
        <div class="desc">${esc(wb.description || '暂无描述')}</div>
        <div class="actions">
          <button class="btn btn-sm" data-act="enter">进入单词</button>
          <button class="btn btn-sm btn-primary" data-act="quiz">练习</button>
          <button class="btn btn-sm" data-act="${isPublic ? 'unpublish' : 'publish'}">${isPublic ? '取消发布' : '发布'}</button>
          <button class="btn btn-sm" data-act="rename">重命名</button>
          <button class="btn btn-sm btn-danger" data-act="delete">删除</button>
        </div>
      </div>`;
  }

  function onListClick(e) {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const cardEl = btn.closest('[data-id]');
    if (!cardEl) return;
    const id = cardEl.dataset.id;
    const act = btn.dataset.act;

    if (act === 'enter') location.hash = `#/wordbooks/${id}/words`;
    else if (act === 'quiz') location.hash = `#/quiz?wordbook_id=${id}`;
    else if (act === 'rename') bookForm(books.find((b) => String(b.id) === String(id)));
    else if (act === 'delete') doDelete(id);
    else if (act === 'publish') doPublish(id, true);
    else if (act === 'unpublish') doPublish(id, false);
  }

  async function doDelete(id) {
    const ok = await confirmDialog('删除词库会同时删除其中的所有单词，确定吗？', { title: '删除词库', danger: true, okText: '删除' });
    if (!ok) return;
    try {
      await api.deleteWordbook(id);
      toast('已删除', 'success');
      load();
    } catch (e) { toast(e.message, 'error'); }
  }

  async function doPublish(id, pub) {
    try {
      await (pub ? api.publishWordbook(id) : api.unpublishWordbook(id));
      toast(pub ? '已发布到广场' : '已取消发布', 'success');
      load();
    } catch (e) { toast(e.message, 'error'); }
  }

  function bookForm(existing) {
    const isEdit = !!existing;
    const m = openModal({
      title: isEdit ? '编辑词库' : '新建词库',
      body: `
        <form id="book-form">
          <div class="form-group">
            <label class="form-label">名称 *</label>
            <input class="form-input" name="name" value="${esc(existing ? existing.name : '')}" placeholder="例如：四级核心词汇">
          </div>
          <div class="form-group">
            <label class="form-label">描述</label>
            <textarea class="form-textarea" name="description" placeholder="可选">${esc(existing ? existing.description : '')}</textarea>
          </div>
          <div class="form-actions">
            <button type="button" class="btn" data-close>取消</button>
            <button type="submit" class="btn btn-primary">保存</button>
          </div>
        </form>`,
    });

    m.body.querySelector('[data-close]').addEventListener('click', m.close);
    m.body.querySelector('#book-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const name = form.elements.name.value.trim();
      if (!name) { toast('词库名称不能为空', 'warn'); return; }
      const data = { name, description: form.elements.description.value.trim() };
      try {
        if (isEdit) await api.updateWordbook(existing.id, data);
        else await api.createWordbook(data);
        toast(isEdit ? '已保存' : '已创建', 'success');
        m.close();
        load();
      } catch (err) { toast(err.message, 'error'); }
    });
  }
}
