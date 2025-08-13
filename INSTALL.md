# SiteStats 插件安装指南

## 快速安装

### 步骤 1：上传插件文件

1. 将整个 `SiteStats` 文件夹上传到您的 Typecho 网站的 `usr/plugins/` 目录
2. 确保文件结构如下：
   ```
   usr/plugins/SiteStats/
   ├── Plugin.php
   ├── api.php
   ├── README.md
   ├── INSTALL.md
   ├── example-sidebar.php
   └── assets/
       ├── stats.css
       └── stats.js
   ```

### 步骤 2：激活插件

1. 登录 Typecho 后台管理界面
2. 进入「控制台」→「插件管理」
3. 找到「SiteStats」插件，点击「激活」
4. 激活成功后，插件会自动创建数据存储目录

### 步骤 3：配置插件

1. 在插件列表中找到「SiteStats」，点击「设置」
2. 根据需要调整以下配置：

#### 基础设置
   - **数据更新间隔**：前端统计数据刷新间隔（默认 3000 毫秒）
   - **动画速度**：数字更新动画效果（慢速/正常/快速/无动画）

#### 时间控制设置
   - **防刷间隔**：同一IP重复访问的防刷时间（默认 300 秒）
     - 防止恶意刷新页面导致浏览量统计异常
     - 建议设置为 300 秒（5分钟）
   - **会话间隔**：用户会话超时时间（默认 1800 秒）
     - 超过此时间的访问将被视为新会话
     - 建议设置为 1800 秒（30分钟）
   - **在线用户超时**：在线用户检测超时时间（默认 60 秒）
     - 用户无活动超过此时间将不再被视为在线
     - 建议设置为 60 秒（1分钟）

#### 显示设置
   - **自定义数据展示模板**：支持使用变量自定义显示内容
     - 可用变量：`{visitors}`、`{views}`、`{today}`、`{online}`
     - 数字元素需包含 `data-type` 和 `data-count` 属性
   - **自定义CSS样式**：自定义统计组件的外观样式

### 步骤 4：集成到主题

选择以下任一方式将统计组件集成到您的主题中：

#### 方式一：侧边栏集成（推荐）

编辑主题的 `sidebar.php` 文件，在适当位置添加：

```php
<?php if (class_exists('SiteStats_Plugin')): ?>
<section class="widget">
    <h3 class="widget-title">网站统计</h3>
    <?php SiteStats_Plugin::render(); ?>
</section>
<?php endif; ?>
```

#### 方式二：页脚集成

编辑主题的 `footer.php` 文件，在适当位置添加：

```php
<?php if (class_exists('SiteStats_Plugin')): ?>
<div class="site-stats-footer">
    <h4>网站统计</h4>
    <?php SiteStats_Plugin::render(); ?>
</div>
<?php endif; ?>
```

#### 方式三：自定义位置

在任何模板文件中添加：

```php
<?php 
if (class_exists('SiteStats_Plugin')) {
    SiteStats_Plugin::render();
}
?>
```

## 详细配置

### 文件权限设置

确保以下目录具有写入权限：

```bash
chmod 755 usr/plugins/SiteStats/
chmod 755 usr/plugins/SiteStats/data/  # 插件激活时自动创建
```

### 服务器要求

- **PHP 版本**：5.6 或更高（推荐 7.0+）
- **Typecho 版本**：1.0 或更高
- **必需扩展**：json
- **可选扩展**：无

### 数据目录结构

插件激活后会自动创建以下数据文件：

```
usr/plugins/SiteStats/data/
├── .htaccess       # 访问保护文件
├── stats.json      # 总体统计数据
├── visits.json     # 访问记录数据
└── online.json     # 在线用户数据
```

## 主题集成示例

### Bootstrap 主题

```php
<?php if (class_exists('SiteStats_Plugin')): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">📊 网站统计</h5>
    </div>
    <div class="card-body">
        <?php SiteStats_Plugin::render(); ?>
    </div>
</div>
<?php endif; ?>
```

### Material Design 主题

```php
<?php if (class_exists('SiteStats_Plugin')): ?>
<div class="mdui-card mdui-m-b-2">
    <div class="mdui-card-primary">
        <div class="mdui-card-primary-title">网站统计</div>
    </div>
    <div class="mdui-card-content">
        <?php SiteStats_Plugin::render(); ?>
    </div>
</div>
<?php endif; ?>
```

### 简约主题

```php
<?php if (class_exists('SiteStats_Plugin')): ?>
<aside class="widget widget-stats">
    <h3 class="widget-title">统计信息</h3>
    <div class="widget-content">
        <?php SiteStats_Plugin::render(); ?>
    </div>
</aside>
<?php endif; ?>
```

## PJAX 兼容配置

如果您的主题使用了 PJAX，插件已自动兼容。但如果遇到问题，可以手动添加以下代码：

```javascript
// 在 PJAX 完成后重新初始化
$(document).on('pjax:complete', function() {
    if (typeof window.cleanupSiteStats === 'function') {
        window.cleanupSiteStats();
    }
    // 重新加载统计脚本
    if (typeof SiteStatsManager !== 'undefined') {
        new SiteStatsManager();
    }
});
```

