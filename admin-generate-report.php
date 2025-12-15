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

// Get report parameters
$reportType = $_GET['type'] ?? 'membership';
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$format = $_GET['format'] ?? 'html';

// Initialize report data
$reportData = [];
$reportTitle = '';
$reportHeaders = [];
$reportRows = [];
$reportSummary = [];

// Generate report based on type
try {
    switch($reportType) {
        case 'membership':
            $reportTitle = 'Membership Report';
            $reportHeaders = ['ID', 'Name', 'Email', 'Plan', 'Status', 'Join Date'];
            
            $stmt = $pdo->prepare("
                SELECT gm.ID, gm.Name, gm.Email, gm.MembershipPlan, 
                       gm.MembershipStatus, gm.JoinDate
                FROM gym_members gm
                WHERE DATE(gm.JoinDate) BETWEEN ? AND ?
                ORDER BY gm.JoinDate DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $reportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Summary
            $summaryStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_members,
                    SUM(CASE WHEN MembershipStatus = 'Active' THEN 1 ELSE 0 END) as active_members,
                    SUM(CASE WHEN MembershipStatus = 'Inactive' THEN 1 ELSE 0 END) as inactive_members,
                    COUNT(DISTINCT MembershipPlan) as total_plans
                FROM gym_members
                WHERE DATE(JoinDate) BETWEEN ? AND ?
            ");
            $summaryStmt->execute([$startDate, $endDate]);
            $reportSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'revenue':
            $reportTitle = 'Revenue Report';
            $reportHeaders = ['ID', 'Member', 'Amount', 'Method', 'Status', 'Date'];
            
            $stmt = $pdo->prepare("
                SELECT p.id, u.full_name as member, p.amount, p.payment_method, 
                       p.status, p.payment_date
                FROM payments p
                JOIN users u ON p.user_id = u.id
                WHERE DATE(p.payment_date) BETWEEN ? AND ?
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $reportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Summary
            $summaryStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_payments,
                    SUM(amount) as total_revenue,
                    AVG(amount) as average_payment,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_revenue
                FROM payments
                WHERE DATE(payment_date) BETWEEN ? AND ?
            ");
            $summaryStmt->execute([$startDate, $endDate]);
            $reportSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'attendance':
            $reportTitle = 'Class Attendance Report';
            $reportHeaders = ['Class', 'Trainer', 'Date', 'Capacity', 'Enrolled', 'Attendance Rate'];
            
            $stmt = $pdo->prepare("
                SELECT 
                    c.class_name,
                    COALESCE(u.full_name, 'No Trainer') as trainer,
                    DATE(c.schedule) as class_date,
                    c.max_capacity,
                    c.current_enrollment,
                    ROUND((c.current_enrollment * 100.0 / c.max_capacity), 1) as attendance_rate
                FROM classes c
                LEFT JOIN trainers t ON c.trainer_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE DATE(c.schedule) BETWEEN ? AND ?
                ORDER BY c.schedule DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $reportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Summary
            $summaryStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_classes,
                    SUM(max_capacity) as total_capacity,
                    SUM(current_enrollment) as total_enrollment,
                    ROUND(AVG(current_enrollment * 100.0 / max_capacity), 1) as avg_attendance
                FROM classes
                WHERE DATE(schedule) BETWEEN ? AND ?
            ");
            $summaryStmt->execute([$startDate, $endDate]);
            $reportSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'equipment':
            $reportTitle = 'Equipment Maintenance Report';
            $reportHeaders = ['Equipment', 'Brand', 'Location', 'Last Maintenance', 'Next Maintenance', 'Status'];
            
            $stmt = $pdo->prepare("
                SELECT 
                    equipment_name, brand, location, 
                    last_maintenance, next_maintenance, status
                FROM equipment
                WHERE status != 'retired'
                ORDER BY next_maintenance ASC
            ");
            $stmt->execute();
            $reportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Summary
            $summaryStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_equipment,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_equipment,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_equipment,
                    SUM(CASE WHEN next_maintenance <= CURDATE() THEN 1 ELSE 0 END) as overdue_maintenance
                FROM equipment
                WHERE status != 'retired'
            ");
            $summaryStmt->execute();
            $reportSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        default:
            $reportTitle = 'Membership Report';
            $reportHeaders = ['ID', 'Name', 'Email', 'Plan', 'Status', 'Join Date'];
            
            $stmt = $pdo->prepare("
                SELECT gm.ID, gm.Name, gm.Email, gm.MembershipPlan, 
                       gm.MembershipStatus, gm.JoinDate
                FROM gym_members gm
                ORDER BY gm.JoinDate DESC
                LIMIT 50
            ");
            $stmt->execute();
            $reportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch(PDOException $e) {
    error_log("Report generation error: " . $e->getMessage());
}

// Export to different formats
if(isset($_GET['export']) && $format !== 'html') {
    if($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report_' . $reportType . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, $reportHeaders);
        
        // Add data
        foreach($reportRows as $row) {
            fputcsv($output, array_values($row));
        }
        
        fclose($output);
        exit;
    } elseif($format === 'pdf') {
        // For PDF, we would use a library like TCPDF or mPDF
        // This is a simplified version
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="report_' . $reportType . '_' . date('Y-m-d') . '.pdf"');
        
        // In a real implementation, you would generate PDF here
        echo "PDF generation would go here. Install a PDF library for actual PDF generation.";
        exit;
    }
}

$adminName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports | CONQUER Gym Admin</title>
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
        }
        
        /* Report Controls */
        .report-controls {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .controls-header {
            margin-bottom: 1.5rem;
        }
        
        .controls-header h2 {
            color: #2d3748;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .control-group {
            display: flex;
            flex-direction: column;
        }
        
        .control-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }
        
        .control-group select,
        .control-group input {
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .control-group input:focus,
        .control-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .control-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
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
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .btn-success {
            background: #38a169;
            color: white;
        }
        
        .btn-success:hover {
            background: #2f855a;
        }
        
        .btn-warning {
            background: #d69e2e;
            color: white;
        }
        
        .btn-warning:hover {
            background: #b7791f;
        }
        
        /* Report Type Cards */
        .report-type-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .report-type-card {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .report-type-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .report-type-card.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .report-type-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #667eea;
        }
        
        .report-type-name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .report-type-desc {
            font-size: 0.85rem;
            color: #718096;
        }
        
        /* Report Summary */
        .report-summary {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .summary-header h3 {
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .summary-stat {
            text-align: center;
            padding: 1rem;
            background: #f7fafc;
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #718096;
        }
        
        /* Report Table */
        .report-table {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .table-header h3 {
            color: #2d3748;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        table th {
            text-align: left;
            padding: 1rem;
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }
        
        table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        table tr:hover {
            background: #f7fafc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-inactive {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .status-pending {
            background: #feebc8;
            color: #744210;
        }
        
        .status-maintenance {
            background: #bee3f8;
            color: #2c5282;
        }
        
        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 400px;
        }
        
        .chart-header {
            margin-bottom: 1rem;
        }
        
        .chart-header h3 {
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            
            .controls-grid {
                grid-template-columns: 1fr;
            }
            
            .report-type-cards {
                grid-template-columns: 1fr;
            }
            
            .control-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <!-- Chart.js for visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="admin-members.php">
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
                <a href="admin-reports.php" class="active">
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
                <a href="admin-dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
            
            <!-- Report Controls -->
            <div class="report-controls">
                <div class="controls-header">
                    <h2><i class="fas fa-chart-bar"></i> Generate Report</h2>
                    <p style="color: #718096;">Select report type, date range, and format</p>
                </div>
                
                <form method="GET" id="reportForm">
                    <input type="hidden" name="type" id="reportType" value="<?php echo htmlspecialchars($reportType); ?>">
                    
                    <div class="report-type-cards">
                        <div class="report-type-card <?php echo $reportType === 'membership' ? 'selected' : ''; ?>" data-type="membership">
                            <div class="report-type-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="report-type-name">Membership</div>
                            <div class="report-type-desc">Member registrations and status</div>
                        </div>
                        <div class="report-type-card <?php echo $reportType === 'revenue' ? 'selected' : ''; ?>" data-type="revenue">
                            <div class="report-type-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="report-type-name">Revenue</div>
                            <div class="report-type-desc">Payment history and income</div>
                        </div>
                        <div class="report-type-card <?php echo $reportType === 'attendance' ? 'selected' : ''; ?>" data-type="attendance">
                            <div class="report-type-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="report-type-name">Attendance</div>
                            <div class="report-type-desc">Class participation rates</div>
                        </div>
                        <div class="report-type-card <?php echo $reportType === 'equipment' ? 'selected' : ''; ?>" data-type="equipment">
                            <div class="report-type-icon">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <div class="report-type-name">Equipment</div>
                            <div class="report-type-desc">Maintenance and status</div>
                        </div>
                    </div>
                    
                    <div class="controls-grid">
                        <div class="control-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="control-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <div class="control-group">
                            <label for="format">Export Format</label>
                            <select id="format" name="format">
                                <option value="html" <?php echo $format === 'html' ? 'selected' : ''; ?>>Web View (HTML)</option>
                                <option value="csv" <?php echo $format === 'csv' ? 'selected' : ''; ?>>Spreadsheet (CSV)</option>
                                <option value="pdf" <?php echo $format === 'pdf' ? 'selected' : ''; ?>>Document (PDF)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="control-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i>
                            Generate Report
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportReport()">
                            <i class="fas fa-download"></i>
                            Export Report
                        </button>
                        <button type="button" class="btn btn-warning" onclick="printReport()">
                            <i class="fas fa-print"></i>
                            Print Report
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='admin-dashboard.php'">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Report Summary -->
            <?php if(!empty($reportSummary)): ?>
            <div class="report-summary">
                <div class="summary-header">
                    <h3><i class="fas fa-chart-pie"></i> Report Summary</h3>
                    <div style="color: #718096; font-size: 0.9rem;">
                        <?php echo date('F j, Y', strtotime($startDate)); ?> to <?php echo date('F j, Y', strtotime($endDate)); ?>
                    </div>
                </div>
                <div class="summary-stats">
                    <?php foreach($reportSummary as $key => $value): ?>
                        <div class="summary-stat">
                            <div class="stat-value">
                                <?php 
                                if(is_numeric($value)) {
                                    if(strpos($key, 'revenue') !== false || strpos($key, 'amount') !== false) {
                                        echo '$' . number_format($value, 2);
                                    } elseif(strpos($key, 'rate') !== false || strpos($key, 'avg') !== false) {
                                        echo number_format($value, 1) . '%';
                                    } else {
                                        echo number_format($value);
                                    }
                                } else {
                                    echo htmlspecialchars($value);
                                }
                                ?>
                            </div>
                            <div class="stat-label">
                                <?php 
                                $labels = [
                                    'total_members' => 'Total Members',
                                    'active_members' => 'Active Members',
                                    'inactive_members' => 'Inactive Members',
                                    'total_plans' => 'Membership Plans',
                                    'total_payments' => 'Total Payments',
                                    'total_revenue' => 'Total Revenue',
                                    'average_payment' => 'Average Payment',
                                    'completed_revenue' => 'Completed Revenue',
                                    'total_classes' => 'Total Classes',
                                    'total_capacity' => 'Total Capacity',
                                    'total_enrollment' => 'Total Enrollment',
                                    'avg_attendance' => 'Avg Attendance',
                                    'total_equipment' => 'Total Equipment',
                                    'active_equipment' => 'Active Equipment',
                                    'maintenance_equipment' => 'In Maintenance',
                                    'overdue_maintenance' => 'Overdue Maintenance'
                                ];
                                echo $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Chart Container -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Visualization</h3>
                </div>
                <canvas id="reportChart"></canvas>
            </div>
            
            <!-- Report Table -->
            <div class="report-table">
                <div class="table-header">
                    <h3><?php echo htmlspecialchars($reportTitle); ?></h3>
                    <div style="color: #718096; font-size: 0.9rem;">
                        Showing <?php echo count($reportRows); ?> records
                    </div>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach($reportHeaders as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($reportRows)): ?>
                                <?php foreach($reportRows as $row): ?>
                                    <tr>
                                        <?php foreach($row as $key => $value): ?>
                                            <td>
                                                <?php 
                                                if($key === 'MembershipStatus' || $key === 'status') {
                                                    $statusClass = strtolower($value);
                                                    if($statusClass === 'active') {
                                                        echo '<span class="status-badge status-active">' . htmlspecialchars($value) . '</span>';
                                                    } elseif($statusClass === 'inactive' || $statusClass === 'failed') {
                                                        echo '<span class="status-badge status-inactive">' . htmlspecialchars($value) . '</span>';
                                                    } elseif($statusClass === 'pending') {
                                                        echo '<span class="status-badge status-pending">' . htmlspecialchars($value) . '</span>';
                                                    } elseif($statusClass === 'maintenance') {
                                                        echo '<span class="status-badge status-maintenance">' . htmlspecialchars($value) . '</span>';
                                                    } else {
                                                        echo htmlspecialchars($value);
                                                    }
                                                } elseif($key === 'amount') {
                                                    echo '$' . number_format($value, 2);
                                                } elseif($key === 'attendance_rate') {
                                                    echo number_format($value, 1) . '%';
                                                } elseif(strpos($key, 'date') !== false || strpos($key, 'Date') !== false) {
                                                    echo date('M j, Y', strtotime($value));
                                                } else {
                                                    echo htmlspecialchars($value);
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($reportHeaders); ?>" style="text-align: center; padding: 3rem;">
                                        <i class="fas fa-inbox" style="font-size: 3rem; color: #cbd5e0; margin-bottom: 1rem;"></i>
                                        <div style="color: #718096;">No data found for the selected criteria</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Report type selection
        document.querySelectorAll('.report-type-card').forEach(card => {
            card.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                document.getElementById('reportType').value = type;
                
                // Update UI
                document.querySelectorAll('.report-type-card').forEach(c => {
                    c.classList.remove('selected');
                });
                this.classList.add('selected');
                
                // Update date range suggestions based on report type
                updateDateRange(type);
            });
        });
        
        // Update date range based on report type
        function updateDateRange(type) {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const today = new Date();
            
            switch(type) {
                case 'revenue':
                    // Last 30 days for revenue
                    const lastMonth = new Date(today);
                    lastMonth.setDate(today.getDate() - 30);
                    startDateInput.value = lastMonth.toISOString().split('T')[0];
                    endDateInput.value = today.toISOString().split('T')[0];
                    break;
                    
                case 'attendance':
                    // Current month for attendance
                    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                    startDateInput.value = firstDay.toISOString().split('T')[0];
                    endDateInput.value = today.toISOString().split('T')[0];
                    break;
                    
                case 'equipment':
                    // All time for equipment (empty dates)
                    startDateInput.value = '';
                    endDateInput.value = '';
                    break;
                    
                default:
                    // Current month for membership
                    const firstDayMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                    startDateInput.value = firstDayMonth.toISOString().split('T')[0];
                    endDateInput.value = today.toISOString().split('T')[0];
                    break;
            }
        }
        
        // Export report
        function exportReport() {
            const format = document.getElementById('format').value;
            const type = document.getElementById('reportType').value;
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if(format === 'html') {
                alert('Use "Generate Report" for HTML view');
                return;
            }
            
            // Show loading
            const exportBtn = document.querySelector('.btn-success');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            exportBtn.disabled = true;
            
            // Create export URL
            const url = `admin-generate-report.php?type=${type}&start_date=${startDate}&end_date=${endDate}&format=${format}&export=1`;
            window.location.href = url;
            
            // Reset button after delay
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 2000);
        }
        
        // Print report
        function printReport() {
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${document.title}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #333; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f5f5f5; }
                        .summary { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
                        .summary-item { margin: 5px 0; }
                        .footer { margin-top: 30px; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <h1>${document.querySelector('.table-header h3').textContent}</h1>
                    <div class="summary">
                        <div class="summary-item"><strong>Date Range:</strong> ${document.querySelector('.summary-header div').textContent}</div>
                        <div class="summary-item"><strong>Generated:</strong> ${new Date().toLocaleString()}</div>
                    </div>
                    ${document.querySelector('.table-container').outerHTML}
                    <div class="footer">
                        <p>Generated by CONQUER Gym Management System</p>
                    </div>
                </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }
        
        // Form validation
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if(startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if(end < start) {
                    e.preventDefault();
                    alert('End date must be after start date!');
                    return false;
                }
                
                // Show loading
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
                submitBtn.disabled = true;
            }
            
            return true;
        });
        
        // Chart.js Visualization
        const ctx = document.getElementById('reportChart').getContext('2d');
        
        // Sample chart data - in real app, this would come from PHP
        const chartData = {
            membership: {
                labels: ['Active', 'Inactive', 'Suspended'],
                datasets: [{
                    label: 'Members by Status',
                    data: [
                        <?php echo $reportSummary['active_members'] ?? 0; ?>,
                        <?php echo $reportSummary['inactive_members'] ?? 0; ?>,
                        0 // Suspended count would come from database
                    ],
                    backgroundColor: [
                        'rgba(56, 161, 105, 0.7)',
                        'rgba(229, 62, 62, 0.7)',
                        'rgba(245, 158, 11, 0.7)'
                    ]
                }]
            },
            revenue: {
                labels: ['Completed', 'Pending', 'Failed'],
                datasets: [{
                    label: 'Revenue by Status',
                    data: [
                        <?php echo $reportSummary['completed_revenue'] ?? 0; ?>,
                        500, // Sample pending
                        100  // Sample failed
                    ],
                    backgroundColor: [
                        'rgba(56, 161, 105, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(229, 62, 62, 0.7)'
                    ]
                }]
            },
            attendance: {
                labels: ['Yoga', 'HIIT', 'Strength', 'Cardio'],
                datasets: [{
                    label: 'Class Attendance',
                    data: [85, 92, 78, 88],
                    backgroundColor: 'rgba(102, 126, 234, 0.7)'
                }]
            },
            equipment: {
                labels: ['Active', 'Maintenance', 'Retired'],
                datasets: [{
                    label: 'Equipment Status',
                    data: [
                        <?php echo $reportSummary['active_equipment'] ?? 0; ?>,
                        <?php echo $reportSummary['maintenance_equipment'] ?? 0; ?>,
                        0 // Retired count would come from database
                    ],
                    backgroundColor: [
                        'rgba(56, 161, 105, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(229, 62, 62, 0.7)'
                    ]
                }]
            }
        };
        
        const reportChart = new Chart(ctx, {
            type: '<?php echo $reportType === "revenue" ? "bar" : "doughnut"; ?>',
            data: chartData['<?php echo $reportType; ?>'] || chartData.membership,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if(label) {
                                    label += ': ';
                                }
                                if(context.parsed.y !== undefined) {
                                    if(<?php echo $reportType === 'revenue' ? 'true' : 'false'; ?>) {
                                        label += '$' + context.parsed.y.toLocaleString();
                                    } else {
                                        label += context.parsed.y.toLocaleString();
                                    }
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Update chart when report type changes
        document.getElementById('reportForm').addEventListener('submit', function() {
            // This would reload with new data from server
        });
        
        // Quick date range buttons
        function setQuickRange(range) {
            const today = new Date();
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            
            endDate.value = today.toISOString().split('T')[0];
            
            switch(range) {
                case 'week':
                    const weekAgo = new Date(today);
                    weekAgo.setDate(today.getDate() - 7);
                    startDate.value = weekAgo.toISOString().split('T')[0];
                    break;
                case 'month':
                    const monthAgo = new Date(today);
                    monthAgo.setMonth(today.getMonth() - 1);
                    startDate.value = monthAgo.toISOString().split('T')[0];
                    break;
                case 'quarter':
                    const quarterAgo = new Date(today);
                    quarterAgo.setMonth(today.getMonth() - 3);
                    startDate.value = quarterAgo.toISOString().split('T')[0];
                    break;
                case 'year':
                    const yearAgo = new Date(today);
                    yearAgo.setFullYear(today.getFullYear() - 1);
                    startDate.value = yearAgo.toISOString().split('T')[0];
                    break;
            }
        }
        
        // Add quick range buttons to UI
        document.addEventListener('DOMContentLoaded', function() {
            const controlsGrid = document.querySelector('.controls-grid');
            const quickRangeDiv = document.createElement('div');
            quickRangeDiv.className = 'control-group';
            quickRangeDiv.innerHTML = `
                <label>Quick Ranges</label>
                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                    <button type="button" onclick="setQuickRange('week')" style="padding: 0.5rem; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">Last Week</button>
                    <button type="button" onclick="setQuickRange('month')" style="padding: 0.5rem; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">Last Month</button>
                    <button type="button" onclick="setQuickRange('quarter')" style="padding: 0.5rem; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">Last Quarter</button>
                </div>
            `;
            controlsGrid.appendChild(quickRangeDiv);
        });
    </script>
</body>
</html>