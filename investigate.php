<?php
session_start();

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 資料庫連接
$host = "localhost";
$dbname = "u765389418_availability_s";
$username = "u765389418_z32345897";
$password = "Eaa890213/";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("資料庫錯誤：" . $e->getMessage());
}

// 獲取當前用戶資訊
$currentUsername = $_SESSION['username'] ?? '未知用戶';
$currentUserId = $_SESSION['user_id'] ?? 0;
$isAdmin = $_SESSION['is_admin'] ?? 0;

// 驗證用戶資訊
if (empty($currentUserId) || empty($currentUsername)) {
    header("Location: login.php");
    exit();
}




// 獲取所有週數
// 獲取所有週數 - 修改為包含未來週數
try {
    $existingWeeks = $pdo->query("SELECT DISTINCT week_number FROM time_slots ORDER BY week_number")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $existingWeeks = [];
}

$currentWeek = (int)date('W');
$currentYear = (int)date('Y');
$allWeeks = [];

for ($i = 0; $i <= 8; $i++) {
    $targetWeek = $currentWeek + $i;
    $targetYear = $currentYear;

    // 簡單跨年處理（假設最多53週）
    if ($targetWeek > 53) {
        $targetYear++;
        $targetWeek = $targetWeek - 53;
    } elseif ($targetWeek > 52) {
        // 檢查當年是否真的有第53週
        $dec28 = strtotime("December 28, $targetYear");
        if (date('W', $dec28) < 53) {
            $targetYear++;
            $targetWeek = $targetWeek - 52;
        }
    }

    $allWeeks[] = $targetWeek;
}



// 合併現有週數和生成的週數，去重並排序
$allWeeks = array_unique(array_merge($allWeeks, $existingWeeks));
sort($allWeeks);

$selectedWeek = isset($_GET['week']) ? (int)$_GET['week'] : $currentWeek;

// 如果選擇的週數不在列表中，添加它
if (!in_array($selectedWeek, $allWeeks)) {
    $allWeeks[] = $selectedWeek;
    sort($allWeeks);
}


// 時間段設定s
$timeSlots = [
    "00:00-01:00",
    "01:00-02:00",
    "02:00-03:00",
    "03:00-04:00",
    "04:00-05:00",
    "05:00-06:00",
    "06:00-07:00",
    "07:00-08:00",
    "08:00-09:00",
    "09:00-10:00",
    "10:00-11:00",
    "11:00-12:00",
    "12:00-13:00",
    "13:00-14:00",
    "14:00-15:00",
    "15:00-16:00",
    "16:00-17:00",
    "17:00-18:00",
    "18:00-19:00",
    "19:00-20:00",
    "20:00-21:00",
    "21:00-22:00",
    "22:00-23:00",
    "23:00-24:00"
];

// 計算週日期
// 計算週日期 - 週四到週三的完整7天
function getWeekDates($week)
{
    $dates = [];
    $weekStart = new DateTime();
    $weekStart->setISODate(date('Y'), $week, 4); // 從週四開始

    $dayNames = ['四', '五', '六', '日', '一', '二', '三'];

    for ($i = 0; $i < 7; $i++) {
        $date = clone $weekStart;
        $date->modify("+$i days");
        $dates[$i + 1] = [
            'dateStr' => $date->format('Y-m-d'),
            'dayText' => $dayNames[$i],
            'display' => $date->format('m/d')
        ];
    }

    return $dates;
}


$weekDates = getWeekDates($selectedWeek);
$message = '';

