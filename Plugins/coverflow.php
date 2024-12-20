<?php
header('Content-Type: text/html; charset=utf-8');

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$configFile = __DIR__ . '/../config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

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
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coverflow 展示</title>
    <link href="../favicon.ico" type="image/x-icon" rel="icon">
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
            background: rgba(0, 0, 0, 0.8);
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
            perspective: 2000px;
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
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
            width: min(800px, 55vw);
            height: min(450px, 31vw);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            transform-origin: center center;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.6);
            will-change: transform, opacity;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }

        .coverflow-item img,
        .coverflow-item video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
        }

        .coverflow-item::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(
                to bottom,
                rgba(255, 255, 255, 0.2) 0%,
                rgba(255, 255, 255, 0.1) 30%,
                rgba(0, 0, 0, 0.1) 70%,
                rgba(0, 0, 0, 0.2) 100%
            );
            pointer-events: none;
        }

        .controls {
            position: fixed;
            bottom: 50px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            display: flex;
            gap: 25px;
            background: rgba(0, 0, 0, 0.7);
            padding: 15px 25px;
            border-radius: 30px;
            backdrop-filter: blur(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .controls.hidden {
            opacity: 0;
            transform: translate(-50%, 100%);
            pointer-events: none;
        }

        .control-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }

        .filename {
            position: fixed;
            bottom: 140px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            padding: 12px 25px;
            border-radius: 20px;
            font-size: 15px;
            color: #fff;
            z-index: 999;
            backdrop-filter: blur(10px);
            white-space: nowrap;
            max-width: 80vw;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .filename.hidden {
            opacity: 0;
            transform: translate(-50%, 100%);
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .coverflow-item {
                width: min(500px, 85vw);
                height: min(281.25px, 48vw);
            }

            .control-btn {
                font-size: 20px;
                width: 40px;
                height: 40px;
            }

            .controls {
                bottom: 40px;
                padding: 12px 20px;
            }

            .filename {
                bottom: 120px;
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
            background: rgba(255, 255, 255, 0.1);
            padding: 12px 20px;
            border-radius: 6px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 0.3px;
            backdrop-filter: blur(5px);
        }

        .nav-bar a:hover, .nav-bar button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        .nav-bar a.active {
            background: rgba(255, 255, 255, 0.2);
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
                font-size: 14px;
            }
        }

        /* 竖屏适配 */
        @media (orientation: portrait) {
            .coverflow-item {
                width: min(600px, 85vw);
                height: min(337.5px, 48vw);
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="nav-bar">
            <a href="../index.php">幻灯片</a>
            <a href="3d-gallery.php">3D相册</a>
            <a href="#" class="active">Coverflow</a>
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

    <script>
        const files = <?php echo json_encode($enabledFiles); ?>;
        const coverflowContainer = document.getElementById('coverflow');
        const filenameDisplay = document.getElementById('filename');
        let currentIndex = 0;
        let autoplayInterval = null;
        const AUTOPLAY_DELAY = 3000;
        let isPlaying = false;

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
                } else {
                    const img = document.createElement('img');
                    img.src = '../' + file.path;
                    img.alt = file.name;
                    item.appendChild(img);
                }

                item.addEventListener('click', () => {
                    if (index !== currentIndex) {
                        currentIndex = index;
                        updateCoverflow();
                    }
                });

                coverflowContainer.appendChild(item);
            });
            
            // 确保初始显示文件名
            if (files.length > 0) {
                filenameDisplay.textContent = files[0].name;
            }
        }

        // 更新 Coverflow 显示
        function updateCoverflow() {
            const items = document.querySelectorAll('.coverflow-item');
            const centerOffset = Math.floor(items.length / 2);
            const viewportWidth = window.innerWidth;
            
            items.forEach((item, index) => {
                const offset = index - currentIndex;
                const absOffset = Math.abs(offset);
                const zIndex = items.length - absOffset;
                const opacity = Math.max(1 - absOffset * 0.25, 0.3);
                
                // 根据视口宽度调整间距
                const spacing = viewportWidth < 768 ? 250 : 500;
                let xTranslate = offset * spacing;
                let zTranslate = -absOffset * 500;
                let yRotate = offset < 0 ? 45 : -45;
                
                if (offset === 0) {
                    yRotate = 0;
                    zTranslate = 200;
                }
                
                item.style.transform = `
                    translateX(${xTranslate}px)
                    translateZ(${zTranslate}px)
                    rotateY(${yRotate}deg)
                    scale(${offset === 0 ? 1.15 : 0.7})
                `;
                item.style.zIndex = zIndex;
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
                filenameDisplay.style.display = 'block'; // 确保文件名可见
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
                updateCoverflow();
            }, AUTOPLAY_DELAY);
        }

        function stopAutoplay() {
            isPlaying = false;
            playPauseBtn.innerHTML = '▶';
            clearInterval(autoplayInterval);
        }

        // 初始化
        createCoverflowItems();
        updateCoverflow();

        // 事件监听
        document.getElementById('prevBtn').addEventListener('click', () => {
            stopAutoplay();
            currentIndex = (currentIndex - 1 + files.length) % files.length;
            updateCoverflow();
        });

        document.getElementById('nextBtn').addEventListener('click', () => {
            stopAutoplay();
            currentIndex = (currentIndex + 1) % files.length;
            updateCoverflow();
        });

        const playPauseBtn = document.getElementById('playPauseBtn');
        playPauseBtn.addEventListener('click', toggleAutoplay);

        // 键盘控制
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowLeft':
                    stopAutoplay();
                    currentIndex = (currentIndex - 1 + files.length) % files.length;
                    updateCoverflow();
                    break;
                case 'ArrowRight':
                    stopAutoplay();
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
                    // 向右滑动
                    currentIndex = (currentIndex - 1 + files.length) % files.length;
                } else {
                    // 向左滑动
                    currentIndex = (currentIndex + 1) % files.length;
                }
                stopAutoplay();
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
            stopAutoplay();
            updateCoverflow();
        }, { passive: true });

        // 添加窗口大小变化监听
        window.addEventListener('resize', () => {
            updateCoverflow();
        });

        // 添加自动隐藏功能
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

            // 在键盘操作时显示UI
            document.addEventListener('keydown', () => {
                showUI();
            });

            // 在滚轮操作时显示UI
            document.addEventListener('wheel', () => {
                showUI();
            });
        });
    </script>
</body>
</html> 