<?php
$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];
$autoplayInterval = $config['autoplayInterval'] ?? 5000; // 默认5秒
$enabledFiles = $config['enabledFiles'] ?? [];

$directory = 'assets/';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'ogg'];
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
        html, body {
            margin:0; padding:0; height:100%; overflow:hidden; font-family: Arial, sans-serif;
            display:flex; flex-direction:column;
            background: linear-gradient(to right, #e0f7fa, #e1bee7);
        }
        nav {
            position:absolute; top:10px; right:10px; z-index:1000;
        }
        nav a {
            color:#fff; text-decoration:none; font-weight:bold;
            background:#444; padding:10px 15px; border-radius:5px;
            transition: background 0.3s;
        }
        nav a:hover {
            background:#555;
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
            min-width: 100vw; min-height:100vh; box-sizing: border-box;
            display:flex; justify-content:center; align-items:center;
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
    </style>
</head>
<body>
<nav>
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
    const track = document.querySelector('.carousel-track');
    const items = document.querySelectorAll('.carousel-item');
    const prevButton = document.getElementById('prev');
    const nextButton = document.getElementById('next');
    const pauseButton = document.getElementById('pauseBtn');
    const controls = document.getElementById('controls');

    let currentIndex = 0;
    let autoplayInterval = <?= intval($autoplayInterval) ?>;
    let isPaused = false;
    let hideControlsTimer;

    function updateCarousel() {
        if (items.length > 0) {
            const itemWidth = items[0].getBoundingClientRect().width;
            track.style.transform = `translateX(-${currentIndex * itemWidth}px)`;
        }
    }

    function autoPlay() {
        if (!isPaused && items.length > 0) {
            currentIndex = (currentIndex === items.length - 1) ? 0 : currentIndex + 1;
            updateCarousel();
        }
    }

    let autoPlayTimer = setInterval(autoPlay, autoplayInterval);

    function resetAutoPlay() {
        clearInterval(autoPlayTimer);
        autoPlayTimer = setInterval(autoPlay, autoplayInterval);
    }

    function showControls() {
        controls.classList.remove('hidden');
        resetHideControlsTimer();
    }

    function hideControls() {
        controls.classList.add('hidden');
    }

    function resetHideControlsTimer() {
        clearTimeout(hideControlsTimer);
        hideControlsTimer = setTimeout(hideControls, 5000); // 5秒后隐藏
    }

    // 初始隐藏计时
    hideControlsTimer = setTimeout(hideControls, 5000);

    // 显示控制按钮并重置计时器
    document.querySelector('.carousel').addEventListener('mousemove', showControls);
    document.querySelector('.carousel').addEventListener('touchstart', showControls);

    if (prevButton && nextButton) {
        prevButton.addEventListener('click', () => {
            if (items.length > 0) {
                currentIndex = (currentIndex === 0) ? items.length - 1 : currentIndex - 1;
                updateCarousel();
                resetAutoPlay();
                showControls();
            }
        });

        nextButton.addEventListener('click', () => {
            if (items.length > 0) {
                currentIndex = (currentIndex === items.length - 1) ? 0 : currentIndex + 1;
                updateCarousel();
                resetAutoPlay();
                showControls();
            }
        });
    }

    if (pauseButton) {
        pauseButton.addEventListener('click', () => {
            isPaused = !isPaused;
            pauseButton.textContent = isPaused ? '继续' : '暂停';
            resetAutoPlay();
            showControls();
        });
    }

    window.addEventListener('resize', updateCarousel);
</script>
</body>
</html>
