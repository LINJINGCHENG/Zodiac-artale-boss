<?php
session_start();

// 設定台灣時區
date_default_timezone_set('Asia/Taipei');

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

function getCustomWeekNumber($date) {
    if (is_string($date)) {
        $dateObj = new DateTime($date);
    } else {
        $dateObj = $date;
    }
    
    $year = (int)$dateObj->format('Y');
    
    // 找到該年第一個週四
    $firstThursday = new DateTime($year . '-01-01');
    while ((int)$firstThursday->format('w') != 4) { // 4 = 週四
        $firstThursday->add(new DateInterval('P1D'));
    }
    
    // 如果日期在第一個週四之前，屬於上一年
    if ($dateObj < $firstThursday) {
        $prevYear = $year - 1;
        $prevFirstThursday = new DateTime($prevYear . '-01-01');
        while ((int)$prevFirstThursday->format('w') != 4) {
            $prevFirstThursday->add(new DateInterval('P1D'));
        }
        $daysDiff = $dateObj->diff($prevFirstThursday)->days;
    } else {
        $daysDiff = $dateObj->diff($firstThursday)->days;
    }
    
    return intval($daysDiff / 7) + 1;
}

// 生成未來14天的日期選項（從今天開始）
function getFutureDates($days = 14) {
  $dates = [];
  for ($i = 0; $i <= $days; $i++) {
      $date = new DateTime('now', new DateTimeZone('Asia/Taipei'));
      $date->modify("+$i days");
      $dates[] = [
          'dateStr' => $date->format('Y-m-d'),
          'display' => $date->format('m/d'),
          'dayName' => ['日', '一', '二', '三', '四', '五', '六'][$date->format('w')],
          'fullDisplay' => $date->format('m/d') . ' (週' . ['日', '一', '二', '三', '四', '五', '六'][$date->format('w')] . ')'
      ];
  }
  return $dates;
}

$futureDates = getFutureDates(14);

// 時間選項（24小時制）
$timeOptions = [];
for ($i = 0; $i <= 24; $i++) {
  if ($i == 24) {
      $timeOptions[] = '24:00';
  } else {
      $timeOptions[] = sprintf('%02d:00', $i);
  }
}


$message = '';

// 處理表單提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $selectedSlots = $_POST['time_slots'] ?? [];
  $clearMode = isset($_POST['clear_all']); // 清除模式

  if ($clearMode) {
      // 清除模式：刪除該用戶的所有記錄
      try {
          $pdo->beginTransaction();

          // 獲取用戶記錄ID
          $stmt = $pdo->prepare("SELECT user_id FROM users WHERE account_id = ?");
          $stmt->execute([$currentUserId]);
          $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

          $deletedCount = 0;
          if ($existingUser) {
              $stmt = $pdo->prepare("DELETE FROM time_slots WHERE user_id = ?");
              $stmt->execute([$existingUser['user_id']]);
              $deletedCount = $stmt->rowCount();
          }

          $pdo->commit();
          $message = '<div class="alert success">🗑️ 已清除您的所有時間安排！（共 ' . $deletedCount . ' 筆記錄）</div>';
      } catch (PDOException $e) {
          $pdo->rollBack();
          $message = '<div class="alert error">❌ 清除失敗：' . htmlspecialchars($e->getMessage()) . '</div>';
      }
  } else {
      // 正常提交模式
      if (empty($selectedSlots)) {
          $message = '<div class="alert error">❌ 請至少選擇一個時間段！</div>';
      } else {
          try {
              $pdo->beginTransaction();

              // 檢查或創建用戶記錄
              $stmt = $pdo->prepare("SELECT user_id FROM users WHERE account_id = ?");
              $stmt->execute([$currentUserId]);
              $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

              if ($existingUser) {
                  $userId = $existingUser['user_id'];
              } else {
                  // 創建新用戶記錄，注意這裡使用 name 欄位而不是 username
                  $stmt = $pdo->prepare("INSERT INTO users (account_id, name) VALUES (?, ?)");
                  $stmt->execute([$currentUserId, $currentUsername]);
                  $userId = $pdo->lastInsertId();
              }

              $successCount = 0;
              $duplicateCount = 0;

             
// 然後修改你的週數計算部分：
foreach ($selectedSlots as $slot) {
    list($date, $time) = explode('_', $slot);
    
    // 組合成 datetime 格式
    $dateTime = $date . ' ' . sprintf('%02d:00:00', $time);
    
    // 使用自定義週數計算（而不是 ISO 8601）
    $weekNumber = getCustomWeekNumber($date); // 修改這裡！
    
    // 檢查是否已存在
    $stmt = $pdo->prepare("SELECT id FROM time_slots WHERE user_id = ? AND date_time = ?");
    $stmt->execute([$userId, $dateTime]);
    
    if (!$stmt->fetch()) {
        // 插入新記錄
        $stmt = $pdo->prepare("INSERT INTO time_slots (user_id, date_time, week_number) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $dateTime, $weekNumber]);
        $successCount++;
    } else {
        $duplicateCount++;
    }
}


              $pdo->commit();
              
              $message = '<div class="alert success">✅ 時間安排提交成功！';
              if ($successCount > 0) {
                  $message .= ' 新增 ' . $successCount . ' 個時段。';
              }
              if ($duplicateCount > 0) {
                  $message .= ' 跳過 ' . $duplicateCount . ' 個重複時段。';
              }
              $message .= '</div>';

          } catch (PDOException $e) {
              $pdo->rollBack();
              $message = '<div class="alert error">❌ 提交失敗：' . htmlspecialchars($e->getMessage()) . '</div>';
          }
      }
  }
}

