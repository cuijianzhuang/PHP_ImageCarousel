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
        $loop = $this->config['background_music']['loop'] ?? false;
        $random = $this->config['background_music']['random'] ?? false;
        $autoplay = $this->config['background_music']['autoplay'] ?? false;
        
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
            cursor: pointer;
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
        </style>
        
        <div class="music-player" id="musicPlayer">
            <audio id="bgMusic" style="display: none;" <?= $loop ? 'loop' : '' ?>>
                <source src="<?= htmlspecialchars($musicFile) ?>" type="audio/mpeg">
            </audio>
            <button class="play-btn" id="musicToggle"></button>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const audio = document.getElementById('bgMusic');
            const player = document.getElementById('musicPlayer');
            const toggle = document.getElementById('musicToggle');
            
            let isPlaying = false;
            
            // 设置初始音量
            audio.volume = <?= $volume ?>;
            
            // 获取保存的音量
            const savedVolume = localStorage.getItem('musicVolume');
            if (savedVolume !== null) {
                audio.volume = parseFloat(savedVolume);
            }
            
            // 使用 BroadcastChannel 监听音量变化
            const volumeChannel = new BroadcastChannel('volumeControl');
            volumeChannel.onmessage = (event) => {
                if (event.data.type === 'volumeChange') {
                    audio.volume = event.data.volume;
                    localStorage.setItem('musicVolume', event.data.volume);
                }
            };

            <?php if ($random): ?>
            // 获取音乐列表（如果启用了随机播放）
            let musicFiles = [];
            let currentMusicIndex = 0;
            
            fetch('script/get_music_list.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        musicFiles = data.files;
                        shuffleArray(musicFiles);
                    }
                });
            <?php endif; ?>

            // 音频结束事件处理
            audio.addEventListener('ended', function() {
                <?php if ($random): ?>
                if (musicFiles.length > 0) {
                    currentMusicIndex = (currentMusicIndex + 1) % musicFiles.length;
                    audio.src = musicFiles[currentMusicIndex].path;
                    audio.play().catch(e => console.log('播放失败:', e));
                    isPlaying = true;
                    updatePlayerState();
                }
                <?php elseif (!$loop): ?>
                isPlaying = false;
                updatePlayerState();
                <?php endif; ?>
            });

            function shuffleArray(array) {
                for (let i = array.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [array[i], array[j]] = [array[j], array[i]];
                }
                return array;
            }

            function updatePlayerState() {
                if (isPlaying) {
                    player.classList.add('playing');
                    toggle.className = 'pause-btn';
                } else {
                    player.classList.remove('playing');
                    toggle.className = 'play-btn';
                }
            }

            function togglePlay() {
                if (isPlaying) {
                    audio.pause();
                } else {
                    audio.play().catch(e => console.log('播放失败:', e));
                }
                isPlaying = !isPlaying;
                updatePlayerState();
            }

            toggle.addEventListener('click', togglePlay);

            // 自动播放处理
            <?php if ($autoplay): ?>
            audio.play().then(() => {
                isPlaying = true;
                updatePlayerState();
            }).catch(e => {
                console.log('自动播放被阻止:', e);
                isPlaying = false;
                updatePlayerState();
            });
            <?php endif; ?>

            // 监听页面可见性变化
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && isPlaying) {
                    audio.play();
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
?> 