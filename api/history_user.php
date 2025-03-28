<?php
// get_data_user.php 修改后
header('Content-Type: application/json');
session_start();

// 错误处理配置
ini_set('display_errors', 0);
error_reporting(0);

// 验证用户登录
if (!isset($_SESSION["username"])) {
    http_response_code(401);
    die(json_encode(["error" => "未经授权的访问"]));
}

// 数据库连接
$conn = new mysqli("localhost", "root", "han040423", "arterial_oxygen_monitor");
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["error" => "数据库连接失败"]));
}

try {
    $currentUser = $_SESSION["username"];
    $dateFilter = isset($_GET['date']) ? $_GET['date'] : null;

    // 构建SQL查询
    $sql = "SELECT 
            UNIX_TIMESTAMP(timestamp) * 1000 AS timestamp_ms,
            spo2,
            status
        FROM red_data 
        WHERE people = ?";
    
    // 添加日期过滤条件
    if ($dateFilter) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
            throw new Exception("日期格式无效");
        }
        $sql .= " AND DATE(timestamp) = ?";
    }

    // 根据查询类型排序
    $sql .= $dateFilter ? " ORDER BY timestamp ASC" : " ORDER BY timestamp DESC LIMIT 2000";

    $stmt = $conn->prepare($sql);
    
    // 动态绑定参数
    if ($dateFilter) {
        $stmt->bind_param("ss", $currentUser, $dateFilter);
    } else {
        $stmt->bind_param("s", $currentUser);
    }

    if (!$stmt->execute()) {
        throw new Exception("查询执行失败: " . $stmt->error);
    }
    
    // 处理结果集
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'timestamp' => (int)$row['timestamp_ms'],
            'spo2' => round((float)$row['spo2'], 2),
            'status' => $row['status']
        ];
    }

    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    $conn->close();
}
?>
