<!DOCTYPE html>
<html>
<head>
    <title>数据处理监测</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <link rel="stylesheet" href="css/process_admin.css">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <style>
        /* 新增采样点样式 */
        .chart-card {
            position: relative;
        }
        .data-status {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 12px;
            color: #666;
        }
       
/* 新增网格布局样式 */
#dashboard {
    display: grid;
    grid-template-columns: repeat(4, 1fr); /* 两列布局 */
    gap: 20px; /* 图表间距 */
}

/* 调整图表容器样式 */
.chart-card {
    background: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

    </style>
</head>
<body>
    <header>
        <img src="images/NJUPT.svg" alt="logo" class="logo">
        <nav>
            <ul class="nav_links">
                <li><a href="index_supervisor.php">实时监测</a></li>
                <li><a href="process_supervisor.php"><strong>处理数据</strong></a></li>
            </ul>
        </nav>
    </header>

    <div id="dashboard" style="width: 90%; margin: 20px auto;"></div>

    <script>
        
        // 配置项
        const CONFIG = {
            dataPoints: 500,         // 显示点数
            refreshInterval: 180000, // 3分钟
            channels: {
                red_value: { color: '#ff6384', label: 'red_value' },
                ired_value: { color: '#4bc0c0', label: 'ired_value' },
                ired_process: { color: '#9966ff', label: 'ired_process' },
                red_process: { color: '#ff9f40', label: 'red_process' }

            }
        };

        // 图表实例缓存
        const charts = new Map();

        // 生成采样点坐标（从1到500）
        function generateSamplePoints(data) {
            return data.map((_, index) => CONFIG.dataPoints - index);
        }

        // 初始化/更新图表
        function updateChart(user, channel, rawData) {
            const canvasId = `${user}_${channel}`;
            
            // 生成带采样点序号的数据
            const displayData = rawData.map((d, i) => ({
                x: CONFIG.dataPoints - i, // 横坐标从500递减到1（数值越大越新）
                y: d.y
            })).reverse(); // 反转数组使最新数据在右侧

            if (!charts.has(canvasId)) {
                // 创建新图表
                const container = document.createElement('div');
                container.className = 'chart-card';
                container.innerHTML = `
                    <div class="data-status">采样点数: ${CONFIG.dataPoints} | 更新间隔: 3分钟</div>
                    <canvas id="${canvasId}"></canvas>
                `;
                document.getElementById('dashboard').appendChild(container);

                const chartInstance = new Chart(document.getElementById(canvasId), {
                    type: 'line',
                    data: {
                        datasets: [{
                            label: `${user} - ${CONFIG.channels[channel].label}`,
                            data: displayData,
                            borderColor: CONFIG.channels[channel].color,
                            tension: 0.1,
                            pointRadius: 0
                        }]
                    },
                    options: {
                        scales: {
                            x: {
                                type: 'linear',
                                title: { display: true, text: '采样点序号' },
                                min: 1,
                                max: CONFIG.dataPoints,
                                ticks: { stepSize: 100 }
                            },
                            y: { title: { display: true, text: 'value' } }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    title: (items) => `采样点: ${items.parsed.x}`,
                                    label: (item) => `${item.dataset.label}: ${item.parsed.y}`
                                }
                            }
                        }
                    }
                });
                charts.set(canvasId, chartInstance); // 关键修复点
            } else {
                // 更新现有图表
                const chart = charts.get(canvasId);
                chart.data.datasets[0].data = displayData; // 关键修复点
                chart.update();
            }
        }

        // 处理数据更新
        async function fetchData() {
            try {
                const response = await fetch('/api/get_process_data.php');
                const allUserData = await response.json();

 // 修改清理逻辑
Array.from(charts.keys()).forEach(key => {
    const [user] = key.split('_');
    if (!allUserData[user]) {
        const chart = charts.get(key);
        if (chart) {
            chart.destroy();
            // 移除容器元素
            const container = chart.canvas.closest('.chart-card');
            if (container) container.remove();
        }
        charts.delete(key);
    }
});

                // 更新或创建图表
                Object.entries(allUserData).forEach(([user, data]) => {
                    Object.keys(CONFIG.channels).forEach(channel => {
                        updateChart(user, channel, data[channel]);
                    });
                });

            } catch (error) {
                console.error('数据更新失败:', error);
            }
        }

        // 初始化
        fetchData();
        setInterval(fetchData, CONFIG.refreshInterval);
    </script>
</body>
</html>
