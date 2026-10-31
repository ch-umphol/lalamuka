<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง (Supervisor - role_id 3)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'supervisor') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$error_message = '';
$success_message = '';

// --- ส่วนการคำนวณวันที่ยื่นลาล่วงหน้า 2 วัน ---
$min_leave_date = date('Y-m-d', strtotime('+2 days')); 
// ------------------------------------------

// ดึงข้อมูลส่วนตัวและหา Manager ของแผนก (ผู้อนุมัติ)
$sql_user = "SELECT E.emp_code, E.first_name, E.last_name, E.dept_id, D.manager_id 
             FROM employees E 
             LEFT JOIN departments D ON E.dept_id = D.dept_id 
             WHERE E.emp_id = ?";

if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $emp_code = $row_user['emp_code'];
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
        $approver_id = $row_user['manager_id']; 
    }
    $stmt_user->close();
}

// 2. ดึงรายการประเภทการลาตามยอดคงเหลือ (ปีปัจจุบัน)
$current_year = date('Y');
$sql_balances = "
    SELECT LT.leave_type_id, LT.leave_type_display, LT.leave_unit,
           (ELB.allowed_days - ELB.used_days) as remaining
    FROM employee_leave_balances ELB
    JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
    WHERE ELB.emp_id = ? AND ELB.year = ?
    ORDER BY LT.leave_type_id ASC";

$stmt_lb = $conn->prepare($sql_balances);
$stmt_lb->bind_param("ii", $emp_id, $current_year);
$stmt_lb->execute();
$balances_res = $stmt_lb->get_result();

