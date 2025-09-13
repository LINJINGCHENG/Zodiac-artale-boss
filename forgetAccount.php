<?php
session_start();

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

// 處理 AJAX 請求
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');

    $response = [
        'success' => false,
        'message' => '',
        'showResult' => false,
        'data' => null
    ];

    // 忘記帳號查詢
    if (isset($_POST['forget_account_by_name'])) {
        $inputUsername = trim($_POST['username']);
        $inputPassword = trim($_POST['password']);

        if (empty($inputUsername) || empty($inputPassword)) {
            $response['message'] = '請輸入使用者名稱和密碼';
        } else {
            $userCheck = $pdo->prepare("SELECT account, password FROM user_accounts WHERE username = ?");
            $userCheck->execute([$inputUsername]);
            $result = $userCheck->fetch();

            if ($result && password_verify($inputPassword, $result['password'])) {
                $response['success'] = true;
                $response['showResult'] = true;
                $response['data'] = [
                    'type' => 'account_by_username',
                    'username' => $inputUsername,
                    'account' => $result['account']
                ];
                $response['message'] = '查詢成功';
            } else {
                $response['message'] = '使用者名稱或密碼錯誤';
            }
        }
    }

    // 重設密碼
    if (isset($_POST['reset_password'])) {
        $inputAccount = trim($_POST['account']);
        $inputUsername = trim($_POST['username']);
        $newPassword = trim($_POST['new_password']);
        $confirmPassword = trim($_POST['confirm_password']);

        if (empty($inputAccount) || empty($inputUsername) || empty($newPassword) || empty($confirmPassword)) {
            $response['message'] = '請填寫所有欄位';
        } elseif ($newPassword !== $confirmPassword) {
            $response['message'] = '新密碼確認不一致';
        } elseif (strlen($newPassword) < 6) {
            $response['message'] = '密碼長度至少需要6個字元';
        } else {
            $userCheck = $pdo->prepare("SELECT id FROM user_accounts WHERE account = ? AND username = ?");
            $userCheck->execute([$inputAccount, $inputUsername]);
            $result = $userCheck->fetch();

            if ($result) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateUserAccounts = $pdo->prepare("UPDATE user_accounts SET password = ? WHERE id = ?");

                if ($updateUserAccounts->execute([$hashedPassword, $result['id']])) {
                    $response['success'] = true;
                    $response['message'] = '密碼重設成功！請使用新密碼登入';
                } else {
                    $response['message'] = '密碼重設失敗，請稍後再試';
                }
            } else {
                $response['message'] = '帳號與使用者名稱不匹配';
            }
        }
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
?>

