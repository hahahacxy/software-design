<?php
session_start();

echo $_SESSION["username"];

$dbhost="localhost";
$dbuser="root";
$dbpass ="han040423";
$conn = mysqli_connect($dbhost, $dbuser, $dbpass); 
if (!$conn) {
    error_log("数据库错误: " . mysqli_connect_error()); 
    die("系统错误，请联系管理员"); 
}
mysqli_select_db($conn, "mydb");
mysqli_query($conn,"set names 'utf8'");

$user_check = $_SESSION["username"];
$user_check_sql ="SELECT * FROM users WHERE username = '{$user_check}'";
$user_check_result = mysqli_query($conn, $user_check_sql);
$row = mysqli_fetch_array($user_check_result, MYSQLI_ASSOC);
$login_session = $row["username"];
if (!isset($login_session)) {
    header("Location: http://172.20.10.4/hello.html");
    exit();
}

// 获取chosen_moniters表中最新记录的用户ID
$chosen_moniters_sql = "SELECT username FROM chosen_moniters ORDER BY id DESC LIMIT 1";
$chosen_moniters_result = mysqli_query($conn, $chosen_moniters_sql);
$chosen_moniters_row = mysqli_fetch_array($chosen_moniters_result, MYSQLI_ASSOC);
$selected_user_ids = json_decode($chosen_moniters_row['username'], true); // 获取监控用户的ID数组

// 获取监控用户的用户名列表
$placeholders = implode(',', array_fill(0, count($selected_user_ids), '?'));
$get_usernames_sql = "SELECT username FROM users WHERE id IN ($placeholders)";
$stmt = mysqli_prepare($conn, $get_usernames_sql);
mysqli_stmt_bind_param($stmt, str_repeat('i', count($selected_user_ids)), ...$selected_user_ids);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$selected_usernames = [];
while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
    $selected_usernames[] = $row['username']; // 收集所有被选中的用户名
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>多用户实时监测</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.bootcdn.net/ajax/libs/echarts/5.4.2/echarts.min.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="row row-cols-1 row-cols-md-3 g-4" id="userContainer">
            <!-- 用户卡片将由JavaScript动态生成 -->
        </div>
    </div>

    <script>
    // 主初始化函数（异步函数）
    async function initDashboard() {
        const container = document.getElementById('userContainer');
        
        // 获取管理员选择的监控用户（从服务器获取）
        try {
            const initRes = await fetch('/api/get_data.php');
            if (!initRes.ok) {
                throw new Error('获取监控用户列表失败');
            }
            const selectedUsers = await initRes.json();  // 返回的是用户的列表

            console.log('已选择的监控用户:', selectedUsers);  // 调试输出

            if (selectedUsers.length === 0) {
                alert('没有选定用户进行监控');
            }

            // 遍历所有选中的用户名
            selectedUsers.forEach((user, index) => {
                const userId = index + 1;
                
                const chartDiv = document.createElement('div');
                chartDiv.className = 'col';
                chartDiv.innerHTML = `
                    <div id="waveChart${userId}" style="height:300px;"></div>
                    <div class="card mt-2">
                        <div class="card-body">
                            <h5 class="card-title">用户${user}</h5>
                            <p id="spo2Value${userId}" class="fs-5">血氧：--%</p>
                            <div id="alertBox${userId}" class="alert alert-danger d-none"></div>
                        </div>
                    </div>
                `;
                container.appendChild(chartDiv);

                const chart = echarts.init(document.getElementById(`waveChart${userId}`));
                chart.setOption({
                    title: { text: `用户${user}的血氧波形图` },
                    xAxis: { type: 'time' },
                    yAxis: { min: 90, max: 105 },
                    series: [{ type: 'line', smooth: true, data: [], animation: false, progressive: 1000 }]
                });

                window.charts = window.charts || [];
                window.charts[userId] = chart;

                console.log(`图表初始化完成: 用户${user}`); // 调试输出
            });

        } catch (error) {
            console.error('初始化监控用户时出错:', error);
            alert('初始化监控用户时出错: ' + error.message);
        }

        setInterval(fetchData, 200); // 每0.2秒更新一次数据
    }

    // 数据更新函数（异步）
    async function fetchData() {
        try {
            const res = await fetch('/api/get_selected_users_data.php');
            if (!res.ok) {
                throw new Error('获取用户数据失败');
            }
            const data = await res.json();

            console.log('接收到的数据:', data);  // 调试输出

            Object.entries(data).forEach(([userName, userData], index) => {
                const userId = index + 1;
                
                if (window.charts[userId]) {
                    window.charts[userId].setOption({
                        series: [{
                            data: userData.map(item => [item.timestamp, item.spo2])
                        }]
                    });
                }

                if (userData.length > 0) {
                    const latest = userData[userData.length - 1];
                    const spo2Element = document.getElementById(`spo2Value${userId}`);
                    const alertBox = document.getElementById(`alertBox${userId}`);
                    
                    spo2Element.textContent = `血氧：${latest.spo2}%`;
                    
                    if (latest.status === 'abnormal') {
                        alertBox.classList.remove('d-none');
                        alertBox.textContent = `异常警告！当前血氧：${latest.spo2}%`;
                    } else {
                        alertBox.classList.add('d-none');
                    }
                }
            });
        } catch (error) {
            console.error('获取数据时出错:', error);
            alert('获取数据时出错: ' + error.message);
        }
    }

    initDashboard();
    </script>
    <hr>
    <div style="border-top: 1px solid #000000;"></div>
</body>
</html>
