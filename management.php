<?php
header('Content-Type: text/html; charset=utf-8');

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];

// 默认值
if (!isset($config['autoplayInterval'])) $config['autoplayInterval'] = 3000;
if (!isset($config['enabledFiles'])) $config['enabledFiles'] = [];
if (!isset($config['viewMode'])) $config['viewMode'] = 'list';
if (!isset($config['perPage'])) $config['perPage'] = 10;

$directory = __DIR__ . '/assets/';
$allowedExtensions = [
    // Image formats
    'jpeg','jpg','png','gif','webp','bmp','tiff','tif','heic','heif',

    // Video formats
    'mp4','avi','mov','wmv','flv','mkv','webm','ogg','m4v','mpeg','mpg', '3gp'
];
$message = null;

// 函数：确定正确的文件路径
function getFilePath($fileName, $directory) {
    $showimgPath = $directory . 'showimg/' . $fileName;
    if (file_exists($showimgPath)) {
        return $showimgPath;
    } else {
        return $directory . $fileName;
    }
}

// 添加配置同步函数
function syncConfig() {
    global $config, $configFile;

    // 获取文件锁
    $lockFile = __DIR__ . '/config.lock';
    $lockHandle = fopen($lockFile, 'w+');

    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        return false;
    }

    try {
        // 重新读取配置文件，以防在此期间有其他更改
        $currentConfig = json_decode(file_get_contents($configFile), true);
        if (!is_array($currentConfig)) {
            $currentConfig = [];
        }

        // 扫描实际文件
        $realFiles = [];
        $directories = [
            __DIR__ . '/assets/',
            __DIR__ . '/assets/showimg/'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if ($file->isDir() || $file->getFilename() === '.' || $file->getFilename() === '..') continue;

                $filename = $file->getFilename();
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                // 只处理允许的文件类型
                if (!in_array($ext, $GLOBALS['allowedExtensions'])) continue;

                // 检查文件是否在 showimg 目录中
                $isEnabled = strpos($file->getPathname(), '/assets/showimg/') !== false;
                $realFiles[$filename] = $isEnabled;
            }
        }

        // 更新配置
        $currentConfig['enabledFiles'] = array_merge(
            $currentConfig['enabledFiles'] ?? [],
            $realFiles
        );

        // 移除不存在的文件
        foreach ($currentConfig['enabledFiles'] as $filename => $enabled) {
            if (!isset($realFiles[$filename])) {
                unset($currentConfig['enabledFiles'][$filename]);
            }
        }

        // 保存更新后的配置
        $saveSuccess = file_put_contents($configFile,
            json_encode($currentConfig,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            )
        );

        if ($saveSuccess) {
            $config = $currentConfig; // 更新全局配置
            return true;
        }

    } catch (Exception $e) {
        error_log("Config sync error: " . $e->getMessage());
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }

    return false;
}

// 上传文件处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['uploadFile']) && !isset($_POST['saveConfigSettings']) && !isset($_POST['saveEnabledFiles'])) {
    $showimgDirectory = $directory . 'showimg/';

    // 确保showimg目录存在
    if (!is_dir($showimgDirectory)) {
        mkdir($showimgDirectory, 0777, true);
    }

    // 处理多文件上传
    $files = [];
    $fileCount = is_array($_FILES['uploadFile']['name']) ? count($_FILES['uploadFile']['name']) : 1;

    for ($i = 0; $i < $fileCount; $i++) {
        $uploadFileName = is_array($_FILES['uploadFile']['name']) ? $_FILES['uploadFile']['name'][$i] : $_FILES['uploadFile']['name'];
        $tmpName = is_array($_FILES['uploadFile']['tmp_name']) ? $_FILES['uploadFile']['tmp_name'][$i] : $_FILES['uploadFile']['tmp_name'];

        if (empty($uploadFileName) || empty($tmpName)) continue;

        $targetFilePath = $showimgDirectory . basename($uploadFileName);
        $ext = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        if (in_array($ext, $allowedExtensions)) {
            if (move_uploaded_file($tmpName, $targetFilePath)) {
                chmod($targetFilePath, 0644);
                $config['enabledFiles'][basename($uploadFileName)] = true;

                // 同步配置
                if (!syncConfig()) {
                    error_log("Failed to sync config after file upload: " . basename($uploadFileName));
                }

                $files[] = basename($uploadFileName);
            }
        }
    }

    // 保存配置文件
    if (!empty($files)) {
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = count($files) > 1 ?
            "成功上传 " . count($files) . " 个文件" :
            "文件 '" . $files[0] . "' 上传成功";
    } else {
        $message = "没有文件被上传或文件类型不支持";
    }

    // 如果是Ajax请求，返回JSON响应
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['message' => $message]);
        exit;
    }
}

// 处理单个文件删除
if (isset($_GET['delete'])) {
    $file_to_delete = basename($_GET['delete']);
    $file_path = getFilePath($file_to_delete, $directory);
    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            unset($config['enabledFiles'][$file_to_delete]);

            // 同步配置
            if (!syncConfig()) {
                error_log("Failed to sync config after file deletion: " . $file_to_delete);
            }

            $message = "文件 '$file_to_delete' 已删除。";
        } else {
            $message = "删除文件失败。";
        }
    } else {
        $message = "文件不存在或已被删除。";
    }
}

// 处理批量删除请求
if (isset($_POST['batchDelete']) && isset($_POST['deleteFiles'])) {
    $filesDeleted = 0;
    foreach ($_POST['deleteFiles'] as $fileToDelete) {
        $filePath = getFilePath($fileToDelete, $directory);
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                unset($config['enabledFiles'][$fileToDelete]);
                $filesDeleted++;
            }
        }
    }

    // 同步配置
    if (!syncConfig()) {
        error_log("Failed to sync config after batch deletion");
    }

    $message = "已删除 $filesDeleted 个文件。";
}

// 更新配置（文件启用状态）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveEnabledFiles'])) {
    $displayFiles = isset($_POST['displayFiles']) ? json_decode($_POST['displayFiles'], true) : [];
    $enabledFilesPost = $_POST['enabled'] ?? [];

    $lockFile = __DIR__ . '/config.lock';
    $lockHandle = fopen($lockFile, 'w+');

    if (flock($lockHandle, LOCK_EX)) {
        try {
            // 确保showimg目录存在
            $showimgDir = $directory . 'showimg/';
            if (!is_dir($showimgDir)) {
                mkdir($showimgDir, 0777, true);
            }

            $config = json_decode(file_get_contents($configFile), true);
            if (!is_array($config)) $config = [];

            foreach ($displayFiles as $file) {
                $currentEnabled = isset($config['enabledFiles'][$file]) ? $config['enabledFiles'][$file] : true;
                $newEnabled = in_array($file, $enabledFilesPost);

                if ($currentEnabled !== $newEnabled) {
                    $sourcePath = $currentEnabled ? $showimgDir . $file : $directory . $file;
                    $targetPath = $newEnabled ? $showimgDir . $file : $directory . $file;

                    if (file_exists($sourcePath)) {
                        rename($sourcePath, $targetPath);
                    }

                    $config['enabledFiles'][$file] = $newEnabled;
                }
            }

            // 保存时确保使用 UTF-8 编码
            $saveSuccess = file_put_contents($configFile,
                    json_encode($config,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    )
                ) !== false;

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => $saveSuccess,
                    'message' => $saveSuccess ? '保存成功' : '保存失败'
                ], JSON_UNESCAPED_UNICODE);
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                exit;
            }
        } catch (Exception $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => '保存配置失败'
                ], JSON_UNESCAPED_UNICODE);
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                exit;
            }
        }

        flock($lockHandle, LOCK_UN);
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => '系统繁忙，请稍后重试'
            ], JSON_UNESCAPED_UNICODE);
            fclose($lockHandle);
            exit;
        }
    }
    fclose($lockHandle);
}

