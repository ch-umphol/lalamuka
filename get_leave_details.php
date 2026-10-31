<?php
session_start();
require_once '../conn.php'; 

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager', 'supervisor'])) {
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

if (isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);

    // SQL แก้ปัญหาประเภทการลาไม่ขึ้น โดยใช้ LIKE และดึงชื่อ Supervisor
    $sql = "
        SELECT 
            LR.request_id, LR.start_date, LR.end_date, LR.reason, LR.status, LR.evidence_file,
            E.first_name, E.last_name,
            -- ใช้ COALESCE เพื่อป้องกันค่าว่าง ถ้าหาไม่เจอให้เอาค่าจากตารางหลักมาโชว์
            COALESCE(LT.leave_type_display, LR.leave_type) AS leave_type_display,
            S.first_name AS super_fname, S.last_name AS super_lname,
            DATEDIFF(LR.end_date, LR.start_date) + 1 AS duration_days
        FROM leave_requests LR
        JOIN employees E ON LR.emp_id = E.emp_id
        LEFT JOIN employees S ON E.supervisor_id = S.emp_id
        -- แก้ไขจุดนี้: ใช้ LIKE เพื่อให้ 'Business L' แมตช์กับ 'Business Leave'
        LEFT JOIN leave_types LT ON LT.leave_type_name LIKE CONCAT(LR.leave_type, '%')
        WHERE LR.request_id = ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $evidence_name = $row['evidence_file'];
            $file_type = "none";
            
            if (!empty($evidence_name)) {
                $ext = strtolower(pathinfo($evidence_name, PATHINFO_EXTENSION));
                $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $file_type = in_array($ext, $image_extensions) ? 'image' : 'document';
            }

            $supervisor_full_name = ($row['super_fname']) 
                ? $row['super_fname'] . ' ' . $row['super_lname'] 
                : 'ไม่มีหัวหน้างานสายตรง';

            echo json_encode([
                'first_name' => htmlspecialchars($row['first_name']),
                'last_name' => htmlspecialchars($row['last_name']),
                'supervisor_name' => htmlspecialchars($supervisor_full_name),
                'leave_type_display' => htmlspecialchars($row['leave_type_display']),
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'duration_days' => $row['duration_days'],
                'reason' => htmlspecialchars($row['reason']),
                'evidence_file' => $evidence_name,
                'file_category' => $file_type
            ]);
        }
        $stmt->close();
    }
}
$conn->close();
?>