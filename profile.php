<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('auth.php?type=login');
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $blood_type = $_POST['blood_type'];
    $chronic_diseases = sanitize($_POST['chronic_diseases']);
    $allergies = sanitize($_POST['allergies']);
    
    $stmt = $pdo->prepare("
        UPDATE users SET 
        full_name = ?, phone = ?, date_of_birth = ?, gender = ?, 
        address = ?, city = ?, blood_type = ?, chronic_diseases = ?, allergies = ? 
        WHERE id = ?
    ");
    
    if ($stmt->execute([$full_name, $phone, $date_of_birth, $gender, $address, $city, $blood_type, $chronic_diseases, $allergies, $user_id])) {
        $_SESSION['user_name'] = $full_name;
        $_SESSION['success'] = 'تم تحديث الملف الشخصي بنجاح';
    } else {
        $_SESSION['error'] = 'حدث خطأ أثناء تحديث الملف الشخصي';
    }
    
    redirect('profile.php');
}

// جلب بيانات المستخدم
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي - رعاية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- نفس شريط التنقل من dashboard.php -->

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <h2 class="mb-4">الملف الشخصي</h2>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">الاسم الكامل</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= $user['full_name'] ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">رقم الهاتف</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= $user['phone'] ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">تاريخ الميلاد</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= $user['date_of_birth'] ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">الجنس</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">اختر الجنس</option>
                                        <option value="male" <?= $user['gender'] === 'male' ? 'selected' : '' ?>>ذكر</option>
                                        <option value="female" <?= $user['gender'] === 'female' ? 'selected' : '' ?>>أنثى</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">العنوان</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?= $user['address'] ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">المدينة</label>
                                    <input type="text" class="form-control" id="city" name="city" value="<?= $user['city'] ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="blood_type" class="form-label">فصيلة الدم</label>
                                    <select class="form-select" id="blood_type" name="blood_type">
                                        <option value="">اختر فصيلة الدم</option>
                                        <option value="A+" <?= $user['blood_type'] === 'A+' ? 'selected' : '' ?>>A+</option>
                                        <option value="A-" <?= $user['blood_type'] === 'A-' ? 'selected' : '' ?>>A-</option>
                                        <option value="B+" <?= $user['blood_type'] === 'B+' ? 'selected' : '' ?>>B+</option>
                                        <option value="B-" <?= $user['blood_type'] === 'B-' ? 'selected' : '' ?>>B-</option>
                                        <option value="AB+" <?= $user['blood_type'] === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                        <option value="AB-" <?= $user['blood_type'] === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                        <option value="O+" <?= $user['blood_type'] === 'O+' ? 'selected' : '' ?>>O+</option>
                                        <option value="O-" <?= $user['blood_type'] === 'O-' ? 'selected' : '' ?>>O-</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="chronic_diseases" class="form-label">الأمراض المزمنة (إن وجدت)</label>
                                <input type="text" class="form-control" id="chronic_diseases" name="chronic_diseases" value="<?= $user['chronic_diseases'] ?>" placeholder="مثل: السكري، الضغط، إلخ">
                            </div>
                            
                            <div class="mb-3">
                                <label for="allergies" class="form-label">الحساسيات (إن وجدت)</label>
                                <input type="text" class="form-control" id="allergies" name="allergies" value="<?= $user['allergies'] ?>" placeholder="مثل: البنسلين، بعض الأطعمة، إلخ">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>