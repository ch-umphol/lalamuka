<?php
session_start();
require_once 'conn.php'; 

// ----------------------------------------------------
// 1. ตรวจสอบสิทธิ์
// ----------------------------------------------------
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['ผู้ดูแลระบบ', 'ผู้จัดการ'])) {
    $_SESSION['status_message'] = "❌ คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    header("location: admin_dashboard.php");
    exit;
}

// ตรวจสอบ employee_id
if (!isset($_GET['employee_id']) || !is_numeric($_GET['employee_id'])) {
    $_SESSION['status_message'] = "❌ ไม่พบรหัสพนักงานที่ต้องการจัดการ";
    header("location: manage_employees.php");
    exit;
}

$target_employee_id = (int)$_GET['employee_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['username'] ?? $_SESSION['user_name'];
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// ----------------------------------------------------
// 2. LOGIC CRUD ยอดวันลา
// ----------------------------------------------------

// ADD
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] === 'add') {
    $leave_type_id = (int)$_POST['leave_type_id'];
    $remaining_days = intval($_POST['remaining_days']);

    if ($remaining_days < 0) {
        $_SESSION['status_message'] = "❌ จำนวนวันลาต้องไม่ติดลบ";
    } else {
        $stmt = $conn->prepare("SELECT balance_id FROM EMPLOYEE_LEAVE_BALANCE WHERE employee_id=? AND leave_type_id=?");
        $stmt->bind_param("ii", $target_employee_id, $leave_type_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $_SESSION['status_message'] = "⚠️ มีรายการนี้แล้ว โปรดใช้ฟังก์ชันแก้ไข";
        } else {
            $stmt2 = $conn->prepare("INSERT INTO EMPLOYEE_LEAVE_BALANCE (employee_id, leave_type_id, remaining_days) VALUES (?, ?, ?)");
            $stmt2->bind_param("iii", $target_employee_id, $leave_type_id, $remaining_days);
            if ($stmt2->execute()) $_SESSION['status_message'] = "✅ เพิ่มยอดวันลาสำเร็จ!";
            else $_SESSION['status_message'] = "❌ เพิ่มไม่สำเร็จ: ".$stmt2->error;
        }
    }
    header("location: manage_balance.php?employee_id=$target_employee_id");
    exit;
}

// EDIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['action'] === 'edit') {
    $balance_id = (int)$_POST['balance_id'];
    $new_remaining_days = intval($_POST['new_remaining_days']);
    if ($new_remaining_days < 0) {
        $_SESSION['status_message'] = "❌ วันลาคงเหลือต้องไม่ติดลบ";
    } else {
        $stmt = $conn->prepare("UPDATE EMPLOYEE_LEAVE_BALANCE SET remaining_days=? WHERE balance_id=?");
        $stmt->bind_param("ii", $new_remaining_days, $balance_id);
        if ($stmt->execute()) $_SESSION['status_message'] = "✅ ปรับปรุงยอดวันลาสำเร็จ!";
        else $_SESSION['status_message'] = "❌ ปรับปรุงไม่สำเร็จ: ".$stmt->error;
    }
    header("location: manage_balance.php?employee_id=$target_employee_id");
    exit;
}

// DELETE
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['balance_id'])) {
    $balance_id = (int)$_GET['balance_id'];
    $stmt = $conn->prepare("DELETE FROM EMPLOYEE_LEAVE_BALANCE WHERE balance_id=?");
    $stmt->bind_param("i", $balance_id);
    if ($stmt->execute()) $_SESSION['status_message'] = "✅ ลบสำเร็จ!";
    else $_SESSION['status_message'] = "❌ ลบไม่สำเร็จ: ".$stmt->error;
    header("location: manage_balance.php?employee_id=$target_employee_id");
    exit;
}

// ----------------------------------------------------
// 3. ดึงข้อมูล
// ----------------------------------------------------
$stmt = $conn->prepare("SELECT first_name,last_name FROM EMPLOYEE WHERE employee_id=?");
$stmt->bind_param("i", $target_employee_id);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();

