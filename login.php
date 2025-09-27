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
    <link rel="stylesheet" type="text/css" href="css/loginStyle.css"> 
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