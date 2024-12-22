<?php
class MusicPlayer {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function render() {
        if (!isset($this->config['background_music']) || !$this->config['background_music']['enabled']) {
            return '';
        }
        
        $musicFile = $this->config['background_music']['file'];
        $volume = $this->config['background_music']['volume'] ?? 0.5;
        $autoplay = $this->config['background_music']['autoplay'] ?? false;
        $loop = $this->config['background_music']['loop'] ?? false;
        $random = $this->config['background_music']['random'] ?? false;
        
        ob_start();
        ?>
        <style>
        .music-player {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            width: 50px;
            height: 50px;
            background: rgba(0, 0, 0, 0.6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            backdrop-filter: blur(5px);
        }
        
        .music-player:hover {
            background: rgba(0, 0, 0, 0.8);
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
            <audio id="bgMusic" style="display: none;"
                <?= $loop ? 'loop' : '' ?>
                src="<?= htmlspecialchars($musicFile) ?>">
            </audio>
            <button class="play-btn" id="musicToggle"></button>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const audio = document.getElementById('bgMusic');
            const player = document.getElementById('musicPlayer');
            const toggle = document.getElementById('musicToggle');
            
            // 从 localStorage 获取播放状态和播放进度
            let isPlaying = localStorage.getItem('musicPlaying') === 'true';
            const savedTime = parseFloat(localStorage.getItem('musicTime') || '0');
            const savedVolume = parseFloat(localStorage.getItem('musicVolume') || '<?= $volume ?>');

            // 设置音量
            audio.volume = savedVolume;

            // 保存音量设置
            audio.addEventListener('volumechange', () => {
                localStorage.setItem('musicVolume', audio.volume);
            });

            // 定期保存播放进度
            setInterval(() => {
                if (isPlaying && !audio.paused) {
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
                localStorage.setItem('musicPlaying', isPlaying);
            }

            toggle.addEventListener('click', togglePlay);

            // 页面加载时恢复播放状态
            if (savedTime > 0) {
                audio.currentTime = savedTime;
            }

            // 如果之前在播放或设置了自动播放，则继续播放
            if (isPlaying || <?= $autoplay ? 'true' : 'false' ?>) {
                audio.play().then(() => {
                    isPlaying = true;
                    localStorage.setItem('musicPlaying', 'true');
                    player.classList.add('playing');
                    toggle.className = 'pause-btn';
                }).catch(e => {
                    console.log('播放被阻止:', e);
                    isPlaying = false;
                    localStorage.setItem('musicPlaying', 'false');
                });
            }

            // 监听播放结束事件
            audio.addEventListener('ended', () => {
                <?php if ($random && !empty($musicFiles)): ?>
                // 随机播放功能
                playNext();
                <?php else: ?>
                if (!audio.loop) {
                    isPlaying = false;
                    localStorage.setItem('musicPlaying', 'false');
                    player.classList.remove('playing');
                    toggle.className = 'play-btn';
                }
                <?php endif; ?>
            });

            // 页面关闭或刷新前保存状态
            window.addEventListener('beforeunload', () => {
                localStorage.setItem('musicTime', audio.currentTime);
                localStorage.setItem('musicPlaying', isPlaying);
            });

            <?php if ($random && !empty($musicFiles)): ?>
            // 随机播放功能
            const musicFiles = <?= json_encode($musicFiles) ?>;
            let currentIndex = 0;

            function playNext() {
                currentIndex = Math.floor(Math.random() * musicFiles.length);
                audio.src = musicFiles[currentIndex];
                audio.play().then(() => {
                    isPlaying = true;
                    localStorage.setItem('musicPlaying', 'true');
                });
            }
            <?php endif; ?>
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
?> 