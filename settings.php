<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];

// 默认值
if (!isset($config['autoplayInterval'])) $config['autoplayInterval'] = 3000;
if (!isset($config['viewMode'])) $config['viewMode'] = 'list';
if (!isset($config['perPage'])) $config['perPage'] = 10;

$message = null;

// 处理配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 间隔时间
    if (isset($_POST['autoplayInterval'])) {
        $interval = intval($_POST['autoplayInterval']);
        $config['autoplayInterval'] = $interval > 0 ? $interval : 3000;
    }

    // 视图模式
    if (isset($_POST['viewMode']) && in_array($_POST['viewMode'], ['list', 'grid'])) {
        $config['viewMode'] = $_POST['viewMode'];
    }

    // 每页显示数
    if (isset($_POST['perPage'])) {
        $pp = intval($_POST['perPage']);
        $config['perPage'] = $pp > 0 ? $pp : 10;
    }

    // 保存配置文件
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $message = "配置已保存。";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置</title>
    <link href="./favicon.ico" type="image/x-icon" rel="icon">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef;
            margin: 0;
            padding: 0;
        }
        header {
            background: #333;
            color: #fff;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header a {
            color: #fff;
            text-decoration: none;
            margin-left: 10px;
        }
        .wrapper {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .settings-form {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .message {
            background: #4CAF50;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="number"],
        input[type="radio"] {
            margin: 5px;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <header>
        <div>系统设置</div>
        <div>
            <a href="management.php">返回管理</a>
            <a href="index.php">返回首页</a>
        </div>
    </header>

    <div class="wrapper">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form class="settings-form" method="post">
            <div class="form-group">
                <label>轮播间隔时间</label>
                <input type="number" name="autoplayInterval" 
                       value="<?= htmlspecialchars($config['autoplayInterval']) ?>" 
                       min="100" step="100">
                <small>毫秒（对首页单文件无作用）</small>
            </div>

            <div class="form-group">
                <label>每页显示文件数</label>
                <input type="number" name="perPage" 
                       value="<?= htmlspecialchars($config['perPage']) ?>" 
                       min="1">
            </div>

            <div class="form-group">
                <label>视图模式</label>
                <div>
                    <label>
                        <input type="radio" name="viewMode" value="list" 
                               <?= $config['viewMode'] === 'list' ? 'checked' : '' ?>> 
                        列表视图
                    </label>
                    <label>
                        <input type="radio" name="viewMode" value="grid" 
                               <?= $config['viewMode'] === 'grid' ? 'checked' : '' ?>> 
                        网格视图
                    </label>
                </div>
            </div>

            <button type="submit">保存设置</button>
        </form>
    </div>
</body>
</html>