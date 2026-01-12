<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('auth.php?type=login');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// جلب الوصفات الطبية
if (isPatient()) {
    // للمرضى: جلب وصفاتهم
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as doctor_name, d.specialization, a.appointment_date 
        FROM prescriptions p 
        JOIN users u ON p.doctor_id = u.id 
        JOIN doctors d ON p.doctor_id = d.user_id 
        JOIN appointments a ON p.appointment_id = a.id 
        WHERE p.patient_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
} else {
    // للأطباء: جلب وصفات مرضاهم
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as patient_name, a.appointment_date 
        FROM prescriptions p 
        JOIN users u ON p.patient_id = u.id 
        JOIN appointments a ON p.appointment_id = a.id 
        WHERE p.doctor_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
}

$prescriptions = $stmt->fetchAll();

// إضافة وصفة طبية جديدة (للأطباء فقط)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_prescription']) && isDoctor()) {
    $appointment_id = $_POST['appointment_id'];
    $patient_id = $_POST['patient_id'];
    $medication_name = sanitize($_POST['medication_name']);
    $dosage = sanitize($_POST['dosage']);
    $frequency = sanitize($_POST['frequency']);
    $duration = sanitize($_POST['duration']);
    $instructions = sanitize($_POST['instructions']);
    
    $stmt = $pdo->prepare("
        INSERT INTO prescriptions 
        (appointment_id, patient_id, doctor_id, medication_name, dosage, frequency, duration, instructions) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$appointment_id, $patient_id, $user_id, $medication_name, $dosage, $frequency, $duration, $instructions])) {
        $_SESSION['success'] = 'تم إضافة الوصفة الطبية بنجاح';
    } else {
        $_SESSION['error'] = 'حدث خطأ أثناء إضافة الوصفة الطبية';
    }
    
    redirect('prescriptions.php');
}

// تحديث حالة الوصفة
if (isset($_GET['update_status'])) {
    $prescription_id = $_GET['update_status'];
    $status = $_GET['status'];
    
    $stmt = $pdo->prepare("UPDATE prescriptions SET status = ? WHERE id = ?");
    
    if ($stmt->execute([$status, $prescription_id])) {
        $_SESSION['success'] = 'تم تحديث حالة الوصفة بنجاح';
    } else {
        $_SESSION['error'] = 'حدث خطأ أثناء تحديث حالة الوصفة';
    }
    
    redirect('prescriptions.php');
}

