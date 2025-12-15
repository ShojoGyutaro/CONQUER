<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

// Initialize database connection
$pdo = null;
$error = '';
$user_id = $_SESSION['user_id'];

try {
    // Require the database class
    require_once 'config/database.php';
    
    // Get database instance
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if(!$user) {
        // User not found in database
        session_destroy();
        header('Location: login.php');
        exit();
    }
    
    // Check if user is active
    if(isset($user['is_active']) && !$user['is_active']) {
        session_destroy();
        header('Location: login.php?error=inactive');
        exit();
    }
    
    // Member info - check if gym_members table exists
    $member = null;
    try {
        $memberStmt = $pdo->prepare("SELECT * FROM gym_members WHERE Email = ?");
        $memberStmt->execute([$user['email']]);
        $member = $memberStmt->fetch();
    } catch(PDOException $e) {
        // Table might not exist, continue without member info
        error_log("Gym members table error: " . $e->getMessage());
    }
    
    // Upcoming classes - FIXED QUERY
    $upcomingClasses = [];
    try {
        $classesStmt = $pdo->prepare("
            SELECT c.*, u.full_name as trainer_name 
            FROM bookings b 
            JOIN classes c ON b.class_id = c.id 
            JOIN users u ON c.trainer_id = u.id 
            WHERE b.user_id = ? AND b.status IN ('confirmed', 'pending') 
            AND c.schedule > NOW() 
            ORDER BY c.schedule ASC 
            LIMIT 3
        ");
        $classesStmt->execute([$user_id]);
        $upcomingClasses = $classesStmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Classes query error: " . $e->getMessage());
        // Try alternative query if trainer join fails
        try {
            $classesStmt = $pdo->prepare("
                SELECT c.*, 'Trainer' as trainer_name 
                FROM bookings b 
                JOIN classes c ON b.class_id = c.id 
                WHERE b.user_id = ? AND b.status IN ('confirmed', 'pending')
                AND c.schedule > NOW() 
                ORDER BY c.schedule ASC 
                LIMIT 3
            ");
            $classesStmt->execute([$user_id]);
            $upcomingClasses = $classesStmt->fetchAll();
        } catch(PDOException $e2) {
            error_log("Alternative classes query error: " . $e2->getMessage());
        }
    }
    
    // Recent payments
    $recentPayments = [];
    try {
        $paymentsStmt = $pdo->prepare("
            SELECT * FROM payments 
            WHERE user_id = ? 
            ORDER BY payment_date DESC 
            LIMIT 5
        ");
        $paymentsStmt->execute([$user_id]);
        $recentPayments = $paymentsStmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Payments query error: " . $e->getMessage());
    }
    
    // Success stories count
    $storiesCount = 0;
    try {
        $storiesStmt = $pdo->prepare("SELECT COUNT(*) as count FROM success_stories WHERE user_id = ? AND approved = 1");
        $storiesStmt->execute([$user_id]);
        $result = $storiesStmt->fetch();
        $storiesCount = $result ? $result['count'] : 0;
    } catch(PDOException $e) {
        error_log("Success stories query error: " . $e->getMessage());
    }
    
    // Get actual notification count
    $notificationCount = 0;
    try {
        // Count upcoming classes in next 24 hours
        $notifStmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM bookings b 
            JOIN classes c ON b.class_id = c.id 
            WHERE b.user_id = ? 
            AND b.status = 'confirmed' 
            AND c.schedule BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ");
        $notifStmt->execute([$user_id]);
        $notifResult = $notifStmt->fetch();
        $notificationCount = $notifResult ? $notifResult['count'] : 0;
    } catch(PDOException $e) {
        error_log("Notification count error: " . $e->getMessage());
    }
    
} catch(PDOException $e) {
    $error = 'Database connection failed. Please try again later.';
    error_log("Dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    
    <style>
        /* Error message styling */
        .error-banner {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.5s ease;
        }
        
        .error-banner i {
            font-size: 1.5rem;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Additional fix for class items */
        .class-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            gap: 15px;
        }
        
        .class-item:last-child {
            border-bottom: none;
        }
        
        .class-time {
            min-width: 80px;
            text-align: center;
        }
        
        .class-details {
            flex: 1;
        }
        
        .class-tag {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .class-tag.yoga { background: #e6f7ff; color: #1890ff; }
        .class-tag.hiit { background: #fff7e6; color: #fa8c16; }
        .class-tag.strength { background: #f6ffed; color: #52c41a; }
        .class-tag.cardio { background: #fff0f6; color: #eb2f96; }
        .class-tag.crossfit { background: #f9f0ff; color: #722ed1; }
        .class-tag.others { background: #f0f0f0; color: #595959; }
        
        /* Fix for notification panel */
        .notification-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 380px;
            height: 100vh;
            background: white;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        [data-theme="dark"] .notification-panel {
            background: var(--dark-color);
        }
        
        .notification-panel.active {
            right: 0;
        }
        
        .notification-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--light-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-header h3 {
            margin: 0;
            color: var(--dark-color);
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close-btn:hover {
            background: var(--light-color);
            color: var(--dark-color);
        }
        
        .notification-list {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        
        .notification-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 0.75rem;
            background: var(--light-bg);
            transition: var(--transition);
        }
        
        .notification-item:hover {
            background: var(--light-color);
            transform: translateX(-5px);
        }
        
        .notification-item i {
            font-size: 1.25rem;
            margin-top: 0.25rem;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-content p {
            margin: 0 0 0.5rem 0;
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .notification-content span {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }
        
        .notification-overlay.active {
            display: block;
        }
        
        .text-primary { color: var(--primary-color); }
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-danger { color: var(--danger); }
    </style>
</head>
<body>
    <?php if($error): ?>
        <div class="error-banner">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <h3>System Error</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
                <p><small>Please contact support if this persists.</small></p>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-dumbbell"></i>
                <span>CONQUER</span>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo isset($user['full_name']) ? strtoupper(substr($user['full_name'], 0, 1)) : 'U'; ?>
                </div>
                <div class="user-details">
                    <h4><?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User'; ?></h4>
                    <p>Member</p>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="user-dashboard.php" class="active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="user-profile.php">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="user-classes.php">
                <i class="fas fa-calendar-alt"></i>
                <span>My Classes</span>
            </a>
            <a href="user-payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="user-stories.php">
                <i class="fas fa-trophy"></i>
                <span>Success Stories</span>
            </a>
            <a href="user-bookclass.php">
                <i class="fas fa-plus-circle"></i>
                <span>Book Class</span>
            </a>
            <a href="user-contact.php">
                <i class="fas fa-envelope"></i>
                <span>Support</span>
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
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification" id="notificationBtn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $notificationCount > 0 ? $notificationCount : '3'; ?></span>
                </button>
                <button class="btn-primary" onclick="window.location.href='user-bookclass.php'">
                    <i class="fas fa-plus"></i>
                    Book Class
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Welcome back, <?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'Member'; ?>! 💪</h1>
                    <p>Track your progress, book classes, and continue your fitness journey</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo isset($upcomingClasses) ? count($upcomingClasses) : 0; ?></h3>
                        <p>Upcoming Classes</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $storiesCount; ?></h3>
                        <p>Success Stories</p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Membership</h3>
                        <p><?php echo isset($member['MembershipPlan']) ? htmlspecialchars($member['MembershipPlan']) : 'Basic Plan'; ?></p>
                        <span class="status-badge active">
                            <?php echo isset($member['MembershipStatus']) ? htmlspecialchars($member['MembershipStatus']) : 'Active'; ?>
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Classes</h3>
                        <p><?php echo isset($upcomingClasses) ? count($upcomingClasses) : 0; ?> Booked</p>
                        <a href="user-classes.php">View All →</a>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Achievements</h3>
                        <p><?php echo $storiesCount; ?> Stories</p>
                        <a href="user-story.php">Share Story →</a>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Health Score</h3>
                        <p>85% Progress</p>
                        <div class="progress-bar">
                            <div class="progress" style="width: 85%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Upcoming Classes -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Upcoming Classes</h3>
                        <a href="user-classes.php">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(isset($upcomingClasses) && count($upcomingClasses) > 0): ?>
                            <?php foreach($upcomingClasses as $class): ?>
                                <div class="class-item">
                                    <div class="class-time">
                                        <h4><?php echo isset($class['schedule']) ? date('g:i A', strtotime($class['schedule'])) : 'N/A'; ?></h4>
                                        <p><?php echo isset($class['schedule']) ? date('M j', strtotime($class['schedule'])) : 'N/A'; ?></p>
                                    </div>
                                    <div class="class-details">
                                        <h4><?php echo isset($class['class_name']) ? htmlspecialchars($class['class_name']) : 'Class'; ?></h4>
                                        <p><i class="fas fa-user"></i> <?php echo isset($class['trainer_name']) ? htmlspecialchars($class['trainer_name']) : 'Trainer'; ?></p>
                                        <span class="class-tag <?php echo isset($class['class_type']) ? strtolower(preg_replace('/[^a-zA-Z]/', '', $class['class_type'])) : 'others'; ?>">
                                            <?php echo isset($class['class_type']) ? htmlspecialchars($class['class_type']) : 'General'; ?>
                                        </span>
                                    </div>
                                    <div class="class-actions">
                                        <button class="btn-sm" onclick="window.location.href='class-details.php?id=<?php echo isset($class['id']) ? $class['id'] : ''; ?>'">
                                            View
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state">No upcoming classes booked</p>
                            <button class="btn-primary" onclick="window.location.href='user-bookclass.php'">
                                <i class="fas fa-plus"></i>
                                Book Your First Class
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Recent Payments</h3>
                        <a href="user-payments.php">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(isset($recentPayments) && count($recentPayments) > 0): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recentPayments as $payment): ?>
                                            <tr>
                                                <td><?php echo isset($payment['payment_date']) ? date('M j, Y', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                                                <td>$<?php echo isset($payment['amount']) ? number_format($payment['amount'], 2) : '0.00'; ?></td>
                                                <td><?php echo isset($payment['payment_method']) ? htmlspecialchars($payment['payment_method']) : 'N/A'; ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo isset($payment['status']) ? strtolower($payment['status']) : 'pending'; ?>">
                                                        <?php echo isset($payment['status']) ? htmlspecialchars($payment['status']) : 'Pending'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="empty-state">No payment history</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions-grid">
                            <a href="user-bookclass.php" class="action-item">
                                <i class="fas fa-plus-circle"></i>
                                <span>Book Class</span>
                            </a>
                            <a href="user-profile.php" class="action-item">
                                <i class="fas fa-user-edit"></i>
                                <span>Edit Profile</span>
                            </a>
                            <a href="user-payments.php" class="action-item">
                                <i class="fas fa-credit-card"></i>
                                <span>Make Payment</span>
                            </a>
                            <a href="user-stories.php" class="action-item">
                                <i class="fas fa-trophy"></i>
                                <span>Share Story</span>
                            </a>
                            <a href="index.php#trainers" class="action-item">
                                <i class="fas fa-users"></i>
                                <span>Find Trainer</span>
                            </a>
                            <a href="user-contact.php" class="action-item">
                                <i class="fas fa-question-circle"></i>
                                <span>Get Help</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Progress Tracker -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Fitness Progress</h3>
                    </div>
                    <div class="card-body">
                        <div class="progress-tracker">
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Weight Goal</span>
                                    <span>75%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: 75%"></div>
                                </div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Strength</span>
                                    <span>60%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: 60%"></div>
                                </div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Cardio</span>
                                    <span>85%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: 85%"></div>
                                </div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Consistency</span>
                                    <span>90%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: 90%"></div>
                                </div>
                            </div>
                        </div>
                        <button class="btn-secondary" onclick="window.location.href='progress.php'">
                            View Detailed Progress
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Overlay -->
    <div class="notification-overlay" id="notificationOverlay"></div>

    <!-- Notification Panel -->
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button class="close-btn" id="closeNotifications">&times;</button>
        </div>
        <div class="notification-list">
            <?php if(count($upcomingClasses) > 0): ?>
                <?php foreach($upcomingClasses as $class): 
                    $classTime = strtotime($class['schedule']);
                    $currentTime = time();
                    $hoursUntil = round(($classTime - $currentTime) / 3600, 1);
                ?>
                    <div class="notification-item">
                        <i class="fas fa-calendar text-primary"></i>
                        <div class="notification-content">
                            <p>Class reminder: <?php echo htmlspecialchars($class['class_name']); ?> at <?php echo date('g:i A', $classTime); ?></p>
                            <span><?php echo $hoursUntil > 24 ? 'Tomorrow' : 'In ' . $hoursUntil . ' hours'; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if(count($recentPayments) > 0): ?>
                <?php 
                $latestPayment = $recentPayments[0];
                $paymentDate = strtotime($latestPayment['payment_date']);
                $daysAgo = round((time() - $paymentDate) / (3600 * 24), 0);
                ?>
                <div class="notification-item">
                    <i class="fas fa-credit-card text-success"></i>
                    <div class="notification-content">
                        <p>Payment of $<?php echo number_format($latestPayment['amount'], 2); ?> completed</p>
                        <span><?php echo $daysAgo == 0 ? 'Today' : ($daysAgo == 1 ? 'Yesterday' : $daysAgo . ' days ago'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="notification-item">
                <i class="fas fa-trophy text-warning"></i>
                <div class="notification-content">
                    <p>Welcome to CONQUER Gym! Start your fitness journey today.</p>
                    <span>Just now</span>
                </div>
            </div>
            
            <?php if($storiesCount == 0): ?>
                <div class="notification-item">
                    <i class="fas fa-star text-primary"></i>
                    <div class="notification-content">
                        <p>Share your success story and inspire others!</p>
                        <span>New</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="notification-item">
                <i class="fas fa-users text-success"></i>
                <div class="notification-content">
                    <p>Join our weekend group classes for extra motivation</p>
                    <span>Weekly reminder</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationPanel = document.getElementById('notificationPanel');
            const closeBtn = document.getElementById('closeNotifications');
            const notificationOverlay = document.getElementById('notificationOverlay');
            
            // Toggle notification panel
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationPanel.classList.toggle('active');
                    notificationOverlay.classList.toggle('active');
                });
            }
            
            // Close notification panel
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    notificationPanel.classList.remove('active');
                    notificationOverlay.classList.remove('active');
                });
            }
            
            // Close panel when clicking overlay
            if (notificationOverlay) {
                notificationOverlay.addEventListener('click', function() {
                    notificationPanel.classList.remove('active');
                    notificationOverlay.classList.remove('active');
                });
            }
            
            // Close panel when clicking outside
            document.addEventListener('click', function(event) {
                if (!notificationPanel.contains(event.target) && 
                    !notificationBtn.contains(event.target) &&
                    notificationPanel.classList.contains('active')) {
                    notificationPanel.classList.remove('active');
                    notificationOverlay.classList.remove('active');
                }
            });
            
            // Close panel with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && notificationPanel.classList.contains('active')) {
                    notificationPanel.classList.remove('active');
                    notificationOverlay.classList.remove('active');
                }
            });
            
            // Mark notifications as read when opening panel
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function() {
                    // In a real app, you would send an AJAX request to mark notifications as read
                    const badge = this.querySelector('.notification-badge');
                    if (badge && badge.textContent !== '0') {
                        badge.textContent = '0';
                        badge.style.display = 'none';
                    }
                });
            }
            
            // Auto-close notifications after 10 seconds if opened
            let notificationTimer;
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function() {
                    clearTimeout(notificationTimer);
                    notificationTimer = setTimeout(function() {
                        if (notificationPanel.classList.contains('active')) {
                            notificationPanel.classList.remove('active');
                            notificationOverlay.classList.remove('active');
                        }
                    }, 10000); // 10 seconds
                });
            }
        });
        
        // Alternative: Simple notification toggle function
        function toggleNotifications() {
            const panel = document.getElementById('notificationPanel');
            const overlay = document.getElementById('notificationOverlay');
            if (panel && overlay) {
                panel.classList.toggle('active');
                overlay.classList.toggle('active');
            }
        }
        
        function closeNotifications() {
            const panel = document.getElementById('notificationPanel');
            const overlay = document.getElementById('notificationOverlay');
            if (panel && overlay) {
                panel.classList.remove('active');
                overlay.classList.remove('active');
            }
        }
    </script>
</body>
</html>