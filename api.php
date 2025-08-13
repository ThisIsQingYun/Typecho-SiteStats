<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    // 如果不在Typecho环境中，尝试加载Typecho
    $typechoPath = dirname(dirname(dirname(__FILE__))) . '/index.php';
    if (file_exists($typechoPath)) {
        require_once $typechoPath;
    } else {
        // 直接处理请求，不依赖Typecho
        define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(__FILE__))));
    }
}

/**
 * 网站统计API接口
 * 处理统计数据的记录和获取
 */
class SiteStatsAPI {
    private $dataDir;
    private $statsFile;
    private $visitsFile;
    private $onlineFile;
    private $config;
    
    public function __construct() {
        // 设置数据存储目录
        $this->dataDir = __DIR__ . '/data';
        $this->statsFile = $this->dataDir . '/stats.json';
        $this->visitsFile = $this->dataDir . '/visits.json';
        $this->onlineFile = $this->dataDir . '/online.json';
        
        $this->loadConfig();
        $this->initStorage();
    }
    
    /**
     * 加载插件配置
     */
    private function loadConfig() {
        $this->config = [
            'antiSpamInterval' => 300,    // 防刷间隔（秒）
            'sessionInterval' => 1800,    // 会话间隔（秒）
            'onlineUserTimeout' => 60     // 在线用户超时（秒）
        ];
        
        // 尝试从Typecho获取配置
        if (defined('__TYPECHO_ROOT_DIR__') && class_exists('Typecho_Widget_Helper_Form')) {
            try {
                $options = Typecho_Widget::widget('Widget_Options');
                $pluginConfig = $options->plugin('SiteStats');
                
                if ($pluginConfig) {
                    if (isset($pluginConfig->antiSpamInterval)) {
                        $this->config['antiSpamInterval'] = intval($pluginConfig->antiSpamInterval);
                    }
                    if (isset($pluginConfig->sessionInterval)) {
                        $this->config['sessionInterval'] = intval($pluginConfig->sessionInterval);
                    }
                    if (isset($pluginConfig->onlineUserTimeout)) {
                        $this->config['onlineUserTimeout'] = intval($pluginConfig->onlineUserTimeout);
                    }
                }
            } catch (Exception $e) {
                // 使用默认配置
            }
        }
    }
    
    /**
     * 初始化存储目录和文件
     */
    private function initStorage() {
        // 创建数据目录
        if (!is_dir($this->dataDir)) {
            @mkdir($this->dataDir, 0755, true);
        }
        
        // 创建 .htaccess 文件保护数据目录
        $htaccessFile = $this->dataDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            @file_put_contents($htaccessFile, "Deny from all\n");
        }
        
        // 初始化统计文件
        if (!file_exists($this->statsFile)) {
            $defaultStats = [
                'total_visitors' => 0,
                'total_views' => 0,
                'updated_at' => time()
            ];
            $this->saveJsonFile($this->statsFile, $defaultStats);
        }
        
        // 初始化访问记录文件
        if (!file_exists($this->visitsFile)) {
            $this->saveJsonFile($this->visitsFile, []);
        }
        
