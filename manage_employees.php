<?php
session_start();
require_once '../conn.php'; 

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['supervisor'])) {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// ดึงข้อมูลผู้ใช้และแผนกของ Supervisor
$sql_user = "SELECT E.first_name, E.last_name, E.dept_id, D.dept_name 
             FROM employees E 
             LEFT JOIN departments D ON E.dept_id = D.dept_id 
             WHERE E.emp_id = ?";
$current_dept_id = 0;
$current_dept_name = "ไม่ระบุแผนก";

if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
        $current_dept_id = $row_user['dept_id'];
        $current_dept_name = htmlspecialchars($row_user['dept_name']);
    }
    $stmt_user->close();
}

// กรองเฉพาะพนักงานทั่วไป (role_id = 4) ในแผนกเดียวกัน
$sql_employees = "
    SELECT E.emp_id, E.emp_code, E.first_name, E.last_name, E.username, R.role_display
    FROM employees E
    JOIN roles R ON E.role_id = R.role_id
    WHERE E.dept_id = ? AND E.role_id = 4
    ORDER BY E.emp_code ASC";

$stmt_list = $conn->prepare($sql_employees);
$stmt_list->bind_param("i", $current_dept_id);
$stmt_list->execute();
$result = $stmt_list->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการพนักงานแผนก <?= $current_dept_name ?> | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#004030; --secondary:#66BB6A; --accent:#81C784; --bg:#f5f7fb; --text:#2e2e2e; }
        body { margin:0; font-family:'Prompt',sans-serif; background:var(--bg); display:flex; color:var(--text); }
        .sidebar { width:250px; background:linear-gradient(180deg,var(--primary),#2e7d32); color:#fff; position:fixed; height:100%; display:flex; flex-direction:column; box-shadow:3px 0 10px rgba(0,0,0,0.15); }
        .sidebar-header { text-align:center; padding:25px 15px; font-weight:600; border-bottom:1px solid rgba(255,255,255,0.2); }
        .menu-item { display:flex; align-items:center; padding:15px 25px; color:rgba(255,255,255,0.85); text-decoration:none; transition:0.25s; }
        .menu-item:hover, .menu-item.active { background:rgba(255,255,255,0.2); color:#fff; font-weight:600; }
        .menu-item i { margin-right:12px; width:20px; text-align:center; }
        .main-content { flex-grow:1; margin-left:250px; padding:30px; }
        .header { background:#fff; border-radius:12px; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 10px rgba(0,0,0,0.05); margin-bottom:25px; }
        .card { background:#fff; border-radius:16px; padding:25px; box-shadow:0 6px 20px rgba(0,0,0,0.05); }
        .data-table { width:100%; border-collapse:collapse; }
        .data-table th, .data-table td { padding:15px; border-bottom:1px solid #eee; text-align:left; }
        .data-table th { background:#f8f9fa; font-weight:600; color:var(--primary); }
        .btn-stats { background:var(--secondary); color:#fff; border:none; padding:8px 15px; border-radius:20px; cursor:pointer; font-size:0.9em; }
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
        .modal-content { background:#fff; margin:5% auto; padding:30px; border-radius:16px; width:90%; max-width:650px; }
        .stat-badge { background:#f1f8f1; padding:12px; border-radius:10px; flex:1; min-width:140px; text-align:center; border:1px solid #e0eee0; }
        .status-badge { padding:4px 8px; border-radius:12px; font-size:0.8em; font-weight:600; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
   <a href="supervisor_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
        <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
        <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องการลา</a>
        <!-- <a href="manage_users.php" class="menu-item"><i class="fas fa-user"></i> ข้อมูลผู้ใช้</a> -->
        <a href="manage_employees.php" class="menu-item "><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="logout.php" style="margin-top:auto; padding:15px; background:#388e3c; text-align:center; color:#fff; text-decoration:none;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div style="font-weight:600; color:var(--primary);">แผนก: <span style="color:var(--secondary);"><?= $current_dept_name ?></span></div>
        <div class="user-profile">
            <span><strong><?= $user_display_name ?></strong> (Supervisor)</span>
            <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary); margin-left:10px;"></i>
        </div>
    </div>

    <div class="card">
        <h1><i class="fas fa-users-cog"></i> รายชื่อพนักงานในความดูแล</h1>
        <table class="data-table">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['emp_code']) ?></td>
                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                    <td>
                        <button class="btn-stats" onclick="viewLeaveStats(<?= $row['emp_id'] ?>, '<?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?>')">
                            <i class="fas fa-chart-bar"></i> ดูสถิติการลา
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="statsModal" class="modal">
    <div class="modal-content">
        <span onclick="closeModal()" style="float:right; cursor:pointer; font-size:24px;">&times;</span>
        <h2 id="modalTitle" style="color:var(--primary); margin-top:0;"></h2>
        
        <h4 style="border-left:4px solid var(--secondary); padding-left:10px; margin-bottom:15px;">ยอดคงเหลือปี <?= date('Y') ?></h4>
        <div id="balanceSection" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:25px;"></div>

        <h4 style="border-left:4px solid var(--primary); padding-left:10px; margin-bottom:15px;">ประวัติการลา 10 รายการล่าสุด</h4>
        <div style="overflow-x:auto;">
            <table class="data-table" style="font-size:0.85em;">
                <thead>
                    <tr>
                        <th>ประเภท</th>
                        <th>วันที่ลา</th>
                        <th>จำนวน</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
function viewLeaveStats(empId, empName) {
    document.getElementById('modalTitle').innerText = "สถิติการลา: " + empName;
    const balanceDiv = document.getElementById('balanceSection');
    const tableBody = document.getElementById('historyTableBody');
    
    balanceDiv.innerHTML = 'กำลังโหลด...';
    tableBody.innerHTML = '';
    document.getElementById('statsModal').style.display = 'block';

    fetch(`get_employee_leave_stats.php?emp_id=${empId}`)
        .then(res => res.json())
        .then(data => {
            // 1. แสดงยอดคงเหลือ
            balanceDiv.innerHTML = data.balances.map(b => `
                <div class="stat-badge">
                    <div style="font-size:0.8em; color:#666;">${b.leave_type_display}</div>
                    <div style="font-size:1.2em; font-weight:700; color:var(--primary);">${parseFloat(b.remaining)} ${b.leave_unit == 'day' ? 'วัน' : 'ชม.'}</div>
                </div>
            `).join('') || 'ไม่พบข้อมูลสิทธิ์วันลา';

            // 2. แสดงประวัติการลา
            tableBody.innerHTML = data.history.map(h => {
                let color = h.status == 'Approved' ? '#2e7d32' : (h.status == 'Pending' ? '#ffa000' : '#c62828');
                return `
                    <tr>
                        <td><strong>${h.leave_display}</strong></td>
                        <td>${h.start_date}</td>
                        <td>${h.duration} วัน</td>
                        <td><span class="status-badge" style="background:${color}15; color:${color}; border:1px solid ${color}40;">${h.status}</span></td>
                    </tr>
                `;
            }).join('') || '<tr><td colspan="4" style="text-align:center;">ไม่พบประวัติการลา</td></tr>';
        });
}

function closeModal() { document.getElementById('statsModal').style.display = 'none'; }
window.onclick = function(event) { if (event.target == document.getElementById('statsModal')) closeModal(); }
</script>
</body>
</html>