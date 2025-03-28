<?php
session_start(); // 保留Session用于Flash消息
ini_set('display_errors', 1); // 生产环境关闭错误显示

error_reporting(E_ALL);
// 启用 mysqli 异常报告
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ===> 删除CSRF验证代码块 <===
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     die('无效的请求');
    // }
    // ===> 

    $dbhost = "localhost";
    $dbuser = "root"; // 改用非 root 账户
    $dbpass = "han040423";
    $dbname = "mydb"; // 明确数据库名

    try {
        $conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
        $conn->set_charset("utf8mb4");

        // 过滤输入数据 ►
        $name = trim($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $message = trim($_POST['message'] ?? '');

        // 验证数据 ►
        if (empty($name) || strlen($name) > 50) {
            throw new Exception('姓名必须为1-50字符');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('邮箱格式错误');
        }
        if (empty($message) || strlen($message) > 500) {
            throw new Exception('留言内容为1-500字符');
        }


        // 插入数据 ►
        $stmt = $conn->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception('数据库准备语句失败: ' . $conn->error);
        }
        
        $stmt->bind_param("sss", $name, $email, $message);
        if (!$stmt->execute()) {
            throw new Exception('执行数据库操作失败: ' . $stmt->error);
        }
        // $stmt->execute();

        if ($stmt->affected_rows === 1) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => '提交成功'];
        } else {
            throw new Exception('提交失败，请重试');
        }

        $stmt->close();
        $conn->close();
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    } catch (Exception $e) {
        error_log($e->getMessage()); // 记录错误日志
        $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
}

// ===> 删除CSRF Token生成代码块 <===
// if (empty($_SESSION['csrf_token'])) {
//     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// }
// ===> 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - NJUPT</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/contact.css">
</head>
<body>
    <div class="logo-group">
        <img src="images/NJUPT.svg" 
             alt="NJUPT Logo" 
             class="university-logo">
             
        <!-- <img src="https://app.svg.la/generate/67d02a60eed66_20250311121944.svg"
             alt="Additional Logo"
             class="additional-logo"> -->
    </div>

    <div class="contact-container">
        <div class="contact-info">
            <h2>Contact Info</h2>
            <div class="item">
                <i class="icon fas fa-map-marker-alt"></i>
                <div>
                    <h3>地址</h3>
                    <a href="https://map.baidu.com/search/%E5%8D%97%E4%BA%AC%E9%82%AE%E7%94%B5%E5%A4%A7%E5%AD%A6(%E4%BB%99%E6%9E%97%E6%A0%A1%E5%8C%BA)/@13239107.110784499,3756074.13782975,15.69z?querytype=s&da_src=shareurl&wd=%E5%8D%97%E4%BA%AC%E9%82%AE%E7%94%B5%E5%A4%A7%E5%AD%A6(%E4%BB%99%E6%9E%97%E6%A0%A1%E5%8C%BA)&c=315&src=0&wd2=%E5%8D%97%E4%BA%AC%E5%B8%82%E6%A0%96%E9%9C%9E%E5%8C%BA&pn=0&sug=1&l=13&b=(13368833,3800100;13392833,3823492)&from=webmap&biz_forward=%7B%22scaler%22:2,%22styles%22:%22pl%22%7D&sug_forward=bbf5fedaa64e73a55abbbca2&device_ratio=2">南京邮电大学（仙林校区）：江苏省南京市仙林大学城文苑路9号</a>
                    <!-- <p>南京邮电大学（仙林校区）：江苏省南京市仙林大学城文苑路9号</p> -->
                </div>
            </div>
            
            <div class="item">
                <i class="icon fas fa-envelope"></i>
                <div>
                    <h3>团队成员-邹艺涵</h3>
                    <p>b22010201@njupt.edu.cn</p>
                </div>
            </div>
            
            <div class="item">
                <i class="icon fas fa-envelope"></i>
                <div>
                    <h3>团队成员-陈欣宇</h3>
                    <p>b22010203@njupt.edu.cn</p>
                </div>
            </div>
            
            <div class="item">
                <i class="icon fas fa-envelope"></i>
                <div>
                    <h3>团队成员-陆叶艳</h3>
                    <p>b22010202@njupt.edu.cn</p>
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <form class="contact-form" action="" method="POST">
            <h2>Contact Form</h2>
            <input type="text" name="name" placeholder="Your Name" required 
           pattern="[\u4e00-\u9fa5A-Za-z ]{2,50}" 
           title="请输入2-50个字符（中英文）">
    
            <input type="email" name="email" placeholder="Your Email" required
           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
    
            <textarea name="message" placeholder="Your Message" required
              minlength="10" maxlength="500"></textarea>
    
            <button type="submit" class="btn">Send Message</button>
        </form>
    </div>
<!-- 在</body>标签前添加以下代码 -->
<?php if (isset($_SESSION['flash']) && $_SESSION['flash']['type'] === 'success'): ?>
<script>
    alert("提交成功！感谢您的反馈。");
</script>
<?php unset($_SESSION['flash']); ?>
<?php endif; ?>

</body>
</html>
