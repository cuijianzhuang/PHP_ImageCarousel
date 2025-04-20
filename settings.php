<?php
require_once __DIR__ . '/script/path_utils.php';
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
if (!isset($config['viewMode'])) $config['viewMode'] = 'list';
if (!isset($config['perPage'])) $config['perPage'] = 10;

$message = null;

// 处理配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // 规范化文件路径
    $musicFile = isset($_POST['background_music']['file']) ? 
        trim($_POST['background_music']['file']) : '/assets/music/background.mp3';
    $musicFile = normalizeMusicPath($musicFile);
    
    $config['background_music'] = [
        'enabled' => isset($_POST['enableMusic']) ? true : false,
        'file' => $musicFile,
        'volume' => isset($_POST['background_music']['volume']) ? 
            max(0, min(1, floatval($_POST['background_music']['volume']))) : 0.5,
        'autoplay' => isset($_POST['background_music']['autoplay']) ? true : false,
        'loop' => isset($_POST['background_music']['loop']) ? true : false,
        'random' => isset($_POST['background_music']['random']) ? true : false
    ];
    
    // 单独设置 enableMusic 配置项
    $config['enableMusic'] = $config['background_music']['enabled'];

    // 保存配置文件
    if (file_put_contents($configFile, json_encode($config, 
        JSON_PRETTY_PRINT | 
        JSON_UNESCAPED_UNICODE | 
        JSON_UNESCAPED_SLASHES
    ))) {
        $message = "配置已保存。";
    } else {
        $message = "保存配置失败，请检查文件权限。";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置</title>
    <link href="./favicon.ico" type="image/x-icon" rel="icon">
    <style>
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --secondary-color: #2ecc71;
            --secondary-dark: #27ae60;
            --danger-color: #e74c3c;
            --danger-dark: #c0392b;
            --warning-color: #f39c12;
            --background-color: #f5f7fa;
            --card-color: #ffffff;
            --text-color: #333333;
            --text-muted: #7f8c8d;
            --border-color: #ecf0f1;
            --header-bg: #2c3e50;
            --header-text: #ecf0f1;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        header {
            background: var(--header-bg);
            color: var(--header-text);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        header .logo {
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        header .nav-links {
            display: flex;
            gap: 20px;
        }

        header a {
            color: var(--header-text);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        header a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .wrapper {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-title {
            margin-bottom: 25px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message {
            background: var(--secondary-color);
            color: white;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .settings-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .settings-card {
            background: var(--card-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .settings-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }

        .card-header {
            background: var(--border-color);
            padding: 18px 25px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-content {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }

        .help-text {
            display: block;
            margin-top: 5px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background: var(--card-color);
            color: var(--text-color);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 25px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-primary {
            background: var(--primary-color);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: var(--secondary-color);
        }

        .btn-success:hover {
            background: var(--secondary-dark);
        }

        .input-group {
            display: flex;
            gap: 10px;
        }

        .input-group .form-control {
            flex: 1;
        }

        .switch-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .switch-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 26px;
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
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:focus + .slider {
            box-shadow: 0 0 1px var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* 音量控制样式 */
        .volume-control {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .volume-control input[type="range"] {
            flex: 1;
            -webkit-appearance: none;
            height: 5px;
            background: #ddd;
            border-radius: 5px;
            outline: none;
        }

        .volume-control input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            background: var(--primary-color);
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
        }

        .volume-control input[type="range"]::-moz-range-thumb {
            width: 20px;
            height: 20px;
            background: var(--primary-color);
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }

        .volume-control input[type="range"]::-webkit-slider-thumb:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .volume-control input[type="range"]::-moz-range-thumb:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        #volumeValue {
            min-width: 50px;
            text-align: center;
            font-weight: 500;
            color: var(--primary-color);
        }

        /* 添加模态框样式 */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            background-color: var(--card-color);
            width: 90%;
            max-width: 600px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: slideIn 0.3s ease-out;
        }

        .modal-header {
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--text-color);
            font-weight: 600;
        }

        .modal-body {
            padding: 25px;
        }

        .close {
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            color: var(--text-color);
            background: var(--border-color);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #musicList {
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
        }

        .music-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .music-item:last-child {
            border-bottom: none;
        }

        .music-item:hover {
            background: var(--background-color);
        }

        .music-item .music-name {
            flex-grow: 1;
            font-weight: 500;
            cursor: pointer;
            padding: 5px 0;
            transition: var(--transition);
        }

        .music-item .music-name:hover {
            color: var(--primary-color);
        }

        .music-item .delete-btn {
            color: var(--danger-color);
            cursor: pointer;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .music-item .delete-btn:hover {
            background: rgba(231, 76, 60, 0.1);
        }

        /* 确认对话框样式 */
        .confirm-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--card-color);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            z-index: 1100;
            min-width: 300px;
            animation: scaleIn 0.3s ease-out;
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }

        .confirm-dialog p {
            margin-bottom: 20px;
            color: var(--text-color);
        }

        .confirm-dialog .buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        .btn-danger {
            background: var(--danger-color);
        }

        .btn-danger:hover {
            background: var(--danger-dark);
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .wrapper {
                padding: 0 15px;
                margin: 20px auto;
            }

            header {
                padding: 12px 15px;
            }

            .settings-content {
                padding: 20px 15px;
            }

            .radio-group {
                flex-direction: column;
                gap: 10px;
            }

            .input-group {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <i class="fas fa-cog fa-spin"></i> 系统设置
        </div>
        <div class="nav-links">
            <a href="management.php"><i class="fas fa-tasks"></i> 返回管理</a>
            <a href="index.php"><i class="fas fa-home"></i> 返回首页</a>
        </div>
    </header>

    <div class="wrapper">
        <h1 class="page-title"><i class="fas fa-sliders-h"></i> 系统配置</h1>
        
        <?php if ($message): ?>
            <div class="message">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form class="settings-form" method="post">
            <div class="settings-card">
                <div class="card-header">
                    <h3><i class="fas fa-photo-video"></i> 基本设置</h3>
                </div>
                <div class="settings-content">
                    <div class="form-group">
                        <label class="form-label">轮播间隔时间</label>
                        <input type="number" name="autoplayInterval" 
                               class="form-control"
                               value="<?= htmlspecialchars($config['autoplayInterval']) ?>" 
                               min="100" step="100">
                        <span class="help-text">轮播幻灯片切换间隔，单位为毫秒（注意：对首页单文件无作用）</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">每页显示文件数</label>
                        <input type="number" name="perPage" 
                               class="form-control"
                               value="<?= htmlspecialchars($config['perPage']) ?>" 
                               min="1">
                        <span class="help-text">在管理页面中每页显示的文件数量</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">视图模式</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="viewMode" value="list" 
                                       <?= $config['viewMode'] === 'list' ? 'checked' : '' ?>> 
                                <i class="fas fa-list"></i> 列表视图
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="viewMode" value="grid" 
                                       <?= $config['viewMode'] === 'grid' ? 'checked' : '' ?>> 
                                <i class="fas fa-th"></i> 网格视图
                            </label>
                        </div>
                        <span class="help-text">文件管理页面默认视图模式</span>
                    </div>
                </div>
            </div>

            <div class="settings-card">
                <div class="card-header">
                    <h3><i class="fas fa-music"></i> 背景音乐设置</h3>
                </div>
                <div class="settings-content">
                    <div class="form-group">
                        <div class="switch-container">
                            <label class="form-label">启用背景音乐</label>
                            <label class="switch">
                                <input type="checkbox" name="enableMusic" 
                                    <?= isset($config['background_music']['enabled']) && $config['background_music']['enabled'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <span class="help-text">启用或禁用网站背景音乐功能</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">音乐文件选择</label>
                        <div class="input-group">
                            <input type="text" name="background_music[file]" id="musicPath"
                                value="<?= htmlspecialchars($config['background_music']['file'] ?? '/assets/music/background.mp3') ?>" 
                                class="form-control" placeholder="选择或上传音乐文件">
                            <button type="button" class="btn btn-secondary" onclick="showMusicSelector()">
                                <i class="fas fa-music"></i> 选择音乐
                            </button>
                            <input type="file" id="musicFileInput" style="display: none" accept=".mp3,.wav,.ogg" onchange="handleMusicFileSelect(this)">
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('musicFileInput').click()">
                                <i class="fas fa-upload"></i> 上传
                            </button>
                        </div>
                        <span class="help-text">选择或上传要播放的背景音乐文件</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">音量控制</label>
                        <div class="volume-control">
                            <i class="fas fa-volume-down"></i>
                            <input type="range" name="background_music[volume]" 
                                min="0" max="1" step="0.1" 
                                value="<?= htmlspecialchars($config['background_music']['volume'] ?? 0.5) ?>"
                                oninput="updateVolumeValue(this.value)">
                            <i class="fas fa-volume-up"></i>
                            <span id="volumeValue"><?= round(($config['background_music']['volume'] ?? 0.5) * 100) ?>%</span>
                        </div>
                        <span class="help-text">调整背景音乐的播放音量</span>
                    </div>

                    <div class="form-group">
                        <div class="switch-container">
                            <label class="form-label">自动播放</label>
                            <label class="switch">
                                <input type="checkbox" name="background_music[autoplay]" 
                                    <?= isset($config['background_music']['autoplay']) && $config['background_music']['autoplay'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <span class="help-text">页面加载后自动开始播放背景音乐</span>
                    </div>

                    <div class="form-group">
                        <div class="switch-container">
                            <label class="form-label">循环播放</label>
                            <label class="switch">
                                <input type="checkbox" name="background_music[loop]" 
                                    <?= isset($config['background_music']['loop']) && $config['background_music']['loop'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <span class="help-text">音乐播放完成后自动重复播放</span>
                    </div>

                    <div class="form-group">
                        <div class="switch-container">
                            <label class="form-label">随机播放</label>
                            <label class="switch">
                                <input type="checkbox" name="background_music[random]" 
                                    <?= isset($config['background_music']['random']) && $config['background_music']['random'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <span class="help-text">从音乐文件夹中随机选择音乐进行播放</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> 保存设置
            </button>
        </form>
    </div>

    <div id="musicSelectorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-music"></i> 选择音乐文件</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="musicList"></div>
            </div>
        </div>
    </div>

    <script>
    // 音量控制
    function updateVolumeValue(value) {
        // 更新显示的音量值
        document.getElementById('volumeValue').textContent = Math.round(value * 100) + '%';
        
        // 更新图标显示
        const volumeIcons = document.querySelectorAll('.volume-control i');
        if (value > 0.6) {
            volumeIcons[0].className = 'fas fa-volume-down';
            volumeIcons[1].className = 'fas fa-volume-up';
        } else if (value > 0.2) {
            volumeIcons[0].className = 'fas fa-volume-down';
            volumeIcons[1].className = 'fas fa-volume-down';
        } else if (value > 0) {
            volumeIcons[0].className = 'fas fa-volume-off';
            volumeIcons[1].className = 'fas fa-volume-down';
        } else {
            volumeIcons[0].className = 'fas fa-volume-mute';
            volumeIcons[1].className = 'fas fa-volume-off';
        }
        
        // 使用 BroadcastChannel 发送消息到所有页面
        try {
            const volumeChannel = new BroadcastChannel('volumeControl');
            const vol = parseFloat(value);
            volumeChannel.postMessage({
                type: 'volumeChange',
                volume: vol
            });
        } catch (e) {
            console.log('BroadcastChannel not supported');
        }
        
        // 更新当前页面的音乐播放器（如果存在）
        const bgMusic = document.getElementById('bgMusic');
        if (bgMusic) {
            bgMusic.volume = parseFloat(value);
            localStorage.setItem('musicVolume', value);
        }
    }

    // 显示音乐选择器
    function showMusicSelector() {
        showLoading(document.getElementById('musicList'));
        
        fetch('script/get_music_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const musicList = document.getElementById('musicList');
                    musicList.innerHTML = '';
                    
                    // 如果没有音乐文件，显示提示
                    if (data.files.length === 0) {
                        musicList.innerHTML = '<div class="empty-message" style="padding: 20px; text-align: center; color: var(--text-muted);"><i class="fas fa-info-circle"></i> 没有找到音乐文件</div>';
                    } else {
                        // 渲染音乐列表
                        data.files.forEach(file => {
                            const div = document.createElement('div');
                            div.className = 'music-item';
                            
                            const nameSpan = document.createElement('span');
                            nameSpan.className = 'music-name';
                            nameSpan.innerHTML = `<i class="fas fa-music"></i> ${file.name}`;
                            nameSpan.onclick = () => {
                                document.getElementById('musicPath').value = file.path;
                                hideModal();
                                showToast('已选择音乐: ' + file.name);
                            };
                            
                            const deleteBtn = document.createElement('span');
                            deleteBtn.className = 'delete-btn';
                            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                            deleteBtn.onclick = (e) => {
                                e.stopPropagation();
                                confirmDelete(file);
                            };
                            
                            div.appendChild(nameSpan);
                            div.appendChild(deleteBtn);
                            musicList.appendChild(div);
                        });
                    }
                    
                    // 显示模态框
                    const modal = document.getElementById('musicSelectorModal');
                    modal.style.display = 'flex';
                    setTimeout(() => {
                        document.querySelector('.modal-content').style.opacity = 1;
                    }, 10);
                } else {
                    showToast('获取音乐列表失败: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('发生错误，请查看控制台', 'error');
            });
    }

    // 显示加载中状态
    function showLoading(container) {
        container.innerHTML = `
            <div style="padding: 20px; text-align: center; color: var(--text-muted);">
                <i class="fas fa-spinner fa-spin"></i> 正在加载...
            </div>
        `;
    }

    // 关闭模态框
    function hideModal() {
        const modal = document.getElementById('musicSelectorModal');
        document.querySelector('.modal-content').style.opacity = 0;
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }

    // 绑定关闭按钮事件
    document.querySelector('.close').onclick = hideModal;

    // 点击模态框背景关闭
    window.onclick = function(event) {
        const modal = document.getElementById('musicSelectorModal');
        if (event.target == modal) {
            hideModal();
        }
    }

    // 处理音乐文件上传
    function handleMusicFileSelect(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            
            // 检查文件类型
            if (!file.type.match('audio.*')) {
                showToast('请选择音频文件（MP3、WAV 或 OGG）', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('musicFile', file);
            
            // 创建上传状态提示
            const uploadStatus = document.createElement('div');
            uploadStatus.className = 'upload-status';
            uploadStatus.innerHTML = `
                <div style="margin-top: 10px; color: var(--primary-color); display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-spinner fa-spin"></i> 正在上传 "${file.name}"...
                </div>
            `;
            document.querySelector('.input-group').after(uploadStatus);
            
            fetch('script/upload_music.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('musicPath').value = data.filePath;
                    uploadStatus.remove();
                    showToast('音乐文件上传成功', 'success');
                } else {
                    uploadStatus.remove();
                    showToast('上传失败: ' + data.message, 'error');
                    console.error('Upload debug info:', data.debug);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                uploadStatus.remove();
                showToast('上传过程发生错误', 'error');
            });
        }
    }

    // 添加通知提示框
    function showToast(message, type = 'success') {
        // 移除现有的toast
        const existingToast = document.querySelector('.toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        // 创建新的toast
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        document.body.appendChild(toast);
        
        // 添加样式
        const style = document.createElement('style');
        style.textContent = `
            .toast {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 9999;
                animation: slideInRight 0.3s ease-out forwards;
            }
            .toast-content {
                background: white;
                color: var(--text-color);
                padding: 15px 20px;
                border-radius: var(--border-radius);
                box-shadow: 0 3px 10px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                gap: 10px;
                min-width: 250px;
            }
            .toast-success i {
                color: var(--secondary-color);
            }
            .toast-error i {
                color: var(--danger-color);
            }
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // 3秒后移除
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease-out forwards';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    }

    // 确认删除对话框
    function confirmDelete(file) {
        const existingDialog = document.querySelector('.confirm-dialog');
        if (existingDialog) {
            existingDialog.remove();
        }
        
        const dialog = document.createElement('div');
        dialog.className = 'confirm-dialog';
        dialog.innerHTML = `
            <p><i class="fas fa-exclamation-triangle" style="color: var(--warning-color);"></i> 确定要删除音乐文件 "${file.name}" 吗？</p>
            <div class="buttons">
                <button class="btn btn-sm" onclick="this.parentElement.parentElement.remove()">取消</button>
                <button class="btn btn-sm btn-danger" onclick="deleteMusic('${file.path}', this.parentElement.parentElement)">确定删除</button>
            </div>
        `;
        document.body.appendChild(dialog);
    }

    // 删除音乐文件
    function deleteMusic(filePath, dialog) {
        const formData = new FormData();
        formData.append('file', filePath);
        
        dialog.innerHTML = `
            <p><i class="fas fa-spinner fa-spin"></i> 正在删除文件...</p>
        `;
        
        fetch('script/delete_music.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                dialog.remove();
                showToast('音乐文件已成功删除', 'success');
                
                // 刷新音乐列表
                if (document.getElementById('musicSelectorModal').style.display === 'flex') {
                    showMusicSelector();
                }
                
                // 如果当前选中的是被删除的文件，清空选择
                if (document.getElementById('musicPath').value === filePath) {
                    document.getElementById('musicPath').value = '';
                }
            } else {
                dialog.remove();
                showToast('删除失败: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            dialog.remove();
            showToast('删除过程发生错误', 'error');
        });
    }

    // 确保在页面加载完成后初始化音量显示
    document.addEventListener('DOMContentLoaded', function() {
        const volumeInput = document.querySelector('input[name="background_music[volume]"]');
        if (volumeInput) {
            updateVolumeValue(volumeInput.value);
        }
        
        // 添加表单提交前验证
        document.querySelector('.settings-form').addEventListener('submit', function(e) {
            const interval = document.querySelector('input[name="autoplayInterval"]').value;
            if (interval < 100) {
                e.preventDefault();
                showToast('轮播间隔时间不能小于100毫秒', 'error');
                return false;
            }
            
            const perPage = document.querySelector('input[name="perPage"]').value;
            if (perPage < 1) {
                e.preventDefault();
                showToast('每页显示文件数不能小于1', 'error');
                return false;
            }
            
            // 显示保存中状态
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
            submitBtn.disabled = true;
            
            return true;
        });
    });
    </script>
</body>
</html>