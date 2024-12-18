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

    // ��大图片
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

        // 添加全局发光效果
        const glow = document.createElement('div');
        glow.className = 'glow';
        document.body.appendChild(glow);

        faces.forEach((face, index) => {
            const rect = face.getBoundingClientRect();
            const container = document.createElement('div');
            container.className = 'exploding-face';
            container.style.left = rect.left + 'px';
            container.style.top = rect.top + 'px';
            document.body.appendChild(container);

            const shards = createBurningShards(face, 12);
            shards.forEach(shard => container.appendChild(shard));

            const direction = directions[index];
            const startTime = Date.now();
            let embers = [];
            let ashes = [];

            const animate = () => {
                const progress = (Date.now() - startTime) / 2000;
                if (progress >= 1) {
                    container.remove();
                    return;
                }

                // 碎片动画增强
                shards.forEach((shard, i) => {
                    const angle = (Math.PI * 2 * i) / shards.length;
                    const speed = 1 - Math.pow(1 - progress, 2);
                    const distance = speed * 500;
                    const x = Math.cos(angle) * distance * direction.x;
                    const y = Math.sin(angle) * distance * direction.y + 
                            progress * 200 + 
                            Math.sin(progress * 10 + i) * 20; // 添加波动
                    const scale = Math.max(0, 1 - progress * 2);
                    const rot = progress * 720 * (i % 2 ? 1 : -1);

                    shard.style.transform = `
                        translate(${x}px, ${y}px)
                        rotate(${rot}deg)
                        scale(${scale})
                    `;
                    shard.style.opacity = Math.max(0, 1 - progress * 2);

                    // 增加火星和烟雾效果
                    if (Math.random() < 0.3) {
                        const ember = createEmber(container, 
                            parseInt(shard.style.left) + x,
                            parseInt(shard.style.top) + y
                        );
                        embers.push({
                            element: ember,
                            x: x,
                            y: y,
                            vx: (Math.random() - 0.5) * 15,
                            vy: -Math.random() * 20,
                            life: 1
                        });

                        if (Math.random() < 0.5) {
                            createSmoke(container, 
                                parseInt(shard.style.left) + x,
                                parseInt(shard.style.top) + y
                            );
                        }
                    }
                });

                // 增强火星动画
                embers = embers.filter(ember => {
                    ember.life -= 0.02;
                    ember.vy += 0.8;
                    ember.vx *= 0.99;
                    ember.x += ember.vx;
                    ember.y += ember.vy;
                    
                    const wobble = Math.sin(Date.now() / 100) * 2;
                    ember.element.style.transform = `
                        translate(${ember.x + wobble}px, ${ember.y}px)
                        scale(${ember.life})
                    `;
                    ember.element.style.opacity = ember.life;

                    if (ember.life <= 0) {
                        ember.element.remove();
                        return false;
                    }
                    
                    // 随机产生灰烬
                    if (Math.random() < 0.1 && ember.life > 0.5) {
                        const ash = createAsh(container, ember.x, ember.y);
                        ashes.push({
                            element: ash,
                            x: ember.x,
                            y: ember.y,
                            vx: ember.vx * 0.3,
                            vy: Math.random() * 2,
                            rot: Math.random() * 360
                        });
                    }
                    
                    return true;
                });

                // 增强灰烬动画
                ashes = ashes.filter(ash => {
                    ash.vy += 0.1;
                    ash.vx *= 0.95;
                    ash.x += ash.vx;
                    ash.y += ash.vy;
                    ash.rot += 2;
                    
                    ash.element.style.transform = `
                        translate(${ash.x}px, ${ash.y}px)
                        rotate(${ash.rot}deg)
                    `;
                    ash.element.style.opacity = Math.max(0, 0.3 - progress);

                    if (progress > 0.9) {
                        ash.element.remove();
                        return false;
                    }
                    return true;
                });

                requestAnimationFrame(animate);
            };

            requestAnimationFrame(animate);
        });

        // 隐藏原始立方体并移除发光效果
        cube.style.opacity = '0';
        autoRotate = false;
        setTimeout(() => {
            glow.remove();
            location.reload();
        }, 2500);
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