<?php
session_start();
require_once '../conn.php'; 

// ------------------- PHP LOGIC (Updated to match hotel.sql) -------------------

// ตรวจสอบสิทธิ์การเข้าถึง (บทบาทพนักงาน role_id = 4)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'employee') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; // อ้างอิง emp_id ตามโครงสร้างใหม่
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

$current_year = date('Y');
$current_month = date('m'); 

// 1. ดึงชื่อจริง นามสกุล และข้อมูลพื้นฐาน (ตาราง employees)
$sql_user = "SELECT emp_code, first_name, last_name FROM employees WHERE emp_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $emp_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($row_user = $result_user->fetch_assoc()) {
    $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
    $emp_code = $row_user['emp_code'];
} else {
    $user_display_name = $_SESSION['username'];
}

// 2. ดึงข้อมูลยอดวันลาคงเหลือ (ตาราง employee_leave_balances JOIN leave_types)
$balances = [];
$sql_balance = "
    SELECT LT.leave_type_display, 
           (ELB.allowed_days - ELB.used_days) AS remaining_days,
           LT.leave_unit
    FROM employee_leave_balances ELB
    JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
    WHERE ELB.emp_id = ? AND ELB.year = ?
    ORDER BY LT.leave_type_id ASC";
$stmt_bal = $conn->prepare($sql_balance);
$stmt_bal->bind_param("ii", $emp_id, $current_year);
$stmt_bal->execute();
$result_balance = $stmt_bal->get_result();
while($row = $result_balance->fetch_assoc()) {
    $balances[] = $row;
}

// 3. ดึงรายการคำขอลาเพื่อคำนวณสรุปสถานะแจ้งเตือน (Logic จากโค้ดแรก)
$sql_notif = "SELECT status, COUNT(*) as cnt FROM leave_requests WHERE emp_id = ? AND status IN ('Pending', 'Rejected') GROUP BY status";
$stmt_notif = $conn->prepare($sql_notif);
$stmt_notif->bind_param("i", $emp_id);
$stmt_notif->execute();
$res_notif = $stmt_notif->get_result();

$count_pending = 0;
$count_rejected = 0;
while($row_n = $res_notif->fetch_assoc()) {
    if($row_n['status'] == 'Pending') $count_pending = $row_n['cnt'];
    if($row_n['status'] == 'Rejected') $count_rejected = $row_n['cnt'];
}
$total_urgent = $count_pending + $count_rejected;

// 4. ดึงรายการวันหยุดพิเศษ (ตาราง public_holidays)
$holidays = [];
$sql_holidays = "SELECT holiday_date, holiday_name FROM public_holidays WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ? ORDER BY holiday_date ASC";
$stmt_hol = $conn->prepare($sql_holidays);
$stmt_hol->bind_param("ii", $current_year, $current_month);
$stmt_hol->execute();
$result_holidays = $stmt_hol->get_result();
while($row = $result_holidays->fetch_assoc()) { $holidays[] = $row; }

