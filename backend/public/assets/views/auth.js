// 登录 / 注册视图
import { api } from '/assets/api.js';
import { store } from '/assets/store.js';
import { toast } from '/assets/ui.js';

export default function authView({ container, mode }) {
  const isRegister = mode === 'register';

  container.innerHTML = `
    <div class="auth-page">
      <div class="auth-card">
        <h1 class="auth-title">${isRegister ? '注册账号' : '欢迎回来'}</h1>
        <p class="auth-sub">${isRegister ? '创建一个新账号，开始背单词' : '登录后管理你的词库与练习'}</p>
        <form id="auth-form" novalidate>
          <div class="form-group">
            <label class="form-label" for="auth-username">用户名</label>
            <input class="form-input" id="auth-username" name="username" autocomplete="username" placeholder="请输入用户名" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="auth-password">密码</label>
            <input class="form-input" id="auth-password" name="password" type="password" autocomplete="${isRegister ? 'new-password' : 'current-password'}" placeholder="${isRegister ? '至少 6 位' : '请输入密码'}" required>
          </div>
          ${isRegister ? `
          <div class="form-group">
            <label class="form-label" for="auth-confirm">确认密码</label>
            <input class="form-input" id="auth-confirm" name="confirm" type="password" autocomplete="new-password" placeholder="再次输入密码" required>
          </div>` : ''}
          <button class="btn btn-primary btn-block" type="submit" id="auth-submit">${isRegister ? '注册' : '登录'}</button>
        </form>
        <p class="auth-link">
          ${isRegister ? '已有账号？<a href="#/login">去登录</a>' : '还没有账号？<a href="#/register">去注册</a>'}
        </p>
      </div>
    </div>
  `;

  const form = container.querySelector('#auth-form');
  const submitBtn = container.querySelector('#auth-submit');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = form.elements.username.value.trim();
    const password = form.elements.password.value;

    if (username.length < 2) { toast('用户名至少 2 个字符', 'warn'); return; }
    if (password.length < 6) { toast('密码至少 6 位', 'warn'); return; }
    if (isRegister && form.elements.confirm.value !== password) {
      toast('两次输入的密码不一致', 'warn');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = isRegister ? '注册中…' : '登录中…';
    try {
      const data = isRegister
        ? await api.register(username, password)
        : await api.login(username, password);
      store.setAuth(data.token, data.user);
      toast(isRegister ? '注册成功' : '登录成功', 'success');
      location.hash = '#/wordbooks';
    } catch (err) {
      toast(err.message || '操作失败', 'error');
      submitBtn.disabled = false;
      submitBtn.textContent = isRegister ? '注册' : '登录';
    }
  });
}