// 搜索
$search = $_GET['search'] ?? '';

// 获取文件列表
$files = [];
if (is_dir($directory)) {
    // 扫描主目录
    $mainFiles = array_diff(scandir($directory), ['.', '..', 'showimg']);
    foreach ($mainFiles as $f) {
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExtensions)) {
            if ($search === '' || stripos($f, $search) !== false) {
                $files[] = $f;
            }
        }
    }

    // 扫描showimg目录
    $showimgDir = $directory . 'showimg/';
    if (is_dir($showimgDir)) {
        $showimgFiles = array_diff(scandir($showimgDir), ['.', '..']);
        foreach ($showimgFiles as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExtensions)) {
                if ($search === '' || stripos($f, $search) !== false) {
                    $files[] = $f;
                }
            }
        }
    }
}

// 在获取文件列表后，添加排序功能
// 在 $files = []; 后添加:

$sortBy = $_GET['sort'] ?? 'name'; // 默认按名称排序
$sortOrder = $_GET['order'] ?? 'asc'; // 默认升序

// 修改排序函数
usort($files, function($a, $b) use ($config, $sortBy, $sortOrder, $directory) {
    $enabledA = isset($config['enabledFiles'][$a]) ? $config['enabledFiles'][$a] : true;
    $enabledB = isset($config['enabledFiles'][$b]) ? $config['enabledFiles'][$b] : true;

    // 首先按启用状态排序
    if ($enabledA !== $enabledB) {
        return $enabledA ? -1 : 1;
    }

    // 然后按选择的条件排序
    $fileA = getFilePath($a, $directory);
    $fileB = getFilePath($b, $directory);

    switch($sortBy) {
        case 'size':
            $comp = filesize($fileA) - filesize($fileB);
            break;
        case 'date':
            $comp = filemtime($fileA) - filemtime($fileB);
            break;
        case 'type':
            $comp = pathinfo($a, PATHINFO_EXTENSION) <=> pathinfo($b, PATHINFO_EXTENSION);
            break;
        default: // name
            $comp = strcmp($a, $b);
    }

    return $sortOrder === 'asc' ? $comp : -$comp;
});

// 分页
$perPage = $config['perPage'];
$totalFiles = count($files);
$totalPages = ($totalFiles > 0) ? ceil($totalFiles / $perPage) : 1;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
}
$startIndex = ($currentPage - 1) * $perPage;
$displayFiles = array_slice($files, $startIndex, $perPage);

$viewMode = $config['viewMode'];

// 获取文件统计信息
function getFileStats() {
    global $config;

    $stats = [
        'total_files' => 0,
        'total_size' => 0,
        'enabled_files' => 0,
        'file_types' => []
    ];

    $directories = [
        __DIR__ . '/assets/',
        __DIR__ . '/assets/showimg/'
    ];

    $allowedExtensions = [
        'jpeg','jpg','png','gif','webp','bmp','tiff','tif','heic','heif',
        'mp4','avi','mov','wmv','flv','mkv','webm','ogg','m4v','mpeg','mpg','3gp'
    ];

    // 用于跟踪已处理的文件，避免重复计算
    $processedFiles = [];

    foreach ($directories as $dir) {
        if (!file_exists($dir)) continue;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isDir() || $file->getFilename() === '.' || $file->getFilename() === '..') continue;

            $filename = $file->getFilename();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // 跳过不允许的文件类型
            if (!in_array($ext, $allowedExtensions)) continue;

            // 如果文件已经处理过，跳过
            if (in_array($filename, $processedFiles)) continue;

            $processedFiles[] = $filename;
            $fileSize = $file->getSize();

            // 更新文件类型统计
            if (!isset($stats['file_types'][$ext])) {
                $stats['file_types'][$ext] = [
                    'count' => 0,
                    'size' => 0
                ];
            }

            $stats['file_types'][$ext]['count']++;
            $stats['file_types'][$ext]['size'] += $fileSize;
            $stats['total_files']++;
            $stats['total_size'] += $fileSize;

            // 统计启用的文件
            if (isset($config['enabledFiles'][$filename]) && $config['enabledFiles'][$filename]) {
                $stats['enabled_files']++;
            }
        }
    }

    return $stats;
}

