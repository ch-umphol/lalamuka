<?php
session_start();
require_once 'conn.php';
header('Content-Type: application/json');

// ตรวจสอบสิทธิ์ (ต้องล็อกอิน)
if (!isset($_SESSION['loggedin'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized access.']));
}

$period = isset($_GET['period']) ? $_GET['period'] : 'monthly'; 
$response_data = ['labels' => [], 'datasets' => []];

// ---------------------------------------------------------------------
// 1. กำหนดเงื่อนไข SQL
// ---------------------------------------------------------------------
$date_format = "";
$date_range = "DATE_SUB(CURDATE(), INTERVAL 12 MONTH)"; // Default: 12 เดือนล่าสุด

switch ($period) {
    case 'daily':
        $date_format = "%Y-%m-%d";
        $date_range = "DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; // 7 วันล่าสุด
        break;
    case 'weekly':
        $date_format = "%Y-W%u";
        $date_range = "DATE_SUB(CURDATE(), INTERVAL 8 WEEK)"; 
        break;
    case 'yearly':
        $date_format = "%Y";
        $date_range = "DATE_SUB(CURDATE(), INTERVAL 5 YEAR)"; 
        break;
    case 'monthly':
    default:
        $date_format = "%Y-%m";
        // date_range ถูกตั้งค่าเป็น 12 เดือนล่าสุดไว้แล้ว
        break;
}

// ---------------------------------------------------------------------
// 2. Query ข้อมูลสถิติ (รวมประเภทการลา)
// ---------------------------------------------------------------------

$sql_stats = "
    SELECT 
        DATE_FORMAT(LR.start_date, '{$date_format}') AS period_label,
        LT.leave_type_name,
        LT.leave_type_id,
        SUM(DATEDIFF(LR.end_date, LR.start_date) + 1) AS total_days
    FROM 
        LEAVE_REQUEST LR
    JOIN 
        LEAVE_TYPE LT ON LR.leave_type_id = LT.leave_type_id
    WHERE 
        LR.status = 'Approved' 
        AND LR.start_date >= '{$date_range}'
    GROUP BY 
        period_label, LT.leave_type_name, LT.leave_type_id
    ORDER BY 
        period_label ASC";

$result = $conn->query($sql_stats);

// ---------------------------------------------------------------------
// 3. จัดโครงสร้างข้อมูลให้อยู่ในรูปแบบที่ Chart.js ต้องการ (Multi-Dataset)
// ---------------------------------------------------------------------

$stats_by_period = []; // [ '2025-01' => [ 'ลาพักร้อน' => 5, 'ลาป่วย' => 2 ], ... ]
$leave_types = [];     // [ 'ลาพักร้อน', 'ลาป่วย', ... ]
$all_periods = [];     // [ '2025-01', '2025-02', ... ]

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $period_label = $row['period_label'];
        $type_name = $row['leave_type_name'];
        $total_days = (float)$row['total_days'];

        // เก็บประเภทการลาทั้งหมด
        if (!in_array($type_name, $leave_types)) {
            $leave_types[] = $type_name;
        }

        // เก็บช่วงเวลาทั้งหมด
        if (!in_array($period_label, $all_periods)) {
            $all_periods[] = $period_label;
        }

        // จัดเก็บสถิติ
        $stats_by_period[$period_label][$type_name] = $total_days;
    }
}

// 4. สร้าง Datasets
$response_data['labels'] = $all_periods;

foreach ($leave_types as $type_name) {
    $dataset = [
        'label' => $type_name,
        'data' => [],
        // กำหนดสีตามประเภท (เพื่อความสวยงาม ควรมีฟังก์ชันสุ่มสีหรือกำหนดสีตายตัว)
        'backgroundColor' => 'rgba(' . rand(50, 200) . ',' . rand(50, 200) . ',' . rand(50, 200) . ', 0.7)' 
    ];

    foreach ($all_periods as $period_label) {
        // หากไม่มีข้อมูลสำหรับช่วงเวลานี้ ให้ใส่ 0
        $dataset['data'][] = $stats_by_period[$period_label][$type_name] ?? 0;
    }
    
    $response_data['datasets'][] = $dataset;
}


$conn->close();
echo json_encode($response_data);
?>