$balances = $conn->prepare("
    SELECT EBL.balance_id, LT.leave_type_name, EBL.remaining_days 
    FROM EMPLOYEE_LEAVE_BALANCE EBL 
    JOIN LEAVE_TYPE LT ON EBL.leave_type_id=LT.leave_type_id
    WHERE employee_id=? ORDER BY LT.leave_type_name ASC");
$balances->bind_param("i", $target_employee_id);
$balances->execute();
$balance_result = $balances->get_result();

$types = $conn->query("SELECT * FROM LEAVE_TYPE ORDER BY leave_type_name ASC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการยอดวันลา | LALA MUKHA</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {--primary:#004030;--secondary:#66BB6A;--bg:#f5f7fb;--text:#2e2e2e;}
body{margin:0;font-family:'Prompt',sans-serif;display:flex;background:var(--bg);color:var(--text);}
.sidebar{width:250px;background:linear-gradient(180deg,var(--primary),#2e7d32);color:#fff;position:fixed;height:100%;display:flex;flex-direction:column;}
.sidebar-header{padding:25px;text-align:center;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.2);}
.menu-item{display:flex;align-items:center;padding:15px 25px;color:rgba(255,255,255,0.85);text-decoration:none;}
.menu-item:hover,.menu-item.active{background:rgba(255,255,255,0.2);color:#fff;}
.menu-item i{margin-right:10px;}
.logout{margin-top:auto;text-align:center;padding:15px;background:#388e3c;color:#fff;text-decoration:none;}
.logout:hover{background:#2e7d32;}
.main-content{flex-grow:1;margin-left:250px;padding:30px;}
.header{display:flex;justify-content:space-between;align-items:center;background:#fff;padding:15px 25px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.05);margin-bottom:25px;}
.card{background:#fff;border-radius:16px;padding:25px;box-shadow:0 6px 20px rgba(0,0,0,0.05);}
.card h1{margin-top:0;color:var(--primary);}
.data-table{width:100%;border-collapse:collapse;}
.data-table th,.data-table td{padding:12px 15px;border-bottom:1px solid #eee;}
.data-table th{background-color:#f4f4f4;}
.action-btn{display:inline-flex;justify-content:center;align-items:center;width:34px;height:34px;border-radius:50%;font-size:15px;margin:0 3px;border:2px solid;cursor:pointer;}
.edit{color:#17A2B8;border-color:#17A2B8;}
.edit:hover{background:#17A2B8;color:#fff;}
.delete{color:#C00;border-color:#C00;}
.delete:hover{background:#C00;color:#fff;}
.alert-success{color:#2e7d32;background:#dff0d8;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #66bb6a;}
.alert-error{color:#c00;background:#fdd;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #f44336;}
.form-row{display:flex;gap:15px;align-items:center;margin-top:20px;}
.form-control{padding:10px;border-radius:6px;border:1px solid #ccc;}
.btn{background:var(--secondary);color:#fff;padding:10px 15px;border:none;border-radius:6px;cursor:pointer;}
.btn:hover{background:#4caf50;}
</style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการการลา</div>
  <a href="supervisor_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
  <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> การลา</a>
  <a href="manage_users.php" class="menu-item"><i class="fas fa-user"></i> ผู้ใช้</a>
  <a href="manage_employees.php" class="menu-item active"><i class="fas fa-users"></i> พนักงาน</a>
  <!-- <a href="manage_leavetype.php" class="menu-item"><i class="fas fa-list"></i> ประเภทลา</a>
  <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a> -->
  <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
  <div class="header">
    <a href="manage_employees.php" class="btn" style="background:#777;"><i class="fas fa-arrow-left"></i> ย้อนกลับ</a>
    <div><?= htmlspecialchars($user_name) ?> (<?= $user_role ?>)</div>
  </div>

  <div class="card">
    <h1>จัดการยอดวันลา - <?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></h1>
    <?php if ($status_message): ?>
        <div class="<?= strpos($status_message,'✅')!==false ? 'alert-success':'alert-error' ?>">
            <?= $status_message ?>
        </div>
    <?php endif; ?>

    <table class="data-table">
      <thead><tr><th>ประเภทการลา</th><th>วันคงเหลือ</th><th>จัดการ</th></tr></thead>
      <tbody>
      <?php if ($balance_result->num_rows): while($r=$balance_result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($r['leave_type_name']) ?></td>
          <td><?= number_format($r['remaining_days'],0) ?></td>
          <td>
            <a class="action-btn edit" onclick="toggleEdit(<?= $r['balance_id'] ?>)"><i class="fas fa-edit"></i></a>
            <a href="?employee_id=<?= $target_employee_id ?>&action=delete&balance_id=<?= $r['balance_id'] ?>" 
               class="action-btn delete" onclick="return confirm('ยืนยันการลบรายการนี้?')"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <tr id="edit-<?= $r['balance_id'] ?>" style="display:none;">
          <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="balance_id" value="<?= $r['balance_id'] ?>">
            <td><?= htmlspecialchars($r['leave_type_name']) ?></td>
            <td><input type="number" name="new_remaining_days" class="form-control" min="0" value="<?= $r['remaining_days'] ?>"></td>
            <td><button class="btn">บันทึก</button></td>
          </form>
        </tr>
      <?php endwhile; else: ?>
        <tr><td colspan="3" align="center">ยังไม่มีข้อมูลยอดลา</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <form method="POST" class="form-row">
      <input type="hidden" name="action" value="add">
      <select name="leave_type_id" class="form-control" required>
        <option value="">เลือกประเภทลา</option>
        <?php while($t=$types->fetch_assoc()): ?>
          <option value="<?= $t['leave_type_id'] ?>"><?= htmlspecialchars($t['leave_type_name']) ?></option>
        <?php endwhile; ?>
      </select>
      <input type="number" name="remaining_days" class="form-control" min="0" value="0" required>
      <button class="btn"><i class="fas fa-plus"></i> เพิ่มยอดลา</button>
    </form>
  </div>
</div>

<script>
function toggleEdit(id){
  const el=document.getElementById('edit-'+id);
  el.style.display = el.style.display==='none'?'table-row':'none';
}
</script>
</body>
</html>
