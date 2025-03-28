<?php
// 连接到数据库
$servername = "localhost";
$username = "root";
$password = "han040423";
$dbname = "mydb";

$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 获取表单输入
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 检查用户名是否已存在
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // 用户名已存在
        echo "<script>alert('用户名已存在，请选择另一个用户名。');</script>";
    } else {
        // 密码加密
        // $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // 插入新用户
        $insert_sql = "INSERT INTO users (username, password_hash) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ss", $username, $password);

        if ($insert_stmt->execute()) {
            // 注册成功后弹窗并跳转
            echo "<script>
                    alert('注册成功，即将跳转到登录页面');
                    window.location.href = 'login_user.php';
                  </script>";
        } else {
            // 注册失败
            echo "<script>alert('注册失败，请稍后再试。');</script>";
        }
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册页面</title>
    <link rel="stylesheet" href="css/login_user.css">
</head>
<body>
    <div class="box">
        <div class="login">
            <img src="images/NJUPT.png" alt="">
            <form method="POST" action="register.php">
                <h3>用户注册</h3>
                <input type="text" name="username" placeholder="请输入您的账号" required>
                <input type="password" name="password" placeholder="请输入您的密码" required>
                <div>
                    <button type="submit">注册</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
