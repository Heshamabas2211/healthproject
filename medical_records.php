<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('auth.php?type=login');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// جلب السجلات الطبية
if (isPatient()) {
    // للمرضى: جلب سجلاتهم الطبية
    $stmt = $pdo->prepare("
        SELECT mr.*, u.full_name as doctor_name, d.specialization 
        FROM medical_records mr 
        JOIN users u ON mr.doctor_id = u.id 
        JOIN doctors d ON mr.doctor_id = d.user_id 
        WHERE mr.patient_id = ? 
        ORDER BY mr.record_date DESC, mr.created_at DESC
    ");
    $stmt->execute([$user_id]);
} else {
    // للأطباء: جلب سجلات مرضاهم
    $stmt = $pdo->prepare("
        SELECT mr.*, u.full_name as patient_name 
        FROM medical_records mr 
        JOIN users u ON mr.patient_id = u.id 
        WHERE mr.doctor_id = ? 
        ORDER BY mr.record_date DESC, mr.created_at DESC
    ");
    $stmt->execute([$user_id]);
}

$medical_records = $stmt->fetchAll();

// إضافة سجل طبي جديد (للأطباء فقط)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_record']) && isDoctor()) {
    $patient_id = $_POST['patient_id'];
    $record_date = $_POST['record_date'];
    $record_type = sanitize($_POST['record_type']);
    $description = sanitize($_POST['description']);
    $diagnosis = sanitize($_POST['diagnosis']);
    $treatment = sanitize($_POST['treatment']);
    
    $stmt = $pdo->prepare("
        INSERT INTO medical_records 
        (patient_id, doctor_id, record_date, record_type, description, diagnosis, treatment) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$patient_id, $user_id, $record_date, $record_type, $description, $diagnosis, $treatment])) {
        $_SESSION['success'] = 'تم إضافة السجل الطبي بنجاح';
    } else {
        $_SESSION['error'] = 'حدث خطأ أثناء إضافة السجل الطبي';
    }
    
    redirect('medical_records.php');
}

// جلب قائمة المرضى (للأطباء)
$patients = [];
if (isDoctor()) {
    $patients_stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.full_name 
        FROM appointments a 
        JOIN users u ON a.patient_id = u.id 
        WHERE a.doctor_id = ?
    ");
    $patients_stmt->execute([$user_id]);
    $patients = $patients_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>السجلات الطبية - رعاية</title>
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
                        <a class="nav-link" href="dashboard.php">لوحة التحكم</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="appointments.php">المواعيد</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="medical_records.php">السجلات الطبية</a>
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
                    <h2>السجلات الطبية</h2>
                    <?php if (isDoctor()): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                            <i class="fas fa-plus me-2"></i>إضافة سجل طبي
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

                <?php if (empty($medical_records)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">لا توجد سجلات طبية</h5>
                            <p class="text-muted"><?= isPatient() ? 'لا توجد سجلات طبية مسجلة لك بعد.' : 'لا توجد سجلات طبية مسجلة لمرضاك بعد.' ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($medical_records as $record): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card medical-record-card h-100">
                                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?= $record['record_type'] ?></h6>
                                        <span class="badge bg-light text-dark"><?= $record['record_date'] ?></span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <strong class="text-primary"><?= isPatient() ? 'الطبيب:' : 'المريض:' ?></strong>
                                            <span><?= isPatient() ? $record['doctor_name'] . ' - ' . $record['specialization'] : $record['patient_name'] ?></span>
                                        </div>
                                        
                                        <?php if (!empty($record['description'])): ?>
                                            <div class="mb-3">
                                                <strong class="text-primary">الوصف:</strong>
                                                <p class="mb-0"><?= nl2br($record['description']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($record['diagnosis'])): ?>
                                            <div class="mb-3">
                                                <strong class="text-primary">التشخيص:</strong>
                                                <p class="mb-0 text-success"><?= nl2br($record['diagnosis']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($record['treatment'])): ?>
                                            <div class="mb-3">
                                                <strong class="text-primary">العلاج:</strong>
                                                <p class="mb-0 text-info"><?= nl2br($record['treatment']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="text-muted small">
                                            <i class="fas fa-clock me-1"></i>
                                            تم التسجيل: <?= date('Y-m-d H:i', strtotime($record['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="btn-group w-100">
                                            <button class="btn btn-outline-primary btn-sm" onclick="printRecord(<?= $record['id'] ?>)">
                                                <i class="fas fa-print me-1"></i>طباعة
                                            </button>
                                            <?php if (isDoctor()): ?>
                                                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editRecordModal<?= $record['id'] ?>">
                                                    <i class="fas fa-edit me-1"></i>تعديل
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal تعديل السجل الطبي -->
                            <?php if (isDoctor()): ?>
                            <div class="modal fade" id="editRecordModal<?= $record['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">تعديل السجل الطبي</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="update_record.php">
                                            <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">نوع السجل</label>
                                                        <input type="text" class="form-control" name="record_type" value="<?= $record['record_type'] ?>" required>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">تاريخ السجل</label>
                                                        <input type="date" class="form-control" name="record_date" value="<?= $record['record_date'] ?>" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">الوصف</label>
                                                    <textarea class="form-control" name="description" rows="3"><?= $record['description'] ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">التشخيص</label>
                                                    <textarea class="form-control" name="diagnosis" rows="3"><?= $record['diagnosis'] ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">العلاج</label>
                                                    <textarea class="form-control" name="treatment" rows="3"><?= $record['treatment'] ?></textarea>
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
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal إضافة سجل طبي جديد -->
    <?php if (isDoctor()): ?>
    <div class="modal fade" id="addRecordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة سجل طبي جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="add_record" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="patient_id" class="form-label">المريض</label>
                                <select class="form-select" id="patient_id" name="patient_id" required>
                                    <option value="">اختر المريض</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?= $patient['id'] ?>"><?= $patient['full_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="record_date" class="form-label">تاريخ السجل</label>
                                <input type="date" class="form-control" id="record_date" name="record_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="record_type" class="form-label">نوع السجل</label>
                            <select class="form-select" id="record_type" name="record_type" required>
                                <option value="">اختر نوع السجل</option>
                                <option value="كشف دوري">كشف دوري</option>
                                <option value="متابعة مرض">متابعة مرض</option>
                                <option value="تحاليل">تحاليل</option>
                                <option value="أشعة">أشعة</option>
                                <option value="عملية جراحية">عملية جراحية</option>
                                <option value="زيارة منزلية">زيارة منزلية</option>
                                <option value="طوارئ">طوارئ</option>
                                <option value="تطعيم">تطعيم</option>
                                <option value="فحص وقائي">فحص وقائي</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">الوصف</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="وصف الحالة والأعراض..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">التشخيص</label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" placeholder="التشخيص الطبي..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="treatment" class="form-label">العلاج</label>
                            <textarea class="form-control" id="treatment" name="treatment" rows="3" placeholder="خطة العلاج والتوصيات..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة السجل</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printRecord(recordId) {
            // في التطبيق الحقيقي، هذا سيفتح نافذة طباعة للسجل المحدد
            alert('سيتم طباعة السجل الطبي رقم ' + recordId);
            // window.open('print_record.php?id=' + recordId, '_blank');
        }
        
        // البحث في السجلات الطبية
        function searchRecords() {
            const searchTerm = document.getElementById('searchRecords').value.toLowerCase();
            const records = document.querySelectorAll('.medical-record-card');
            
            records.forEach(record => {
                const text = record.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    record.style.display = 'block';
                } else {
                    record.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>