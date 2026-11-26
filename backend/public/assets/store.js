// 本地状态：token / 用户信息（localStorage 持久化）

const TOKEN_KEY = 'wordlearn_token';
const USER_KEY = 'wordlearn_user';

export const store = {
  getToken() {
    return localStorage.getItem(TOKEN_KEY) || '';
  },
  setToken(token) {
    localStorage.setItem(TOKEN_KEY, token || '');
  },
  getUser() {
    try {
      return JSON.parse(localStorage.getItem(USER_KEY));
    } catch (e) {
      return null;
    }
  },
  setUser(user) {
    if (user) {
      localStorage.setItem(USER_KEY, JSON.stringify(user));
    } else {
      localStorage.removeItem(USER_KEY);
    }
  },
  setAuth(token, user) {
    this.setToken(token);
    this.setUser(user);
  },
  clear() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  },
  isAdmin() {
    const u = this.getUser();
    return !!u && u.role === 'admin';
  },
};
