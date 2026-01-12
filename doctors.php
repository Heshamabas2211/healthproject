<?php
require_once 'config.php';

// جلب الأطباء مع إمكانية البحث والتصفية
$search = $_GET['search'] ?? '';
$specialization = $_GET['specialization'] ?? '';

$sql = "
    SELECT u.id, u.full_name, d.specialization, d.experience_years, d.rating, d.bio, d.consultation_fee 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR d.specialization LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($specialization)) {
    $sql .= " AND d.specialization = ?";
    $params[] = $specialization;
}

$sql .= " ORDER BY d.rating DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// جلب التخصصات المتاحة
$specializations_stmt = $pdo->query("SELECT DISTINCT specialization FROM doctors ORDER BY specialization");
$specializations = $specializations_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الأطباء - رعاية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- شريط التنقل -->

    <div class="container py-5">
        <h2 class="section-title">فريق الأطباء المتخصصين</h2>
        
        <!-- نموذج البحث والتصفية -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="search" placeholder="ابحث عن طبيب..." value="<?= $search ?>">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="specialization">
                            <option value="">جميع التخصصات</option>
                            <?php foreach ($specializations as $spec): ?>
                                <option value="<?= $spec ?>" <?= $specialization === $spec ? 'selected' : '' ?>><?= $spec ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">بحث</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- قائمة الأطباء -->
        <div class="row g-4">
            <?php if (empty($doctors)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">لا توجد نتائج</div>
                </div>
            <?php else: ?>
                <?php foreach ($doctors as $doctor): ?>
                    <div class="col-md-4">
                        <div class="card doctor-card h-100">
                            <div class="card-body text-center">
                                <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 1.5rem;">
                                    <?= mb_substr($doctor['full_name'], 0, 1) ?>
                                </div>
                                <h5 class="card-title"><?= $doctor['full_name'] ?></h5>
                                <p class="card-text text-primary"><?= $doctor['specialization'] ?></p>
                                <p class="card-text">خبرة: <?= $doctor['experience_years'] ?> سنوات</p>
                                <p class="card-text">سعر الكشف: <?= $doctor['consultation_fee'] ?> ريال</p>
                                <div class="text-warning mb-3">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= floor($doctor['rating']) ? '' : '-half-alt' ?>"></i>
                                    <?php endfor; ?>
                                    <span class="text-muted">(<?= $doctor['rating'] ?>)</span>
                                </div>
                                <p class="card-text small"><?= $doctor['bio'] ?></p>
                                <?php if (isLoggedIn() && isPatient()): ?>
                                    <a href="appointments.php?book_doctor=<?= $doctor['id'] ?>" class="btn btn-primary">حجز موعد</a>
                                <?php elseif (!isLoggedIn()): ?>
                                    <a href="auth.php?type=login" class="btn btn-outline-primary">سجل الدخول للحجز</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>