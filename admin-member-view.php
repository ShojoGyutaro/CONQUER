<?php
session_start();
require_once 'config/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$memberId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch member details
$member = null;
$gymMember = null;
$bookings = [];
$payments = [];

try {
    // Fetch user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'member'");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($member) {
        // Fetch gym_members details
        $stmt = $pdo->prepare("SELECT * FROM gym_members WHERE Email = ?");
        $stmt->execute([$member['email']]);
        $gymMember = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Fetch bookings
        $stmt = $pdo->prepare("
            SELECT b.*, c.class_name, c.schedule 
            FROM bookings b 
            JOIN classes c ON b.class_id = c.id 
            WHERE b.user_id = ? 
            ORDER BY b.booking_date DESC 
            LIMIT 5
        ");
        $stmt->execute([$memberId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch payments
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY payment_date DESC LIMIT 5");
        $stmt->execute([$memberId]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    error_log("Member view error: " . $e->getMessage());
}

if(!$member) {
    header('Location: admin-members.php');
    exit();
}

$adminName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($member['full_name']); ?> | CONQUER Gym Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f5f7fa;
            color: #333;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar - Same as before */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1a1f2e 0%, #2d3748 100%);
            color: white;
            position: fixed;
            height: 100vh;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .sidebar-logo i {
            color: #ff4757;
        }
        
        .admin-badge {
            background: #ff4757;
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-avatar.admin {
            background: #667eea;
        }
        
        .user-details h4 {
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        
        .user-details p {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 3px solid #ff4757;
        }
        
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 3px solid #ff4757;
        }
        
        .sidebar-nav a i {
            width: 20px;
            margin-right: 0.8rem;
        }
        
        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            position: absolute;
            bottom: 0;
            width: 100%;
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 2rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background: #e2e8f0;
            color: #4a5568;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        
        .back-btn:hover {
            background: #cbd5e0;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-warning {
            background: #ed8936;
            color: white;
        }
        
        .btn-warning:hover {
            background: #dd6b20;
        }
        
        .btn-danger {
            background: #e53e3e;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c53030;
        }
        
        /* Member Profile */
        .member-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .member-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-info h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }
        
        .member-status {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-inactive {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .info-item h4 {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }
        
        .info-item p {
            font-size: 1rem;
            color: #2d3748;
        }
        
        /* Details Sections */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .details-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .details-card h3 {
            margin-bottom: 1.5rem;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .details-card h3 i {
            color: #667eea;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            text-align: left;
            padding: 0.8rem;
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }
        
        table td {
            padding: 0.8rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-warning {
            background: #feebc8;
            color: #744210;
        }
        
        .badge-danger {
            background: #fed7d7;
            color: #742a2a;
        }
        
        /* Notes Section */
        .notes-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .notes-section h3 {
            margin-bottom: 1rem;
            color: #2d3748;
        }
        
        .notes-form textarea {
            width: 100%;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 1rem;
            font-family: inherit;
        }
        
        .notes-list {
            margin-top: 1.5rem;
        }
        
        .note-item {
            padding: 1rem;
            background: #f7fafc;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }
        
        .note-item:last-child {
            margin-bottom: 0;
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .note-author {
            font-weight: 600;
            color: #2d3748;
        }
        
        .note-date {
            font-size: 0.8rem;
            color: #718096;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .sidebar-logo span:not(:first-child),
            .sidebar .user-details,
            .sidebar-nav a span,
            .logout-btn span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 1rem;
            }
            
            .member-header {
                flex-direction: column;
                text-align: center;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-dumbbell"></i>
                    <span>CONQUER</span>
                    <span class="admin-badge">ADMIN</span>
                </div>
                <div class="user-profile">
                    <div class="user-avatar admin">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($adminName); ?></h4>
                        <p>System Administrator</p>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="admin-dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="admin-members.php" class="active">
                    <i class="fas fa-users"></i>
                    <span>Members</span>
                </a>
                <a href="admin-trainers.php">
                    <i class="fas fa-user-tie"></i>
                    <span>Trainers</span>
                </a>
                <a href="admin-classes.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Classes</span>
                </a>
                <a href="admin-payments.php">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
                <a href="admin-stories.php">
                    <i class="fas fa-trophy"></i>
                    <span>Success Stories</span>
                </a>
                <a href="admin-equipment.php">
                    <i class="fas fa-dumbbell"></i>
                    <span>Equipment</span>
                </a>
                <a href="admin-messages.php">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
                <a href="admin-reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="admin-settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <a href="admin-members.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Members
                </a>
                <div class="action-buttons">
                    <button class="btn btn-warning" onclick="window.location.href='admin-member-edit.php?id=<?php echo $memberId; ?>'">
                        <i class="fas fa-edit"></i>
                        Edit
                    </button>
                    <button class="btn btn-primary" onclick="sendReminder()">
                        <i class="fas fa-envelope"></i>
                        Send Reminder
                    </button>
                    <button class="btn btn-danger" onclick="confirmDeactivation()">
                        <i class="fas fa-user-slash"></i>
                        Deactivate
                    </button>
                </div>
            </div>
            
            <!-- Member Profile -->
            <div class="member-header">
                <div class="member-avatar">
                    <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                </div>
                <div class="member-info">
                    <h2><?php echo htmlspecialchars($member['full_name']); ?></h2>
                    <span class="member-status status-active">
                        <?php echo $member['is_active'] ? 'Active Member' : 'Inactive'; ?>
                    </span>
                    <p>Member since <?php echo date('F j, Y', strtotime($member['created_at'])); ?></p>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <h4>Email Address</h4>
                            <p><?php echo htmlspecialchars($member['email']); ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Username</h4>
                            <p><?php echo htmlspecialchars($member['username']); ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Last Login</h4>
                            <p><?php echo $member['last_login'] ? date('M j, Y g:i A', strtotime($member['last_login'])) : 'Never'; ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Account Status</h4>
                            <p><?php echo $member['is_active'] ? 'Active' : 'Suspended'; ?></p>
                        </div>
                    </div>
                    
                    <?php if($gymMember): ?>
                    <div class="info-grid" style="margin-top: 1.5rem;">
                        <div class="info-item">
                            <h4>Membership Plan</h4>
                            <p><?php echo htmlspecialchars($gymMember['MembershipPlan']); ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Contact Number</h4>
                            <p><?php echo htmlspecialchars($gymMember['ContactNumber']); ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Age</h4>
                            <p><?php echo htmlspecialchars($gymMember['Age']); ?> years</p>
                        </div>
                        <div class="info-item">
                            <h4>Gym Status</h4>
                            <p><?php echo htmlspecialchars($gymMember['MembershipStatus']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Details Grid -->
            <div class="details-grid">
                <!-- Recent Bookings -->
                <div class="details-card">
                    <h3><i class="fas fa-calendar-check"></i> Recent Bookings</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($bookings) > 0): ?>
                                    <?php foreach($bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['class_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($booking['schedule'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; padding: 2rem;">No bookings found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if(count($bookings) > 0): ?>
                        <div style="margin-top: 1rem; text-align: center;">
                            <a href="admin-bookings.php?member_id=<?php echo $memberId; ?>" style="color: #667eea; text-decoration: none;">View all bookings</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Payment History -->
                <div class="details-card">
                    <h3><i class="fas fa-credit-card"></i> Payment History</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($payments) > 0): ?>
                                    <?php foreach($payments as $payment): ?>
                                        <tr>
                                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; padding: 2rem;">No payments found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if(count($payments) > 0): ?>
                        <div style="margin-top: 1rem; text-align: center;">
                            <a href="admin-payments.php?member_id=<?php echo $memberId; ?>" style="color: #667eea; text-decoration: none;">View all payments</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Notes Section -->
            <div class="notes-section">
                <h3>Admin Notes</h3>
                <form class="notes-form" onsubmit="addNote(event)">
                    <textarea id="noteText" placeholder="Add a note about this member..."></textarea>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add Note
                    </button>
                </form>
                
                <div class="notes-list">
                    <!-- Sample notes - in real app, these would come from database -->
                    <div class="note-item">
                        <div class="note-header">
                            <span class="note-author">System Admin</span>
                            <span class="note-date">Today, 10:30 AM</span>
                        </div>
                        <p>Member inquired about personal training packages. Forwarded to trainer Mark.</p>
                    </div>
                    <div class="note-item">
                        <div class="note-header">
                            <span class="note-author">System Admin</span>
                            <span class="note-date">Yesterday, 3:45 PM</span>
                        </div>
                        <p>Payment reminder sent for monthly subscription renewal.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function sendReminder() {
            if(confirm('Send payment reminder to <?php echo htmlspecialchars($member['full_name']); ?>?')) {
                alert('Reminder email has been sent!');
                // In real app, you would make an AJAX call here
            }
        }
        
        function confirmDeactivation() {
            if(confirm('Are you sure you want to deactivate this member? They will lose access to their account.')) {
                if(confirm('This action cannot be undone. Proceed?')) {
                    window.location.href = 'admin-member-deactivate.php?id=<?php echo $memberId; ?>';
                }
            }
        }
        
        function addNote(event) {
            event.preventDefault();
            const noteText = document.getElementById('noteText').value.trim();
            
            if(noteText) {
                // In real app, you would send this to server via AJAX
                alert('Note added successfully!');
                document.getElementById('noteText').value = '';
                
                // Simulate adding note to list
                const notesList = document.querySelector('.notes-list');
                const newNote = document.createElement('div');
                newNote.className = 'note-item';
                newNote.innerHTML = `
                    <div class="note-header">
                        <span class="note-author">You</span>
                        <span class="note-date">Just now</span>
                    </div>
                    <p>${noteText}</p>
                `;
                notesList.insertBefore(newNote, notesList.firstChild);
            }
        }
    </script>
</body>
</html>