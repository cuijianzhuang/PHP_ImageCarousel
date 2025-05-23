<?php
require_once __DIR__ . '/../script/path_utils.php';

class MusicPlayer {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function render() {
        if (!isset($this->config['background_music']) || !$this->config['background_music']['enabled']) {
            return '';
        }
        
        $musicFile = normalizeMusicPath($this->config['background_music']['file']);
        $volume = $this->config['background_music']['volume'] ?? 0.5;
        $loop = $this->config['background_music']['loop'] ?? true;
        $random = $this->config['background_music']['random'] ?? false;
        $autoplay = $this->config['background_music']['autoplay'] ?? true;
        
        ob_start();
        ?>
        <style>
        .music-player {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.6);
            padding: 10px;
            border-radius: 8px;
            z-index: 1000;
            backdrop-filter: blur(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        
        .music-player.hidden {
            opacity: 0;
            transform: translateY(20px);
            pointer-events: none;
        }
        
        .music-player .play-btn,
        .music-player .pause-btn {
            width: 20px;
            height: 20px;
            border: none;
            background: none;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .music-player .play-btn::before {
            content: '';
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 10px 0 10px 15px;
            border-color: transparent transparent transparent #ffffff;
        }
        
        .music-player .pause-btn::before,
        .music-player .pause-btn::after {
            content: '';
            width: 4px;
            height: 16px;
            background: #ffffff;
            position: absolute;
        }
        
        .music-player .pause-btn::before {
            left: 4px;
        }
        
        .music-player .pause-btn::after {
            right: 4px;
        }
        
        .music-player.playing {
            animation: rotate 8s linear infinite;
        }
        
        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        </style>
        
        <div class="music-player" id="musicPlayer">
            <audio id="bgMusic" style="display: none;" loop>
                <source src="<?= htmlspecialchars($musicFile) ?>" type="audio/mpeg">
            </audio>
            <button class="play-btn" id="musicToggle"></button>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const audio = document.getElementById('bgMusic');
            const player = document.getElementById('musicPlayer');
            const toggle = document.getElementById('musicToggle');
            
            // 设置默认状态
            let isPlaying = <?= $autoplay ? 'true' : 'false' ?>;
            
            // 设置初始音量
            audio.volume = <?= $volume ?>; // 直接使用 PHP 变量设置初始音量
            
            // 获取保存的音量（如果有的话）
            const savedVolume = localStorage.getItem('musicVolume');
            if (savedVolume !== null) {
                audio.volume = parseFloat(savedVolume);
            }
            
            // 使用 BroadcastChannel 监听音量变化
            const volumeChannel = new BroadcastChannel('volumeControl');
            volumeChannel.onmessage = (event) => {
                if (event.data.type === 'volumeChange') {
                    audio.volume = event.data.volume;
                    // 更新音量显示（如果有的话）
                    const volumeDisplay = document.getElementById('volumeDisplay');
                    if (volumeDisplay) {
                        volumeDisplay.textContent = Math.round(event.data.volume * 100) + '%';
                    }
                }
            };

            // 保存音量设置
            audio.addEventListener('volumechange', () => {
                localStorage.setItem('musicVolume', audio.volume);
            });

            // 获取保存的播放进度
            const savedTime = parseFloat(localStorage.getItem('musicTime') || '0');

            // 定期保存播放进度
            setInterval(() => {
                if (!audio.paused) {
                    localStorage.setItem('musicTime', audio.currentTime);
                }
            }, 1000);

            function togglePlay() {
                if (isPlaying) {
                    audio.pause();
                    player.classList.remove('playing');
                    toggle.className = 'play-btn';
                } else {
                    audio.play();
                    player.classList.add('playing');
                    toggle.className = 'pause-btn';
                }
                isPlaying = !isPlaying;
            }

            toggle.addEventListener('click', togglePlay);

            // 恢复播放进度
            if (savedTime > 0) {
                audio.currentTime = savedTime;
            }

            // 自动播放处理函数
            function startPlayback() {
                audio.play().then(() => {
                    isPlaying = true;
                    player.classList.add('playing');
                    toggle.className = 'pause-btn';
                }).catch(e => {
                    console.log('自动播放被阻止:', e);
                    // 如果自动播放失败，等待用户交互
                    const resumePlay = () => {
                        audio.play().then(() => {
                            isPlaying = true;
                            player.classList.add('playing');
                            toggle.className = 'pause-btn';
                            document.removeEventListener('click', resumePlay);
                        });
                    };
                    document.addEventListener('click', resumePlay);
                });
            }

            // 如果配置为自动播放，则尝试自动播放
            <?php if ($autoplay): ?>
            // 页面加载时自动开始播放
            startPlayback();
            <?php else: ?>
            // 不自动播放时，更新UI状态
            player.classList.remove('playing');
            toggle.className = 'play-btn';
            <?php endif; ?>

            // 监听播放结束事件
            audio.addEventListener('ended', () => {
                <?php if ($random && !empty($musicFiles)): ?>
                // 随机播放功能
                playNext();
                <?php else: ?>
                // 继续播放当前音频（虽然有 loop 属性，但为了保险起见）
                if (!audio.loop) {
                    audio.currentTime = 0;
                    audio.play().catch(function(error) {
                        console.log("重新播放失败:", error);
                    });
                }
                <?php endif; ?>
            });

            // 页面关闭或刷新前保存播放进度
            window.addEventListener('beforeunload', () => {
                localStorage.setItem('musicTime', audio.currentTime);
            });

            <?php if ($random && !empty($musicFiles)): ?>
            // 随机播放功能
            const musicFiles = <?= json_encode($musicFiles) ?>;
            let currentIndex = 0;

            function playNext() {
                currentIndex = Math.floor(Math.random() * musicFiles.length);
                audio.src = musicFiles[currentIndex];
                startPlayback();
            }
            <?php endif; ?>

            // 监听页面可见性变化
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && isPlaying) {
                    audio.play();
                }
            });

            const musicPlayer = document.querySelector('.music-player');
            let hideTimeout;

            function showMusicPlayer() {
                musicPlayer.classList.remove('hidden');
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(() => {
                    if (!isMouseOverPlayer) {
                        musicPlayer.classList.add('hidden');
                    }
                }, 5000);
            }

            let isMouseOverPlayer = false;

            // 监听鼠标移动
            document.addEventListener('mousemove', showMusicPlayer);

            // 监听触摸事件
            document.addEventListener('touchstart', showMusicPlayer);

            // 监听鼠标悬停在播放器上的情况
            musicPlayer.addEventListener('mouseenter', () => {
                isMouseOverPlayer = true;
                showMusicPlayer();
            });

            musicPlayer.addEventListener('mouseleave', () => {
                isMouseOverPlayer = false;
                showMusicPlayer();
            });

            // 初始显示播放器
            showMusicPlayer();

            // 在点击播放器按钮时重置计时器
            musicPlayer.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', showMusicPlayer);
            });

            // 键盘操作时显示播放器
            document.addEventListener('keydown', showMusicPlayer);
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
?> 