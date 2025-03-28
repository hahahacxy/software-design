<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="css/login_user.css">
</head>
<body>
<?php
// 启用错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (isset($_POST["login"])) {
    $dbhost = "localhost";
    $dbuser = "root";
    $dbpass = "han040423";

    // 连接数据库
    $conn = new mysqli($dbhost, $dbuser, $dbpass, "mydb");

    if ($conn->connect_error) {
        error_log("数据库连接失败: " . $conn->connect_error);
        die("系统错误，请联系管理员");
    }

    // 设置字符集
    $conn->set_charset("utf8");

    $username = $_POST["username"];
    $password = $_POST["password"];

    // 预处理 SQL 语句，防止 SQL 注入
    $stmt = $conn->prepare("SELECT * FROM supervisor WHERE supervisorname = ? AND password_supervisor = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $user_result = $stmt->get_result();

    if ($user_result->num_rows == 1) {
        // 获取用户的 IPv4 地址
        $login_ip = $_SERVER['REMOTE_ADDR'];

        // 插入 online 表
        $insert_stmt = $conn->prepare("INSERT INTO online (login_time, kind, login_name, login_ip) VALUES (NOW(), 'supervisor', ?, ?)");
        $insert_stmt->bind_param("ss", $username, $login_ip);
        $insert_stmt->execute();
        // 启动会话并存储用户名
        session_start();
        $_SESSION["supervisor_name"] = $username;
        // 跳转到主页
        header("Location: http://172.20.10.4/index_supervisor.php");
        exit;
    } else {
        echo "<script>
            alert('用户名或密码无效!');
            window.location.href = 'http://172.20.10.4/login_supervisor.php';
        </script>";
    }

    // 关闭连接
    $stmt->close();
    $insert_stmt->close();
    $conn->close();
}
?>

    <div class="box">
        <div class="login">
            <img src="images/NJUPT.png" alt="">
            <form action="" method="POST">
                <h3>监测员登录</h3>
                <input type="text" name="username" placeholder="请输入您的账号" required>
                <input type="password" name="password" placeholder="请输入您的密码" required>
                <div>
                    <button type="submit" name="login"> 登录 </button>
                </div>
                
            </form>
        </div>
    </div>
</body>
</html>
