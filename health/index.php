<?php
/**
 * System Health Monitor
 * Digital Salon - Sistem sağlık izleme
 */

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/performance.php';

// Health check configuration
$config = [
    'checks' => [
        'database' => true,
        'redis' => true,
        'disk_space' => true,
        'memory' => true,
        'cpu' => true,
        'file_permissions' => true
    ],
    'thresholds' => [
        'disk_space_warning' => 80, // 80%
        'disk_space_critical' => 90, // 90%
        'memory_warning' => 80, // 80%
        'memory_critical' => 90, // 90%
        'cpu_warning' => 80, // 80%
        'cpu_critical' => 90 // 90%
    ]
];

// Initialize health monitor
$healthMonitor = new SystemHealthMonitor($config);

// Run health checks
$results = $healthMonitor->runAllChecks();

// Output results
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);

/**
 * System Health Monitor Class
 */
class SystemHealthMonitor {
    private $config;
    private $results;
    
    public function __construct($config) {
        $this->config = $config;
        $this->results = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => []
        ];
    }
    
    public function runAllChecks() {
        foreach ($this->config['checks'] as $check => $enabled) {
            if ($enabled) {
                $this->results['checks'][$check] = $this->runCheck($check);
            }
        }
        
        // Determine overall status
        $this->determineOverallStatus();
        
        return $this->results;
    }
    
    private function runCheck($checkName) {
        switch ($checkName) {
            case 'database':
                return $this->checkDatabase();
            case 'redis':
                return $this->checkRedis();
            case 'disk_space':
                return $this->checkDiskSpace();
            case 'memory':
                return $this->checkMemory();
            case 'cpu':
                return $this->checkCPU();
            case 'file_permissions':
                return $this->checkFilePermissions();
            default:
                return [
                    'status' => 'unknown',
                    'message' => 'Unknown check type'
                ];
        }
    }
    
    private function checkDatabase() {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4", 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Test basic query
            $stmt = $pdo->query("SELECT 1");
            $result = $stmt->fetch();
            
            if ($result) {
                // Check connection count
                $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
                $threads = $stmt->fetch();
                
                // Check slow queries
                $stmt = $pdo->query("SHOW STATUS LIKE 'Slow_queries'");
                $slowQueries = $stmt->fetch();
                
                return [
                    'status' => 'healthy',
                    'message' => 'Database connection successful',
                    'details' => [
                        'connected_threads' => $threads['Value'] ?? 'N/A',
                        'slow_queries' => $slowQueries['Value'] ?? 'N/A'
                    ]
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Database query failed'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkRedis() {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            
            // Test basic operations
            $redis->set('health_check', 'test');
            $value = $redis->get('health_check');
            $redis->del('health_check');
            
            if ($value === 'test') {
                // Get Redis info
                $info = $redis->info();
                
                return [
                    'status' => 'healthy',
                    'message' => 'Redis connection successful',
                    'details' => [
                        'version' => $info['redis_version'] ?? 'N/A',
                        'used_memory' => $info['used_memory_human'] ?? 'N/A',
                        'connected_clients' => $info['connected_clients'] ?? 'N/A'
                    ]
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Redis operations failed'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Redis connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkDiskSpace() {
        try {
            $totalSpace = disk_total_space('.');
            $freeSpace = disk_free_space('.');
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercentage = ($usedSpace / $totalSpace) * 100;
            
            $status = 'healthy';
            if ($usagePercentage >= $this->config['thresholds']['disk_space_critical']) {
                $status = 'critical';
            } elseif ($usagePercentage >= $this->config['thresholds']['disk_space_warning']) {
                $status = 'warning';
            }
            
            return [
                'status' => $status,
                'message' => 'Disk space check completed',
                'details' => [
                    'total_space' => $this->formatBytes($totalSpace),
                    'free_space' => $this->formatBytes($freeSpace),
                    'used_space' => $this->formatBytes($usedSpace),
                    'usage_percentage' => round($usagePercentage, 2)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Disk space check failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkMemory() {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            $memoryLimitBytes = $this->returnBytes($memoryLimit);
            $usagePercentage = ($memoryUsage / $memoryLimitBytes) * 100;
            
            $status = 'healthy';
            if ($usagePercentage >= $this->config['thresholds']['memory_critical']) {
                $status = 'critical';
            } elseif ($usagePercentage >= $this->config['thresholds']['memory_warning']) {
                $status = 'warning';
            }
            
            return [
                'status' => $status,
                'message' => 'Memory usage check completed',
                'details' => [
                    'current_usage' => $this->formatBytes($memoryUsage),
                    'memory_limit' => $memoryLimit,
                    'usage_percentage' => round($usagePercentage, 2),
                    'peak_usage' => $this->formatBytes(memory_get_peak_usage(true))
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Memory check failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkCPU() {
        try {
            // Get CPU usage using system commands
            $loadAvg = sys_getloadavg();
            $cpuCount = 1;
            
            // Try to get CPU count
            if (function_exists('sys_getloadavg')) {
                $cpuCount = $this->getCPUCount();
            }
            
            $cpuUsage = ($loadAvg[0] / $cpuCount) * 100;
            
            $status = 'healthy';
            if ($cpuUsage >= $this->config['thresholds']['cpu_critical']) {
                $status = 'critical';
            } elseif ($cpuUsage >= $this->config['thresholds']['cpu_warning']) {
                $status = 'warning';
            }
            
            return [
                'status' => $status,
                'message' => 'CPU usage check completed',
                'details' => [
                    'load_average' => $loadAvg,
                    'cpu_count' => $cpuCount,
                    'cpu_usage_percentage' => round($cpuUsage, 2)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'CPU check failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkFilePermissions() {
        try {
            $criticalPaths = [
                'uploads' => 'uploads/',
                'config' => 'config/',
                'includes' => 'includes/',
                'logs' => 'logs/'
            ];
            
            $results = [];
            $overallStatus = 'healthy';
            
            foreach ($criticalPaths as $name => $path) {
                if (is_dir($path)) {
                    $permissions = substr(sprintf('%o', fileperms($path)), -4);
                    $writable = is_writable($path);
                    
                    $status = 'healthy';
                    if (!$writable) {
                        $status = 'warning';
                        $overallStatus = 'warning';
                    }
                    
                    $results[$name] = [
                        'path' => $path,
                        'permissions' => $permissions,
                        'writable' => $writable,
                        'status' => $status
                    ];
                } else {
                    $results[$name] = [
                        'path' => $path,
                        'status' => 'error',
                        'message' => 'Directory does not exist'
                    ];
                    $overallStatus = 'error';
                }
            }
            
            return [
                'status' => $overallStatus,
                'message' => 'File permissions check completed',
                'details' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'File permissions check failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function determineOverallStatus() {
        $statuses = array_column($this->results['checks'], 'status');
        
        if (in_array('critical', $statuses)) {
            $this->results['status'] = 'critical';
        } elseif (in_array('error', $statuses)) {
            $this->results['status'] = 'error';
        } elseif (in_array('warning', $statuses)) {
            $this->results['status'] = 'warning';
        } else {
            $this->results['status'] = 'healthy';
        }
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    private function returnBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    private function getCPUCount() {
        if (PHP_OS_FAMILY === 'Windows') {
            return (int) shell_exec('echo %NUMBER_OF_PROCESSORS%');
        } else {
            return (int) shell_exec('nproc');
        }
    }
}

// Additional health check endpoints
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'detailed':
            // Return detailed health information
            break;
            
        case 'metrics':
            // Return metrics in Prometheus format
            header('Content-Type: text/plain');
            echo "# Digital Salon Health Metrics\n";
            echo "system_health_status{status=\"" . $results['status'] . "\"} 1\n";
            echo "system_health_timestamp " . time() . "\n";
            break;
            
        case 'ping':
            // Simple ping endpoint
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'timestamp' => date('Y-m-d H:i:s')]);
            break;
    }
}
?>
