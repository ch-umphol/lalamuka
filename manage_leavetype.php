<?php
session_start();
require_once 'conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['ผู้ดูแลระบบ', 'ผู้จัดการ', 'หัวหน้างาน'])) {
    header("location: admin_dashboard.php");
    exit;
}

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'] ?? $_SESSION['username']; 
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

$action = $_GET['action'] ?? 'read';
$type_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ------------------ CREATE ------------------
if ($action === 'add' && $_SERVER["REQUEST_METHOD"] === "POST") {
    $type_name = $conn->real_escape_string($_POST['leave_type_name']);
    $max_days = (int)$_POST['max_days_per_year'];

    $conn->begin_transaction();
    try {
        $conn->query("INSERT INTO LEAVE_TYPE (leave_type_name, max_days_per_year) VALUES ('$type_name', $max_days)");
        $conn->commit();
        $_SESSION['status_message'] = "✅ เพิ่มประเภทการลา $type_name สำเร็จ!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
    }
    header("location: manage_leavetype.php");
    exit;
}

// ------------------ READ ------------------
$type_data = null;
if ($type_id > 0) {
    $result = $conn->query("SELECT * FROM LEAVE_TYPE WHERE leave_type_id = $type_id");
    if ($result->num_rows === 1) $type_data = $result->fetch_assoc();
}

// ------------------ UPDATE ------------------
if ($action === 'edit' && $_SERVER["REQUEST_METHOD"] === "POST" && $type_data) {
    $type_name = $conn->real_escape_string($_POST['leave_type_name']);
    $max_days = (int)$_POST['max_days_per_year'];

    $conn->begin_transaction();
    try {
        $conn->query("UPDATE LEAVE_TYPE SET leave_type_name='$type_name', max_days_per_year=$max_days WHERE leave_type_id=$type_id");
        $conn->commit();
        $_SESSION['status_message'] = "✅ แก้ไขประเภทการลา $type_name สำเร็จ!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
    }
    header("location: manage_leavetype.php");
    exit;
}

// ------------------ DELETE ------------------
if ($action === 'delete' && $type_data) {
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM LEAVE_TYPE WHERE leave_type_id = $type_id");
        $conn->commit();
        $_SESSION['status_message'] = "✅ ลบประเภทการลา ".$type_data['leave_type_name']." สำเร็จ";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ ล้มเหลวในการลบประเภทการลา: ".$e->getMessage();
    }
    header("location: manage_leavetype.php");
    exit;
}

