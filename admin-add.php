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

$adminName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Administrator';

// Get stats from database - SAME AS DASHBOARD
try {
    // Total members
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE user_type = 'member'");
    $stmt->execute();
    $totalMembers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active classes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM classes WHERE status = 'active' AND schedule >= NOW()");
    $stmt->execute();
    $activeClasses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Monthly revenue
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) AND status = 'completed'");
    $stmt->execute();
    $monthlyRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Today's revenue
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) = CURDATE() AND status = 'completed'");
    $stmt->execute();
    $todayRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Equipment needing maintenance - SAME AS DASHBOARD
    $stmt = $pdo->prepare("
        SELECT * FROM equipment 
        WHERE status = 'maintenance' 
        OR next_maintenance <= DATE_ADD(NOW(), INTERVAL 7 DAY) 
        LIMIT 5
    ");
    $stmt->execute();
    $maintenanceNeeded = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pending success stories - SAME AS DASHBOARD
    $pendingStories = $pdo->query("SELECT COUNT(*) FROM success_stories WHERE approved = 0")->fetchColumn() ?: 0;
    
    // Unread messages - SAME AS DASHBOARD
    $unreadMessages = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn() ?: 0;
    
    // Total notifications - SAME CALCULATION AS DASHBOARD
    $totalNotifications = $pendingStories + count($maintenanceNeeded) + $unreadMessages;
    
} catch (PDOException $e) {
    // If queries fail, set defaults
    $totalMembers = 0;
    $activeClasses = 0;
    $monthlyRevenue = 0;
    $todayRevenue = 0;
    $maintenanceNeeded = [];
    $pendingStories = 0;
    $unreadMessages = 0;
    $totalNotifications = 0;
}

