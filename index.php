<?php
// 设置页面缓存控制
$seconds_to_cache = 3600; // 缓存时间，例如这里设置为1小时
$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . ' GMT';
header("Expires: $ts");
header("Pragma: cache");
header("Cache-Control: max-age=$seconds_to_cache");

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
$slides = [];
if (is_dir($directory)) {
    $files = array_diff(scandir($directory), ['.', '..']);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExtensions)) {
            $enabled = isset($enabledFiles[$file]) ? $enabledFiles[$file] : true;
            if ($enabled) {
                $slides[] = $file;
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
        }
        nav {
            position:absolute; top:10px; right:10px; z-index:1000;
            display: flex;
            gap: 10px;
            opacity: 1;
            transition: opacity 0.5s;
        }

        nav.hidden {
            opacity: 0;
            pointer-events: none;
        }

        nav a, nav button {
            color: var(--text-color);
            text-decoration:none; font-weight:bold;
            background: var(--control-bg);
            padding:10px 15px; border-radius:5px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }
        nav a:hover, nav button:hover {
            background: rgba(0, 0, 0, 0.8);
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
            transition: all 0.8s ease;
        }
        .carousel-item.active {
            opacity: 1;
        }
        .carousel-item img, .carousel-item video {
            width:100%; height:100%; object-fit: contain;
            background: #000; /* 黑色背景填充空白区域 */
        }
        .carousel-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            width: 100%; display: flex; justify-content: space-between; pointer-events:none;
        }
        .carousel-nav button {
            background-color: rgba(0, 0, 0, 0.5); color: white;
            border: none; padding: 15px; cursor: pointer; border-radius:50%;
            font-size:20px; line-height:1; width:50px; height:50px; display:flex; justify-content:center; align-items:center;
            pointer-events:auto; transition: background 0.3s;
        }
        .carousel-nav button:hover {
            background-color: rgba(0, 0, 0, 0.8);
        }
        .controls {
            position:absolute; bottom:30px; left:50%; transform:translateX(-50%);
            display:flex; gap:20px; opacity:1; transition: opacity 0.5s;
            z-index:1000;
        }
        .controls button {
            background:#333; color:#fff; border:none; padding:10px 20px; border-radius:5px; cursor:pointer;
            font-size:16px; transition: background 0.3s;
        }
        .controls button:hover {
            background:#555;
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
            .carousel-nav button {
                padding:10px; font-size:16px; width:40px; height:40px;
            }
            .controls button {
                padding:8px 16px; font-size:14px;
            }
        }

        /* 添加过渡动画效果 */
        .fade-transition {
            opacity: 0;
            transform: scale(1);
        }
        .fade-transition.active {
            opacity: 1;
            transform: scale(1);
        }

        /* 缩放 */
        .zoom-transition {
            opacity: 0;
            transform: scale(0.3);
        }
        .zoom-transition.active {
            opacity: 1;
            transform: scale(1);
        }

        /* 左滑入 */
        .slide-left-transition {
            opacity: 0;
            transform: translateX(100%);
        }
        .slide-left-transition.active {
            opacity: 1;
            transform: translateX(0);
        }

        /* 右滑入 */
        .slide-right-transition {
            opacity: 0;
            transform: translateX(-100%);
        }
        .slide-right-transition.active {
            opacity: 1;
            transform: translateX(0);
        }

        /* 上滑入 */
        .slide-up-transition {
            opacity: 0;
            transform: translateY(100%);
        }
        .slide-up-transition.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* 下滑入 */
        .slide-down-transition {
            opacity: 0;
            transform: translateY(-100%);
        }
        .slide-down-transition.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* 旋转缩放 */
        .rotate-scale-transition {
            opacity: 0;
            transform: rotate(180deg) scale(0.3);
        }
        .rotate-scale-transition.active {
            opacity: 1;
            transform: rotate(0) scale(1);
        }

        /* 翻转 */
        .flip-transition {
            opacity: 0;
            transform: perspective(1000px) rotateY(90deg);
        }
        .flip-transition.active {
            opacity: 1;
            transform: perspective(1000px) rotateY(0);
        }
    </style>
</head>
<body>
<nav>
    <a href="Plugins/3d-gallery.php">3D相册</a>
    <button onclick="toggleTheme()">切换主题</button>
    <a href="login.php">文件管理</a>
</nav>

<?php if (!empty($slides)): ?>
    <div class="carousel">
        <div class="carousel-track">
            <?php foreach ($slides as $file):
                $filePath = $directory . $file;
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'webm', 'ogg'])) {
                    echo "<div class='carousel-item'><video src='$filePath' controls muted preload='none'></video></div>";
                } else {
                    echo "<div class='carousel-item'><img src='$filePath' alt=''></div>";
                }
            endforeach; ?>
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
                'flip-transition'
            ];
            this.controlsTimeout = null;
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

        getRandomTransition() {
            return this.transitions[Math.floor(Math.random() * this.transitions.length)];
        }

        getRandomIndex() {
            let randomIndex;
            do {
                randomIndex = Math.floor(Math.random() * this.items.length);
            } while (this.history.includes(randomIndex) && this.history.length < this.items.length);

            this.history.push(randomIndex);
            if (this.history.length > this.maxHistoryLength) {
                this.history.shift();
            }

            return randomIndex;
        }

        updateCarousel(newIndex) {
            // 移除当前项目的活动状态和过渡类
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

            // 应用新的过渡效果
            const transition = this.getRandomTransition();
            nextItem.classList.add(transition);

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
        }

        next() {
            const nextIndex = this.getRandomIndex();
            this.updateCarousel(nextIndex);
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
                    const prevIndex = this.getRandomIndex();
                    this.updateCarousel(prevIndex);
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
</script>
</body>
</html>