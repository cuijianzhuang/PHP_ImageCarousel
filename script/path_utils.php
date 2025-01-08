<?php
function normalizeMusicPath($path) {
    // 统一处理路径分隔符和多余的斜杠
    $path = str_replace(['\\', '//'], '/', $path);
    // 确保路径以单个斜杠开头
    $path = '/' . ltrim($path, '/');
    // 移除可能存在的多余斜杠
    $path = preg_replace('#/+#', '/', $path);
    // 确保路径在 /assets/music/ 目录下
    if (strpos($path, '/assets/music/') !== 0) {
        $path = '/assets/music/' . basename($path);
    }
    return $path;
}
?> 