<?php
session_start();
require_once 'conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง (Admin, Manager)
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['supervisor'])) {
    header("location: login.php");
    exit;
}

$current_user_id = $_SESSION['emp_id']; 
$user_role = $_SESSION['user_role'];

// 2. ดึงข้อมูลผู้ใช้งานที่ Login
$sql_user = "SELECT first_name, last_name FROM employees WHERE emp_id = ?";
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $current_user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
    }
    $stmt_user->close();
}

$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// 3. SQL Query ดึงรายการคำขอลาของคนที่เป็น Supervisor (role_id = 3)
$sql = "
    SELECT 
        LR.request_id,
        E.first_name,
        E.last_name,
        COALESCE(LT.leave_type_display, LR.leave_type) AS leave_type_name,
        LR.status,
        DATEDIFF(LR.end_date, LR.start_date) + 1 AS duration_days
    FROM leave_requests LR
    JOIN employees E ON LR.emp_id = E.emp_id
    LEFT JOIN leave_types LT ON LT.leave_type_name LIKE CONCAT(LR.leave_type, '%')
    WHERE LR.status = 'Pending' AND E.role_id = 4
    ORDER BY LR.start_date DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการลา | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #004030; --secondary: #66BB6A; --bg: #f5f7fb; --text: #2e2e2e; }
        body { margin: 0; font-family: 'Prompt', sans-serif; background: var(--bg); display: flex; color: var(--text); }
        .sidebar { width: 250px; background: linear-gradient(180deg, var(--primary), #2e7d32); color: #fff; position: fixed; height: 100%; display: flex; flex-direction: column; box-shadow: 3px 0 10px rgba(0,0,0,0.15); }
        .sidebar-header { text-align: center; padding: 25px 15px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.85); text-decoration: none; transition: 0.25s; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.2); color: #fff; font-weight: 600; }
        .menu-item i { margin-right: 12px; }
        .logout { margin-top: auto; text-align: center; padding: 15px; background: rgba(255,255,255,0.15); color: #fff; text-decoration: none; }
        .main-content { flex-grow: 1; margin-left: 250px; padding: 30px; }
        .header { background: #fff; border-radius: 12px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card { background: #fff; border-radius: 16px; padding: 25px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .data-table th, .data-table td { padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; }
        .status-badge { padding: 6px 12px; border-radius: 15px; font-size: 0.85em; background: #fff3e0; color: #ef6c00; font-weight: 500; }
        .action-btn { border: 1px solid #ccc; border-radius: 50%; width: 35px; height: 35px; cursor: pointer; margin: 0 3px; background: #fff; transition: 0.2s; }
        .action-btn.approve { color: #4CAF50; border-color: #4CAF50; }
        .action-btn.reject { color: #F44336; border-color: #F44336; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); }
        .modal-content { background: #fff; margin: 2% auto; border-radius: 16px; width: 95%; max-width: 550px; position: relative; max-height: 90vh; overflow-y: auto; box-shadow: 0 15px 40px rgba(0,0,0,0.2); }
        .detail-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f8f9fa; }
        .detail-label { min-width: 130px; font-weight: 600; color: var(--primary); }
        .reason-box { background: #f8f9fa; padding: 12px; border-radius: 8px; margin-top: 5px; border-left: 4px solid var(--secondary); font-size: 0.95em; }
        .btn-submit { padding: 10px 25px; border-radius: 25px; border: none; color: white; cursor: pointer; font-weight: 500; transition: 0.3s; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการผู้ใช้งาน</div>
     <a href="supervisor_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
        <a href="manage_leave.php" class="menu-item active"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
        <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องการลา</a>
        <!-- <a href="manage_users.php" class="menu-item"><i class="fas fa-user"></i> ข้อมูลผู้ใช้</a> -->
        <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <h3 style="margin:0;">ยินดีต้อนรับ</h3>
        <div class="user-profile">
            <span><?php echo $user_display_name; ?> (<?php echo $user_role; ?>)</span>
            <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary); margin-left:10px;"></i>
        </div>
    </div>

    <div class="card">
        <h1 style="color: var(--primary);"><i class="fas fa-tasks"></i> คำร้องจาก Supervisor</h1>
        <?php if ($status_message): ?><div style="background:#e8f5e9; color:#2e7d32; padding:15px; border-radius:8px; margin-bottom:20px;"><?php echo $status_message; ?></div><?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th>ประเภทการลา</th>
                    <th>จำนวนวัน</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): $i=1; while($row=$result->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                    <td><?= htmlspecialchars($row['leave_type_name']) ?></td>
                    <td><?= $row['duration_days'] ?> วัน</td>
                    <td><span class="status-badge">รอพิจารณา</span></td>
                    <td>
                        <button class="action-btn approve" onclick="handleApproval(<?= $row['request_id'] ?>,'Approved')"><i class="fas fa-check"></i></button>
                        <button class="action-btn reject" onclick="handleApproval(<?= $row['request_id'] ?>,'Rejected')"><i class="fas fa-times"></i></button>
                        <button class="action-btn" style="color:#17A2B8; border-color:#17A2B8;" onclick="showDetails(<?= $row['request_id'] ?>)"><i class="fas fa-eye"></i></button>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6" style="text-align:center; padding:30px;">ไม่มีคำขอลาที่รอการอนุมัติ</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="leaveModal" class="modal">
    <div class="modal-content">
        <div style="padding: 20px 25px; border-bottom: 2px solid #f1f3f5;">
            <span style="float:right; cursor:pointer; font-size:24px;" onclick="closeModal()">&times;</span>
            <h2 style="color:var(--primary); margin:0;"><i class="fas fa-file-alt"></i> รายละเอียดการลา</h2>
        </div>
        <div id="modal-body" style="padding: 20px 25px;"></div>
    </div>
</div>

<script>
function handleApproval(id, action) {
    if (confirm(`ยืนยันการทำรายการนี้?`)) {
        window.location.href = `approve_leave.php?request_id=${id}&status=${action}`;
    }
}

function showDetails(id) {
    const modal = document.getElementById("leaveModal");
    const modalBody = document.getElementById("modal-body");
    modal.style.display = "block";
    modalBody.innerHTML = '<div style="text-align:center;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    fetch(`get_leave_details.php?request_id=${id}`)
        .then(response => response.json())
        .then(data => {
            let evidenceHTML = '<span style="color:#999;">ไม่มีไฟล์หลักฐาน</span>';
            if (data.evidence_file) {
                const fileURL = `../uploads/evidence/${data.evidence_file}`;
                if (data.file_category === 'image') {
                    evidenceHTML = `<div style="text-align:center; margin-top:10px;"><img src="${fileURL}" style="max-width:100%; border-radius:8px; border:1px solid #ddd;" onerror="this.src='../Image/no-image.png';"></div>`;
                } else {
                    evidenceHTML = `<a href="${fileURL}" target="_blank" class="btn-submit" style="background:#007bff; text-decoration:none; display:inline-block;">เปิดไฟล์เอกสาร</a>`;
                }
            }

            modalBody.innerHTML = `
                <div class="detail-row"><span class="detail-label">พนักงาน:</span><span>${data.first_name} ${data.last_name}</span></div>
                <div class="detail-row"><span class="detail-label">ประเภท:</span><span>${data.leave_type_display}</span></div>
                <div class="detail-row"><span class="detail-label">วันที่ลา:</span><span>${data.start_date} ถึง ${data.end_date} (<b>${data.duration_days} วัน)</b></span></div>
                <div style="margin-top:15px;"><span class="detail-label">เหตุผล:</span><div class="reason-box">${data.reason || '-'}</div></div>
                <div style="margin-top:15px;"><span class="detail-label">หลักฐาน:</span><div>${evidenceHTML}</div></div>
                <div style="margin-top:25px; text-align:right;">
                    <button class="btn-submit" style="background:var(--secondary); margin-right:8px;" onclick="handleApproval(${id}, 'Approved')">อนุมัติ</button>
                    <button class="btn-submit" style="background:#dc3545;" onclick="handleApproval(${id}, 'Rejected')">ไม่อนุมัติ</button>
                </div>`;
        });
}

function closeModal() { document.getElementById("leaveModal").style.display = "none"; }
window.onclick = function(event) { if (event.target == document.getElementById("leaveModal")) closeModal(); }
</script>
</body>
</html>