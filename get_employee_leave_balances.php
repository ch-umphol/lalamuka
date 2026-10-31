<?php
require_once '../conn.php';
header('Content-Type: application/json');

if (isset($_GET['emp_id'])) {
    $emp_id = intval($_GET['emp_id']);
    $year = date('Y');

    $sql = "SELECT LT.leave_type_display, LT.leave_unit, 
            (ELB.allowed_days - ELB.used_days) as remaining
            FROM employee_leave_balances ELB
            JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
            WHERE ELB.emp_id = ? AND ELB.year = ?
            ORDER BY LT.leave_type_id ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $emp_id, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $balances = [];
    while ($row = $result->fetch_assoc()) {
        $balances[] = [
            'leave_type_display' => $row['leave_type_display'],
            'remaining' => number_format($row['remaining'], 1),
            'leave_unit' => $row['leave_unit']
        ];
    }
    echo json_encode($balances);
}
?>