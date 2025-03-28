<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理设置</title>
    <link rel="stylesheet" href="css/index_admin.css">
</head>
<body>

<!-- 导航栏 -->
<header>
    <img src="images/NJUPT.svg" alt="logo" class="logo">
    <div class="biaoti">
        <h1>管理设置</h1>
    </div>
</header>

<div class="container">

    <!-- 左侧部分：血氧阈值设置 & 监控范围设置 -->
    <div class="left-section">
        <h2>血氧阈值设置</h2>

        <?php
        $config = [
            "host" => "localhost",
            "user" => "root",
            "pass" => "han040423",
            "db"   => "arterial_oxygen_monitor"
        ];

        $max_data = "";
        $min_data = "";
        $message = "";

        try {
            $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['db']);
            if ($conn->connect_error) throw new Exception("数据库连接失败: " . $conn->connect_error);

            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                if (isset($_POST['max_data'], $_POST['min_data'])) {
                    $max_data = floatval($_POST['max_data']);
                    $min_data = floatval($_POST['min_data']);
                    if ($min_data >= $max_data) throw new Exception("无效的阈值: min_data 必须小于 max_data");

                    $stmt = $conn->prepare("INSERT INTO limit_data (max_data, min_data) VALUES (?, ?)");
                    $stmt->bind_param("dd", $max_data, $min_data);
                    if ($stmt->execute()) $message = "血氧阈值设置成功！";
                    $stmt->close();
                }
            }

            $result = $conn->query("SELECT max_data, min_data FROM limit_data ORDER BY id DESC LIMIT 1");
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $max_data = $row["max_data"];
                $min_data = $row["min_data"];
            }

        } catch (Exception $e) {
            echo "<p class='error'>错误: " . $e->getMessage() . "</p>";
        } finally {
            $conn->close();
        }
        ?>

        <!-- 血氧阈值设置 -->
        <form method="post">
            <label for="max_data">血氧正常值上限：</label>
            <input type="number" step="0.01" id="max_data" name="max_data" required value="<?= $max_data ?>">

            <label for="min_data">血氧正常值下限：</label>
            <input type="number" step="0.01" id="min_data" name="min_data" required value="<?= $min_data ?>">

            <button type="submit">提交</button>
        </form>

        <!-- 监控范围设置 -->
        <h2>监控范围设置</h2>
        <form method="post">
            <label>请选择需要监控的用户：</label>
            <div class="checkbox-group">
                <?php
                $config_mydb = [
                    "host" => "localhost",
                    "user" => "root",
                    "pass" => "han040423",
                    "db"   => "mydb"
                ];

                try {
                    $conn = new mysqli($config_mydb['host'], $config_mydb['user'], $config_mydb['pass'], $config_mydb['db']);
                    if ($conn->connect_error) throw new Exception("数据库连接失败: " . $conn->connect_error);

                    $result = $conn->query("SELECT id, username FROM users");
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<label><input type='checkbox' name='monitor_users[]' value='{$row['id']}'> {$row['username']}</label><br>";
                        }
                    } else {
                        echo "<p>暂无用户</p>";
                    }

                } catch (Exception $e) {
                    echo "<p class='error'>错误: " . $e->getMessage() . "</p>";
                } finally {
                    $conn->close();
                }
                ?>
            </div>

            <!-- 监控选项 -->
            <div>
                <label><input type="radio" name="monitor_option" value="enable" checked> 启用监控</label>
                <label><input type="radio" name="monitor_option" value="disable"> 不监控</label>
            </div>

            <!-- 提交按钮 -->
            <div>
                <button type="submit" name="submit_monitor">提交监控设置</button>
            </div>
        </form>
    </div>

    <!-- 右侧部分：监控设备列表 -->
    <div class="right-section">
    <h2>监控设备列表</h2>
    <?php
    $config_mydb = [
        "host" => "localhost",
        "user" => "root",
        "pass" => "han040423",
        "db"   => "mydb"
    ];
    
    $conn = new mysqli($config_mydb['host'], $config_mydb['user'], $config_mydb['pass'], $config_mydb['db']);
    if ($conn->connect_error) {
        echo "<p class='error'>数据库连接失败: " . $conn->connect_error . "</p>";
    } else {
        $result = $conn->query("SELECT id, login_time, kind, login_name, login_ip FROM online WHERE login_time >= NOW() - INTERVAL 3 HOUR ORDER BY login_time DESC");
        
        if ($result->num_rows > 0) {
            echo "<ul style='max-height: 400px; overflow-y: auto; padding: 10px; background: #fff; border: 1px solid #ccc; border-radius: 5px; height: 60vh;'>";
            while ($row = $result->fetch_assoc()) {
                echo "<li>[{$row['login_time']}] {$row['kind']} - {$row['login_name']} ({$row['login_ip']})</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>最近3小时内无设备在线</p>";
        }
    }
    $conn->close();
    ?>
</div>


<?php
// 处理监控范围设置
if (isset($_POST['submit_monitor'])) {
    $monitor_option = $_POST['monitor_option'];
    $monitor_users = isset($_POST['monitor_users']) ? $_POST['monitor_users'] : [];

    // 判断是否禁用监控
    $monitor_users_json = ($monitor_option == 'disable') ? json_encode([]) : json_encode($monitor_users);

    $config_mydb = [
        "host" => "localhost",
        "user" => "root",
        "pass" => "han040423",
        "db"   => "mydb"
    ];

    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli($config_mydb['host'], $config_mydb['user'], $config_mydb['pass'], $config_mydb['db']);

        if ($conn->connect_error) throw new Exception("数据库连接失败: " . $conn->connect_error);

        //调试输出检查数据
        // echo "<pre>监控用户 JSON: $monitor_users_json</pre>";

        $stmt = $conn->prepare("INSERT INTO chosen_moniters (username) VALUES (?)");
        $stmt->bind_param("s", $monitor_users_json);
        $stmt->execute();
        // if ($stmt->execute()) {
        //     echo "<p class='success'>监控设置成功！</p>";
        // } else {
        //     echo "<p class='error'>SQL 执行错误: " . $stmt->error . "</p>";
        // }

        $stmt->close();
    } catch (Exception $e) {
        echo "<p class='error'>错误: " . $e->getMessage() . "</p>";
    } finally {
        $conn->close();
    }
}
?>


</body>
</html>
