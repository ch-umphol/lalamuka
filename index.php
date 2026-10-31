<?php
session_start();
require_once 'conn.php'; 

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // ปรับ Query ตามโครงสร้าง hotel.sql:
    // 1. เปลี่ยนจาก EMPLOYEE เป็น employees
    // 2. เปลี่ยนจาก USER_TYPE เป็น roles
    // 3. เชื่อมต่อโดยใช้ role_id
    $sql = "SELECT E.emp_id, E.password, R.role_name, R.role_display
            FROM employees E
            JOIN roles R ON E.role_id = R.role_id
            WHERE E.username = '$username'";
            
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // ตรวจสอบรหัสผ่านแบบ Plain Text ตามข้อมูลใน Dumping data
        if ($password === $user['password']) { 
            $_SESSION['loggedin'] = TRUE;
            $_SESSION['emp_id'] = $user['emp_id'];
            $_SESSION['username'] = $username;
            $_SESSION['user_role'] = $user['role_name'];

            // แยกเส้นทางตาม role_name ในฐานข้อมูล (admin, manager, supervisor, employee)
            switch ($_SESSION['user_role']) {
                case 'admin':
                    header("location: admin/admin_dashboard.php");
                    break;
                case 'manager':
                    header("location: manager/manager_dashboard.php");
                    break;
                case 'supervisor':
                    header("location: supervisor/supervisor_dashboard.php");
                    break;
                case 'employee':
                default:
                    header("location: employee/employee_dashboard.php");
                    break;
            }
            exit;
        } else {
            $error_message = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error_message = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ | LALA MUKHA</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* CSS คงเดิมตามที่คุณออกแบบไว้ทุกประการ */
:root { --primary: #004030; --secondary: #66BB6A; --bg: #f5f7fb; --text: #333; --base-scale: 1.0; }
body { margin: 0; height: 100vh; display: flex; font-family: 'Prompt', sans-serif; background: linear-gradient(120deg, rgba(0,64,48,0.95), rgba(102,187,106,0.9)), url('https://images.unsplash.com/photo-1603481589399-1f7c0a11c73f?auto=format&fit=crop&w=1400&q=80') no-repeat center/cover; align-items: center; justify-content: center; }
.login-card { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 40px 50px; max-width: 420px; width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.15); text-align: center; animation: fadeIn 0.7s ease; transform: scale(var(--base-scale)); transform-origin: center; transition: transform 0.2s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(15px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1.0); } }
.login-card img { width: 100px; margin-bottom: 15px; border-radius: 50%; border: 2px solid var(--secondary); padding: 5px; }
h2 { margin: 10px 0 25px; color: var(--primary); font-weight: 600; }
.form-group { margin-bottom: 20px; text-align: left; }
input[type="text"], input[type="password"] { width: 100%; padding: 14px; border: 1px solid #ccc; border-radius: 10px; font-size: 1em; font-family: 'Prompt', sans-serif; transition: all 0.3s; }
input:focus { border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(102,187,106,0.2); outline: none; }
button { width: 100%; background: var(--secondary); color: white; border: none; padding: 14px; font-size: 1.1em; font-weight: 600; border-radius: 10px; cursor: pointer; transition: all 0.3s; }
button:hover { background: #43A047; transform: scale(1.02); }
.error-message { background: #fdd; color: #b71c1c; border-left: 4px solid #f44336; padding: 10px; border-radius: 6px; margin-bottom: 20px; text-align: left; }
.forgot-password { margin-top: 10px; text-align: right; }
.forgot-password a { text-decoration: none; color: var(--primary); font-size: 0.9em; }
.forgot-password a:hover { text-decoration: underline; }
.footer { margin-top: 25px; font-size: 0.9em; color: #777; }
.zoom-controls { position: absolute; top: 20px; right: 20px; display: flex; gap: 5px; background: #fff; padding: 8px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.zoom-controls button { width: 35px; height: 35px; padding: 0; border-radius: 5px; background: var(--bg); color: var(--primary); font-size: 1.1em; border: 1px solid #ddd; transition: background 0.2s; }
.zoom-controls button:hover { background: #e0e0e0; transform: none; }
</style>
</head>
<body>
    <div class="zoom-controls">
        <button id="zoom-out" title="ลดขนาด"><i class="fas fa-search-minus"></i></button>
        <button id="zoom-in" title="เพิ่มขนาด"><i class="fas fa-search-plus"></i></button>
    </div>
    
    <div class="login-card">
      <img src="Image/logo.jpg" alt="Logo">
      <h2>เข้าสู่ระบบ</h2>

      <?php if (!empty($error_message)): ?>
        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <input type="text" name="username" placeholder="ชื่อผู้ใช้" required>
        </div>
        <div class="form-group">
          <input type="password" name="password" placeholder="รหัสผ่าน" required>
        </div>
        <button type="submit">เข้าสู่ระบบ</button>
      </form>

      <div class="forgot-password">
        <a href="#">ลืมรหัสผ่านใช่หรือไม่?</a>
      </div>

      <div class="footer">© 2025 LALA MUKHA Tented Resort Khao Yai</div>
    </div>

<script>
let currentScale = 1.0;
const scaleStep = 0.1;
const maxScale = 1.3;
const minScale = 0.8;
const loginCard = document.querySelector('.login-card');

function adjustZoom(direction) {
    if (direction === 'in' && currentScale < maxScale) {
        currentScale = Math.min(maxScale, currentScale + scaleStep);
    } else if (direction === 'out' && currentScale > minScale) {
        currentScale = Math.max(minScale, currentScale - scaleStep);
    }
    loginCard.style.transform = `scale(${currentScale})`; 
}

document.getElementById('zoom-in').addEventListener('click', () => adjustZoom('in'));
document.getElementById('zoom-out').addEventListener('click', () => adjustZoom('out'));
</script>
</body>
</html>