import numpy as np
from scipy.signal import butter, lfilter,filtfilt, find_peaks
from flask import Flask, request, jsonify
import random

app = Flask(__name__)

# 用于存储接收到的数据
data_buffer = {"ired": [], "red": []}
BUFFER_SIZE = 100  # 每 100 条数据计算一次血氧


class MAX30102Data:
    def __init__(self, sampling_rate=50):
        self.sampling_rate = sampling_rate
    def preprocessing(self, sequence):
        """Remove DC component (mean) and apply moving average smoothing"""
        mean = np.mean(sequence)
        data = sequence - mean  # 去除直流分量（DC）
        MA_SIZE = 4  # 设置移动平均窗口大小
        smoothed_data = np.copy(data)
        # 使用移动平均处理数据，前 MA_SIZE-1 个点用填充值
        for k in range(MA_SIZE, len(data)):
            smoothed_data[k] = np.mean(data[k - MA_SIZE:k])  # 平滑处理
        # 填充前面的缺失值，用第一个有效值填充
        smoothed_data[:MA_SIZE - 1] = smoothed_data[MA_SIZE]  # 使用第一个有效值填充
        return smoothed_data

    def butter_lowpass_filter(self, sequence, cutoff, order=5):
        """Low-pass filter"""
        fs = self.sampling_rate
        # 创建低通滤波器的系数
        b, a = butter(order, 2.0 * cutoff / fs, btype="low", analog=False)
        # 对输入信号进行预处理
        data = self.preprocessing(sequence)
        # 使用filtfilt进行前向和反向滤波，消除相位延迟
        filtered_data = filtfilt(b, a, data)
        # 左移信号
        shifted_data = np.roll(filtered_data, -6)
        # 去掉末尾的6个值
        shifted_data = shifted_data[:-6]
        return shifted_data, filtered_data

    def calculate_spo2(self, ired, red, min_value, max_value):
        """Calculate SpO2"""
        if len(ired) < BUFFER_SIZE or len(red) < BUFFER_SIZE:
            print("Not enough data to calculate")
            if 97 < min_value or 97 > max_value:
                return 97, False
            else:
                return 97, True
        ir_data = np.array(ired)
        red_data = np.array(red)
        # 低通滤波
        ir_filtered_data, buyong = self.butter_lowpass_filter(ired, cutoff=2)
        # 使用find_peaks检测波峰（这里使用负值找到原始信号的波谷）
        peaks, properties = find_peaks(-ir_filtered_data, height=None, distance=10, prominence=2.0)
        if len(peaks) < 2:
            if 97 < min_value or 97 > max_value:
                return 97, False
            else:
                return 97, True
        ratio = []
        i_ratio_count=0
        red_dc_max_index = -1
        ir_dc_max_index = -1
        for k in range(len(peaks) - 1):
            if peaks[k + 1] - peaks[k] > 3:
                red_dc_max = -16777216
                ir_dc_max = -16777216
                for i in range(peaks[k], peaks[k + 1]):
                    if ir_data[i] > ir_dc_max:
                        ir_dc_max = ir_data[i]
                        ir_dc_max_index = i
                    if red_data[i] > red_dc_max:
                        red_dc_max = red_data[i]
                        red_dc_max_index = i
                red_ac = int((red_data[peaks[k + 1]] - red_data[peaks[k]]) * (
                            red_dc_max_index - peaks[k]))
                red_ac = red_data[peaks[k]] + int(red_ac / (peaks[k + 1] - peaks[k]))
                red_ac = red_data[red_dc_max_index] - red_ac  # subtract linear DC components from raw

                ir_ac = int((ir_data[peaks[k + 1]] - ir_data[peaks[k]]) * (
                            ir_dc_max_index - peaks[k]))
                ir_ac = ir_data[peaks[k]] + int(ir_ac / (peaks[k + 1] - peaks[k]))
                ir_ac = ir_data[ir_dc_max_index] - ir_ac  # subtract linear DC components from raw
                nume = red_ac * ir_dc_max
                denom = ir_ac * red_dc_max
                if denom > 0 and nume != 0:
                    ratio.append(int(((nume * 100) & 0xffffffff) / denom))
                    i_ratio_count += 1

        if len(ratio) > 0:
            # choose median value since PPG signal may vary from beat to beat
            ratio = sorted(ratio)  # sort to ascending order
            mid_index = int(i_ratio_count / 2)

            ratio_ave = 0
            if mid_index > 1:
                ratio_ave = int((ratio[mid_index - 1] + ratio[mid_index]) / 2)
            else:
                if len(ratio) != 0:
                    ratio_ave = ratio[mid_index]
        else:
            if 97 < min_value or 97 > max_value:
                return 97, False
            else:
                return 97, True

        spo2 = -45.060 * (ratio_ave ** 2) / 10000.0 + 30.054 * ratio_ave / 100.0 + 94.845
        if spo2 < min_value or spo2 > max_value:
            return round(spo2, 2), False
        return round(spo2, 2), True

max30102 = MAX30102Data()


@app.route('/receive_data', methods=['POST'])
def receive_data():
    global data_buffer
    try:
        input_data = request.get_json()
        ired = int(input_data["ired"])
        red = int(input_data["red"])
        # 获取传入的最小值和最大值（默认值为85和100）
        min_value = int(input_data.get("min_value", 85))
        max_value = int(input_data.get("max_value", 100))

        # 记录收到的数据
        print(
            f"接收到数据 -> ired: {ired}, red: {red}, min_value: {min_value}, max_value: {max_value}, 缓冲区大小: {len(data_buffer['ired']) + 1}")

        # 存入缓冲区
        data_buffer["ired"].append(ired)
        data_buffer["red"].append(red)

        # 当数据达到 50 组时计算血氧
        if len(data_buffer["ired"]) >= BUFFER_SIZE:
            spo2, spo2_valid = max30102.calculate_spo2(data_buffer["ired"], data_buffer["red"], min_value, max_value)
            status = "normal" if spo2_valid else "abnormal"
            # 预处理和滤波
            ir_filtered_data ,ir_buyong= max30102.butter_lowpass_filter(data_buffer["ired"], cutoff=2)
            re_filtered_data ,re_buyong= max30102.butter_lowpass_filter(data_buffer["red"], cutoff=2)
            # 组织返回的数据
            response_data = [
                {
                    "ired": data_buffer["ired"][i],
                    "red": data_buffer["red"][i],
                    "spo2": spo2,
                    "status": status,
                    "ir_filtered": ir_buyong[i],  # 添加处理后的红外数据
                    "re_filtered": re_buyong[i]  # 添加处理后的红光数据
                }
                for i in range(BUFFER_SIZE)
            ]
            # 清空缓冲区
            data_buffer = {"ired": [], "red": []}
            print("已接收到100条数据，血氧饱和度计算完成，服务器返回一百条数据")
            return jsonify(response_data)  # 这是一个数组

        print("缓冲区未满，服务器返回ABC")
        return jsonify("ABC")
    except Exception as e:
        print(f"发生错误: {e}")
        return jsonify({"error": str(e)}), 400


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
