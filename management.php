<?php
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

// 上传文件处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['uploadFile']) && !isset($_POST['saveConfigSettings']) && !isset($_POST['saveEnabledFiles'])) {
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    // 处理多文件��传
    $files = [];
    $fileCount = is_array($_FILES['uploadFile']['name']) ? count($_FILES['uploadFile']['name']) : 1;

    for ($i = 0; $i < $fileCount; $i++) {
        $uploadFileName = is_array($_FILES['uploadFile']['name']) ? $_FILES['uploadFile']['name'][$i] : $_FILES['uploadFile']['name'];
        $tmpName = is_array($_FILES['uploadFile']['tmp_name']) ? $_FILES['uploadFile']['tmp_name'][$i] : $_FILES['uploadFile']['tmp_name'];
        
        if (empty($uploadFileName) || empty($tmpName)) continue;

        $targetFilePath = $directory . basename($uploadFileName);
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

// 删除文件
if (isset($_GET['delete'])) {
    $file_to_delete = basename($_GET['delete']);
    $file_path = $directory . $file_to_delete;
    if (file_exists($file_path)) {
        unlink($file_path);
        $message = "文件 '$file_to_delete' 已删除。";
        unset($config['enabledFiles'][$file_to_delete]);
        // 保存配置文件
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        $message = "文件不存在或已被删除。";
    }
}

// 更新配置（文件启用状态）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveEnabledFiles'])) {
    // 获取当前页面的文件列表
    $displayFiles = isset($_POST['displayFiles']) ? json_decode($_POST['displayFiles'], true) : [];
    $enabledFilesPost = $_POST['enabled'] ?? [];

    // 添加文件锁
    $lockFile = __DIR__ . '/config.lock';
    $lockHandle = fopen($lockFile, 'w+');
    
    if (flock($lockHandle, LOCK_EX)) {  // 获取独占锁
        try {
            // 重新读取配置文件以确保获取最新状态
            $config = json_decode(file_get_contents($configFile), true);
            if (!is_array($config)) $config = [];
            
            // 更新配置
            foreach ($displayFiles as $file) {
                if (isset($config['enabledFiles'][$file])) {
                    $config['enabledFiles'][$file] = in_array($file, $enabledFilesPost);
                }
            }

            // 保存配置文件
            $saveSuccess = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => $saveSuccess]);
                flock($lockHandle, LOCK_UN);  // 释放锁
                fclose($lockHandle);
                exit;
            }
        } catch (Exception $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => '保存配置失败']);
                flock($lockHandle, LOCK_UN);  // 释放锁
                fclose($lockHandle);
                exit;
            }
        }
        
        flock($lockHandle, LOCK_UN);  // 释放锁
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => '系统繁忙，请稍后重试']);
            fclose($lockHandle);
            exit;
        }
    }
    fclose($lockHandle);
}

// 搜索文件
$search = $_GET['search'] ?? '';