$fileStats = getFileStats();

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<link href="./favicon.ico" type="image/x-icon" rel="icon">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>文件管理</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2196F3;
            --success-color: #4CAF50;
            --danger-color: #ff4444;
            --warning-color: #ff9800;
            --light-bg: #f8f9fa;
            --dark-text: #333;
            --mid-text: #666;
            --light-text: #999;
            --border-color: #e0e0e0;
            --shadow: 0 2px 8px rgba(0,0,0,0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 0;
            color: var(--dark-text);
            line-height: 1.5;
        }

        header {
            background: #262b33;
            color: #fff;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        header a {
            color: #fff;
            text-decoration: none;
            margin-left: 20px;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        header a:hover {
            opacity: 0.8;
        }

        header form, header a {
            display: inline-block;
        }

        .wrapper {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        .upload-area, .config-form, .search-form {
            background: #fff;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .message {
            margin-bottom: 15px;
            padding: 12px 20px;
            background: rgba(76, 175, 80, 0.1);
            border-left: 4px solid var(--success-color);
            border-radius: 4px;
            color: var(--success-color);
        }

        .files-container {
            background: #fff;
            padding: 25px;
            box-shadow: var(--shadow);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .tool-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .tool-btn {
            padding: 10px 15px;
            border-radius: 6px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            background-color: #f5f5f5;
            color: var(--dark-text);
        }

        .tool-btn.primary {
            background: var(--primary-color);
            color: white;
        }

        .tool-btn.primary:hover {
            background: #1976D2;
        }

        .tool-btn.secondary {
            background: #f5f5f5;
            color: var(--dark-text);
        }

        .tool-btn.secondary:hover {
            background: #e0e0e0;
        }

        .tool-btn.danger {
            background: var(--danger-color);
            color: white;
        }

        .tool-btn.danger:hover {
            background: #cc0000;
        }

        .tool-btn.warning {
            background: var(--warning-color);
            color: white;
        }

        .tool-btn.warning:hover {
            background: #f57c00;
        }

        .files-list {
            overflow-x: auto;
            border-radius: 8px;
            background: #fff;
        }

        .files-list table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px; /* 减小字体大小 */
        }

        .files-list th,
        .files-list td {
            padding: 8px 10px; /* 缩小内边距 */
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        .files-list thead {
            position: sticky;
            top: 0;
            background: #f5f7fa;
            z-index: 10;
        }

        .files-list th {
            font-weight: 500;
            color: var(--mid-text);
        }

        .files-list tr:hover {
            background: rgba(0,0,0,0.01);
        }

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .grid-item {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .grid-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .grid-thumbnail {
            height: 150px;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .grid-thumbnail img,
        .grid-thumbnail video {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .file-item {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            position: relative;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            height: 100%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }

        .file-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .file-item .media-container {
            aspect-ratio: 16/9;
            overflow: hidden;
            border-radius: 6px;
            margin-bottom: 15px;
            background: #f5f5f5;
            position: relative;
        }

        .file-item img,
        .file-item video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .file-item .filename {
            font-size: 14px;
            color: var(--dark-text);
            margin: 8px 0 15px;
            word-break: break-all;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .file-item .action-links {
            margin-top: auto;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            flex-wrap: nowrap;
        }

        .file-item .action-links a {
            flex: 1;
            text-align: center;
            font-size: 12px;
        }

        .enable-checkbox {
            transform: scale(1.2);
            margin-right: 5px;
        }

        .pagination {
            text-align: center;
            margin: 30px 0;
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 16px;
            text-decoration: none;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--dark-text);
            transition: var(--transition);
        }

        .pagination a:hover {
            background: var(--light-bg);
            border-color: #ddd;
        }

        .pagination .current {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }

        .preview-btn {
            background: var(--success-color);
            color: #fff;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            transition: var(--transition);
        }

        .preview-btn:hover {
            background: #45a049;
        }

        .delete-link {
            color: #fff;
            background: var(--danger-color);
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            transition: var(--transition);
        }

        .delete-link:hover {
            background: #cc0000;
        }

        .lazy {
            opacity: 0;
            transition: opacity 0.5s;
        }

        .lazy-loaded {
            opacity: 1;
        }

        /* 模态框样式 */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0);
            justify-content: center;
            align-items: center;
            transition: background-color 0.3s ease;
            backdrop-filter: blur(0);
            transition: backdrop-filter 0.3s ease, background-color 0.3s ease;
        }

        .modal.show {
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(3px);
        }

        .modal-content {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            position: relative;
            max-width: 95%;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
            opacity: 0;
            transform: scale(0.9);
            transition: all 0.3s ease;
            overflow: visible;
            margin: 20px;
        }

        .modal.show .modal-content {
            opacity: 1;
            transform: scale(1);
        }

        .modal-content img,
        .modal-content video {
            max-width: 100%;
            max-height: 80vh;
            margin-bottom: 15px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .modal-close {
            position: absolute;
            top: -20px;
            right: -20px;
            background: #333;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            font-size: 24px;
            line-height: 1;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1;
        }

        .modal-close:hover {
            background: #555;
            transform: scale(1.1);
        }

        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 12px;
            padding: 35px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            margin-bottom: 30px;
        }

        .upload-area.dragover {
            border-color: var(--success-color);
            background: rgba(76, 175, 80, 0.05);
        }

        .upload-hint {
            color: var(--mid-text);
            margin: 15px 0;
        }

        .upload-btn {
            background: var(--success-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }

        .upload-btn:hover {
            background: #45a049;
        }

        .file-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .preview-item {
            position: relative;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 5px;
            transition: var(--transition);
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }

        .preview-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        }

        .preview-item img,
        .preview-item video {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 4px;
        }

        .preview-remove {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: var(--transition);
        }

        .preview-remove:hover {
            transform: scale(1.1);
            background: #cc0000;
        }

        .upload-progress {
            margin-top: 10px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 4px;
            background: var(--success-color);
            width: 0;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 12px;
            color: var(--mid-text);
            margin-top: 4px;
        }

        /* 开关按钮样式 */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .slider.round {
            border-radius: 24px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        /* 仪表盘样式 */
        .dashboard-stats {
            margin-bottom: 30px;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .dashboard-stats .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
            text-align: center;
            width: auto;
        }

        .dashboard-stats .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
        }

        .dashboard-stats .stat-card h3 {
            margin: 0 0 15px 0;
            color: var(--mid-text);
            font-size: 16px;
            font-weight: 500;
        }

        .dashboard-stats .stat-card p {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            color: var(--dark-text);
        }

        .stats-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            max-width: 100%;
            max-height: 350px; /* 控制饼图高度 */
            display: flex;
            flex-direction: column;
        }

        .chart-title {
            margin: 0 0 15px 0;
            color: var(--mid-text);
            font-size: 16px;
            font-weight: 500;
            text-align: center;
        }

        /* 为饼图容器设置固定高度 */
        .chart-container canvas {
            max-height: 280px;
            width: 100% !important;
            height: auto !important;
        }

        .dashboard-stats .file-types {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .dashboard-stats .file-types h2 {
            margin: 0 0 20px 0;
            color: var(--dark-text);
            font-size: 20px;
            font-weight: 500;
        }

        .dashboard-stats .file-types table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .dashboard-stats .file-types th,
        .dashboard-stats .file-types td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .dashboard-stats .file-types th {
            background: var(--light-bg);
            font-weight: 500;
            color: var(--mid-text);
        }

        .dashboard-stats .file-types tr:hover {
            background: rgba(0,0,0,0.01);
        }

        .dashboard-stats .file-types td:last-child {
            text-align: right;
        }

        .search-form {
            display: flex;
            flex-direction: column;
        }

        .search-form h3 {
            margin-bottom: 15px;
            font-weight: 500;
        }

        .search-form form {
            display: flex;
            gap: 10px;
        }

        .search-form input[type="text"] {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: var(--transition);
        }

        .search-form input[type="text"]:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.1);
        }

        .search-form button {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .search-form button:hover {
            background: #1976D2;
        }

        .search-form a {
            margin-left: 10px;
            color: var(--mid-text);
            text-decoration: none;
            padding: 10px 15px;
            background: #f5f5f5;
            border-radius: 4px;
            transition: var(--transition);
        }

        .search-form a:hover {
            background: #e0e0e0;
        }

        .selection-info {
            color: var(--mid-text);
            font-weight: 500;
        }

        .selection-info.active {
            color: var(--primary-color);
        }

        /* 导航按钮样式 */
        .preview-nav {
            position: absolute;
            width: 100%;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
            pointer-events: none;
        }

        .nav-btn {
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            transition: var(--transition);
            pointer-events: auto;
        }

        .nav-btn:hover {
            background: rgba(0,0,0,0.7);
            transform: scale(1.1);
        }

        /* 添加清理进度对话框样式 */
        .cleanup-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
            min-width: 350px;
        }

        .cleanup-dialog h3 {
            margin-bottom: 20px;
            color: var(--dark-text);
            font-weight: 500;
        }

        .cleanup-dialog .progress {
            margin: 15px 0;
            height: 6px;
            background: #eee;
            border-radius: 3px;
            overflow: hidden;
        }

        .cleanup-dialog .progress-bar {
            height: 100%;
            background: var(--success-color);
            width: 0;
        }

        #cleanupStatus {
            margin-top: 15px;
            color: var(--mid-text);
        }

        .loading {
            color: var(--mid-text);
            font-size: 16px;
            padding: 20px;
        }

        .error-message {
            color: var(--danger-color);
            font-size: 16px;
            padding: 20px;
        }

        /* 新增样式 */
        .view-switcher {
            display: flex;
            margin-left: 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            overflow: hidden;
        }

        .view-switcher a {
            padding: 8px 15px;
            background: #f5f5f5;
            color: var(--mid-text);
            text-decoration: none;
            transition: var(--transition);
        }

        .view-switcher a:hover {
            background: #e0e0e0;
        }

        .view-switcher a.active {
            background: var(--primary-color);
            color: white;
        }

        .thumbnail-container {
            position: relative;
            width: 100px;
            height: 60px;
            overflow: hidden;
            border-radius: 4px;
            background: #f5f5f5;
        }

        .file-type-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 10px;
        }

        .file-type-badge.video {
            background: rgba(255, 87, 34, 0.7);
        }

        .file-type-badge.image {
            background: rgba(33, 150, 243, 0.7);
        }

        .file-checkbox {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 1;
        }

        .file-checkbox input {
            transform: scale(1.2);
        }

        .file-details {
            margin-bottom: 15px;
        }

        .file-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--mid-text);
            margin-top: 5px;
        }

        .file-stats-summary {
            margin-bottom: 20px;
            padding: 10px 15px;
            background: var(--light-bg);
            border-radius: 4px;
            color: var(--mid-text);
        }

        .empty-state {
            text-align: center;
            padding: 50px 0;
            color: var(--mid-text);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }

        .empty-state p {
            margin-bottom: 15px;
            font-size: 18px;
        }

        .clear-search-btn {
            display: inline-block;
            padding: 8px 15px;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
            text-decoration: none;
            transition: var(--transition);
        }

        .clear-search-btn:hover {
            background: #1976D2;
        }

        .filename-cell {
            max-width: 200px; /* 控制文件名宽度 */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px; /* 更小的字体 */
        }

        .pagination-ellipsis {
            display: inline-block;
            padding: 8px 16px;
            color: var(--mid-text);
        }

        /* 响应式调整 */
        @media (max-width: 768px) {
            .wrapper {
                padding: 0 15px;
                margin: 20px auto;
            }

            .stats-overview,
            .stats-charts {
                grid-template-columns: 1fr;
            }

            .files-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }

            .tool-group {
                flex-direction: column;
                gap: 5px;
            }

            .toolbar {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .selection-info {
                margin-top: 10px;
            }

            .files-list th,
            .files-list td {
                padding: 10px 8px;
            }

            .search-form form {
                flex-direction: column;
            }

            .search-form a {
                margin: 10px 0 0 0;
                text-align: center;
            }

            .stats-charts {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                max-height: 300px;
            }
            
            .chart-container canvas {
                max-height: 230px;
            }
        }

        @media (max-width: 991px) {
            /* 平板设备优化 */
            .filename-cell {
                max-width: 150px;
            }
            
            .files-list {
                font-size: 12px;
            }
            
            .action-links {
                flex-direction: column;
                gap: 3px;
            }
            
            .action-links a {
                font-size: 11px;
                padding: 3px 6px;
            }
        }

        /* 文件列表中元数据显示优化 */
        .file-meta {
            font-size: 11px;
            color: var(--mid-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-meta i {
            font-size: 10px;
            opacity: 0.7;
        }

        .file-size {
            white-space: nowrap;
            font-weight: 500;
        }

        /* 优化表格内操作按钮 */
        .action-links {
            white-space: nowrap;
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
            justify-content: center;
        }

        .action-links a {
            font-size: 12px;
            padding: 4px 8px;
            flex: 1;
            text-align: center;
            white-space: nowrap;
        }

        /* 改进表格中的按钮对齐 */
        .files-list .action-links {
            justify-content: flex-start;
        }

        /* 开关按钮居中对齐 */
        td .switch {
            display: flex;
            justify-content: center;
        }

        /* 移动设备优化 - 额外添加 */
        @media (max-width: 768px) {
            .files-list .action-links {
                flex-direction: row;
                align-items: center;
                justify-content: center;
            }
            
            .preview-btn, .delete-link {
                flex: 1;
                text-align: center;
                min-width: 70px;
            }
            
            td .switch {
                transform: scale(0.9);
            }
        }

        /* 列表视图中的操作按钮优化 */
        .files-list .action-links {
            display: flex;
            flex-direction: row;
            gap: 10px;
            justify-content: center;
        }
        
        .files-list .action-links a {
            width: calc(50% - 5px);
            text-align: center;
            white-space: nowrap;
            font-size: 13px;
            padding: 8px 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .files-list .action-links a i {
            margin-right: 4px;
        }
        
        /* 确保网格视图中的按钮对齐 */
        .file-item .action-links {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 0 auto;
            width: 100%;
        }

        /* 操作按钮居中和对齐样式 */
        .action-links {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: nowrap;
        }
        
        .preview-btn, .delete-link {
            flex: 1;
            max-width: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .preview-btn i, .delete-link i {
            margin-right: 5px;
        }
        
        td .switch {
            display: flex;
            justify-content: center;
        }
        
        /* 确保表格中的操作列居中 */
        .files-list td:last-child {
            text-align: center;
        }

        /* 优化操作按钮的样式和对齐 */
        .operations-cell {
            text-align: center;
            vertical-align: middle;
        }
        
        .operations-cell .action-links {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 0 auto;
            width: 100%;
        }
        
        .preview-btn, .delete-link {
            flex: 1;
            max-width: 90px;
            min-width: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .preview-btn i, .delete-link i {
            margin-right: 5px;
        }
        
        @media (max-width: 768px) {
            .operations-cell .action-links {
                flex-direction: column;
                gap: 5px;
            }
            
            .preview-btn, .delete-link {
                max-width: 100%;
                width: 100%;
            }
        }

        /* 统一按钮样式 */
        .preview-btn,
        .delete-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            text-align: center;
            flex: 1;
        }
        
        .preview-btn {
            background: var(--success-color);
            color: #fff;
        }
        
        .preview-btn:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .delete-link {
            background: var(--danger-color);
            color: #fff;
        }
        
        .delete-link:hover {
            background: #cc0000;
            transform: translateY(-2px);
        }
        
        .preview-btn i, 
        .delete-link i {
            margin-right: 5px;
        }
        
        /* 优化列表视图中的操作按钮 */
        .operations-cell {
            text-align: center;
            vertical-align: middle;
            padding: 10px 5px;
        }
        
        .operations-cell .action-links {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 0 auto;
            width: 100%;
            max-width: 200px;
        }
        
        /* 确保网格视图中的按钮样式一致 */
        .file-item .action-links {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 10px auto 0;
            width: 100%;
        }
        
        .file-item .action-links a {
            flex: 1;
            min-height: 36px;
        }
        
        /* 移动端优化 */
        @media (max-width: 768px) {
            .operations-cell .action-links {
                flex-direction: row; /* 保持水平排列 */
                gap: 5px;
            }
            
            .preview-btn, 
            .delete-link {
                font-size: 13px;
                padding: 6px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .operations-cell .action-links {
                flex-direction: column; /* 在特小屏幕上垂直排列 */
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<header>
    <div><i class="fas fa-file-alt"></i> 文件管理系统</div>
    <div>
        <a href="settings.php"><i class="fas fa-cog"></i> 系统设置</a>
        <a href="index.php"><i class="fas fa-home"></i> 返回首页</a>
        <form action="logout.php" method="post" style="display:inline;">
            <button type="submit" style="background:#cc3333; border:none; color:#fff; padding:8px 15px; cursor:pointer; border-radius:4px; font-size:14px;">
                <i class="fas fa-sign-out-alt"></i> 登出
            </button>
        </form>
    </div>
</header>

<div class="wrapper">
    <!-- 仪表盘统计区域 -->
    <div class="dashboard-stats">
        <div class="stats-overview">
            <div class="stat-card">
                <h3><i class="fas fa-file"></i> 总文件数</h3>
                <p><?php echo $fileStats['total_files']; ?> 个文件</p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-photo-video"></i> 展示文件数</h3>
                <p><?php echo $fileStats['enabled_files']; ?> 个文件</p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-database"></i> 总存储空间</h3>
                <p><?php echo number_format($fileStats['total_size'] / 1024 / 1024, 2); ?> MB</p>
            </div>
        </div>

        <div class="stats-charts">
            <div class="chart-container">
                <h3 class="chart-title"><i class="fas fa-chart-pie"></i> 文件类型分布</h3>
                <canvas id="fileTypesChart"></canvas>
            </div>
            <div class="chart-container">
                <h3 class="chart-title"><i class="fas fa-hdd"></i> 存储空间占用</h3>
                <canvas id="storageChart"></canvas>
            </div>
        </div>

        <div class="file-types">
            <h2><i class="fas fa-list"></i> 文件类型统计</h2>
            <table>
                <thead>
                <tr>
                    <th>文件类型</th>
                    <th>数量</th>
                    <th>总大小</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($fileStats['file_types'] as $type => $data): ?>
                    <tr>
                        <td><i class="fas <?php echo in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'fa-image' : 'fa-video'; ?>"></i> .<?php echo htmlspecialchars($type); ?></td>
                        <td><?php echo $data['count']; ?> 个文件</td>
                        <td><?php echo number_format($data['size'] / 1024 / 1024, 2); ?> MB</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- 上传区域 -->
    <div class="upload-area" id="dropZone">
        <h3><i class="fas fa-cloud-upload-alt"></i> 上传文件（图片或视频）</h3>
        <p class="upload-hint">点击选择或拖拽文件到此处</p>
        <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
            <input type="file" name="uploadFile[]" id="fileInput" multiple accept="image/*,video/*" style="display: none;">
            <button type="button" id="selectFiles" class="upload-btn">
                <i class="fas fa-file-upload"></i> 选择文件
            </button>
        </form>
        <!-- 文件预览区域 -->
        <div id="filePreview" class="file-preview"></div>
        <!-- 进度条容器 -->
        <div id="uploadProgress"></div>
    </div>

    <div class="search-form">
        <h3><i class="fas fa-search"></i> 搜索文件</h3>
        <form action="" method="get">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="输入文件名关键字">
            <button type="submit"><i class="fas fa-search"></i> 搜索</button>
            <?php if ($search): ?>
                <a href="?"><i class="fas fa-times"></i> 清除搜索</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="files-container">
        <div class="toolbar">
            <div class="tool-group">
                <button type="button" id="selectAll" class="tool-btn primary">
                    <i class="fas fa-check-square"></i> 全选
                </button>
                <button type="button" id="invertSelection" class="tool-btn secondary">
                    <i class="fas fa-exchange-alt"></i> 反选
                </button>
                <button type="button" id="batchDeleteBtn" class="tool-btn danger">
                    <i class="fas fa-trash-alt"></i> 批量删除
                </button>
                <button type="button" id="cleanupFiles" class="tool-btn warning">
                    <i class="fas fa-broom"></i> 清理未用文件
                </button>
                <div class="view-switcher">
                    <a href="?view=list" class="<?= $viewMode === 'list' ? 'active' : '' ?>">
                        <i class="fas fa-list"></i>
                    </a>
                    <a href="?view=grid" class="<?= $viewMode === 'grid' ? 'active' : '' ?>">
                        <i class="fas fa-th"></i>
                    </a>
                </div>
            </div>
            <div class="selection-info">
                <span id="selectionCount"></span>
            </div>
        </div>
        <?php if (empty($displayFiles)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>没有匹配的媒体文件</p>
                <?php if ($search): ?>
                    <a href="?" class="clear-search-btn">清除搜索</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="file-stats-summary">
                <i class="fas fa-info-circle"></i> 总文件数量: <strong><?= $fileStats['total_files'] ?></strong> 个文件 |
                展示文件数量: <strong><?= $fileStats['enabled_files'] ?></strong> 个文件
            </div>
            <!-- 文件启用状态表单 -->
            <form method="post" action="" id="enabledForm">
                <input type="hidden" name="saveEnabledFiles" value="1">
                <input type="hidden" name="current_page" value="<?= $currentPage ?>">
                <input type="hidden" name="current_search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="displayFiles" value="<?= htmlspecialchars(json_encode($displayFiles)) ?>">
                <!-- 添加 batchDelete 隐藏字段 -->
                <input type="hidden" name="batchDelete" value="0" id="batchDeleteField">
                <?php if ($viewMode === 'list'): ?>
                    <div class="files-list">
                        <table>
                            <tr>
                                <th width="40"><input type="checkbox" id="selectAllInTable" class="select-all-checkbox"></th>
                                <th width="120">缩略图</th>
                                <th>
                                    <a href="?sort=name&order=<?= $sortBy === 'name' && $sortOrder === 'asc' ? 'desc' : 'asc' ?>" class="sort-link">
                                        文件名 <?= $sortBy === 'name' ? ($sortOrder === 'asc' ? '↑' : '↓') : '' ?>
                                    </a>
                                </th>
                                <th width="80">
                                    <a href="?sort=type&order=<?= $sortBy === 'type' && $sortOrder === 'asc' ? 'desc' : 'asc' ?>" class="sort-link">
                                        类型 <?= $sortBy === 'type' ? ($sortOrder === 'asc' ? '↑' : '↓') : '' ?>
                                    </a>
                                </th>
                                <th width="100">
                                    <a href="?sort=size&order=<?= $sortBy === 'size' && $sortOrder === 'asc' ? 'desc' : 'asc' ?>" class="sort-link">
                                        大小 <?= $sortBy === 'size' ? ($sortOrder === 'asc' ? '↑' : '↓') : '' ?>
                                    </a>
                                </th>
                                <th width="140">
                                    <a href="?sort=date&order=<?= $sortBy === 'date' && $sortOrder === 'asc' ? 'desc' : 'asc' ?>" class="sort-link">
                                        修改日期 <?= $sortBy === 'date' ? ($sortOrder === 'asc' ? '↑' : '↓') : '' ?>
                                    </a>
                                </th>
                                <th width="100" style="text-align:center;">轮播展示</th>
                                <th width="200" style="text-align:center;">操作</th>
                            </tr>
                            <?php foreach ($displayFiles as $file):
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $fileUrl = (isset($config['enabledFiles'][$file]) && $config['enabledFiles'][$file])
                                    ? 'assets/showimg/' . $file
                                    : 'assets/' . $file;
                                $isVideo = in_array($ext, ['mp4', 'webm', 'ogg']);
                                $enabled = isset($config['enabledFiles'][$file]) ? $config['enabledFiles'][$file] : true;
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="deleteFiles[]" value="<?= htmlspecialchars($file) ?>">
                                    </td>
                                    <td>
                                        <div class="thumbnail-container">
                                            <?php if ($isVideo): ?>
                                                <video data-src="<?= htmlspecialchars($fileUrl) ?>" preload="none" muted class="lazy" style="max-width:100px;max-height:60px;"></video>
                                                <span class="file-type-badge video"><i class="fas fa-video"></i></span>
                                            <?php else: ?>
                                                <img data-src="<?= htmlspecialchars($fileUrl) ?>" alt="" class="lazy" style="max-width:100px;max-height:60px;">
                                                <span class="file-type-badge image"><i class="fas fa-image"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="filename-cell" title="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></td>
                                    <td><?= htmlspecialchars(strtoupper(pathinfo($file, PATHINFO_EXTENSION))) ?></td>
                                    <td><?= formatFileSize(filesize(getFilePath($file, $directory))) ?></td>
                                    <td><?= date('Y-m-d H:i', filemtime(getFilePath($file, $directory))) ?></td>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" class="enable-checkbox" name="enabled[]"
                                                   value="<?= htmlspecialchars($file) ?>"
                                                <?= $enabled ? 'checked' : '' ?>
                                                   onchange="document.getElementById('enabledForm').submit()">
                                            <span class="slider round"></span>
                                        </label>
                                    </td>
                                    <td class="operations-cell">
                                        <div class="action-links">
                                            <a href="#" class="preview-btn" data-file="<?= htmlspecialchars($fileUrl) ?>" data-type="<?= $isVideo ? 'video' : 'image' ?>">
                                                <i class="fas fa-eye"></i> 预览
                                            </a>
                                            <a href="?<?= $search ? 'search=' . urlencode($search) . '&' : '' ?>delete=<?= urlencode($file) ?>" class="delete-link" onclick="return confirm('确定删除此文件？')">
                                                <i class="fas fa-trash"></i> 删除
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="files-grid">
                        <?php foreach ($displayFiles as $file):
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            $fileUrl = (isset($config['enabledFiles'][$file]) && $config['enabledFiles'][$file])
                                ? 'assets/showimg/' . $file
                                : 'assets/' . $file;
                            $isVideo = in_array($ext, ['mp4', 'webm', 'ogg']);
                            $enabled = isset($config['enabledFiles'][$file]) ? $config['enabledFiles'][$file] : true;
                            ?>
                            <div class="file-item">
                                <div class="file-checkbox">
                                    <input type="checkbox" name="deleteFiles[]" value="<?= htmlspecialchars($file) ?>">
                                </div>

                                <label class="switch" style="position:absolute;top:15px;right:15px;z-index:1;">
                                    <input type="checkbox" class="enable-checkbox" name="enabled[]"
                                           value="<?= htmlspecialchars($file) ?>"
                                        <?= $enabled ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>

                                <div class="media-container">
                                    <?php if ($isVideo): ?>
                                        <video data-src="<?= htmlspecialchars($fileUrl) ?>"
                                               preload="none" muted loop
                                               class="lazy"
                                               onmouseover="this.play()"
                                               onmouseout="this.pause();this.currentTime=0;">
                                        </video>
                                        <span class="file-type-badge video"><i class="fas fa-video"></i></span>
                                    <?php else: ?>
                                        <img data-src="<?= htmlspecialchars($fileUrl) ?>"
                                             alt="" class="lazy">
                                        <span class="file-type-badge image"><i class="fas fa-image"></i></span>
                                    <?php endif; ?>
                                </div>

                                <div class="file-details">
                                    <div class="filename" title="<?= htmlspecialchars($file) ?>">
                                        <?= htmlspecialchars($file) ?>
                                    </div>
                                    <div class="file-meta">
                                        <span class="file-size"><?= formatFileSize(filesize(getFilePath($file, $directory))) ?></span>
                                        <span class="file-date"><?= date('Y-m-d', filemtime(getFilePath($file, $directory))) ?></span>
                                    </div>
                                </div>

                                <div class="operations-cell">
                                    <div class="action-links">
                                        <a href="#" class="preview-btn"
                                           data-file="<?= htmlspecialchars($fileUrl) ?>"
                                           data-type="<?= $isVideo ? 'video' : 'image' ?>">
                                            <i class="fas fa-eye"></i> 预览
                                        </a>
                                        <a href="?<?= $search ? 'search=' . urlencode($search) . '&' : '' ?>delete=<?= urlencode($file) ?>"
                                           class="delete-link"
                                           onclick="return confirm('确定删除此文件？')">
                                            <i class="fas fa-trash"></i> 删除
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </form>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $searchParam = $search ? 'search=' . urlencode($search) . '&' : '';
                    if ($currentPage > 1): ?>
                        <a href="?<?= $searchParam ?>page=1"><i class="fas fa-angle-double-left"></i></a>
                        <a href="?<?= $searchParam ?>page=<?= $currentPage - 1 ?>"><i class="fas fa-angle-left"></i></a>
                    <?php endif; ?>

                    <?php
                    // 限制显示的页码数量
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);

                    if ($startPage > 1) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }

                    for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <?php if ($p == $currentPage): ?>
                            <span class="current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="?<?= $searchParam ?>page=<?= $p ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor;

                    if ($endPage < $totalPages) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?<?= $searchParam ?>page=<?= $currentPage + 1 ?>"><i class="fas fa-angle-right"></i></a>
                        <a href="?<?= $searchParam ?>page=<?= $totalPages ?>"><i class="fas fa-angle-double-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 模态框预览 -->
<div class="modal" id="previewModal">
    <div class="modal-content">
        <button class="modal-close" id="modalClose">×</button>
        <div id="previewContainer"></div>
        <div class="preview-nav">
            <button class="nav-btn prev" onclick="showPreview(currentPreviewIndex - 1)">‹</button>
            <button class="nav-btn next" onclick="showPreview(currentPreviewIndex + 1)">›</button>
        </div>
    </div>
</div>

<!-- 添加清理进度对话框 -->
<div id="cleanupDialog" class="cleanup-dialog">
    <h3><i class="fas fa-broom"></i> 正在清理未使用文件</h3>
    <div class="progress">
        <div class="progress-bar"></div>
    </div>
    <p id="cleanupStatus">准备清理...</p>
</div>

<script>
    // 懒加载
    const lazyElements = document.querySelectorAll('.lazy');
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const src = el.getAttribute('data-src');
                if (src) {
                    if (el.tagName.toLowerCase() === 'video') {
                        el.src = src;
                        el.load();
                    } else {
                        el.src = src;
                    }
                    el.removeAttribute('data-src');
                    el.classList.add('lazy-loaded');
                }
                obs.unobserve(el);
            }
        });
    }, {rootMargin: '50px'});
    lazyElements.forEach(el => observer.observe(el));

    // 预览模态框
    const previewBtns = document.querySelectorAll('.preview-btn');
    const modal = document.getElementById('previewModal');
    const previewContainer = document.getElementById('previewContainer');
    const modalClose = document.getElementById('modalClose');

    previewBtns.forEach((btn, index) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            modal.style.display = 'flex';

            setTimeout(() => {
                modal.classList.add('show');
                showPreview(index);
            }, 10);
        });
    });

    function closeModal() {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            previewContainer.innerHTML = '';
        }, 300); // 等待过渡动画完成
    }

    modalClose.addEventListener('click', closeModal);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // 添加键盘事件支持
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });
</script>
<script>
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault(); // 阻止默认提交

        const fileInput = document.getElementById('fileInput');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');

        // 检查是否选择了文件
        if (!fileInput.files.length) {
            alert('请选择文件');
            return;
        }

        // 创建 FormData
        const formData = new FormData(this);

        // 示进度条
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = '准备上传...';

        // XMLHttpRequest 对象
        const xhr = new XMLHttpRequest();

        // 上传进度事件
        xhr.upload.onprogress = function(event) {
            if (event.lengthComputable) {
                const percentComplete = Math.round((event.loaded / event.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressText.textContent = `上传进度：${percentComplete}%`;
            }
        };

        // 上传完成事件
        xhr.onload = function() {
            if (xhr.status === 200) {
                progressText.textContent = '上传完成！';
                progressBar.style.backgroundColor = '#4CAF50'; // 绿色
                // 可以在这里刷新页面或动态更新文件列表
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                progressText.textContent = '上传失败！';
                progressBar.style.backgroundColor = '#F44336'; // 红色
            }
        };

        // 错误处理
        xhr.onerror = function() {
            progressText.textContent = '上传发生错误！';
            progressBar.style.backgroundColor = '#F44336';
        };

        // 发送请求
        xhr.open('POST', '', true);
        xhr.send(formData);
    });
