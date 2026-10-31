<?php
session_start();
require_once 'conn.php'; 

// ------------------- PHP LOGIC -------------------

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['ผู้ดูแลระบบ', 'ผู้จัดการ', 'หัวหน้างาน'])) {
    header("location: login.php");
    exit;
}

$current_user_id = $_SESSION['employee_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['username'];
$action = $_GET['action'] ?? 'read';
$holiday_id = (int)($_GET['id'] ?? 0);
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// กำหนดการแบ่งหน้า
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;


// ------------------ ADD / EDIT / DELETE LOGIC (Unchanged) ------------------

// *** DELETE HOLIDAY ***
if($action === 'delete' && $holiday_id > 0) {
    $holiday_data_old = $conn->query("SELECT holiday_name FROM public_holiday WHERE holiday_id=$holiday_id")->fetch_assoc();
    
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM public_holiday WHERE holiday_id=$holiday_id");
        $conn->commit();
        $_SESSION['status_message'] = "✅ ลบวันหยุด '{$holiday_data_old['holiday_name']}' เรียบร้อยแล้ว";
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาดในการลบ: ".$e->getMessage();
    }
    header("location: manage_holiday.php");
    exit;
}

// *** POST HANDLING for EDIT ***
if($action === 'edit' && $holiday_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $holiday_name = $conn->real_escape_string($_POST['holiday_name']);
    $holiday_date = $conn->real_escape_string($_POST['holiday_date']);
    
    $conn->begin_transaction();
    try {
        $sql = "UPDATE public_holiday SET holiday_name='$holiday_name', holiday_date='$holiday_date' WHERE holiday_id=$holiday_id";
        $conn->query($sql);
        $conn->commit();
        $_SESSION['status_message'] = "✅ แก้ไขวันหยุด '{$holiday_name}' สำเร็จ!";
        header("location: manage_holiday.php");
        exit;
    } catch(Exception $e) {
        $conn->rollback();
        $error_msg = (strpos($e->getMessage(), 'Duplicate entry') !== false ? 'วันที่นี้มีวันหยุดอื่นกำหนดไว้แล้ว' : $e->getMessage());
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: " . $error_msg;
    }
}

// *** POST HANDLING for ADD ***
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $holiday_name = $conn->real_escape_string($_POST['holiday_name']);
    $holiday_date = $conn->real_escape_string($_POST['holiday_date']);

    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO public_holiday (holiday_date, holiday_name) VALUES ('$holiday_date', '$holiday_name')";
        $conn->query($sql);
        $conn->commit();
        $_SESSION['status_message'] = "✅ เพิ่มวันหยุด '{$holiday_name}' สำเร็จ!";
        header("location: manage_holiday.php");
        exit;
    } catch(Exception $e) {
        $conn->rollback();
        $error_msg = (strpos($e->getMessage(), 'Duplicate entry') !== false ? 'วันที่นี้มีวันหยุดอื่นกำหนดไว้แล้ว' : $e->getMessage());
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: " . $error_msg;
    }
}


// ------------------ FETCH DATA FOR DISPLAY ------------------

// 1. นับจำนวนรายการทั้งหมดเพื่อคำนวณหน้า
$total_rows_result = $conn->query("SELECT COUNT(holiday_id) AS total FROM public_holiday");
$total_rows = $total_rows_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $items_per_page);

// 2. ดึงรายการวันหยุดสำหรับหน้าปัจจุบัน
$sql = "SELECT * FROM public_holiday ORDER BY holiday_date ASC LIMIT $items_per_page OFFSET $offset";
$holidays_result = $conn->query($sql);


// ------------------ FORM DISPLAY LOGIC (FOR MODAL/ADD) ------------------

