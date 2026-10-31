<?php
session_start();
require_once '../conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง (อิงตาม role_name ในฐานข้อมูลใหม่)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'manager') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$user_role = $_SESSION['user_role'];

// ดึงข้อมูล Manager และแผนก (อิงตามโครงสร้าง hotel.sql)
$sql_user = "SELECT first_name, last_name, dept_id FROM employees WHERE emp_id = ?";
$current_dept_id = 0;
$user_display_name = $_SESSION['username'];

if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
        $current_dept_id = $row_user['dept_id'];
    }
    $stmt_user->close();
}

// นับสถิติสำหรับ Dashboard (เฉพาะในแผนกที่ Manager ดูแล)
$stats = ['total_employees' => 0, 'pending_requests' => 0];

// 1. นับพนักงานในแผนกที่ยังไม่ลาออก
$sql_emp_count = "SELECT COUNT(emp_id) AS total FROM employees WHERE dept_id = ? AND resign_date IS NULL";
if ($stmt_emp = $conn->prepare($sql_emp_count)) {
    $stmt_emp->bind_param("i", $current_dept_id);
    $stmt_emp->execute();
    $stats['total_employees'] = $stmt_emp->get_result()->fetch_assoc()['total'];
    $stmt_emp->close();
}

// 2. นับคำขอลาที่รออนุมัติเฉพาะพนักงานในแผนก
$sql_pend_count = "SELECT COUNT(lr.request_id) AS total 
                   FROM leave_requests lr 
                   JOIN employees e ON lr.emp_id = e.emp_id 
                   WHERE lr.status = 'Pending' AND e.dept_id = ?";
if ($stmt_pend = $conn->prepare($sql_pend_count)) {
    $stmt_pend->bind_param("i", $current_dept_id);
    $stmt_pend->execute();
    $stats['pending_requests'] = $stmt_pend->get_result()->fetch_assoc()['total'];
    $stmt_pend->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* UI Color Scheme from manage_users.php */
    :root {
        --primary: #004030;
        --secondary: #66BB6A;
        --accent: #81C784;
        --bg: #f5f7fb;
        --text: #2e2e2e;
    }

    body {
        margin: 0;
        font-family: 'Prompt', sans-serif;
        background: var(--bg);
        display: flex;
        min-height: 100vh;
        color: var(--text);
    }

    /* Sidebar Style from manage_users.php */
    .sidebar {
        width: 250px;
        background: linear-gradient(180deg, var(--primary), #2e7d32);
        color: #fff;
        position: fixed;
        height: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
        z-index: 100;
    }

    .sidebar-header {
        padding: 25px;
        text-align: center;
        font-weight: 600;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .menu-list {
        flex-grow: 1;
        padding-top: 10px;
    }

    .menu-item {
        display: flex;
        align-items: center;
        padding: 15px 25px;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        transition: 0.2s;
    }

    .menu-item:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
    }

    .menu-item.active {
        background: rgba(255, 255, 255, 0.25);
        color: #fff;
        font-weight: 600;
    }

    .menu-item i {
        margin-right: 12px;
        width: 20px;
        text-align: center;
    }

    .logout-btn {
        margin-top: auto;
        text-align: center;
        padding: 15px;
        background: #388e3c;
        color: #fff;
        text-decoration: none;
        transition: 0.3s;
    }

    .logout-btn:hover {
        background: #2e7d32;
    }

    /* Main Content Style */
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
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
    }

    .card h1 {
        margin-top: 0;
        color: var(--primary);
        font-size: 1.8em;
    }

    /* Stat Cards */
    .stat-cards {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .stat-card {
        flex: 1;
        min-width: 240px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        transition: 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        font-size: 2.5em;
        color: var(--secondary);
    }

    .stat-details h3 {
        margin: 0;
        font-size: 2.2em;
        color: var(--primary);
    }

    .stat-details p {
        margin: 5px 0 0;
        color: #666;
        font-size: 1em;
    }

    .btn-action {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        padding: 12px 25px;
        border-radius: 8px;
        text-decoration: none;
        transition: 0.3s;
        font-weight: 500;
    }

    .btn-action:hover {
        background: var(--secondary);
        transform: scale(1.02);
    }
    </style>
</head>

<body>

    <div class="sidebar">
        <div class="sidebar-header">
            LALA MUKHA<br><span style="font-size: 0.8em; opacity: 0.8;">Manager Control Panel</span>
        </div>
        <div class="menu-list">
            <a href="manager_dashboard.php" class="menu-item active">
                <i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ
            </a>
            <a href="manage_leave.php" class="menu-item">
                <i class="fas fa-clipboard-list"></i> จัดการการลา
            </a>
            <a href="manage_employees.php" class="menu-item">
                <i class="fas fa-users"></i> ข้อมูลพนักงาน
            </a>
            <a href="manage_leavetype.php" class="menu-item">
                <i class="fas fa-list-ul"></i> ประเภทการลา
            </a>
            <a href="manage_department.php" class="menu-item">
                <i class="fas fa-building"></i> แผนก
            </a>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
        </a>
    </div>

    <div class="main-content">
        <div class="header">
            <div style="color: var(--primary); font-weight: 600;">ยินดีต้อนรับสู่ระบบจัดการแผนก</div>
            <div class="user-profile">
                <span><?php echo $user_display_name; ?> (Manager)</span>
                <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary);"></i>
            </div>
        </div>

        <div class="card">
            <h1>ภาพรวมแผนกของคุณ</h1>
            <div class="stat-cards">
                <div class="stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_employees']); ?></h3>
                        <p>พนักงานในแผนก</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock stat-icon" style="color:#FFC107;"></i>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['pending_requests']); ?></h3>
                        <p>คำขอรออนุมัติ</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>เมนูจัดการด่วน</h2>
            <p>เลือกจัดการรายการลา ข้อมูลพนักงาน หรือสรุปผลงานรายเดือนของแผนกคุณ</p>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="manage_leave.php" class="btn-action">
                    อนุมัติการลา <i class="fas fa-clipboard-check" style="margin-left: 8px;"></i>
                </a>

                <a href="manage_employees.php" class="btn-action" style="background: var(--secondary);">
                    ดูข้อมูลพนักงาน <i class="fas fa-users" style="margin-left: 8px;"></i>
                </a>
            </div>
        </div>
    </div>

</body>

</html>