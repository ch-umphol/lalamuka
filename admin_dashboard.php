<?php
session_start();
require_once '../conn.php'; 

// ปรับการเช็คสิทธิ์ให้ตรงกับ role_name ในฐานข้อมูลใหม่
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager', 'supervisor'])) {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id'];
$user_role = $_SESSION['user_role'];
$username = $_SESSION['username']; 
$error_message = '';
$success_message = '';
$today = date('Y-m-d');

// ดึงชื่อจริงและนามสกุลเพื่อแสดงผล (อิงตามตาราง employees)
$user_display_name = $username;
$sql_user = "SELECT first_name, last_name FROM employees WHERE emp_id = ?";
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
    }
    $stmt_user->close();
}

$stats = ['total_employees' => 0, 'pending_requests' => 0, 'total_leave_types' => 0];

// 1. นับพนักงานทั้งหมด
$sql_employees = "SELECT COUNT(emp_id) AS total FROM employees";
$result_employees = $conn->query($sql_employees);
if ($result_employees) { $stats['total_employees'] = $result_employees->fetch_assoc()['total']; }

// 2. นับรายการรออนุมัติ (Pending)
// หมายเหตุ: ใน SQL structure ใหม่ไม่มี supervisor_id ในตาราง employees โดยตรง 
// หากต้องการแยกตามสายงานอาจต้องมีการปรับโครงสร้างเพิ่ม แต่เบื้องต้นดึงรวมตามสิทธิ์
$sql_pending = "SELECT COUNT(request_id) AS total FROM leave_requests WHERE status = 'Pending'";
$result_pending = $conn->query($sql_pending);
if ($result_pending) { $stats['pending_requests'] = $result_pending->fetch_assoc()['total']; }

// 3. นับประเภทการลาที่มีการใช้งาน
$sql_leave_types = "SELECT COUNT(DISTINCT leave_type) AS total FROM leave_requests";
$result_leave_types = $conn->query($sql_leave_types);
if ($result_leave_types) { $stats['total_leave_types'] = $result_leave_types->fetch_assoc()['total']; }

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ภาพรวมระบบ | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

    <style>
    :root {
        --primary: #004030;
        --secondary: #66BB6A;
        --accent: #81C784;
        --bg: #f5f7fb;
        --text: #2e2e2e;
        --font-scale: 1.0;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'Prompt', sans-serif;
        background: var(--bg);
        display: flex;
        min-height: 100vh;
        font-size: calc(16px * var(--font-scale));
    }

    /* Sidebar */
    .sidebar {
        width: 250px;
        background: linear-gradient(180deg, var(--primary), #2e7d32);
        color: #fff;
        position: fixed;
        height: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar-header {
        font-size: 1.1em;
        font-weight: 600;
        text-align: center;
        padding: 25px 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .menu-item {
        display: flex;
        align-items: center;
        padding: 15px 25px;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        transition: all 0.2s;
    }

    .menu-item:hover, .menu-item.active {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
    }

    .menu-item i { margin-right: 12px; font-size: 1.2em; }

    .logout {
        margin-top: auto;
        background: #388e3c;
        text-align: center;
        padding: 15px 0;
        color: #fff;
        text-decoration: none;
    }

    /* Main Content */
    .main-content {
        flex-grow: 1;
        margin-left: 250px;
        padding: 30px;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        padding: 15px 25px;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
    }

    .zoom-controls { display: flex; gap: 5px; border-right: 1px solid #ddd; padding-right: 15px; }
    .zoom-controls button {
        background: #f0f0f0; border: 1px solid #ddd; width: 32px; height: 32px; border-radius: 6px; cursor: pointer;
    }
    .zoom-controls button.active { background: var(--primary); color: white; }

    .card {
        background: #fff;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
    }

    .stat-cards { display: flex; flex-wrap: wrap; gap: 20px; }
    .stat-card {
        flex: 1; min-width: 240px; display: flex; justify-content: space-between; align-items: center;
        background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }
    .stat-icon { font-size: 2.5em; color: var(--secondary); }
    .stat-details h3 { margin: 0; font-size: 2em; color: var(--primary); }

    .btn {
        display: inline-block; background: var(--primary); color: #fff; padding: 10px 18px;
        border-radius: 6px; text-decoration: none;
    }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
        <a href="admin_dashboard.php" class="menu-item active"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
        <a href="manage_users.php" class="menu-item"><i class="fas fa-user-cog"></i> จัดการพนักงาน</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="zoom-controls">
                <button id="zoom-small" data-scale="0.8">ก-</button>
                <button id="zoom-normal" class="active" data-scale="1.0">ก</button>
                <button id="zoom-large" data-scale="1.2">ก+</button>
            </div>
            <div class="user-profile">
                <span><?php echo $user_display_name; ?> (<?php echo strtoupper($user_role); ?>)</span>
                <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary); margin-left:10px;"></i>
            </div>
        </div>

        <div class="card">
            <h1>ภาพรวมระบบ</h1>
            <div class="stat-cards">
                <div class="stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_employees']); ?></h3>
                        <p>พนักงานทั้งหมด</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock stat-icon" style="color:#FFC107;"></i>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['pending_requests']); ?></h3>
                        <p>คำขอรออนุมัติ</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-list stat-icon" style="color:#17A2B8;"></i>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_leave_types']); ?></h3>
                        <p>ประเภทการลาที่ใช้</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>รายงานการลา</h2>
            <canvas id="leaveChart"></canvas>
        </div>

        <div class="card">
            <h2>เมนูจัดการ</h2>
            <a href="manage_leave.php" class="btn">จัดการการลา <i class="fas fa-arrow-right"></i></a>
            <a href="manage_users.php" class="btn" style="background:var(--secondary);">จัดการข้อมูลพนักงาน</a>
        </div>
    </div>

    <script>
    // ระบบ Zoom
    const zoomControls = document.querySelector('.zoom-controls');
    const root = document.documentElement;
    zoomControls.addEventListener('click', e => {
        if (e.target.tagName === 'BUTTON') {
            const scale = e.target.dataset.scale;
            root.style.setProperty('--font-scale', scale);
            zoomControls.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
        }
    });

    // ตัวอย่าง Chart (ควรมีไฟล์ api_leave_stats.php รองรับ)
    const ctx = document.getElementById('leaveChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.'],
            datasets: [{
                label: 'จำนวนวันลา (วัน)',
                data: [12, 19, 3, 5],
                backgroundColor: '#66BB6A'
            }]
        },
        options: { responsive: true }
    });
    </script>
</body>
</html>