<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('auth.php?type=login');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// جلب الإحصائيات
if (isPatient()) {
    // إحصائيات المريض
    $appointments_count = $pdo->prepare("
        SELECT COUNT(*) as count, status 
        FROM appointments 
        WHERE patient_id = ? 
        GROUP BY status
    ");
    $appointments_count->execute([$user_id]);
    $appointment_stats = $appointments_count->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $upcoming_appointments = $pdo->prepare("
        SELECT a.*, u.full_name as doctor_name, d.specialization 
        FROM appointments a 
        JOIN users u ON a.doctor_id = u.id 
        JOIN doctors d ON a.doctor_id = d.user_id 
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() 
        ORDER BY a.appointment_date, a.appointment_time 
        LIMIT 5
    ");
    $upcoming_appointments->execute([$user_id]);
    $appointments = $upcoming_appointments->fetchAll();
} else {
    // إحصائيات الطبيب
    $appointments_count = $pdo->prepare("
        SELECT COUNT(*) as count, status 
        FROM appointments 
        WHERE doctor_id = ? 
        GROUP BY status
    ");
    $appointments_count->execute([$user_id]);
    $appointment_stats = $appointments_count->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $today_appointments = $pdo->prepare("
        SELECT a.*, u.full_name as patient_name 
        FROM appointments a 
        JOIN users u ON a.patient_id = u.id 
        WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() 
        ORDER BY a.appointment_time
    ");
    $today_appointments->execute([$user_id]);
    $appointments = $today_appointments->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - رعاية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-heartbeat me-2"></i>رعاية</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">لوحة التحكم</a>
                    </li>
					 <li class="nav-item">
                        <a class="nav-link active" href="index.php">الموقع</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="appointments.php">المواعيد</a>
                    </li>
                    <?php if (isPatient()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="medical_records.php">السجلات الطبية</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="prescriptions.php">الوصفات الطبية</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">الملف الشخصي</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">مرحباً، <?= $_SESSION['user_name'] ?></span>
                    <a href="logout.php" class="btn btn-outline-light">تسجيل الخروج</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card dashboard-card mb-4">
                    <div class="card-body text-center">
                        <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 1.5rem;">
                            <?= mb_substr($_SESSION['user_name'], 0, 1) ?>
                        </div>
                        <h5><?= $_SESSION['user_name'] ?></h5>
                        <p class="text-muted"><?= $user_type === 'patient' ? 'مريض' : 'طبيب' ?></p>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm">تعديل الملف</a>
                    </div>
                </div>
                
                <div class="card dashboard-card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">القائمة</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="dashboard.php" class="list-group-item list-group-item-action active">الرئيسية</a>
                        <a href="appointments.php" class="list-group-item list-group-item-action">المواعيد</a>
                        <?php if (isPatient()): ?>
                            <a href="medical_records.php" class="list-group-item list-group-item-action">السجلات الطبية</a>
                            <a href="prescriptions.php" class="list-group-item list-group-item-action">الوصفات الطبية</a>
                        <?php endif; ?>
                        <a href="profile.php" class="list-group-item list-group-item-action">الملف الشخصي</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <h2 class="mb-4">لوحة التحكم</h2>
                
                <div class="row">
                    <!-- بطاقات الإحصائيات -->
                    <div class="col-md-4 mb-4">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?= $appointment_stats['confirmed'] ?? 0 ?></h4>
                                        <p>مواعيد مؤكدة</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?= $appointment_stats['pending'] ?? 0 ?></h4>
                                        <p>مواعيد قيد الانتظار</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?= $appointment_stats['completed'] ?? 0 ?></h4>
                                        <p>مواعيد مكتملة</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- المواعيد القادمة -->
                <div class="card dashboard-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <?= isPatient() ? 'مواعيدي القادمة' : 'مواعيد اليوم' ?>
                        </h5>
                        <a href="appointments.php" class="btn btn-light btn-sm">عرض الكل</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <p class="text-center text-muted">لا توجد مواعيد</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <?php if (isPatient()): ?>
                                                <th>الطبيب</th>
                                                <th>التخصص</th>
                                            <?php else: ?>
                                                <th>المريض</th>
                                            <?php endif; ?>
                                            <th>التاريخ</th>
                                            <th>الوقت</th>
                                            <th>الحالة</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <?php if (isPatient()): ?>
                                                    <td><?= $appointment['doctor_name'] ?></td>
                                                    <td><?= $appointment['specialization'] ?></td>
                                                <?php else: ?>
                                                    <td><?= $appointment['patient_name'] ?></td>
                                                <?php endif; ?>
                                                <td><?= $appointment['appointment_date'] ?></td>
                                                <td><?= $appointment['appointment_time'] ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $appointment['status'] === 'confirmed' ? 'success' : 
                                                        ($appointment['status'] === 'pending' ? 'warning' : 
                                                        ($appointment['status'] === 'completed' ? 'info' : 'danger')) 
                                                    ?>">
                                                        <?= $appointment['status'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="appointments.php?view=<?= $appointment['id'] ?>" class="btn btn-sm btn-outline-primary">عرض</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>