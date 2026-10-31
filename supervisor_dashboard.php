<?php
session_start();
require_once '../conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง (Manager หรือ Supervisor)
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['supervisor'])) {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id'];
$user_role = $_SESSION['user_role'];
$user_name_session = $_SESSION['username']; 
$today = date('Y-m-d');

// 1. ดึงชื่อจริงและนามสกุลเพื่อแสดงผล (อิงตามตาราง employees)
$sql_user = "SELECT first_name, last_name, dept_id FROM employees WHERE emp_id = ?";
$current_dept_id = 0;
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
        $current_dept_id = $row_user['dept_id'];
    }
    $stmt_user->close();
}

$stats = ['total_employees' => 0, 'pending_requests' => 0, 'total_leave_types' => 0];

// ------------------- 2. นับจำนวนพนักงาน (กรองตามแผนกของผู้ใช้) -------------------
$where_dept = "";
$display_employees_label = "พนักงานทั้งหมด";

if ($user_role !== 'admin') {
    $where_dept = "WHERE dept_id = $current_dept_id";
    $display_employees_label = "พนักงานในแผนก";
} else {
    $display_employees_label = "พนักงานทั้งหมด (ในระบบ)";
}

$sql_employees = "SELECT COUNT(emp_id) AS total FROM employees $where_dept";
$result_employees = $conn->query($sql_employees);
if ($result_employees) { $stats['total_employees'] = $result_employees->fetch_assoc()['total']; }

// ------------------- 3. นับคำขอรออนุมัติ (นับเฉพาะลูกน้องตรง หรือ พนักงานในแผนก) -------------------
// ถ้าเป็น Supervisor ให้นับเฉพาะคนที่ส่งถึงเขา (approver_id หรือ supervisor_id)
$where_pending = "";
if ($user_role === 'supervisor') {
    $where_pending = "AND E.supervisor_id = $emp_id";
} elseif ($user_role === 'manager') {
    $where_pending = "AND E.dept_id = $current_dept_id";
}

$sql_pending = "SELECT COUNT(LR.request_id) AS total FROM leave_requests LR 
                JOIN employees E ON LR.emp_id = E.emp_id
                WHERE LR.status = 'Pending' $where_pending";
$result_pending = $conn->query($sql_pending);
if ($result_pending) { $stats['pending_requests'] = $result_pending->fetch_assoc()['total']; }

// ------------------- 4. ดึงข้อมูลยอดวันลาคงเหลือของผู้ใช้ปัจจุบัน -------------------
$user_balances = [];
$current_year = date('Y');
$sql_balance = "
    SELECT LT.leave_type_display, (ELB.allowed_days - ELB.used_days) as remaining_days
    FROM employee_leave_balances ELB
    JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
    WHERE ELB.emp_id = $emp_id AND ELB.year = $current_year
    ORDER BY LT.leave_type_id ASC";
$result_balance = $conn->query($sql_balance);
if ($result_balance) {
    while($row = $result_balance->fetch_assoc()) {
        $user_balances[] = $row;
    }
}

// ------------------- 5. ดึงรายการขอลาล่าสุด 5 รายการ (ของผู้ใช้ปัจจุบัน) -------------------
$recent_leaves = [];
$sql_recent = "
    SELECT LR.request_id, LR.status, LT.leave_type_display, LR.start_date, LR.end_date,
            DATEDIFF(LR.end_date, LR.start_date) + 1 AS duration
    FROM leave_requests LR
    JOIN leave_types LT ON LR.leave_type = LT.leave_type_name
    WHERE LR.emp_id = $emp_id
    ORDER BY LR.request_id DESC
    LIMIT 5";
$result_recent = $conn->query($sql_recent);
if ($result_recent) {
    while($row = $result_recent->fetch_assoc()) {
        $recent_leaves[] = $row;
    }
}
$conn->close();