// 處理表單提交 - 修復重複鍵問題
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selectedSlots = $_POST['time_slots'] ?? [];

    if (!empty($selectedSlots)) {
        try {
            $pdo->beginTransaction();

            // 修改查詢邏輯：先檢查 account_id，再檢查 name
            $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE account_id = ?");
            $stmt->execute([$currentUserId]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingUser) {
                // 檢查是否有相同名稱的用戶（但不同 account_id）
                $stmt = $pdo->prepare("SELECT user_id, account_id FROM users WHERE name = ?");
                $stmt->execute([$currentUsername]);
                $duplicateNameUser = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($duplicateNameUser) {
                    // 如果有重複名稱，使用帶編號的用戶名
                    $baseUsername = $currentUsername;
                    $counter = 1;

                    do {
                        $newUsername = $baseUsername . '_' . $counter;
                        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE name = ?");
                        $stmt->execute([$newUsername]);
                        $counter++;
                    } while ($stmt->fetch());

                    $finalUsername = $newUsername;
                    error_log("用戶名重複，使用新名稱: $finalUsername");
                } else {
                    $finalUsername = $currentUsername;
                }

                // 創建新用戶記錄
                $stmt = $pdo->prepare("INSERT INTO users (name, account_id) VALUES (?, ?)");
                $stmt->execute([$finalUsername, $currentUserId]);
                $userRecordId = $pdo->lastInsertId();
            } else {
                $userRecordId = $existingUser['user_id'];

                // 如果用戶名不同，更新用戶名（避免重複）
                if ($existingUser['name'] !== $currentUsername) {
                    // 檢查新用戶名是否已存在
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE name = ? AND user_id != ?");
                    $stmt->execute([$currentUsername, $userRecordId]);

                    if (!$stmt->fetch()) {
                        // 新用戶名不存在，可以更新
                        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE user_id = ?");
                        $stmt->execute([$currentUsername, $userRecordId]);
                    }
                    // 如果新用戶名已存在，保持原用戶名不變
                }
            }

            // 刪除該用戶在該週的所有舊記錄
            $stmt = $pdo->prepare("DELETE FROM time_slots WHERE user_id = ? AND week_number = ?");
            $stmt->execute([$userRecordId, $selectedWeek]);

            // 插入新的時間段
            $stmt = $pdo->prepare("INSERT INTO time_slots (user_id, date_time, week_number) VALUES (?, ?, ?)");
            $insertCount = 0;

            foreach ($selectedSlots as $slot) {
                $stmt->execute([$userRecordId, $slot, $selectedWeek]);
                $insertCount++;
            }

            $pdo->commit();
            $message = '<div class="alert success">✅ 時間段已成功提交！共選擇了 ' . $insertCount . ' 個時段。</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("提交失敗: " . $e->getMessage());
            $message = '<div class="alert error">❌ 提交失敗：' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert error">⚠️ 請至少選擇一個時間段</div>';
    }
}

