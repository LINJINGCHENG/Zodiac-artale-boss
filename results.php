<?php
session_start();

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 獲取當前用戶資訊
$currentUsername = $_SESSION['username'];
$currentUserId = $_SESSION['user_id'];
$isAdmin = $_SESSION['is_admin'] ?? 0;

// 數據庫連接
$host = "localhost";
$dbname = "u765389418_availability_s";
$username = "u765389418_z32345897";
$password = "Eaa890213/";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("數據庫錯誤：" . $e->getMessage());
}

// 獲取當前週數和所有週數
$currentWeek = (int)date('W');
$allWeeks = $pdo->query("SELECT DISTINCT week_number FROM time_slots ORDER BY week_number")->fetchAll(PDO::FETCH_COLUMN);
$selectedWeek = isset($_GET['week']) ? (int)$_GET['week'] : $currentWeek;

if (!in_array($selectedWeek, $allWeeks) && !empty($allWeeks)) {
    $selectedWeek = (int)$allWeeks[0];
}
if (empty($allWeeks)) {
    $allWeeks = [$currentWeek];
    $selectedWeek = $currentWeek;
}

$currentMode = $_GET['mode'] ?? 'view';

// 時間段設定 (00:00-24:00)
$timeSlots = [];
for ($hour = 0; $hour < 24; $hour++) {
    $startTime = sprintf("%02d:00", $hour);
    $endTime = sprintf("%02d:00", $hour + 1);
    if ($hour == 23) $endTime = "24:00";
    $timeSlots[] = "$startTime-$endTime";
}

