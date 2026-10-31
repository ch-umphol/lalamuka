<?php
session_start();
require_once 'conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง: ต้องเป็นพนักงานที่ล็อกอินอยู่
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'ผู้จัดการ') {
    header("location: login.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$user_name = $_SESSION['user_name'];
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($request_id === 0) {
    $_SESSION['status_message'] = "❌ ไม่พบคำขอลาที่ระบุ";
    header("location: leave_history.php");
    exit;
}

$conn->begin_transaction();

try {
    // 1. ตรวจสอบและอัปเดตสถานะคำขอลาเป็น 'Cancelled'
    // ต้องมั่นใจว่า:
    // a) คำขอเป็นของพนักงานคนนี้
    // b) สถานะปัจจุบันต้องเป็น 'Pending' เท่านั้น
    $sql_update = "UPDATE LEAVE_REQUEST 
                   SET status = 'Cancelled' 
                   WHERE request_id = $request_id 
                   AND employee_id = $employee_id 
                   AND status = 'Pending'";

    if ($conn->query($sql_update) && $conn->affected_rows > 0) {
        
        // 2. ส่ง Notification ไปยัง Supervisor เพื่อแจ้งยกเลิก
        $supervisor_id_result = $conn->query("SELECT supervisor_id FROM EMPLOYEE WHERE employee_id = $employee_id");
        $supervisor_id = $supervisor_id_result->fetch_assoc()['supervisor_id'];
        
        if ($supervisor_id) {
            $notif_msg = "คำขอลา ID $request_id ของ $user_name ถูกยกเลิกโดยพนักงานแล้ว";
            $sql_notif = "INSERT INTO NOTIFICATION (request_id, recipient_id, message, timestamp) 
                          VALUES ($request_id, $supervisor_id, '$notif_msg', NOW())";
            $conn->query($sql_notif);
        }
        
        $conn->commit();
        $_SESSION['status_message'] = "✅ ยกเลิกคำร้องขอลา ID $request_id สำเร็จแล้ว";
        
    } else {
        // กรณีที่ affected_rows เป็น 0 หมายความว่าไม่พบคำขอที่ตรงตามเงื่อนไข (อาจไม่ใช่ Pending หรือไม่ใช่เจ้าของ)
        throw new Exception("ไม่สามารถยกเลิกคำขอได้ สถานะต้องเป็น 'Pending' และคุณต้องเป็นเจ้าของคำขอนั้น");
    }

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['status_message'] = "❌ ยกเลิกคำร้องล้มเหลว: " . $e->getMessage();
}

$conn->close();
header("location: leave_history.php");
exit;
?>