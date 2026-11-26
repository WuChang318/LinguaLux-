# LinguaLux —— 英语单词学习网站（自建词库 + 随机抽考 + 词库广场）

LinguaLux 是一个可部署在**宝塔面板（PHP）**上的英语单词学习网站。

## 功能
- 用户注册 / 登录（数据相互隔离），首个注册账号自动成为管理员。
- 自建多个词库，增删单词，支持批量导入。
- 添加单词时**自动填充**音标、发音、英文释义、例句、中文释义；支持 **DeepSeek 聊天模型**（可选）或免费 API，词库页可**一键批量填充**。
- 用**自己的词库**随机抽考，4 种题型：选择题（英→中 / 中→英）、拼写、听写、配对。
- 答题成绩与正确率统计。
- **词库广场**：发布自己的词库公开，浏览/搜索/复制成自己的副本；靠"举报 + 管理员下架"治理（免人工审核）。

## 技术栈
- 后端：**纯 PHP**（无框架、无 Composer）+ PDO + MySQL。
- 前端：**原生 ES 模块 SPA**（无 Node、无构建、无 CDN）。
- 翻译/自动填充：可选 **DeepSeek（deepseek-chat，批量调用省 token）**，未配置 Key 时回退 Free Dictionary API + MyMemory；听写用浏览器 TTS 兜底发音。

> 之所以不选 ThinkPHP/Laravel + Vue 构建：最大化"直接部署"体验（无需 Composer、无需 Node），
> 并规避 ThinkPHP 6 当前被 Packagist 安全公告拦截安装的问题。

## 目录结构
```
backend/
  public/            ← 站点运行目录（Web 根）
    index.php        ← 前端控制器：/api 路由 + 静态资源 + SPA 回退
    index.html       ← SPA 外壳
    assets/          ← 前端 JS/CSS
  src/               ← 后端 PHP（控制器、路由、服务）
  config/.env        ← 数据库配置（复制 .env.example 修改）
  database/schema.sql← 建表 SQL
deploy/
  nginx.conf         ← Nginx 参考配置
  宝塔部署说明.md     ← 详细部署步骤
```

## 快速开始（本地）
1. 建库并导入 `backend/database/schema.sql`。
2. 复制 `backend/config/.env.example` 为 `backend/config/.env`，填写数据库连接。
3. 启动内置服务器（无需 Nginx）：
   ```
   php -S 127.0.0.1:8000 -t backend/public backend/public/index.php
   ```
4. 浏览器打开 http://127.0.0.1:8000 ，注册第一个账号（即管理员）。

## 部署到宝塔
见 [`deploy/宝塔部署说明.md`](deploy/宝塔部署说明.md)。
