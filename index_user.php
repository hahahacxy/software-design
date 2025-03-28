<?php
session_start();
$dbhost = "localhost";
$dbuser = "root";
$dbpass = "han040423";
$conn = mysqli_connect($dbhost, $dbuser, $dbpass);

if (!$conn) {
    error_log("数据库错误: " . mysqli_connect_error());
    die("系统错误，请联系管理员");
}

mysqli_select_db($conn, "mydb");
mysqli_query($conn, "set names 'utf8'");

// 获取当前用户信息
$user_check = $_SESSION["username"];
$user_check_sql = "SELECT username, nicheng FROM users WHERE username = '{$user_check}'";
$user_check_result = mysqli_query($conn, $user_check_sql);
$row = mysqli_fetch_array($user_check_result, MYSQLI_ASSOC);

if(!isset($row["username"])){
    header("Location: http://172.20.10.4/hello.html");
    exit();
}

// 获取最新阈值配置（修改后的部分）
$threshold_sql = "SELECT max_data, min_data FROM limit_data ORDER BY id DESC LIMIT 1";
$threshold_result = mysqli_query($conn, $threshold_sql);
$threshold_row = mysqli_fetch_assoc($threshold_result);

$thresholds = [
    'max_data' => $threshold_row['max_data'] ?? 99,
    'min_data' => $threshold_row['min_data'] ?? 95
];

mysqli_close($conn);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $row["username"]; ?>的实时监测</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.bootcdn.net/ajax/libs/echarts/5.4.2/echarts.min.js"></script>
    <link rel="stylesheet" href="css/index_user.css">
</head>
<body>
    <header>
        <img src="images/NJUPT.svg" alt="NJUPT Logo" class="logo" style="background-color: transparent;">
        <nav>
            <ul class="nav_links">
                <!-- <li><strong>登陆用户：<?php echo $row["username"]; ?></strong></li> -->
                <li><a href="index_user.php"><strong>实时监测</strong></a></li>
                <li><a href="index_history_user.php">历史数据</a></li>
            </ul>
        </nav>
        <button class="contact_button"><a href="contact.php" class="cta">联系我们</a></button>
        <div class="yh">
            <div class="yh1">
                昵称：<?php echo $row["nicheng"]; ?>  <a href="javascript:void(0);">修改</a>
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
        <div class="row">
            <div class="col">
                <div id="waveChart" style="height:500px;"></div>
                <div class="card mt-2">
                    <div class="card-body">
                        <h5 class="card-title">用户<?php echo $row["username"]; ?></h5>
                        <p id="spo2Value" class="fs-5">血氧：--%</p>
                        <div id="alertBox" class="alert alert-danger d-none"></div>
                    </div>
                </div>
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
            document.querySelector('.yh1').innerHTML = `昵称：${newName}  <a href="javascript:showModal()">修改</a>`;
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


    // PHP变量传递（修改后的部分）
    const currentUser = '<?php echo $row["username"]; ?>';
    const defaultThresholds = {
        max_data: <?php echo $thresholds['max_data']; ?>,
        min_data: <?php echo $thresholds['min_data']; ?>
    };

    async function initDashboard() {
        try {
            // 动态获取最新阈值
            const thresholdResponse = await fetch('/api/get_limit.php');
            const thresholds = await thresholdResponse.json();
            
            initChart(thresholds);
        } catch (error) {
            console.error('阈值获取失败，使用默认值:', error);
            initChart(defaultThresholds);
        }

        // 启动数据更新
        await fetchData();
        setInterval(fetchData, 200);
    }
    function updateStatusDisplay(userData) {
        if (userData.length > 0) {
            const latest = userData[0];
            const spo2Element = document.getElementById('spo2Value');
            const alertBox = document.getElementById('alertBox');
            
            spo2Element.textContent = `血氧：${latest.spo2.toFixed(2)}%`;
            spo2Element.style.color = latest.status === 'abnormal' ? '#dc3545' : '#28a745';
            
            if (latest.status === 'abnormal') {
                alertBox.classList.remove('d-none');
                alertBox.innerHTML = `异常警告！当前血氧：${latest.spo2}%`;//<br>
                //（正常范围: ${thresholds.min_data}%-${thresholds.max_data}%)
            } else {
                alertBox.classList.add('d-none');
            }
        }
    }


    function initChart(thresholds) {
        const chart = echarts.init(document.getElementById('waveChart'));
        const option = {
            title: { 
                text: `${currentUser} 的血氧波形图`,
                subtext: `警报阈值: 最大 ${thresholds.max_data}% | 最小 ${thresholds.min_data}%`
            },
            xAxis: { type: 'time' },
            yAxis: { 
                min: 85, 
                max: 100,
                axisLabel: {
                    formatter: '{value}%'
                }
            },
            series: [{ 
                type: 'line',
                smooth: true,
                data: [],
                animation: false,
                progressive: 1000,
                markLine: {
                    data: [
                        createThresholdMarker(thresholds.max_data, '#228b22', '最大阈值'),
                        createThresholdMarker(thresholds.min_data, '#b22222', '最小阈值')
                    ],
                    symbol: 'none'
                }
            }],
            tooltip: {
                trigger: 'axis',
                formatter: function(params) {
                    const dataPoint = params;
                    const timestamp = dataPoint.value;  // 时间戳在第一个位置
                    const spo2Value = dataPoint.value;    // 血氧值在第二个位置
                     const date = new Date(timestamp);
                    // return `时间: ${date.toLocaleString()}<br/>血氧: ${spo2Value}%`;
                }
            }
        };

        chart.setOption(option);
        window.currentChart = chart;
    }

    function createThresholdMarker(value, color, name) {
        return {
            yAxis: value,
            lineStyle: { 
                type: 'dashed', 
                width: 3, 
                color: color 
            },
            label: {
                show: true,
                position: 'end',
                formatter: name,
                color: color,
                backgroundColor: 'rgba(255,255,255,0.8)',
                padding: [2, 5]
            }
        };
    }

    async function fetchData() {
        try {
            const res = await fetch(`/api/get_data_user.php?user=${encodeURIComponent(currentUser)}`);
            const userData = await res.json();
            updateStatusDisplay(userData);
            if (window.currentChart) {
                window.currentChart.setOption({
                    series: [{
                        data: userData.map(item => [parseInt(item.timestamp),  // 强制转换为数字
                        Number(item.spo2.toFixed(2)) ])
                    }]
                });
            }

        
        } catch (error) {
            console.error('数据获取失败:', error);
            document.getElementById('spo2Value').textContent = '血氧：数据获取失败';
        }
    }

    
    // 初始化仪表盘
    initDashboard();
    </script>
</body>
</html>