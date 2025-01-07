<?php
// 设置页面缓存控制
$seconds_to_cache = 3600; // 缓存时间，例如这里设置为1小时
$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . ' GMT';
header("Expires: $ts");
header("Pragma: cache");
header("Cache-Control: max-age=$seconds_to_cache");

// 引入音乐播放器组件
require_once 'Plugins/music_player.php';

$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];
$autoplayInterval = $config['autoplayInterval'] ?? 5000; // 默认5秒
$enabledFiles = $config['enabledFiles'] ?? [];

$directory = 'assets/showimg/';
$allowedExtensions = [
    // Image formats
    'jpeg','jpg','png','gif','webp','bmp','tiff','tif','heic','heif',

    // Video formats
    'mp4','avi','mov','wmv','flv','mkv','webm','ogg','m4v','mpeg','mpg', '3gp'
];
$enabledImages = $config['enabledFiles']['images'] ?? [];
$enabledVideos = $config['enabledFiles']['videos'] ?? [];

// 定义允许的文件扩展名
$imageExtensions = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'heic', 'heif'];
$videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'ogg', 'm4v', 'mpeg', 'mpg', '3gp'];

$slides = [];
if (is_dir($directory)) {
    $files = array_diff(scandir($directory), ['.', '..']);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        // 处理图片文件
        if (in_array($ext, $imageExtensions)) {
            $enabled = isset($enabledImages[$file]) ? $enabledImages[$file] : true;
            if ($enabled) {
                $slides[] = [
                    'type' => 'image',
                    'file' => $file,
                    'path' => $directory . $file
                ];
            }
        }
        // 处理视频文件
        elseif (in_array($ext, $videoExtensions)) {
            $enabled = isset($enabledVideos[$file]) ? $enabledVideos[$file] : true;
            if ($enabled) {
                $slides[] = [
                    'type' => 'video',
                    'file' => $file,
                    'path' => $directory . $file
                ];
            }
        }
    }
    
    // 随机打乱幻灯片顺序
    shuffle($slides);
}

