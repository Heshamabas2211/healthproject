<?php
require_once 'config.php';

if (!isLoggedIn() || !isDoctor()) {
    redirect('auth.php?type=login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_id = $_POST['record_id'];
    $record_type = sanitize($_POST['record_type']);
    $record_date = $_POST['record_date'];
    $description = sanitize($_POST['description']);
    $diagnosis = sanitize($_POST['diagnosis']);
    $treatment = sanitize($_POST['treatment']);
    
    // التحقق من أن السجل يخص الطبيب
    $check_stmt = $pdo->prepare("SELECT id FROM medical_records WHERE id = ? AND doctor_id = ?");
    $check_stmt->execute([$record_id, $_SESSION['user_id']]);
    
    if ($check_stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            UPDATE medical_records 
            SET record_type = ?, record_date = ?, description = ?, diagnosis = ?, treatment = ? 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$record_type, $record_date, $description, $diagnosis, $treatment, $record_id])) {
            $_SESSION['success'] = 'تم تحديث السجل الطبي بنجاح';
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء تحديث السجل الطبي';
        }
    } else {
        $_SESSION['error'] = 'غير مصرح بتعديل هذا السجل';
    }
}

redirect('medical_records.php');
?>