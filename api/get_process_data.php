<?php
header('Content-Type: application/json');

// 错误处理配置
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "han040423", "mydb");
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["error" => "Database connection failed"]));
}

try {
    // 获取监控用户列表
    $monitorResult = $conn->query("SELECT username FROM chosen_moniters ORDER BY id DESC LIMIT 1");
    if (!$monitorResult || $monitorResult->num_rows === 0) {
        throw new Exception("No monitor data found");
    }
    
    // 解析监控用户ID
    $userIds = json_decode($monitorResult->fetch_assoc()['username']);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid user ID format");
    }

    // 获取用户名映射
    $userMap = [];
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id IN (".implode(',', array_fill(0, count($userIds), '?')).")");
    $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
    $stmt->execute();
    foreach ($stmt->get_result() as $row) {
        $userMap[$row['id']] = $row['username'];
    }

    // 切换到氧饱和度数据库
    $conn->select_db("arterial_oxygen_monitor");

    // 获取四通道数据（优化后的单次查询）
    $finalData = [];
    foreach ($userMap as $userId => $username) {
        $stmt = $conn->prepare("
            SELECT 
                UNIX_TIMESTAMP(timestamp) * 1000 AS timestamp_ms,
                red_value,
                ired_value,
                ired_process,
                red_process
            FROM red_data 
            WHERE people = ?
            ORDER BY timestamp DESC
            LIMIT 500
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        $dataset = [
            'red_value' => [],
            'ired_value' => [],
            'red_process' => [],
            'ired_process' => []
        ];

        while ($row = $result->fetch_assoc()) {
            $dataset['red_value'][] = [
                'x' => (int)$row['timestamp_ms'],
                'y' => (float)$row['red_value']
            ];
            $dataset['red_process'][] = [
                'x' => (int)$row['timestamp_ms'],
                'y' => (float)$row['red_process']
            ];
            $dataset['ired_value'][] = [
                'x' => (int)$row['timestamp_ms'],
                'y' => (float)$row['ired_value']
            ];
            $dataset['ired_process'][] = [
                'x' => (int)$row['timestamp_ms'],
                'y' => (float)$row['ired_process']
            ];

        }

        // 按时间升序排列（适配图表渲染）
        foreach ($dataset as &$channel) {
            $channel = array_reverse($channel);
        }

        $finalData[$username] = $dataset;
    }

    echo json_encode($finalData);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    $conn->close();
}
?>