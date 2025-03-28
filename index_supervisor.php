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
$user_check = $_SESSION["supervisor_name"];
$user_check_sql = "SELECT * FROM supervisor WHERE supervisorname = '{$user_check}'";
$user_check_result = mysqli_query($conn, $user_check_sql);
$row = mysqli_fetch_array($user_check_result, MYSQLI_ASSOC);

if (!isset($row["supervisorname"])) {
    header("Location: http://172.20.10.4/hello.html");
    exit();
}

// 获取最新阈值配置
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
    <title>多用户实时监测</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.bootcdn.net/ajax/libs/echarts/5.4.2/echarts.min.js"></script>
    <link rel="stylesheet" href="css/index_supervisor.css">
</head>
<body>
    <header>
        <img src="images/NJUPT.svg" alt="NJUPT Logo" class="logo" style="background-color: transparent;">
        <nav>
            <ul class="nav_links">
                <li><a href="index_supervisor.php"><strong>实时监测</strong></a></li>
                <li><a href="process_supervisor.php">数据处理</a></li>
            </ul>
        </nav>
    </header>

    <div class="container mt-4">
        <div class="row row-cols-1 row-cols-md-2 g-4" id="userContainer"></div>
    </div>

    <script>
    // 全局阈值配置
    let globalThresholds = {
        max_data: <?php echo $thresholds['max_data']; ?>,
        min_data: <?php echo $thresholds['min_data']; ?>
    };

    async function initDashboard() {
        // 销毁所有旧实例
if (window.charts) {
  Object.values(window.charts).forEach(chart => chart.dispose());
}
window.charts = [];
        try {
            // 动态获取最新阈值
            const thresholdRes = await fetch('/api/get_limit.php');
            const newThresholds = await thresholdRes.json();
            globalThresholds = {
                max_data: newThresholds.max_data || 99,
                min_data: newThresholds.min_data || 95
            };
        } catch (error) {
            console.error('阈值获取失败，使用默认值:', error);
        }

        const container = document.getElementById('userContainer');
        const initRes = await fetch('/api/get_data.php');
        const initData = await initRes.json();
        const users = Object.keys(initData);

        // 清空旧内容
        container.innerHTML = '';

        users.forEach((user, index) => {
            const userId = index + 1;
            const chartDiv = document.createElement('div');
            chartDiv.className = 'col';
            chartDiv.innerHTML = `
                <div id="waveChart${userId}" style="height:350px;"></div>
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
            chart.setOption(createChartOption(user, globalThresholds));
            
            window.charts = window.charts || [];
            window.charts[userId] = chart;
        });

        setInterval(fetchData, 200);
        // 每5分钟更新一次阈值
        setInterval(async () => {
            try {
                const res = await fetch('/api/get_limit.php');
                const newThresholds = await res.json();
                globalThresholds = {
                    max_data: newThresholds.max_data || 99,
                    min_data: newThresholds.min_data || 95
                };
                updateAllChartsThreshold();
            } catch (error) {
                console.error('阈值更新失败:', error);
            }
        }, 300000);
    }

    function createChartOption(user, thresholds) {
        return {
            title: { 
                text: `用户${user} 的血氧波形图`,
                subtext: `阈值: ${thresholds.min_data}%-${thresholds.max_data}%`
            },
            xAxis: { type: 'time' },
            yAxis: { 
                min: 90, 
                max: 100,
                axisLabel: { formatter: '{value}%' }
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
                    const date = new Date(params.value);
                    // return `时间: ${date.toLocaleString()}<br/>血氧: ${params.value}%`;
                }
            }
        };
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

    function updateAllChartsThreshold() {
        Object.keys(window.charts || {}).forEach(userId => {
            window.charts[userId].setOption({
                title: {
                    subtext: `阈值: ${globalThresholds.min_data}%-${globalThresholds.max_data}%`
                },
                series: [{
                    markLine: {
                        data: [
                            createThresholdMarker(globalThresholds.max_data, '#228b22', '最大阈值'),
                            createThresholdMarker(globalThresholds.min_data, '#b22222', '最小阈值')
                        ]
                    }
                }]
            });
        });
    }

    async function fetchData() {
        try {
            const res = await fetch('/api/get_data.php');
            const data = await res.json();
            
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
                    const latest = userData[0];
                    const spo2Element = document.getElementById(`spo2Value${userId}`);
                    const alertBox = document.getElementById(`alertBox${userId}`);
                    
                    spo2Element.textContent = `血氧：${latest.spo2}%`;
                    spo2Element.style.color = latest.status === 'abnormal' ? '#dc3545' : '#28a745';
                    
                    if (latest.status === 'abnormal') {
                        alertBox.classList.remove('d-none');
                        alertBox.innerHTML = `异常警告！当前血氧：${latest.spo2}%`;
                    } else {
                        alertBox.classList.add('d-none');
                    }
                }
            });
        } catch (error) {
            console.error('数据获取失败:', error);
        }
    }

    initDashboard();
    </script>
</body>
</html>