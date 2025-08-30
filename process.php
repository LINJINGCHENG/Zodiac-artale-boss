<?php
// 設置錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 數據庫連接設置
$host = "localhost";
$dbname = "u765389418_availability_s";
$username = "u765389418_z32345897";  // 替換為您的數據庫用戶名
$password = "Eaa890213/";  // 替換為您的數據庫密碼

// 檢查表單是否已提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 獲取表單數據
    $name = trim($_POST["name"]);
    $selectedTimes = isset($_POST["selectedTimes"]) ? $_POST["selectedTimes"] : [];

    // 驗證數據
    if (empty($name)) {
        die("請輸入姓名");
    }

    if (empty($selectedTimes)) {
        die("請至少選擇一個時間段");
    }

    try {
        // 連接到數據庫
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 開始事務
        $pdo->beginTransaction();

        // 檢查用戶是否已存在
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE name = ?");
        $stmt->execute([$name]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $userId = null;

        if ($user) {
            // 用戶已存在，獲取用戶ID
            $userId = $user['user_id'];
        } else {
            // 創建新用戶
            $stmt = $pdo->prepare("INSERT INTO users (name) VALUES (?)");
            $stmt->execute([$name]);
            $userId = $pdo->lastInsertId();
        }

        // 處理每個選中的時間段
        foreach ($selectedTimes as $timeSlot) {
            // 時間格式: YYYY-MM-DD_HH:00
            list($dateStr, $timeStr) = explode('_', $timeSlot);

            // 計算週數
            $date = new DateTime($dateStr);
            $weekNumber = $date->format("W"); // ISO-8601 週數

            // 嘗試插入時間段，忽略重複項
            $stmt = $pdo->prepare("INSERT IGNORE INTO time_slots (user_id, date_time, week_number) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $timeSlot, $weekNumber]);
        }

        // 提交事務
        $pdo->commit();

        // 顯示成功消息
        echo "<!DOCTYPE html>
        <html lang='zh-TW'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>提交成功</title>
            <style>
                body {
                    font-family: 'Microsoft JhengHei', Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background-color: #f5f5f5;
                    text-align: center;
                }
                
                .container {
                    max-width: 800px;
                    margin: 0 auto;
                    background-color: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                
                h1 {
                    color: #4CAF50;
                }
                
                .btn {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #4CAF50;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    margin-top: 20px;
                }
                
                .time-list {
                    text-align: left;
                    max-width: 400px;
                    margin: 20px auto;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>提交成功！</h1>
                <p>感謝您，" . htmlspecialchars($name) . "！您的時間選擇已成功記錄。</p>
                
                <div class='time-list'>
                    <h3>您選擇的時間段：</h3>
                    <ul>";

        foreach ($selectedTimes as $timeSlot) {
            list($dateStr, $timeStr) = explode('_', $timeSlot);
            $date = new DateTime($dateStr);
            $formattedDate = $date->format('Y年m月d日');
            echo "<li>" . $formattedDate . " " . $timeStr . "</li>";
        }

        echo "</ul>
                </div>
                
                <a href='investigate.html' class='btn'>返回調查表單</a>
            </div>
        </body>
        </html>";
    } catch (PDOException $e) {
        // 回滾事務
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // 顯示錯誤信息
        die("錯誤：" . $e->getMessage());
    }
} else {
    // 如果不是通過表單提交訪問此頁面，重定向到表單頁面
    header("Location: investigate.html");
    exit();
}
