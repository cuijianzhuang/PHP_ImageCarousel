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
        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            pointer-events: none;
            transition: opacity 0.3s ease; /* 添加过渡效果 */
        }
        .carousel-nav button {
            background-color: var(--control-bg);
            color: var(--control-color);
            border: none;
            padding: 0;
            cursor: pointer;
            border-radius: 50%;
            font-size: 24px;
            line-height: 1;
            width: 50px;
            height: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            pointer-events: auto;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            margin: 0 20px; /* 添加左右边距 */
        }
        .carousel-nav button:hover {
            background-color: var(--control-bg);
            transform: scale(1.1);
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

            .carousel-nav button {
                width: 40px;
                height: 40px;
                font-size: 20px;
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
        <div class="carousel-nav">
            <button id="prev">‹</button>
            <button id="next">›</button>
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
            this.history = [];
            this.maxHistoryLength = 3;
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
                'spiral-transition'
            ];
            this.lastTransition = '';
            this.controlsTimeout = null;
            this.lastTransitions = [];
            this.init();
        }

        init() {
            this.next();
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
            const prevButton = document.getElementById('prev');
            const nextButton = document.getElementById('next');

            [pauseButton, prevButton, nextButton].forEach(button => {
                if (button) {
                    button.addEventListener('click', showControls);
                }
            });
        }

        getRandomTransitions() {
            // 随机选择2-3个过渡效果
            const numEffects = Math.floor(Math.random() * 2) + 2;
            const selectedEffects = [];
            const availableTransitions = [...this.transitions];

            // 移除上一次使用的效果，确保不重复
            this.lastTransitions.forEach(last => {
                const index = availableTransitions.indexOf(last);
                if (index > -1) {
                    availableTransitions.splice(index, 1);
                }
            });

            // 如果可用效果不足，重新填充
            if (availableTransitions.length < numEffects) {
                availableTransitions.push(...this.transitions.filter(t => !this.lastTransitions.includes(t)));
            }

            // 选择新的效果组合
            for (let i = 0; i < numEffects; i++) {
                if (availableTransitions.length === 0) break;
                const randomIndex = Math.floor(Math.random() * availableTransitions.length);
                const effect = availableTransitions.splice(randomIndex, 1)[0];
                selectedEffects.push(effect);
            }

            // 更新上一次使用的效果记录
            this.lastTransitions = selectedEffects;
            return selectedEffects;
        }

        getRandomIndex() {
            let randomIndex;
            do {
                randomIndex = Math.floor(Math.random() * this.items.length);
            } while (randomIndex === this.currentIndex);
            return randomIndex;
        }

        updateCarousel(newIndex) {
            // 移除当前项目的活动状态和所有过渡类
            if (this.currentIndex >= 0) {
                const currentItem = this.items[this.currentIndex];
                currentItem.classList.remove('active');
                this.transitions.forEach(transition => {
                    currentItem.classList.remove(transition);
                });
            }

            // 更新索引
            this.currentIndex = newIndex;
            const nextItem = this.items[this.currentIndex];

            // 获取随机过渡效果组合
            const selectedEffects = this.getRandomTransitions();

            // 应用选中的过渡效果
            selectedEffects.forEach(effect => {
                nextItem.classList.add(effect);
            });

            // 确保过渡效果生效
            setTimeout(() => {
                nextItem.classList.add('active');
            }, 50);

            // 媒体控制
            this.items.forEach((item, index) => {
                const media = item.querySelector('video');
                if (media) {
                    if (index === this.currentIndex) {
                        media.currentTime = 0;
                        media.play().catch(e => console.log('播放错误:', e));
                    } else {
                        media.pause();
                    }
                }
            });

            // 在过渡结束后清理效果
            const cleanupTransitions = () => {
                if (this.currentIndex !== newIndex) return;
                selectedEffects.forEach(effect => {
                    if (nextItem !== this.items[this.currentIndex]) {
                        nextItem.classList.remove(effect);
                    }
                });
            };

            // 监听过渡结束
            nextItem.addEventListener('transitionend', cleanupTransitions, { once: true });
        }

        next() {
            const nextIndex = this.getRandomIndex();
            this.updateCarousel(nextIndex);
        }

        prev() {
            const prevIndex = this.getRandomIndex();
            this.updateCarousel(prevIndex);
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
            const nextButton = document.getElementById('next');
            const prevButton = document.getElementById('prev');
            const pauseButton = document.getElementById('pauseBtn');

            if (nextButton) {
                nextButton.addEventListener('click', () => {
                    this.next();
                    this.startAutoplay();
                });
            }

            if (prevButton) {
                prevButton.addEventListener('click', () => {
                    this.prev();
                    this.startAutoplay();
                });
            }

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

            // 监听过渡结束事件
            this.items.forEach(item => {
                item.addEventListener('transitionend', () => {
                    this.transitions.forEach(transition => {
                        if (item !== this.items[this.currentIndex]) {
                            item.classList.remove(transition);
                        }
                    });
                });
            });

            window.addEventListener('resize', () => {
                this.updateCarousel(this.currentIndex);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const carouselItems = document.querySelectorAll('.carousel-item');
        const infiniteCarousel = new InfiniteRandomCarousel(carouselItems, <?= intval($autoplayInterval) ?>);
    });

    document.addEventListener('DOMContentLoaded', () => {
        const carousel = document.querySelector('.carousel');
        const carouselNav = document.querySelector('.carousel-nav');
        const prevBtn = document.getElementById('prev');
        const nextBtn = document.getElementById('next');
        let hideTimeout;

        function showControls() {
            carouselNav.style.opacity = '1';
            clearTimeout(hideTimeout);
            hideTimeout = setTimeout(() => {
                if (!isMouseOverControls) {
                    carouselNav.style.opacity = '0';
                }
            }, 5000);
        }

        let isMouseOverControls = false;

        // 监听鼠标移动
        document.addEventListener('mousemove', showControls);

        // 监听触摸事件
        document.addEventListener('touchstart', showControls);

        // 监听鼠标悬停在控制按钮上的情况
        carouselNav.addEventListener('mouseenter', () => {
            isMouseOverControls = true;
            showControls();
        });

        carouselNav.addEventListener('mouseleave', () => {
            isMouseOverControls = false;
            showControls();
        });

        // 初始显示控制按钮
        showControls();

        // 添加键盘控制
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    prevBtn.click();
                    showControls();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    nextBtn.click();
                    showControls();
                    break;
                case ' ': // 空格键
                    e.preventDefault();
                    document.getElementById('pauseBtn').click();
                    showControls();
                    break;
            }
        });

        // 点击控制按钮时重置隐藏计时器
        [prevBtn, nextBtn].forEach(btn => {
            btn.addEventListener('click', showControls);
        });
    });
</script>

<?php
// 渲染音乐播放器
$musicPlayer = new MusicPlayer($config);
echo $musicPlayer->render();
?>
</body>
</html>