## 配置示例

### 自定义数据展示模板

以下是一些常用的模板示例：

#### 简洁风格
```html
<div class="stats-welcome-text">欢迎访问本站！</div>
<div class="stats-grid">
    <div class="stats-item">
        <span class="stats-label">访客</span>
        <span class="stats-value" data-type="visitors" data-count="0">{visitors}</span>
    </div>
    <div class="stats-item">
        <span class="stats-label">浏览</span>
        <span class="stats-value" data-type="views" data-count="0">{views}</span>
    </div>
    <div class="stats-item">
        <span class="stats-label">今日</span>
        <span class="stats-value" data-type="today" data-count="0">{today}</span>
    </div>
    <div class="stats-item">
        <span class="stats-label">在线</span>
        <span class="stats-value" data-type="online" data-count="0">{online}</span>
    </div>
</div>
```

#### 卡片风格
```html
<div class="stats-cards">
    <div class="stats-card">
        <div class="stats-icon">👥</div>
        <div class="stats-info">
            <div class="stats-number" data-type="visitors" data-count="0">{visitors}</div>
            <div class="stats-text">总访客</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-icon">📊</div>
        <div class="stats-info">
            <div class="stats-number" data-type="views" data-count="0">{views}</div>
            <div class="stats-text">总浏览</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-icon">🌟</div>
        <div class="stats-info">
            <div class="stats-number" data-type="today" data-count="0">{today}</div>
            <div class="stats-text">今日访问</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-icon">🟢</div>
        <div class="stats-info">
            <div class="stats-number" data-type="online" data-count="0">{online}</div>
            <div class="stats-text">在线用户</div>
        </div>
    </div>
</div>
```

### 时间间隔配置建议

根据不同网站类型的推荐配置：

#### 个人博客
- 防刷间隔：300秒（5分钟）
- 会话间隔：1800秒（30分钟）
- 在线用户超时：60秒（1分钟）
- 数据更新间隔：5000毫秒（5秒）

#### 商业网站
- 防刷间隔：180秒（3分钟）
- 会话间隔：900秒（15分钟）
- 在线用户超时：30秒（30秒）
- 数据更新间隔：3000毫秒（3秒）

#### 高流量网站
- 防刷间隔：120秒（2分钟）
- 会话间隔：600秒（10分钟）
- 在线用户超时：30秒（30秒）
- 数据更新间隔：10000毫秒（10秒）

## 自定义样式

### 覆盖默认样式

在主题的 CSS 文件中添加：

```css
/* 自定义统计组件样式 */
.site-stats {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
}

.stats-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid #dee2e6;
}

.stats-value {
    color: #007bff;
    font-weight: bold;
}
```

### 卡片风格样式

```css
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}

.stats-card {
    background: #fff;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.stats-icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.stats-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #007bff;
}

.stats-text {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.25rem;
}
```

### 深色主题适配

```css
@media (prefers-color-scheme: dark) {
    .site-stats {
        background: #343a40;
        color: #fff;
    }
    
    .stats-item {
        border-bottom-color: #495057;
    }
}
```

## 故障排除

### 常见问题

#### 1. 插件激活失败

**原因**：数据目录创建失败

**解决方案**：
```bash
# 手动创建目录并设置权限
mkdir usr/plugins/SiteStats/data
chmod 755 usr/plugins/SiteStats/data
```

#### 2. 统计数据不显示

**原因**：模板中未添加渲染代码

**解决方案**：检查主题文件中是否正确添加了 `SiteStats_Plugin::render()` 调用

#### 3. 数据不更新

**原因**：JavaScript 文件加载失败或 API 接口不可访问

**解决方案**：
1. 检查浏览器控制台错误
2. 确认 `assets/stats.js` 文件可访问
3. 检查 `api.php` 文件权限

#### 4. PJAX 兼容问题

**原因**：页面切换时统计组件未重新初始化

**解决方案**：参考上面的 PJAX 兼容配置

### 调试模式

在浏览器控制台中运行以下代码启用调试：

```javascript
// 启用调试模式
window.SiteStatsDebug = true;

// 查看当前配置
console.log(window.SiteStatsConfig);

// 手动触发数据更新
if (window.siteStatsInstance) {
    window.siteStatsInstance.updateStats();
}
```

## 升级指南

### 从旧版本升级

1. 备份现有数据文件（如果有）
2. 上传新版本文件覆盖旧文件
3. 在后台重新激活插件
4. 检查配置是否需要更新

### 数据迁移

如果从其他统计插件迁移，可以手动编辑 `data/stats.json` 文件：

```json
{
    "total_visitors": 1000,
    "total_views": 5000,
    "updated_at": 1640995200
}
```

## 技术支持

如果在安装过程中遇到问题：

1. 检查服务器环境是否满足要求
2. 确认文件权限设置正确
3. 查看服务器错误日志
4. 检查 Typecho 版本兼容性

## 卸载插件

如需卸载插件：

1. 在后台插件管理中禁用插件
2. 从主题文件中移除相关代码
3. 删除插件文件夹（可选保留数据文件）

**注意**：卸载插件不会自动删除数据文件，如需完全清理，请手动删除 `data/` 目录。