// جلب المواعيد للمرضى (للأطباء)
$appointments = [];
if (isDoctor()) {
    $appointments_stmt = $pdo->prepare("
        SELECT a.id, u.full_name as patient_name, a.appointment_date 
        FROM appointments a 
        JOIN users u ON a.patient_id = u.id 
        WHERE a.doctor_id = ? AND a.status = 'completed'
        ORDER BY a.appointment_date DESC
    ");
    $appointments_stmt->execute([$user_id]);
    $appointments = $appointments_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الوصفات الطبية - رعاية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .prescription-card {
            border-left: 4px solid #28a745;
        }
        .prescription-active {
            border-left-color: #28a745;
        }
        .prescription-completed {
            border-left-color: #6c757d;
        }
        .prescription-cancelled {
            border-left-color: #dc3545;
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
                        <a class="nav-link" href="appointments.php">المواعيد</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="medical_records.php">السجلات الطبية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="prescriptions.php">الوصفات الطبية</a>
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
                    <h2>الوصفات الطبية</h2>
                    <?php if (isDoctor()): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPrescriptionModal">
                            <i class="fas fa-plus me-2"></i>إضافة وصفة طبية
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
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?= count(array_filter($prescriptions, fn($p) => $p['status'] === 'active')) ?></h4>
                                <p>وصفات نشطة</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4><?= count(array_filter($prescriptions, fn($p) => $p['status'] === 'completed')) ?></h4>
                                <p>وصفات مكتملة</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h4><?= count(array_filter($prescriptions, fn($p) => $p['status'] === 'cancelled')) ?></h4>
                                <p>وصفات ملغاة</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4><?= count($prescriptions) ?></h4>
                                <p>إجمالي الوصفات</p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($prescriptions)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-prescription fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">لا توجد وصفات طبية</h5>
                            <p class="text-muted"><?= isPatient() ? 'لا توجد وصفات طبية مسجلة لك بعد.' : 'لا توجد وصفات طبية مسجلة لمرضاك بعد.' ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">قائمة الوصفات الطبية</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>الدواء</th>
                                            <th>الجرعة</th>
                                            <th>التكرار</th>
                                            <th>المدة</th>
                                            <?php if (isPatient()): ?>
                                                <th>الطبيب</th>
                                            <?php else: ?>
                                                <th>المريض</th>
                                            <?php endif; ?>
                                            <th>التاريخ</th>
                                            <th>الحالة</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($prescriptions as $prescription): ?>
                                            <tr class="prescription-<?= $prescription['status'] ?>">
                                                <td>
                                                    <strong><?= $prescription['medication_name'] ?></strong>
                                                    <?php if (!empty($prescription['instructions'])): ?>
                                                        <br><small class="text-muted"><?= $prescription['instructions'] ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $prescription['dosage'] ?></td>
                                                <td><?= $prescription['frequency'] ?></td>
                                                <td><?= $prescription['duration'] ?></td>
                                                <td>
                                                    <?php if (isPatient()): ?>
                                                        <?= $prescription['doctor_name'] ?><br>
                                                        <small class="text-muted"><?= $prescription['specialization'] ?></small>
                                                    <?php else: ?>
                                                        <?= $prescription['patient_name'] ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $prescription['appointment_date'] ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $prescription['status'] === 'active' ? 'success' : 
                                                        ($prescription['status'] === 'completed' ? 'secondary' : 'danger')
                                                    ?>">
                                                        <?= $prescription['status'] === 'active' ? 'نشط' : 
                                                            ($prescription['status'] === 'completed' ? 'مكتمل' : 'ملغى') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="printPrescription(<?= $prescription['id'] ?>)">
                                                            <i class="fas fa-print"></i>
                                                        </button>
                                                        <?php if (isPatient() && $prescription['status'] === 'active'): ?>
                                                            <a href="prescriptions.php?update_status=<?= $prescription['id'] ?>&status=completed" 
                                                               class="btn btn-outline-success" 
                                                               onclick="return confirm('هل اكتملت هذه الوصفة؟')">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if (isDoctor()): ?>
                                                            <a href="prescriptions.php?update_status=<?= $prescription['id'] ?>&status=cancelled" 
                                                               class="btn btn-outline-danger" 
                                                               onclick="return confirm('هل تريد إلغاء هذه الوصفة؟')">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- عرض الوصفات بشكل بطاقات للمرضى -->
                    <?php if (isPatient()): ?>
                    <div class="row mt-4 d-none d-md-flex">
                        <?php foreach ($prescriptions as $prescription): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card prescription-card h-100 prescription-<?= $prescription['status'] ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?= $prescription['medication_name'] ?></h6>
                                        <span class="badge bg-<?= 
                                            $prescription['status'] === 'active' ? 'success' : 
                                            ($prescription['status'] === 'completed' ? 'secondary' : 'danger')
                                        ?>">
                                            <?= $prescription['status'] === 'active' ? 'نشط' : 
                                                ($prescription['status'] === 'completed' ? 'مكتمل' : 'ملغى') ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <strong>الجرعة:</strong><br>
                                                <?= $prescription['dosage'] ?>
                                            </div>
                                            <div class="col-6">
                                                <strong>التكرار:</strong><br>
                                                <?= $prescription['frequency'] ?>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <strong>المدة:</strong><br>
                                                <?= $prescription['duration'] ?>
                                            </div>
                                            <div class="col-6">
                                                <strong>الطبيب:</strong><br>
                                                <?= $prescription['doctor_name'] ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($prescription['instructions'])): ?>
                                            <div class="mb-3">
                                                <strong>التعليمات:</strong><br>
                                                <?= nl2br($prescription['instructions']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-muted small">
                                            <i class="fas fa-calendar me-1"></i>
                                            تاريخ الوصفة: <?= $prescription['appointment_date'] ?>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="btn-group w-100">
                                            <button class="btn btn-outline-primary btn-sm" onclick="printPrescription(<?= $prescription['id'] ?>)">
                                                <i class="fas fa-print me-1"></i>طباعة
                                            </button>
                                            <?php if ($prescription['status'] === 'active'): ?>
                                                <a href="prescriptions.php?update_status=<?= $prescription['id'] ?>&status=completed" 
                                                   class="btn btn-outline-success btn-sm" 
                                                   onclick="return confirm('هل اكتملت هذه الوصفة؟')">
                                                    <i class="fas fa-check me-1"></i>إكمال
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal إضافة وصفة طبية جديدة -->
    <?php if (isDoctor()): ?>
    <div class="modal fade" id="addPrescriptionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة وصفة طبية جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="add_prescription" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="appointment_id" class="form-label">الموعد</label>
                                <select class="form-select" id="appointment_id" name="appointment_id" required onchange="updatePatientInfo()">
                                    <option value="">اختر الموعد</option>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <option value="<?= $appointment['id'] ?>" data-patient-id="<?= $appointment['patient_id'] ?? '' ?>">
                                            <?= $appointment['patient_name'] ?> - <?= $appointment['appointment_date'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="patient_id" class="form-label">المريض</label>
                                <input type="text" class="form-control" id="patient_id_display" readonly>
                                <input type="hidden" id="patient_id" name="patient_id">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="medication_name" class="form-label">اسم الدواء</label>
                                <input type="text" class="form-control" id="medication_name" name="medication_name" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="dosage" class="form-label">الجرعة</label>
                                <input type="text" class="form-control" id="dosage" name="dosage" placeholder="مثال: 500mg" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="frequency" class="form-label">التكرار</label>
                                <select class="form-select" id="frequency" name="frequency" required>
                                    <option value="">اختر التكرار</option>
                                    <option value="مرة يومياً">مرة يومياً</option>
                                    <option value="مرتين يومياً">مرتين يومياً</option>
                                    <option value="ثلاث مرات يومياً">ثلاث مرات يومياً</option>
                                    <option value="أربع مرات يومياً">أربع مرات يومياً</option>
                                    <option value="كل 6 ساعات">كل 6 ساعات</option>
                                    <option value="كل 8 ساعات">كل 8 ساعات</option>
                                    <option value="كل 12 ساعة">كل 12 ساعة</option>
                                    <option value="حسب الحاجة">حسب الحاجة</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="duration" class="form-label">المدة</label>
                                <input type="text" class="form-control" id="duration" name="duration" placeholder="مثال: 7 أيام" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="status" class="form-label">الحالة</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" selected>نشط</option>
                                    <option value="completed">مكتمل</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="instructions" class="form-label">تعليمات الاستخدام</label>
                            <textarea class="form-control" id="instructions" name="instructions" rows="3" placeholder="تعليمات خاصة باستخدام الدواء..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة الوصفة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printPrescription(prescriptionId) {
            // في التطبيق الحقيقي، هذا سيفتح نافذة طباعة للوصفة المحددة
            alert('سيتم طباعة الوصفة الطبية رقم ' + prescriptionId);
            // window.open('print_prescription.php?id=' + prescriptionId, '_blank');
        }
        
        function updatePatientInfo() {
            const appointmentSelect = document.getElementById('appointment_id');
            const selectedOption = appointmentSelect.options[appointmentSelect.selectedIndex];
            const patientId = selectedOption.getAttribute('data-patient-id');
            const patientName = selectedOption.textContent.split(' - ')[0];
            
            document.getElementById('patient_id_display').value = patientName;
            document.getElementById('patient_id').value = patientId;
        }
        
        // البحث في الوصفات الطبية
        function searchPrescriptions() {
            const searchTerm = document.getElementById('searchPrescriptions').value.toLowerCase();
            const prescriptions = document.querySelectorAll('.prescription-card, .prescription-active, .prescription-completed, .prescription-cancelled');
            
            prescriptions.forEach(prescription => {
                const text = prescription.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    prescription.style.display = 'block';
                } else {
                    prescription.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>