// 添加文件缓存机制
function getCachedFiles($directory) {
    $cacheFile = __DIR__ . '/cache/files.json';
    $cacheTime = 300; // 5分钟缓存

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    // 原有的文件扫描逻辑
    $files = [];
    // ... 扫描目录代码 ...

    // 保存缓存
    if (!is_dir(__DIR__ . '/cache')) {
        mkdir(__DIR__ . '/cache');
    }
    file_put_contents($cacheFile, json_encode($files));

    return $files;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<link href="./favicon.ico" type="image/x-icon" rel="icon">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>首页展示</title>
    <style>
        :root {
            --bg-color: #f0f0f0;
            --text-color: #333;
            --control-bg: rgba(0, 0, 0, 0.5);
            --control-color: #fff;
            --nav-gap: 10px;  /* 添加导航按钮间距变量 */
        }

        [data-theme="dark"] {
            --bg-color: #222;
            --text-color: #fff;
            --control-bg: rgba(255, 255, 255, 0.3);
            --control-color: #fff;
        }

        body {
            margin:0; padding:0; height:100%; overflow:hidden; font-family: Arial, sans-serif;
            display:flex; flex-direction:column;
            background: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            animation: fadeInScale 0.3s ease-out;
        }
        nav {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            display: flex;
            gap: var(--nav-gap);
            opacity: 1;
            transition: opacity 0.5s;
            background: rgba(0, 0, 0, 0.2);  /* 添加半透明背景 */
            padding: 10px;
            border-radius: 8px;
            backdrop-filter: blur(10px);  /* 添加毛玻璃效果 */
        }

        nav.hidden {
            opacity: 0;
            pointer-events: none;
        }

        nav a, nav button {
            color: var(--text-color);
            text-decoration: none;
            background: var(--control-bg);
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        nav a:hover, nav button:hover {
            background: var(--control-bg);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        .carousel {
            position: relative;
            width:100vw;
            height:100vh;
            overflow:hidden;
            display:flex; justify-content:center; align-items:center;
        }
        .carousel-track {
            display: flex; transition: transform 0.5s ease-in-out;
            height:100%; width:100%;
        }
        .carousel-item {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .carousel-item.active {
            opacity: 1;
        }
        .carousel-item img, .carousel-item video {
            width:100%; height:100%; object-fit: contain;
            background: #000; /* 黑色背景填充空白区域 */
        }
        .controls {
            position:absolute; bottom:30px; left:50%; transform:translateX(-50%);
            display:flex; gap:20px; opacity:1; transition: opacity 0.5s;
            z-index:1000;
        }
        .controls button {
            background: var(--control-bg);
            color: var(--control-color);
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            letter-spacing: 0.3px;
        }
        .controls button:hover {
            background: var(--control-bg);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        .controls.hidden {
            opacity:0;
            pointer-events: none;
        }
        .no-file {
            font-size:24px; color:#333;
        }

        /* 响应式调整 */
        @media(max-width:768px) {
            nav a, nav button, .controls button {
                padding: 10px 16px;
                font-size: 14px;
            }
        }

        /* 添加过渡动画效果 */
        .fade-transition {
            opacity: 0;
            transform: scale(1);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .fade-transition.active {
            opacity: 1;
            transform: scale(1);
        }

        /* 缩放 */
        .zoom-transition {
            opacity: 0;
            transform: scale(0.3);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .zoom-transition.active {
            opacity: 1;
            transform: scale(1);
        }

        /* 左滑入 */
        .slide-left-transition {
            opacity: 0;
            transform: translateX(100%);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .slide-left-transition.active {
            opacity: 1;
            transform: translateX(0);
        }

        /* 右滑入 */
        .slide-right-transition {
            opacity: 0;
            transform: translateX(-100%);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .slide-right-transition.active {
            opacity: 1;
            transform: translateX(0);
        }

        /* 上滑入 */
        .slide-up-transition {
            opacity: 0;
            transform: translateY(100%);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .slide-up-transition.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* 下滑入 */
        .slide-down-transition {
            opacity: 0;
            transform: translateY(-100%);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .slide-down-transition.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* 旋转缩放 */
        .rotate-scale-transition {
            opacity: 0;
            transform: rotate(180deg) scale(0.3);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .rotate-scale-transition.active {
            opacity: 1;
            transform: rotate(0) scale(1);
        }

        /* 翻转 */
        .flip-transition {
            opacity: 0;
            transform: perspective(1000px) rotateY(90deg);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .flip-transition.active {
            opacity: 1;
            transform: perspective(1000px) rotateY(0);
        }

        /* 添加页面切换动画 */
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.98);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* 添加导航栏按钮激活状态 */
        nav a.active {
            background: var(--control-bg);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* 添加键盘操作提示 */
        .carousel-nav button::after {
            position: absolute;
            bottom: -25px;
            font-size: 12px;
            color: var(--text-color);
            opacity: 0.7;
        }

        .carousel-nav button:first-child::after {
            /*content: '← 左方向键';*/
        }

        .carousel-nav button:last-child::after {
            /*content: '右方向键 →';*/
        }

        /* 添加新的过渡效果 */
        
        /* 缩放淡入 */
        .zoom-fade-transition {
            opacity: 0;
            transform: scale(1.5);
            filter: brightness(2);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .zoom-fade-transition.active {
            opacity: 1;
            transform: scale(1);
            filter: brightness(1);
        }

        /* 旋转淡入 */
        .rotate-fade-transition {
            opacity: 0;
            transform: rotate(-45deg) scale(0.5);
            filter: blur(10px);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .rotate-fade-transition.active {
            opacity: 1;
            transform: rotate(0) scale(1);
            filter: blur(0);
        }

        /* 模糊淡入 */
        .blur-fade-transition {
            opacity: 0;
            filter: blur(50px) brightness(2);
            transform: scale(0.9);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .blur-fade-transition.active {
            opacity: 1;
            filter: blur(0) brightness(1);
            transform: scale(1);
        }

        /* 摆动效果 */
        .swing-transition {
            opacity: 0;
            transform: rotate(15deg) scale(0.7);
            transform-origin: top center;
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .swing-transition.active {
            opacity: 1;
            transform: rotate(0) scale(1);
        }

        /* 对角线滑入 */
        .diagonal-transition {
            opacity: 0;
            transform: translate(-100%, 100%) rotate(-45deg);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .diagonal-transition.active {
            opacity: 1;
            transform: translate(0, 0) rotate(0);
        }

        /* 螺旋效果 */
        .spiral-transition {
            opacity: 0;
            transform: rotate(360deg) scale(0) translate(100px, 100px);
            filter: hue-rotate(90deg);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .spiral-transition.active {
            opacity: 1;
            transform: rotate(0) scale(1) translate(0, 0);
            filter: hue-rotate(0);
        }

        /* 波纹效果 */
        .ripple-transition {
            opacity: 0;
            transform: scale(0.3);
            filter: blur(20px);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .ripple-transition.active {
            opacity: 1;
            transform: scale(1);
            filter: blur(0);
        }

        /* 3D翻转效果 */
        .flip-3d-transition {
            opacity: 0;
            transform: perspective(1000px) rotateY(-90deg) scale(0.8);
            transform-origin: center;
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .flip-3d-transition.active {
            opacity: 1;
            transform: perspective(1000px) rotateY(0) scale(1);
        }

        /* 弹性缩放 */
        .elastic-transition {
            opacity: 0;
            transform: scale(1.5);
            transition: all 1.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .elastic-transition.active {
            opacity: 1;
            transform: scale(1);
        }

        /* 折叠效果 */
        .fold-transition {
            opacity: 0;
            transform-origin: top;
            transform: perspective(1000px) rotateX(-90deg);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .fold-transition.active {
            opacity: 1;
            transform: perspective(1000px) rotateX(0);
        }

        /* 棋盘效果 */
        .checkerboard-transition {
            opacity: 0;
            clip-path: inset(0 0 100% 0);
            filter: saturate(0);
            transition: all 2s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .checkerboard-transition.active {
            opacity: 1;
            clip-path: inset(0 0 0 0);
            filter: saturate(1);
        }

        /* 百叶窗效果 */
        .blinds-transition {
            opacity: 0;
            transform: perspective(1000px);
            transform-origin: 50% 0;
            transform: rotateX(-90deg);
            transition: all 1.5s cubic-bezier(0.4, 0, 0.2, 1.2);
        }
        .blinds-transition.active {
            opacity: 1;
            transform: rotateX(0);
        }
    </style>
</head>
<body>
<nav>
    <a href="Plugins/3d-gallery.php">3D相册</a>
    <a href="Plugins/coverflow.php">Coverflow</a>
    <button onclick="toggleTheme()">切换主题</button>
    <a href="login.php">文件管理</a>
</nav>

<?php if (!empty($slides)): ?>
    <div class="carousel">
        <div class="carousel-track">
            <?php foreach ($slides as $slide): ?>
                <div class="carousel-item">
                    <?php if ($slide['type'] === 'video'): ?>
                        <video src="<?= htmlspecialchars($slide['path']) ?>" 
                               controls 
                               muted 
                               preload="metadata"
                               playsinline
                               loop>
                        </video>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($slide['path']) ?>" alt="">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="controls" id="controls">
            <button id="pauseBtn">暂停</button>
        </div>
    </div>
<?php else: ?>
    <div class="carousel">
        <p class="no-file">暂无可显示的图片或视频。</p>
    </div>
<?php endif; ?>

<script>
    function toggleTheme() {
        const body = document.body;
        const currentTheme = body.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        body.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.body.setAttribute('data-theme', savedTheme);
        }

        // 添加导航栏自动隐藏功能
        const nav = document.querySelector('nav');
        let navTimeout;

        const showNav = () => {
            nav.classList.remove('hidden');

            if (navTimeout) {
                clearTimeout(navTimeout);
            }

            navTimeout = setTimeout(() => {
                nav.classList.add('hidden');
            }, 5000);
        };

        // 初始显示
        showNav();

        // 鼠标移动时显示
        document.addEventListener('mousemove', showNav);

        // 触摸时显示
        document.addEventListener('touchstart', showNav);

        // 点击按钮时重置计时器
        nav.querySelectorAll('a, button').forEach(element => {
            element.addEventListener('click', showNav);
        });

        // 获取当前页面路径
        const currentPath = window.location.pathname;

        // 设置当前页面对应的导航链接为激活状态
        const navLinks = document.querySelectorAll('nav a');
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath.split('/').pop()) {
                link.classList.add('active');
            }
        });
    });

    class InfiniteRandomCarousel {
        constructor(items, autoplayInterval = 5000) {
            this.items = items;
            this.currentIndex = -1;
            this.autoplayInterval = autoplayInterval;
            this.isPaused = false;
            this.transitions = [
                'fade-transition',
                'zoom-transition',
                'slide-left-transition',
                'slide-right-transition',
                'slide-up-transition',
                'slide-down-transition',
                'rotate-scale-transition',
                'flip-transition',
                'zoom-fade-transition',
                'rotate-fade-transition',
                'blur-fade-transition',
                'swing-transition',
                'diagonal-transition',
                'spiral-transition',
                'ripple-transition',
                'flip-3d-transition',
                'elastic-transition',
                'fold-transition',
                'checkerboard-transition',
                'blinds-transition'
            ];
            this.currentTransitions = new Map(); // 使用Map来跟踪每个item当前的过渡效果
            this.transitionHistory = [];
            this.maxHistoryLength = 5; // 记录最近使用的5个效果
            this.isTransitioning = false; // 添加过渡状态标志
            items.length > 0 && this.init();
        }

        init() {
            // 重置过渡历史记录
            this.transitionHistory = [];
            
            const firstIndex = this.getRandomIndex();
            this.updateCarousel(firstIndex);
            this.startAutoplay();
            this.bindEvents();
            this.setupControlsVisibility();
        }

        setupControlsVisibility() {
            const controls = document.getElementById('controls');
            const carousel = document.querySelector('.carousel');

            // Show controls on mouse move or touch
            const showControls = () => {
                controls.classList.remove('hidden');

                // Clear existing timeout
                if (this.controlsTimeout) {
                    clearTimeout(this.controlsTimeout);
                }

                // Set new timeout to hide controls
                this.controlsTimeout = setTimeout(() => {
                    controls.classList.add('hidden');
                }, 5000);
            };

            // Initial show
            showControls();

            // Add event listeners
            carousel.addEventListener('mousemove', showControls);
            carousel.addEventListener('touchstart', showControls);

            // Show controls when buttons are clicked
            const pauseButton = document.getElementById('pauseBtn');

            if (pauseButton) {
                pauseButton.addEventListener('click', showControls);
            }
        }

        getRandomTransition() {
            // 过滤掉最近使用过的过渡效果
            let availableTransitions = this.transitions.filter(t => 
                !this.transitionHistory.includes(t)
            );
            
            // 如果所有过渡效果都用过了，重置历史记录
            if (availableTransitions.length === 0) {
                this.transitionHistory = [];
                availableTransitions = [...this.transitions];
            }
            
            // 随机选择一个可用的过渡效果
            const randomIndex = Math.floor(Math.random() * availableTransitions.length);
            const selectedTransition = availableTransitions[randomIndex];
            
            // 更新历史记录
            this.transitionHistory.push(selectedTransition);
            if (this.transitionHistory.length > this.maxHistoryLength) {
                this.transitionHistory.shift();
            }
            
            console.log('Selected transition:', selectedTransition); // 用于调试
            return selectedTransition;
        }

        getRandomIndex() {
            let randomIndex;
            do {
                randomIndex = Math.floor(Math.random() * this.items.length);
            } while (randomIndex === this.currentIndex);
            return randomIndex;
        }

        updateCarousel(newIndex, isManualChange = false) {
            // 获取当前项和下一项
            const currentItem = this.currentIndex >= 0 ? this.items[this.currentIndex] : null;
            const nextItem = this.items[newIndex];
            
            // 为下一项选择新的随机过渡效果
            const newTransition = this.getRandomTransition();
            
            // 清理所有项的过渡效果和激活状态
            this.items.forEach(item => {
                item.style.display = 'none';
                item.classList.remove('active');
                this.transitions.forEach(transition => {
                    item.classList.remove(transition);
                });
            });
            
            // 准备下一项
            nextItem.style.display = '';
            
            // 使用 RAF 链来确保正确的动画序列
            requestAnimationFrame(() => {
                nextItem.classList.add(newTransition);
                
                requestAnimationFrame(() => {
                    nextItem.offsetHeight;
                    
                    requestAnimationFrame(() => {
                        nextItem.classList.add('active');
                        this.currentIndex = newIndex;
                        this.handleMediaPlayback(newIndex);
                        
                        // 在过渡结束后重置状态
                        setTimeout(() => {
                            this.isTransitioning = false;
                            
                            // 如果是手动切换，延迟重启自动播放
                            if (isManualChange && !this.isPaused) {
                                this.startAutoplay();
                            }
                        }, 2000); // 等待过渡动画完成
                    });
                });
            });
        }

        handleMediaPlayback(newIndex) {
            this.items.forEach((item, index) => {
                const media = item.querySelector('video');
                if (media) {
                    if (index === newIndex) {
                        media.currentTime = 0;
                        const playPromise = media.play();
                        if (playPromise) {
                            playPromise.catch(e => console.log('视频播放错误:', e));
                        }
                    } else {
                        media.pause();
                    }
                }
            });
        }

        next() {
            const nextIndex = this.getRandomIndex();
            this.updateCarousel(nextIndex, true);
        }

        prev() {
            const prevIndex = this.getRandomIndex();
            this.updateCarousel(prevIndex, true);
        }

        startAutoplay() {
            this.stopAutoplay();
            this.autoplayTimer = setInterval(() => {
                if (!this.isPaused) {
                    this.next();
                }
            }, this.autoplayInterval);
        }

        stopAutoplay() {
            if (this.autoplayTimer) {
                clearInterval(this.autoplayTimer);
            }
        }

        togglePause() {
            this.isPaused = !this.isPaused;
            return this.isPaused;
        }

        bindEvents() {
            const pauseButton = document.getElementById('pauseBtn');

            // 保留键盘事件支持
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') {
                    this.handleClick('prev');
                } else if (e.key === 'ArrowRight') {
                    this.handleClick('next');
                }
            });

            if (pauseButton) {
                pauseButton.addEventListener('click', () => {
                    const isPaused = this.togglePause();
                    pauseButton.textContent = isPaused ? '继续' : '暂停';
                    if (isPaused) {
                        this.stopAutoplay();
                    } else {
                        this.startAutoplay();
                    }
                });
            }
        }

        handleClick(direction) {
            // 如果正在过渡中，忽略点击
            if (this.isTransitioning) {
                return;
            }

            // 停止当前的自动播放
            this.stopAutoplay();
            
            // 设置过渡标志
            this.isTransitioning = true;
            
            // 执行切换
            if (direction === 'next') {
                this.next();
            } else {
                this.prev();
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const carousel = document.querySelector('.carousel');
        let hideTimeout;
        let carouselInstance; // 添加变量存储轮播实例

        function showControls() {
            clearTimeout(hideTimeout);
            hideTimeout = setTimeout(() => {
            }, 5000);
        }

        // 监听鼠标移动
        document.addEventListener('mousemove', showControls);

        // 监听触摸事件
        document.addEventListener('touchstart', showControls);

        // 初始显示控制按钮
        showControls();

        // 修改键盘事件处理
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    carouselInstance.handleClick('prev');
                    showControls();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    carouselInstance.handleClick('next');
                    showControls();
                    break;
                case ' ': // 空格键
                    e.preventDefault();
                    document.getElementById('pauseBtn').click();
                    showControls();
                    break;
            }
        });

        // 初始化轮播实例并保存引用
        const carouselItems = document.querySelectorAll('.carousel-item');
        carouselInstance = new InfiniteRandomCarousel(carouselItems, <?= intval($autoplayInterval) ?>);
    });
</script>

<?php
// 渲染音乐播放器
$musicPlayer = new MusicPlayer($config);
echo $musicPlayer->render();
?>
</body>
</html>