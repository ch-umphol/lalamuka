<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../conn.php';

// รับค่าจากฟอร์ม
$emp_id = $_GET['emp_id'];
$type = $_GET['report_type'];
$date_val = $_GET['report_date'];
$dept_id = $_GET['dept_id'];

// 1. จัดการเงื่อนไขวันที่
$where_date = "";
$period_text = "";
if ($type == 'daily') {
    $where_date = "AND lr.start_date = '$date_val'";
    $period_text = "วันที่ " . date('d/m/Y', strtotime($date_val));
} elseif ($type == 'monthly') {
    $where_date = "AND lr.start_date LIKE '$date_val%'";
    $period_text = "ประจำเดือน " . date('m/Y', strtotime($date_val));
} else {
    $where_date = "AND YEAR(lr.start_date) = '$date_val'";
    $period_text = "ประจำปี ค.ศ. $date_val";
}

// 2. Query ข้อมูล (รายคน หรือ ทั้งแผนก)
if ($emp_id === 'all') {
    $sql = "SELECT lr.*, e.first_name, e.last_name, d.dept_name 
            FROM leave_requests lr 
            JOIN employees e ON lr.emp_id = e.emp_id 
            JOIN departments d ON e.dept_id = d.dept_id
            WHERE e.dept_id = ? $where_date AND lr.status = 'Approved' 
            ORDER BY lr.start_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $dept_id);
} else {
    $sql = "SELECT lr.*, e.first_name, e.last_name, d.dept_name 
            FROM leave_requests lr 
            JOIN employees e ON lr.emp_id = e.emp_id 
            JOIN departments d ON e.dept_id = d.dept_id
            WHERE e.emp_id = ? $where_date AND lr.status = 'Approved' 
            ORDER BY lr.start_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $emp_id);
}

$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$dept_display = $data[0]['dept_name'] ?? "ไม่ระบุ";

// 3. ตั้งค่า mPDF และ Font TH Sarabun New
$defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];
$defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'fontDir' => array_merge($fontDirs, [__DIR__ . '/../fonts']), // โฟลเดอร์ fonts ของคุณ
    'fontdata' => $fontData + [
        'thsarabunnew' => [
            'R' => 'THSarabunNew.ttf',
            'B' => 'THSarabunNew Bold.ttf',
            'I' => 'THSarabunNew Italic.ttf',
            'BI' => 'THSarabunNew BoldItalic.ttf',
        ]
    ],
    'default_font' => 'thsarabunnew'
]);

// 4. สร้างเนื้อหา HTML
$html = '
<style>
    body { font-family: "thsarabunnew"; font-size: 16pt; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table, th, td { border: 1px solid black; padding: 5px; text-align: center; }
    .header { text-align: center; font-weight: bold; font-size: 20pt; }
</style>

<div class="header">รายงานสรุปการอนุมัติการลาพนักงาน</div>
<p>
    <b>แผนก:</b> '.$dept_display.' <br>
    <b>ข้อมูลของ:</b> '.($emp_id === 'all' ? "พนักงานทุกคน" : $data[0]['first_name']." ".$data[0]['last_name']).' <br>
    <b>ช่วงเวลา:</b> '.$period_text.'
</p>

<table>
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th width="8%">ลำดับ</th>
            '.($emp_id === 'all' ? '<th>ชื่อ-นามสกุล</th>' : '').'
            <th>ประเภทการลา</th>
            <th>วันที่เริ่ม</th>
            <th>วันที่สิ้นสุด</th>
            <th>เหตุผล</th>
        </tr>
    </thead>
    <tbody>';

if (empty($data)) {
    $html .= '<tr><td colspan="'.($emp_id === 'all' ? '6' : '5').'">ไม่พบข้อมูลในช่วงเวลานี้</td></tr>';
} else {
    foreach ($data as $index => $row) {
        $html .= '<tr>
            <td>'.($index + 1).'</td>
            '.($emp_id === 'all' ? '<td style="text-align:left;">'.$row['first_name'].' '.$row['last_name'].'</td>' : '').'
            <td>'.$row['leave_type'].'</td>
            <td>'.date('d/m/Y', strtotime($row['start_date'])).'</td>
            <td>'.date('d/m/Y', strtotime($row['end_date'])).'</td>
            <td style="text-align:left;">'.$row['reason'].'</td>
        </tr>';
    }
}

$html .= '</tbody></table>';

$mpdf->WriteHTML($html);
$mpdf->Output("Report_Leave.pdf", "I");