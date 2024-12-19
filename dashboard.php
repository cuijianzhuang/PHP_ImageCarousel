<?php
require_once 'settings.php';

// 检查用户登录状态
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// 获取文件统计信息
function getFileStats() {
    $stats = [
        'total_files' => 0,
        'total_size' => 0,
        'file_types' => []
    ];
    
    // 设置要扫描的目录
    $directories = [
        __DIR__ . '/assets/',
        __DIR__ . '/assets/showimg/'
    ];
    
    // 允许的文件类型
    $allowedExtensions = [
        // 图片格式
        'jpeg','jpg','png','gif','webp','bmp','tiff','tif','heic','heif',
        // 视频格式
        'mp4','avi','mov','wmv','flv','mkv','webm','ogg','m4v','mpeg','mpg','3gp'
    ];
    
    // 扫描所有指定目录
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            continue;
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            // 跳过目录和特殊文件
            if ($file->isDir() || $file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }
            
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            
            // 只统计允许的文件类型
            if (!in_array($ext, $allowedExtensions)) {
                continue;
            }
            
            $fileSize = $file->getSize();
            
            // 如果是第一次遇到这个文件类型
            if (!isset($stats['file_types'][$ext])) {
                $stats['file_types'][$ext] = [
                    'count' => 0,
                    'size' => 0
                ];
            }
            
            // 更新统计信息
            $stats['file_types'][$ext]['count']++;
            $stats['file_types'][$ext]['size'] += $fileSize;
            $stats['total_files']++;
            $stats['total_size'] += $fileSize;
        }
    }
    
    return $stats;
}

$fileStats = getFileStats();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件仪表盘</title>
    <link href="./favicon.ico" type="image/x-icon" rel="icon">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f0f0f0;
        }
        
        header {
            background: #333;
            color: #fff;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header a {
            color: #fff;
            text-decoration: none;
            margin-left: 15px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        header a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .stats-overview {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
        }
        
        .stat-card p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .file-types {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .file-types h2 {
            margin: 0 0 20px 0;
            color: #333;
        }
        
        .file-types table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .file-types th,
        .file-types td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .file-types th {
            background: #f5f5f5;
            font-weight: bold;
            color: #666;
        }
        
        .file-types tr:hover {
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <header>
        <div>文件仪表盘</div>
        <div>
            <a href="index.php">返回首页</a>
            <a href="management.php">文件管理</a>
            <a href="settings.php">系统设置</a>
        </div>
    </header>

    <div class="dashboard-container">
        <h1>文件仪表盘</h1>
        
        <div class="stats-overview">
            <div class="stat-card">
                <h3>总文件数</h3>
                <p><?php echo $fileStats['total_files']; ?></p>
            </div>
            <div class="stat-card">
                <h3>总存储空间</h3>
                <p><?php echo number_format($fileStats['total_size'] / 1024 / 1024, 2); ?> MB</p>
            </div>
        </div>

        <div class="file-types">
            <h2>文件类型统计</h2>
            <table>
                <tr>
                    <th>文件类型</th>
                    <th>数量</th>
                    <th>总大小</th>
                </tr>
                <?php foreach ($fileStats['file_types'] as $type => $data): ?>
                <tr>
                    <td><?php echo htmlspecialchars($type); ?></td>
                    <td><?php echo $data['count']; ?></td>
                    <td><?php echo number_format($data['size'] / 1024 / 1024, 2); ?> MB</td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html> 