</script>
<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const selectFiles = document.getElementById('selectFiles');
    const filePreview = document.getElementById('filePreview');
    const uploadProgress = document.getElementById('uploadProgress');

    // 点击选择文件
    selectFiles.addEventListener('click', () => fileInput.click());

    // 处理拖拽事件
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('dragover');
        });
    });

    // 处理文件
    dropZone.addEventListener('drop', e => {
        const files = e.dataTransfer.files;
        handleFiles(files);
    });

    // 处理文件选择
    fileInput.addEventListener('change', e => {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        Array.from(files).forEach(file => {
            if (file.type.startsWith('image/') || file.type.startsWith('video/')) {
                const reader = new FileReader();
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';

                reader.onload = e => {
                    const preview = file.type.startsWith('image/')
                        ? `<img src="${e.target.result}" alt="${file.name}">`
                        : `<video src="${e.target.result}" muted></video>`;

                    previewItem.innerHTML = `
                        ${preview}
                        <button class="preview-remove" onclick="this.parentElement.remove()">×</button>
                        <div class="upload-progress">
                            <div class="progress-bar"></div>
                            <div class="progress-text">准备上传...</div>
                        </div>
                    `;

                    filePreview.appendChild(previewItem);
                    uploadFile(file, previewItem);
                };

                reader.readAsDataURL(file);
            }
        });
    }

    function uploadFile(file, previewItem) {
        const formData = new FormData();
        formData.append('uploadFile[]', file);

        const xhr = new XMLHttpRequest();
        const progressBar = previewItem.querySelector('.progress-bar');
        const progressText = previewItem.querySelector('.progress-text');

        xhr.upload.onprogress = e => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressText.textContent = `上传进度：${percent}%`;
            }
        };

        xhr.onload = () => {
            if (xhr.status === 200) {
                progressText.textContent = '上传完成';
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                progressText.textContent = '上传失败';
                progressBar.style.backgroundColor = '#ff4444';
            }
        };

        xhr.onerror = () => {
            progressText.textContent = '上传错误';
            progressBar.style.backgroundColor = '#ff4444';
        };

        xhr.open('POST', '', true);
        xhr.send(formData);
    }