// 3. ประมวลผลการยื่นคำร้อง (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $leave_type_id = filter_input(INPUT_POST, 'leave_type_id', FILTER_VALIDATE_INT);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    $evidence_file = null;

    if ($start_date < $min_leave_date) {
        $error_message = "❌ ผิดเงื่อนไข: ท่านต้องยื่นขอลาล่วงหน้าอย่างน้อย 2 วัน";
    } elseif (!$leave_type_id || !$start_date || !$end_date || empty($reason)) {
        $error_message = "❌ กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน";
    } else {
        // ดึงชื่อประเภทการลา
        $stmt_t = $conn->prepare("SELECT leave_type_name FROM leave_types WHERE leave_type_id = ?");
        $stmt_t->bind_param("i", $leave_type_id);
        $stmt_t->execute();
        $lt_name = $stmt_t->get_result()->fetch_assoc()['leave_type_name'];

        // จัดการไฟล์แนบ
        if (!empty($_FILES['evidence_file']['name'])) {
            $ext = strtolower(pathinfo($_FILES["evidence_file"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                $target_dir = "../uploads/evidence/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $new_name = "ev_" . time() . "_" . $emp_id . "." . $ext;
                if (move_uploaded_file($_FILES["evidence_file"]["tmp_name"], $target_dir . $new_name)) {
                    $evidence_file = $new_name;
                }
            }
        }

        // บันทึกลงฐานข้อมูล
        $sql_ins = "INSERT INTO leave_requests (emp_id, leave_type, start_date, end_date, reason, evidence_file, status, approver_id) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)";
        if ($stmt_ins = $conn->prepare($sql_ins)) {
            $stmt_ins->bind_param("isssssi", $emp_id, $lt_name, $start_date, $end_date, $reason, $evidence_file, $approver_id);
            if ($stmt_ins->execute()) {
                $success_message = "✅ ยื่นคำร้องสำเร็จ! ส่งข้อมูลให้ Manager พิจารณาแล้ว";
            } else {
                $error_message = "❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ยื่นคำร้องการลา | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #004030; --secondary: #66BB6A; --bg: #f5f7fb; --text: #2e2e2e; }
        body { margin: 0; font-family: 'Prompt', sans-serif; background: var(--bg); display: flex; color: var(--text); }
        .sidebar { width: 250px; background: linear-gradient(180deg, var(--primary), #2e7d32); color: #fff; position: fixed; height: 100vh; display: flex; flex-direction: column; box-shadow: 3px 0 10px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 25px; text-align: center; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.8); text-decoration: none; transition: 0.3s; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.15); color: #fff; }
        .menu-item i { margin-right: 12px; width: 20px; text-align: center; }
        .main-content { flex-grow: 1; margin-left: 250px; padding: 30px; width: calc(100% - 250px); }
        .header { background: #fff; border-radius: 12px; padding: 15px 25px; display: flex; justify-content: flex-end; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card { background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
        .info-box { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid var(--secondary); display: flex; gap: 25px; font-size: 0.95em; }
        .notice-box { background: #fff8e1; border: 1px solid #ffe082; padding: 12px 15px; border-radius: 10px; margin-bottom: 20px; color: #795548; font-size: 0.9em; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--primary); }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 10px; font-family: 'Prompt'; box-sizing: border-box; }
        .btn-submit { background: var(--primary); color: #fff; border: none; padding: 15px; border-radius: 10px; cursor: pointer; width: 100%; font-size: 16px; font-weight: 600; transition: 0.3s; margin-top: 10px; }
        .btn-submit:hover { background: var(--secondary); transform: translateY(-2px); }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 5px solid #66bb6a; }
        .alert-danger { background: #ffebee; color: #c62828; border-left: 5px solid #ef5350; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
     <a href="supervisor_dashboard.php" class="menu-item "><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
        <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
        <a href="apply_leave.php" class="menu-item active"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องการลา</a>
        <!-- <a href="manage_users.php" class="menu-item"><i class="fas fa-user"></i> ข้อมูลผู้ใช้</a> -->
        <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="logout.php" style="margin-top:auto; background:#388e3c; text-align:center; padding:15px; color:#fff; text-decoration:none;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div style="display:flex; align-items:center; gap:10px;">
            <span><strong><?= $user_display_name ?></strong> (SUPERVISOR)</span>
            <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary);"></i>
        </div>
    </div>

    <div class="card">
        <h1 style="margin-top:0; color:var(--primary);"><i class="fas fa-paper-plane"></i> ยื่นคำร้องขอลางาน</h1>

        <div class="info-box">
            <div><strong>รหัสพนักงาน:</strong> <?= $emp_code ?></div>
            <div><strong>ชื่อ-นามสกุล:</strong> <?= $user_display_name ?></div>
        </div>

        <div class="notice-box">
            <i class="fas fa-info-circle"></i> <strong>เงื่อนไข:</strong> ต้องยื่นลาล่วงหน้าอย่างน้อย <strong>2 วัน</strong> (เริ่มลาได้ตั้งแต่วันที่ <strong><?= date('d/m/Y', strtotime($min_leave_date)) ?></strong> เป็นต้นไป)
        </div>

        <?php if ($success_message): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>ประเภทการลา</label>
                <select name="leave_type_id" class="form-control" required>
                    <option value="">-- เลือกประเภทการลา --</option>
                    <?php while($row = $balances_res->fetch_assoc()): ?>
                        <option value="<?= $row['leave_type_id'] ?>">
                            <?= htmlspecialchars($row['leave_type_display']) ?> (คงเหลือ: <?= number_format($row['remaining'], 1) ?> <?= ($row['leave_unit'] == 'day' ? 'วัน' : 'ชม.') ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div class="form-group">
                    <label>วันที่เริ่มลา</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" min="<?= $min_leave_date ?>" required>
                </div>
                <div class="form-group">
                    <label>วันที่สิ้นสุด</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label>เหตุผลการลา</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="ระบุเหตุผลความจำเป็น..." required></textarea>
            </div>

            <div class="form-group">
                <label>แนบหลักฐาน (JPG, PNG, PDF)</label>
                <input type="file" name="evidence_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
            </div>

            <button type="submit" class="btn-submit"><i class="fas fa-share-square"></i> ส่งคำร้องขอลา</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('start_date').addEventListener('change', function() {
        const endDate = document.getElementById('end_date');
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) endDate.value = this.value;
    });
</script>

</body>
</html>