// 首页（公开落地页）：公告 + 入口按钮 + 捐款渠道
import { api } from '/assets/api.js';
import { store } from '/assets/store.js';
import { esc, spinner, emptyState } from '/assets/ui.js';

export default async function homeView({ container }) {
  container.innerHTML = `
    <div class="home">
      <section class="home-hero">
        <h1 class="home-title">LinguaLux</h1>
        <p class="home-slogan">轻松积累英语词汇，四类题型帮你巩固记忆</p>
        <div class="home-actions" id="home-actions">${spinner()}</div>
      </section>
      <section id="home-announcements"></section>
      <section id="home-donations"></section>
    </div>
  `;

  let data;
  try {
    data = await api.home();
  } catch (e) {
    container.querySelector('#home-actions').innerHTML = emptyState('加载失败：' + e.message);
    return;
  }
  render(data);

  function render(d) {
    renderButtons(d);
    renderAnnouncements(d);
    renderDonations(d);
  }

  function renderButtons(d) {
    const logged = !!store.getToken();
    const builtin = [{ label: logged ? '进入我的词库' : '登录 / 注册', route: logged ? '#/wordbooks' : '#/login' }];
    const buttons = [...(d.entry_buttons || []), ...builtin];
    const seen = new Set();
    const unique = buttons.filter((b) => {
      if (!b || !b.route || seen.has(b.route)) return false;
      seen.add(b.route);
      return true;
    });
    container.querySelector('#home-actions').innerHTML = unique
      .map((b) => `<a class="btn btn-primary home-btn" href="${esc(b.route)}">${esc(b.label)}</a>`)
      .join('');
  }

  function renderAnnouncements(d) {
    const box = container.querySelector('#home-announcements');
    if (!d.announcements_enabled || !d.announcements || !d.announcements.length) {
      box.innerHTML = '';
      return;
    }
    box.innerHTML = `
      <h2 class="home-section-title">公告</h2>
      <div class="home-ann-list">
        ${d.announcements.map((a) => `
          <div class="card home-ann-item">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <strong>${esc(a.title)}</strong>
              ${Number(a.is_pinned) ? '<span class="badge badge-pending">置顶</span>' : ''}
            </div>
            <div class="home-ann-content">${a.content}</div>
          </div>`).join('')}
      </div>`;
  }

  function renderDonations(d) {
    const box = container.querySelector('#home-donations');
    if (!d.donation_enabled || !d.donations || !d.donations.length) {
      box.innerHTML = '';
      return;
    }
    box.innerHTML = `
      <h2 class="home-section-title">支持我们</h2>
      ${d.donation_intro ? `<p class="home-donation-intro">${esc(d.donation_intro)}</p>` : ''}
      <div class="home-donation-list">
        ${d.donations.map((x) => `
          <div class="card home-donation-item">
            ${x.image_url ? `<img class="home-donation-img" src="${esc(x.image_url)}" alt="${esc(x.name)}">` : ''}
            <strong>${esc(x.name)}</strong>
            ${x.description ? `<div style="color:var(--text-muted);font-size:13px;">${esc(x.description)}</div>` : ''}
          </div>`).join('')}
      </div>`;
  }
}
