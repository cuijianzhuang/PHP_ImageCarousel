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
            transition: transform 0.5s ease, opacity 0.5s ease;
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

        .exploding-face {
            position: fixed;
            width: calc(var(--cube-size));
            height: calc(var(--cube-size));
            transition: none;
            z-index: 1000;
        }

        .shard {
            position: absolute;
            overflow: hidden;
            transform-origin: center;
            backface-visibility: hidden;
            box-shadow: 0 0 20px rgba(255, 107, 0, 0.3);
        }

        .shard::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, 
                rgba(255,107,0,0.2),
                rgba(255,68,0,0.1),
                rgba(255,34,0,0));
            mix-blend-mode: overlay;
        }

        .ember {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #ff6b00;
            border-radius: 50%;
            filter: blur(1px);
            box-shadow: 
                0 0 4px #ff6b00,
                0 0 8px #ff4400,
                0 0 12px #ff2200,
                0 0 16px #ff0000;
            animation: flicker 0.2s ease-in-out infinite alternate;
        }

        .ash {
            position: absolute;
            background: #333;
            border-radius: 50%;
            opacity: 0.3;
            filter: blur(1px);
            transform-origin: center;
        }

        .glow {
            position: absolute;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,107,0,0.2) 0%, rgba(255,68,0,0) 70%);
            mix-blend-mode: screen;
            pointer-events: none;
        }

        @keyframes flicker {
            0% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .smoke {
            position: absolute;
            width: 8px;
            height: 8px;
            background: rgba(100, 100, 100, 0.3);
            border-radius: 50%;
            filter: blur(4px);
            animation: rise 2s ease-out forwards;
        }

        @keyframes rise {
            0% { 
                transform: translateY(0) scale(1);
                opacity: 0.3;
            }
            100% { 
                transform: translateY(-100px) scale(3);
                opacity: 0;
            }
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
    let lastTouchX = 0;
    let lastTouchY = 0;

    // 自动旋转
    function startAutoRotation() {
        if (!autoRotate) return;
        rotationY += 0.2; // 降低自动旋转速度
        cube.style.transform = `rotateX(${rotationX}deg) rotateY(${rotationY}deg)`;
        requestAnimationFrame(startAutoRotation);
    }
    startAutoRotation();

    // 鼠标控制
    document.addEventListener('mousemove', (e) => {
        if (e.buttons === 1) { // 鼠标左键下时
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

    // 触摸控制
    document.addEventListener('touchstart', (e) => {
        autoRotate = false;
        const touch = e.touches[0];
        lastTouchX = touch.clientX;
        lastTouchY = touch.clientY;
    });

    document.addEventListener('touchmove', (e) => {
        e.preventDefault(); // 防止页面滚动
        const touch = e.touches[0];
        const deltaX = touch.clientX - lastTouchX;
        const deltaY = touch.clientY - lastTouchY;

        rotationY += deltaX * 0.5;
        rotationX += deltaY * 0.5;

        cube.style.transform = `rotateX(${rotationX}deg) rotateY(${rotationY}deg)`;

        lastTouchX = touch.clientX;
        lastTouchY = touch.clientY;
    }, { passive: false });

    document.addEventListener('touchend', () => {
        autoRotate = true;
        startAutoRotation();
    });

    // 大图片
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

    // 鼠标追随旋转
    document.addEventListener('mousemove', (e) => {
        if (!autoRotate) return;
        
        const rect = cube.getBoundingClientRect();
        const cubeX = rect.left + rect.width / 2;
        const cubeY = rect.top + rect.height / 2;
        
        // 计算鼠标相对于立方体中心的位置
        const deltaX = e.clientX - cubeX;
        const deltaY = e.clientY - cubeY;
        
        // 将位置差转换为旋转角度
        rotationY = deltaX * 0.1;
        rotationX = -deltaY * 0.1;
        
        cube.style.transform = `rotateX(${rotationX}deg) rotateY(${rotationY}deg)`;
    });

    function createBurningShards(face, count) {
        const shards = [];
        const width = face.offsetWidth;
        const height = face.offsetHeight;
        const img = face.querySelector('img').src;

        for (let i = 0; i < count; i++) {
            const shard = document.createElement('div');
            shard.className = 'shard';
            
            // 随机大小和位置
            const w = Math.random() * (width / 3) + width / 6;
            const h = Math.random() * (height / 3) + height / 6;
            const left = Math.random() * (width - w);
            const top = Math.random() * (height - h);
            
            shard.style.width = w + 'px';
            shard.style.height = h + 'px';
            shard.style.left = left + 'px';
            shard.style.top = top + 'px';
            
            const imgElement = document.createElement('img');
            imgElement.src = img;
            imgElement.style.left = -left + 'px';
            imgElement.style.top = -top + 'px';
            
            shard.appendChild(imgElement);
            shards.push(shard);
        }
        return shards;
    }

    function createEmber(container, x, y) {
        const ember = document.createElement('div');
        ember.className = 'ember';
        ember.style.left = x + 'px';
        ember.style.top = y + 'px';
        container.appendChild(ember);
        return ember;
    }

    function createAsh(container, x, y) {
        const ash = document.createElement('div');
        ash.className = 'ash';
        ash.style.left = x + 'px';
        ash.style.top = y + 'px';
        ash.style.width = Math.random() * 3 + 2 + 'px';
        ash.style.height = ash.style.width;
        container.appendChild(ash);
        return ash;
    }

    function createSmoke(container, x, y) {
        const smoke = document.createElement('div');
        smoke.className = 'smoke';
        smoke.style.left = x + 'px';
        smoke.style.top = y + 'px';
        container.appendChild(smoke);
        
        smoke.addEventListener('animationend', () => smoke.remove());
        return smoke;
    }

    function createBurningEffect() {
        const faces = document.querySelectorAll('.cube-face');
        const directions = [
            { x: 1, y: 0 }, { x: -1, y: 0 }, { x: 0, y: -1 },
            { x: 0, y: 1 }, { x: 0.7, y: -0.7 }, { x: -0.7, y: -0.7 }
        ];

        // 使用 DocumentFragment 减少 DOM 操作
        const fragment = document.createDocumentFragment();
        const containers = [];
        const allShards = [];
        const allEmbers = [];
        const allAshes = [];

        // 预先创建所有元素
        faces.forEach((face, index) => {
            const rect = face.getBoundingClientRect();
            const container = document.createElement('div');
            container.className = 'exploding-face';
            container.style.left = rect.left + 'px';
            container.style.top = rect.top + 'px';

            const shards = createBurningShards(face, 6); // 减少碎片数量
            shards.forEach(shard => container.appendChild(shard));
            
            fragment.appendChild(container);
            containers.push(container);
            allShards.push(...shards);
        });

        document.body.appendChild(fragment);

        const startTime = Date.now();
        let lastFrame = startTime;
        const FRAME_RATE = 1000 / 60; // 限制帧率为 60fps

        const animate = () => {
            const currentTime = Date.now();
            const deltaTime = currentTime - lastFrame;

            // 限制帧率
            if (deltaTime < FRAME_RATE) {
                requestAnimationFrame(animate);
                return;
            }

            const progress = (currentTime - startTime) / 2000;
            if (progress >= 1) {
                containers.forEach(container => container.remove());
                location.reload();
                return;
            }

            lastFrame = currentTime;

            // 批量更新碎片
            allShards.forEach((shard, i) => {
                const directionIndex = Math.floor(i / 6);
                const direction = directions[directionIndex];
                const speed = 1 - Math.pow(1 - progress, 2);
                const distance = speed * 500;
                const x = Math.cos(i) * distance * direction.x;
                const y = Math.sin(i) * distance * direction.y + progress * 200;
                const scale = Math.max(0, 1 - progress * 2);
                const rot = progress * 360 * (i % 2 ? 1 : -1);

                // 使用 transform3d 触发 GPU 加速
                shard.style.transform = `translate3d(${x}px, ${y}px, 0) rotate(${rot}deg) scale(${scale})`;
                shard.style.opacity = Math.max(0, 1 - progress * 2);

                // 降低火星生成频率
                if (Math.random() < 0.1 && allEmbers.length < 30) {
                    const ember = createEmber(containers[directionIndex], x, y);
                    allEmbers.push({
                        element: ember,
                        x: x,
                        y: y,
                        vx: (Math.random() - 0.5) * 10,
                        vy: -Math.random() * 15,
                        life: 1
                    });
                }
            });

            // 批量更新火星
            for (let i = allEmbers.length - 1; i >= 0; i--) {
                const ember = allEmbers[i];
                ember.life -= 0.02;
                ember.vy += 0.5;
                ember.x += ember.vx;
                ember.y += ember.vy;

                if (ember.life <= 0) {
                    ember.element.remove();
                    allEmbers.splice(i, 1);
                    continue;
                }

                ember.element.style.transform = `translate3d(${ember.x}px, ${ember.y}px, 0) scale(${ember.life})`;
                ember.element.style.opacity = ember.life;

                // 降低灰烬生成频率
                if (Math.random() < 0.05 && allAshes.length < 20) {
                    const ash = createAsh(containers[Math.floor(i / 6)], ember.x, ember.y);
                    allAshes.push({
                        element: ash,
                        x: ember.x,
                        y: ember.y,
                        vy: Math.random() * 2
                    });
                }
            }

            // 批量更新灰烬
            for (let i = allAshes.length - 1; i >= 0; i--) {
                const ash = allAshes[i];
                ash.y += ash.vy;

                if (progress > 0.9) {
                    ash.element.remove();
                    allAshes.splice(i, 1);
                    continue;
                }

                ash.element.style.transform = `translate3d(0, ${ash.y}px, 0)`;
                ash.element.style.opacity = Math.max(0, 0.3 - progress);
            }

            requestAnimationFrame(animate);
        };

        // 隐藏原始立方体
        cube.style.opacity = '0';
        autoRotate = false;

        requestAnimationFrame(animate);
    }

    // 修改点击事件监听器
    document.addEventListener('click', (e) => {
        if (e.target.closest('.nav-bar') || e.target.closest('.enlarged')) return;
        createBurningEffect();
    });

    // 修改原有的自动旋转
    function startAutoRotation() {
        if (!autoRotate) return;
        rotationY += 0.2; // 降低自动旋转速度
        cube.style.transform = `rotateX(${rotationX}deg) rotateY(${rotationY}deg)`;
        requestAnimationFrame(startAutoRotation);
    }
</script>
</body>
</html>