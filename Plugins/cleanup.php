<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '未登录或会话已过期']);
    exit;
}

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // 读取配置文件
    $configFile = __DIR__ . '/../config.json';
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    if (!is_array($config)) {
        throw new Exception('配置文件读取失败');
    }

    // 检查 FileManager 类文件是否存在
    $fileManagerPath = __DIR__ . '/../classes/FileManager.php';
    if (!file_exists($fileManagerPath)) {
        throw new Exception('FileManager 类文件不存在');
    }

    // 引入 FileManager 类
    require_once $fileManagerPath;

    // 检查类是否已定义
    if (!class_exists('FileManager')) {
        throw new Exception('FileManager 类未正确加载');
    }

    // 初始化文件管理器
    $fileManager = new FileManager(__DIR__ . '/../assets/');

    // 执行清理
    $deletedFiles = $fileManager->cleanupUnusedFiles($config);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => '清理完成',
        'count' => count($deletedFiles),
        'files' => $deletedFiles
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Cleanup error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}

exit;
?> 