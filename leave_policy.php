<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'พนักงาน') {
    header("location: login.php");
    exit;
}

$user_name = $_SESSION['user_name'] ?? $_SESSION['username'];

$result = $conn->query("SELECT * FROM leave_policy ORDER BY upload_date DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>หลักการการลา | LALA MUKHA</title>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --primary: #182848;
  --secondary: #4b6cb7;
  --bg: #f5f7fb;
  --text: #2e2e2e;
}

body {
  margin: 0;
  font-family: 'Kanit', sans-serif;
  background: var(--bg);
  display: flex;
  color: var(--text);
}

/* Sidebar */
.sidebar {
  width: 250px;
  background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
  color: #fff;
  position: fixed;
  height: 100vh;
  display: flex;
  flex-direction: column;
  box-shadow: 3px 0 10px rgba(0,0,0,0.15);
}
.sidebar-header {
  text-align: center;
  padding: 25px 15px;
  font-weight: 600;
  border-bottom: 1px solid rgba(255,255,255,0.2);
}
.menu-item {
  display: flex;
  align-items: center;
  padding: 15px 25px;
  color: rgba(255,255,255,0.85);
  text-decoration: none;
  transition: 0.25s;
}
.menu-item:hover, .menu-item.active {
  background: rgba(255,255,255,0.2);
  color: #fff;
  font-weight: 600;
}
.menu-item i { margin-right: 12px; }
.logout {
  margin-top: auto;
  text-align: center;
  padding: 15px;
  background: rgba(255,255,255,0.15);
  color: #fff;
}
.logout:hover { background: rgba(255,255,255,0.3); }

/* Main Content */
.main-content {
  flex-grow: 1;
  margin-left: 250px;
  padding: 30px;
}

/* Header */
.topbar {
  background: #fff;
  border-radius: 12px;
  padding: 15px 25px;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
  margin-bottom: 25px;
}
.topbar i {
  color: var(--secondary);
  font-size: 1.8em;
  margin-left: 10px;
}
.topbar span { font-weight: 500; }

/* Card */
.card {
  background: rgba(255,255,255,0.95);
  border-radius: 16px;
  padding: 25px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.05);
}
.card h1 {
  margin-top: 0;
  color: var(--primary);
  margin-bottom: 20px;
}

/* Table */
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95em;
  border-radius: 10px;
  overflow: hidden;
}
.data-table th, .data-table td {
  padding: 12px 15px;
  text-align: left;
}
.data-table th {
  background-color: var(--secondary);
  color: white;
}
.data-table tr:nth-child(even) {
  background-color: #f9f9f9;
}
.data-table tr:hover {
  background-color: #eef3ff;
}
.data-table td {
  border-bottom: 1px solid #eee;
}

/* Button */
.btn-submit {
  background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
  color: white;
  text-decoration: none;
  padding: 8px 14px;
  border-radius: 25px;
  transition: 0.3s;
  font-weight: 500;
}
.btn-submit:hover {
  background: linear-gradient(135deg, #5c7de1 0%, #243a73 100%);
  transform: translateY(-1px);
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการการลา</div>
  <a href="employee_dashboard.php" class="menu-item"><i class="fas fa-home"></i> Dashboard</a>
  <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องขอลางาน</a>
  <a href="leave_history.php" class="menu-item"><i class="fas fa-list"></i> ประวัติการลา</a>
  <a href="notifications.php" class="menu-item"><i class="fas fa-bell"></i> การแจ้งเตือน</a>
  <a href="logout.php" class="menu-item logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<!-- Main -->
<div class="main-content">
  <div class="topbar">
    <span><?php echo htmlspecialchars($user_name); ?> (พนักงาน)</span>
    <i class="fas fa-user-circle"></i>
  </div>

  <div class="card">
    <h1><i class="fas fa-book"></i> หลักการการลา</h1>
    <table class="data-table">
      <tr>
        <th>ชื่อเอกสาร</th>
        <th>วันที่อัปโหลด</th>
        <th>ดาวน์โหลด</th>
      </tr>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($row['policy_title']); ?></td>
          <td><?php echo htmlspecialchars($row['upload_date']); ?></td>
          <td><a href="uploads/<?php echo htmlspecialchars($row['policy_file']); ?>" target="_blank" class="btn-submit"><i class="fas fa-download"></i> ดูไฟล์</a></td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="3" style="text-align:center;">ไม่มีข้อมูลหลักการการลา</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>

</body>
</html>
