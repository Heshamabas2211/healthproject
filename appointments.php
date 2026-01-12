<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('auth.php?type=login');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// حجز موعد جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $notes = sanitize($_POST['notes']);
    
    $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, notes) VALUES (?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$user_id, $doctor_id, $appointment_date, $appointment_time, $notes])) {
        $_SESSION['success'] = 'تم حجز الموعد بنجاح';
    } else {
        $_SESSION['error'] = 'حدث خطأ أثناء حجز الموعد';
    }
    
    redirect('appointments.php');
}

// تحديث حالة الموعد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    $diagnosis = sanitize($_POST['diagnosis'] ?? '');
    $prescription = sanitize($_POST['prescription'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE appointments SET status = ?, diagnosis = ?, prescription = ? WHERE id = ?");
    
    if ($stmt->execute([$status, $diagnosis, $prescription, $appointment_id])) {
        $_SESSION['success'] = 'تم تحديث حالة الموعد بنجاح';
    } else {
        $_SESSION['error'] = 'حدث خطأ أثناء تحديث حالة الموعد';
    }
    
    redirect('appointments.php');
}

// إلغاء موعد
if (isset($_GET['cancel'])) {
    $appointment_id = $_GET['cancel'];
    
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND patient_id = ?");
    
    if ($stmt->execute([$appointment_id, $user_id])) {
        $_SESSION['success'] = 'تم إلغاء الموعد بنجاح';
    } else {
        $_SESSION['error'] = 'حدث خطأ أثناء إلغاء الموعد';
    }
    
    redirect('appointments.php');
}

// جلب المواعيد مع البحث والتصفية
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

if (isPatient()) {
    $sql = "
        SELECT a.*, u.full_name as doctor_name, d.specialization, d.consultation_fee 
        FROM appointments a 
        JOIN users u ON a.doctor_id = u.id 
        JOIN doctors d ON a.doctor_id = d.user_id 
        WHERE a.patient_id = ?
    ";
    $params = [$user_id];
} else {
    $sql = "
        SELECT a.*, u.full_name as patient_name, u.phone as patient_phone 
        FROM appointments a 
        JOIN users u ON a.patient_id = u.id 
        WHERE a.doctor_id = ?
    ";
    $params = [$user_id];
}

if (!empty($status_filter)) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $sql .= " AND a.appointment_date = ?";
    $params[] = $date_filter;
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// جلب الأطباء لحجز موعد جديد
$doctors_stmt = $pdo->query("
    SELECT u.id, u.full_name, d.specialization, d.consultation_fee, d.rating 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE u.user_type = 'doctor'
    ORDER BY d.rating DESC
");
$doctors = $doctors_stmt->fetchAll();

// إحصائيات المواعيد
$stats_stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    WHERE " . (isPatient() ? "patient_id = ?" : "doctor_id = ?") . "
    GROUP BY status
");
$stats_stmt->execute([$user_id]);
$appointment_stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المواعيد - رعاية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .appointment-card {
            transition: all 0.3s ease;
        }
        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-heartbeat me-2"></i>رعاية</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">لوحة التحكم</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="appointments.php">المواعيد</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="medical_records.php">السجلات الطبية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="prescriptions.php">الوصفات الطبية</a>
                    </li>
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
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>إدارة المواعيد</h2>
                    <?php if (isPatient()): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">
                            <i class="fas fa-plus me-2"></i>حجز موعد جديد
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- إحصائيات سريعة -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?= $appointment_stats['confirmed'] ?? 0 ?></h4>
                                <p>مواعيد مؤكدة</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h4><?= $appointment_stats['pending'] ?? 0 ?></h4>
                                <p>قيد الانتظار</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4><?= $appointment_stats['completed'] ?? 0 ?></h4>
                                <p>مكتملة</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h4><?= $appointment_stats['cancelled'] ?? 0 ?></h4>
                                <p>ملغاة</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- فلترة المواعيد -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">حالة الموعد</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">جميع الحالات</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>قيد الانتظار</option>
                                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>مؤكد</option>
                                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>مكتمل</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>ملغى</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="date" class="form-label">التاريخ</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?= $date_filter ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">تصفية</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- قائمة المواعيد -->
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">قائمة المواعيد</h5>
                        <span class="badge bg-light text-dark"><?= count($appointments) ?> موعد</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">لا توجد مواعيد</h5>
                                <p class="text-muted"><?= isPatient() ? 'لا توجد مواعيد مسجلة لك بعد.' : 'لا توجد مواعيد مسجلة لمرضاك بعد.' ?></p>
                                <?php if (isPatient()): ?>
                                    <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">
                                        احجز موعدك الأول
                                    </button>
                                <?php endif; ?>
                            </div>
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
                                                <th>الهاتف</th>
                                            <?php endif; ?>
                                            <th>التاريخ</th>
                                            <th>الوقت</th>
                                            <th>الحالة</th>
                                            <th>ملاحظات</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <?php if (isPatient()): ?>
                                                    <td>
                                                        <strong><?= $appointment['doctor_name'] ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= $appointment['consultation_fee'] ?> ريال</small>
                                                    </td>
                                                    <td><?= $appointment['specialization'] ?></td>
                                                <?php else: ?>
                                                    <td>
                                                        <strong><?= $appointment['patient_name'] ?></strong>
                                                    </td>
                                                    <td><?= $appointment['patient_phone'] ?></td>
                                                <?php endif; ?>
                                                <td>
                                                    <?= $appointment['appointment_date'] ?>
                                                    <?php if ($appointment['appointment_date'] == date('Y-m-d')): ?>
                                                        <span class="badge bg-info status-badge">اليوم</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= substr($appointment['appointment_time'], 0, 5) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $appointment['status'] === 'confirmed' ? 'success' : 
                                                        ($appointment['status'] === 'pending' ? 'warning text-dark' : 
                                                        ($appointment['status'] === 'completed' ? 'info' : 'danger')) 
                                                    ?> status-badge">
                                                        <?= $appointment['status'] === 'pending' ? 'قيد الانتظار' : 
                                                            ($appointment['status'] === 'confirmed' ? 'مؤكد' : 
                                                            ($appointment['status'] === 'completed' ? 'مكتمل' : 'ملغى')) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($appointment['notes'])): ?>
                                                        <button class="btn btn-sm btn-outline-secondary" 
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-placement="top" 
                                                                title="<?= $appointment['notes'] ?>">
                                                            <i class="fas fa-sticky-note"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewAppointmentModal<?= $appointment['id'] ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if (isPatient() && $appointment['status'] === 'pending'): ?>
                                                            <a href="appointments.php?cancel=<?= $appointment['id'] ?>" 
                                                               class="btn btn-outline-danger" 
                                                               onclick="return confirm('هل أنت متأكد من إلغاء هذا الموعد؟')">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if (isDoctor() && in_array($appointment['status'], ['pending', 'confirmed'])): ?>
                                                            <button class="btn btn-outline-success" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#updateStatusModal<?= $appointment['id'] ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Modal عرض تفاصيل الموعد -->
                                            <div class="modal fade" id="viewAppointmentModal<?= $appointment['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">تفاصيل الموعد</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-6">
                                                                    <strong>التاريخ:</strong><br>
                                                                    <?= $appointment['appointment_date'] ?>
                                                                </div>
                                                                <div class="col-6">
                                                                    <strong>الوقت:</strong><br>
                                                                    <?= substr($appointment['appointment_time'], 0, 5) ?>
                                                                </div>
                                                            </div>
                                                            <div class="row mt-3">
                                                                <div class="col-6">
                                                                    <strong>الحالة:</strong><br>
                                                                    <span class="badge bg-<?= 
                                                                        $appointment['status'] === 'confirmed' ? 'success' : 
                                                                        ($appointment['status'] === 'pending' ? 'warning text-dark' : 
                                                                        ($appointment['status'] === 'completed' ? 'info' : 'danger')) 
                                                                    ?>">
                                                                        <?= $appointment['status'] === 'pending' ? 'قيد الانتظار' : 
                                                                            ($appointment['status'] === 'confirmed' ? 'مؤكد' : 
                                                                            ($appointment['status'] === 'completed' ? 'مكتمل' : 'ملغى')) ?>
                                                                    </span>
                                                                </div>
                                                                <div class="col-6">
                                                                    <strong>رقم الموعد:</strong><br>
                                                                    #<?= $appointment['id'] ?>
                                                                </div>
                                                            </div>
                                                            <?php if (!empty($appointment['notes'])): ?>
                                                                <div class="mt-3">
                                                                    <strong>ملاحظات:</strong><br>
                                                                    <?= nl2br($appointment['notes']) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($appointment['diagnosis'])): ?>
                                                                <div class="mt-3">
                                                                    <strong>التشخيص:</strong><br>
                                                                    <?= nl2br($appointment['diagnosis']) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($appointment['prescription'])): ?>
                                                                <div class="mt-3">
                                                                    <strong>الوصفة الطبية:</strong><br>
                                                                    <?= nl2br($appointment['prescription']) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Modal تحديث حالة الموعد (للأطباء) -->
                                            <?php if (isDoctor()): ?>
                                            <div class="modal fade" id="updateStatusModal<?= $appointment['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">تحديث حالة الموعد</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <input type="hidden" name="update_status" value="1">
                                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                            <div class="modal-body">
                                                                <div class="row mb-3">
                                                                    <div class="col-md-6">
                                                                        <label for="status<?= $appointment['id'] ?>" class="form-label">حالة الموعد</label>
                                                                        <select class="form-select" id="status<?= $appointment['id'] ?>" name="status" required>
                                                                            <option value="pending" <?= $appointment['status'] === 'pending' ? 'selected' : '' ?>>قيد الانتظار</option>
                                                                            <option value="confirmed" <?= $appointment['status'] === 'confirmed' ? 'selected' : '' ?>>مؤكد</option>
                                                                            <option value="completed" <?= $appointment['status'] === 'completed' ? 'selected' : '' ?>>مكتمل</option>
                                                                            <option value="cancelled" <?= $appointment['status'] === 'cancelled' ? 'selected' : '' ?>>ملغى</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">المريض</label>
                                                                        <input type="text" class="form-control" value="<?= $appointment['patient_name'] ?>" readonly>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="diagnosis<?= $appointment['id'] ?>" class="form-label">التشخيص</label>
                                                                    <textarea class="form-control" id="diagnosis<?= $appointment['id'] ?>" name="diagnosis" rows="3" placeholder="التشخيص الطبي..."><?= $appointment['diagnosis'] ?></textarea>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="prescription<?= $appointment['id'] ?>" class="form-label">الوصفة الطبية</label>
                                                                    <textarea class="form-control" id="prescription<?= $appointment['id'] ?>" name="prescription" rows="3" placeholder="الوصفة الطبية والتوصيات..."><?= $appointment['prescription'] ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
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

    <!-- Modal حجز موعد جديد -->
    <?php if (isPatient()): ?>
    <div class="modal fade" id="bookAppointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">حجز موعد جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="book_appointment" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="doctor_id" class="form-label">اختر الطبيب</label>
                                <select class="form-select" id="doctor_id" name="doctor_id" required onchange="updateDoctorInfo()">
                                    <option value="">اختر الطبيب</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?= $doctor['id'] ?>" 
                                                data-fee="<?= $doctor['consultation_fee'] ?>" 
                                                data-rating="<?= $doctor['rating'] ?>">
                                            <?= $doctor['full_name'] ?> - <?= $doctor['specialization'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">معلومات الطبيب</label>
                                <div id="doctorInfo" class="p-2 border rounded bg-light">
                                    <small class="text-muted">اختر طبيباً لعرض المعلومات</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="appointment_date" class="form-label">التاريخ</label>
                                <input type="date" class="form-control" id="appointment_date" name="appointment_date" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="appointment_time" class="form-label">الوقت</label>
                                <select class="form-select" id="appointment_time" name="appointment_time" required>
                                    <option value="">اختر الوقت</option>
                                    <option value="08:00">08:00 ص</option>
                                    <option value="09:00">09:00 ص</option>
                                    <option value="10:00">10:00 ص</option>
                                    <option value="11:00">11:00 ص</option>
                                    <option value="12:00">12:00 م</option>
                                    <option value="13:00">01:00 م</option>
                                    <option value="14:00">02:00 م</option>
                                    <option value="15:00">03:00 م</option>
                                    <option value="16:00">04:00 م</option>
                                    <option value="17:00">05:00 م</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات إضافية</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="أي ملاحظات إضافية تريد إضافتها..."></textarea>
                        </div>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>معلومات مهمة:</h6>
                            <ul class="mb-0">
                                <li>سيتم تأكيد الموعد من قبل الطبيب خلال 24 ساعة</li>
                                <li>يرجى التأكد من العنوان الصحيح في ملفك الشخصي</li>
                                <li>يكون الطبيب في منزلك في الوقت المحدد</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حجز الموعد</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateDoctorInfo() {
            const doctorSelect = document.getElementById('doctor_id');
            const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
            const fee = selectedOption.getAttribute('data-fee');
            const rating = selectedOption.getAttribute('data-rating');
            
            if (fee && rating) {
                let stars = '';
                for (let i = 1; i <= 5; i++) {
                    if (i <= Math.floor(rating)) {
                        stars += '<i class="fas fa-star text-warning"></i>';
                    } else if (i === Math.ceil(rating) && rating % 1 !== 0) {
                        stars += '<i class="fas fa-star-half-alt text-warning"></i>';
                    } else {
                        stars += '<i class="far fa-star text-warning"></i>';
                    }
                }
                
                document.getElementById('doctorInfo').innerHTML = `
                    <div class="doctor-details">
                        <div class="mb-1"><strong>سعر الكشف:</strong> ${fee} ريال</div>
                        <div class="mb-1"><strong>التقييم:</strong> ${stars} (${rating})</div>
                        <div><strong>الحالة:</strong> <span class="text-success">متاح للحجوزات</span></div>
                    </div>
                `;
            } else {
                document.getElementById('doctorInfo').innerHTML = '<small class="text-muted">اختر طبيباً لعرض المعلومات</small>';
            }
        }

        // تفعيل أدوات التلميح
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // منع اختيار التواريخ الماضية
        document.getElementById('appointment_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>