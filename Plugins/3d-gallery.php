<?php
$directory = '../assets/';
$allowedExtensions = ['jpeg','jpg','png','gif','webp'];
$images = [];

if (is_dir($directory)) {
    $files = array_diff(scandir($directory), ['.', '..']);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExtensions)) {
            $images[] = $directory . $file;
        }
    }
    shuffle($images);
    $images = array_slice($images, 0, 6); // 只取6张图片
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D相册展示</title>
    <style>
        :root {
            --bg-color: #f0f0f0;
            --text-color: #333;
            --card-bg: #fff;
            --shadow-color: rgba(0,0,0,0.1);
            --cube-size: min(70vw, 70vh);
        }

        [data-theme="dark"] {
            --bg-color: #222;
            --text-color: #fff;
            --card-bg: #333;
            --shadow-color: rgba(255,255,255,0.1);
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .nav-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .nav-bar a, .nav-bar button {
            padding: 10px 20px;
            background: var(--card-bg);
            color: var(--text-color);
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cube-container {
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            perspective: 2000px;
        }

        .cube {
            width: var(--cube-size);
            height: var(--cube-size);
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.5s ease;
        }

        .cube-face {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            cursor: pointer;
            transition: transform 0.5s ease;
        }

        .cube-face img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .front  { transform: translateZ(calc(var(--cube-size) / 2)); }
        .back   { transform: rotateY(180deg) translateZ(calc(var(--cube-size) / 2)); }
        .right  { transform: rotateY(90deg) translateZ(calc(var(--cube-size) / 2)); }
        .left   { transform: rotateY(-90deg) translateZ(calc(var(--cube-size) / 2)); }
        .top    { transform: rotateX(90deg) translateZ(calc(var(--cube-size) / 2)); }
        .bottom { transform: rotateX(-90deg) translateZ(calc(var(--cube-size) / 2)); }

        .enlarged {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            cursor: zoom-out;
        }

        .enlarged img {
            max-width: 90vw;
            max-height: 90vh;
            object-fit: contain;
        }
    </style>
</head>
<body>
<div class="nav-bar">
    <a href="../index.php">幻灯片模式</a>
    <button onclick="toggleTheme()">切换主题</button>
    <a href="../login.php">文件管理</a>
</div>

<div class="cube-container">
    <div class="cube">
        <?php
        $faces = ['front', 'back', 'right', 'left', 'top', 'bottom'];
        foreach ($faces as $index => $face): ?>
            <div class="cube-face <?= $face ?>" onclick="enlargeImage(this)">
                <img src="<?= $images[$index] ?>" alt="Cube <?= $face ?> face">
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    const cube = document.querySelector('.cube');
    let autoRotate = true;
    let rotationX = 0;
    let rotationY = 0;
    let lastMouseX = 0;
    let lastMouseY = 0;

    // 自动旋转
    function startAutoRotation() {
        if (!autoRotate) return;
        rotationY += 0.5;
        cube.style.transform = `rotateX(${rotationX}deg) rotateY(${rotationY}deg)`;
        requestAnimationFrame(startAutoRotation);
    }
    startAutoRotation();

    // 鼠标控制
    document.addEventListener('mousemove', (e) => {
        if (e.buttons === 1) { // 鼠标左键按下时
            autoRotate = false;
            const deltaX = e.clientX - lastMouseX;
            const deltaY = e.clientY - lastMouseY;

            rotationY += deltaX * 0.5;
            rotationX += deltaY * 0.5;

            cube.style.transform = `rotateX(${rotationX}deg) rotateY(${rotationY}deg)`;
        }
        lastMouseX = e.clientX;
        lastMouseY = e.clientY;
    });

    document.addEventListener('mouseup', () => {
        autoRotate = true;
        startAutoRotation();
    });

    // 放大图片
    function enlargeImage(element) {
        const img = element.querySelector('img');
        const enlargedDiv = document.createElement('div');
        enlargedDiv.className = 'enlarged';
        enlargedDiv.innerHTML = `<img src="${img.src}" alt="${img.alt}">`;

        enlargedDiv.onclick = () => {
            document.body.removeChild(enlargedDiv);
            autoRotate = true;
            startAutoRotation();
        };

        document.body.appendChild(enlargedDiv);
        autoRotate = false;
    }

    // 主题切换
    function toggleTheme() {
        const body = document.body;
        const currentTheme = body.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

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
</script>
</body>
</html>