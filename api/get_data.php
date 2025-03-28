<?php
header('Content-Type: application/json');

// 错误处理配置
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 数据库连接到 mydb
$conn = new mysqli("localhost", "root", "han040423", "mydb");
if ($conn->connect_error) {
    http_response_code(500);
    error_log("Database connection failed: " . $conn->connect_error);
    die(json_encode(["error" => "Database connection failed"]));
}

try {
    // 获取最新的监控范围（从 mydb 数据库中的 chosen_moniters 表中获取最新记录）
    $monitorResult = $conn->query("SELECT username FROM chosen_moniters ORDER BY id DESC LIMIT 1");
    if (!$monitorResult) {
        throw new Exception("Failed to fetch monitor data: " . $conn->error);
    }
    
    $monitorData = $monitorResult->fetch_assoc();
    if (!$monitorData) {
        throw new Exception("No monitor data found.");
    }
    
    // 解析 username 字段，它是一个 JSON 格式的字符串
    $userIds = json_decode($monitorData['username']);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON format in username: " . json_last_error_msg());
    }
    
    error_log("Fetched user IDs: " . implode(", ", $userIds));  // 输出用户ID列表，用于调试
    
    // 获取用户的用户名（根据 user_id 从 mydb 数据库中的 users 表获取）
    $usernames = [];
    foreach ($userIds as $userId) {
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query for user ID $userId: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $usernames[] = $row['username'];
        } else {
            error_log("No username found for user ID $userId");
        }
    }

    if (empty($usernames)) {
        throw new Exception("No valid users found.");
    }

    error_log("Fetched usernames: " . implode(", ", $usernames));  // 输出用户名列表，用于调试

    // 切换数据库到 arterial_oxygen_monitor 获取每个用户的数据
    // 修改后的数据获取部分（约第53行开始）
$conn->select_db("arterial_oxygen_monitor");

$userData = [];
foreach ($usernames as $user) {
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
    $stmt->bind_param("s", $user);
    
    // ...保持原有错误处理逻辑...





        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query for user $user: " . $stmt->error);
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
        
        $userData[$user] = $data; // 不再反转数组
    }
    
    echo json_encode($userData);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error: " . $e->getMessage());  // 输出异常信息到日志
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    $conn->close();
}
?>