<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง (Supervisor เท่านั้น)
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['supervisor'])) {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$status_message = $_SESSION['status_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['status_message'], $_SESSION['error_message']);

// 2. ดึงข้อมูลส่วนตัวของ Supervisor เอง
$sql_user = "SELECT E.*, D.dept_name, R.role_display 
             FROM employees E 
             LEFT JOIN departments D ON E.dept_id = D.dept_id 
             LEFT JOIN roles R ON E.role_id = R.role_id
             WHERE E.emp_id = ?";

if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user_data = $result_user->fetch_assoc();
    
    $user_display_name = htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']);
    $current_dept_name = htmlspecialchars($user_data['dept_name']);
    $stmt_user->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการบัญชีส่วนตัว | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#004030; --secondary:#66BB6A; --accent:#81C784; --bg:#f5f7fb; --text:#2e2e2e; }
        body { margin:0; font-family:'Prompt',sans-serif; background:var(--bg); display:flex; color:var(--text); }
        .sidebar { width:250px; background:linear-gradient(180deg,var(--primary),#2e7d32); color:#fff; position:fixed; height:100%; display:flex; flex-direction:column; box-shadow:3px 0 10px rgba(0,0,0,0.15); }
        .sidebar-header { text-align:center; padding:25px 15px; font-weight:600; border-bottom:1px solid rgba(255,255,255,0.2); }
        .menu-item { display:flex; align-items:center; padding:15px 25px; color:rgba(255,255,255,0.85); text-decoration:none; transition:0.25s; }
        .menu-item:hover, .menu-item.active { background:rgba(255,255,255,0.2); color:#fff; font-weight:600; }
        .menu-item i { margin-right:12px; width:20px; text-align:center; }
        .main-content { flex-grow:1; margin-left:250px; padding:30px; }
        .header { background:#fff; border-radius:12px; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 10px rgba(0,0,0,0.05); margin-bottom:25px; }
        
        /* Account Form Styles */
        .card { background:#fff; border-radius:16px; padding:30px; box-shadow:0 6px 20px rgba(0,0,0,0.05); max-width: 600px; margin: 0 auto; }
        .card h1 { margin-top:0; color:var(--primary); font-size:1.5em; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; background: #fff; font-family: inherit; }
        .form-group input[readonly] { background: #f9f9f9; color: #888; cursor: not-allowed; }
        .btn-save { background: var(--primary); color: #fff; border: none; padding: 12px 25px; border-radius: 25px; cursor: pointer; font-weight: 600; width: 100%; font-size: 1em; transition: 0.3s; }
        .btn-save:hover { background: #002d22; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }
        .info-box { background: #f0f7f4; padding: 15px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid var(--secondary); }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
    <a href="supervisor_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องการลา</a>
    <a href="manage_users.php" class="menu-item active"><i class="fas fa-user-cog"></i> บัญชีของฉัน</a>
    <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="logout.php" style="margin-top:auto; padding:15px; background:#388e3c; text-align:center; color:#fff; text-decoration:none;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div style="font-weight:600; color:var(--primary); font-size:1.1em;">
             แผนก: <span style="color:var(--secondary);"><?= $current_dept_name ?></span>
        </div>
        <div class="user-profile">
            <span><strong><?= $user_display_name ?></strong></span>
            <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary); margin-left:10px;"></i>
        </div>
    </div>

    <div class="card">
        <h1><i class="fas fa-user-edit"></i> ตั้งค่าบัญชีผู้ใช้</h1>
        
        <?php if ($status_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $status_message ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error_message ?></div>
        <?php endif; ?>

        <div class="info-box">
            <small style="color: #666;">ข้อมูลพนักงาน:</small><br>
            <strong>รหัส:</strong> <?= htmlspecialchars($user_data['emp_code']) ?> | 
            <strong>ตำแหน่ง:</strong> <?= htmlspecialchars($user_data['position']) ?> | 
            <strong>สิทธิ์:</strong> <?= htmlspecialchars($user_data['role_display']) ?>
        </div>

        <form action="update_my_account.php" method="POST">
            <div class="form-group">
                <label>ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user_data['username']) ?>" required>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">
            <p style="font-weight: 600; color: var(--primary); margin-bottom: 15px;"><i class="fas fa-lock"></i> เปลี่ยนรหัสผ่าน (เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</p>

            <div class="form-group">
                <label>รหัสผ่านใหม่</label>
                <input type="password" name="new_password" placeholder="อย่างน้อย 4 ตัวอักษร">
            </div>

            <div class="form-group">
                <label>ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password">
            </div>

            <div class="form-group" style="margin-top: 30px; background: #fff9e6; padding: 15px; border-radius: 8px;">
                <label style="color: #856404;"><i class="fas fa-shield-alt"></i> ยืนยันรหัสผ่านปัจจุบัน</label>
                <input type="password" name="current_password" required placeholder="ระบุรหัสผ่านปัจจุบันเพื่อบันทึกข้อมูล">
            </div>

            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
            </button>
        </form>
    </div>
</div>

</body>
</html>