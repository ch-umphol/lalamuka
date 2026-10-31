<?php
session_start();
require_once 'conn.php'; 

// Check user role: Must be an Employee
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'พนักงาน') {
    header("location: login.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$user_display_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : $_SESSION['username'];

// Fetch all notifications for the current employee
$notifications = [];
$sql_notif = "
    SELECT 
        notification_id,
        message,
        timestamp,
        is_read,
        request_id
    FROM 
        NOTIFICATION
    WHERE 
        recipient_id = $employee_id
    ORDER BY 
        timestamp DESC";

$result_notif = $conn->query($sql_notif);
if ($result_notif) {
    while($row = $result_notif->fetch_assoc()) {
        $notifications[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การแจ้งเตือน | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* (CSS พื้นฐาน Sidebar/Layout เหมือนเดิม) */
        :root { --primary-dark: #1A5243; --secondary-green: #38761D; --highlight-green: #6AA84F; --kanit-font: 'Kanit', sans-serif; --bg-color: #F7F9FC; --text-dark: #333; }
        body { font-family: var(--kanit-font); margin: 0; padding: 0; background-color: var(--bg-color); display: flex; color: var(--text-dark); }
        .sidebar { width: 250px; background-color: var(--primary-dark); color: white; height: 100vh; position: fixed; padding: 20px 0; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); }
        .sidebar-header { text-align: center; padding: 10px 0 30px 0; font-size: 1.1em; font-weight: 500; }
        .menu-item { padding: 15px 20px; display: flex; align-items: center; text-decoration: none; color: rgba(255, 255, 255, 0.8); transition: background-color 0.2s, color 0.2s; }
        .menu-item i { margin-right: 10px; font-size: 1.1em; }
        .menu-item:hover { background-color: #103830; color: white; }
        .menu-item[href="notifications.php"] { background-color: var(--highlight-green); color: white; font-weight: 500; }
        
        .main-content { margin-left: 250px; flex-grow: 1; padding: 30px; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); }
        .card h1 { color: var(--primary-dark); font-size: 1.8em; margin-top: 0; margin-bottom: 20px; font-weight: 600; }
        .header { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 20px; }
        .user-profile { display: flex; align-items: center; }

        /* Notification List Styles */
        .notification-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            background-color: white;
            transition: background-color 0.2s;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item.unread {
            background-color: #e6f9ff; /* Light blue for unread */
            border-left: 4px solid #17A2B8;
            font-weight: 500;
        }

        .notif-icon {
            font-size: 1.5em;
            color: var(--primary-dark);
            margin-right: 15px;
        }
        
        .notif-body {
            flex-grow: 1;
        }

        .notif-time {
            font-size: 0.85em;
            color: #777;
            text-align: right;
            min-width: 150px;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">Lala Mukha Tented Khao Yai<br>ระบบจัดการการลา</div>
        <a href="employee_dashboard.php" class="menu-item"><i class="fas fa-home"></i> Dashboard</a>
        <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องขอลางาน</a>
        <a href="leave_history.php" class="menu-item"><i class="fas fa-list-alt"></i> ตรวจสอบประวัติการลา</a>
        <a href="notifications.php" class="menu-item active"><i class="fas fa-bell"></i> การแจ้งเตือน</a>
        <a href="logout.php" class="menu-item" style="position: absolute; bottom: 0; width: 100%; box-sizing: border-box; background-color: #38761D;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="user-profile">
                <span style="margin-right: 15px; font-weight: 500;"><?php echo htmlspecialchars($user_display_name); ?> (พนักงาน)</span>
                <i class="fas fa-user-circle" style="font-size: 2em; color: var(--secondary-green);"></i>
            </div>
        </div>
        
        <div class="card">
            <h1><i class="fas fa-bell"></i> การแจ้งเตือน</h1>
            
            <div class="notification-list">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notif): 
                        $is_read_class = $notif['is_read'] ? '' : 'unread';
                        // Determine icon based on message content
                        $icon_class = 'fas fa-envelope';
                        if (strpos($notif['message'], 'อนุมัติ') !== false) {
                            $icon_class = 'fas fa-check-circle';
                        } elseif (strpos($notif['message'], 'ปฏิเสธ') !== false) {
                            $icon_class = 'fas fa-times-circle';
                        } elseif (strpos($notif['message'], 'ยกเลิก') !== false) {
                            $icon_class = 'fas fa-trash-alt';
                        }
                    ?>
                        <div class="notification-item <?php echo $is_read_class; ?>" 
                             onclick="markAsRead(<?php echo $notif['notification_id']; ?>)" 
                             style="cursor: pointer;">
                            
                            <i class="<?php echo $icon_class; ?> notif-icon"></i>
                            
                            <div class="notif-body">
                                <strong><?php echo htmlspecialchars($notif['message']); ?></strong>
                                <br>
                                <?php if ($notif['request_id']): ?>
                                    <span style="font-size: 0.9em; color:#555;">(คำขอลา ID: <?php echo $notif['request_id']; ?>)</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notif-time">
                                <?php echo date('d/m/Y H:i', strtotime($notif['timestamp'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #777;">ไม่มีการแจ้งเตือนใหม่</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function markAsRead(notificationId) {
            // **ในระบบจริง: ส่ง AJAX Request ไปยังไฟล์ PHP (เช่น mark_read.php) เพื่ออัปเดต is_read = 1**
            
            console.log(`Notification ID ${notificationId} marked as read.`);
            
            // Temporary visual update (for demonstration only)
            const item = document.querySelector(`.notification-item[onclick*='${notificationId}']`);
            if (item) {
                item.classList.remove('unread');
                item.style.borderLeft = 'none';
                item.style.fontWeight = '400';
            }
            
            // Example of how the AJAX call would look:
            // fetch(`mark_read.php?id=${notificationId}`)
            //     .then(response => {
            //         if (response.ok) {
            //             // Update visual status here
            //         }
            //     });
        }
    </script>
</body>
</html>