// 获取文件列表
$files = [];
if (is_dir($directory)) {
    $scan = array_diff(scandir($directory), ['.', '..']);
    foreach ($scan as $f) {
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExtensions)) {
            // 如果有搜索条件，就过滤文件名
            if ($search === '' || stripos($f, $search) !== false) {
                // 所有文件都添加到列表，不再检查用状态
                $files[] = $f;
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
?>
<!DOCTYPE html>
<html lang="zh-CN">
<link href="./favicon.ico" type="image/x-icon" rel="icon">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>文件管理</title>
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
            max-width:1200px; margin:20px auto;
            padding:0 20px;
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
            display:flex; flex-wrap:wrap; gap:10px;
        }
        .file-item {
            background:#fff; border:1px solid #ccc; border-radius:5px; padding:10px; width:calc(20% - 10px);
            box-sizing:border-box; text-align:center; position:relative;
        }
        .file-item:hover {
            background:#f9f9f9;
        }
        .file-item img, .file-item video {
            max-width:100%; max-height:100px; display:block; margin-bottom:10px; border-radius:3px;
        }
        .action-links a {
            margin-right:10px; text-decoration:none; color:#333; font-weight:bold; font-size:14px;
        }
        .enable-checkbox {
            transform:scale(1.2);
            margin-right:5px;
        }
        .pagination {
            text-align:center;
            margin:20px 0;
        }
        .pagination a, .pagination span {
            display:inline-block; padding:5px 10px; margin:0 5px;
            text-decoration:none; background:#fff; border:1px solid #ccc; border-radius:3px; color:#333;
        }
        .pagination a:hover {
            background:#eee;
        }
        .pagination .current {
            background:#333; color:#fff; border-color:#333;
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
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
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
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
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
    </style>
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
    <?php if ($message) echo "<p class='message'>" . htmlspecialchars($message) . "</p>"; ?>

    <!-- 上传区域 -->
    <div class="upload-area" id="dropZone">
        <h3>上传文件（图片或视频）</h3>
        <p class="upload-hint">点击选择拖拽文件到此处</p>
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
                <a href="?">���除搜索</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="files-container">
        <?php if (empty($displayFiles)): ?>
            <p>没有匹配的媒体文件。</p>
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
                <?php if ($viewMode === 'list'): ?>
                    <div class="files-list">
                        <table>
                            <tr>
                                <th>缩略图</th>
                                <th>文件名</th>
                                <th>轮播展示</th>
                                <th>操作</th>
                            </tr>
                            <?php foreach ($displayFiles as $file):
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                $fileUrl = 'assets/' . $file;
                                $isVideo = in_array($ext, ['mp4', 'webm', 'ogg']);
                                $enabled = isset($config['enabledFiles'][$file]) ? $config['enabledFiles'][$file] : true;
                                ?>
                                <tr>
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
                            $fileUrl = 'assets/' . $file;
                            $isVideo = in_array($ext, ['mp4', 'webm', 'ogg']);
                            $enabled = isset($config['enabledFiles'][$file]) ? $config['enabledFiles'][$file] : true;
                            ?>
                            <div class="file-item">
                                <label class="switch" style="position:absolute;top:10px;left:10px;">
                                    <input type="checkbox" class="enable-checkbox" name="enabled[]" 
                                           value="<?= htmlspecialchars($file) ?>" 
                                           <?= $enabled ? 'checked' : '' ?> 
                                           onchange="document.getElementById('enabledForm').submit()">
                                    <span class="slider round"></span>
                                </label>
                                <?php if ($isVideo): ?>
                                    <video data-src="<?= htmlspecialchars($fileUrl) ?>" preload="none" muted class="lazy"></video>
                                <?php else: ?>
                                    <img data-src="<?= htmlspecialchars($fileUrl) ?>" alt="" class="lazy">
                                <?php endif; ?>
                                <div><?= htmlspecialchars($file) ?></div>
                                <div class="action-links" style="margin-top:5px;">
                                    <a href="#" class="preview-btn" data-file="<?= htmlspecialchars($fileUrl) ?>" data-type="<?= $isVideo ? 'video' : 'image' ?>">预览</a><br>
                                    <a href="?<?= $search ? 'search=' . urlencode($search) . '&' : '' ?>delete=<?= urlencode($file) ?>" class="delete-link" onclick="return confirm('确定删��此文件？')">删除</a>
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

        // 显示进度条
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
    
    // 处理文件拖放
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
                progressText.textContent = `上传中: ${percent}%`;
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
    // 创建防抖版本的处理函数
    const debouncedHandler = debounce(function(e) {
        const form = document.getElementById('enabledForm');
        const formData = new FormData(form);
        
        // 显示加载指示器或禁用复选框
        this.disabled = true;
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                // 如果保存失败，恢复复选框状态
                this.checked = !this.checked;
                alert(data.message || '保存失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // 发生错误时恢复复选框状态
            this.checked = !this.checked;
            alert('保存失败，请重试');
        })
        .finally(() => {
            // 重新启用复选框
            this.disabled = false;
        });
    }, 300); // 300ms 的防抖延迟

    checkbox.addEventListener('change', function(e) {
        e.preventDefault();
        debouncedHandler.call(this, e);
    });
});
</script>
</body>
</html>
