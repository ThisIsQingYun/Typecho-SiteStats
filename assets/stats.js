/**
 * 网站统计组件 JavaScript
 * 处理统计数据的获取和显示
 * 兼容PJAX页面切换
 */

// 使用立即执行函数表达式(IIFE)避免全局作用域污染
(function() {
    'use strict';
    
    // 检查是否已经存在SiteStatsManager类，避免重复声明
    if (typeof window.SiteStatsManager !== 'undefined') {
        console.log('SiteStatsManager already exists, skipping initialization');
        return;
    }
    
    // 检查是否已经执行过初始化
    if (window.siteStatsInitialized) {
        console.log('SiteStats already initialized, skipping');
        return;
    }
    
    // 标记已初始化
    window.siteStatsInitialized = true;
    
    class SiteStatsManager {
        constructor() {
            this.statsContainer = null;
            this.statsDataContainer = null;
            this.statsIPv6Container = null;
            this.updateInterval = null;
            this.isUpdating = false;
            this.retryCount = 0;
            this.maxRetries = 3;
            this.hasRecordedVisit = false;
            this.sessionKey = 'site_stats_session';
            
            // 缓存访客数据，只在页面加载时获取一次
            this.cachedVisitorData = null;
            
            // 获取配置
            this.config = window.SiteStatsConfig || {
                apiUrl: '/usr/plugins/SiteStats/api.php',
                updateInterval: 3000,
                showIPv6: true
            };
            
            this.init();
        }
        
        /**
         * 初始化
         */
        init() {
            // 等待DOM加载完成
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
            
            // PJAX兼容性处理
            this.setupPjaxCompatibility();
        }
        
        /**
         * 设置PJAX兼容性
         */
        setupPjaxCompatibility() {
            // 监听PJAX事件
            document.addEventListener('pjax:start', () => {
                this.cleanup();
                document.body.classList.add('pjax-loading');
            });
            
            document.addEventListener('pjax:end', () => {
                document.body.classList.remove('pjax-loading');
                // 重置初始化状态
                this.hasRecordedVisit = false;
                this.cachedVisitorData = null;
                // 延迟重新初始化，确保DOM已更新
                setTimeout(() => {
                    this.setup();
                }, 100);
            });
            
            // 兼容其他AJAX导航库
            document.addEventListener('turbo:load', () => {
                this.hasRecordedVisit = false;
                this.cachedVisitorData = null;
                this.setup();
            });
            
            document.addEventListener('swup:contentReplaced', () => {
                this.hasRecordedVisit = false;
                this.cachedVisitorData = null;
                this.setup();
            });
        }
        
        /**
         * 检查是否为新会话
         */
        isNewSession() {
            try {
                const sessionData = sessionStorage.getItem(this.sessionKey);
                if (!sessionData) {
                    // 没有会话数据，标记为新会话
                    sessionStorage.setItem(this.sessionKey, JSON.stringify({
                        startTime: Date.now(),
                        visitRecorded: false
                    }));
                    return true;
                }
                
                const session = JSON.parse(sessionData);
                return !session.visitRecorded;
            } catch (error) {
                console.warn('Session check failed:', error);
                return true; // 出错时默认为新会话
            }
        }
        
        /**
         * 标记会话已记录访问
         */
        markSessionVisitRecorded() {
            try {
                const sessionData = sessionStorage.getItem(this.sessionKey);
                if (sessionData) {
                    const session = JSON.parse(sessionData);
                    session.visitRecorded = true;
                    sessionStorage.setItem(this.sessionKey, JSON.stringify(session));
                }
            } catch (error) {
                console.warn('Failed to mark session visit:', error);
            }
        }
        
        /**
         * 检查页面是否可见
         */
        isPageVisible() {
            return !document.hidden;
        }
        
        /**
         * 应用主题和样式配置
         */
        applyThemeAndStyle() {
            const statsElement = this.statsContainer.closest('.site-stats');
            if (!statsElement) return;
            
            const config = this.config;
            
            // 应用主题
            if (config.theme && config.theme !== 'auto') {
                statsElement.setAttribute('data-theme', config.theme);
            } else {
                // 自动检测系统主题
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                statsElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
                
                // 监听系统主题变化
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                    if (config.theme === 'auto') {
                        statsElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
                    }
                });
            }
            
            // 应用样式
            if (config.cardStyle) {
                statsElement.setAttribute('data-style', config.cardStyle);
            }
        }
        
        /**
         * 设置统计组件
         */
        setup() {
            this.statsContainer = document.getElementById('site-stats-content');
            
            if (!this.statsContainer) {
                console.warn('Site stats container not found');
                return;
            }
            
            // 应用主题和样式配置
            this.applyThemeAndStyle();
            
            // IPv6信息现在直接在PHP中渲染，无需JavaScript动态添加
            
            // 只在页面可见且为新会话时记录访问
            if (this.isPageVisible() && this.isNewSession()) {
                this.recordVisit(true);
            } else {
                // 即使不记录访问，也要获取统计数据
                this.updateStats();
            }
            
            // 定期更新统计数据，但不更新访客数
            this.updateInterval = setInterval(() => {
                if (this.isPageVisible()) {
                    this.updateDynamicStats();
                }
            }, this.config.updateInterval);
            
            // 页面卸载时清理
            window.addEventListener('beforeunload', () => {
                this.cleanup();
            });
        }
        
        /**
         * IPv6信息现在直接在PHP中渲染，此方法已废弃
         */
        initializeIPv6Display() {
            // IPv6信息现在直接在PHP模板中渲染，无需JavaScript动态添加
            // 保留此方法以维持向后兼容性
        }
        
        /**
         * 记录访问
         */
        async recordVisit(isNewSession = false) {
            // 防止重复记录
            if (this.hasRecordedVisit && !isNewSession) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'record_visit');
                formData.append('is_new_session', isNewSession ? '1' : '0');
                
                const response = await fetch(this.config.apiUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        this.hasRecordedVisit = true;
                        this.cachedVisitorData = result.data;
                        this.markSessionVisitRecorded();
                        
                        // 记录访问后立即获取完整统计数据
                        this.updateStats();
                    } else {
                        console.warn('Failed to record visit:', result.error);
                        this.showError('记录访问失败');
                    }
                } else {
                    throw new Error(`HTTP ${response.status}`);
                }
            } catch (error) {
                console.error('Error recording visit:', error);
                this.showError('网络连接失败');
                
                // 即使记录失败，也尝试获取统计数据
                this.updateStats();
            }
        }
        
        /**
         * 更新统计数据（包含访客数据）
         */
        async updateStats() {
            if (this.isUpdating) {
                return;
            }
            
            this.isUpdating = true;
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_stats');
                
                const response = await fetch(this.config.apiUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                if (response.ok) {
                    const result = await response.json();
                    console.log('API Response:', result); // 调试日志
                    if (result.success) {
                        console.log('Updating stats with data:', result.data); // 调试日志
                        this.displayStats(result.data);
                        this.retryCount = 0; // 重置重试计数
                    } else {
                        throw new Error(result.error || 'Unknown error');
                    }
                } else {
                    throw new Error(`HTTP ${response.status}`);
                }
            } catch (error) {
                console.error('Error updating stats:', error);
                this.handleUpdateError();
            } finally {
                this.isUpdating = false;
            }
        }
        
        /**
         * 更新动态统计数据（不包含访客数据）
         */
        async updateDynamicStats() {
            // 只更新在线用户数，不重新获取访客数据
            await this.updateStats();
        }
        
        /**
         * 显示统计数据（初始化时使用）
         */
        displayStats(data) {
            const container = this.statsContainer;
            console.log('displayStats called, container:', container); // 调试日志
            if (!container) {
                console.log('No container found in displayStats'); // 调试日志
                return;
            }
            
            // 检查是否已经初始化
            const existingCounter = container.querySelector('.stats-counter');
            console.log('Existing counter found:', existingCounter); // 调试日志
            console.log('Container current HTML:', container.innerHTML); // 调试日志
            if (existingCounter) {
                // 检查现有元素是否有正确的 data-type 属性
                const hasDataType = container.querySelector('[data-type]');
                console.log('Elements with data-type found:', hasDataType); // 调试日志
                if (!hasDataType) {
                    console.log('Existing HTML lacks data-type attributes, reinitializing...'); // 调试日志
                    this.initializeStatsHTML(data);
                    return;
                }
                // 如果已经初始化，只更新数字
                console.log('Container already initialized, updating numbers only'); // 调试日志
                this.updateStatsNumbers(data);
                return;
            }
            
            // 首次初始化，构建完整HTML
            console.log('Initializing HTML for the first time'); // 调试日志
            this.initializeStatsHTML(data);
        }
        
        /**
         * 初始化统计HTML结构
         */
        initializeStatsHTML(data) {
            const container = this.statsContainer;
            if (!container) {
                return;
            }
            
            const config = window.SiteStatsConfig || {};
            let html = '';
            
            // 如果有自定义模板，使用模板
            if (config.displayTemplate) {
                // 使用缓存的访客数据或当前数据
                const visitorData = this.cachedVisitorData || {};
                const todayVisitCount = data.today_visit_count || 1;
                
                // 替换模板中的变量
                html = config.displayTemplate
                    .replace(/{visitors}/g, this.formatNumber(data.total_visitors))
                    .replace(/{views}/g, this.formatNumber(data.total_views))
                    .replace(/{today}/g, this.formatNumber(todayVisitCount))
                    .replace(/{online}/g, this.formatNumber(data.online_users));
                
                // 更新data-count属性
                html = html
                    .replace(/data-count="{visitors}"/g, `data-count="${data.total_visitors}"`)
                    .replace(/data-count="{views}"/g, `data-count="${data.total_views}"`)
                    .replace(/data-count="{today}"/g, `data-count="${todayVisitCount}"`)
                    .replace(/data-count="{online}"/g, `data-count="${data.online_users}"`);
            } else {
                // 使用默认HTML结构（向后兼容）
                const visitorData = this.cachedVisitorData || {};
                const todayVisitCount = data.today_visit_count || 1;
                
                html = `
                    <div class="stats-welcome-text">欢迎访问本站！</div>
                    <p>您是第 <span class="stats-counter stats-number" data-type="visitors" data-count="${data.total_visitors}">${this.formatNumber(data.total_visitors)}</span> 位访客</p>
                    <p>本站共被浏览 <span class="stats-counter stats-highlight" data-type="views" data-count="${data.total_views}">${this.formatNumber(data.total_views)}</span> 次</p>
                    <p>这是您第 <span class="stats-counter stats-number" data-type="today" data-count="${todayVisitCount}">${this.formatNumber(todayVisitCount)}</span> 次访问本站</p>
                    <p><span class="online-indicator"></span>当前在线用户数: <span class="stats-counter stats-highlight" data-type="online" data-count="${data.online_users}">${this.formatNumber(data.online_users)}</span></p>
                    <div class="stats-ipv6-info"><span class="stats-ipv6-icon">🌐</span>本站已支持 <span class="stats-ipv6-text">IPv6</span> 访问</div>
                `;
            }
            
            console.log('Setting container innerHTML with:', html); // 调试日志
            container.innerHTML = html;
            console.log('Container innerHTML set, checking elements:'); // 调试日志
            console.log('Visitors element:', container.querySelector('[data-type="visitors"]')); // 调试日志
            console.log('Views element:', container.querySelector('[data-type="views"]')); // 调试日志
            
            // 根据配置应用动画
            const animationSpeed = config.animationSpeed || 'normal';
            if (animationSpeed !== 'none') {
                container.classList.add('stats-updating');
                
                // 根据速度设置动画持续时间
                let duration = 800; // 默认正常速度
                switch (animationSpeed) {
                    case 'slow':
                        duration = 1200;
                        break;
                    case 'fast':
                        duration = 400;
                        break;
                }
                
                // 动态设置CSS动画持续时间
                container.style.setProperty('--animation-duration', duration + 'ms');
                
                // 移除动画类
                setTimeout(() => {
                    container.classList.remove('stats-updating');
                }, duration);
            }
        }
        
        /**
         * 只更新数字部分，不重建HTML结构
         */
        updateStatsNumbers(data) {
            console.log('updateStatsNumbers called with:', data); // 调试日志
            const container = this.statsContainer;
            if (!container) {
                console.log('No stats container found'); // 调试日志
                return;
            }

            const config = window.SiteStatsConfig || {};
            const animationSpeed = config.animationSpeed || 'normal';

            // 获取今日访问次数
            const todayVisitCount = data.today_visit_count || 1;
            
            // 更新各个数字
            const updates = [
                { type: 'visitors', value: data.total_visitors },
                { type: 'views', value: data.total_views },
                { type: 'today', value: todayVisitCount }
            ];
            
            // 如果显示在线用户数
            if (config.showOnlineUsers !== false) {
                updates.push({ type: 'online', value: data.online_users });
            }
            
            // 逐个更新数字
            updates.forEach(update => {
                const element = container.querySelector(`[data-type="${update.type}"]`);
                console.log(`Looking for element with data-type="${update.type}", found:`, element); // 调试日志
                if (element) {
                    console.log(`Updating ${update.type} from ${element.getAttribute('data-count')} to ${update.value}`); // 调试日志
                    this.animateNumberChange(element, update.value, animationSpeed);
                } else {
                    console.log(`Element with data-type="${update.type}" not found`); // 调试日志
                }
            });
        }
        
        /**
         * 数字变化动画
         */
        animateNumberChange(element, newValue, animationSpeed) {
            const oldValue = parseInt(element.getAttribute('data-count')) || 0;
            
            if (oldValue === newValue) {
                return;
            }
            
            // 设置动画持续时间
            let duration = 600;
            switch (animationSpeed) {
                case 'slow': 
                    duration = 1000; 
                    break;
                case 'fast': 
                    duration = 300; 
                    break;
                case 'none': 
                    duration = 0; 
                    break;
            }
            
            if (duration === 0) {
                // 无动画，直接更新
                element.textContent = this.formatNumber(newValue);
                element.setAttribute('data-count', newValue);
                return;
            }
            
            // 添加更新动画类
            element.classList.add('number-updating');
            
            // 数字递增动画
            const startTime = Date.now();
            const animate = () => {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // 使用缓动函数
                const easeProgress = 1 - Math.pow(1 - progress, 3);
                const currentValue = Math.round(oldValue + (newValue - oldValue) * easeProgress);
                
                element.textContent = this.formatNumber(currentValue);
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    element.textContent = this.formatNumber(newValue);
                    element.setAttribute('data-count', newValue);
                    element.classList.remove('number-updating');
                }
            };
            
            requestAnimationFrame(animate);
        }
        
        /**
         * 格式化数字显示
         */
        formatNumber(num) {
            if (num >= 10000) {
                return (num / 10000).toFixed(1) + '万';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'k';
            }
            return num.toString();
        }
        
        /**
         * 处理更新错误
         */
        handleUpdateError() {
            this.retryCount++;
            
            if (this.retryCount <= this.maxRetries) {
                // 指数退避重试
                const retryDelay = Math.pow(2, this.retryCount) * 1000;
                setTimeout(() => {
                    this.updateStats();
                }, retryDelay);
            } else {
                this.showError('数据加载失败，请刷新页面重试');
            }
        }
        
        /**
         * 显示错误信息
         */
        showError(message) {
            if (!this.statsDataContainer) {
                return;
            }
            
            this.statsDataContainer.innerHTML = `
                <div class="stats-error">
                    <p>⚠️ ${message}</p>
                    <p style="font-size: 0.7rem; margin-top: 0.5rem;">
                        <a href="javascript:location.reload()" style="color: #007bff; text-decoration: none;">
                            点击刷新
                        </a>
                    </p>
                </div>
            `;
        }
        
        /**
         * 清理资源
         */
        cleanup() {
            if (this.updateInterval) {
                clearInterval(this.updateInterval);
                this.updateInterval = null;
            }
            
            this.isUpdating = false;
            this.retryCount = 0;
        }
    }
    
    // 创建全局实例
    window.SiteStatsManager = SiteStatsManager;
    
    // 自动初始化
    new SiteStatsManager();
    
    // 导出清理函数供PJAX使用
    window.cleanupSiteStats = function() {
        if (window.siteStatsInstance) {
            window.siteStatsInstance.cleanup();
        }
        window.siteStatsInitialized = false;
    };
    
})();