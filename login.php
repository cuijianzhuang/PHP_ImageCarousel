<?php
session_start();
$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];
$adminUser = $config['admin']['username'] ?? 'admin';
$adminPass = $config['admin']['password'] ?? 'password123';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: management.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username === $adminUser && $password === $adminPass) {
        $_SESSION['logged_in'] = true;
        header('Location: management.php');
        exit;
    } else {
        $error = "用户名或密码错误！";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>登录 - 文件管理</title>
    <style>
        body {
            background: #eef; font-family: Arial, sans-serif;
            height:100vh; display:flex; justify-content:center; align-items:center; margin:0;
        }
        .login-form {
            background: #fff; padding: 20px; box-shadow:0 0 10px rgba(0,0,0,.1); border-radius:5px; width:300px;
        }
        .login-form h2 {
            margin:0 0 20px; text-align:center;
        }
        .login-form input {
            width:100%; padding:10px; margin-bottom:10px; box-sizing:border-box; border:1px solid #ccc; border-radius:3px;
        }
        .login-form button {
            width:100%; padding:10px; background:#333; color:#fff; border:none; cursor:pointer; border-radius:3px; font-weight:bold;
        }
        .login-form button:hover {
            background:#555;
        }
        .error {
            color:red; margin-bottom:10px; text-align:center;
        }
    </style>
</head>
<body>
<div class="login-form">
    <h2>登录文件管理</h2>
    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form action="" method="post">
        <input type="text" name="username" placeholder="用户名" required>
        <input type="password" name="password" placeholder="密码" required>
        <button type="submit">登录</button>
    </form>
</div>
</body>
</html>