        // 初始化在线用户文件
        if (!file_exists($this->onlineFile)) {
            $this->saveJsonFile($this->onlineFile, []);
        }
    }
    
    /**
     * 读取JSON文件
     */
    private function loadJsonFile($file) {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return [];
        }
        
        $data = @json_decode($content, true);
        return $data !== null ? $data : [];
    }
    
    /**
     * 保存JSON文件
     */
    private function saveJsonFile($file, $data) {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return @file_put_contents($file, $json, LOCK_EX) !== false;
    }
    
    /**
     * 获取客户端IP
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * 记录访问
     */
    public function recordVisit($isNewSession = false) {
        $ip = $this->getClientIP();
        $now = time();
        $today = date('Y-m-d');
        
        // 加载访问记录
        $visits = $this->loadJsonFile($this->visitsFile);
        
        $isNewVisitor = false;
        $shouldCountView = false;
        $todayVisitCount = 0;
        
        if (!isset($visits[$ip])) {
            // 新访客
            $visits[$ip] = [
                'first_visit' => $now,
                'last_visit' => $now,
                'visit_count' => 1,
                'last_page_view' => $now,
                'last_visit_date' => $today,
                'last_session' => $now
            ];
            
            $isNewVisitor = true;
            $shouldCountView = true;
            $todayVisitCount = 1;
            
            // 增加总访客数
            $this->incrementStat('total_visitors');
        } else {
            // 老访客
            $visitor = $visits[$ip];
            $lastPageView = $visitor['last_page_view'] ?? 0;
            $lastSession = $visitor['last_session'] ?? 0;
            
            // 检查是否应该计算浏览量（防刷间隔内不重复计算）
            if ($now - $lastPageView >= $this->config['antiSpamInterval']) {
                $shouldCountView = true;
                $visits[$ip]['last_page_view'] = $now;
            }
            
            // 检查今天的访问次数
            $lastVisitDate = $visitor['last_visit_date'] ?? '';
            
            if ($today === $lastVisitDate) {
                // 如果是新会话或者距离上次会话超过配置的会话间隔，才增加访问次数
                if ($isNewSession || ($now - $lastSession >= $this->config['sessionInterval'])) {
                    $todayVisitCount = $visitor['visit_count'] + 1;
                    $visits[$ip]['visit_count'] = $todayVisitCount;
                    $visits[$ip]['last_session'] = $now;
                } else {
                    $todayVisitCount = $visitor['visit_count'];
                }
            } else {
                // 新的一天，重置访问次数
                $todayVisitCount = 1;
                $visits[$ip]['visit_count'] = 1;
                $visits[$ip]['last_visit_date'] = $today;
                $visits[$ip]['last_session'] = $now;
            }
            
            $visits[$ip]['last_visit'] = $now;
        }
        
        // 保存访问记录
        $this->saveJsonFile($this->visitsFile, $visits);
        
        // 增加浏览量
        if ($shouldCountView) {
            $this->incrementStat('total_views');
        }
        
        // 更新在线用户
        $this->updateOnlineUser($ip);
        
        return [
            'is_new_visitor' => $isNewVisitor,
            'today_visit_count' => $todayVisitCount
        ];
    }
    
    /**
     * 更新在线用户
     */
    public function updateOnlineUser($ip = null) {
        if ($ip === null) {
            $ip = $this->getClientIP();
        }
        $now = time();
        $timeoutThreshold = $now - $this->config['onlineUserTimeout'];
        
        // 加载在线用户数据
        $online = $this->loadJsonFile($this->onlineFile);
        
        // 清理超时的在线记录
        foreach ($online as $userIp => $lastActivity) {
            if ($lastActivity < $timeoutThreshold) {
                unset($online[$userIp]);
            }
        }
        
        // 更新当前用户活动时间
        $online[$ip] = $now;
        
        // 保存在线用户数据
        $this->saveJsonFile($this->onlineFile, $online);
    }
    
    /**
     * 增加统计数据
     */
    private function incrementStat($key) {
        $stats = $this->loadJsonFile($this->statsFile);
        
        if (!isset($stats[$key])) {
            $stats[$key] = 0;
        }
        
        $stats[$key]++;
        $stats['updated_at'] = time();
        
        $this->saveJsonFile($this->statsFile, $stats);
    }
    
    /**
     * 获取统计数据
     */
    public function getStats() {
        $ip = $this->getClientIP();
        $today = date('Y-m-d');
        
        // 获取总访客数和总浏览量
        $stats = $this->loadJsonFile($this->statsFile);
        $totalVisitors = $stats['total_visitors'] ?? 0;
        $totalViews = $stats['total_views'] ?? 0;
        
        // 获取当前用户今天的访问次数
        $visits = $this->loadJsonFile($this->visitsFile);
        $todayVisitCount = 1;
        
        if (isset($visits[$ip])) {
            $visitor = $visits[$ip];
            $lastVisitDate = $visitor['last_visit_date'] ?? '';
            
            if ($today === $lastVisitDate) {
                $todayVisitCount = $visitor['visit_count'] ?? 1;
            }
        }
        
        // 获取在线用户数（先清理过期数据）
        $online = $this->loadJsonFile($this->onlineFile);
        $now = time();
        $oneMinuteAgo = $now - 60;
        
        $activeUsers = [];
        foreach ($online as $userIp => $lastActivity) {
            if ($lastActivity >= $oneMinuteAgo) {
                $activeUsers[$userIp] = $lastActivity;
            }
        }
        
        // 如果有清理，保存更新后的在线用户数据
        if (count($activeUsers) !== count($online)) {
            $this->saveJsonFile($this->onlineFile, $activeUsers);
        }
        
        return [
            'total_visitors' => $totalVisitors,
            'total_views' => $totalViews,
            'today_visit_count' => $todayVisitCount,
            'online_users' => count($activeUsers)
        ];
    }
    
    /**
     * 清理过期的在线用户数据
     */
    public function cleanupOnlineUsers() {
        $online = $this->loadJsonFile($this->onlineFile);
        $oneMinuteAgo = time() - 60;
        $onlineCleaned = false;
        
        foreach ($online as $ip => $lastActivity) {
            if ($lastActivity < $oneMinuteAgo) {
                unset($online[$ip]);
                $onlineCleaned = true;
            }
        }
        
        if ($onlineCleaned) {
            $this->saveJsonFile($this->onlineFile, $online);
        }
    }
}

// 处理API请求
header('Content-Type: application/json; charset=utf-8');

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// 检查是否是AJAX请求
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    $api = new SiteStatsAPI();
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'record_visit':
            $isNewSession = isset($_POST['is_new_session']) ? (bool)$_POST['is_new_session'] : false;
            $result = $api->recordVisit($isNewSession);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'get_stats':
            // 更新在线用户状态
            $api->updateOnlineUser();
            $data = $api->getStats();
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>