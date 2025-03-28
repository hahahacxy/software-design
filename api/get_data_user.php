<?php
header('Content-Type: application/json');
session_start();  // 启用会话

// 错误处理配置
ini_set('display_errors', 0);
error_reporting(0);

// 验证用户登录状态
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
    $currentUser = $_SESSION["username"];  // 从会话获取用户名

    // 获取当前用户数据
    $stmt = $conn->prepare("
        SELECT 
            UNIX_TIMESTAMP(timestamp) * 1000 AS timestamp_ms,
            spo2,
            status
        FROM (
            SELECT 
                @row := @row + 1 AS row_num,
                rd.*
            FROM red_data rd, (SELECT @row := 0) r
            WHERE people = ? 
            ORDER BY timestamp DESC
            LIMIT 2000  -- 扩大数据范围保证能取到足够数据
        ) AS tmp 
        WHERE row_num % 50 = 1  -- 每50条取第1条
        ORDER BY timestamp DESC
        LIMIT 40  -- 最终获取50条
    ");
    $stmt->bind_param("s", $currentUser);
    
    if (!$stmt->execute()) {
        throw new Exception("查询执行失败: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'timestamp' => (int)$row['timestamp_ms'],
            'spo2' => (float)$row['spo2'],
            'status' => $row['status']
        ];
    }

    // 返回格式改为单个用户的数据数组
    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "服务器错误: " . $e->getMessage()]);
} finally {
    $conn->close();
}
?>
