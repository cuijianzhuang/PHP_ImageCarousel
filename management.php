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
                $config['enabledFiles'][basename($uploadFileName)] = true;
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
        unlink($file_path);
        unset($config['enabledFiles'][$file_to_delete]);
        // 保存更新后的配置
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = "文件 '$file_to_delete' 已删除。";
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
            unlink($filePath);
            unset($config['enabledFiles'][$fileToDelete]);
            $filesDeleted++;
        }
    }
    // 保存更新后的配置
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

// 对文件列表进行排序，优先显示启用的文件
usort($files, function($a, $b) use ($config) {
    $enabledA = isset($config['enabledFiles'][$a]) ? $config['enabledFiles'][$a] : true;
    $enabledB = isset($config['enabledFiles'][$b]) ? $config['enabledFiles'][$b] : true;

    // 启用的文件排在前面
    if ($enabledA !== $enabledB) {
        return $enabledA ? -1 : 1;
    }

    // 如果启用状态相同，按文件名排序
    return strcmp($a, $b);
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
    global $config; // 添加对全局 config 的访问
    
    $stats = [
        'total_files' => 0,
        'total_size' => 0,
        'enabled_files' => 0, // 添加展示文件计数
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
        body {
            font-family: Arial, sans-serif; background:#eef; margin:0; padding:0;
        }
        header {
            background:#333; color:#fff; padding:10px; display:flex; justify-content:space-between; align-items:center;
        }
        header a {
            color:#fff; text-decoration:none; margin-left:10px; font-weight:bold;
        }
        header form, header a {
            display:inline-block;
        }
        .wrapper {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 30px;
        }
        .upload-area, .config-form, .search-form {
            background:#fff; padding:20px; margin-bottom:20px;
            box-shadow:0 0 10px rgba(0,0,0,.1); border-radius:5px;
        }
        .message {
            margin-bottom:10px; color:green;
        }
        .files-container {
            background:#fff; padding:20px; box-shadow:0 0 10px rgba(0,0,0,.1); border-radius:5px;
            overflow:hidden;
        }
        .files-list table {
            width:100%; border-collapse: collapse;
        }
        .files-list th, .files-list td {
            padding:10px; border-bottom:1px solid #eee; text-align:left; vertical-align: middle;
        }
        .files-list th {
            background:#f0f0f0;
        }
        .files-list tr:hover {
            background:#f9f9f9;
        }
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            padding: 25px;
        }

        .file-item {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
            position: relative;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .file-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .file-item .media-container {
            aspect-ratio: 16/9;
            overflow: hidden;
            border-radius: 6px;
            margin-bottom: 10px;
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
            color: #333;
            margin: 8px 0;
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
            gap: 8px;
            justify-content: center;
        }
        .enable-checkbox {
            transform:scale(1.2);
            margin-right:5px;
        }
        .pagination {
            text-align:center;
            margin:30px 0;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .pagination a, .pagination span {
            display:inline-block; padding:8px 16px; margin:0 5px;
            text-decoration:none; background:#fff; border:1px solid #eee; border-radius:8px; color:#444;
            transition: all 0.2s ease;
        }
        .pagination a:hover {
            background:#f8f9fa;
            border-color: #ddd;
        }
        .pagination .current {
            background:#2196F3; color:#fff; border-color:#2196F3;
        }
        .preview-btn {
            background:#4CAF50;
            color:#fff;
            padding:5px;
            border-radius:3px;
            text-decoration:none;
            font-size:14px;
            display:inline-block;
            margin-bottom:5px;
        }
        .preview-btn:hover {
            background:#45a049;
        }
        .delete-link {
            color:#fff;
            background: #ff4444;
            padding:5px;
            border-radius:3px;
            text-decoration:none;
            font-size:14px;
            display:inline-block;
        }
        .delete-link:hover {
            background:#cc0000;
        }
        .lazy {
            opacity:0; transition:opacity .3s;
        }
        .lazy-loaded {
            opacity:1;
        }

        /* 模态框样式 */
        .modal {
            display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.7);
            justify-content:center; align-items:center;
        }
        .modal-content {
            background:#fff; padding:20px; border-radius:5px; position:relative; max-width:90%; max-height:90%;
            display:flex; flex-direction:column; align-items:center; box-shadow:0 0 10px rgba(0,0,0,.3);
        }
        .modal-content img, .modal-content video {
            max-width:100%; max-height:80vh; margin-bottom:10px;
        }
        .modal-close {
            position:absolute; top:10px; right:10px; background:#333; color:#fff; border:none; border-radius:50%;
            width:30px; height:30px; display:flex; justify-content:center; align-items:center; cursor:pointer;
            font-size:20px; line-height:1;
        }
        .modal-close:hover {
            background:#555;
        }

        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            margin-bottom: 30px;
        }
        
        .upload-area.dragover {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
        }
        
        .upload-hint {
            color: #666;
            margin: 15px 0;
        }
        
        .upload-btn {
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
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
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
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
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .upload-progress {
            margin-top: 10px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 4px;
            background: #4CAF50;
            width: 0;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            font-size: 12px;
            color: #666;
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
            background-color: #2196F3;
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
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
            text-align: center;
            width: auto;
        }
        
        .dashboard-stats .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .dashboard-stats .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 16px;
        }
        
        .dashboard-stats .stat-card p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .dashboard-stats .file-types {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .dashboard-stats .file-types h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 18px;
        }
        
        .dashboard-stats .file-types table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .dashboard-stats .file-types th,
        .dashboard-stats .file-types td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .dashboard-stats .file-types th {
            background: #f5f5f5;
            font-weight: bold;
            color: #666;
        }
        
        .dashboard-stats .file-types tr:hover {
            background: #f9f9f9;
        }
        
        .dashboard-stats .file-types td:last-child {
            text-align: right;
        }

        /* 添加工具栏按钮样式 */
        .tool-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #4CAF50;
            color: white;
            transition: background-color 0.3s;
        }

        .tool-btn:hover {
            background: #45a049;
        }

        .tool-btn.cleanup {
            background: #ff9800;
        }

        .tool-btn.cleanup:hover {
            background: #f57c00;
        }

        #selectionCount {
            color: #666;
            margin-left: 10px;
        }

        /* 添加清理进度对话框样式 */
        .cleanup-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
        }

        .cleanup-dialog .progress {
            margin: 15px 0;
            height: 4px;
            background: #eee;
            border-radius: 2px;
            overflow: hidden;
        }

        .cleanup-dialog .progress-bar {
            height: 100%;
            background: #4CAF50;
            width: 0;
            transition: width 0.3s;
        }

        /* 工具栏样式 */
        .toolbar {
            background: #fff;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .tool-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .tool-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #4CAF50;
            color: white;
            transition: background-color 0.3s;
        }

        .tool-btn:hover {
            background: #45a049;
        }

        .tool-btn.primary {
            background: #4CAF50;
        }

        .tool-btn.primary:hover {
            background: #43A047;
        }

        .tool-btn.secondary {
            background: #2196F3;
        }

        .tool-btn.secondary:hover {
            background: #1E88E5;
        }

        .tool-btn.danger {
            background: #F44336;
        }

        .tool-btn.danger:hover {
            background: #E53935;
        }

        .tool-btn.warning {
            background: #FF9800;
        }

        .tool-btn.warning:hover {
            background: #F57C00;
        }

        .selection-info {
            padding: 6px 12px;
            background: #f5f5f5;
            border-radius: 4px;
            color: #666;
            font-size: 14px;
        }

        /* 当选中文件时高亮显示 */
        .selection-info.active {
            background: #E3F2FD;
            color: #1976D2;
            font-weight: 500;
        }

        /* 复选框样式 */
        .select-all-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        input[type="checkbox"][name="deleteFiles[]"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            vertical-align: middle;
        }

        /* 表格样式优化 */
        .files-list table th:first-child,
        .files-list table td:first-child {
            width: 40px;
            text-align: center;
            padding: 8px;
        }

        /* 添加饼图容器样式 */
        .stats-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }
        
        .chart-container {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            min-height: 300px;
        }
        
        .chart-title {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<header>
    <div>文件管理</div>
    <div>
        <a href="settings.php">系统设置</a>
        <a href="index.php">返回首页</a>
        <form action="logout.php" method="post" style="display:inline;">
            <button type="submit" style="background:#c33; border:none; color:#fff; padding:5px 10px; cursor:pointer; border-radius:3px;">
                登出
            </button>
        </form>
    </div>
</header>

<div class="wrapper">
    <!-- 仪表盘统计区域 -->
    <div class="dashboard-stats">
        <div class="stats-overview">
            <div class="stat-card">
                <h3>总文件数</h3>
                <p><?php echo $fileStats['total_files']; ?> 个文件</p>
            </div>
            <div class="stat-card">
                <h3>展示文件数</h3>
                <p><?php echo $fileStats['enabled_files']; ?> 个文件</p>
            </div>
            <div class="stat-card">
                <h3>总存储空间</h3>
                <p><?php echo number_format($fileStats['total_size'] / 1024 / 1024, 2); ?> MB</p>
            </div>
        </div>

        <div class="stats-charts">
            <div class="chart-container">
                <h3 class="chart-title">文件类型分布</h3>
                <canvas id="fileTypesChart"></canvas>
            </div>
            <div class="chart-container">
                <h3 class="chart-title">存储空间占用</h3>
                <canvas id="storageChart"></canvas>
            </div>
        </div>

        <div class="file-types">
            <h2>文件类型统计</h2>
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
                        <td>.<?php echo htmlspecialchars($type); ?></td>
                        <td><?php echo $data['count']; ?> 个文件</td>
                        <td><?php echo number_format($data['size'] / 1024 / 1024, 2); ?> MB</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($message) echo "<p class='message'>" . htmlspecialchars($message) . "</p>"; ?>

    <!-- 上传区域 -->
    <div class="upload-area" id="dropZone">
        <h3>上传文件（图片或视频）</h3>
        <p class="upload-hint">点击选择或拖拽文件到此处</p>
        <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
            <input type="file" name="uploadFile[]" id="fileInput" multiple accept="image/*,video/*" style="display: none;">
            <button type="button" id="selectFiles" class="upload-btn">选择文件</button>
        </form>
        <!-- 文件预览区域 -->
        <div id="filePreview" class="file-preview"></div>
        <!-- 进度条容器 -->
        <div id="uploadProgress"></div>
    </div>

    <div class="search-form">
        <h3>搜索文件</h3>
        <form action="" method="get">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="输入文件名关键字">
            <button type="submit">搜索</button>
            <?php if ($search): ?>
                <a href="?">清除搜索</a>
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
            </div>
            <div class="selection-info">
                <span id="selectionCount"></span>
            </div>
        </div>
        <?php if (empty($displayFiles)): ?>
            <p>没有匹配的媒体文件</p>
        <?php else: ?>
            <div style="margin-bottom: 15px;">
                总文件数量: <?= $totalFiles ?> 个文件 | 
                展示文件数量: <?= count(array_filter($config['enabledFiles'], function($enabled) { return $enabled; })) ?> 个文件
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
                                <th><input type="checkbox" id="selectAllInTable" class="select-all-checkbox"></th>
                                <th>缩略图</th>
                                <th>文件名</th>
                                <th>轮播展示</th>
                                <th>操作</th>
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
                                        <?php if ($isVideo): ?>
                                            <video data-src="<?= htmlspecialchars($fileUrl) ?>" preload="none" muted class="lazy" style="max-width:100px;max-height:60px;"></video>
                                        <?php else: ?>
                                            <img data-src="<?= htmlspecialchars($fileUrl) ?>" alt="" class="lazy" style="max-width:100px;max-height:60px;">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($file) ?></td>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" class="enable-checkbox" name="enabled[]" 
                                                   value="<?= htmlspecialchars($file) ?>" 
                                                   <?= $enabled ? 'checked' : '' ?> 
                                                   onchange="document.getElementById('enabledForm').submit()">
                                            <span class="slider round"></span>
                                        </label>
                                    </td>
                                    <td class="action-links">
                                        <a href="#" class="preview-btn" data-file="<?= htmlspecialchars($fileUrl) ?>" data-type="<?= $isVideo ? 'video' : 'image' ?>">预览</a>
                                        <a href="?<?= $search ? 'search=' . urlencode($search) . '&' : '' ?>delete=<?= urlencode($file) ?>" class="delete-link" onclick="return confirm('确定删除此文件？')">删除</a>
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
                                <label class="switch" style="position:absolute;top:10px;right:10px;z-index:1;">
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
                                    <?php else: ?>
                                        <img data-src="<?= htmlspecialchars($fileUrl) ?>" 
                                             alt="" class="lazy">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="filename" title="<?= htmlspecialchars($file) ?>">
                                    <?= htmlspecialchars($file) ?>
                                </div>
                                
                                <div class="action-links">
                                    <a href="#" class="preview-btn" 
                                       data-file="<?= htmlspecialchars($fileUrl) ?>" 
                                       data-type="<?= $isVideo ? 'video' : 'image' ?>">
                                        预览
                                    </a>
                                    <a href="?<?= $search ? 'search=' . urlencode($search) . '&' : '' ?>delete=<?= urlencode($file) ?>" 
                                       class="delete-link" 
                                       onclick="return confirm('确定删除此文件？')">
                                        删除
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <!-- Remove the submit button since we're using auto-submit -->
            </form>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $searchParam = $search ? 'search=' . urlencode($search) . '&' : '';
                    if ($currentPage > 1): ?>
                        <a href="?<?= $searchParam ?>page=1">«</a>
                        <a href="?<?= $searchParam ?>page=<?= $currentPage - 1 ?>">‹</a>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php if ($p == $currentPage): ?>
                            <span class="current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="?<?= $searchParam ?>page=<?= $p ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?<?= $searchParam ?>page=<?= $currentPage + 1 ?>">›</a>
                        <a href="?<?= $searchParam ?>page=<?= $totalPages ?>">»</a>
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
    </div>
</div>

<!-- 添加清理进度对话框 -->
<div id="cleanupDialog" class="cleanup-dialog">
    <h3>正在清理未使用文件</h3>
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

    previewBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const fileUrl = btn.getAttribute('data-file');
            const fileType = btn.getAttribute('data-type');
            previewContainer.innerHTML = '';
            if (fileType === 'image') {
                const img = document.createElement('img');
                img.src = fileUrl;
                previewContainer.appendChild(img);
            } else {
                const video = document.createElement('video');
                video.src = fileUrl;
                video.controls = true;
                video.autoplay = true;
                previewContainer.appendChild(video);
            }
            modal.style.display = 'flex';
        });
    });

    modalClose.addEventListener('click', () => {
        modal.style.display = 'none';
        previewContainer.innerHTML = '';
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
            previewContainer.innerHTML = '';
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
    fetch('./plugins/cleanup.php', {
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

    // 文件数量饼图
    new Chart(document.getElementById('fileTypesChart'), {
        type: 'pie',
        data: {
            labels: fileTypes.map(item => '.' + item.type),
            datasets: [{
                data: fileTypes.map(item => item.count),
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: {
                            size: 12
                        }
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
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: {
                            size: 12
                        }
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

// 页面加载完成后初始化图表
document.addEventListener('DOMContentLoaded', initFileTypesChart);
</script>
</body>
</html>

