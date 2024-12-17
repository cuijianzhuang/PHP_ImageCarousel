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
    $uploadFileName = basename($_FILES['uploadFile']['name']);
    $targetFilePath = $directory . $uploadFileName;
    $ext = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

    if (in_array($ext, $allowedExtensions)) {
        if (move_uploaded_file($_FILES['uploadFile']['tmp_name'], $targetFilePath)) {
            $message = "文件上传成功！";
            $config['enabledFiles'][$uploadFileName] = true; // 默认启用新文件
            // 保存配置文件
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $message = "文件上传失败。";
        }
    } else {
        $message = "不支持的文件类型。";
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

// 更新配置（配置设置）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveConfigSettings'])) {
    // 间隔时间
    if (isset($_POST['autoplayInterval'])) {
        $interval = intval($_POST['autoplayInterval']);
        $config['autoplayInterval'] = $interval > 0 ? $interval : 3000;
    }

    // 视图模式
    if (isset($_POST['viewMode']) && in_array($_POST['viewMode'], ['list', 'grid'])) {
        $config['viewMode'] = $_POST['viewMode'];
    }

    // 每页显示数
    if (isset($_POST['perPage'])) {
        $pp = intval($_POST['perPage']);
        $config['perPage'] = $pp > 0 ? $pp : 10;
    }

    // 保存配置文件
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $message = "配置已保存。";
}

// 更新配置（文件启用状态）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveEnabledFiles'])) {
    // 文件启用状态
    $enabledFilesPost = $_POST['enabled'] ?? [];
    // 遍历当前文件夹中的文件
    if (is_dir($directory)) {
        $scan = array_diff(scandir($directory), ['.', '..']);
        foreach ($scan as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExtensions)) {
                // 如果文件在提交的启用列表中，则设置为 true，否则保持原有状态
                if (in_array($f, $enabledFilesPost)) {
                    $config['enabledFiles'][$f] = true;
                } else {
                    // 只修改当前页面显示的文件状态
                    if (isset($config['enabledFiles'][$f])) {
                        $config['enabledFiles'][$f] = false;
                    }
                }
            }
        }
    }

    // 保存配置文件
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $message = "文件显示配置已保存。";
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
                // 所有文件都添加到列表，不再检查启用状态
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
            background:#333; color:#fff; padding:5px; border-radius:3px; text-decoration:none; font-size:14px;
            display:inline-block; margin-bottom:5px;
        }
        .preview-btn:hover {
            background:#555;
        }
        .delete-link {
            color:#c00;
        }
        .delete-link:hover {
            text-decoration:underline;
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
    </style>
</head>
<body>
<header>
    <div>文件管理</div>
    <div>
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

    <div class="upload-area">
        <h3>上传文件（图片或视频）</h3>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="uploadFile" required>
            <button type="submit">上传</button>
        </form>
    </div>


    <!-- 配置设置表单 -->
    <form class="config-form" method="post" action="">
        <h3>配置项</h3>
        <p>
            轮播间隔时间（毫秒，对首页单文件无作用，可留存）：<input type="number" name="autoplayInterval" value="<?= htmlspecialchars($config['autoplayInterval']) ?>" min="100" step="100">
        </p>
        <p>
            每页显示：<input type="number" name="perPage" value="<?= htmlspecialchars($config['perPage']) ?>" min="1" style="width:60px;"> 个文件
        </p>
        <h3>视图模式</h3>
        <p>
            <label>
                <input type="radio" name="viewMode" value="list" <?= $viewMode === 'list' ? 'checked' : '' ?>> 列表视图
            </label>
            <label>
                <input type="radio" name="viewMode" value="grid" <?= $viewMode === 'grid' ? 'checked' : '' ?>> 网格视图
            </label>
        </p>
        <input type="hidden" name="saveConfigSettings" value="1">
        <button type="submit">保存配置</button>
    </form>

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
        <?php if (empty($displayFiles)): ?>
            <p>没有匹配的媒体文件。</p>
        <?php else: ?>
            <!-- 文件启用状态表单 -->
            <form method="post" action="">
                <input type="hidden" name="saveEnabledFiles" value="1">
                <?php if ($viewMode === 'list'): ?>
                    <div class="files-list">
                        <table>
                            <tr>
                                <th>显示</th>
                                <th>缩略图</th>
                                <th>文件名</th>
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
                                        <label>
                                            <input type="checkbox" class="enable-checkbox" name="enabled[]" value="<?= htmlspecialchars($file) ?>" <?= $enabled ? 'checked' : '' ?>>
                                            显示
                                        </label>
                                    </td>
                                    <td>
                                        <?php if ($isVideo): ?>
                                            <video data-src="<?= htmlspecialchars($fileUrl) ?>" preload="none" muted class="lazy" style="max-width:100px;max-height:60px;"></video>
                                        <?php else: ?>
                                            <img data-src="<?= htmlspecialchars($fileUrl) ?>" alt="" class="lazy" style="max-width:100px;max-height:60px;">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($file) ?></td>
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
                                <label style="position:absolute;top:10px;left:10px;">
                                    <input type="checkbox" class="enable-checkbox" name="enabled[]" value="<?= htmlspecialchars($file) ?>" <?= $enabled ? 'checked' : '' ?>>
                                </label>
                                <?php if ($isVideo): ?>
                                    <video data-src="<?= htmlspecialchars($fileUrl) ?>" preload="none" muted class="lazy"></video>
                                <?php else: ?>
                                    <img data-src="<?= htmlspecialchars($fileUrl) ?>" alt="" class="lazy">
                                <?php endif; ?>
                                <div><?= htmlspecialchars($file) ?></div>
                                <div class="action-links" style="margin-top:5px;">
                                    <a href="#" class="preview-btn" data-file="<?= htmlspecialchars($fileUrl) ?>" data-type="<?= $isVideo ? 'video' : 'image' ?>">预览</a><br>
                                    <a href="?<?= $search ? 'search=' . urlencode($search) . '&' : '' ?>delete=<?= urlencode($file) ?>" class="delete-link" onclick="return confirm('确定删除此文件？')">删除</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <button type="submit" style="margin-top:10px;">保存文件显示配置</button>
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
    });q

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

</body>
</html>