// Calculate growth percentages (dummy data for demo) - SAME AS DASHBOARD
$memberGrowth = $totalMembers > 10 ? '+12%' : '+0%';
$revenueGrowth = $monthlyRevenue > 1000 ? '+18%' : '+0%';
$classGrowth = $activeClasses > 5 ? '+8%' : '+0%';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New | CONQUER Gym Admin</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Add Page Specific Styles - Matching dashboard exactly */
        .add-options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .add-option-card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 1.75rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            cursor: pointer;
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 300px;
            position: relative;
            overflow: hidden;
        }
        
        .add-option-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
        }
        
        .add-option-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }
        
        .add-option-card:hover .add-option-icon {
            transform: scale(1.1);
        }
        
        .add-option-icon i {
            font-size: 1.5rem;
            color: white;
        }
        
        .add-option-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .add-option-card p {
            color: var(--text-light);
            margin-bottom: 1.5rem;
            line-height: 1.6;
            font-size: 0.9rem;
            flex: 1;
        }
        
        .add-option-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.9rem;
            width: fit-content;
        }
        
        .add-option-btn:hover {
            background: var(--primary-dark);
            transform: translateX(5px);
        }
        
        /* Different colors for each option card - matching dashboard color scheme */
        .add-option-card:nth-child(1) .add-option-icon {
            background: #3498db;
        }
        
        .add-option-card:nth-child(1) .add-option-btn {
            background: #3498db;
        }
        
        .add-option-card:nth-child(2) .add-option-icon {
            background: #2ecc71;
        }
        
        .add-option-card:nth-child(2) .add-option-btn {
            background: #2ecc71;
        }
        
        .add-option-card:nth-child(3) .add-option-icon {
            background: #f39c12;
        }
        
        .add-option-card:nth-child(3) .add-option-btn {
            background: #f39c12;
        }
        
        .add-option-card:nth-child(4) .add-option-icon {
            background: #e74c3c;
        }
        
        .add-option-card:nth-child(4) .add-option-btn {
            background: #e74c3c;
        }
        
        .add-option-card:nth-child(5) .add-option-icon {
            background: #9b59b6;
        }
        
        .add-option-card:nth-child(5) .add-option-btn {
            background: #9b59b6;
        }
        
        .add-option-card:nth-child(6) .add-option-icon {
            background: #1abc9c;
        }
        
        .add-option-card:nth-child(6) .add-option-btn {
            background: #1abc9c;
        }
        
        /* Page header matching dashboard */
        .page-header {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        .page-header h1 {
            font-size: 1.5rem;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header h1 i {
            color: var(--primary-color);
        }
        
        .page-description {
            color: var(--text-light);
            font-size: 0.9rem;
            max-width: 800px;
            line-height: 1.6;
        }
        
        /* Quick stats matching dashboard stats-grid */
        .add-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .add-stat-card {
            background: var(--white);
            padding: 1.25rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: var(--transition);
        }
        
        .add-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .add-stat-card h4 {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .add-stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .add-stat-card .stat-subtext {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        /* Notification Dropdown Styles - EXACTLY FROM DASHBOARD */
        .notification-dropdown {
            position: relative;
        }
        
        .notification-menu {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
            margin-top: 10px;
        }
        
        .notification-menu.active {
            display: block;
        }
        
        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-header h4 {
            margin: 0;
            font-size: 1rem;
        }
        
        .notification-body {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            gap: 0.8rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item.unread {
            background: #f0f7ff;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        
        .notification-icon.story { background: #f6ad55; }
        .notification-icon.equipment { background: #4fd1c5; }
        .notification-icon.message { background: #667eea; }
        
        .notification-content h5 {
            margin: 0 0 0.3rem 0;
            font-size: 0.9rem;
        }
        
        .notification-content p {
            margin: 0;
            font-size: 0.8rem;
            color: #666;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: #999;
            margin-top: 0.2rem;
        }
        
        .notification-footer {
            padding: 1rem;
            text-align: center;
            border-top: 1px solid #eee;
        }
        
        /* Update welcome banner - SAME AS DASHBOARD */
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .welcome-content .admin-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        /* Mobile responsive fixes - SAME AS DASHBOARD */
        @media (max-width: 768px) {
            .notification-menu {
                width: 300px;
                right: -50px;
            }
            
            .add-options-grid {
                grid-template-columns: 1fr;
            }
            
            .add-option-card {
                min-height: auto;
                padding: 1.5rem;
            }
            
            .page-header {
                padding: 1.25rem;
            }
            
            .page-header h1 {
                font-size: 1.3rem;
            }
            
            .add-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .add-stats {
                grid-template-columns: 1fr;
            }
        }
        
        /* Custom scrollbar - SAME AS DASHBOARD */
        .notification-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .notification-body::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .notification-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- Sidebar - EXACTLY AS IN DASHBOARD -->
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
                    <p>System Admin</p>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="admin-dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin-add.php" class="active">
                <i class="fas fa-plus-circle"></i>
                <span>Add New</span>
            </a>
            <a href="admin-members.php">
                <i class="fas fa-users"></i>
                <span>Members</span>
                <span class="nav-badge"><?php echo $totalMembers; ?></span>
            </a>
            <a href="admin-classes.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Classes</span>
                <span class="nav-badge"><?php echo $activeClasses; ?></span>
            </a>
            <a href="admin-payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="admin-stories.php">
                <i class="fas fa-trophy"></i>
                <span>Success Stories</span>
                <?php if($pendingStories > 0): ?>
                    <span class="nav-badge alert"><?php echo $pendingStories; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin-equipment.php">
                <i class="fas fa-dumbbell"></i>
                <span>Equipment</span>
                <?php if(count($maintenanceNeeded) > 0): ?>
                    <span class="nav-badge alert"><?php echo count($maintenanceNeeded); ?></span>
                <?php endif; ?>
            </a>
            <a href="admin-messages.php">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
                <?php if($unreadMessages > 0): ?>
                    <span class="nav-badge alert"><?php echo $unreadMessages; ?></span>
                <?php endif; ?>
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

    <!-- Main Content - EXACT STRUCTURE AS DASHBOARD -->
    <div class="main-content">
        <!-- Top Bar - EXACTLY AS IN DASHBOARD -->
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search members, classes, equipment...">
            </div>
            <div class="top-bar-actions">
                <!-- Notification Button with Dropdown - EXACTLY AS IN DASHBOARD -->
                <div class="notification-dropdown">
                    <button class="btn-notification">
                        <i class="fas fa-bell"></i>
                        <?php if($totalNotifications > 0): ?>
                            <span class="notification-badge"><?php echo $totalNotifications; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-menu">
                        <div class="notification-header">
                            <h4>Notifications (<?php echo $totalNotifications; ?>)</h4>
                            <a href="javascript:void(0)" class="mark-all-read" style="font-size: 0.85rem; color: #667eea;">Mark all as read</a>
                        </div>
                        <div class="notification-body">
                            <?php if($pendingStories > 0): ?>
                                <div class="notification-item unread">
                                    <div class="notification-icon story">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <div class="notification-content">
                                        <h5>Pending Success Stories</h5>
                                        <p><?php echo $pendingStories; ?> success stories awaiting approval</p>
                                        <div class="notification-time">Just now</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(count($maintenanceNeeded) > 0): ?>
                                <div class="notification-item unread">
                                    <div class="notification-icon equipment">
                                        <i class="fas fa-tools"></i>
                                    </div>
                                    <div class="notification-content">
                                        <h5>Maintenance Required</h5>
                                        <p><?php echo count($maintenanceNeeded); ?> equipment items need attention</p>
                                        <div class="notification-time">Today</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($unreadMessages > 0): ?>
                                <div class="notification-item unread">
                                    <div class="notification-icon message">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="notification-content">
                                        <h5>Unread Messages</h5>
                                        <p>You have <?php echo $unreadMessages; ?> unread contact messages</p>
                                        <div class="notification-time">Today</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($totalNotifications === 0): ?>
                                <div class="notification-item">
                                    <div class="notification-icon" style="background: #a0aec0;">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="notification-content">
                                        <h5>All caught up!</h5>
                                        <p>No new notifications</p>
                                        <div class="notification-time">Today</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="notification-footer">
                            <a href="admin-notifications.php" style="color: #667eea; font-size: 0.85rem;">View all notifications</a>
                        </div>
                    </div>
                </div>
                
                <button class="btn-primary" onclick="window.location.href='admin-add.php'">
                    <i class="fas fa-plus"></i>
                    Add New
                </button>
            </div>
        </div>

        <!-- Dashboard Content - Same container as dashboard -->
        <div class="dashboard-content">
            <!-- Welcome Banner - Matching dashboard style -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Add New <span class="admin-badge">ADMIN</span></h1>
                    <p>Welcome back, <?php echo htmlspecialchars($adminName); ?>! Quickly add new content to your gym management system.</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3>$<?php echo number_format($todayRevenue, 0); ?></h3>
                        <p>Today's Revenue</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $activeClasses; ?></h3>
                        <p>Active Classes</p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid - Matching dashboard stats grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalMembers; ?></h3>
                        <p>Total Members</p>
                        <span class="status-badge success"><?php echo $memberGrowth; ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($monthlyRevenue, 0); ?></h3>
                        <p>Monthly Revenue</p>
                        <span class="status-badge success"><?php echo $revenueGrowth; ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $activeClasses; ?></h3>
                        <p>Active Classes</p>
                        <span class="status-badge success"><?php echo $classGrowth; ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($maintenanceNeeded); ?></h3>
                        <p>Maintenance</p>
                        <?php if(count($maintenanceNeeded) > 0): ?>
                            <span class="status-badge pending">Attention</span>
                        <?php else: ?>
                            <span class="status-badge success">All Good</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- REMOVED: Page Header and description section -->
            
            <!-- Quick Stats -->
            <div class="add-stats">
                <div class="add-stat-card">
                    <h4>Total Members</h4>
                    <div class="stat-number"><?php echo number_format($totalMembers); ?></div>
                    <div class="stat-subtext">Active members in system</div>
                </div>
                <div class="add-stat-card">
                    <h4>Active Classes</h4>
                    <div class="stat-number"><?php echo number_format($activeClasses); ?></div>
                    <div class="stat-subtext">Currently scheduled</div>
                </div>
                <div class="add-stat-card">
                    <h4>Monthly Revenue</h4>
                    <div class="stat-number">$<?php echo number_format($monthlyRevenue, 2); ?></div>
                    <div class="stat-subtext">This month's total</div>
                </div>
                <div class="add-stat-card">
                    <h4>Maintenance Needed</h4>
                    <div class="stat-number"><?php echo number_format(count($maintenanceNeeded)); ?></div>
                    <div class="stat-subtext">Equipment requiring attention</div>
                </div>
            </div>

            <!-- Add Options Grid -->
            <div class="add-options-grid">
                <div class="add-option-card" onclick="navigateTo('admin-add-member.php')">
                    <div class="add-option-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Add New Member</h3>
                    <p>Register a new gym member with membership plan, contact details, health information, and account setup for portal access.</p>
                    <button class="add-option-btn">
                        <i class="fas fa-plus"></i>
                        Add Member
                    </button>
                </div>
                
                <div class="add-option-card" onclick="navigateTo('admin-add-trainer.php')">
                    <div class="add-option-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3>Add New Trainer</h3>
                    <p>Add a certified fitness trainer with specialty areas, certifications, experience, availability schedule, and contact information.</p>
                    <button class="add-option-btn">
                        <i class="fas fa-plus"></i>
                        Add Trainer
                    </button>
                </div>
                
                <div class="add-option-card" onclick="navigateTo('admin-add-class.php')">
                    <div class="add-option-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <h3>Create New Class</h3>
                    <p>Schedule a new fitness class with trainer assignment, timing, capacity limits, difficulty level, equipment requirements, and description.</p>
                    <button class="add-option-btn">
                        <i class="fas fa-plus"></i>
                        Create Class
                    </button>
                </div>
                
                <div class="add-option-card" onclick="navigateTo('admin-add-equipment.php')">
                    <div class="add-option-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h3>Add Equipment</h3>
                    <p>Add new gym equipment with purchase details, maintenance schedule, warranty information, location, and usage instructions.</p>
                    <button class="add-option-btn">
                        <i class="fas fa-plus"></i>
                        Add Equipment
                    </button>
                </div>
                
                <div class="add-option-card" onclick="navigateTo('admin-add-payment.php')">
                    <div class="add-option-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3>Record Payment</h3>
                    <p>Record member payments, subscription renewals, personal training fees, payment method details, and generate receipts.</p>
                    <button class="add-option-btn">
                        <i class="fas fa-plus"></i>
                        Record Payment
                    </button>
                </div>
                
                <div class="add-option-card" onclick="navigateTo('admin-add-story.php')">
                    <div class="add-option-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3>Add Success Story</h3>
                    <p>Add a member success story with before/after photos, weight loss/gain statistics, achievements, and inspirational quotes.</p>
                    <button class="add-option-btn">
                        <i class="fas fa-plus"></i>
                        Add Story
                    </button>
                </div>
            </div>
            
            <!-- REMOVED: Admin Actions / Quick Actions section -->
        </div>
    </div>

    <script>
        // Navigation function
        function navigateTo(url) {
            const button = event.currentTarget.querySelector('.add-option-btn');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            button.disabled = true;
            
            setTimeout(() => {
                window.location.href = url;
            }, 300);
            
            // Reset button after 2 seconds in case navigation fails
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 2000);
        }
        
        // Add hover effects to cards
        document.querySelectorAll('.add-option-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                const icon = this.querySelector('.add-option-icon');
                icon.style.transform = 'scale(1.1)';
            });
            
            card.addEventListener('mouseleave', function() {
                const icon = this.querySelector('.add-option-icon');
                icon.style.transform = 'scale(1)';
            });
        });
        
        // Notification Dropdown Functionality - EXACTLY AS IN DASHBOARD
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBtn = document.querySelector('.btn-notification');
            const notificationMenu = document.querySelector('.notification-menu');
            const markAllReadBtn = document.querySelector('.mark-all-read');
            
            // Toggle notification dropdown
            if(notificationBtn && notificationMenu) {
                notificationBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationMenu.classList.toggle('active');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if(!notificationMenu.contains(e.target) && !notificationBtn.contains(e.target)) {
                        notificationMenu.classList.remove('active');
                    }
                });
                
                // Mark all as read
                if(markAllReadBtn) {
                    markAllReadBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Remove unread class from all notifications
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                        
                        // Update notification badge
                        const badge = document.querySelector('.notification-badge');
                        if(badge) {
                            badge.remove();
                        }
                        
                        // Show success message
                        alert('All notifications marked as read');
                        
                        // Close dropdown
                        notificationMenu.classList.remove('active');
                    });
                }
            }
            
            // Make search bar functional - matching dashboard
            const searchInput = document.querySelector('.search-bar input');
            if(searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if(e.key === 'Enter') {
                        const searchTerm = this.value.trim();
                        if(searchTerm) {
                            alert('Searching for: ' + searchTerm);
                            // Implement actual search logic here
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>