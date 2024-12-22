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
    // 确保路径使用正斜杠，移除多余的斜杠
    $musicFile = str_replace('\\', '/', $musicFile);
    $musicFile = preg_replace('#/+#', '/', $musicFile);
    
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
    if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
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
        body {
            font-family: Arial, sans-serif;
            background: #eef;
            margin: 0;
            padding: 0;
        }
        header {
            background: #333;
            color: #fff;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header a {
            color: #fff;
            text-decoration: none;
            margin-left: 10px;
        }
        .wrapper {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .settings-form {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .message {
            background: #4CAF50;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="number"],
        input[type="radio"] {
            margin: 5px;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #45a049;
        }
        .settings-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .settings-card h3 {
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-content {
            padding: 0 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .switch-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .volume-control {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .volume-control input[type="range"] {
            flex: 1;
        }

        #volumeValue {
            min-width: 45px;
            text-align: right;
        }

        .input-group {
            display: flex;
            gap: 10px;
        }

        .input-group .form-control {
            flex: 1;
        }

        .btn-secondary {
            padding: 8px 12px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: #5a6268;
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
            border-radius: 24px;
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
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2196F3;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* 添加模态框样式 */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 5px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        #musicList {
            max-height: 300px;
            overflow-y: auto;
        }

        .music-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .music-item .music-name {
            flex-grow: 1;
            cursor: pointer;
        }

        .music-item .delete-btn {
            color: #dc3545;
            cursor: pointer;
            padding: 5px 10px;
            margin-left: 10px;
        }

        .delete-btn:hover {
            color: #c82333;
        }

        /* 确认对话框样式 */
        .confirm-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1100;
        }

        .confirm-dialog .buttons {
            margin-top: 15px;
            text-align: right;
        }

        .confirm-dialog button {
            margin-left: 10px;
            padding: 5px 15px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <header>
        <div>系统设置</div>
        <div>
            <a href="management.php">返回管理</a>
            <a href="index.php">返回首页</a>
        </div>
    </header>

    <div class="wrapper">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form class="settings-form" method="post">
            <div class="form-group">
                <label>轮播间隔时间</label>
                <input type="number" name="autoplayInterval" 
                       value="<?= htmlspecialchars($config['autoplayInterval']) ?>" 
                       min="100" step="100">
                <small>毫秒（对首页单文件无作用）</small>
            </div>

            <div class="form-group">
                <label>每页显示文件数</label>
                <input type="number" name="perPage" 
                       value="<?= htmlspecialchars($config['perPage']) ?>" 
                       min="1">
            </div>

            <div class="form-group">
                <label>视图模式</label>
                <div>
                    <label>
                        <input type="radio" name="viewMode" value="list" 
                               <?= $config['viewMode'] === 'list' ? 'checked' : '' ?>> 
                        列表视图
                    </label>
                    <label>
                        <input type="radio" name="viewMode" value="grid" 
                               <?= $config['viewMode'] === 'grid' ? 'checked' : '' ?>> 
                        网格视图
                    </label>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-music"></i> 背景音乐设置</h3>
                <div class="settings-content">
                    <div class="form-group">
                        <label class="switch-label">
                            <span>启用背景音乐</span>
                            <label class="switch">
                                <input type="checkbox" name="enableMusic" 
                                    <?= isset($config['background_music']['enabled']) && $config['background_music']['enabled'] ? 'checked' : '' ?>>
                                <span class="slider round"></span>
                            </label>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>音乐文件选择</label>
                        <div class="input-group">
                            <input type="text" name="background_music[file]" id="musicPath"
                                value="<?= htmlspecialchars($config['background_music']['file'] ?? '/assets/music/background.mp3') ?>" 
                                class="form-control">
                            <button type="button" class="btn btn-secondary" onclick="showMusicSelector()">
                                <i class="fas fa-music"></i> 选择音乐
                            </button>
                            <input type="file" id="musicFileInput" style="display: none" accept=".mp3,.wav,.ogg" onchange="handleMusicFileSelect(this)">
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('musicFileInput').click()">
                                <i class="fas fa-upload"></i> 上传
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>音量控制</label>
                        <div class="volume-control">
                            <input type="range" name="background_music[volume]" 
                                min="0" max="1" step="0.1" 
                                value="<?= htmlspecialchars($config['background_music']['volume'] ?? 0.5) ?>"
                                oninput="updateVolumeValue(this.value)">
                            <span id="volumeValue"><?= round(($config['background_music']['volume'] ?? 0.5) * 100) ?>%</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="switch-label">
                            <span>自动播放</span>
                            <label class="switch">
                                <input type="checkbox" name="background_music[autoplay]" 
                                    <?= isset($config['background_music']['autoplay']) && $config['background_music']['autoplay'] ? 'checked' : '' ?>>
                                <span class="slider round"></span>
                            </label>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="switch-label">
                            <span>循环播放</span>
                            <label class="switch">
                                <input type="checkbox" name="background_music[loop]" 
                                    <?= isset($config['background_music']['loop']) && $config['background_music']['loop'] ? 'checked' : '' ?>>
                                <span class="slider round"></span>
                            </label>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="switch-label">
                            <span>随机播放文件夹中的音乐</span>
                            <label class="switch">
                                <input type="checkbox" name="background_music[random]" 
                                    <?= isset($config['background_music']['random']) && $config['background_music']['random'] ? 'checked' : '' ?>>
                                <span class="slider round"></span>
                            </label>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit">保存设置</button>
        </form>
    </div>

    <div id="musicSelectorModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>选择音乐文件</h2>
            <div id="musicList"></div>
        </div>
    </div>

    <script>
    function updateVolumeValue(value) {
        document.getElementById('volumeValue').textContent = Math.round(value * 100) + '%';
    }

    function showMusicSelector() {
        fetch('script/get_music_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const musicList = document.getElementById('musicList');
                    musicList.innerHTML = '';
                    data.files.forEach(file => {
                        const div = document.createElement('div');
                        div.className = 'music-item';
                        
                        const nameSpan = document.createElement('span');
                        nameSpan.className = 'music-name';
                        nameSpan.textContent = file.name;
                        nameSpan.onclick = () => {
                            document.getElementById('musicPath').value = file.path;
                            document.getElementById('musicSelectorModal').style.display = 'none';
                        };
                        
                        const deleteBtn = document.createElement('span');
                        deleteBtn.className = 'delete-btn';
                        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                        deleteBtn.onclick = () => confirmDelete(file);
                        
                        div.appendChild(nameSpan);
                        div.appendChild(deleteBtn);
                        musicList.appendChild(div);
                    });
                    document.getElementById('musicSelectorModal').style.display = 'block';
                }
            });
    }

    document.querySelector('.close').onclick = function() {
        document.getElementById('musicSelectorModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('musicSelectorModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    function handleMusicFileSelect(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            
            // 检查文件类型
            if (!file.type.match('audio.*')) {
                alert('请选择音频文件（MP3、WAV 或 OGG）');
                return;
            }
            
            const formData = new FormData();
            formData.append('musicFile', file);
            
            // 显示上传中的提示
            const uploadStatus = document.createElement('div');
            uploadStatus.textContent = '文件上传中...';
            uploadStatus.style.color = 'blue';
            input.parentNode.appendChild(uploadStatus);
            
            fetch('script/upload_music.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('musicPath').value = data.filePath;
                    uploadStatus.textContent = '上传成功！';
                    uploadStatus.style.color = 'green';
                    setTimeout(() => uploadStatus.remove(), 3000);
                    
                    // 刷新音乐列表
                    showMusicSelector();
                } else {
                    uploadStatus.textContent = '上传失败: ' + data.message;
                    uploadStatus.style.color = 'red';
                    console.error('Upload debug info:', data.debug);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                uploadStatus.textContent = '上传出错，请查看控制台';
                uploadStatus.style.color = 'red';
            });
        }
    }

    // 添加确认删除对话框
    function confirmDelete(file) {
        const dialog = document.createElement('div');
        dialog.className = 'confirm-dialog';
        dialog.innerHTML = `
            <p>确定要删除音乐文件 "${file.name}" 吗？</p>
            <div class="buttons">
                <button onclick="this.parentElement.parentElement.remove()">取消</button>
                <button onclick="deleteMusic('${file.path}', this.parentElement.parentElement)">确定</button>
            </div>
        `;
        document.body.appendChild(dialog);
    }

    // 删除音乐文件
    function deleteMusic(filePath, dialog) {
        const formData = new FormData();
        formData.append('file', filePath);
        
        fetch('script/delete_music.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                dialog.remove();
                showMusicSelector(); // 刷新音乐列表
                
                // 如果当前选中的是被删除的文件，清空选择
                if (document.getElementById('musicPath').value === filePath) {
                    document.getElementById('musicPath').value = '';
                }
            } else {
                alert('删除失败: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('删除过程发生错误');
        });
    }
    </script>
</body>
</html>