// *** Logic พิเศษสำหรับดึง Form ด้วย Fetch API (สำหรับ EDIT) ***
if ($action === 'fetch_form' && $holiday_id > 0) {
    // ดึงข้อมูลวันหยุดสำหรับฟอร์มแก้ไข
    $holiday_data_form = $conn->query("SELECT holiday_id, holiday_date, holiday_name FROM public_holiday WHERE holiday_id=$holiday_id")->fetch_assoc();

    if (!$holiday_data_form) {
        http_response_code(404);
        echo "ไม่พบข้อมูลวันหยุด";
        exit;
    }
    
    // เริ่มสร้าง HTML Form สำหรับส่งกลับไปที่ Modal
    ?>
    <div class="modal-header">
        <h2><i class="fas fa-edit"></i> แก้ไขวันหยุด: <?= htmlspecialchars($holiday_data_form['holiday_name']) ?></h2>
        <button onclick="closeModal()">&times;</button>
    </div>
    <form method="POST" action="manage_holiday.php?action=edit&id=<?= $holiday_id ?>">
        
        <div class="form-group">
            <label>ชื่อวันหยุด</label>
            <input type="text" name="holiday_name" required value="<?= htmlspecialchars($holiday_data_form['holiday_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>วันที่</label>
            <input type="date" name="holiday_date" required value="<?= htmlspecialchars($holiday_data_form['holiday_date'] ?? '') ?>">
        </div>
        
        <div style="text-align: right; margin-top: 20px;">
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
        </div>
    </form>
    <?php
    exit; // สำคัญ: หยุดการทำงานของ PHP ที่นี่เพื่อส่ง Form HTML กลับไป
}
// *************************************************************************


