<?php
require_once 'config.php';

$type = $_GET['type'] ?? 'login';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($type === 'register') {
        // تسجيل مستخدم جديد
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $user_type = sanitize($_POST['user_type']);
        
        // التحقق من البيانات
        if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
            $error = 'جميع الحقول مطلوبة';
        } elseif ($password !== $confirm_password) {
            $error = 'كلمات المرور غير متطابقة';
        } else {
            // التحقق من عدم وجود البريد الإلكتروني مسبقاً
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'البريد الإلكتروني مسجل مسبقاً';
            } else {
                // تسجيل المستخدم
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, user_type) VALUES (?, ?, ?, ?, ?)");
                
                if ($stmt->execute([$full_name, $email, $phone, $hashed_password, $user_type])) {
                    if ($user_type === 'doctor') {
                        // إدخال بيانات الطبيب
                        $user_id = $pdo->lastInsertId();
                        $specialization = sanitize($_POST['specialization']);
                        $license_number = sanitize($_POST['license_number']);
                        
                        $stmt = $pdo->prepare("INSERT INTO doctors (user_id, specialization, license_number) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $specialization, $license_number]);
                    }
                    
                    $success = 'تم إنشاء الحساب بنجاح. يمكنك تسجيل الدخول الآن.';
                    $type = 'login';
                } else {
                    $error = 'حدث خطأ أثناء إنشاء الحساب';
                }
            }
        }
    } else {
        // تسجيل الدخول
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['user_email'] = $user['email'];
            
            redirect('dashboard.php');
        } else {
            $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $type === 'login' ? 'تسجيل الدخول' : 'إنشاء حساب' ?> - رعاية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-heartbeat me-2"></i>رعاية</a>
        </div>
    </nav>

    <div class="container">
        <div class="login-container">
            <h2 class="text-center mb-4"><?= $type === 'login' ? 'تسجيل الدخول' : 'إنشاء حساب' ?></h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <?php if ($type === 'login'): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">كلمة المرور</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">تسجيل الدخول</button>
                    <div class="text-center mt-3">
                        <p>ليس لديك حساب؟ <a href="auth.php?type=register">إنشاء حساب جديد</a></p>
                    </div>
                </form>
            <?php else: ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">الاسم الكامل</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">رقم الهاتف</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="user_type" class="form-label">نوع المستخدم</label>
                        <select class="form-select" id="user_type" name="user_type" required>
                            <option value="">اختر نوع المستخدم</option>
                            <option value="patient">مريض</option>
                            <option value="doctor">طبيب</option>
                        </select>
                    </div>
                    <div id="doctor_fields" style="display: none;">
                        <div class="mb-3">
                            <label for="specialization" class="form-label">التخصص</label>
                            <input type="text" class="form-control" id="specialization" name="specialization">
                        </div>
                        <div class="mb-3">
                            <label for="license_number" class="form-label">رقم الترخيص</label>
                            <input type="text" class="form-control" id="license_number" name="license_number">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">كلمة المرور</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">إنشاء حساب</button>
                    <div class="text-center mt-3">
                        <p>لديك حساب بالفعل؟ <a href="auth.php?type=login">تسجيل الدخول</a></p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('user_type').addEventListener('change', function() {
            const doctorFields = document.getElementById('doctor_fields');
            doctorFields.style.display = this.value === 'doctor' ? 'block' : 'none';
        });
    </script>
</body>
</html>