<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>拉圖斯時間調查 - 忘記帳號或密碼</title>
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

        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
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

        .logo p {
            color: #666;
            margin: 10px 0 0 0;
            font-size: 14px;
        }

        .form-container {
            margin-bottom: 20px;
        }

        .form-container h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 20px;
        }

        .form-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.5;
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
            margin-bottom: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn.secondary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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

        .result-container {
            margin-bottom: 20px;
        }

        .result-container .alert h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }

        .result-container .alert p {
            margin: 10px 0;
            font-size: 16px;
        }

        .result-container .btn {
            width: auto;
            margin: 5px;
            padding: 8px 20px;
            display: inline-block;
        }

        .form-switch {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .form-switch a {
            color: #667eea;
            text-decoration: none;
        }

        .form-switch a:hover {
            text-decoration: underline;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
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

        /* 載入動畫 */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading .btn {
            background: #ccc;
            cursor: not-allowed;
        }

        /* 淡入淡出動畫 */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        .fade-out {
            animation: fadeOut 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(-10px);
            }
        }

        /* 響應式設計 */
        @media (max-width: 480px) {
            .container {
                padding: 20px;
                margin: 20px;
            }

            .result-container .btn {
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <h1>🕐 拉圖斯時間調查</h1>
            <p>帳號與密碼找回</p>
        </div>

        <!-- 訊息顯示區域 -->
        <div id="message-area"></div>

        <!-- 查詢結果顯示區域 -->
        <div id="result-area" class="result-container" style="display: none;">
            <div class="alert success">
                <h3>✅ 查詢成功！</h3>
                <p>您的帳號是：<strong id="found-account"></strong></p>
                <button type="button" class="btn secondary" onclick="showResetForm()">重設密碼</button>
                <button type="button" class="btn" onclick="location.href='login.php'">返回登入</button>
            </div>
        </div>

        <!-- 忘記帳號表單 -->
        <div id="forget-account-form" class="form-container">
            <h2>忘記帳號？</h2>
            <p class="form-description">請輸入您的 artale 名稱和密碼來查詢帳號</p>

            <form id="forgetAccountForm">
                <div class="form-group">
                    <label for="username">artale名稱：</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="password">密碼：</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn">查詢帳號</button>
            </form>

            <div class="form-switch">
                <p>想要重設密碼？ <a href="#" onclick="showResetForm()">點此重設</a></p>
            </div>
        </div>

        <!-- 重設密碼表單 -->
        <div id="reset-password-form" class="form-container" style="display: none;">
            <h2>重設密碼</h2>
            <p class="form-description">請輸入您的帳號和 artale 名稱來重設密碼</p>

            <form id="resetPasswordForm">
                <div class="form-group">
                    <label for="reset-account">帳號：</label>
                    <input type="text" id="reset-account" name="account" required>
                    <div class="password-hint">請輸入您的登入帳號</div>
                </div>

                <div class="form-group">
                    <label for="reset-username">artale名稱：</label>
                    <input type="text" id="reset-username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="new-password">新密碼：</label>
                    <input type="password" id="new-password" name="new_password" required>
                    <div class="password-hint">密碼至少需要6個字元</div>
                </div>

                <div class="form-group">
                    <label for="confirm-password">確認新密碼：</label>
                    <input type="password" id="confirm-password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn">重設密碼</button>
            </form>

            <div class="form-switch">
                <p>想要查詢帳號？ <a href="#" onclick="showForgetForm()">點此查詢</a></p>
            </div>
        </div>

        <div class="login-link">
            <p>記起來了？ <a href="login.php">立即登入</a></p>
        </div>
    </div>

    <script>
        // DOM 元素
        const forgetAccountForm = document.getElementById('forget-account-form');
        const resetPasswordForm = document.getElementById('reset-password-form');
        const messageArea = document.getElementById('message-area');
        const resultArea = document.getElementById('result-area');
        const foundAccountSpan = document.getElementById('found-account');

        // 表單元素
        const forgetForm = document.getElementById('forgetAccountForm');
        const resetForm = document.getElementById('resetPasswordForm');

        // 獲取當前頁面的檔案名稱
        const currentPage = window.location.pathname.split('/').pop();

        // 顯示訊息
        function showMessage(message, type = 'error') {
            messageArea.innerHTML = `<div class="alert ${type}">${message}</div>`;
            messageArea.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        // 清除訊息
        function clearMessage() {
            messageArea.innerHTML = '';
        }

        // 顯示忘記帳號表單
        function showForgetForm() {
            forgetAccountForm.style.display = 'block';
            resetPasswordForm.style.display = 'none';
            resultArea.style.display = 'none';
            clearMessage();

            // 添加動畫效果
            forgetAccountForm.classList.add('fade-in');
            setTimeout(() => forgetAccountForm.classList.remove('fade-in'), 300);
        }

        // 顯示重設密碼表單
        function showResetForm() {
            forgetAccountForm.style.display = 'none';
            resetPasswordForm.style.display = 'block';
            resultArea.style.display = 'none';
            clearMessage();

            // 添加動畫效果
            resetPasswordForm.classList.add('fade-in');
            setTimeout(() => resetPasswordForm.classList.remove('fade-in'), 300);
        }

        // 顯示查詢結果
        function showResult(account) {
            foundAccountSpan.textContent = account;
            resultArea.style.display = 'block';
            forgetAccountForm.style.display = 'none';
            resetPasswordForm.style.display = 'none';
            clearMessage();

            // 添加動畫效果
            resultArea.classList.add('fade-in');
            setTimeout(() => resultArea.classList.remove('fade-in'), 300);
        }

        // 表單驗證
        function validateForgetForm(formData) {
            const username = formData.get('username').trim();
            const password = formData.get('password').trim();

            if (!username || !password) {
                showMessage('請輸入使用者名稱和密碼');
                return false;
            }

            return true;
        }

        function validateResetForm(formData) {
            const account = formData.get('account').trim();
            const username = formData.get('username').trim();
            const newPassword = formData.get('new_password').trim();
            const confirmPassword = formData.get('confirm_password').trim();

            if (!account || !username || !newPassword || !confirmPassword) {
                showMessage('請填寫所有欄位');
                return false;
            }

            if (newPassword !== confirmPassword) {
                showMessage('新密碼確認不一致');
                return false;
            }

            if (newPassword.length < 6) {
                showMessage('密碼長度至少需要6個字元');
                return false;
            }

            return true;
        }

        // 設置載入狀態
        function setLoading(form, loading) {
            const container = form.closest('.form-container');
            if (loading) {
                container.classList.add('loading');
            } else {
                container.classList.remove('loading');
            }
        }

        // 忘記帳號表單提交
        forgetForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearMessage();

            const formData = new FormData(forgetForm);

            if (!validateForgetForm(formData)) {
                return;
            }

            setLoading(forgetForm, true);

            try {
                // 添加表單識別
                formData.append('forget_account_by_name', '1');

                // 使用當前頁面發送請求
                const response = await fetch(currentPage, {
                    method: 'POST',
                    body: formData
                });

                // 檢查響應是否成功
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('伺服器回應格式錯誤');
                }

                const result = await response.json();

                if (result.success) {
                    if (result.showResult && result.data) {
                        showResult(result.data.account);
                    } else {
                        showMessage('查詢成功', 'success');
                    }
                } else {
                    showMessage(result.message || '查詢失敗，請稍後再試');
                }
            } catch (error) {
                console.error('Error:', error);
                if (error.message.includes('HTTP error')) {
                    showMessage('伺服器錯誤，請稍後再試');
                } else if (error.message.includes('格式錯誤')) {
                    showMessage('伺服器回應格式錯誤，請聯絡管理員');
                } else {
                    showMessage('網路錯誤，請檢查連線後再試');
                }
            } finally {
                setLoading(forgetForm, false);
            }
        });

        // 重設密碼表單提交
        resetForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearMessage();

            const formData = new FormData(resetForm);

            if (!validateResetForm(formData)) {
                return;
            }

            setLoading(resetForm, true);

            try {
                // 添加表單識別
                formData.append('reset_password', '1');

                // 使用當前頁面發送請求
                const response = await fetch(currentPage, {
                    method: 'POST',
                    body: formData
                });

                // 檢查響應是否成功
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('伺服器回應格式錯誤');
                }

                const result = await response.json();

                if (result.success) {
                    showMessage(result.message || '密碼重設成功！請使用新密碼登入', 'success');
                    // 3秒後跳轉到登入頁面
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 3000);
                } else {
                    showMessage(result.message || '密碼重設失敗，請稍後再試');
                }
            } catch (error) {
                console.error('Error:', error);
                if (error.message.includes('HTTP error')) {
                    showMessage('伺服器錯誤，請稍後再試');
                } else if (error.message.includes('格式錯誤')) {
                    showMessage('伺服器回應格式錯誤，請聯絡管理員');
                } else {
                    showMessage('網路錯誤，請檢查連線後再試');
                }
            } finally {
                setLoading(resetForm, false);
            }
        });

        // 密碼確認即時驗證
        document.getElementById('confirm-password').addEventListener('input', function() {
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = this.value;

            if (confirmPassword && newPassword !== confirmPassword) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#ddd';
            }
        });

        // 初始化頁面
        document.addEventListener('DOMContentLoaded', function() {
            // 檢查 URL 參數決定顯示哪個表單
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');

            if (action === 'reset') {
                showResetForm();
            } else {
                showForgetForm();
            }
        });

        // 全域函數供 HTML 調用
        window.showForgetForm = showForgetForm;
        window.showResetForm = showResetForm;
    </script>
</body>

</html>