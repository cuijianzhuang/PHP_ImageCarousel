<?php
class FileManager {
    private $baseDirectory;
    private $allowedExtensions = [
        // Image formats
        'jpeg','jpg','png','gif','webp','bmp','tiff','tif','heic','heif',
        // Video formats
        'mp4','avi','mov','wmv','flv','mkv','webm','ogg','m4v','mpeg','mpg','3gp'
    ];

    public function __construct($baseDirectory) {
        $this->baseDirectory = rtrim($baseDirectory, '/');
    }

    /**
     * 清理未使用的文件
     * @param array $config 配置数组
     * @return array 被删除的文件列表
     */
    public function cleanupUnusedFiles($config) {
        $deletedFiles = [];
        $enabledFiles = $config['enabledFiles'] ?? [];
        
        // 扫描主目录和showimg目录
        $directories = [
            $this->baseDirectory,
            $this->baseDirectory . '/showimg'
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = array_diff(scandir($directory), ['.', '..']);
            
            foreach ($files as $file) {
                // 跳过目录
                if (is_dir($directory . '/' . $file)) {
                    continue;
                }

                // 检查文件扩展名
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $this->allowedExtensions)) {
                    continue;
                }

                // 如果文件不在启用列表中或被标记为未启用，则删除
                if (!isset($enabledFiles[$file]) || $enabledFiles[$file] === false) {
                    $filePath = $directory . '/' . $file;
                    if (file_exists($filePath) && is_writable($filePath)) {
                        if (unlink($filePath)) {
                            $deletedFiles[] = $file;
                            // 从配置中移除该文件的记录
                            unset($config['enabledFiles'][$file]);
                        }
                    }
                }
            }
        }

        // 保存更新后的配置
        if (!empty($deletedFiles)) {
            $configFile = dirname($this->baseDirectory) . '/config.json';
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $deletedFiles;
    }

    /**
     * 获取文件的完整路径
     * @param string $fileName 文件名
     * @return string|null 文件的完整路径，如果文件不存在则返回null
     */
    public function getFilePath($fileName) {
        $showimgPath = $this->baseDirectory . '/showimg/' . $fileName;
        $mainPath = $this->baseDirectory . '/' . $fileName;

        if (file_exists($showimgPath)) {
            return $showimgPath;
        } elseif (file_exists($mainPath)) {
            return $mainPath;
        }

        return null;
    }

    /**
     * 检查文件是否存在
     * @param string $fileName 文件名
     * @return bool
     */
    public function fileExists($fileName) {
        return $this->getFilePath($fileName) !== null;
    }
} 