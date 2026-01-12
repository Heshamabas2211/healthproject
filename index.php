<?php
require_once 'config.php';

// جلب الأطباء للعرض في الصفحة الرئيسية
$doctors_stmt = $pdo->query("
    SELECT u.full_name, d.specialization, d.experience_years, d.rating, d.bio 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    ORDER BY d.rating DESC 
    LIMIT 3
");
$featured_doctors = $doctors_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رعاية - منصة الرعاية الصحية المنزلية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- شريط التنقل -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-heartbeat me-2"></i>رعاية</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">الرئيسية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="doctors.php">الأطباء</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">من نحن</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">اتصل بنا</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php" class="btn btn-outline-light me-2">لوحة التحكم</a>
                        <a href="logout.php" class="btn btn-light">تسجيل الخروج</a>
                    <?php else: ?>
                        <a href="auth.php?type=login" class="btn btn-outline-light me-2">تسجيل الدخول</a>
                        <a href="auth.php?type=register" class="btn btn-light">إنشاء حساب</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- قسم البطل -->
    <section class="hero-section">
        <div class="container">
            <h1 class="display-4 fw-bold">الرعاية الصحية في منزلك</h1>
            <p class="lead mb-4">منصة سعودية متكاملة لتقديم خدمات الرعاية الصحية المنزلية بأعلى معايير الجودة</p>
            <?php if (!isLoggedIn()): ?>
                <a href="auth.php?type=register" class="btn btn-primary btn-lg me-2">احجز موعد الآن</a>
            <?php else: ?>
                <a href="appointments.php" class="btn btn-primary btn-lg me-2">إدارة المواعيد</a>
            <?php endif; ?>
            <a href="doctors.php" class="btn btn-outline-light btn-lg">استكشف الأطباء</a>
        </div>
    </section>

    <!-- قسم الخدمات -->
    <section class="py-5">
        <div class="container">
            <h2 class="section-title">خدماتنا</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card service-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-user-md fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">زيارات طبية منزلية</h5>
                            <p class="card-text">تقديم خدمات طبية متخصصة في منزلك من قبل أطباء معتمدين</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card service-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-procedures fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">تمريض منزلي</h5>
                            <p class="card-text">رعاية تمريضية متخصصة للمرضى في منازلهم بتقنيات حديثة</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card service-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-pills fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">صيدلية منزلية</h5>
                            <p class="card-text">توصيل الأدوية والمستلزمات الطبية إلى منزلك في أي وقت</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- قسم الأطباء المميزين -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="section-title">أطباؤنا المتميزون</h2>
            <div class="row g-4">
                <?php foreach ($featured_doctors as $doctor): ?>
                    <div class="col-md-4">
                        <div class="card doctor-card h-100">
                            <div class="card-body text-center">
                                <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 1.5rem;">
                                    <?= mb_substr($doctor['full_name'], 0, 1) ?>
                                </div>
                                <h5 class="card-title"><?= $doctor['full_name'] ?></h5>
                                <p class="card-text text-primary"><?= $doctor['specialization'] ?></p>
                                <p class="card-text">خبرة: <?= $doctor['experience_years'] ?> سنوات</p>
                                <div class="text-warning mb-3">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= floor($doctor['rating']) ? '' : '-half-alt' ?>"></i>
                                    <?php endfor; ?>
                                    <span class="text-muted">(<?= $doctor['rating'] ?>)</span>
                                </div>
                                <p class="card-text small"><?= $doctor['bio'] ?></p>
                             
                              
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- قسم من نحن -->
    <section id="about" class="py-5">
        <div class="container">
            <h2 class="section-title">من نحن</h2>
            <div class="row">
                <div class="col-md-6">
                    <h3>رسالتنا</h3>
                    <p>نحن منصة سعودية رائدة في تقديم خدمات الرعاية الصحية المنزلية، نهدف إلى توفير رعاية طبية متميزة في منازل المرضى لتخفيف العبء عن كاهلهم وذويهم.</p>
                </div>
                <div class="col-md-6">
                    <h3>رؤيتنا</h3>
                    <p>أن نكون المنصة الرائدة في تقديم خدمات الرعاية الصحية المنزلية في المملكة العربية السعودية والشرق الأوسط.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- قسم اتصل بنا -->
    <section id="contact" class="py-5 bg-light">
        <div class="container">
            <h2 class="section-title">اتصل بنا</h2>
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="card">
                        <div class="card-body">
                            <form>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">الاسم</label>
                                        <input type="text" class="form-control" id="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">البريد الإلكتروني</label>
                                        <input type="email" class="form-control" id="email" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="message" class="form-label">الرسالة</label>
                                    <textarea class="form-control" id="message" rows="5" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">إرسال الرسالة</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- التذييل -->
    <footer class="py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-heartbeat me-2"></i>رعاية</h5>
                    <p>منصة سعودية متكاملة للرعاية الصحية المنزلية</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>© 2023 رعاية. جميع الحقوق محفوظة.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>