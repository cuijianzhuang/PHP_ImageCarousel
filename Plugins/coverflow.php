<?php
// 设置页面缓存控制
$seconds_to_cache = 3600; // 缓存时间，例如这里设置为1小时
$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . ' GMT';
header("Expires: $ts");
header("Pragma: cache");
header("Cache-Control: max-age=$seconds_to_cache");

// 引入音乐播放器组件
require_once dirname(__DIR__) . '/Plugins/music_player.php';

$configFile = dirname(__DIR__) . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];

// 移除登录验证相关代码
// session_start();
// if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
//     header('Location: ../login.php');
//     exit;
// }

// 获取启用的文件列表
$directory = __DIR__ . '/../assets/';
$enabledFiles = [];
foreach ($config['enabledFiles'] as $file => $enabled) {
    if ($enabled) {
        $filePath = $directory . 'showimg/' . $file;
        if (file_exists($filePath)) {
            $enabledFiles[] = [
                'name' => $file,
                'path' => 'assets/showimg/' . $file,
                'type' => strtolower(pathinfo($file, PATHINFO_EXTENSION))
            ];
        }
    }
}

// 随机打乱文件数组
shuffle($enabledFiles);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coverflow 展示</title>
    <link href="../favicon.ico" type="image/x-icon" rel="icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #000;
            color: #fff;
            font-family: Arial, sans-serif;
            overflow: hidden;
            height: 100vh;
            width: 100vw;
        }

        header {
            position: fixed;
            top: 20px;
            left: 0;
            right: 0;
            background: transparent;
            padding: 15px;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        header.hidden {
            opacity: 0;
            transform: translateY(-100%);
            pointer-events: none;
        }

        header a {
            color: #fff;
            text-decoration: none;
            margin-left: 15px;
            padding: 8px 15px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.1);
            transition: background 0.3s;
        }

        header a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .coverflow-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            perspective: 2500px;
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            padding: 20px;
            box-sizing: border-box;
        }

        .coverflow {
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .coverflow-item {
            position: absolute;
            width: min(1200px, 65vw);
            height: min(800px, 75vh);
            min-width: 280px;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            transform-origin: center center;
            will-change: transform, opacity;
            background: none;
            overflow: visible;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            box-sizing: border-box;
        }

        .coverflow-item img,
        .coverflow-item video {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            background: none;
            display: block;
            border-radius: 0;
        }

        .coverflow-item::before,
        .coverflow-item::after {
            display: none;
        }

        .controls {
            position: fixed;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            display: flex;
            gap: 15px;
            background: rgba(0, 0, 0, 0.5);
            padding: 12px 20px;
            border-radius: 25px;
            backdrop-filter: blur(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .controls.hidden {
            opacity: 0;
            transform: translate(-50%, 100%);
            pointer-events: none;
        }

        .control-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .filename {
            position: fixed;
            bottom: 110px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.5);
            padding: 10px 20px;
            border-radius: 15px;
            font-size: 14px;
            color: #fff;
            z-index: 999;
            backdrop-filter: blur(10px);
            white-space: nowrap;
            max-width: 80vw;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: opacity 0.3s ease, transform 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .filename.hidden {
            opacity: 0;
            transform: translate(-50%, 100%);
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .coverflow-item {
                width: min(900px, 85vw);
                height: min(600px, 70vh);
                min-width: 250px;
                padding: 0;
            }

            .control-btn {
                font-size: 20px;
                width: 40px;
                height: 40px;
            }

            .controls {
                bottom: 20px;
                padding: 10px 15px;
            }

            .filename {
                bottom: 80px;
                font-size: 13px;
                padding: 8px 16px;
            }
        }

        /* 导航栏样式 */
        .nav-bar {
            display: flex;
            gap: var(--nav-gap, 10px);
            justify-content: center;
            padding: 10px;
        }

        .nav-bar a, .nav-bar button {
            color: #fff;
            text-decoration: none;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px 18px;
            border-radius: 20px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 0.3px;
            backdrop-filter: blur(10px);
            margin: 0 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .nav-bar a:hover, .nav-bar button:hover {
            background: rgba(0, 0, 0, 0.7);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .nav-bar a.active {
            background: rgba(0, 0, 0, 0.7);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        /* 主题切换相关样式 */
        :root {
            --bg-color: #000;
            --text-color: #fff;
        }

        [data-theme="light"] {
            --bg-color: #f0f0f0;
            --text-color: #333;
        }

        body {
            background: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease;
        }

        @media (max-width: 768px) {
            .nav-bar {
                padding: 5px;
                gap: 5px;
            }

            .nav-bar a, .nav-bar button {
                padding: 8px 12px;
                font-size: 13px;
            }
        }

        /* 竖屏适配 */
        @media (orientation: portrait) {
            .coverflow-item {
                width: min(900px, 90vw);
                height: min(600px, 65vh);
                min-width: 250px;
            }
        }

        /* 添加放大查看样式 */
        .fullscreen-view {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .fullscreen-view.active {
            opacity: 1;
        }
        
        .fullscreen-content {
            max-width: 95vw;
            max-height: 95vh;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            box-sizing: border-box;
        }
        
        .fullscreen-content img,
        .fullscreen-content video {
            max-width: calc(100vw - 80px);
            max-height: calc(100vh - 80px);
            object-fit: contain;
            border-radius: 4px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            width: auto;
            height: auto;
        }
        
        .fullscreen-close {
            position: absolute;
            top: 0;
            right: 0;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            color: #fff;
            font-size: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 2001;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z'/%3E%3C/svg%3E");
            background-size: 24px;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .fullscreen-close:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        /* 移动端适配 */
        @media (max-width: 768px) {
            .fullscreen-content {
                padding: 20px;
            }
            
            .fullscreen-content img,
            .fullscreen-content video {
                max-width: calc(100vw - 40px);
                max-height: calc(100vh - 40px);
            }
            
            .fullscreen-close {
                width: 36px;
                height: 36px;
                background-size: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="nav-bar">
            <a href="../index.php">幻灯片</a>
            <a href="3d-gallery.php">3D相册</a>
            <button onclick="toggleTheme()">切换主题</button>
            <a href="../login.php">文件管理</a>
        </div>
    </header>

    <div class="coverflow-container">
        <div class="coverflow" id="coverflow"></div>
    </div>

    <div class="filename" id="filename"></div>

    <div class="controls">
        <button class="control-btn" id="prevBtn">⟨</button>
        <button class="control-btn" id="playPauseBtn">▶</button>
        <button class="control-btn" id="nextBtn">⟩</button>
    </div>

    <div class="fullscreen-view" id="fullscreenView">
        <div class="fullscreen-content">
            <button class="fullscreen-close" id="fullscreenClose" aria-label="关闭"></button>
            <div id="fullscreenContainer"></div>
        </div>
    </div>

    <script>
        const files = <?php echo json_encode($enabledFiles); ?>;
        const coverflowContainer = document.getElementById('coverflow');
        const filenameDisplay = document.getElementById('filename');
        let currentIndex = 0;
        let autoplayInterval = null;
        const AUTOPLAY_DELAY = 3000;
        let isPlaying = true;

        // 创建 Coverflow 项目
        function createCoverflowItems() {
            files.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'coverflow-item';
                
                if (file.type === 'mp4' || file.type === 'webm' || file.type === 'ogg') {
                    const video = document.createElement('video');
                    video.src = '../' + file.path;
                    video.muted = true;
                    video.loop = true;
                    item.appendChild(video);
                    
                    // 为视频添加点击事件
                    video.addEventListener('click', (e) => {
                        if (index === currentIndex) {
                            e.stopPropagation();
                            showFullscreen(file);
                        }
                    });
                } else {
                    const img = document.createElement('img');
                    img.src = '../' + file.path;
                    img.alt = file.name;
                    item.appendChild(img);
                    
                    // 为图片添加点击事件
                    img.addEventListener('click', (e) => {
                        if (index === currentIndex) {
                            e.stopPropagation();
                            showFullscreen(file);
                        }
                    });
                }

                item.addEventListener('click', () => {
                    if (index !== currentIndex) {
                        currentIndex = index;
                        updateCoverflow();
                    }
                });

                coverflowContainer.appendChild(item);
            });
            
            if (files.length > 0) {
                filenameDisplay.textContent = files[0].name;
            }
        }

        // 更新 Coverflow 显示
        function updateCoverflow() {
            const items = document.querySelectorAll('.coverflow-item');
            const viewportWidth = window.innerWidth;
            const isMobile = viewportWidth < 768;
            
            items.forEach((item, index) => {
                const offset = index - currentIndex;
                const absOffset = Math.abs(offset);
                
                // 计算基础参数
                const baseSpacing = isMobile ? 200 : 400;
                const baseScale = isMobile ? 0.8 : 0.85;
                const maxRotation = 60;
                
                // 计算变换参数
                let xTranslate = offset * baseSpacing;
                let zTranslate = -absOffset * 300;
                let scale = offset === 0 ? 1 : Math.max(baseScale - absOffset * 0.1, 0.6);
                let yRotate = offset === 0 ? 0 : (offset < 0 ? maxRotation : -maxRotation);
                let opacity = Math.max(1 - absOffset * 0.2, 0.3);
                
                // 中心图片特殊处理
                if (offset === 0) {
                    zTranslate = 0;
                    scale = 1;
                }
                
                // 应用变换
                item.style.transform = `
                    translateX(${xTranslate}px)
                    translateZ(${zTranslate}px)
                    rotateY(${yRotate}deg)
                    scale(${scale})
                `;
                
                // 设置层级和透明度
                item.style.zIndex = items.length - absOffset;
                item.style.opacity = opacity;
                
                // 处理视频播放
                const video = item.querySelector('video');
                if (video) {
                    if (index === currentIndex) {
                        video.play().catch(() => {});
                    } else {
                        video.pause();
                        video.currentTime = 0;
                    }
                }
            });

            // 更新文件名显示
            if (files[currentIndex]) {
                filenameDisplay.textContent = files[currentIndex].name;
                filenameDisplay.style.display = 'block';
            }
        }

        // 自动播放控制
        function toggleAutoplay() {
            if (isPlaying) {
                stopAutoplay();
            } else {
                startAutoplay();
            }
        }

        function startAutoplay() {
            isPlaying = true;
            playPauseBtn.innerHTML = '⏸';
            autoplayInterval = setInterval(() => {
                currentIndex = (currentIndex + 1) % files.length;
                
                // 当播放到最后一张时，平滑重新随机排序
                if (currentIndex === 0) {
                    // 淡出当前项目
                    const items = document.querySelectorAll('.coverflow-item');
                    items.forEach((item, index) => {
                        // 根据索引添加不同的延迟，创建波浪效果
                        item.style.transition = `opacity 0.5s ease ${index * 50}ms, transform 0.5s ease ${index * 50}ms`;
                        item.style.opacity = '0';
                        
                        // 添加更自然的位移和缩放效果
                        const direction = index % 2 === 0 ? 1 : -1;
                        item.style.transform = `translateX(${100 * direction}%) scale(0.5) rotate(${10 * direction}deg)`;
                    });

                    // 延迟重建 coverflow
                    setTimeout(() => {
                        // 重新随机排序文件数组
                        shuffleArray(files);
                        
                        // 重建 coverflow 项目
                        coverflowContainer.innerHTML = '';
                        createCoverflowItems();
                        
                        // 立即更新并淡入
                        const newItems = document.querySelectorAll('.coverflow-item');
                        
                        // 初始设置新项目的起始位置和透明度
                        newItems.forEach((item, index) => {
                            // 添加交错效果
                            const direction = index % 2 === 0 ? 1 : -1;
                            item.style.transition = 'none';
                            item.style.opacity = '0';
                            item.style.transform = `translateX(${100 * direction}%) scale(0.5) rotate(${10 * direction}deg)`;
                        });

                        // 强制重绘
                        void coverflowContainer.offsetWidth;

                        // 应用过渡效果，添加交错和旋转
                        newItems.forEach((item, index) => {
                            const direction = index % 2 === 0 ? 1 : -1;
                            item.style.transition = `all 0.6s cubic-bezier(0.4, 0, 0.2, 1) ${index * 100}ms`;
                            item.style.opacity = '1';
                            item.style.transform = 'translateX(0) scale(1) rotate(0deg)';
                        });

                        // 更新 Coverflow
                        updateCoverflow();
                    }, 500);
                } else {
                    updateCoverflow();
                }
            }, AUTOPLAY_DELAY);
        }

        function stopAutoplay() {
            isPlaying = false;
            playPauseBtn.innerHTML = '▶';
            clearInterval(autoplayInterval);
        }

        // 添加数组随机排序函数
        function shuffleArray(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
            return array;
        }

        // 初始化
        createCoverflowItems();
        updateCoverflow();
        const playPauseBtn = document.getElementById('playPauseBtn');
        playPauseBtn.innerHTML = '⏸';
        startAutoplay();

        // 事件监听
        document.getElementById('prevBtn').addEventListener('click', () => {
            currentIndex = (currentIndex - 1 + files.length) % files.length;
            updateCoverflow();
        });

        document.getElementById('nextBtn').addEventListener('click', () => {
            currentIndex = (currentIndex + 1) % files.length;
            updateCoverflow();
        });

        playPauseBtn.addEventListener('click', toggleAutoplay);

        // 键盘控制
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowLeft':
                    currentIndex = (currentIndex - 1 + files.length) % files.length;
                    updateCoverflow();
                    break;
                case 'ArrowRight':
                    currentIndex = (currentIndex + 1) % files.length;
                    updateCoverflow();
                    break;
                case ' ':
                    e.preventDefault();
                    toggleAutoplay();
                    break;
            }
        });

        // 添加触摸滑动支持
        let touchStartX = 0;
        let touchEndX = 0;

        document.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, false);

        document.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, false);

        function handleSwipe() {
            const swipeThreshold = 50;
            const diff = touchEndX - touchStartX;

            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    currentIndex = (currentIndex - 1 + files.length) % files.length;
                } else {
                    currentIndex = (currentIndex + 1) % files.length;
                }
                updateCoverflow();
            }
        }

        // 添加主题切换功能
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }

        // 加载保存的主题
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.body.setAttribute('data-theme', savedTheme);
            }
        });

        // 添加鼠标滚轮控制
        document.addEventListener('wheel', (e) => {
            if (e.deltaY > 0) {
                // 向下滚动
                currentIndex = (currentIndex + 1) % files.length;
            } else {
                // 向上滚动
                currentIndex = (currentIndex - 1 + files.length) % files.length;
            }
            
            // 保持当前播放状态，不强制停止自动播放
            updateCoverflow();
        }, { passive: true });

        // 添加窗口大小变化监听
        window.addEventListener('resize', () => {
            updateCoverflow();
        });

        // 添加自隐藏功能
        document.addEventListener('DOMContentLoaded', () => {
            const header = document.querySelector('header');
            const controls = document.querySelector('.controls');
            const filename = document.querySelector('.filename');
            let hideTimeout;

            function showUI() {
                header.classList.remove('hidden');
                controls.classList.remove('hidden');
                if (files.length > 0) {  // 只在有文件时显示文件名
                    filename.classList.remove('hidden');
                    filename.style.display = 'block';
                }
                
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(() => {
                    if (!isMouseOverUI) {
                        header.classList.add('hidden');
                        controls.classList.add('hidden');
                        filename.classList.add('hidden');
                    }
                }, 5000);
            }

            let isMouseOverUI = false;

            // 监听鼠标移动
            document.addEventListener('mousemove', () => {
                showUI();
            });

            // 监听触摸事件
            document.addEventListener('touchstart', () => {
                showUI();
            });

            // 监听鼠标悬停在UI元素上的情况
            [header, controls, filename].forEach(element => {
                element.addEventListener('mouseenter', () => {
                    isMouseOverUI = true;
                    showUI();
                });

                element.addEventListener('mouseleave', () => {
                    isMouseOverUI = false;
                    showUI();
                });
            });

            // 初始显示UI和文件名
            if (files.length > 0) {
                filenameDisplay.textContent = files[0].name;
                filenameDisplay.style.display = 'block';
            }
            showUI();

            // 在点击控制按钮时重置计时器
            document.querySelectorAll('.control-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    showUI();
                });
            });

            // 键盘操作时显示UI
            document.addEventListener('keydown', () => {
                showUI();
            });

            // 在滚轮操作时显示UI
            document.addEventListener('wheel', () => {
                showUI();
            });
        });

        // 添加放大查看相关函数
        const fullscreenView = document.getElementById('fullscreenView');
        const fullscreenContainer = document.getElementById('fullscreenContainer');
        const fullscreenClose = document.getElementById('fullscreenClose');
        
        function showFullscreen(file) {
            fullscreenContainer.innerHTML = '';
            
            if (file.type === 'mp4' || file.type === 'webm' || file.type === 'ogg') {
                const video = document.createElement('video');
                video.src = '../' + file.path;
                video.controls = true;
                video.autoplay = true;
                // 添加加载事件以确保正确计算尺寸
                video.addEventListener('loadedmetadata', () => {
                    adjustContentSize(video);
                });
                fullscreenContainer.appendChild(video);
            } else {
                const img = document.createElement('img');
                img.src = '../' + file.path;
                img.alt = file.name;
                // 添加加载事件以确保正确计算尺寸
                img.addEventListener('load', () => {
                    adjustContentSize(img);
                });
                fullscreenContainer.appendChild(img);
            }
            
            fullscreenView.style.display = 'flex';
            setTimeout(() => fullscreenView.classList.add('active'), 10);
            
            // 暂停自动播放
            if (isPlaying) {
                stopAutoplay();
            }
        }
        
        function closeFullscreen() {
            fullscreenView.classList.remove('active');
            setTimeout(() => {
                fullscreenView.style.display = 'none';
                fullscreenContainer.innerHTML = '';
            }, 300);
            
            // 恢复自动播放
            if (!isPlaying) {
                startAutoplay();
            }
        }
        
        // 添加关闭事件监听
        fullscreenClose.addEventListener('click', closeFullscreen);
        fullscreenView.addEventListener('click', (e) => {
            if (e.target === fullscreenView) {
                closeFullscreen();
            }
        });
        
        // 添加键盘事件支持
        document.addEventListener('keydown', (e) => {
            if (fullscreenView.style.display === 'flex') {
                if (e.key === 'Escape') {
                    closeFullscreen();
                }
            }
        });

        // 添加内容尺寸调整函数
        function adjustContentSize(element) {
            const viewport = {
                width: window.innerWidth - (window.innerWidth > 768 ? 80 : 40),
                height: window.innerHeight - (window.innerWidth > 768 ? 80 : 40)
            };
            
            const ratio = element.naturalWidth ? 
                element.naturalWidth / element.naturalHeight :
                element.videoWidth / element.videoHeight;
            
            if (ratio > viewport.width / viewport.height) {
                // 如果内容更宽，以宽度为基准
                element.style.width = viewport.width + 'px';
                element.style.height = (viewport.width / ratio) + 'px';
            } else {
                // 如果内容更高，以高度为基准
                element.style.height = viewport.height + 'px';
                element.style.width = (viewport.height * ratio) + 'px';
            }
        }
        
        // 添加窗口大小变化监听
        window.addEventListener('resize', () => {
            const content = fullscreenContainer.querySelector('img, video');
            if (content && fullscreenView.style.display === 'flex') {
                adjustContentSize(content);
            }
        });
    </script>

    <!-- 在 body 结束标签前添加音乐播放器 -->
    <?php
    $musicPlayer = new MusicPlayer($config);
    echo $musicPlayer->render();
    ?>
</body>
</html> 