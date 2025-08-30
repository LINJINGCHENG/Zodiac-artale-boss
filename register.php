<?php
session_start();

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

$message = '';

// 處理註冊
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $inputUsername = trim($_POST['username']);
    $inputPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($inputUsername) || empty($inputPassword) || empty($confirmPassword)) {
        $message = '<div class="alert error">請填寫所有欄位</div>';
    } elseif ($inputPassword !== $confirmPassword) {
        $message = '<div class="alert error">密碼確認不一致</div>';
    } elseif (strlen($inputPassword) < 6) {
        $message = '<div class="alert error">密碼至少需要6個字元</div>';
    } else {
        // 檢查帳號是否已存在
        $stmt = $pdo->prepare("SELECT id FROM user_accounts WHERE username = ?");
        $stmt->execute([$inputUsername]);
        
        if ($stmt->fetch()) {
            $message = '<div class="alert error">此帳號已被使用</div>';
        } else {
            // 建立新帳號
            $hashedPassword = password_hash($inputPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO user_accounts (username, password) VALUES (?, ?)");
            
            if ($stmt->execute([$inputUsername, $hashedPassword])) {
                $message = '<div class="alert success">註冊成功！請登入</div>';
            } else {
                $message = '<div class="alert error">註冊失敗，請稍後再試</div>';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>拉圖斯時間調查 - 註冊</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Microsoft JhengHei', Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #333;
            margin: 0;
            font-size: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        
        .alert.error {
            background: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        
        .alert.success {
            background: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>🕐 拉圖斯時間調查</h1>
            <p>建立新帳號</p>
        </div>
        
        <?php echo $message; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">帳號：</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">密碼：</label>
                <input type="password" id="password" name="password" required>
                <div class="password-hint">密碼至少需要6個字元</div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">確認密碼：</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" name="register" class="btn">註冊</button>
        </form>
        
        <div class="login-link">
            <p>已有帳號？ <a href="login.php">立即登入</a></p>
        </div>
    </div>
</body>
</html>
