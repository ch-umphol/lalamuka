<?php
session_start();
require_once 'conn.php'; 

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager', 'supervisor'])) {
    header("location: login.php");
    exit;
}

$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$current_user_id = $_SESSION['emp_id']; 

if ($request_id > 0 && in_array($status, ['Approved', 'Rejected'])) {
    
    $conn->begin_transaction();

    try {
        // 1. อัปเดตสถานะในตาราง leave_requests
        $sql_update = "UPDATE leave_requests SET status = ?, approver_id = ? WHERE request_id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("sii", $status, $current_user_id, $request_id);
        
        if (!$stmt->execute()) {
            throw new Exception("ไม่สามารถอัปเดตสถานะการลาได้");
        }

        // 2. ถ้าอนุมัติ (Approved) ให้ลดจำนวนวันลาในตาราง employee_leave_balances
        if ($status === 'Approved') {
            // ดึงข้อมูลคำขอลาเพื่อดูว่าเป็นของใคร, ประเภทอะไร และลากี่วัน
            $sql_info = "SELECT emp_id, leave_type, 
                        DATEDIFF(end_date, start_date) + 1 AS days 
                        FROM leave_requests WHERE request_id = ?";
            $stmt_info = $conn->prepare($sql_info);
            $stmt_info->bind_param("i", $request_id);
            $stmt_info->execute();
            $req_data = $stmt_info->get_result()->fetch_assoc();

            if ($req_data) {
                $target_emp_id = $req_data['emp_id'];
                $leave_type_name = $req_data['leave_type'];
                $leave_days = $req_data['days'];
                $current_year = date('Y');

                // อัปเดต used_days ในตาราง employee_leave_balances 
                // โดยค้นหาจากชื่อประเภทการลา (LIKE) เพื่อให้แมตช์กับ Business L / Business Leave
                $sql_deduct = "UPDATE employee_leave_balances ELB
                               JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
                               SET ELB.used_days = ELB.used_days + ?
                               WHERE ELB.emp_id = ? 
                               AND ELB.year = ? 
                               AND LT.leave_type_name LIKE CONCAT(?, '%')";
                
                $stmt_deduct = $conn->prepare($sql_deduct);
                $stmt_deduct->bind_param("diis", $leave_days, $target_emp_id, $current_year, $leave_type_name);
                
                if (!$stmt_deduct->execute()) {
                    throw new Exception("ไม่สามารถหักยอดวันลาคงเหลือได้");
                }
            }
        }

        $conn->commit();
        $_SESSION['status_message'] = "ดำเนินการเรียบร้อยแล้ว: รายการที่ #$request_id ถูก $status";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
} else {
    $_SESSION['status_message'] = "ข้อมูลไม่ถูกต้อง";
}

header("location: manage_leave.php");
$conn->close();
exit;
?>