<?php
session_start();
include 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Step 1: Email
if ($step == 1 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Generate verification code
        $verification_code = rand(100000, 999999);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Store in session for verification
        $_SESSION['reset_data'] = [
            'email' => $email,
            'verification_code' => $verification_code,
            'code_expiry' => $expiry
        ];
        
        // Update verification code in database
        $update_stmt = $conn->prepare("UPDATE users SET verification_code = ?, code_expiry = ? WHERE email = ?");
        $update_stmt->bind_param("sss", $verification_code, $expiry, $email);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Send verification email
        $subject = "رمز التحقق لإعادة تعيين كلمة المرور في Poppo Live";
        $message = generateVerificationEmail($verification_code, 'reset');
        $api_url = "https://test-hosting-bot.ct.ws/imad.php?email=".urlencode($email)."&apikey=imad213&subject=".urlencode($subject)."&message=".urlencode($message)."&code=".$verification_code;
        
        $response = file_get_contents($api_url);
        if ($response !== false) {
            header("Location: reset_password.php?step=2");
            exit();
        } else {
            $error = "حدث خطأ في إرسال رمز التحقق، يرجى المحاولة لاحقاً";
        }
    } else {
        $error = "البريد الإلكتروني غير مسجل";
    }
    $stmt->close();
}

// Step 2: Verify code
if ($step == 2 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_code = sanitize_input($_POST['verification_code']);
    $email = $_SESSION['reset_data']['email'];
    
    // Check code from database
    $stmt = $conn->prepare("SELECT verification_code, code_expiry FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $stored_code = $user['verification_code'];
        $expiry = $user['code_expiry'];
        
        if (time() > strtotime($expiry)) {
            $error = "رمز التحقق انتهت صلاحيته، يرجى طلب رمز جديد";
            unset($_SESSION['reset_data']);
            header("Location: reset_password.php");
            exit();
        } elseif ($user_code == $stored_code) {
            header("Location: reset_password.php?step=3");
            exit();
        } else {
            $error = "رمز التحقق غير صحيح";
        }
    } else {
        $error = "حدث خطأ، يرجى المحاولة لاحقاً";
    }
    $stmt->close();
}

// Step 3: Set new password
if ($step == 3 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = sanitize_input($_POST['password']);
    $confirm_password = sanitize_input($_POST['confirm_password']);
    $email = $_SESSION['reset_data']['email'];
    
    if ($password !== $confirm_password) {
        $error = "كلمة المرور غير متطابقة";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ?, verification_code = NULL, code_expiry = NULL WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if ($stmt->execute()) {
            $success = "تم تحديث كلمة المرور بنجاح! يمكنك الآن تسجيل الدخول.";
            unset($_SESSION['reset_data']);
            header("Refresh: 3; url=index.php");
        } else {
            $error = "حدث خطأ أثناء تحديث كلمة المرور، يرجى المحاولة لاحقاً";
        }
        $stmt->close();
    }
}

function generateVerificationEmail($code, $type) {
    $title = ($type == 'register') ? "مرحباً بك في Poppo Live!" : "إعادة تعيين كلمة المرور";
    $action = ($type == 'register') ? "إتمام عملية التسجيل" : "إعادة تعيين كلمة المرور";
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>رمز التحقق</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            padding: 20px;
            direction: rtl;
        }
        .container {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            max-width: 400px;
            margin: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .code {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin: 20px 0;
            text-align: center;
        }
        .footer {
            font-size: 12px;
            color: #6c757d;
            margin-top: 30px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>$title</h2>
        <p>لقد طلبت إعادة تعيين كلمة المرور. رمز التحقق الخاص بك هو:</p>
        <div class="code">$code</div>
        <p>يرجى استخدام هذا الرمز ل$action.</p>
        <p>إذا لم تطلب هذا الرمز، يمكنك تجاهل هذه الرسالة.</p>
        <div class="footer">هذا البريد تم إنشاؤه تلقائياً، يرجى عدم الرد عليه.</div>
    </div>
</body>
</html>
HTML;

    return $html;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - Poppo Live</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Tajawal', sans-serif;
        }
        .reset-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            max-width: 150px;
        }
        .form-control {
            margin-bottom: 15px;
        }
        .btn-primary {
            width: 100%;
            padding: 10px;
            font-weight: bold;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        .step-number {
            width: 30px;
            height: 30px;
            background-color: #e9ecef;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .step.active .step-number {
            background-color: #0d6efd;
            color: white;
        }
        .step.completed .step-number {
            background-color: #198754;
            color: white;
        }
        .step-title {
            font-size: 14px;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #e9ecef;
            z-index: -1;
        }
        .step.completed:not(:last-child)::after {
            background-color: #198754;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-container">
            <div class="logo">
                <h3>إعادة تعيين كلمة المرور</h3>
            </div>
            
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <div class="step-number">1</div>
                    <div class="step-title">البريد الإلكتروني</div>
                </div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-title">التحقق</div>
                </div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-title">كلمة المرور الجديدة</div>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
            
                <?php if ($step == 1): ?>
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <button type="submit" class="btn btn-primary">إرسال رمز التحقق</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="index.php">العودة إلى تسجيل الدخول</a>
                    </div>
                
                <?php elseif ($step == 2): ?>
                    <form action="" method="POST">
                        <div class="mb-3">
                            <p>تم إرسال رمز التحقق إلى بريدك الإلكتروني: <strong><?php echo $_SESSION['reset_data']['email']; ?></strong></p>
                            <label for="verification_code" class="form-label">رمز التحقق</label>
                            <input type="text" class="form-control" id="verification_code" name="verification_code" required maxlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary">تحقق</button>
                    </form>
                
                <?php elseif ($step == 3): ?>
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="password" class="form-label">كلمة المرور الجديدة</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">تأكيد كلمة المرور الجديدة</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary">تحديث كلمة المرور</button>
                    </form>
                <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
