<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die(json_encode(['success' => false, 'message' => '未登录']));
}

if (!isset($_POST['file']) || empty($_POST['file'])) {
    die(json_encode(['success' => false, 'message' => '未指定文件']));
}

// 获取文件路径并进行安全检查
$filePath = $_POST['file'];
$filePath = str_replace('\\', '/', $filePath);
$filePath = preg_replace('#/+#', '/', $filePath);

// 确保文件路径在音乐目录下
if (strpos($filePath, '/assets/music/') !== 0) {
    die(json_encode(['success' => false, 'message' => '无效的文件路径']));
}

// 转换为服务器上的实际路径
$realPath = dirname(__DIR__) . $filePath;

// 检查文件是否存在
if (!file_exists($realPath)) {
    die(json_encode(['success' => false, 'message' => '文件不存在']));
}

// 尝试删除文件
if (unlink($realPath)) {
    echo json_encode(['success' => true, 'message' => '文件已删除']);
} else {
    echo json_encode(['success' => false, 'message' => '删除失败，请检查文件权限']);
} 