<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง (เฉพาะพนักงาน role_id = 4)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'employee') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$current_year = date('Y');
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// 2. ดึงข้อมูลส่วนตัวพนักงาน
$sql_user = "SELECT emp_code, first_name, last_name FROM employees WHERE emp_id = ?";
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $emp_code = htmlspecialchars($row_user['emp_code']);
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
    }
    $stmt_user->close();
}

// 3. LOGIC: CANCEL (ยกเลิกคำร้องที่ยังรอการอนุมัติ)
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['request_id'])) {
    $cancel_id = (int)$_GET['request_id'];
    // พนักงานจะยกเลิกได้เฉพาะรายการของตัวเองที่ยัง Pending เท่านั้น
    $stmt_cancel = $conn->prepare("UPDATE leave_requests SET status = 'Cancelled' WHERE request_id = ? AND emp_id = ? AND status = 'Pending'");
    $stmt_cancel->bind_param("ii", $cancel_id, $emp_id);
    if ($stmt_cancel->execute() && $stmt_cancel->affected_rows > 0) {
        $_SESSION['status_message'] = "✅ ยกเลิกคำร้องสำเร็จเรียบร้อยแล้ว";
    } else {
        $_SESSION['status_message'] = "❌ ไม่สามารถยกเลิกได้ เนื่องจากรายการอาจได้รับการอนุมัติไปแล้ว";
    }
    header("location: leave_history.php");
    exit;
}

// 4. ดึงสิทธิ์วันลาคงเหลือ (จากตาราง employee_leave_balances)
$balances = [];
$sql_balance = "
    SELECT LT.leave_type_display, ELB.allowed_days, (ELB.allowed_days - ELB.used_days) as remaining
    FROM employee_leave_balances ELB
    JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
    WHERE ELB.emp_id = ? AND ELB.year = ?
    ORDER BY LT.leave_type_id ASC";
$stmt_bal = $conn->prepare($sql_balance);
$stmt_bal->bind_param("ii", $emp_id, $current_year);
$stmt_bal->execute();
$bal_res = $stmt_bal->get_result();
while($row = $bal_res->fetch_assoc()) { $balances[] = $row; }

// 5. ดึงประวัติการลา (Join leave_types เพื่อชื่อไทย และ Join employees เพื่อชื่อผู้อนุมัติ)
$sql_history = "
    SELECT 
        LR.request_id,
        COALESCE(LT.leave_type_display, LR.leave_type) as leave_display,
        LR.start_date, LR.end_date, LR.status, LR.reason,
        E.first_name as app_fname, E.last_name as app_lname,
        DATEDIFF(LR.end_date, LR.start_date) + 1 AS duration_days
    FROM leave_requests LR
    LEFT JOIN leave_types LT ON LT.leave_type_name LIKE CONCAT(LR.leave_type, '%')
    LEFT JOIN employees E ON LR.approver_id = E.emp_id
    WHERE LR.emp_id = ?
    ORDER BY LR.request_id DESC";