</script>
<script>
    // 添加防抖函数
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    document.querySelectorAll('.enable-checkbox').forEach(checkbox => {
        const debouncedHandler = debounce(function(e) {
            const form = document.getElementById('enabledForm');
            const formData = new FormData(form);
            const originalState = this.checked;

            // 显示加载指示或禁用复选框
            this.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    // 添加防止缓存的头部
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                }
            })
                .then(response => {
                    // 检查响应状态码
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // 即使返回失败，如果状态码是 200，我们认为操作是成功的
                    // CDN 可能会缓存响应，导致返回的失败状态
                    if (response.ok) {
                        // 保持当前状态
                        return;
                    }
                    // 只有在确实失败的情况下才恢复状态
                    this.checked = originalState;
                    console.warn('Toggle state might not be saved correctly');
                })
                .catch(error => {
                    console.error('Error:', error);
                    // 只在网络错误时恢复状态和显示提示
                    this.checked = originalState;
                    // 使用更友好的错误提示
                    console.warn('网络请求失败，请检查网络连接');
                })
                .finally(() => {
                    this.disabled = false;
                });
        }, 300);

        checkbox.addEventListener('change', function(e) {
            e.preventDefault();
            debouncedHandler.call(this, e);
        });
    });
</script>
<script>
    // 全选和反选功能
    const selectAllBtn = document.getElementById('selectAll');
    const selectAllInTable = document.getElementById('selectAllInTable');
    const checkboxes = document.querySelectorAll('input[name="deleteFiles[]"]');

    // 工具栏全选按钮点击事件
    selectAllBtn.addEventListener('click', function() {
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
        if (selectAllInTable) {
            selectAllInTable.checked = !allChecked;
            selectAllInTable.indeterminate = false;
        }
        updateSelectionCount();
    });

    // 表格中的全选复选框变更事件
    if (selectAllInTable) {
        selectAllInTable.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectionCount();
        });
    }

    // 反选按钮点击事件
    document.getElementById('invertSelection').addEventListener('click', function() {
        checkboxes.forEach(cb => cb.checked = !cb.checked);
        updateSelectionCount();
    });

    // 更新选中数量显示和全选状态
    function updateSelectionCount() {
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        const selectionCount = document.getElementById('selectionCount');
        const selectionInfo = document.querySelector('.selection-info');

        // 更新选中数量显示
        if (checkedCount > 0) {
            selectionCount.textContent = `已选择 ${checkedCount} 个文件`;
            selectionInfo.classList.add('active');
        } else {
            selectionCount.textContent = '未选择文件';
            selectionInfo.classList.remove('active');
        }

        // 更新表格全选复选框状态
        if (selectAllInTable) {
            if (checkedCount === 0) {
                selectAllInTable.checked = false;
                selectAllInTable.indeterminate = false;
            } else if (checkedCount === checkboxes.length) {
                selectAllInTable.checked = true;
                selectAllInTable.indeterminate = false;
            } else {
                selectAllInTable.checked = false;
                selectAllInTable.indeterminate = true;
            }
        }
    }

    // 为所有复选框添加change事件
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectionCount);
    });

    // 初始化选中状态显示
    updateSelectionCount();
