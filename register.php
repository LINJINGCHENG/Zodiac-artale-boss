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
    $inputAccount = trim($_POST['account']);
    $inputUsername = trim($_POST['username']);
    $inputPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($inputUsername) || empty($inputAccount) || empty($inputPassword) || empty($confirmPassword)) {
        $message = '<div class="alert error">請填寫所有欄位</div>';
    } elseif (strlen($inputAccount) < 7) {
        $message = '<div class="alert error">帳號至少需要7個字元</div>';
    } elseif (!preg_match('/[A-Z]/', $inputPassword)) {
        $message = '<div class="alert error">密碼必須包含至少一個大寫字母</div>';
    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]/', $inputPassword)) {
        $message = '<div class="alert error">密碼必須包含至少一個特殊符號</div>';
    } elseif ($inputPassword !== $confirmPassword) {
        $message = '<div class="alert error">密碼確認不一致</div>';
    } elseif (strlen($inputPassword) < 6) {
        $message = '<div class="alert error">密碼至少需要6個字元</div>';
    } else {
        // 檢查帳號是否已存在
        $usernameCheck = $pdo->prepare("SELECT id FROM user_accounts WHERE username = ?");
        $userAccountCheck = $pdo->prepare("SELECT id FROM user_accounts WHERE account = ?");
        $usernameCheck->execute([$inputUsername]);
        $userAccountCheck->execute([$inputAccount]);

        if ($usernameCheck->fetch()) {
            $message = '<div class="alert error">此使用者名稱已被使用</div>';
        } elseif ($userAccountCheck->fetch()) {
            $message = '<div class="alert error">此帳號已被使用</div>';
        } else {
            // 建立新帳號
            $hashedPassword = password_hash($inputPassword, PASSWORD_DEFAULT);

            $insertAccountData = $pdo->prepare("INSERT INTO user_accounts (account, username, password) VALUES (?, ?, ?)");

            if ($insertAccountData->execute([$inputAccount, $inputUsername, $hashedPassword])) {
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
    <link rel="stylesheet" type="text/css" href="css/registerStyle.css">  
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
                <input type="text" id="account" name="account" required>
                <div class="password-hint">帳號至少需要7個字元</div>
            </div>
            <div class="form-group">
                <label for="username">artale名稱：</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">密碼：</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required oninput="checkPasswordStrength(this.value)">
                    <span class="toggle-password" onclick="togglePassword('password')">
                        👁
                    </span>
                    <div class="password-hint">
                        密碼至少需要6個字元，包含大寫字母和特殊符號
                    </div>
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="strength-indicator">
                            密碼強度:
                            <span class="strength-level" id="strengthLevel">
                                弱
                            </span>
                        </div>
                        <ul class="requirements">
                            <li id="length">至少6個字元</li>
                            <li id="uppercase">包含大寫字母</li>
                            <li id="symbol">包含特殊符號</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="confirm_password">確認密碼：</label>
                <div class="password-field">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>

            <button type="submit" name="register" class="btn">註冊</button>
        </form>

        <div class="login-link">
            <p>已有帳號？ <a href="login.php">立即登入</a></p>
        </div>
    </div>
</body>
<script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const toggle = field.nextElementSibling;
        const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
        field.setAttribute('type', type);
        toggle.textContent = type === 'password' ? '👁' : '🙈';
    }

    function checkPasswordStrength(password) {
        const strengthDiv = document.getElementById('passwordStrength');
        const strengthLevel = document.getElementById('strengthLevel');

        if (password.length === 0) {
            strengthDiv.style.display = 'none';
            return;
        }

        strengthDiv.style.display = 'block';

        const requirements = {
            length: password.length >= 6,
            uppercase: /[A-Z]/.test(password),
            symbol: /[^a-zA-Z0-9]/.test(password)
        };

        Object.keys(requirements).forEach(req => {
            const element = document.getElementById(req);
            if (requirements[req]) {
                element.classList.add('valid');
            } else {
                element.classList.remove('valid');
            }
        });

        const score = Object.values(requirements).filter(Boolean).length;
        let level, className;

        switch (score) {
            case 0:
            case 1:
                level = '弱';
                className = 'weak';
                break;
            case 2:
                level = '中等';
                className = 'medium';
                break;
            case 3:
                level = '強';
                className = 'strong';
                break;
        }

        strengthLevel.textContent = level;
        strengthLevel.className = 'strength-level' + className;
    }
</script>

</html>