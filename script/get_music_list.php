<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die(json_encode(['success' => false, 'message' => '未登录']));
}

$musicDir = dirname(__DIR__) . '/assets/music/';
$allowedExtensions = ['mp3', 'wav', 'ogg'];
$musicFiles = [];

if (is_dir($musicDir)) {
    $files = scandir($musicDir);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExtensions)) {
            // 规范化路径格式
            $path = '/assets/music/' . $file;
            $path = str_replace('\\', '/', $path);
            $path = preg_replace('#/+#', '/', $path);
            
            $musicFiles[] = [
                'name' => $file,
                'path' => $path
            ];
        }
    }
}

echo json_encode(['success' => true, 'files' => $musicFiles]); 