<?php
require_once __DIR__ . '/path_utils.php';
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die(json_encode(['success' => false, 'message' => '未登录']));
}

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置允许的文件类型
$allowedTypes = ['audio/mpeg', 'audio/wav', 'audio/ogg'];
$uploadDir = dirname(__DIR__) . '/assets/music/';

// 记录调试信息
$debug = [];

// 确保上传目录存在
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        die(json_encode([
            'success' => false, 
            'message' => '无法创建上传目录',
            'debug' => error_get_last()
        ]));
    }
}

// 检查是否有文件上传
if (!isset($_FILES['musicFile'])) {
    die(json_encode([
        'success' => false, 
        'message' => '没有接收到文件',
        'post' => $_POST,
        'files' => $_FILES
    ]));
}

$file = $_FILES['musicFile'];
$debug['file_info'] = $file;

// 检查上传错误
if ($file['error'] !== UPLOAD_ERR_OK) {
    $message = match($file['error']) {
        UPLOAD_ERR_INI_SIZE => '文件超过了php.ini中upload_max_filesize的限制',
        UPLOAD_ERR_FORM_SIZE => '文件超过了表单中MAX_FILE_SIZE的限制',
        UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
        UPLOAD_ERR_NO_FILE => '没有文件被上传',
        UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        default => '未知上传错误'
    };
    die(json_encode([
        'success' => false, 
        'message' => $message,
        'debug' => $debug
    ]));
}

// 验证文件类型
if (!in_array($file['type'], $allowedTypes)) {
    die(json_encode([
        'success' => false, 
        'message' => '不支持的文件类型: ' . $file['type'],
        'debug' => $debug
    ]));
}

// 生成安全的文件名
$filename = date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $file['name']);
$targetPath = $uploadDir . $filename;

// 检查目录是否可写
if (!is_writable($uploadDir)) {
    die(json_encode([
        'success' => false, 
        'message' => '上传目录没有写入权限',
        'debug' => [
            'uploadDir' => $uploadDir,
            'permissions' => substr(sprintf('%o', fileperms($uploadDir)), -4)
        ]
    ]));
}

// 移动上传的文件
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // 设置文件权限
    chmod($targetPath, 0644);
    
    // 返回相对路径
    $relativePath = normalizeMusicPath('/assets/music/' . $filename);
    echo json_encode([
        'success' => true, 
        'filePath' => $relativePath,
        'debug' => $debug
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => '文件上传失败',
        'debug' => [
            'error' => error_get_last(),
            'target' => $targetPath,
            'original' => $debug
        ]
    ]);
} 