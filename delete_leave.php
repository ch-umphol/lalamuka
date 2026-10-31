<?php
session_start();
require_once 'conn.php'; 

// ตรวจสอบการเข้าสู่ระบบและบทบาท
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'พนักงาน') {
    header("location: login.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($request_id > 0) {
    $conn->begin_transaction();
    try {
        // 1. ดึงข้อมูลและตรวจสอบสิทธิ์/สถานะ (Pending)
        $sql_select = "
            SELECT evidence_file, status 
            FROM LEAVE_REQUEST 
            WHERE request_id = ? AND employee_id = ?";
        
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("ii", $request_id, $employee_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("ไม่พบคำร้องขอลา หรือคุณไม่มีสิทธิ์ลบ");
        }

        $data = $result->fetch_assoc();
        $evidence_file = $data['evidence_file'];
        $status = $data['status'];
        $stmt_select->close();

        if ($status !== 'Pending') {
            throw new Exception("ไม่สามารถลบคำร้องขอลาได้เนื่องจากถูกดำเนินการไปแล้ว (สถานะ: $status)");
        }
        
        // 2. ลบไฟล์หลักฐาน (ถ้ามี)
        if ($evidence_file) {
            $file_path = "../uploads/evidence/" . $evidence_file; 
            if (file_exists($file_path)) {
                unlink($file_path); // ลบไฟล์
            }
        }
        
        // *** 3. แก้ไข: ลบรายการที่อ้างอิง Foreign Key ก่อน ***

        // ลบข้อมูลการอนุมัติที่เกี่ยวข้อง (ถ้ามี)
        $sql_delete_approval = "DELETE FROM leave_approval WHERE request_id = ?";
        $stmt_delete_approval = $conn->prepare($sql_delete_approval);
        $stmt_delete_approval->bind_param("i", $request_id);
        $stmt_delete_approval->execute();
        $stmt_delete_approval->close();

        // ลบข้อมูลการแจ้งเตือนที่เกี่ยวข้อง (ถ้ามี)
        $sql_delete_notification = "DELETE FROM notification WHERE request_id = ?";
        $stmt_delete_notification = $conn->prepare($sql_delete_notification);
        $stmt_delete_notification->bind_param("i", $request_id);
        $stmt_delete_notification->execute();
        $stmt_delete_notification->close();

        // 4. ลบรายการหลักจาก LEAVE_REQUEST
        $sql_delete = "DELETE FROM LEAVE_REQUEST WHERE request_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $request_id);
        
        if (!$stmt_delete->execute()) {
            throw new Exception("เกิดข้อผิดพลาดในการลบฐานข้อมูล: " . $conn->error);
        }
        $stmt_delete->close();

        $conn->commit();
        $_SESSION['status_message'] = "✅ ลบคำร้องขอลา ID: $request_id สำเร็จแล้ว";

    } catch (Exception $e) {
        $conn->rollback();
        // แสดงข้อความที่ชัดเจนขึ้นสำหรับผู้ใช้
        $_SESSION['status_message'] = "❌ การลบล้มเหลว: " . $e->getMessage();
    }
} else {
    $_SESSION['status_message'] = "❌ คำขอไม่ถูกต้อง";
}

$conn->close();
header("location: leave_history.php"); // นำกลับไปหน้าประวัติการลา
exit;
?>