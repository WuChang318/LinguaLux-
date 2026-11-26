// 单词管理视图（某词库下的单词）
import { api } from '/assets/api.js';
import { esc, toast, confirmDialog, openModal, spinner, emptyState, speak } from '/assets/ui.js';

export default function wordsView({ container, params }) {
  const wordbookId = params.id;

  container.innerHTML = `
    <div class="page-head">
      <div>
        <a class="btn btn-ghost btn-sm" href="#/wordbooks" style="margin-bottom:6px;">← 返回词库</a>
        <h1 class="page-title" id="wb-title">单词管理</h1>
      </div>
      <div class="page-actions">
        <button class="btn" id="autofill-btn">✨ 一键填充</button>
        <button class="btn" id="import-btn">批量导入</button>
        <button class="btn btn-primary" id="add-word">＋ 新增单词</button>
      </div>
    </div>
    <div id="words-body">${spinner()}</div>
  `;

  container.querySelector('#add-word').addEventListener('click', () => wordForm(null));
  container.querySelector('#import-btn').addEventListener('click', importForm);
  container.querySelector('#autofill-btn').addEventListener('click', autofillAll);

  load();

  async function load() {
    const body = container.querySelector('#words-body');
    const title = container.querySelector('#wb-title');
    try {
      const [books, words] = await Promise.all([
        api.listWordbooks(),
        api.listWords(wordbookId),
      ]);
      const book = books.find((b) => String(b.id) === String(wordbookId));
      title.textContent = book ? book.name : '单词管理';
      renderWords(words);
    } catch (e) {
      body.innerHTML = emptyState(e.message || '加载失败', '<a class="btn" href="#/wordbooks">返回词库</a>');
    }
  }

  function renderWords(words) {
    const body = container.querySelector('#words-body');
    if (!words.length) {
      body.innerHTML = emptyState('这个词库还没有单词，点击右上角「新增单词」或「批量导入」');
      return;
    }
    body.innerHTML = `
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>单词</th><th>中文释义</th><th>英文释义</th><th>例句</th><th>操作</th></tr>
          </thead>
          <tbody>
            ${words.map((w) => `
              <tr data-id="${w.id}">
                <td>
                  <div class="word">${esc(w.word)}</div>
                  ${w.phonetic ? `<div class="phonetic">${esc(w.phonetic)}</div>` : ''}
                  ${w.pos ? `<div class="sub">${esc(w.pos)}</div>` : ''}
                </td>
                <td>${esc(w.meaning_zh || '—')}</td>
                <td class="sub">${esc(w.definition_en || '—')}</td>
                <td class="sub">${esc(w.example || '—')}</td>
                <td style="white-space:nowrap;">
                  <button class="btn btn-sm" data-act="speak">试听</button>
                  <button class="btn btn-sm" data-act="edit">编辑</button>
                  <button class="btn btn-sm btn-danger" data-act="delete">删除</button>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`;

    body.querySelector('tbody').addEventListener('click', async (e) => {
      const btn = e.target.closest('button[data-act]');
      if (!btn) return;
      const row = btn.closest('tr');
      const id = row.dataset.id;
      const word = words.find((w) => String(w.id) === String(id));
      if (btn.dataset.act === 'speak') speak(word.word, word.audio_url);
      else if (btn.dataset.act === 'edit') wordForm(word);
      else if (btn.dataset.act === 'delete') {
        const ok = await confirmDialog(`确定删除单词「${word.word}」吗？`, { title: '删除单词', danger: true, okText: '删除' });
        if (!ok) return;
        try { await api.deleteWord(id); toast('已删除', 'success'); load(); }
        catch (err) { toast(err.message, 'error'); }
      }
    });
  }

  // 一键填充：批量补齐缺失的单词信息（DeepSeek）
  async function autofillAll() {
    const btn = container.querySelector('#autofill-btn');
    btn.disabled = true;
    btn.textContent = '填充中…';
    try {
      const r = await api.autofillWordbook(wordbookId);
      const failNote = r.failed ? `，${r.failed} 个失败` : '';
      toast(`已填充 ${r.filled} / ${r.total} 个单词${failNote}`, r.failed ? 'warn' : 'success');
      load();
    } catch (e) {
      toast(e.message, 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = '✨ 一键填充';
    }
  }

  // 新增 / 编辑单词弹窗
  function wordForm(existing) {
    const isEdit = !!existing;
    const fields = ['phonetic', 'pos', 'meaning_zh', 'definition_en', 'example', 'example_zh', 'note'];
    const m = openModal({
      title: isEdit ? '编辑单词' : '新增单词',
      wide: true,
      body: `
        <form id="word-form">
          <div class="form-row">
            <div class="form-group grow">
              <label class="form-label">单词 *</label>
              <input class="form-input" name="word" value="${esc(existing ? existing.word : '')}" placeholder="输入英文单词" required>
            </div>
            <div class="form-group" style="align-self:flex-end;">
              <button type="button" class="btn btn-ghost" id="autofill-btn">自动填充</button>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group grow">
              <label class="form-label">音标</label>
              <input class="form-input" name="phonetic" value="${esc(existing ? existing.phonetic : '')}">
            </div>
            <div class="form-group grow">
              <label class="form-label">词性</label>
              <input class="form-input" name="pos" value="${esc(existing ? existing.pos : '')}" placeholder="如 n. / v.">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">中文释义</label>
            <textarea class="form-textarea" name="meaning_zh">${esc(existing ? existing.meaning_zh : '')}</textarea>
          </div>
          <div class="form-group">
            <label class="form-label">英文释义</label>
            <textarea class="form-textarea" name="definition_en">${esc(existing ? existing.definition_en : '')}</textarea>
          </div>
          <div class="form-group">
            <label class="form-label">例句</label>
            <textarea class="form-textarea" name="example">${esc(existing ? existing.example : '')}</textarea>
          </div>
          <div class="form-group">
            <label class="form-label">例句翻译</label>
            <textarea class="form-textarea" name="example_zh">${esc(existing ? existing.example_zh : '')}</textarea>
          </div>
          <div class="form-group">
            <label class="form-label">备注</label>
            <textarea class="form-textarea" name="note">${esc(existing ? existing.note : '')}</textarea>
          </div>
          <input type="hidden" name="audio_url" value="${esc(existing ? existing.audio_url : '')}">
          <div class="form-hint" id="autofill-hint"></div>
          <div class="form-actions">
            <button type="button" class="btn" data-close>取消</button>
            <button type="submit" class="btn btn-primary">保存</button>
          </div>
        </form>`,
    });

    const form = m.body.querySelector('#word-form');
    const autofillBtn = m.body.querySelector('#autofill-btn');
    const hint = m.body.querySelector('#autofill-hint');

    m.body.querySelector('[data-close]').addEventListener('click', m.close);

    autofillBtn.addEventListener('click', async () => {
      const word = form.elements.word.value.trim();
      if (!word) { toast('请先输入单词', 'warn'); return; }
      autofillBtn.disabled = true;
      autofillBtn.textContent = '查询中…';
      hint.textContent = '';
      try {
        const d = await api.dict(word);
        const map = ['phonetic', 'audio_url', 'pos', 'definition_en', 'example', 'meaning_zh', 'example_zh'];
        let missing = false;
        for (const f of map) {
          if (d[f] !== null && d[f] !== undefined && String(d[f]).trim() !== '') {
            form.elements[f].value = d[f];
          } else if (f !== 'audio_url') {
            missing = true;
          }
        }
        hint.textContent = missing ? '部分字段未获取到，请手动补充' : '已自动填充，可再手动修改';
        hint.className = 'form-hint' + (missing ? ' warn' : '');
      } catch (err) {
        hint.textContent = '自动填充失败：' + err.message;
        hint.className = 'form-hint warn';
      } finally {
        autofillBtn.disabled = false;
        autofillBtn.textContent = '自动填充';
      }
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const data = { word: form.elements.word.value.trim() };
      for (const f of fields.concat(['audio_url'])) {
        data[f] = form.elements[f].value.trim();
      }
      if (!data.word) { toast('单词不能为空', 'warn'); return; }
      try {
        if (isEdit) await api.updateWord(existing.id, data);
        else await api.createWord(wordbookId, data);
        toast(isEdit ? '已保存' : '已添加', 'success');
        m.close();
        load();
      } catch (err) { toast(err.message, 'error'); }
    });
  }

  // 批量导入弹窗
  function importForm() {
    const m = openModal({
      title: '批量导入',
      wide: true,
      body: `
        <form id="import-form">
          <div class="form-group">
            <label class="form-label">单词列表</label>
            <textarea class="form-textarea" name="raw" rows="10" placeholder="每行一个单词；或用逗号/制表符分隔，例如：&#10;apple, 苹果&#10;banana	香蕉&#10;cherry"></textarea>
            <div class="form-hint">支持纯单词，或「单词, 中文释义」「单词\t中文释义」格式</div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn" data-close>取消</button>
            <button type="submit" class="btn btn-primary">导入</button>
          </div>
        </form>`,
    });

    m.body.querySelector('[data-close]').addEventListener('click', m.close);
    m.body.querySelector('#import-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const raw = e.target.elements.raw.value;
      const words = parseImport(raw);
      if (!words.length) { toast('没有可导入的单词', 'warn'); return; }
      try {
        const r = await api.importWords(wordbookId, words);
        toast(`成功导入 ${r.imported} 个单词`, 'success');
        m.close();
        load();
      } catch (err) { toast(err.message, 'error'); }
    });
  }

  function parseImport(text) {
    const result = [];
    for (let line of (text || '').split(/\r?\n/)) {
      line = line.trim();
      if (!line) continue;
      let word = '';
      let meaning = '';
      if (line.includes('\t')) {
        const i = line.indexOf('\t');
        word = line.slice(0, i).trim();
        meaning = line.slice(i + 1).trim();
      } else if (line.includes(',')) {
        const i = line.indexOf(',');
        word = line.slice(0, i).trim();
        meaning = line.slice(i + 1).trim();
      } else {
        word = line;
      }
      if (word) result.push({ word, meaning_zh: meaning });
    }
    return result;
  }
}
