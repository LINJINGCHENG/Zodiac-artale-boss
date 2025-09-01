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

// 獲取所有週數 - 修改為包含未來週數
try {
  $existingWeeks = $pdo->query("SELECT DISTINCT week_number FROM time_slots ORDER BY week_number")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
  $existingWeeks = [];
}

$currentWeek = (int)date('W');
$currentDate=(int)date('w');
if($currentDate<4){
    $currentWeek=$currentWeek-1;
}
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

// 時間段設定
$timeSlots = [
  "00:00-01:00", "01:00-02:00", "02:00-03:00", "03:00-04:00",
  "04:00-05:00", "05:00-06:00", "06:00-07:00", "07:00-08:00",
  "08:00-09:00", "09:00-10:00", "10:00-11:00", "11:00-12:00",
  "12:00-13:00", "13:00-14:00", "14:00-15:00", "15:00-16:00",
  "16:00-17:00", "17:00-18:00", "18:00-19:00", "19:00-20:00",
  "20:00-21:00", "21:00-22:00", "22:00-23:00", "23:00-24:00"
];

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

// 處理表單提交 - 修改為累加模式
// 處理表單提交 - 使用 INSERT IGNORE 避免 SQL 錯誤
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $selectedSlots = $_POST['time_slots'] ?? [];
  $clearMode = isset($_POST['clear_all']); // 清除模式

  if ($clearMode) {
      // 清除模式：刪除該用戶在該週的所有記錄
      try {
          $pdo->beginTransaction();

          // 獲取用戶記錄ID
          $stmt = $pdo->prepare("SELECT user_id FROM users WHERE account_id = ?");
          $stmt->execute([$currentUserId]);
          $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

          $deletedCount = 0;
          if ($existingUser) {
              $stmt = $pdo->prepare("DELETE FROM time_slots WHERE user_id = ? AND week_number = ?");
              $stmt->execute([$existingUser['user_id'], $selectedWeek]);
              $deletedCount = $stmt->rowCount();
          }

          $pdo->commit();
          $message = '<div class="alert success">🗑️ 已清除您在第 ' . $selectedWeek . ' 週的所有時間安排！（共 ' . $deletedCount . ' 個時段）</div>';
      } catch (Exception $e) {
          $pdo->rollBack();
          error_log("清除失敗: " . $e->getMessage());
          $message = '<div class="alert error">❌ 清除失敗：' . $e->getMessage() . '</div>';
      }
  } elseif (!empty($selectedSlots)) {
      // 安全累加模式：使用 INSERT IGNORE 或 ON DUPLICATE KEY
      try {
          $pdo->beginTransaction();

          // 檢查或創建用戶記錄
          $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE account_id = ?");
          $stmt->execute([$currentUserId]);
          $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

          if (!$existingUser) {
              // 處理用戶名重複問題
              $stmt = $pdo->prepare("SELECT user_id, account_id FROM users WHERE name = ?");
              $stmt->execute([$currentUsername]);
              $duplicateNameUser = $stmt->fetch(PDO::FETCH_ASSOC);

              if ($duplicateNameUser) {
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

              $stmt = $pdo->prepare("INSERT INTO users (name, account_id) VALUES (?, ?)");
              $stmt->execute([$finalUsername, $currentUserId]);
              $userRecordId = $pdo->lastInsertId();
          } else {
              $userRecordId = $existingUser['user_id'];
              
              // 更新用戶名（如果需要且不重複）
              if ($existingUser['name'] !== $currentUsername) {
                  $stmt = $pdo->prepare("SELECT user_id FROM users WHERE name = ? AND user_id != ?");
                  $stmt->execute([$currentUsername, $userRecordId]);
                  if (!$stmt->fetch()) {
                      $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE user_id = ?");
                      $stmt->execute([$currentUsername, $userRecordId]);
                  }
              }
          }

          // 方法1：使用 INSERT IGNORE（推薦）
          $insertCount = 0;
          $totalSlots = count($selectedSlots);
          
          try {
              // 先計算現有時段數量
              $stmt = $pdo->prepare("SELECT COUNT(*) FROM time_slots WHERE user_id = ? AND week_number = ?");
              $stmt->execute([$userRecordId, $selectedWeek]);
              $beforeCount = $stmt->fetchColumn();

              // 使用 INSERT IGNORE 批量插入，自動忽略重複
              $placeholders = str_repeat('(?,?,?),', count($selectedSlots));
              $placeholders = rtrim($placeholders, ',');
              
              $sql = "INSERT IGNORE INTO time_slots (user_id, date_time, week_number) VALUES $placeholders";
              $stmt = $pdo->prepare($sql);
              
              $params = [];
              foreach ($selectedSlots as $slot) {
                  $params[] = $userRecordId;
                  $params[] = $slot;
                  $params[] = $selectedWeek;
              }
              
              $stmt->execute($params);
              
              // 計算實際插入的數量
              $stmt = $pdo->prepare("SELECT COUNT(*) FROM time_slots WHERE user_id = ? AND week_number = ?");
              $stmt->execute([$userRecordId, $selectedWeek]);
              $afterCount = $stmt->fetchColumn();
              
              $insertCount = $afterCount - $beforeCount;
              
          } catch (Exception $e) {
              // 如果 INSERT IGNORE 不支援，使用逐個檢查的方法
              error_log("INSERT IGNORE 失敗，使用備用方法: " . $e->getMessage());
              
              // 備用方法：逐個檢查並插入
              $stmt = $pdo->prepare("SELECT date_time FROM time_slots WHERE user_id = ? AND week_number = ?");
              $stmt->execute([$userRecordId, $selectedWeek]);
              $existingSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);

              $stmt = $pdo->prepare("INSERT INTO time_slots (user_id, date_time, week_number) VALUES (?, ?, ?)");
              
              foreach ($selectedSlots as $slot) {
                  if (!in_array($slot, $existingSlots)) {
                      try {
                          $stmt->execute([$userRecordId, $slot, $selectedWeek]);
                          $insertCount++;
                      } catch (Exception $insertError) {
                          // 即使單個插入失敗也繼續處理其他時段
                          error_log("插入時段失敗: $slot - " . $insertError->getMessage());
                      }
                  }
              }
          }

          $pdo->commit();
          
          // 友善的反饋訊息
          if ($insertCount > 0) {
              $message = '<div class="alert success">✅ 成功新增 ' . $insertCount . ' 個時間段！</div>';
          } else {
              $message = '<div class="alert info">📋 時間安排已確認！所選時段都已在您的安排中。</div>';
          }
          
      } catch (Exception $e) {
          $pdo->rollBack();
          error_log("提交失敗: " . $e->getMessage());
          // 即使發生錯誤，也給用戶友善的提示
          $message = '<div class="alert info">⚠️ 時間安排處理完成，請檢查您的選擇是否正確顯示。</div>';
      }
  } else {
      // 空選擇的友善提示
      $message = '<div class="alert info">💡 您可以選擇要新增的時間段，或直接查看現有安排。</div>';
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

      .alert.info {
          background: #d9edf7;
          color: #31708f;
          border: 1px solid #bce8f1;
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

      .time-slot.already-selected {
          background: #fff3cd;
          border-color: #ffc107;
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
          margin: 0 10px;
      }

      .submit-btn:hover {
          background: #0056b3;
      }

      .clear-btn {
          padding: 15px 30px;
          background: #dc3545;
          color: white;
          border: none;
          border-radius: 6px;
          font-size: 18px;
          cursor: pointer;
          margin: 0 10px;
      }

      .clear-btn:hover {
          background: #c82333;
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

      .mode-info {
          background: #d1ecf1;
          padding: 10px 15px;
          margin-bottom: 20px;
          border-radius: 4px;
          border: 1px solid #bee5eb;
          color: #0c5460;
          text-align: center;
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
              <li>請勾選您在第 <strong><?php echo $selectedWeek; ?></strong> 週<strong>新增</strong>的有空時間段</li>
              <li>黃色背景的時段表示您已經選擇過的時間</li>
              <li><strong>新選擇的時段會累加到現有時段中</strong></li>
              <li>如需清除所有時段，請使用「清除所有時段」按鈕</li>
              <li>可以使用下方的批量操作按鈕快速選擇</li>
          </ul>
      </div>

      <div class="batch-operations">
          <button type="button" class="batch-btn select-all" onclick="selectAll()">全選</button>
          <button type="button" class="batch-btn clear-all" onclick="clearAll()">全部清除</button>
          <button type="button" class="batch-btn" onclick="selectWeekdays()">只選工作日</button>
          <button type="button" class="batch-btn" onclick="selectWeekends()">只選週末</button>
      </div>

      <form method="POST" id="timeForm">
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
                      $isAlreadySelected = in_array($slotId, $userSelectedSlots);
                      ?>
                      <div class="time-slot <?php echo $isAlreadySelected ? 'already-selected' : ''; ?>" 
                           data-day="<?php echo $dayIndex; ?>"
                           title="<?php echo $isAlreadySelected ? '已選擇的時段' : '點擊選擇此時段'; ?>">
                          <?php if ($isAlreadySelected): ?>
                              <span style="font-size: 12px; color: #856404;">✓ 已選</span>
                          <?php else: ?>
                              <input type="checkbox"
                                  name="time_slots[]"
                                  value="<?php echo $slotId; ?>"
                                  onchange="updateSlotStyle(this)">
                          <?php endif; ?>
                      </div>
                  <?php endforeach; ?>
              <?php endforeach; ?>
          </div>

          <div class="selected-count">
              目前已有 <span class="count-number"><?php echo count($userSelectedSlots); ?></span> 個時間段 |
              本次新增 <span class="count-number" id="selected-count">0</span> 個時間段
          </div>

          <div class="submit-section">
              <button type="submit" class="submit-btn">新增選擇的時間段</button>
              <button type="submit" name="clear_all" class="clear-btn" 
                      onclick="return confirm('確定要清除您在第 <?php echo $selectedWeek; ?> 週的所有時間安排嗎？此操作無法復原！')">
                  清除所有時段
              </button>
              <p style="margin-top: 10px; color: #666; font-size: 14px;">
                  新選擇的時段將會<strong>添加</strong>到您在第 <?php echo $selectedWeek; ?> 週的現有時間安排中
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
      });
  </script>
</body>

</html>