// ------------------ FETCH ALL ------------------
$types_result = $conn->query("SELECT * FROM LEAVE_TYPE ORDER BY leave_type_id ASC");
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการประเภทการลา | LALA MUKHA</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {--primary:#004030;--secondary:#66BB6A;--accent:#81C784;--bg:#f5f7fb;--text:#2e2e2e;}
body{margin:0;font-family:'Prompt',sans-serif;display:flex;background:var(--bg);color:var(--text);}
.sidebar{width:250px;background:linear-gradient(180deg,var(--primary),#2e7d32);color:#fff;position:fixed;height:100%;display:flex;flex-direction:column;box-shadow:3px 0 10px rgba(0,0,0,0.1);}
.sidebar-header{padding:25px;text-align:center;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.2);}
.menu-item{display:flex;align-items:center;padding:15px 25px;color:rgba(255,255,255,0.85);text-decoration:none;transition:0.2s;}
.menu-item:hover{background:rgba(255,255,255,0.15);color:#fff;}
.menu-item.active{background:rgba(255,255,255,0.25);color:#fff;font-weight:600;}
.menu-item i{margin-right:12px;}
.logout{margin-top:auto;text-align:center;padding:15px;background:#388e3c;color:#fff;text-decoration:none;}
.logout:hover{background:#2e7d32;}
.main-content{flex-grow:1;margin-left:250px;padding:30px;}
.header{display:flex;justify-content:space-between;align-items:center;background:#fff;padding:15px 25px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.05);margin-bottom:25px;position:sticky;top:0;z-index:10;}
.user-profile{display:flex;align-items:center;gap:10px;}
.card{background:rgba(255,255,255,0.9);border-radius:16px;padding:25px;box-shadow:0 6px 20px rgba(0,0,0,0.05);}
.card h1{margin-top:0;color:var(--primary);}
.data-table{width:100%;border-collapse:collapse;font-size:0.95em;}
.data-table th,.data-table td{padding:12px 15px;border-bottom:1px solid #eee;text-align:left;}
.data-table th{background-color:#f4f4f4;color:#333;font-weight:600;}
.btn-add{background:var(--secondary);color:#fff;padding:10px 15px;border-radius:5px;text-decoration:none;display:inline-block;margin-bottom:15px;}
.btn-add:hover{background:#4caf50;}
.form-group{margin-bottom:15px;}
.form-group label{display:block;margin-bottom:5px;font-weight:500;color:#555;}
.form-group input{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;}
.btn-submit{background:#17A2B8;color:#fff;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;font-weight:500;}
.btn-submit:hover{background:#117a8b;}
.alert-success{color:#2e7d32;background:#dff0d8;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #66bb6a;}
.alert-error{color:#c00;background:#fdd;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #f44336;}
.action-btn{display:inline-flex;justify-content:center;align-items:center;width:36px;height:36px;border-radius:50%;font-size:16px;transition:0.2s;margin-right:5px;border:2px solid;color:inherit;background:transparent;}
.action-edit{border-color:#17A2B8;color:#17A2B8;}
.action-edit:hover{background:#17A2B8;color:#fff;}
.action-delete{border-color:#c00;color:#c00;}
.action-delete:hover{background:#c00;color:#fff;}
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
    <a href="supervisor_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <a href="manage_users.php" class="menu-item"><i class="fas fa-user"></i> ข้อมูลผู้ใช้</a>
    <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="manage_leavetype.php" class="menu-item active"><i class="fas fa-list"></i> ประเภทการลา
    <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
  <div class="header">
    <div></div>
    <div class="user-profile">
        <span><?= htmlspecialchars($user_name) ?> (<?= $user_role ?>)</span>
        <i class="fas fa-user-circle" style="font-size:1.8em;color:#004030"></i>
    </div>
  </div>

  <div class="card">
    <h1>จัดการประเภทการลา</h1>

    <?php if($status_message): ?>
      <div class="<?= strpos($status_message,'✅')!==false?'alert-success':'alert-error' ?>">
        <?= $status_message ?>
      </div>
    <?php endif; ?>

    <?php if($action==='read'): ?>
      <a href="manage_leavetype.php?action=add" class="btn-add"><i class="fas fa-plus"></i> เพิ่มประเภทการลา</a>
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>ชื่อประเภทการลา</th>
            <th>วันลาสูงสุด/ปี</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php if($types_result && $types_result->num_rows>0): ?>
          <?php while($row=$types_result->fetch_assoc()): ?>
          <tr>
            <td><?= $row['leave_type_id'] ?></td>
            <td><?= htmlspecialchars($row['leave_type_name']) ?></td>
            <td><?= $row['max_days_per_year'] ?></td>
            <td>
              <a href="manage_leavetype.php?action=edit&id=<?= $row['leave_type_id'] ?>" class="action-btn action-edit" title="แก้ไข"><i class="fas fa-edit"></i></a>
              <a href="manage_leavetype.php?action=delete&id=<?= $row['leave_type_id'] ?>" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบประเภทการลา <?= htmlspecialchars($row['leave_type_name']) ?> ?');" class="action-btn action-delete" title="ลบ"><i class="fas fa-trash-alt"></i></a>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="4" style="text-align:center;">ไม่มีข้อมูลประเภทการลา</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    <?php else: ?>
      <form method="post">
        <div class="form-group">
          <label>ชื่อประเภทการลา</label>
          <input type="text" name="leave_type_name" value="<?= htmlspecialchars($type_data['leave_type_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>วันลาสูงสุดต่อปี</label>
          <input type="number" name="max_days_per_year" value="<?= htmlspecialchars($type_data['max_days_per_year'] ?? '') ?>" required>
        </div>
        <button type="submit" class="btn-submit"><?= $action==='edit'?'บันทึกการแก้ไข':'เพิ่มประเภทการลา' ?></button>
        <a href="manage_leavetype.php" style="margin-left:10px;color:#555;text-decoration:underline;">ยกเลิก</a>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
