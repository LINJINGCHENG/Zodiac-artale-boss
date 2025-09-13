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

// 處理登入
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $inputUserAccount = trim($_POST['user_account']);
    $inputPassword = $_POST['password'];

    if (empty($inputUserAccount) || empty($inputPassword)) {
        $message = '<div class="alert error">請填寫帳號和密碼</div>';
    } else {
        $accountCheck = $pdo->prepare("SELECT id, account, username, password, is_admin FROM user_accounts WHERE account = ?");
        $accountCheck->execute([$inputUserAccount]);
        $user = $accountCheck->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($inputPassword, $user['password'])) {
            $_SESSION['account'] =$user['account'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];

            // 重定向到調查頁面
            header("Location: investigate.php");
            exit();
        } else {
            $message = '<div class="alert error">帳號或密碼錯誤</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>拉圖斯時間調查 - 登入</title>
    <style>
        * {
            box-sizing: border-box;
        }

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

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
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

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
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

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">
            <h1>🕐 拉圖斯時間調查</h1>
            <p>請登入您的帳號</p>
        </div>

        <?php echo $message; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">帳號：</label>
                <input type="text" id="user_account" name="user_account" required>
            </div>

            <div class="form-group">
                <label for="password">密碼：</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" name="login" class="btn">登入</button>
        </form>

        <div class="register-link">
            <p>還沒有帳號？ <a href="register.php">立即註冊</a></p>
        </div>
        <div class="register-link">
            <p>忘記帳號或密碼帳號？ <a href="forgetAccount.php">忘記帳號或密碼</a></p>
        </div>
    </div>
</body>

</html>