// 獲取用戶已選擇的時段
$userSelectedSlots = [];
try {
  $stmt = $pdo->prepare("
      SELECT DATE(ts.date_time) as date_part, HOUR(ts.date_time) as hour_part
      FROM time_slots ts 
      JOIN users u ON ts.user_id = u.user_id 
      WHERE u.account_id = ?
  ");
  $stmt->execute([$currentUserId]);
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  foreach ($results as $row) {
      $userSelectedSlots[] = $row['date_part'] . '_' . $row['hour_part'];
  }
} catch (PDOException $e) {
  // 處理錯誤，但不中斷程式
  $message .= '<div class="alert info">🔍 查詢現有記錄時發生錯誤：' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>拉圖斯時間調查表單</title>
<link rel="stylesheet" type="text/css" href="css/investigateStyle.css"
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

  <div class="instructions">
      <h3>📋 填寫說明</h3>
      <ul>
          <li>選擇日期期間（開始日期到結束日期）</li>
          <li>選擇時間範圍（開始時間到結束時間）</li>
          <li>點擊「生成時間表格」按鈕生成可選時段</li>
          <li>使用 checkbox 選擇您有空的時段</li>
          <li>黃色背景表示您已經選過的時段（無法重複選擇）</li>
      </ul>
  </div>

  <form method="POST" id="timeForm">
      <div class="date-time-selector">
          <div class="selector-row">
              <label for="start-date">開始日期：</label>
              <select id="start-date">
                  <option value="">請選擇開始日期</option>
                  <?php foreach ($futureDates as $date): ?>
                      <option value="<?php echo $date['dateStr']; ?>">
                          <?php echo $date['fullDisplay']; ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>
          
          <div class="selector-row">
              <label for="end-date">結束日期：</label>
              <select id="end-date">
                  <option value="">請選擇結束日期</option>
                  <?php foreach ($futureDates as $date): ?>
                      <option value="<?php echo $date['dateStr']; ?>">
                          <?php echo $date['fullDisplay']; ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>
          
          <div class="selector-row">
              <label for="start-time">開始時間：</label>
              <select id="start-time">
                  <option value="">請選擇開始時間</option>
                  <?php foreach ($timeOptions as $time): ?>
                      <option value="<?php echo substr($time, 0, 2); ?>">
                          <?php echo $time; ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>
          
          <div class="selector-row">
              <label for="end-time">結束時間：</label>
              <select id="end-time">
                  <option value="">請選擇結束時間</option>
                  <?php foreach ($timeOptions as $time): ?>
                      <option value="<?php echo substr($time, 0, 2); ?>">
                          <?php echo $time; ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>
          
          <button type="button" class="generate-btn" onclick="generateTimeTable()" id="generate-button" disabled>
              生成時間表格
          </button>
      </div>

      <div class="time-table-container" id="time-table-container">
          <h3>時間選擇表格：</h3>
          <div id="time-table-wrapper"></div>
          
          <div class="table-legend">
              <div class="legend-item">
                  <div class="legend-color available"></div>
                  <span>可選擇</span>
              </div>
              <div class="legend-item">
                  <div class="legend-color existing"></div>
                  <span>已選過</span>
              </div>
          </div>
          
          <div class="batch-operations">
              <button type="button" class="batch-btn select-all" onclick="selectAllAvailable()">選擇所有可用時段</button>
              <button type="button" class="batch-btn clear-selection" onclick="clearSelection()">清除選擇</button>
          </div>
      </div>

      <div class="selected-count" id="selected-count-display" style="display: none;">
          已選擇 <span class="count-number" id="selected-count">0</span> 個時間段
      </div>

      <div class="submit-section" id="submit-section" style="display: none;">
          <button type="submit" class="submit-btn">提交選擇的時間段</button>
          <button type="submit" name="clear_all" class="clear-btn" 
                  onclick="return confirm('確定要清除您的所有時間安排嗎？此操作無法復原！')">
              清除所有時段
          </button>
      </div>
  </form>
</div>

<script>
  const existingSlots = <?php echo json_encode($userSelectedSlots ?? []); ?>;
  let currentTableData = [];

  // 監聽下拉選單變化
  document.getElementById('start-date').addEventListener('change', checkGenerateButton);
  document.getElementById('end-date').addEventListener('change', checkGenerateButton);
  document.getElementById('start-time').addEventListener('change', checkGenerateButton);
  document.getElementById('end-time').addEventListener('change', checkGenerateButton);

  function checkGenerateButton() {
      const startDate = document.getElementById('start-date').value;
      const endDate = document.getElementById('end-date').value;
      const startTime = document.getElementById('start-time').value;
      const endTime = document.getElementById('end-time').value;
      const generateButton = document.getElementById('generate-button');
      
      if (startDate && endDate && startTime && endTime) {
          // 檢查日期和時間的邏輯性
          if (new Date(startDate) <= new Date(endDate) && parseInt(startTime) < parseInt(endTime)) {
              generateButton.disabled = false;
          } else {
              generateButton.disabled = true;
          }
      } else {
          generateButton.disabled = true;
      }
  }

  function generateTimeTable() {
      const startDate = document.getElementById('start-date').value;
      const endDate = document.getElementById('end-date').value;
      const startTime = parseInt(document.getElementById('start-time').value);
      const endTime = parseInt(document.getElementById('end-time').value);
      
      if (!startDate || !endDate || isNaN(startTime) || isNaN(endTime)) {
          alert('請完整選擇日期和時間範圍');
          return;
      }
      
      if (new Date(startDate) > new Date(endDate)) {
          alert('結束日期不能早於開始日期');
          return;
      }
      
      if (startTime >= endTime) {
          alert('結束時間必須晚於開始時間');
          return;
      }

      // 生成日期範圍
      const dates = [];
      const current = new Date(startDate);
      const end = new Date(endDate);
      
      while (current <= end) {
          const dayNames = ['日', '一', '二', '三', '四', '五', '六'];
          dates.push({
              dateStr: current.toISOString().split('T')[0],
              display: (current.getMonth() + 1) + '/' + current.getDate(),
              dayName: dayNames[current.getDay()]
          });
          current.setDate(current.getDate() + 1);
      }
      
      // 生成時間範圍
      const times = [];
      for (let i = startTime; i < endTime; i++) {
          times.push({
              hour: i,
              display: String(i).padStart(2, '0') + ':00-' + String(i + 1).padStart(2, '0') + ':00'
          });
      }
      
      // 生成表格
      let tableHTML = '<table class="time-table"><thead><tr><th>時間\\日期</th>';
      
      dates.forEach(date => {
          tableHTML += `<th>${date.display}<br>(週${date.dayName})</th>`;
      });
      
      tableHTML += '</tr></thead><tbody>';
      
      times.forEach(time => {
          tableHTML += `<tr><td><strong>${time.display}</strong></td>`;
          
          dates.forEach(date => {
              const slotKey = `${date.dateStr}_${time.hour}`;
              const isExisting = existingSlots.includes(slotKey);
              
              if (isExisting) {
                  tableHTML += `<td class="existing">
                      <input type="checkbox" disabled checked>
                      <span class="existing-label">已選過</span>
                  </td>`;
              } else {
                  tableHTML += `<td>
                      <input type="checkbox" name="time_slots[]" value="${slotKey}" onchange="updateSelectedCount()">
                  </td>`;
              }
          });
          
          tableHTML += '</tr>';
      });
      
      tableHTML += '</tbody></table>';
      
      document.getElementById('time-table-wrapper').innerHTML = tableHTML;
      document.getElementById('time-table-container').style.display = 'block';
      document.getElementById('submit-section').style.display = 'block';
      
      updateSelectedCount();
  }

  function selectAllAvailable() {
      const checkboxes = document.querySelectorAll('input[type="checkbox"]:not([disabled])');
      checkboxes.forEach(checkbox => {
          checkbox.checked = true;
      });
      updateSelectedCount();
  }

  function clearSelection() {
      const checkboxes = document.querySelectorAll('input[type="checkbox"]:not([disabled])');
      checkboxes.forEach(checkbox => {
          checkbox.checked = false;
      });
      updateSelectedCount();
  }

  function updateSelectedCount() {
      const checkedBoxes = document.querySelectorAll('input[type="checkbox"]:not([disabled]):checked');
      const count = checkedBoxes.length;
      
      document.getElementById('selected-count').textContent = count;
      
      if (count > 0) {
          document.getElementById('selected-count-display').style.display = 'block';
      } else {
          document.getElementById('selected-count-display').style.display = 'none';
      }
  }
</script>
</body>
</html>