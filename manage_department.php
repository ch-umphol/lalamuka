<?php
session_start();
require_once 'conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['ผู้ดูแลระบบ', 'ผู้จัดการ'])) {
    // แก้ไข: ถ้าไม่ใช่ผู้ดูแลระบบ/ผู้จัดการ ให้กลับไปที่ Dashboard ของ Admin
    header("location: admin_dashboard.php");
    exit;
}

$current_user_id = $_SESSION['employee_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['username'];
$action = $_GET['action'] ?? 'read';
$dept_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// -----------------------------------------------------------------------------
// FETCH DROPDOWN (ดึงรายชื่อพนักงานทุกคนมาเป็นหัวหน้าแผนกได้)
// -----------------------------------------------------------------------------

// *** โค้ดที่แก้ไข: เพิ่มเงื่อนไขการดึงพนักงานตามบทบาท ***
$employee_where_clause = "";
if ($user_role === 'หัวหน้างาน') {
    // ถ้าเป็นหัวหน้างาน ให้ดึงเฉพาะพนักงานที่อยู่ภายใต้การดูแลของตัวเองเท่านั้น
    $employee_where_clause = "WHERE supervisor_id = $current_user_id OR employee_id = $current_user_id";
} else {
    // ถ้าเป็นผู้ดูแลระบบหรือผู้จัดการ ให้ดึงพนักงานทุกคน (เนื่องจากมีสิทธิ์จัดการโครงสร้างแผนกทั้งหมด)
    $employee_where_clause = ""; 
}

$employees_result = $conn->query("
    SELECT employee_id, first_name, last_name 
    FROM EMPLOYEE 
    $employee_where_clause
    ORDER BY first_name
");
// *****************************************************************************


// -----------------------------------------------------------------------------
// CREATE
// -----------------------------------------------------------------------------
if ($action === 'add' && $_SERVER["REQUEST_METHOD"] === "POST") {
    $dept_name = $conn->real_escape_string($_POST['department_name']);
    $dept_head_id = (int)$_POST['dept_head_id']; // รับ employee_id ของหัวหน้าแผนก

    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO Department_Type (department_name) VALUES ('$dept_name')";
        if (!$conn->query($sql)) { 
            if ($conn->errno == 1062) throw new Exception("ชื่อแผนกนี้มีอยู่แล้วในระบบ");
            else throw new Exception("Error saving Department: " . $conn->error);
        }
        $new_dept_id = $conn->insert_id; // ได้ ID แผนกใหม่

        // NEW: บันทึกหัวหน้าแผนก
        if ($dept_head_id > 0) {
            $conn->query("INSERT INTO Department_Head (department_id, employee_id) VALUES ($new_dept_id, $dept_head_id)");
        }
        
        $conn->commit();
        $_SESSION['status_message'] = "✅ เพิ่มแผนก **$dept_name** สำเร็จ!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ เพิ่มแผนกล้มเหลว: " . $e->getMessage();
    }
    header("location: manage_department.php");
    exit;
}

// -----------------------------------------------------------------------------
// READ / EDIT / DELETE
// -----------------------------------------------------------------------------
$dept_data = null;
$current_head_id = 0; // ตัวแปรเก็บ ID หัวหน้าแผนกปัจจุบัน
if ($dept_id > 0) {
    $result = $conn->query("SELECT * FROM Department_Type WHERE department_id = $dept_id");
    $dept_data = $result->num_rows ? $result->fetch_assoc() : null;

    // ดึง ID หัวหน้าแผนกปัจจุบัน
    $head_result = $conn->query("SELECT employee_id FROM Department_Head WHERE department_id = $dept_id");
    if ($head_result->num_rows) {
        $current_head_id = $head_result->fetch_assoc()['employee_id'];
    }
}

// UPDATE
if ($action === 'edit' && $_SERVER["REQUEST_METHOD"] === "POST") {
    $dept_name = $conn->real_escape_string($_POST['department_name']);
    $dept_head_id = (int)$_POST['dept_head_id']; // รับ employee_id ของหัวหน้าแผนก

    $conn->begin_transaction();
    try {
        $sql = "UPDATE Department_Type SET department_name='$dept_name' WHERE department_id=$dept_id";
        if (!$conn->query($sql)) { 
            if ($conn->errno == 1062) throw new Exception("ชื่อแผนกนี้มีอยู่แล้วในระบบ");
            else throw new Exception("Error updating Department: " . $conn->error);
        }
        
        // NEW: จัดการหัวหน้าแผนก (ลบของเก่าแล้วเพิ่มของใหม่)
        $conn->query("DELETE FROM Department_Head WHERE department_id=$dept_id");
        if ($dept_head_id > 0) {
            $conn->query("INSERT INTO Department_Head (department_id, employee_id) VALUES ($dept_id, $dept_head_id)");
        }
        
        $conn->commit();
        $_SESSION['status_message'] = "✅ แก้ไขแผนก **$dept_name** สำเร็จ!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ แก้ไขล้มเหลว: " . $e->getMessage();
    }
    header("location: manage_department.php");
    exit;
}

// DELETE
if ($action === 'delete') {
    $conn->begin_transaction();
    try {
        $emp_count = $conn->query("SELECT COUNT(*) AS total FROM EMPLOYEE WHERE department_id=$dept_id")->fetch_assoc()['total'];
        if ($emp_count > 0) throw new Exception("ไม่สามารถลบได้ เนื่องจากมีพนักงานในแผนกนี้ ($emp_count คน)");
        
        $conn->query("DELETE FROM Department_Head WHERE department_id=$dept_id");
        $conn->query("DELETE FROM Department_Type WHERE department_id=$dept_id");
        $conn->commit();
        $_SESSION['status_message'] = "✅ ลบแผนกสำเร็จ!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ ลบไม่สำเร็จ: " . $e->getMessage();
    }
    header("location: manage_department.php");
    exit;
}

// ดึงข้อมูลทั้งหมด (READ)
if ($action === 'read') {
    $departments_result = $conn->query("
        SELECT 
            DT.department_id, DT.department_name,
            E.first_name AS head_first, E.last_name AS head_last
        FROM Department_Type DT
        LEFT JOIN Department_Head DH ON DT.department_id = DH.department_id
        LEFT JOIN EMPLOYEE E ON DH.employee_id = E.employee_id
        ORDER BY DT.department_id ASC
    ");
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการแผนก | LALA MUKHA</title>
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
.header{display:flex;justify-content:space-between;align-items:center;background:#fff;padding:15px 25px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.05);margin-bottom:25px;}
.user-profile{display:flex;align-items:center;gap:10px;}
.card{background:rgba(255,255,255,0.9);border-radius:16px;padding:25px;box-shadow:0 6px 20px rgba(0,0,0,0.05);}
.card h1{margin-top:0;color:var(--primary);}
.data-table{width:100%;border-collapse:collapse;font-size:0.95em;}
.data-table th,.data-table td{padding:12px 15px;border-bottom:1px solid #eee;text-align:left;}
.data-table th{background-color:#f4f4f4;color:#333;font-weight:600;}
.btn-add,.btn-back{background:var(--secondary);color:#fff;padding:10px 15px;border-radius:6px;text-decoration:none;display:inline-block;margin-bottom:15px;}
.btn-back{background:#777;}
.btn-add:hover{background:#4caf50;}
.btn-submit{background:#17A2B8;color:#fff;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;}
.action-btn{display:inline-flex;justify-content:center;align-items:center;width:36px;height:36px;border-radius:50%;font-size:16px;transition:0.2s;margin-right:5px;border:2px solid;color:inherit;background:transparent;}
.action-edit{border-color:#17A2B8;color:#17A2B8;}
.action-edit:hover{background:#17A2B8;color:#fff;}
.action-delete{border-color:#c00;color:#c00;}
.action-delete:hover{background:#c00;color:#fff;}
.alert-success{color:#2e7d32;background:#dff0d8;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #66bb6a;}
.alert-error{color:#c00;background:#fdd;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #f44336;}
.form-group{margin-bottom:15px;}
.form-group label{font-weight:500;}
.form-group input, .form-group select{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;}
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการการลา</div>
    <a href="supervisor_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <a href="manage_users.php" class="menu-item"><i class="fas fa-user"></i> ข้อมูลผู้ใช้</a>
    <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <!-- <a href="manage_leavetype.php" class="menu-item"><i class="fas fa-list"></i> ประเภทการลา</a>
    <a href="manage_department.php" class="menu-item active"><i class="fas fa-building"></i> แผนก</a> -->
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
        <h1>จัดการแผนก</h1>
        <?php if($status_message): ?>
            <div class="<?= strpos($status_message,'✅')!==false?'alert-success':'alert-error' ?>">
                <?= $status_message ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'read'): ?>
            <a href="manage_department.php?action=add" class="btn-add"><i class="fas fa-plus"></i> เพิ่มแผนก</a>
            <table class="data-table">
                <thead>
                    <tr><th>ID</th><th>ชื่อแผนก</th><th>หัวหน้าแผนก</th><th>จัดการ</th></tr>
                </thead>
                <tbody>
                <?php if($departments_result && $departments_result->num_rows>0): 
                    while($row=$departments_result->fetch_assoc()):
                        $head = $row['head_first'] ? $row['head_first'].' '.$row['head_last'] : '-';
                ?>
                    <tr>
                        <td><?= $row['department_id'] ?></td>
                        <td><?= htmlspecialchars($row['department_name']) ?></td>
                        <td><?= htmlspecialchars($head) ?></td>
                        <td>
                            <a href="manage_department.php?action=edit&id=<?= $row['department_id'] ?>" class="action-btn action-edit"><i class="fas fa-edit"></i></a>
                            <a href="manage_department.php?action=delete&id=<?= $row['department_id'] ?>" onclick="return confirm('ยืนยันการลบแผนกนี้หรือไม่?')" class="action-btn action-delete"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="4" style="text-align:center;">ไม่มีข้อมูลแผนก</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($action === 'add'): 
            // ต้องรีเซ็ตตัวชี้ของพนักงานก่อนใช้
            if (isset($employees_result)) $employees_result->data_seek(0);
        ?>
            <a href="manage_department.php" class="btn-back"><i class="fas fa-arrow-left"></i> ย้อนกลับ</a>
            <h3>เพิ่มแผนกใหม่</h3>
            <form method="POST" action="manage_department.php?action=add">
                <div class="form-group">
                    <label>ชื่อแผนก</label>
                    <input type="text" name="department_name" required>
                </div>
                
                <div class="form-group">
                    <label>หัวหน้าแผนก/หัวหน้างาน</label>
                    <select name="dept_head_id">
                        <option value="0">--- ไม่มีหัวหน้าแผนก ---</option>
                        <?php while($emp = $employees_result->fetch_assoc()): ?>
                            <option value="<?= $emp['employee_id'] ?>">
                                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึก</button>
            </form>

        <?php elseif ($action === 'edit' && $dept_data): 
            // ต้องรีเซ็ตตัวชี้ของพนักงานก่อนใช้
            if (isset($employees_result)) $employees_result->data_seek(0);
        ?>
            <a href="manage_department.php" class="btn-back"><i class="fas fa-arrow-left"></i> ย้อนกลับ</a>
            <h3>แก้ไขแผนก: <?= htmlspecialchars($dept_data['department_name']) ?></h3>
            <form method="POST" action="manage_department.php?action=edit&id=<?= $dept_id ?>">
                <div class="form-group">
                    <label>ชื่อแผนก</label>
                    <input type="text" name="department_name" value="<?= htmlspecialchars($dept_data['department_name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>หัวหน้าแผนก/หัวหน้างาน</label>
                    <select name="dept_head_id">
                        <option value="0">--- ไม่มีหัวหน้าแผนก ---</option>
                        <?php while($emp = $employees_result->fetch_assoc()): ?>
                            <option value="<?= $emp['employee_id'] ?>" <?= $emp['employee_id'] == $current_head_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึก</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>