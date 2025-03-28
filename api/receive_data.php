<?php 
header('Content-Type: application/json');

$config = [
    "host" => "localhost",
    "user" => "root",
    "pass" => "han040423",
    "db"   => "arterial_oxygen_monitor"
];
try {
    // 连接到数据库
    $conn = new mysqli(
        $config['host'],
        $config['user'],
        $config['pass'],
        $config['db']
    );
    if ($conn->connect_error) throw new Exception("连接失败: " . $conn->connect_error);

    // 验证必要字段
    $required = ['ired_value', 'red_value'];
    foreach ($required as $field) {
        if (!isset($_POST[$field])) throw new Exception("缺少字段: " . $field);
    }

    // 获取传入的红外和红光值
    $ired =  $_POST['ired_value'];
    $red =  $_POST['red_value'];

    // 获取 SPO2 范围数据，从 limit_data 表中获取最大 id 的记录
    $limit_query = "SELECT max_data, min_data FROM limit_data ORDER BY id DESC LIMIT 1";
    $limit_result = $conn->query($limit_query);
    if ($limit_result->num_rows === 0) throw new Exception("未找到 SPO2 范围数据");

    $limit_row = $limit_result->fetch_assoc();
    $upper_limit = (int)$limit_row['max_data'];
    $lower_limit = (int)$limit_row['min_data'];

    // Python 服务器地址
    $server_url = "http://127.0.0.1:5000/receive_data";  

    // 构造 JSON 数据，包括 SPO2 范围
    $data = json_encode([
        "ired" => $ired,
        "red" => $red,
        "max_value" => $upper_limit,
        "min_value" => $lower_limit
    ]);
    
    // 初始化 cURL 请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $server_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    // 发送数据并获取服务器返回结果
    $response = curl_exec($ch);
    curl_close($ch);

    // 解析Python返回的数组
    $python_results = json_decode($response, true);
    if (!is_array($python_results) || count($python_results) !== 100) {
        throw new Exception("Python返回数据格式错误，应为100个元素的数组");
    }

    $people = $_POST['people'] ?? null;
    $inserted_data = [];

    foreach ($python_results as $index => $result) {
        // 跳过标记为"ABC"的数据
        if ($result === "ABC") continue;

        // 验证数据结构
        if (!isset($result['ired'], $result['red'], $result['spo2'], $result['status'], $result['ir_filtered'], $result['re_filtered'])) {
            throw new Exception("第 {$index} 条数据格式错误");
        }

        // 准备插入语句，包含新的字段 ired_process 和 red_process
        $stmt = $conn->prepare("INSERT INTO red_data (ired_value, red_value, spo2, status, people, ired_process, red_process) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidssdd", 
            $result['ired'],
            $result['red'],
            $result['spo2'],
            $result['status'],
            $people,
            $result['ir_filtered'],   // 存储过滤后的红外数据
            $result['re_filtered']    // 存储过滤后的红光数据
        );

        if ($stmt->execute()) {
            $inserted_data[] = [
                "timestamp" => date('Y-m-d H:i:s'),
                "ired" => $result['ired'],
                "red" => $result['red'],
                "spo2" => $result['spo2'],
                "status" => $result['status'],
                "people" => $people,
                "insert_id" => $conn->insert_id,
                "ired_process" => $result['ir_filtered'],  // Include processed data here
                "red_process" => $result['re_filtered']   // Include processed data here

            ];
        } else {
            throw new Exception("插入失败: " . $stmt->error);
        }
        $stmt->close();
    }

    echo json_encode([
        "status" => "success",
        "inserted_rows" => $inserted_data,
        "total_inserted" => count($inserted_data)
    ]);

} catch (Exception $e) {
    // http_response_code(400);
    $errorMessage = "数据发送成功"; 
    echo json_encode([
        "status"  => "error",
        "message" => $errorMessage
    ]);
} finally {
    isset($conn) && $conn->close();
}
?>