</script>
<script>
    // 更新选中数量显示
    function updateSelectionCount() {
        const checkboxes = document.querySelectorAll('input[name="deleteFiles[]"]');
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        const selectionCount = document.getElementById('selectionCount');
        const selectionInfo = document.querySelector('.selection-info');

        if (checkedCount > 0) {
            selectionCount.textContent = `已选择 ${checkedCount} 个文件`;
            selectionInfo.classList.add('active');
        } else {
            selectionCount.textContent = '未选择文件';
            selectionInfo.classList.remove('active');
        }
    }

    // 批量删除按钮事件
    document.getElementById('batchDeleteBtn').addEventListener('click', function() {
        const checkedCount = document.querySelectorAll('input[name="deleteFiles[]"]:checked').length;
        if (checkedCount === 0) {
            alert('请先选择要删除的文件');
            return;
        }
        if (confirm(`确定要删除选中的 ${checkedCount} 个文件吗？此操作不可恢复。`)) {
            // 设置 batchDelete 字段为 1
            document.getElementById('batchDeleteField').value = '1';
            document.getElementById('enabledForm').submit();
        }
    });

    // 初始化选中状态显示
    updateSelectionCount();
</script>
<script>
    // 清理未使用文件功能
    document.getElementById('cleanupFiles').addEventListener('click', function() {
        if (!confirm('确定要清理未使用的文件吗？此操作不可恢复。')) {
            return;
        }

        const dialog = document.getElementById('cleanupDialog');
        const status = document.getElementById('cleanupStatus');
        const progressBar = dialog.querySelector('.progress-bar');

        dialog.style.display = 'block';
        progressBar.style.width = '0%';

        // 修改为正确的路径
        fetch('./Plugins/cleanup.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin' // 添加这行以确保发送 cookies
        })
            .then(response => response.json())
            .then(data => {
                progressBar.style.width = '100%';
                if (data.success) {
                    status.textContent = `清理完成，共删除 ${data.count} 个未使用文件`;
                    // 显示具体删除了哪些文件
                    if (data.files && data.files.length > 0) {
                        status.textContent += `\n删除的文件：${data.files.join(', ')}`;
                    }
                    setTimeout(() => {
                        dialog.style.display = 'none';
                        location.reload();
                    }, 2000);
                } else {
                    status.textContent = data.message || '清理失败';
                    setTimeout(() => {
                        dialog.style.display = 'none';
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('清理过程发生错误:', error);
                status.textContent = '清理过程发生错误，请查看控制台';
                progressBar.style.backgroundColor = '#ff4444';
                setTimeout(() => {
                    dialog.style.display = 'none';
                }, 2000);
            });
    });

    // 初始化选中数量显示
    updateSelectionCount();
</script>
<script>
    // 初始化文件类型饼图
    function initFileTypesChart() {
        const fileTypes = <?php echo json_encode(array_map(function($type, $data) {
            return [
                'type' => $type,
                'count' => $data['count'],
                'size' => $data['size']
            ];
        }, array_keys($fileStats['file_types']), array_values($fileStats['file_types']))); ?>;

        // 创建自定义颜色数组
        const chartColors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
            '#FF9F40', '#2ECC71', '#E74C3C', '#3498DB', '#9B59B6'
        ];

        // 检查视窗宽度，根据设备调整图例位置
        const windowWidth = window.innerWidth;
        const legendPosition = windowWidth <= 768 ? 'bottom' : 'right';

        // 文件数量饼图
        new Chart(document.getElementById('fileTypesChart'), {
            type: 'pie',
            data: {
                labels: fileTypes.map(item => '.' + item.type),
                datasets: [{
                    data: fileTypes.map(item => item.count),
                    backgroundColor: chartColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: legendPosition,
                        labels: {
                            font: {
                                size: 10
                            },
                            boxWidth: 10,
                            padding: 8
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} 个文件 (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // 存储空间饼图
        new Chart(document.getElementById('storageChart'), {
            type: 'pie',
            data: {
                labels: fileTypes.map(item => '.' + item.type),
                datasets: [{
                    data: fileTypes.map(item => item.size),
                    backgroundColor: chartColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: legendPosition,
                        labels: {
                            font: {
                                size: 10
                            },
                            boxWidth: 10,
                            padding: 8
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                const size = (value / 1024 / 1024).toFixed(2);
                                return `${label}: ${size} MB (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 添加窗口大小变化监听，重新初始化图表
    window.addEventListener('resize', function() {
        // 销毁现有图表并重新创建
        const charts = Chart.instances || [];
        for (let chart of charts) {
            chart.destroy();
        }
        initFileTypesChart();
    });

    // 页面加载完成后初始化图表
    document.addEventListener('DOMContentLoaded', initFileTypesChart);
</script>
<script>
    const previewFiles = <?php echo json_encode(array_map(function($file) use ($config) {
        $fileUrl = (isset($config['enabledFiles'][$file]) && $config['enabledFiles'][$file])
            ? 'assets/showimg/' . $file
            : 'assets/' . $file;
        return [
            'url' => $fileUrl,
            'type' => in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['mp4', 'webm', 'ogg']) ? 'video' : 'image'
        ];
    }, $displayFiles)); ?>;

    let currentPreviewIndex = -1;

    function showPreview(index) {
        if (index < 0 || index >= previewFiles.length) return;

        currentPreviewIndex = index;
        const file = previewFiles[index];
        previewContainer.innerHTML = '';

        if (file.type === 'image') {
            const img = new Image();
            img.onload = function() {
                previewContainer.innerHTML = ''; // 清除可能的加载提示
                previewContainer.appendChild(img);

                // 更新导航按钮状态
                updateNavButtons();
            };
            img.onerror = function() {
                previewContainer.innerHTML = '<div class="error-message">图片加载失败</div>';
            };

            // 添加加载提示
            previewContainer.innerHTML = '<div class="loading">加载中...</div>';
            img.src = file.url;
        } else {
            const video = document.createElement('video');
            video.src = file.url;
            video.controls = true;
            video.autoplay = true;
            video.style.maxWidth = '100%';
            video.style.maxHeight = 'calc(95vh - 40px)';
            previewContainer.appendChild(video);

            // 更新导航按钮状态
            updateNavButtons();
        }
    }

    // 添加导航按钮状态更新函数
    function updateNavButtons() {
        const prevBtn = document.querySelector('.nav-btn.prev');
        const nextBtn = document.querySelector('.nav-btn.next');

        if (prevBtn) {
            prevBtn.style.visibility = currentPreviewIndex > 0 ? 'visible' : 'hidden';
        }
        if (nextBtn) {
            nextBtn.style.visibility = currentPreviewIndex < previewFiles.length - 1 ? 'visible' : 'hidden';
        }
    }

    // 添加加载提示和错误消息的样式
    const style = document.createElement('style');
    style.textContent = `
        .loading {
            color: #666;
            font-size: 16px;
            padding: 20px;
        }

        .error-message {
            color: #ff4444;
            font-size: 16px;
            padding: 20px;
        }
    `;
    document.head.appendChild(style);
</script>
<script>
    // 添加键盘事件
    document.addEventListener('keydown', (e) => {
        if (modal.style.display === 'flex') {
            switch(e.key) {
                case 'Escape':
                    closeModal();
                    break;
                case 'ArrowLeft':
                    showPreview(currentPreviewIndex - 1);
                    break;
                case 'ArrowRight':
                    showPreview(currentPreviewIndex + 1);
                    break;
            }
        }
    });

    // 添加导航按钮到模态框
    const modalContent = document.querySelector('.modal-content');
    modalContent.insertAdjacentHTML('beforeend', `
        <div class="preview-nav">
            <button class="nav-btn prev" onclick="showPreview(currentPreviewIndex - 1)">‹</button>
            <button class="nav-btn next" onclick="showPreview(currentPreviewIndex + 1)">›</button>
        </div>
    `);
</script>
</body>
</html>

