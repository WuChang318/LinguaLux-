// 抽考视图：配置 → 逐题作答 → 结果
import { api } from '/assets/api.js';
import { esc, toast, spinner, emptyState, speak, num } from '/assets/ui.js';

const TYPES = [
  { value: 'choice', label: '选择题' },
  { value: 'spelling', label: '拼写题' },
  { value: 'dictation', label: '听写题' },
  { value: 'matching', label: '配对题' },
];

export default function quizView({ container, query }) {
  let wordbooks = [];
  let questions = [];
  let wordbookId = null;
  let type = 'choice';
  let total = 0;
  let correct = 0;
  let index = 0;
  let detail = [];

  const preselectBook = query.wordbook_id || '';
  const preselectType = TYPES.some((t) => t.value === query.type) ? query.type : 'choice';

  renderConfig();

  async function renderConfig() {
    container.innerHTML = `
      <div class="card quiz-card">
        <h2 style="margin:0 0 4px;">开始抽考</h2>
        <p class="page-sub" style="margin-bottom:20px;">从你的词库随机抽题，考考自己</p>
        <div id="config-body">${spinner()}</div>
      </div>`;

    const body = container.querySelector('#config-body');
    try {
      wordbooks = await api.listWordbooks();
    } catch (e) {
      body.innerHTML = emptyState('加载词库失败：' + e.message);
      return;
    }

    if (!wordbooks.length) {
      body.innerHTML = emptyState('还没有词库，请先创建并添加单词', '<a class="btn btn-primary" href="#/wordbooks">去建词库</a>');
      return;
    }

    const selBook = preselectBook || String(wordbooks[0].id);
    body.innerHTML = `
      <form id="quiz-config">
        <div class="form-group">
          <label class="form-label">选择词库</label>
          <select class="form-select" name="wordbook_id" required>
            ${wordbooks.map((b) => `<option value="${b.id}" ${String(b.id) === selBook ? 'selected' : ''}>${esc(b.name)}（${num(b.word_count)} 词）</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">题型</label>
          <div class="type-options">
            ${TYPES.map((t) => `
              <label class="type-option">
                <input type="radio" name="type" value="${t.value}" ${t.value === preselectType ? 'checked' : ''}>
                ${t.label}
              </label>`).join('')}
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">题目数量（1–50）</label>
          <input class="form-input" type="number" name="count" min="1" max="50" value="10">
        </div>
        <button class="btn btn-primary btn-block" type="submit">开始</button>
      </form>`;

    body.querySelector('#quiz-config').addEventListener('submit', startQuiz);
  }

  async function startQuiz(e) {
    e.preventDefault();
    const form = e.target;
    wordbookId = form.elements.wordbook_id.value;
    type = form.elements.type.value;
    const count = parseInt(form.elements.count.value, 10) || 10;

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = '出题中…';
    try {
      const data = await api.generateQuiz({ wordbook_id: wordbookId, type, count });
      questions = data.questions || [];
      if (!questions.length) { toast('未能生成题目', 'warn'); btn.disabled = false; btn.textContent = '开始'; return; }
      wordbookId = data.wordbook_id;
      type = data.type;
      correct = 0;
      index = 0;
      detail = [];

      if (type === 'matching') {
        total = questions[0].pairs ? questions[0].pairs.length : 0;
        renderMatching(questions[0]);
      } else {
        total = questions.length;
        renderQuestion(0);
      }
    } catch (err) {
      toast(err.message, 'error');
      btn.disabled = false;
      btn.textContent = '开始';
    }
  }

  // ---------- 选择题 / 拼写 / 听写 ----------
  function renderQuestion(i) {
    if (i >= questions.length) { finish(); return; }
    const q = questions[i];
    container.innerHTML = `<div class="card quiz-card">${q.type === 'choice' ? choiceHtml(q, i) : q.type === 'spelling' ? spellingHtml(q, i) : dictationHtml(q, i)}</div>`;

    if (q.type === 'choice') bindChoice(q);
    else if (q.type === 'spelling') bindSpelling(q);
    else bindDictation(q);
  }

  function progressHtml(i) {
    return `<div class="quiz-progress">第 ${i + 1} / ${total} 题 · ${typeLabelLocal(type)}</div>`;
  }

  function typeLabelLocal(t) {
    const found = TYPES.find((x) => x.value === t);
    return found ? found.label : t;
  }

  function choiceHtml(q, i) {
    return `
      ${progressHtml(i)}
      <div class="quiz-prompt">${q.direction === 'en2zh' ? '请选择正确的<span style="color:var(--text)">中文释义</span>' : '请选择正确的<span style="color:var(--text)">英文单词</span>'}</div>
      <div class="quiz-big">${esc(q.prompt)}</div>
      <div class="quiz-options">
        ${q.options.map((opt, idx) => `<button class="option" type="button" data-idx="${idx}">${esc(opt)}</button>`).join('')}
      </div>
      <div class="quiz-feedback"></div>
      <div class="quiz-actions"></div>`;
  }

  function bindChoice(q) {
    const options = container.querySelectorAll('.option');
    const feedback = container.querySelector('.quiz-feedback');
    const actions = container.querySelector('.quiz-actions');
    options.forEach((btn) => {
      btn.addEventListener('click', () => {
        if (btn.disabled) return;
        const idx = Number(btn.dataset.idx);
        const ok = idx === q.answer_index;
        options.forEach((o) => (o.disabled = true));
        options[q.answer_index].classList.add('correct');
        if (!ok) btn.classList.add('wrong');
        feedback.className = 'quiz-feedback ' + (ok ? 'feedback-correct' : 'feedback-wrong');
        feedback.textContent = ok ? '✓ 回答正确' : `✗ 回答错误，正确答案是：${q.options[q.answer_index]}`;
        if (ok) correct++;
        detail.push({ word_id: q.word_id, correct: ok });
        actions.innerHTML = nextButtonHtml();
        bindNext(actions);
      });
    });
  }

  function spellingHtml(q, i) {
    return `
      ${progressHtml(i)}
      <div class="quiz-prompt">根据释义拼写单词</div>
      <div class="quiz-big">${esc(q.prompt)}</div>
      ${q.phonetic ? `<div class="page-sub" style="margin-bottom:16px;">音标：${esc(q.phonetic)}</div>` : ''}
      <div class="form-group">
        <input class="form-input" id="answer-input" placeholder="输入英文单词" autocomplete="off" spellcheck="false">
      </div>
      <div class="quiz-feedback"></div>
      <div class="quiz-actions">
        <button class="btn btn-primary" type="button" id="submit-answer">提交</button>
      </div>`;
  }

  function bindSpelling(q) {
    const input = container.querySelector('#answer-input');
    const feedback = container.querySelector('.quiz-feedback');
    const actions = container.querySelector('.quiz-actions');
    const submit = container.querySelector('#submit-answer');
    input.focus();
    const check = () => {
      if (submit.disabled) return;
      const val = input.value.trim().toLowerCase();
      if (!val) { toast('请输入答案', 'warn'); return; }
      const ok = val === String(q.answer).trim().toLowerCase();
      submit.disabled = true;
      input.disabled = true;
      feedback.className = 'quiz-feedback ' + (ok ? 'feedback-correct' : 'feedback-wrong');
      feedback.textContent = ok ? '✓ 拼写正确' : `✗ 正确答案：${q.answer}`;
      if (ok) correct++;
      detail.push({ word_id: q.word_id, correct: ok });
      actions.innerHTML = nextButtonHtml();
      bindNext(actions);
    };
    submit.addEventListener('click', check);
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') check(); });
  }

  function dictationHtml(q, i) {
    return `
      ${progressHtml(i)}
      <div class="quiz-prompt">听发音，拼写单词</div>
      <div style="margin-bottom:16px;">
        <button class="btn" type="button" id="play-btn">🔊 播放发音</button>
        ${q.phonetic ? `<span class="page-sub" style="margin-left:10px;">音标：${esc(q.phonetic)}</span>` : ''}
      </div>
      <div class="form-group">
        <input class="form-input" id="answer-input" placeholder="输入你听到的单词" autocomplete="off" spellcheck="false">
      </div>
      <div class="quiz-feedback"></div>
      <div class="quiz-actions">
        <button class="btn btn-primary" type="button" id="submit-answer">提交</button>
      </div>`;
  }

  function bindDictation(q) {
    const input = container.querySelector('#answer-input');
    const feedback = container.querySelector('.quiz-feedback');
    const actions = container.querySelector('.quiz-actions');
    const submit = container.querySelector('#submit-answer');
    const play = () => speak(q.answer, q.audio_url);
    container.querySelector('#play-btn').addEventListener('click', play);
    play(); // 自动播放一次
    const check = () => {
      if (submit.disabled) return;
      const val = input.value.trim().toLowerCase();
      if (!val) { toast('请输入答案', 'warn'); return; }
      const ok = val === String(q.answer).trim().toLowerCase();
      submit.disabled = true;
      input.disabled = true;
      feedback.className = 'quiz-feedback ' + (ok ? 'feedback-correct' : 'feedback-wrong');
      feedback.textContent = ok ? '✓ 拼写正确' : `✗ 正确答案：${q.answer}`;
      if (ok) correct++;
      detail.push({ word_id: q.word_id, correct: ok });
      actions.innerHTML = nextButtonHtml();
      bindNext(actions);
    };
    submit.addEventListener('click', check);
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') check(); });
  }

  function nextButtonHtml() {
    const isLast = index >= total - 1;
    return `<button class="btn btn-primary" type="button" id="next-btn">${isLast ? '查看结果' : '下一题'}</button>`;
  }

  function bindNext(actions) {
    actions.querySelector('#next-btn').addEventListener('click', () => {
      index++;
      renderQuestion(index);
    });
  }

  // ---------- 配对题 ----------
  function renderMatching(q) {
    const pairs = q.pairs || [];
    const leftItems = shuffle(pairs.map((p, i) => ({ id: p.word_id, text: p.left, key: i })));
    const rightItems = shuffle(pairs.map((p, i) => ({ id: p.word_id, text: p.right, key: i })));

    container.innerHTML = `
      <div class="card quiz-card">
        <div class="quiz-progress">配对题 · 共 ${pairs.length} 对 · <span id="matched-count">已配对 0</span></div>
        <div class="quiz-prompt">点击左侧英文，再点击对应释义进行配对（顺序可互换）</div>
        <div class="matching-board">
          <div class="matching-col">
            <h4>英文</h4>
            ${leftItems.map((it) => `<div class="matching-item" data-side="left" data-id="${it.id}">${esc(it.text)}</div>`).join('')}
          </div>
          <div class="matching-col">
            <h4>释义</h4>
            ${rightItems.map((it) => `<div class="matching-item" data-side="right" data-id="${it.id}">${esc(it.text)}</div>`).join('')}
          </div>
        </div>
        <div class="quiz-actions" id="matching-actions"></div>
      </div>`;

    const matched = new Set();
    let selected = null;
    const countEl = container.querySelector('#matched-count');
    const actions = container.querySelector('#matching-actions');

    container.querySelectorAll('.matching-item').forEach((el) => {
      el.addEventListener('click', () => {
        const id = String(el.dataset.id);
        const side = el.dataset.side;
        if (matched.has(id)) return;

        if (!selected) {
          selected = { side, id, el };
          el.classList.add('selected');
          return;
        }
        if (selected.side === side) {
          selected.el.classList.remove('selected');
          selected = { side, id, el };
          el.classList.add('selected');
          return;
        }

        // 相反侧 → 尝试配对
        const a = selected;
        if (a.id === id) {
          a.el.classList.remove('selected');
          a.el.classList.add('matched');
          el.classList.add('matched');
          matched.add(id);
          selected = null;
          correct++;
          detail.push({ word_id: Number(id), correct: true });
          countEl.textContent = `已配对 ${matched.size}`;
          if (matched.size === pairs.length) finishMatching(actions);
        } else {
          a.el.classList.add('wrong');
          el.classList.add('wrong');
          const prev = a.el;
          setTimeout(() => {
            prev.classList.remove('selected', 'wrong');
            el.classList.remove('wrong');
          }, 400);
          selected = null;
        }
      });
    });
  }

  function finishMatching(actions) {
    setTimeout(() => {
      actions.innerHTML = `<button class="btn btn-primary" type="button" id="next-btn">查看结果</button>`;
      actions.querySelector('#next-btn').addEventListener('click', finish);
    }, 400);
  }

  // ---------- 结果 ----------
  function finish() {
    const rate = total > 0 ? Math.round((correct / total) * 1000) / 10 : 0;
    container.innerHTML = `
      <div class="card quiz-card result-card">
        <div class="result-rate">${rate}%</div>
        <div class="result-detail">答对 ${correct} / ${total} 题 · ${typeLabelLocal(type)}</div>
        <div class="quiz-actions" style="justify-content:center;">
          <button class="btn" id="again-btn">再来一轮</button>
          <button class="btn btn-primary" id="done-btn">返回词库</button>
        </div>
      </div>`;

    container.querySelector('#again-btn').addEventListener('click', () => {
      renderConfig();
    });
    container.querySelector('#done-btn').addEventListener('click', () => {
      location.hash = '#/wordbooks';
    });

    // 落库（失败不影响展示）
    api.submitQuiz({ wordbook_id: wordbookId, type, total, correct, detail })
      .catch(() => { /* 忽略 */ });
  }
}

function shuffle(arr) {
  const a = arr.slice();
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}