// 獲取用戶已選擇的時間段
$userSelectedSlots = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.date_time 
        FROM time_slots t 
        JOIN users u ON t.user_id = u.user_id 
        WHERE u.account_id = ? AND t.week_number = ?
    ");
    $stmt->execute([$currentUserId, $selectedWeek]);
    $userSelectedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("獲取用戶選擇失敗: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>拉圖斯時間調查表單</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Microsoft JhengHei', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
        }

        .user-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #b3d9ff;
        }

        .user-info p {
            margin: 0;
            font-size: 16px;
            color: #0066cc;
        }

        .admin-badge {
            background: #ff6b6b;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }

        .user-badge {
            background: #4CAF50;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }

        .btn {
            display: inline-block;
            padding: 6px 12px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }

        .btn:hover {
            background: #45a049;
        }

        .btn.btn-small {
            padding: 4px 8px;
            font-size: 11px;
        }

        .btn.btn-primary {
            background: #007bff;
        }

        .btn.btn-primary:hover {
            background: #0056b3;
        }

        .week-selector {
            text-align: center;
            margin-bottom: 20px;
        }

        .week-selector select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
        }

        .alert.success {
            background: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }

        .alert.error {
            background: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }

        .time-grid {
            display: grid;
            grid-template-columns: 100px repeat(7, 1fr);
            gap: 1px;
            margin: 20px 0;
            font-size: 14px;
        }

        .time-header {
            background: #f2f2f2;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #ddd;
        }

        .time-slot {
            border: 1px solid #ddd;
            padding: 8px;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            position: relative;
        }

        .time-slot:hover {
            background: #e9e9e9;
        }

        .time-label {
            background: #f9f9f9;
            font-weight: bold;
        }

        .time-label:hover {
            background: #f9f9f9;
        }

        .time-slot input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            transform: scale(1.2);
        }

        .time-slot input[type="checkbox"]:checked {
            accent-color: #4CAF50;
        }

        .time-slot.selected {
            background: #e8f5e8;
            border-color: #4CAF50;
        }

        .submit-section {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .submit-btn {
            padding: 15px 30px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #0056b3;
        }

        .instructions {
            background: #fff3cd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border: 1px solid #ffeaa7;
        }

        .instructions h3 {
            margin-top: 0;
            color: #856404;
        }

        .instructions ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .instructions li {
            margin: 5px 0;
            color: #856404;
        }

        .selected-count {
            margin-top: 20px;
            text-align: center;
            font-size: 16px;
            color: #333;
        }

        .count-number {
            font-weight: bold;
            color: #007bff;
        }

        .batch-operations {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .batch-btn {
            padding: 8px 16px;
            margin: 0 5px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .batch-btn:hover {
            background: #5a6268;
        }

        .batch-btn.select-all {
            background: #28a745;
        }

        .batch-btn.select-all:hover {
            background: #218838;
        }

        .batch-btn.clear-all {
            background: #dc3545;
        }

        .batch-btn.clear-all:hover {
            background: #c82333;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>拉圖斯時間調查表單</h1>

        <div class="user-info">
            <p>歡迎，<?php echo htmlspecialchars($currentUsername); ?>！
                <?php if ($isAdmin): ?>
                    <span class="admin-badge">管理員</span>
                <?php else: ?>
                    <span class="user-badge">一般用戶</span>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-small">登出</a>
                <a href="results.php" class="btn btn-small btn-primary">查看結果</a>
            </p>
        </div>

        <?php echo $message; ?>

        <div class="week-selector">
            <select onchange="window.location.href='?week='+this.value;">
                <?php foreach ($allWeeks as $week): ?>
                    <option value="<?php echo $week; ?>" <?php echo $selectedWeek == $week ? 'selected' : ''; ?>>
                        第 <?php echo $week; ?> 週 <?php echo $week == $currentWeek ? '(當前週)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="instructions">
            <h3>📋 填寫說明</h3>
            <ul>
                <li>請勾選您在第 <strong><?php echo $selectedWeek; ?></strong> 週有空的時間段</li>
                <li>直接點擊 checkbox 來選擇或取消選擇</li>
                <li>可以使用下方的批量操作按鈕</li>
                <li>提交後會覆蓋之前的選擇</li>
            </ul>
        </div>

        <div class="batch-operations">
            <button type="button" class="batch-btn select-all" onclick="selectAll()">全選</button>
            <button type="button" class="batch-btn clear-all" onclick="clearAll()">全部清除</button>
            <button type="button" class="batch-btn" onclick="selectWeekdays()">只選工作日</button>
            <button type="button" class="batch-btn" onclick="selectWeekends()">只選週末</button>
        </div>

        <form method="POST">
            <div class="time-grid">
                <div class="time-header">時間</div>
                <?php foreach ($weekDates as $day): ?>
                    <div class="time-header">週<?php echo $day['dayText']; ?><br><?php echo $day['display']; ?></div>
                <?php endforeach; ?>

                <?php foreach ($timeSlots as $time): ?>
                    <?php $timeKey = substr($time, 0, 5); ?>
                    <div class="time-slot time-label"><?php echo $time; ?></div>
                    <?php foreach ($weekDates as $dayIndex => $day): ?>
                        <?php
                        $slotId = $day['dateStr'] . '_' . $timeKey;
                        $isSelected = in_array($slotId, $userSelectedSlots);
                        ?>
                        <div class="time-slot <?php echo $isSelected ? 'selected' : ''; ?>" data-day="<?php echo $dayIndex; ?>">
                            <input type="checkbox"
                                name="time_slots[]"
                                value="<?php echo $slotId; ?>"
                                <?php echo $isSelected ? 'checked' : ''; ?>
                                onchange="updateSlotStyle(this)">
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>

            <div class="selected-count">
                已選擇 <span class="count-number" id="selected-count"><?php echo count($userSelectedSlots); ?></span> 個時間段
            </div>

            <div class="submit-section">
                <button type="submit" class="submit-btn">提交時間安排</button>
                <p style="margin-top: 10px; color: #666; font-size: 14px;">
                    提交後將覆蓋您在第 <?php echo $selectedWeek; ?> 週的所有時間安排
                </p>
            </div>
        </form>
    </div>

    <script>
        function updateSlotStyle(checkbox) {
            const slot = checkbox.parentElement;
            if (checkbox.checked) {
                slot.classList.add('selected');
            } else {
                slot.classList.remove('selected');
            }
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('input[name="time_slots[]"]:checked');
            document.getElementById('selected-count').textContent = checkedBoxes.length;
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('input[name="time_slots[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
                updateSlotStyle(checkbox);
            });
        }

        function clearAll() {
            const checkboxes = document.querySelectorAll('input[name="time_slots[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
                updateSlotStyle(checkbox);
            });
        }

        function selectWeekdays() {
            clearAll();
            const checkboxes = document.querySelectorAll('input[name="time_slots[]"]');
            checkboxes.forEach(checkbox => {
                const dayIndex = parseInt(checkbox.parentElement.getAttribute('data-day'));
                if (dayIndex >= 1 && dayIndex <= 5) {
                    checkbox.checked = true;
                    updateSlotStyle(checkbox);
                }
            });
        }

        function selectWeekends() {
            clearAll();
            const checkboxes = document.querySelectorAll('input[name="time_slots[]"]');
            checkboxes.forEach(checkbox => {
                const dayIndex = parseInt(checkbox.parentElement.getAttribute('data-day'));
                if (dayIndex === 6 || dayIndex === 7) {
                    checkbox.checked = true;
                    updateSlotStyle(checkbox);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
            const checkedBoxes = document.querySelectorAll('input[name="time_slots[]"]:checked');
            checkedBoxes.forEach(checkbox => {
                updateSlotStyle(checkbox);
            });
        });
    </script>
</body>

</html>