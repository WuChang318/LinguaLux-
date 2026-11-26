// 成绩统计视图
import { api } from '/assets/api.js';
import { esc, spinner, emptyState, fmtDate, typeLabel, num } from '/assets/ui.js';

const TYPE_ORDER = ['choice', 'spelling', 'dictation', 'matching'];

export default function statsView({ container }) {
  container.innerHTML = `
    <div class="page-head">
      <h1 class="page-title">我的成绩</h1>
    </div>
    <div id="stats-body">${spinner()}</div>
  `;

  load();

  async function load() {
    const body = container.querySelector('#stats-body');
    try {
      const s = await api.stats();
      render(s);
    } catch (e) {
      body.innerHTML = emptyState('加载失败：' + e.message);
    }
  }

  function render(s) {
    const body = container.querySelector('#stats-body');
    const totalQ = num(s.total_questions);
    const totalC = num(s.total_correct);
    const rate = totalQ > 0 ? Math.round((totalC / totalQ) * 1000) / 10 : 0;

    const byTypeRows = TYPE_ORDER.map((t) => {
      const d = (s.by_type && s.by_type[t]) || { total: 0, correct: 0, times: 0 };
      const tr = num(d.total);
      const r = tr > 0 ? Math.round((num(d.correct) / tr) * 1000) / 10 : 0;
      return `<tr>
        <td>${typeLabel(t)}</td>
        <td>${tr}</td>
        <td>${num(d.correct)}</td>
        <td>${num(d.times)}</td>
        <td>${r}%</td>
      </tr>`;
    }).join('');

    const recentRows = (s.recent || []).map((r) => {
      const rr = num(r.total) > 0 ? Math.round((num(r.correct) / num(r.total)) * 1000) / 10 : 0;
      return `<tr>
        <td class="sub">${fmtDate(r.created_at)}</td>
        <td>${typeLabel(r.type)}</td>
        <td>${esc(r.wordbook_name || '—')}</td>
        <td>${num(r.correct)} / ${num(r.total)}</td>
        <td>${rr}%</td>
      </tr>`;
    }).join('');

    body.innerHTML = `
      <div class="stat-cards">
        <div class="stat-card"><div class="stat-value">${totalQ}</div><div class="stat-label">总题数</div></div>
        <div class="stat-card"><div class="stat-value">${rate}%</div><div class="stat-label">总正确率</div></div>
        <div class="stat-card"><div class="stat-value">${num(s.total_quizzes)}</div><div class="stat-label">测验次数</div></div>
      </div>

      <div class="card" style="padding:20px;margin-bottom:20px;">
        <h3 style="margin:0 0 12px;">各题型统计</h3>
        <div class="table-wrap" style="border:none;">
          <table class="table" style="min-width:0;">
            <thead><tr><th>题型</th><th>出题数</th><th>答对数</th><th>次数</th><th>正确率</th></tr></thead>
            <tbody>${byTypeRows}</tbody>
          </table>
        </div>
      </div>

      <div class="card" style="padding:20px;">
        <h3 style="margin:0 0 12px;">最近答题记录</h3>
        ${recentRows ? `
          <div class="table-wrap" style="border:none;">
            <table class="table" style="min-width:0;">
              <thead><tr><th>时间</th><th>题型</th><th>词库</th><th>答对/总数</th><th>正确率</th></tr></thead>
              <tbody>${recentRows}</tbody>
            </table>
          </div>` : '<p class="sub">还没有答题记录，去<a href="#/quiz">抽考</a>一次吧</p>'}
      </div>`;
  }
}
