<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 网站统计插件
 * 
 * @package SiteStats
 * @author QingYun
 * @version 1.1.0
 * @link https://github.com/ThisIsQingYun/Typecho-SiteStats
 */
class SiteStats_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // 创建数据存储目录
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            if (!@mkdir($dataDir, 0755, true)) {
                throw new Typecho_Plugin_Exception('无法创建数据目录: ' . $dataDir);
            }
        }
        
        // 创建 .htaccess 文件保护数据目录
        $htaccessFile = $dataDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            @file_put_contents($htaccessFile, "Deny from all\n");
        }
        
        // 注册路由
        Typecho_Plugin::factory('Widget_Archive')->header = array('SiteStats_Plugin', 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('SiteStats_Plugin', 'footer');
        
        return '网站统计插件激活成功';
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        return '网站统计插件已禁用';
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 时间设置
        $updateInterval = new Typecho_Widget_Helper_Form_Element_Text('updateInterval', NULL, '3000', 
            _t('数据更新间隔'), _t('统计数据自动更新间隔，单位毫秒，默认3000毫秒（3秒）'));
        $form->addInput($updateInterval);
        
        // 防刷间隔设置
        $antiSpamInterval = new Typecho_Widget_Helper_Form_Element_Text(
            'antiSpamInterval', 
            null, 
            '300', 
            _t('防刷间隔 (秒)'), 
            _t('同一IP在此时间内的重复访问不会增加浏览量，建议设置为300秒（5分钟)')
        );
        $form->addInput($antiSpamInterval);
        
        // 会话间隔设置
        $sessionInterval = new Typecho_Widget_Helper_Form_Element_Text(
            'sessionInterval', 
            null, 
            '1800', 
            _t('会话间隔 (秒)'), 
            _t('同一IP超过此时间后的访问将被视为新会话，建议设置为1800秒（30分钟)')
        );
        $form->addInput($sessionInterval);
        
        // 在线用户检测间隔设置
        $onlineUserTimeout = new Typecho_Widget_Helper_Form_Element_Text(
            'onlineUserTimeout', 
            null, 
            '60', 
            _t('在线用户超时 (秒)'), 
            _t('用户超过此时间无活动将不再被视为在线，建议设置为60秒（1分钟)')
        );
        $form->addInput($onlineUserTimeout);
        
        $animationSpeed = new Typecho_Widget_Helper_Form_Element_Radio('animationSpeed', 
            array('slow' => _t('慢速'), 'normal' => _t('正常'), 'fast' => _t('快速'), 'none' => _t('无动画')), 'normal', 
            _t('动画速度'), _t('选择数据更新时的动画速度'));
        $form->addInput($animationSpeed);
        
        // 自定义数据展示内容
        $displayTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('displayTemplate', NULL, 
            '<div class="stats-welcome-text">欢迎访问本站！</div>' .
            '<p>您是第 <span class="stats-counter stats-number" data-type="visitors" data-count="{visitors}">{visitors}</span> 位访客</p>' .
            '<p>本站共被浏览 <span class="stats-counter stats-highlight" data-type="views" data-count="{views}">{views}</span> 次</p>' .
            '<p>这是您第 <span class="stats-counter stats-number" data-type="today" data-count="{today}">{today}</span> 次访问本站</p>' .
            '<p><span class="online-indicator"></span>当前在线用户数: <span class="stats-counter stats-highlight" data-type="online" data-count="{online}">{online}</span></p>' .
            '<div class="stats-ipv6-info"><span class="stats-ipv6-icon">🌐</span>本站已支持 <span class="stats-ipv6-text">IPv6</span> 访问</div>', 
            _t('数据展示模板'), 
            _t('自定义统计数据的展示内容，支持以下变量：<br>' .
               '<strong>{visitors}</strong> - 总访客数<br>' .
               '<strong>{views}</strong> - 总浏览量<br>' .
               '<strong>{today}</strong> - 今日访问次数<br>' .
               '<strong>{online}</strong> - 在线用户数<br>' .
               '注意：数字元素必须包含 data-type 和 data-count 属性才能实现动画更新'));
        $form->addInput($displayTemplate);
        
        // 自定义CSS
        $customCSS = new Typecho_Widget_Helper_Form_Element_Textarea('customCSS', NULL, '', 
            _t('自定义CSS样式'), _t('添加自定义CSS样式，可以完全自定义统计面板的外观'));
        $form->addInput($customCSS);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 输出头部资源
     * 
     * @access public
     * @return void
     */
    public static function header()
    {
        $options = Helper::options();
        $pluginUrl = $options->pluginUrl . '/SiteStats';
        
        echo '<link rel="stylesheet" href="' . $pluginUrl . '/assets/stats.css">' . "\n";
    }
    
    /**
     * 输出底部资源
     * 
     * @access public
     * @return void
     */
    public static function footer()
    {
        $options = Helper::options();
        $pluginUrl = $options->pluginUrl . '/SiteStats';
        
        echo '<script src="' . $pluginUrl . '/assets/stats.js"></script>' . "\n";
        
        // 初始化配置
        $config = $options->plugin('SiteStats');
        $updateInterval = isset($config->updateInterval) ? intval($config->updateInterval) : 3000;
        $antiSpamInterval = isset($config->antiSpamInterval) ? intval($config->antiSpamInterval) : 300;
        $sessionInterval = isset($config->sessionInterval) ? intval($config->sessionInterval) : 1800;
        $onlineUserTimeout = isset($config->onlineUserTimeout) ? intval($config->onlineUserTimeout) : 60;
        $animationSpeed = isset($config->animationSpeed) ? $config->animationSpeed : 'normal';
        $displayTemplate = isset($config->displayTemplate) ? $config->displayTemplate : '';
        $customCSS = isset($config->customCSS) ? $config->customCSS : '';
        
        echo '<script>' . "\n";
        echo 'if (typeof SiteStatsConfig === "undefined") {' . "\n";
        echo '    window.SiteStatsConfig = {' . "\n";
        echo '        apiUrl: "' . $pluginUrl . '/api.php",' . "\n";
        echo '        updateInterval: ' . $updateInterval . ',' . "\n";
        echo '        antiSpamInterval: ' . $antiSpamInterval . ',' . "\n";
        echo '        sessionInterval: ' . $sessionInterval . ',' . "\n";
        echo '        onlineUserTimeout: ' . $onlineUserTimeout . ',' . "\n";
        echo '        animationSpeed: "' . $animationSpeed . '",' . "\n";
        echo '        displayTemplate: ' . json_encode($displayTemplate) . "\n";
        echo '    };' . "\n";
        echo '}' . "\n";
        echo '</script>' . "\n";
        
        // 输出自定义CSS
        if (!empty($customCSS)) {
            echo '<style>' . "\n";
            echo $customCSS . "\n";
            echo '</style>' . "\n";
        }
    }
    
    /**
     * 渲染统计组件
     * 
     * @access public
     * @return void
     */
    public static function render()
    {
        $config = Helper::options()->plugin('SiteStats');
        $displayTemplate = isset($config->displayTemplate) ? $config->displayTemplate : '';
        
        // 如果没有自定义模板，使用默认模板
        if (empty($displayTemplate)) {
            $displayTemplate = '<div class="stats-welcome-text">欢迎访问本站！</div>' . "\n" .
                '<p>您是第 <span class="stats-counter stats-number" data-type="visitors" data-count="{visitors}">{visitors}</span> 位访客</p>' . "\n" .
                '<p>本站共被浏览 <span class="stats-counter stats-highlight" data-type="views" data-count="{views}">{views}</span> 次</p>' . "\n" .
                '<p>这是您第 <span class="stats-counter stats-number" data-type="today" data-count="{today}">{today}</span> 次访问本站</p>' . "\n" .
                '<p><span class="online-indicator"></span>当前在线用户数: <span class="stats-counter stats-highlight" data-type="online" data-count="{online}">{online}</span></p>' . "\n" .
                '<div class="stats-ipv6-info"><span class="stats-ipv6-icon">🌐</span>本站已支持 <span class="stats-ipv6-text">IPv6</span> 访问</div>';
        }
        
        // 将变量替换为初始值0
        $html = str_replace(
            array('{visitors}', '{views}', '{today}', '{online}'),
            array('0', '0', '0', '0'),
            $displayTemplate
        );
        
        echo '<div class="site-stats-content" id="site-stats-content">' . "\n";
        echo $html . "\n";
        echo '</div>' . "\n";
    }
}