// ปิดการเชื่อมต่อ DB ทันทีหลังดึงข้อมูลทั้งหมดเสร็จ
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการวันหยุด | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* Styles for Sidebar, Header, Alerts, and Action Buttons (Unchanged) */
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
    .search-box{background:#f7f7f7;border-radius:25px;padding:6px 15px;display:flex;align-items:center;box-shadow:inset 0 2px 4px rgba(0,0,0,0.05);}
    .search-box input{border:none;outline:none;padding-left:10px;font-size:1em;background:transparent;}
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
    .form-group input,.form-group select{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;font-family:'Prompt',sans-serif;}
    .btn-submit{background:#17A2B8;color:#fff;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;font-weight:500;}
    .btn-submit:hover{background:#117a8b;}
    .alert-success{color:#2e7d32;background:#dff0d8;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #66bb6a;}
    .alert-error{color:#c00;background:#fdd;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #f44336;}
    .action-btn {display:inline-flex;justify-content:center;align-items:center;width:36px;height:36px;border-radius:50%;text-decoration:none;font-size:16px;transition:0.2s;margin-right:5px;border:2px solid;color:inherit;background:transparent;}
    .action-edit {border-color:#17A2B8;color:#17A2B8;}
    .action-edit:hover {background:#17A2B8;color:#fff;}
    .action-delete {border-color:#c00;color:#c00;}
    .action-delete:hover {background:#c00;color:#fff;}
    .form-row { display: flex; gap: 20px; margin-bottom: 5px; }
    .form-col { flex: 1; }

    /* Pagination Styles */
    .pagination {
        margin-top: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
    }
    .pagination a, .pagination span {
        text-decoration: none;
        color: var(--primary);
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        transition: background-color 0.2s;
        font-size: 0.9em;
    }
    .pagination a:hover {
        background-color: #f0f0f0;
    }
    .pagination .active {
        background-color: var(--secondary);
        color: white;
        border-color: var(--secondary);
    }

    /* *** MODAL STYLES *** */
    .modal {
        display: none; 
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4); 
    }
    .modal-content {
        background-color: #fff;
        margin: 5% auto; 
        padding: 20px;
        border-radius: 12px;
        width: 80%;
        max-width: 500px; 
        box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19);
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    .modal-header h2 {
        margin: 0;
        color: var(--primary);
        font-size: 1.5em;
    }
    .modal-header button {
        background: none;
        border: none;
        font-size: 2em;
        cursor: pointer;
        color: #888;
    }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการ</div>
    <a href="manager_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <!-- <a href="manage_users.php" class="menu-item"><i class="fas fa-user"></i> ข้อมูลผู้ใช้</a> -->
    <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="manage_leavetype.php" class="menu-item"><i class="fas fa-list"></i> ประเภทการลา</a>
    <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a>
    <a href="manage_holiday.php" class="menu-item active"><i class="fas fa-calendar-alt"></i> วันหยุดพิเศษ</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div class="search-box"><i class="fas fa-search"></i><input type="text" placeholder="ค้นหา..."></div>
        <div class="user-profile"><span><?= htmlspecialchars($user_name) ?> (<?= $user_role ?>)</span><i class="fas fa-user-circle" style="font-size:1.8em;color:var(--primary)"></i></div>
    </div>

    <div class="card">
        <h1>จัดการวันหยุดพิเศษ</h1>

        <?php if($status_message): ?>
            <div class="<?= strpos($status_message,'✅')!==false?'alert-success':'alert-error' ?>">
                <?= $status_message ?>
            </div>
        <?php endif; ?>

        <?php if($action !== 'add'): ?>
            <a href="manage_holiday.php?action=add" class="btn-add"><i class="fas fa-plus"></i> เพิ่มวันหยุดใหม่</a>

            <p style="font-weight: 600; margin-top: 15px;">สรุป: มีวันหยุดพิเศษทั้งหมด <?= number_format($total_rows) ?> วัน</p>

            <?php if($holidays_result && $holidays_result->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>วันที่</th>
                        <th>ชื่อวันหยุด</th>
                        <th>สร้างเมื่อ</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $holidays_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['holiday_id'] ?></td>
                        <td><?= htmlspecialchars($row['holiday_date']) ?></td>
                        <td><?= htmlspecialchars($row['holiday_name']) ?></td>
                        <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
                        <td>
                            <a href="#" onclick="openEditModal(<?= $row['holiday_id'] ?>)" class="action-btn action-edit" title="แก้ไข">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="manage_holiday.php?action=delete&id=<?= $row['holiday_id'] ?>" class="action-btn action-delete" title="ลบ" onclick="return confirm('คุณต้องการลบวันหยุดนี้หรือไม่?');">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php if ($total_pages > 1): ?>
                    <?php if ($current_page > 1): ?>
                        <a href="manage_holiday.php?page=<?= $current_page - 1 ?>">&laquo; ก่อนหน้า</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="manage_holiday.php?page=<?= $i ?>" class="<?= ($i === $current_page) ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="manage_holiday.php?page=<?= $current_page + 1 ?>">ถัดไป &raquo;</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <p>ไม่พบรายการวันหยุดพิเศษ</p>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($action === 'add'): ?>
        <div class="card" style="margin-top:25px;">
            <h1><i class="fas fa-calendar-plus"></i> เพิ่มวันหยุดใหม่</h1>
            <form method="POST" action="manage_holiday.php?action=add">
                <div class="form-group">
                    <label>ชื่อวันหยุด</label>
                    <input type="text" name="holiday_name" required>
                </div>
                <div class="form-group">
                    <label>วันที่</label>
                    <input type="date" name="holiday_date" required>
                </div>
                
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกวันหยุด</button>
                <a href="manage_holiday.php" class="btn-submit" style="background:#888; margin-left: 10px;"><i class="fas fa-times"></i> ยกเลิก</a>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div id="editModal" class="modal" onclick="if(event.target.id === 'editModal') closeModal()">
        <div class="modal-content">
            <div id="modalFormContainer" style="text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: var(--primary);"></i>
                <p>กำลังโหลดฟอร์ม...</p>
            </div>
        </div>
    </div>

<script>
const editModal = document.getElementById('editModal');
const modalFormContainer = document.getElementById('modalFormContainer');

function openEditModal(holidayId) {
    editModal.style.display = 'block';
    
    // แสดง loading state
    modalFormContainer.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 2em; color: var(--primary);"></i><p>กำลังโหลดฟอร์ม...</p></div>';

    // ใช้ Fetch API เพื่อดึงเฉพาะ Form HTML
    fetch(`manage_holiday.php?action=fetch_form&id=${holidayId}`)
        .then(response => response.text())
        .then(html => {
            modalFormContainer.innerHTML = html;
        })
        .catch(error => {
            modalFormContainer.innerHTML = '<p style="color:red;">❌ โหลดฟอร์มไม่สำเร็จ</p>';
            console.error('Fetch error:', error);
        });
}

function closeModal() {
    editModal.style.display = 'none';
}

// ปิด Modal ด้วยปุ่ม Esc
document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") {
        closeModal();
    }
});
</script>
</body>
</html>