$thai_months = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard พนักงาน | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #182848; --secondary: #4b6cb7; --bg: #f5f7fb; --text: #2e2e2e; }
        body { margin: 0; font-family: 'Prompt', sans-serif; background: var(--bg); display: flex; color: var(--text); }
        .sidebar { width: 250px; background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); color: #fff; position: fixed; height: 100vh; display: flex; flex-direction: column; z-index: 20; }
        .sidebar-header { text-align: center; padding: 25px 15px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.85); text-decoration: none; transition: 0.25s; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.2); color: #fff; font-weight: 600; }
        .menu-item i { margin-right: 12px; width: 20px; text-align: center; }
        .main-content { flex-grow: 1; margin-left: 250px; padding: 30px; width: calc(100% - 250px); }
        
        /* Topbar & Notification */
        .topbar { background: #fff; border-radius: 12px; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .notif-badge-item { display: inline-flex; align-items: center; margin-right: 20px; font-size: 0.85em; font-weight: 500; text-decoration: none; color: var(--text); background: #f8f9fa; padding: 5px 12px; border-radius: 8px; transition: 0.2s; }
        .notif-badge-item:hover { background: #f0f4ff; }
        .notif-badge-item i { margin-right: 6px; }
        .count-tag { background: #ff5252; color: #fff; padding: 1px 7px; border-radius: 10px; font-size: 0.8em; margin-left: 5px; }
        .count-tag.wait { background: #ffa000; }

        .card { background: #fff; border-radius: 16px; padding: 25px; margin-bottom: 25px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .stat-card { background: linear-gradient(135deg, #4b6cb7, #182848); color: white; border-radius: 12px; padding: 20px; }
        .stat-card p { font-size: 1.8em; margin: 0; font-weight: 700; }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: 60px; bottom: 0; top: auto; flex-direction: row; }
            .sidebar-header, .menu-item span { display: none; }
            .main-content { margin-left: 0; padding: 15px; width: 100%; padding-bottom: 80px; }
            .topbar { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการการลา</div>
    <a href="employee_dashboard.php" class="menu-item active"><i class="fas fa-home"></i><span>Dashboard</span></a>
    <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i><span>ยื่นคำร้อง</span></a>
    <a href="leave_history.php" class="menu-item"><i class="fas fa-list"></i><span>ประวัติลา</span></a>
    <a href="../logout.php" class="menu-item" style="margin-top:auto;"><i class="fas fa-sign-out-alt"></i><span> ออกจากระบบ</span></a>
</div>

<div class="main-content">
    <div class="topbar">
        <div style="display: flex; align-items: center;">
            <?php if ($count_pending > 0): ?>
                <a href="leave_history.php" class="notif-badge-item">
                    <i class="fas fa-hourglass-half" style="color: #ffa000;"></i> รออนุมัติ <span class="count-tag wait"><?php echo $count_pending; ?></span>
                </a>
            <?php endif; ?>
            <?php if ($count_rejected > 0): ?>
                <a href="leave_history.php" class="notif-badge-item">
                    <i class="fas fa-exclamation-circle" style="color: #ff5252;"></i> ถูกปฏิเสธ <span class="count-tag"><?php echo $count_rejected; ?></span>
                </a>
            <?php endif; ?>
            <?php if ($total_urgent == 0): ?>
                <span style="font-size: 0.85em; color: #999; margin-left: 10px;"><i class="fas fa-check-circle" style="color: #81C784;"></i> ไม่มีรายการค้าง</span>
            <?php endif; ?>
        </div>

        <div class="topbar-actions">
            <span><strong><?php echo $user_display_name; ?></strong> (<?php echo $emp_code; ?>)</span>
            <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary); margin-left:10px; vertical-align: middle;"></i>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0; color:var(--primary); font-size:1.4em;"><i class="fas fa-chart-pie"></i> สรุปวันลาคงเหลือประจำปี <?php echo $current_year; ?></h2>
        <div class="stat-cards">
            <?php foreach ($balances as $balance): ?>
                <div class="stat-card">
                    <h3 style="margin:0; font-weight:400; font-size:0.9em; opacity:0.8;"><?php echo htmlspecialchars($balance['leave_type_display']); ?></h3>
                    <p><?php echo number_format($balance['remaining_days'], 1); ?> <span style="font-size: 0.5em; font-weight: 400;"><?php echo ($balance['leave_unit'] == 'day' ? 'วัน' : 'ชม.'); ?></span></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 25px;">
        <div class="card">
            <h2 style="margin-top:0;"><i class="fas fa-history"></i> รายการลาล่าสุด</h2>
            <p style="color:#666; text-align:center; padding:20px;">
                ตรวจสอบสถานะคำขอลาได้ที่เมนู <a href="leave_history.php" style="color:var(--secondary); font-weight:600;">ประวัติการลา</a>
            </p>
        </div>

        <div class="card">
            <h2 style="margin-top:0;"><i class="fas fa-calendar-alt"></i> วันหยุดพิเศษ (<?php echo $thai_months[(int)$current_month]; ?>)</h2>
            <?php if (!empty($holidays)): ?>
                <?php foreach ($holidays as $h): ?>
                    <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eee; font-size:0.9em;">
                        <span style="color:var(--secondary); font-weight:600;"><?php echo date('d', strtotime($h['holiday_date'])); ?></span>
                        <span><?php echo htmlspecialchars($h['holiday_name']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #999; margin-top: 15px;">ไม่มีวันหยุดพิเศษในเดือนนี้</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>