// 計算週日期
// 計算週日期 - 週四到週三的完整7天
function getWeekDates($week) {
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

// 獲取用戶時間段數據
$stmt = $pdo->prepare("
    SELECT u.name, u.user_id, u.account_id, t.date_time 
    FROM users u 
    JOIN time_slots t ON u.user_id = t.user_id 
    WHERE t.week_number = ?
    ORDER BY u.name, t.date_time
");
$stmt->execute([$selectedWeek]);

$usersBySlot = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $usersBySlot[$row['date_time']][] = [
        'user_id' => $row['user_id'], 
        'name' => $row['name'],
        'account_id' => $row['account_id']
    ];
}

// 獲取團隊成員資訊（用於標記團隊成員）
$teamMembers = [];
$teamStmt = $pdo->query("
    SELECT DISTINCT u.name, t.time_slot
    FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id 
    JOIN users u ON tm.user_id = u.user_id
");
foreach ($teamStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $timeSlots_team = explode(',', $row['time_slot']);
    foreach ($timeSlots_team as $slot) {
        $teamMembers[trim($slot)][] = $row['name'];
    }
}

$message = '';

// 處理表單提交 - 修復刪除邏輯
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 刪除時段
    if (isset($_POST['delete_selected_slots'])) {
        $selectedSlots = $_POST['delete_slots'] ?? [];
        $targetUserId = $_POST['target_user_id'] ?? null; // 目標用戶ID
        
        if (!empty($selectedSlots) && $targetUserId) {
            try {
                $pdo->beginTransaction();
                
               
                
                // 驗證權限：管理員可以刪除任何人的時段，一般用戶只能刪除自己的
                if (!$isAdmin && $targetUserId != $currentUserId) {
                    throw new Exception("權限不足：您只能刪除自己的時段");
                }
                
                $deletedCount = 0;
                
                // 統一的刪除邏輯：只刪除指定用戶的指定時段
                foreach ($selectedSlots as $slot) {
                    $stmt = $pdo->prepare("
                        DELETE FROM time_slots 
                        WHERE date_time = ? AND week_number = ? AND user_id = ?
                    ");
                    
                    error_log("執行刪除SQL: DELETE FROM time_slots WHERE date_time = '$slot' AND week_number = $selectedWeek AND user_id = $targetUserId");
                    
                    $stmt->execute([$slot, $selectedWeek, $targetUserId]);
                    $rowsDeleted = $stmt->rowCount();
                    $deletedCount += $rowsDeleted;
                    
                    error_log("時段 $slot 刪除結果: $rowsDeleted 筆記錄");
                }
                
                error_log("總刪除結果: 刪除了 $deletedCount 筆記錄");
                
                // 獲取目標用戶名稱用於顯示
                $stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
                $stmt->execute([$targetUserId]);
                $targetUserName = $stmt->fetchColumn();
                
                if ($deletedCount > 0) {
                    if ($isAdmin && $targetUserId != $currentUserId) {
                        $message = '<div class="alert success">已刪除 ' . htmlspecialchars($targetUserName) . ' 的 ' . $deletedCount . ' 個時段！</div>';
                    } else {
                        $message = '<div class="alert success">已刪除您的 ' . $deletedCount . ' 個時段！</div>';
                    }
                } else {
                    $message = '<div class="alert error">沒有找到可刪除的時段</div>';
                }
                
                $pdo->commit();
                error_log("=== 刪除操作完成 ===");
                
                // 重新導向以刷新頁面
                header("Location: ?mode=$currentMode&week=$selectedWeek");
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("刪除操作失敗: " . $e->getMessage());
                $message = '<div class="alert error">刪除失敗：' . $e->getMessage() . '</div>';
            }
        } else {
            $message = '<div class="alert error">請選擇要刪除的時段</div>';
        }
    }
    
    // 建立團隊
    if (isset($_POST['create_team'])) {
        $teamName = trim($_POST['team_name'] ?? '');
        $selectedDate = $_POST['selected_date'] ?? '';
        $selectedTimeSlots = $_POST['selected_time_slots'] ?? [];
        $selectedUsers = $_POST['selected_users'] ?? [];
        
        if ($teamName && $selectedDate && $selectedTimeSlots && $selectedUsers) {
            try {
                $pdo->beginTransaction();
                
                // 組合完整的時間段標識符
                $fullTimeSlots = [];
                foreach ($selectedTimeSlots as $timeSlot) {
                    $fullTimeSlots[] = $selectedDate . '_' . $timeSlot;
                }
                
                $timeSlotsStr = implode(',', $fullTimeSlots);
                
                $stmt = $pdo->prepare("INSERT INTO teams (name, date, time_slot, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$teamName, $selectedDate, $timeSlotsStr]);
                $teamId = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id) VALUES (?, ?)");
                foreach ($selectedUsers as $userId) {
                    $stmt->execute([$teamId, $userId]);
                }
                
                $pdo->commit();
                $message = '<div class="alert success">團隊 "' . htmlspecialchars($teamName) . '" 創建成功！</div>';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("創建團隊失敗: " . $e->getMessage());
                $message = '<div class="alert error">創建團隊失敗：' . $e->getMessage() . '</div>';
            }
        } else {
            $message = '<div class="alert error">請填寫完整資訊（團隊名稱、日期、時間段、成員）</div>';
        }
    }
    
    if (isset($_POST['delete_team'])) {
    $teamId = (int)$_POST['team_id'];
    
    try {
        $pdo->beginTransaction();
        
        // 先刪除團隊成員關聯
        $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id = ?");
        $stmt->execute([$teamId]);
        
        // 再刪除團隊
        $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
        $stmt->execute([$teamId]);
        
        $pdo->commit();
        $message = '<div class="alert success">團隊刪除成功！</div>';
        
        // 重新導向以刷新頁面
        header("Location: ?mode=$currentMode&week=$selectedWeek");
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollback();
        error_log("刪除團隊失敗: " . $e->getMessage());
        $message = '<div class="alert error">刪除失敗：' . $e->getMessage() . '</div>';
    }
}
}

// 獲取團隊列表
$teams = $pdo->query("
    SELECT t.id, t.name, t.date, t.time_slot, 
           GROUP_CONCAT(u.name SEPARATOR ', ') as members
    FROM teams t 
    JOIN team_members tm ON t.id = tm.team_id 
    JOIN users u ON tm.user_id = u.user_id 
    GROUP BY t.id ORDER BY t.date, t.time_slot
")->fetchAll(PDO::FETCH_ASSOC);


// 搜尋用戶的時段（用於刪除功能）
$searchResults = [];
if (isset($_GET['search_user']) && !empty($_GET['search_user'])) {
    $searchName = trim($_GET['search_user']);
    
    // 修復搜尋邏輯，確保準確匹配
    $stmt = $pdo->prepare("
        SELECT u.name, u.user_id, u.account_id, t.date_time, t.week_number
        FROM users u 
        JOIN time_slots t ON u.user_id = t.user_id 
        WHERE u.name LIKE ? AND t.week_number = ?
        ORDER BY t.date_time
    ");
    $stmt->execute(["%$searchName%", $selectedWeek]);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("搜尋用戶: $searchName, 找到 " . count($searchResults) . " 筆記錄");
}

// 為 JavaScript 準備數據
$jsUsersBySlot = json_encode($usersBySlot);
$jsWeekDates = json_encode($weekDates);
$jsTimeSlots = json_encode($timeSlots);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>拉圖斯時間調查管理系統</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Microsoft JhengHei', Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; color: #333; margin: 0 0 20px 0; }
        
        .user-info { background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid #b3d9ff; }
        .user-info p { margin: 0; font-size: 16px; color: #0066cc; }
        .admin-badge { background: #ff6b6b; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px; }
        .user-badge { background: #4CAF50; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px; }
        
        .nav { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .nav a { padding: 10px 20px; background: #f0f0f0; color: #333; text-decoration: none; border-radius: 4px; margin: 2px; }
        .nav a.active { background: #4CAF50; color: white; }
        
        .week-selector { text-align: center; margin-bottom: 20px; }
        .week-selector select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
        
        .btn { display: inline-block; padding: 8px 16px; background: #4CAF50; color: white; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin: 2px; }
        .btn:hover { background: #45a049; }
        .btn.btn-danger { background: #dc3545; }
        .btn.btn-danger:hover { background: #c82333; }
        .btn.btn-primary { background: #007bff; }
        .btn.btn-primary:hover { background: #0056b3; }
        .btn.btn-small { padding: 4px 8px; font-size: 12px; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert.success { background: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; }
        .alert.error { background: #f2dede; color: #a94442; border: 1px solid #ebccd1; }
        .alert.info { background: #d9edf7; color: #31708f; border: 1px solid #bce8f1; }
        
        /* 綠底黑字表格樣式 */
        .time-grid { display: grid; grid-template-columns: 100px repeat(7, 1fr); gap: 1px; margin: 20px 0; font-size: 12px; }
        .time-header { background: #4CAF50; color: black; padding: 8px; text-align: center; font-weight: bold; border: 1px solid #45a049; }
        .time-slot { background: #e8f5e8; color: black; border: 1px solid #4CAF50; padding: 4px; min-height: 60px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; }
        .time-label { background: #4CAF50; color: black; font-weight: bold; }
        
        .user-tag { background: #e3f2fd;color: #1976d2;padding: 2px 6px; margin: 1px; border-radius: 10px; font-size: 15px; border: none; cursor: pointer; transition: all 0.2s; }
        .user-tag:hover { background: #1976d2; color: white; }
        .user-tag.current-user { background: #4CAF50; color: white; border-color: #45a049; }
        .user-tag.current-user:hover { background: #45a049; }
        
        /* 團隊成員樣式 - 咖啡色字體加底線 */
        .user-tag.team-member { color: #8B4513; text-decoration: underline; font-weight: bold; }
        .user-tag.team-member.current-user { background: #4CAF50; color: white; text-decoration: underline; }
        
        .search-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .search-input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; margin-right: 10px; }
        
        .search-results { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-top: 20px; }
        .slot-item { display: flex; align-items: center; padding: 8px; border-bottom: 1px solid #eee; }
        .slot-item:last-child { border-bottom: none; }
        .slot-item input[type="checkbox"] { margin-right: 10px; }
        
        .team-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .team-form { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px; }
        .team-form-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .team-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; }
        .team-card { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 15px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        
        .checkbox-list { max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: white; }
        .checkbox-list label { display: block; margin: 5px 0; font-weight: normal; }
        
        .step-container { display: none; }
        .step-container.active { display: block; }
        
        .step-indicator { display: flex; justify-content: center; margin-bottom: 20px; }
        .step { padding: 10px 20px; background: #f0f0f0; margin: 0 5px; border-radius: 4px; }
        .step.active { background: #4CAF50; color: white; }
        .step.completed { background: #28a745; color: white; }
        /* 確認對話框樣式 */
.confirm-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.confirm-content {
    background: white;
    padding: 30px;
    border-radius: 10px;
    text-align: center;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.confirm-buttons {
    margin-top: 20px;
}

.confirm-btn {
    padding: 10px 20px;
    margin: 0 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s;
}

.confirm-yes {
    background: #dc3545;
    color: white;
}

.confirm-yes:hover {
    background: #c82333;
}

.confirm-no {
    background: #6c757d;
    color: white;
}

.confirm-no:hover {
    background: #545b62;
}

.team-actions {
    margin-top: 15px;
    text-align: center;
}

.team-card {
    position: relative;
    transition: transform 0.2s;
}

.team-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

        
      
        
        @media (max-width: 768px) {
            .time-grid { grid-template-columns: 80px repeat(7, 1fr); font-size: 10px; }
            .user-tag { font-size: 9px; padding: 1px 4px; }
            .team-form-row { grid-template-columns: 1fr; }
            .nav { justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🕐 拉圖斯時間調查管理系統</h1>
        
        <div class="user-info">
            <p>歡迎，<?php echo htmlspecialchars($currentUsername); ?>！
            <?php if ($isAdmin): ?>
                <span class="admin-badge">管理員</span>
            <?php else: ?>
                <span class="user-badge">一般用戶</span>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-small">登出</a>
            <a href="investigate.php" class="btn btn-small">填寫時間表</a>
            </p>
        </div>
        
        <?php echo $message; ?>
        
        <div class="nav">
            <a href="?mode=view&week=<?php echo $selectedWeek; ?>" class="<?php echo $currentMode == 'view' ? 'active' : ''; ?>">📊 查看時間表</a>
            <a href="?mode=team&week=<?php echo $selectedWeek; ?>" class="<?php echo $currentMode == 'team' ? 'active' : ''; ?>">👥 建立團隊</a>
            <a href="?mode=delete&week=<?php echo $selectedWeek; ?>" class="<?php echo $currentMode == 'delete' ? 'active' : ''; ?>">🗑️ 刪除時段</a>
        </div>
        
        <div class="week-selector">
            <select onchange="window.location.href='?mode=<?php echo $currentMode; ?>&week='+this.value;">
                <?php foreach ($allWeeks as $week): ?>
                    <option value="<?php echo $week; ?>" <?php echo $selectedWeek == $week ? 'selected' : ''; ?>>
                        第 <?php echo $week; ?> 週 <?php echo $week == $currentWeek ? '(當前週)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if ($currentMode == 'view'): ?>
            <h2>📊 第 <?php echo $selectedWeek; ?> 週時間表</h2>
            <div class="alert info">綠色標籤表示您的時間段，咖啡色加底線表示團隊成員。</div>
            
            <div class="time-grid">
                <div class="time-header">時間</div>
                <?php foreach ($weekDates as $day): ?>
                    <div class="time-header">週<?php echo $day['dayText']; ?><br><?php echo $day['display']; ?></div>
                <?php endforeach; ?>
                
                <?php foreach ($timeSlots as $time): ?>
                    <?php $timeKey = substr($time, 0, 5); ?>
                    <div class="time-slot time-label"><?php echo $time; ?></div>
                    <?php foreach ($weekDates as $day): ?>
                        <?php $slotId = $day['dateStr'] . '_' . $timeKey; ?>
                        <div class="time-slot" data-slot="<?php echo $slotId; ?>">
                            <?php if (isset($usersBySlot[$slotId])): ?>
                                <?php foreach ($usersBySlot[$slotId] as $user): ?>
                                    <?php 
                                    // 檢查是否為團隊成員
                                    $isTeamMember = false;
                                    if (isset($teamMembers[$slotId])) {
                                        $isTeamMember = in_array($user['name'], $teamMembers[$slotId]);
                                    }
                                    
                                    $classes = [];
                                    if ($user['account_id'] == $currentUserId) $classes[] = 'current-user';
                                    if ($isTeamMember) $classes[] = 'team-member';
                                    $classStr = implode(' ', $classes);
                                    ?>
                                    <span class="user-tag <?php echo $classStr; ?>">
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            
        <?php elseif ($currentMode == 'team'): ?>
            <h2>👥 建立團隊</h2>
            
            <div class="team-section">
                <h3>建立新團隊</h3>
                
                <div class="step-indicator">
                    <div class="step active" id="step1-indicator">1. 團隊名稱</div>
                    <div class="step" id="step2-indicator">2. 選擇日期</div>
                    <div class="step" id="step3-indicator">3. 選擇時段</div>
                    <div class="step" id="step4-indicator">4. 選擇成員</div>
                </div>
                
                <form method="POST" id="teamForm">
                    <!-- 步驟 1: 團隊名稱 -->
                    <div class="step-container active" id="step1">
                        <div class="form-group">
                            <label>團隊名稱:</label>
                            <input type="text" name="team_name" id="team_name" required>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="nextStep(1)">下一步</button>
                    </div>
                    
                    <!-- 步驟 2: 選擇日期 -->
                    <div class="step-container" id="step2">
                        <div class="form-group">
                            <label>選擇日期:</label>
                            <select name="selected_date" id="selected_date" onchange="loadTimeSlots()">
                                <option value="">請選擇日期</option>
                                <?php foreach ($weekDates as $day): ?>
                                    <option value="<?php echo $day['dateStr']; ?>">
                                        <?php echo $day['dateStr']; ?> (週<?php echo $day['dayText']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn" onclick="prevStep(2)">上一步</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(2)">下一步</button>
                    </div>
                    
                    <!-- 步驟 3: 選擇時段 -->
                    <div class="step-container" id="step3">
                        <div class="form-group">
                            <label>選擇時間段:</label>
                            <div class="checkbox-list" id="time-slots-list">
                                <!-- 時間段選項會通過 JavaScript 動態載入 -->
                            </div>
                        </div>
                        <button type="button" class="btn" onclick="prevStep(3)">上一步</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(3)">下一步</button>
                    </div>
                    
                    <!-- 步驟 4: 選擇成員 -->
                    <div class="step-container" id="step4">
                        <div class="form-group">
                            <label>可用成員:</label>
                            <div class="checkbox-list" id="available-users-list">
                                <!-- 可用成員會通過 JavaScript 動態載入 -->
                            </div>
                        </div>
                        <button type="button" class="btn" onclick="prevStep(4)">上一步</button>
                        <button type="submit" name="create_team" class="btn btn-primary">建立團隊</button>
                    </div>
                </form>
            </div>
            
            <h3>現有團隊</h3>
<div class="team-list">
    <?php if (empty($teams)): ?>
        <div class="alert info">目前沒有任何團隊</div>
    <?php else: ?>
        <?php foreach ($teams as $team): ?>
            <div class="team-card">
                <h4><?php echo htmlspecialchars($team['name']); ?></h4>
                <p><strong>日期:</strong> <?php echo $team['date']; ?></p>
                <p><strong>時間段:</strong> <?php echo $team['time_slot']; ?></p>
                <p><strong>成員:</strong> <?php echo htmlspecialchars($team['members']); ?></p>
                <div class="team-actions">
                    <button type="button" class="btn btn-danger btn-small" 
                            onclick="confirmDeleteTeam(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['name'], ENT_QUOTES); ?>')">
                        🗑️ 刪除團隊
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- 隱藏的刪除表單 -->
<form id="deleteTeamForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_team" value="1">
    <input type="hidden" name="team_id" id="deleteTeamId">
</form>

<!-- 確認刪除對話框 -->
<div id="confirmDeleteDialog" class="confirm-dialog">
    <div class="confirm-content">
        <h3>⚠️ 確認刪除團隊</h3>
        <p id="confirmDeleteMessage"></p>
        <div class="confirm-buttons">
            <button class="confirm-btn confirm-yes" onclick="executeDeleteTeam()">確認刪除</button>
            <button class="confirm-btn confirm-no" onclick="cancelDeleteTeam()">取消</button>
        </div>
    </div>
</div>

            
        <?php elseif ($currentMode == 'delete'): ?>
            <h2>🗑️ 刪除時段</h2>
            
            <div class="search-section">
                <form method="GET">
                    <input type="hidden" name="mode" value="delete">
                    <input type="hidden" name="week" value="<?php echo $selectedWeek; ?>">
                    <input type="text" name="search_user" class="search-input" placeholder="輸入用戶名稱搜尋..." 
                           value="<?php echo htmlspecialchars($_GET['search_user'] ?? ''); ?>">
                    <button type="submit" class="btn">搜尋</button>
                </form>
                
                <!-- 顯示搜尋到的用戶詳細資訊 -->
                <?php if (!empty($searchResults)): ?>
                    <div class="debug-info" style="margin-top: 15px;">
                        <strong>搜尋結果詳細資訊：</strong><br>
                        用戶名稱: <?php echo htmlspecialchars($searchResults[0]['name']); ?><br>
                        用戶ID: <?php echo $searchResults[0]['user_id']; ?><br>
                        帳號ID: <?php echo $searchResults[0]['account_id']; ?><br>
                        找到時段數: <?php echo count($searchResults); ?>個<br>
                        當前用戶ID: <?php echo $currentUserId; ?><br>
                        權限匹配: <?php echo ($searchResults[0]['account_id'] == $currentUserId || $isAdmin) ? '✅ 可刪除' : '❌ 無權限'; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($searchResults)): ?>
                <div class="search-results">
                    <h4>搜尋結果：<?php echo htmlspecialchars($searchResults[0]['name']); ?> 的可用時段</h4>
                    
                    <?php if (!$isAdmin && $searchResults[0]['account_id'] != $currentUserId): ?>
                        <div class="alert error">
                            您只能刪除自己的時段<br>
                            <small>目標用戶ID: <?php echo $searchResults[0]['account_id']; ?> | 您的ID: <?php echo $currentUserId; ?></small>
                        </div>
                    <?php else: ?>
                        <form method="POST" onsubmit="return confirmDelete()">
                            <!-- 隱藏字段傳遞目標用戶ID -->
                            <input type="hidden" name="target_user_id" value="<?php echo $searchResults[0]['user_id']; ?>">
                            
                            <?php foreach ($searchResults as $result): ?>
                                <div class="slot-item">
                                    <input type="checkbox" name="delete_slots[]" value="<?php echo $result['date_time']; ?>" id="slot_<?php echo $result['date_time']; ?>">
                                    <label for="slot_<?php echo $result['date_time']; ?>">
                                        <?php echo $result['date_time']; ?>
                                        <small>(週<?php echo $result['week_number']; ?>)</small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            
                            <div style="margin-top: 15px;">
                                <button type="submit" name="delete_selected_slots" class="btn btn-danger">
                                    刪除選中時段
                                </button>
                                <button type="button" class="btn" onclick="toggleAllCheckboxes()">全選/取消全選</button>
                                
                                <!-- 顯示即將執行的操作 -->
                                <div class="debug-info" style="margin-top: 10px; font-size: 11px;">
                                    <strong>即將執行的刪除操作：</strong><br>
                                    <?php if ($isAdmin): ?>
                                        管理員模式：將刪除 <?php echo htmlspecialchars($searchResults[0]['name']); ?> 的選中時段
                                    <?php else: ?>
                                        一般用戶模式：僅刪除屬於您（ID: <?php echo $currentUserId; ?>）的選中時段
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php elseif (isset($_GET['search_user']) && !empty($_GET['search_user'])): ?>
                <div class="alert info">沒有找到符合條件的用戶或該用戶在第 <?php echo $selectedWeek; ?> 週沒有可用時段</div>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>

    <script>
        // 為 JavaScript 準備的數據
        const usersBySlot = <?php echo $jsUsersBySlot; ?>;
        const weekDates = <?php echo $jsWeekDates; ?>;
        const timeSlots = <?php echo $jsTimeSlots; ?>;
        const currentUserId = <?php echo $currentUserId; ?>;
        
        let currentStep = 1;
        
        // 步驟控制函數
        function nextStep(step) {
            // 驗證當前步驟
            if (!validateStep(step)) {
                return;
            }
            
            // 隱藏當前步驟
            document.getElementById('step' + step).classList.remove('active');
            document.getElementById('step' + step + '-indicator').classList.remove('active');
            document.getElementById('step' + step + '-indicator').classList.add('completed');
            
            // 顯示下一步驟
            currentStep = step + 1;
            document.getElementById('step' + currentStep).classList.add('active');
            document.getElementById('step' + currentStep + '-indicator').classList.add('active');
        }
        
        function prevStep(step) {
            // 隱藏當前步驟
            document.getElementById('step' + step).classList.remove('active');
            document.getElementById('step' + step + '-indicator').classList.remove('active');
            
            // 顯示上一步驟
            currentStep = step - 1;
            document.getElementById('step' + currentStep).classList.add('active');
            document.getElementById('step' + currentStep + '-indicator').classList.add('active');
            document.getElementById('step' + currentStep + '-indicator').classList.remove('completed');
        }
        
        // 驗證步驟
        function validateStep(step) {
            switch(step) {
                case 1:
                    const teamName = document.getElementById('team_name').value.trim();
                    if (!teamName) {
                        alert('請輸入團隊名稱');
                        return false;
                    }
                    break;
                case 2:
                    const selectedDate = document.getElementById('selected_date').value;
                    if (!selectedDate) {
                        alert('請選擇日期');
                        return false;
                    }
                    break;
                case 3:
                    const selectedTimeSlots = document.querySelectorAll('input[name="selected_time_slots[]"]:checked');
                    if (selectedTimeSlots.length === 0) {
                        alert('請至少選擇一個時間段');
                        return false;
                    }
                    loadAvailableUsers();
                    break;
            }
            return true;
        }
        
        // 團隊刪除相關函數
let teamToDelete = null;

function confirmDeleteTeam(teamId, teamName) {
    teamToDelete = teamId;
    document.getElementById('confirmDeleteMessage').innerHTML = 
        `確定要刪除團隊「<strong>${teamName}</strong>」嗎？<br><br>此操作將會：<br>• 刪除團隊資訊<br>• 移除所有團隊成員關聯<br>• <span style="color: #dc3545;">此操作無法復原</span>`;
    document.getElementById('confirmDeleteDialog').style.display = 'flex';
}

function executeDeleteTeam() {
    if (teamToDelete) {
        document.getElementById('deleteTeamId').value = teamToDelete;
        document.getElementById('deleteTeamForm').submit();
    }
}

function cancelDeleteTeam() {
    teamToDelete = null;
    document.getElementById('confirmDeleteDialog').style.display = 'none';
}

// 點擊對話框外部關閉
document.addEventListener('DOMContentLoaded', function() {
    const dialog = document.getElementById('confirmDeleteDialog');
    if (dialog) {
        dialog.addEventListener('click', function(e) {
            if (e.target === this) {
                cancelDeleteTeam();
            }
        });
    }
    
    // ESC 鍵關閉對話框
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cancelDeleteTeam();
        }
    });
});

        
        // 載入時間段選項
        function loadTimeSlots() {
            const selectedDate = document.getElementById('selected_date').value;
            const timeSlotsContainer = document.getElementById('time-slots-list');
            
            if (!selectedDate) {
                timeSlotsContainer.innerHTML = '<p>請先選擇日期</p>';
                return;
            }
            
            let html = '';
            let hasAvailableSlots = false;
            
            timeSlots.forEach(timeSlot => {
                const timeKey = timeSlot.substring(0, 5); // 取得 HH:MM 格式
                const slotId = selectedDate + '_' + timeKey;
                
                if (usersBySlot[slotId] && usersBySlot[slotId].length > 0) {
                    hasAvailableSlots = true;
                    html += `
                        <label>
                            <input type="checkbox" name="selected_time_slots[]" value="${timeKey}">
                            ${timeSlot} (${usersBySlot[slotId].length} 人可用)
                        </label>
                    `;
                }
            });
            
            if (!hasAvailableSlots) {
                html = '<p class="alert info">該日期沒有任何可用的時間段</p>';
            }
            
            timeSlotsContainer.innerHTML = html;
        }
        
        // 載入可用成員
        function loadAvailableUsers() {
            const selectedDate = document.getElementById('selected_date').value;
            const selectedTimeSlots = Array.from(document.querySelectorAll('input[name="selected_time_slots[]"]:checked'))
                .map(cb => cb.value);
            const usersContainer = document.getElementById('available-users-list');
            
            if (!selectedDate || selectedTimeSlots.length === 0) {
                usersContainer.innerHTML = '<p>請先選擇日期和時間段</p>';
                return;
            }
            
            // 找出在所有選中時間段都有空的用戶
            let availableUsers = new Map();
            let isFirstSlot = true;
            
            selectedTimeSlots.forEach(timeSlot => {
                const slotId = selectedDate + '_' + timeSlot;
                
                if (usersBySlot[slotId]) {
                    if (isFirstSlot) {
                        // 第一個時間段，加入所有用戶
                        usersBySlot[slotId].forEach(user => {
                            availableUsers.set(user.user_id, user);
                        });
                        isFirstSlot = false;
                    } else {
                        // 後續時間段，只保留同時有空的用戶
                        const currentSlotUsers = new Set(usersBySlot[slotId].map(u => u.user_id));
                        for (let [userId, user] of availableUsers) {
                            if (!currentSlotUsers.has(userId)) {
                                availableUsers.delete(userId);
                            }
                        }
                    }
                }
            });
            
            let html = '';
            if (availableUsers.size === 0) {
                html = '<p class="alert info">沒有用戶在所有選中的時間段都有空</p>';
            } else {
                availableUsers.forEach(user => {
                    const isCurrentUser = user.account_id == currentUserId;
                    html += `
                        <label>
                            <input type="checkbox" name="selected_users[]" value="${user.user_id}">
                            ${user.name}
                            ${isCurrentUser ? '<span class="user-badge">您</span>' : ''}
                        </label>
                    `;
                });
            }
            
            usersContainer.innerHTML = html;
        }
        
        // 全選/取消全選功能（用於刪除功能）
        function toggleAllCheckboxes() {
            const checkboxes = document.querySelectorAll('input[name="delete_slots[]"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
        }
        
        // 確認刪除對話框
        function confirmDelete() {
            const selectedSlots = document.querySelectorAll('input[name="delete_slots[]"]:checked');
            if (selectedSlots.length === 0) {
                alert('請選擇要刪除的時段');
                return false;
            }
            
            const slotList = Array.from(selectedSlots).map(cb => cb.value).join(', ');
            return confirm(`確定要刪除以下 ${selectedSlots.length} 個時段嗎？\n\n${slotList}\n\n此操作無法復原！`);
        }
        
        // 頁面載入時初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 如果是團隊模式，確保步驟指示器正確顯示
            if (document.getElementById('step1')) {
                currentStep = 1;
            }
            
            // 添加頁面載入完成的除錯資訊
            console.log('頁面載入完成');
            console.log('用戶資料:', usersBySlot);
            console.log('當前用戶ID:', currentUserId);
        });
    </script>
</body>
</html>