$stmt_hist = $conn->prepare($sql_history);
$stmt_hist->bind_param("i", $emp_id);
$stmt_hist->execute();
$history_result = $stmt_hist->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการลา | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #182848; --secondary: #4b6cb7; --accent: #81C784; --bg: #f5f7fb; --text: #2e2e2e; }
        body { margin: 0; font-family: 'Prompt', sans-serif; background: var(--bg); display: flex; color: var(--text); }
        
        /* Sidebar Style */
        .sidebar { width: 250px; background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); color: #fff; position: fixed; height: 100vh; display: flex; flex-direction: column; box-shadow: 3px 0 10px rgba(0,0,0,0.15); z-index: 20; }
        .sidebar-header { text-align: center; padding: 25px 15px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.85); text-decoration: none; transition: 0.25s; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.2); color: #fff; font-weight: 600; }
        .menu-item i { margin-right: 12px; width: 20px; text-align: center; }
        
        /* Main Content */
        .main-content { flex-grow: 1; margin-left: 250px; padding: 30px; width: calc(100% - 250px); }
        .top-header { background: #fff; border-radius: 12px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        
        /* Dashboard Card & Table */
        .card { background: #fff; border-radius: 16px; padding: 25px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card h2 { margin-top: 0; color: var(--primary); font-size: 1.4em; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        
        /* Balance Cards */
        .balance-container { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 10px; }
        .balance-card { flex: 1; min-width: 180px; background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 15px; border-top: 4px solid var(--secondary); box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .balance-card h4 { margin: 0; font-size: 0.9em; color: #666; font-weight: 500; }
        .balance-card .days { font-size: 1.6em; font-weight: 700; color: var(--primary); margin: 5px 0; }
        
        /* Status Badges */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
        .status-Pending { background: #fff3e0; color: #ef6c00; }
        .status-Approved { background: #e8f5e9; color: #2e7d32; }
        .status-Rejected { background: #ffebee; color: #c62828; }
        .status-Cancelled { background: #f5f5f5; color: #757575; }

        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .history-table th { background: #f8f9fa; color: var(--primary); font-weight: 600; }
        
        .btn-cancel { color: #dc3545; background: none; border: none; font-size: 1.2em; cursor: pointer; transition: 0.2s; }
        .btn-cancel:hover { transform: scale(1.1); color: #a71d2a; }

        @media (max-width: 768px) {
            .sidebar { width: 100%; height: 65px; bottom: 0; top: auto; flex-direction: row; justify-content: space-around; }
            .sidebar-header, .menu-item span { display: none; }
            .main-content { margin-left: 0; padding: 15px; padding-bottom: 80px; width: 100%; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการการลา</div>
    <a href="employee_dashboard.php" class="menu-item"><i class="fas fa-home"></i> <span>Dashboard</span></a>
    <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> <span>ยื่นคำร้อง</span></a>
    <a href="leave_history.php" class="menu-item active"><i class="fas fa-list"></i> <span>ประวัติลา</span></a>
    <a href="logout.php" class="menu-item" style="margin-top:auto;"><i class="fas fa-sign-out-alt"></i> <span>ออกจากระบบ</span></a>
</div>

<div class="main-content">
    <div class="top-header">
        <h3 style="margin:0;"><i class="fas fa-history"></i> ประวัติการลางาน</h3>
        <div style="display:flex; align-items:center; gap:10px;">
            <span><strong><?= $emp_code ?></strong>: <?= $user_display_name ?></span>
            <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary);"></i>
        </div>
    </div>

    <div class="card">
        <h2><i class="fas fa-chart-pie"></i> สรุปสิทธิ์วันลาคงเหลือประจำปี <?= $current_year ?></h2>
        <div class="balance-container">
            <?php foreach($balances as $bal): ?>
            <div class="balance-card">
                <h4><?= htmlspecialchars($bal['leave_type_display']) ?></h4>
                <div class="days"><?= number_format($bal['remaining'], 1) ?> วัน</div>
                <div style="font-size:0.75em; color:#999;">จากสิทธิ์ทั้งหมด <?= $bal['allowed_days'] ?> วัน</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2><i class="fas fa-list-ul"></i> รายการคำขอลาทั้งหมด</h2>
        <?php if ($status_message): ?>
            <div style="padding:12px; border-radius:8px; margin-bottom:20px; font-size:0.9em; background:<?= (strpos($status_message, '✅') !== false ? '#e8f5e9' : '#fbe9e7') ?>;">
                <?= $status_message ?>
            </div>
        <?php endif; ?>
        
        <div style="overflow-x: auto;">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>ประเภทการลา</th>
                        <th>ช่วงวันที่ลา</th>
                        <th>จำนวน</th>
                        <th>สถานะ</th>
                        <th>ผู้อนุมัติ</th>
                        <th>ยกเลิก</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while($row = $history_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($row['leave_display']) ?></strong></td>
                        <td style="font-size: 0.9em;"><?= date('d/m/Y', strtotime($row['start_date'])) ?> - <?= date('d/m/Y', strtotime($row['end_date'])) ?></td>
                        <td><?= $row['duration_days'] ?> วัน</td>
                        <td><span class="badge status-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
                        <td><?= $row['app_fname'] ? htmlspecialchars($row['app_fname']." ".$row['app_lname']) : '<span style="color:#ccc;">-</span>' ?></td>
                        <td style="text-align:center;">
                            <?php if($row['status'] === 'Pending'): ?>
                                <a href="?action=cancel&request_id=<?= $row['request_id'] ?>" class="btn-cancel" onclick="return confirm('ยืนยันการยกเลิกคำร้องที่รอการอนุมัตินี้?')" title="ยกเลิกรายการ">
                                    <i class="fas fa-times-circle"></i>
                                </a>
                            <?php else: ?>
                                <span style="color:#eee;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($i === 1): ?>
                        <tr><td colspan="7" style="text-align:center; padding:40px; color:#999;">ไม่พบประวัติการยื่นคำขอลาในระบบ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>