<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$dbname = 'arterial_oxygen_monitor';  // 确保数据库名称正确
$user = 'root';
$pass = 'han040423';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT max_data, min_data FROM limit_data ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            'max_data' => (float)$row['max_data'],
            'min_data' => (float)$row['min_data']
        ]);
    } else {
        echo json_encode([
            'max_data' => 99,
            'min_data' => 95
        ]);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'max_data' => 99,
        'min_data' => 95
    ]);
}
?>