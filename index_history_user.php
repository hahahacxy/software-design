<?php
session_start();
//echo $_SESSION["username"];
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
$user_check_sql = "SELECT username, nicheng FROM users WHERE username = '{$user_check}'";
$user_check_result = mysqli_query($conn, $user_check_sql);
$row = mysqli_fetch_array($user_check_result, MYSQLI_ASSOC);
$login_session = $row["username"];
if(!isset($login_session)){
    header("Location: http://172.20.10.4/hello.html");
    exit();
}
 mysqli_close($conn);//新加
?>


<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $login_session; ?>的血氧监测</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.bootcdn.net/ajax/libs/echarts/5.4.2/echarts.min.js"></script>
    <link rel="stylesheet" href="css/index_history_user.css">
</head>
<body>
    <header>
        <img src="images/NJUPT.svg" alt="" class="logo" alt="logo" style="background-color: transparent;">
        <nav>
            <ul class="nav_links">
                <li><a href="index_user.php">实时监测</a></li>
                <li><a href="index_history_user.php"><strong>历史数据</strong></a></li>
            </ul>
        </nav>
        <button class="contact_button"><a href="contact.php" class="cta">联系我们</a></button>
        <div class="yh">
            <div class="yh1">
                登陆昵称：<?php echo $row["nicheng"]; ?>  <a href="javascript:void(0);">修改</a>
            </div>
            <div class="yh2">
                登陆ID：<?php echo $row["username"]; ?>
            </div>
        </div>
    
    </header>
    <!-- 在header部分之后添加 -->
<div id="nicknameModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>修改昵称</h3>
        <input type="text" id="newNickname" placeholder="输入新昵称">
        <div>
            <button onclick="closeModal()">取消</button>
            <button onclick="submitNickname()">提交</button>
        </div>
    </div>
</div>
    <div class="container mt-4">
        <!-- 添加日期选择控件 -->
        <div class="row mb-3">
            <div class="col-md-4">
                <input type="date" id="datePicker" class="form-control">
                <button type="button" onclick="loadHistoricalData()" class="date_button">查询历史数据</button>
            </div>
        </div>

        <!-- 监测面板 -->
        <div class="row">
            <div class="col">
                <div id="waveChart" style="height:300px;"></div>
                <!-- <div class="card mt-2">
                    <div class="card-body">
                        <h5 class="card-title">用户<?php echo $login_session; ?></h5>
                        <p id="spo2Value" class="fs-5">血氧：--%</p>
                        <div id="alertBox" class="alert alert-danger d-none"></div>
                    </div>
                </div>-->
            </div>
        </div>
    </div>

<script>
// 显示弹窗
function showModal() {
    document.getElementById('nicknameModal').style.display = 'block';
    document.body.insertAdjacentHTML('beforeend', '<div class="modal-backdrop"></div>');
}

// 关闭弹窗
function closeModal() {
    document.getElementById('nicknameModal').style.display = 'none';
    document.querySelector('.modal-backdrop').remove();
}

// 提交修改
async function submitNickname() {
    const newName = document.getElementById('newNickname').value.trim();
    
    try {
        const response = await fetch('/api/update_nickname.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                username: '<?php echo $login_session; ?>',
                newNickname: newName
            })
        });

        const result = await response.json();
        
        if (result.success) {
            // 更新页面显示
            document.querySelector('.yh1').innerHTML = `登陆昵称：${newName}  <a href="javascript:showModal()">修改</a>`;
            closeModal();
            alert('修改成功！');
        } else {
            throw new Error(result.message || '修改失败');
        }
    } catch (error) {
        alert('修改失败: ' + error.message);
    }
}
//修改原链接调用方式
document.querySelector('.yh1 a').addEventListener('click', function(e) {
    e.preventDefault();
    showModal();
});



// 新增变量
let refreshInterval;
const currentUser = '<?php echo $login_session; ?>';

// 初始化仪表盘
async function initDashboard() {
    const chart = echarts.init(document.getElementById('waveChart'));
    chart.setOption({
        title: { text: `${currentUser} 的历史检测血氧波形图` },
        xAxis: { type: 'time' },
        yAxis: { min: 85, max: 100 },
        series: [{ 
            type: 'line',
            smooth: true,
            data: [],
            animation: false,
            progressive: 1000
        }]
    });
    window.currentChart = chart;
    startRealtimeMonitoring();
}

// 启动实时监测
function startRealtimeMonitoring() {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(fetchRealtimeData, 200);
    document.getElementById('datePicker').value = ''; // 清空日期选择
}

// 获取实时数据
async function fetchRealtimeData() {
    try {
        const res = await fetch(`/api/history_user.php`);
        const data = await res.json();
        updateDisplay(data);
    } catch (error) {
        console.error('实时数据获取失败:', error);
    }
}

// 加载历史数据
async function loadHistoricalData() {
    const selectedDate = document.getElementById('datePicker').value;
    if (!selectedDate) {
        alert('请选择查询日期');
        return;
    }
    
    if (refreshInterval) clearInterval(refreshInterval);
    
    try {
        const res = await fetch(`/api/history_user.php?date=${selectedDate}`);
        const data = await res.json();
        updateDisplay(data);
    } catch (error) {
        console.error('历史数据获取失败:', error);
    }
}

// 更新显示
function updateDisplay(data) {
    if (window.currentChart) {
        window.currentChart.setOption({
            series: [{
                data: data.map(item => [item.timestamp, item.spo2])
            }]
        });
    }

    /*if (data.length > 0) {
        const latest = data[data.length - 1];
        document.getElementById('spo2Value').textContent = `血氧：${latest.spo2}%`;
        
        const alertBox = document.getElementById('alertBox');
        alertBox.classList.toggle('d-none', latest.status !== 'abnormal');
        if (latest.status === 'abnormal') {
            alertBox.textContent = `异常警告！历史血氧：${latest.spo2}%`;
        }
    }*/
}



// 初始化
initDashboard();
</script>
</body>
</html>
