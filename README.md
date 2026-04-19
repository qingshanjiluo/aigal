# 爱旮旯给目 · AI 互动叙事游戏 V2

![Glassmorphism UI](https://img.shields.io/badge/UI-Glassmorphism-6CB7FF?style=flat-square)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php)
![MySQL 5.7+](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat-square&logo=mysql)
![DeepSeek API](https://img.shields.io/badge/AI-DeepSeek-0A66C2?style=flat-square)
![License](https://img.shields.io/badge/License-MIT-blue?style=flat-square)

一个采用玻璃态（Glassmorphism）设计风格的 Web 互动叙事游戏（Galgame），由 DeepSeek API 驱动，支持多角色对话、好感度系统、多结局、立绘表情切换、长期记忆、世界观切换、存档回溯等丰富功能。

## ✨ 核心特色

### 🎭 **角色立绘系统**
- 支持上传自定义角色头像（PNG/JPG）
- 根据对话情感关键词自动切换立绘表情（开心、生气、悲伤、害羞等）
- 多姿态支持（日常、战斗、特殊场景）
- 角色管理：增删改查，自定义性格描述与初始好感度

### 📖 **增强剧情引擎**
- 章节/场景结构化叙事（第 N 章 · 第 M 场）
- 长期记忆系统：AI 自动提取重要事件并持久化，影响后续剧情发展
- 多结局系统：幸福结局、离别结局、隐藏结局等，解锁后进入图鉴
- 分支选项：AI 动态生成 2-4 个剧情选项，引导故事走向

### 🎨 **玻璃态交互界面**
- macOS 风格毛玻璃效果，平滑动画过渡
- 自适应布局：左侧栏（章节/场景/快捷按钮）、中间聊天区、右侧立绘与状态
- 混合模式切换：明亮/暗黑主题、自动播放、自动语音
- 响应式设计，适配 PC 与移动端

### 🔄 **世界切换系统**
- **6 个预设世界**：现代校园、奇幻冒险、科幻未来、古风江湖、末日生存、职场都市
- **用户自定义世界**：可新建/编辑/删除，完全自定义世界观背景、全局提示词、故事背景
- 每个世界独立对话上下文，切换时自动清空历史，实现完全隔离的叙事体验
- 系统世界与自定义世界视觉区分（紫色边框标记）

### 💾 **存档与回溯**
- **9 格存档系统**：使用 localStorage 保存完整游戏状态（对话历史、好感度、章节进度等）
- **剧情回溯**：点击任意历史对话可跳转至该时间点，并清除后续对话，实现“后悔药”功能
- **一键导出**：将完整对话记录导出为 TXT 文件

### 🗣️ **语音与辅助**
- TTS 语音合成：每条角色回复可自动或手动播放语音（使用第三方 API）
- 自动播放模式：每 3 秒自动发送当前输入框内容
- CG 图鉴：解锁并收藏游戏中的特殊场景图片
- 情感检测：通过关键词匹配自动识别对话情感，触发相应立绘表情

## 🚀 快速开始

### 环境要求
- **Web 服务器**：Apache / Nginx / IIS（支持 PHP）
- **PHP**：7.4 或更高（需开启 curl、pdo_mysql、session 扩展）
- **MySQL**：5.7 或更高（或 MariaDB 10.2+）
- **浏览器**：现代浏览器（Chrome 90+, Edge 90+, Firefox 88+, Safari 14+）

### 本地部署（XAMPP/WAMP）
1. **克隆仓库**
   ```bash
   git clone https://github.com/qingshanjiluo/aigal.git
   cd aigal
   ```

2. **导入数据库**
   - 使用 phpMyAdmin 或命令行导入 `database.sql`
   - 或执行：`mysql -u root -p < database.sql`

3. **配置连接**
   - 复制 `config.php.example` 为 `config.php`（如不存在则直接编辑 `config.php`）
   - 修改数据库连接信息：
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'ai_galgame');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```
   - 填入你的 DeepSeek API 密钥：
     ```php
     define('DEEPSEEK_API_KEY', 'sk-your-api-key-here');
     ```

4. **启动服务**
   - 将项目文件夹放入 XAMPP 的 `htdocs` 目录
   - 启动 Apache 和 MySQL
   - 访问 `http://localhost/aigal/index.html`

### 线上部署（InfinityFree 等免费主机）
1. **上传文件**：通过 FTP 将所有文件上传到主机
2. **导入数据库**：使用主机的 phpMyAdmin 导入 `infinityfree_database.sql`（该文件已移除 `CREATE DATABASE` 语句）
3. **修改配置**：编辑 `config.php`，使用主机提供的数据库连接信息：
   ```php
   define('DB_HOST', 'sql208.infinityfree.com');
   define('DB_PORT', '3306');
   define('DB_NAME', 'if0_41698662_aigal');
   define('DB_USER', 'if0_41698662');
   define('DB_PASS', 'your_password');
   ```
4. **访问游戏**：打开你的域名对应地址

## 📁 项目结构

```
aigal/
├── index.html              # 主界面 HTML
├── style.css               # 玻璃态样式与动画
├── script.js               # 前端交互逻辑（存档、世界切换、回溯等）
├── game.php                # 核心游戏 API（对话、记忆、结局、TTS）
├── user.php                # 用户注册/登录/登出 API
├── db.php                  # 数据库连接与操作类
├── config.php              # 配置文件（数据库、API 密钥）
├── database.sql            # 本地完整数据库建表脚本
├── infinityfree_database.sql # 线上主机专用建表脚本（无 CREATE DATABASE）
├── update_db.sql           # 数据库升级脚本（V1 → V2）
├── README.md               # 本文件
├── test_*.php              # 功能测试文件
└── cookie.txt              # 会话测试文件（可忽略）
```

## 🎮 使用指南

### 首次使用
1. **注册账号**：输入用户名（≥3字符）和密码（≥4字符）
2. **登录游戏**：系统自动加载你的游戏进度（如无则初始化）
3. **添加角色**：点击左侧“➕ 添加角色”，填写名称、性格、上传头像

### 游戏界面
- **左侧栏**：
  - 章节/场景显示（第 N 章 · 第 M 场）
  - 角色立绘预览
  - 快捷按钮：📖 回溯、💾 存档、📂 读档、🌍 世界切换
  - 设置开关：自动播放、自动语音、暗黑模式
- **中间聊天区**：
  - 对话气泡（玩家蓝色，角色绿色）
  - AI 生成的剧情选项按钮
  - 底部输入框（支持回车发送）
- **右侧栏**：
  - 角色列表与好感度
  - 重要事件记录
  - 结局图鉴入口

### 核心操作
- **发送消息**：在输入框打字后按 Enter 或点击“发送”
- **选择选项**：点击 AI 生成的选项按钮自动填入并发送
- **切换世界**：点击🌍按钮，选择预设或自定义世界，对话上下文将重置
- **存档/读档**：点击💾保存当前进度到9个槽位之一，点击📂加载
- **剧情回溯**：点击📖打开历史面板，点击任意对话跳转到该时间点
- **导出故事**：在设置面板中点击“导出故事”下载 TXT 文件

### 自定义世界
1. 点击🌍按钮打开世界切换面板
2. 点击“新建世界”按钮
3. 填写：
   - **世界名称**：如“魔法学院”
   - **图标**：选择一个 emoji 或上传图片
   - **描述**：简要介绍世界观
   - **故事背景**：用于 AI 生成剧情的背景设定（如“这是一个充满魔法与龙的奇幻世界”）
   - **全局提示词**：指导 AI 角色行为的系统提示（如“所有角色都会使用魔法”）
4. 保存后即可在列表中选择，开始全新的故事线

## 🔧 配置说明

### API 密钥
- **DeepSeek API**：在 [DeepSeek 平台](https://platform.deepseek.com/) 注册获取，填入 `config.php` 的 `DEEPSEEK_API_KEY`
- **TTS 语音**：使用第三方免费接口，无需密钥，如需更换请修改 `game.php` 中的 `tts` 函数

### 数据库表
```sql
users                    # 用户账户
user_game_data           # 游戏设置、API 密钥、世界观配置
characters               # 角色信息
conversations            # 对话历史
important_events         # 重要事件（长期记忆）
endings                  # 已解锁结局
cg_collection            # CG 图鉴
```

### 情感关键词配置
在 `script.js` 的 `detectEmotionFromText()` 函数中可自定义情感关键词映射：
```javascript
const keywords = {
    '开心': ['高兴', '快乐', '微笑', '哈哈', '喜欢'],
    '生气': ['生气', '愤怒', '讨厌', '恨', '不爽'],
    '悲伤': ['悲伤', '难过', '哭泣', '伤心', '失望'],
    '害羞': ['害羞', '脸红', '尴尬', '不好意思'],
    // 可继续添加
};
```

## 🐛 故障排除

| 问题 | 可能原因 | 解决方案 |
|------|----------|----------|
| 登录后白屏 | 数据库连接失败 | 检查 `config.php` 配置，确认 MySQL 服务运行 |
| AI 接口调用失败（HTTP 401） | API 密钥错误或格式问题 | 确认密钥有效，检查 `game.php` 的 `getApiKey()` 函数 |
| 语音无法播放 | TTS API 失效或浏览器限制 | 更换 TTS 接口或检查浏览器控制台网络请求 |
| 新世界无法切换 | 事件绑定失败或后端未接收参数 | 确保 `script.js` 中世界点击事件正确绑定，`game.php` 接收 `world_prompt` 参数 |
| 存档不保存 | localStorage 被禁用 | 检查浏览器设置，或改用服务器存档（需扩展后端） |
| 立绘不切换 | 情感关键词不匹配 | 调整 `detectEmotionFromText()` 中的关键词，或手动上传对应表情图片 |

## 📈 扩展开发

### 添加新功能
1. **战斗系统**：扩展 `game.php` 的 `chat` 动作，识别战斗指令并处理数值
2. **道具系统**：新增 `items` 表，在对话中通过选项获取/使用道具
3. **更多 AI 模型**：支持 OpenAI、Claude 等，修改 `callDeepSeekAPI()` 函数
4. **多人互动**：添加房间系统，多个玩家与同一 AI 角色对话

### 自定义样式
- **主题颜色**：修改 `style.css` 中的 CSS 变量（`--glass-bg`、`--primary-color` 等）
- **动画效果**：调整 `@keyframes` 定义，如 `fadeInUp`、`pulse`
- **布局调整**：修改 `.game-container` 的网格布局比例

### 数据库扩展
如需新增数据表，请在 `database.sql` 和 `infinityfree_database.sql` 中同步添加，并更新 `db.php` 中的相关操作。

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request 来改进项目！

1. Fork 本仓库
2. 创建功能分支：`git checkout -b feature/your-feature`
3. 提交更改：`git commit -m 'Add some feature'`
4. 推送到分支：`git push origin feature/your-feature`
5. 提交 Pull Request

## 📄 许可证

本项目采用 MIT 许可证。详见 [LICENSE](LICENSE) 文件。

## 🙏 致谢

- **DeepSeek**：提供强大的对话生成 API
- **Glassmorphism 设计**：灵感来源于 macOS 和现代 UI 趋势
- **开源社区**：感谢所有贡献者和用户反馈

## 📞 联系与支持

- **GitHub Issues**：[提交问题或建议](https://github.com/qingshanjiluo/aigal/issues)
- **邮箱**：可通过 GitHub 个人资料联系开发者

---

**祝您游戏愉快，创作出独一无二的互动故事！** ✨

*如果觉得这个项目有帮助，请给个 ⭐ Star 支持一下！*
