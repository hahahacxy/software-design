<?php
session_start();
header('Content-Type: application/json');

// 验证会话
if (!isset($_SESSION["username"])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

// 接收数据
$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['newNickname'])) {
    echo json_encode(['success' => false, 'message' => '昵称不能为空']);
    exit;
}

// 数据库连接
$conn = new mysqli("localhost", "root", "han040423", "mydb");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}
$conn->set_charset("utf8mb4");

// 更新昵称
$stmt = $conn->prepare("UPDATE users SET nicheng = ? WHERE username = ?");
$stmt->bind_param("ss", $data['newNickname'], $_SESSION["username"]);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => '数据库更新失败']);
}

$stmt->close();
$conn->close();
?>
