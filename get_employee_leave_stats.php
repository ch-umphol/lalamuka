<?php
session_start();
require_once '../conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง (Supervisor เท่านั้น)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'supervisor' || !isset($_GET['emp_id'])) {
    exit(json_encode(['error' => 'Unauthorized']));
}

$target_emp_id = intval($_GET['emp_id']);
$current_year = date('Y');

// 1. ดึงสิทธิ์วันลาคงเหลือจากตารางจริง
$balances = [];
$sql_bal = "SELECT LT.leave_type_display, (ELB.allowed_days - ELB.used_days) as remaining, LT.leave_unit
            FROM employee_leave_balances ELB
            JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
            WHERE ELB.emp_id = ? AND ELB.year = ?";
$stmt_bal = $conn->prepare($sql_bal);
$stmt_bal->bind_param("ii", $target_emp_id, $current_year);
$stmt_bal->execute();
$res_bal = $stmt_bal->get_result();
while($row = $res_bal->fetch_assoc()) { $balances[] = $row; }

// 2. ดึงประวัติการลาล่าสุด 10 รายการ
$history = [];
$sql_hist = "SELECT COALESCE(LT.leave_type_display, LR.leave_type) as leave_display, 
             start_date, end_date, status, DATEDIFF(end_date, start_date) + 1 as duration
             FROM leave_requests LR
             LEFT JOIN leave_types LT ON LT.leave_type_name LIKE CONCAT(LR.leave_type, '%')
             WHERE LR.emp_id = ? ORDER BY start_date DESC LIMIT 10";
$stmt_hist = $conn->prepare($sql_hist);
$stmt_hist->bind_param("i", $target_emp_id);
$stmt_hist->execute();
$res_hist = $stmt_hist->get_result();
while($row = $res_hist->fetch_assoc()) { $history[] = $row; }

echo json_encode(['balances' => $balances, 'history' => $history]);