// สรุปสถานะสำหรับแจ้งเตือน
$count_pending = count(array_filter($recent_leaves, fn($leave) => $leave['status'] === 'Pending'));
$count_approved = count(array_filter($recent_leaves, fn($leave) => $leave['status'] === 'Approved'));
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ภาพรวมระบบ | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <style>
        :root {--primary:#004030; --secondary:#66BB6A; --accent:#81C784; --bg:#f5f7fb; --text:#2e2e2e;}
        body {margin:0; font-family:'Prompt',sans-serif; background:var(--bg); display:flex;}
        
        .sidebar {width:250px; background:linear-gradient(180deg, var(--primary), #2e7d32); color:#fff; position:fixed; height:100%; display:flex; flex-direction:column;}
        .sidebar-header {padding:25px; text-align:center; font-weight:600; border-bottom:1px solid rgba(255,255,255,0.2);}
        .menu-item {display:flex; align-items:center; padding:15px 25px; color:rgba(255,255,255,0.85); text-decoration:none; transition:0.2s;}
        .menu-item:hover, .menu-item.active {background:rgba(255,255,255,0.15); color:#fff;}
        .menu-item i {margin-right:12px; width:20px; text-align:center;}
        
        .main-content {flex-grow:1; margin-left:250px; padding:30px;}
        .header {display:flex; justify-content:space-between; align-items:center; background:#fff; padding:15px 25px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.05); margin-bottom:25px;}
        
        .card {background:#fff; border-radius:16px; padding:25px; box-shadow:0 6px 20px rgba(0,0,0,0.05); margin-bottom:25px;}
        .stat-cards {display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px;}
        .stat-card {background:#fff; border-radius:12px; padding:25px; display:flex; align-items:center; gap:20px; box-shadow:0 4px 10px rgba(0,0,0,0.05); transition:0.3s; text-decoration:none; color:inherit;}
        .stat-card:hover {transform:translateY(-5px); background:#f1f8e9;}
        .stat-icon {font-size:2.5em; color:var(--secondary);}
        .stat-details h3 {margin:0; font-size:2em; color:var(--primary);}
        .stat-details p {margin:0; color:#666;}

        .leave-balance-cards {display:flex; gap:15px; flex-wrap:wrap; margin-top:15px;}
        .leave-balance-card {flex:1; min-width:180px; background:linear-gradient(135deg, #2e7d32, var(--primary)); color:#fff; border-radius:12px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1);}
        .leave-balance-card h3 {margin:0 0 5px 0; font-size:0.9em; font-weight:400; opacity:0.9;}
        .leave-balance-card p {margin:0; font-size:1.5em; font-weight:700;}

        .btn {display:inline-block; background:var(--primary); color:#fff; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:500; transition:0.3s;}
        .btn:hover {background:var(--secondary);}
        .btn-green {background:#388e3c;}

        .topbar-notification {display:flex; gap:15px; align-items:center;}
        .alert-item {font-size:0.9em; font-weight:500; color:var(--text); text-decoration:none; display:flex; align-items:center; gap:5px;}
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
       <a href="supervisor_dashboard.php" class="menu-item active"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
        <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
        <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องการลา</a>
        <!-- <a href="manage_users.php" class="menu-item"><i class="fas fa-user"></i> ข้อมูลผู้ใช้</a> -->
        <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
        <a href="logout.php" style="margin-top:auto; background:#388e3c; text-align:center; padding:15px; color:#fff; text-decoration:none;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="topbar-notification">
                <?php if ($count_pending > 0): ?>
                    <a href="leave_history.php" class="alert-item"><i class="fas fa-bell" style="color:#fbc02d;"></i> คุณมีรายการรออนุมัติ: <strong><?= $count_pending ?></strong></a>
                <?php else: ?>
                    <span class="alert-item" style="color:#666;"><i class="fas fa-check-circle" style="color:var(--secondary);"></i> ไม่มีรายการค้างคา</span>
                <?php endif; ?>
            </div>
            <div class="user-profile">
                <span><strong><?= $user_display_name ?></strong> (<?= strtoupper($user_role) ?>)</span>
                <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary); margin-left:10px;"></i>
            </div>
        </div>

        <div class="card">
            <h1>แผงควบคุมหลัก</h1>
            <div class="stat-cards">
                <a href="manage_employees.php" class="stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-details">
                        <h3><?= number_format($stats['total_employees']) ?></h3>
                        <p><?= $display_employees_label ?></p>
                    </div>
                </a>
                <a href="manage_leave.php" class="stat-card">
                    <i class="fas fa-clock stat-icon" style="color:#FFC107;"></i>
                    <div class="stat-details">
                        <h3><?= number_format($stats['pending_requests']) ?></h3>
                        <p>คำขอรออนุมัติ (ลูกน้อง)</p>
                    </div>
                </a>
            </div>
        </div>

        <div class="card">
            <h2><i class="fas fa-umbrella-beach"></i> ยอดวันลาคงเหลือของคุณ (ปี <?= $current_year ?>)</h2>
            <div class="leave-balance-cards">
                <?php if (!empty($user_balances)): foreach ($user_balances as $balance): ?>
                    <div class="leave-balance-card">
                        <h3><?= htmlspecialchars($balance['leave_type_display']) ?></h3>
                        <p><?= number_format($balance['remaining_days'], 1) ?> วัน</p>
                    </div>
                <?php endforeach; else: ?>
                    <p style="color:#666;">ไม่พบข้อมูลสิทธิ์วันลาในปีนี้</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2>รายงานสถิติการลา</h2>
            <canvas id="leaveChart" style="max-height: 300px;"></canvas>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2>จัดการและยื่นใบลา</h2>
                <a href="apply_leave.php" class="btn"><i class="fas fa-plus-circle"></i> ยื่นใบลาส่วนตัว</a>
            </div>
            <p>คุณสามารถจัดการคำร้องขอลาของลูกน้องในความดูแล หรือดูประวัติการลาของคุณเองได้ที่ปุ่มด้านล่าง</p>
            <div style="display:flex; gap:15px; margin-top:10px;">
                <a href="manage_leave.php" class="btn">จัดการการลาลูกน้อง <i class="fas fa-tasks"></i></a>
                <a href="leave_history.php" class="btn btn-green">ประวัติการลาส่วนตัว <i class="fas fa-history"></i></a>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('leaveChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
                datasets: [{
                    label: 'จำนวนวันที่ลาอนุมัติแล้ว (รวมพนักงานทั้งหมด)',
                    data: [2, 5, 3, 8, 4, 6, 2, 5, 7, 3, 4, 5], // ข้อมูลตัวอย่าง (ควรดึงจาก API)
                    backgroundColor: 'rgba(102, 187, 106, 0.6)',
                    borderColor: '#004030',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true } }
            }
        });
    });
    </script>
</body>
</html>