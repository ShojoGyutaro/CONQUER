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
                    (SELECT COUNT(*) FROM bookings b WHERE b.class_id = c.id AND b.status = 'confirmed') as enrollment_count,
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
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <!-- Chart.js for visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            color: var(--dark-color, #2f3542);
            min-height: 100vh;
            overflow: hidden;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        
        /* Main Content - EXACT SAME AS DASHBOARD */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width, 250px);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
        }
        
        /* Top Bar - EXACT SAME AS DASHBOARD */
        .top-bar {
            background: var(--white, #ffffff);
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            position: sticky;
            top: 0;
            z-index: 99;
            gap: 1rem;
            flex-wrap: wrap;
            flex-shrink: 0;
            min-height: 60px;
        }
        
        .search-bar {
            display: flex;
            align-items: center;
            background: var(--light-color, #f1f2f6);
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-md, 12px);
            flex: 1;
            max-width: 400px;
            position: relative;
            min-width: 200px;
        }
        
        .search-bar i {
            position: absolute;
            left: 0.75rem;
            color: var(--text-light, #6c757d);
            z-index: 1;
            font-size: 0.9rem;
        }
        
        .search-bar input {
            border: none;
            background: none;
            outline: none;
            font-family: inherit;
            font-size: 0.85rem;
            width: 100%;
            padding-left: 1.75rem;
            color: var(--dark-color, #2f3542);
        }
        
        .search-bar input::placeholder {
            color: var(--text-light, #6c757d);
            font-size: 0.85rem;
        }
        
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }
        
        .btn-notification {
            background: var(--light-color, #f1f2f6);
            border: none;
            font-size: 1rem;
            color: var(--dark-color, #2f3542);
            cursor: pointer;
            position: relative;
            padding: 0.4rem;
            border-radius: 50%;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .btn-notification:hover {
            background: var(--primary-color, #ff4757);
            color: white;
        }
        
        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--danger-color, #ff4757);
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            border: 2px solid var(--white, #ffffff);
        }
        
        .btn-primary {
            background: var(--primary-color, #ff4757);
            color: white;
            border: none;
            padding: 0.6rem 1.25rem;
            border-radius: var(--radius-md, 12px);
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-decoration: none;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark, #ff2e43);
            transform: translateY(-1px);
            box-shadow: 0 3px 15px rgba(255, 71, 87, 0.3);
        }
        
        /* Dashboard Content - EXACT SAME CONTAINER */
        .dashboard-content {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            overflow-x: hidden;
            width: 100%;
            margin: 0 auto;
        }
        
        /* Page Header - MATCHING DASHBOARD STYLE */
        .page-header {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-header h1 {
            font-size: 1.5rem;
            color: var(--dark-color, #2f3542);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }
        
        .page-header h1 i {
            color: var(--primary-color, #ff4757);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            background: var(--light-color, #f1f2f6);
            color: var(--dark-color, #2f3542);
            border-radius: var(--radius-md, 12px);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            font-size: 0.9rem;
        }
        
        .back-btn:hover {
            background: var(--primary-color, #ff4757);
            color: white;
        }
        
        /* Messages - MATCHING DASHBOARD STYLE */
        .message {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            animation: slideIn 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.success {
            border-left-color: var(--success-color, #2ed573);
            background: rgba(46, 213, 115, 0.1);
            color: var(--success-color, #2ed573);
        }
        
        .message.error {
            border-left-color: var(--danger-color, #ff4757);
            background: rgba(255, 71, 87, 0.1);
            color: var(--danger-color, #ff4757);
        }
        
        .message.warning {
            border-left-color: var(--warning-color, #ffa502);
            background: rgba(255, 165, 2, 0.1);
            color: var(--warning-color, #ffa502);
        }
        
        /* Report Controls - Matching Equipment Form Style */
        .report-controls {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .report-form {
            padding: 2rem;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h2 {
            font-size: 1.5rem;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-weight: 600;
        }
        
        .form-header p {
            color: var(--text-light, #6c757d);
            font-size: 0.95rem;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        /* Report Type Cards - Matching Equipment Type Cards */
        .report-type-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .report-type-card {
            border: 2px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .report-type-card:hover {
            border-color: var(--primary-color, #ff4757);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md, 0 4px 20px rgba(0,0,0,0.15));
        }
        
        .report-type-card.selected {
            border-color: var(--primary-color, #ff4757);
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.05), rgba(255, 71, 87, 0.02));
        }
        
        .report-type-card.selected::before {
            content: 'SELECTED';
            position: absolute;
            top: 10px;
            right: -25px;
            background: var(--primary-color, #ff4757);
            color: white;
            padding: 0.25rem 2rem;
            font-size: 0.7rem;
            font-weight: 600;
            transform: rotate(45deg);
        }
        
        .report-type-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: var(--primary-color, #ff4757);
        }
        
        .report-type-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .report-type-card p {
            color: var(--text-light, #6c757d);
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        /* Controls Grid - Matching Equipment Form */
        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 2rem;
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            border: 1px solid var(--border-color, #e0e0e0);
        }
        
        .control-group {
            display: flex;
            flex-direction: column;
        }
        
        .control-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color, #2f3542);
            font-size: 0.9rem;
        }
        
        .control-group input,
        .control-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            font-family: inherit;
            font-size: 0.9rem;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            background: var(--white, #ffffff);
            color: var(--dark-color, #2f3542);
        }
        
        .control-group input:focus,
        .control-group select:focus {
            outline: none;
            border-color: var(--primary-color, #ff4757);
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        .control-group small {
            display: block;
            margin-top: 0.25rem;
            color: var(--text-light, #6c757d);
            font-size: 0.8rem;
        }
        
        /* Format Select Styling */
        .format-select-container {
            position: relative;
        }
        
        .format-select-container select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            padding-right: 2.5rem;
        }
        
        /* Quick Range Buttons - Matching Location Tags */
        .quick-range-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .quick-range-btn {
            padding: 0.6rem 1.25rem;
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            font-size: 0.9rem;
            font-weight: 500;
            border: 2px solid transparent;
            color: var(--text-light, #6c757d);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quick-range-btn:hover {
            background: var(--primary-color, #ff4757);
            color: white;
            transform: translateY(-2px);
            border-color: var(--primary-color, #ff4757);
        }
        
        .quick-range-btn i {
            font-size: 0.8rem;
        }
        
        /* Form Actions - Matching Equipment Form */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: var(--radius-md, 12px);
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            font-family: inherit;
            font-size: 0.9rem;
        }
        
        .btn i {
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--primary-color, #ff4757);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark, #ff2e43);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
        }
        
        .btn-success {
            background: var(--success-color, #2ed573);
            color: white;
            border-color: var(--success-color, #2ed573);
        }
        
        .btn-success:hover {
            background: #25c464;
            border-color: #25c464;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: var(--warning-color, #ffa502);
            color: white;
            border-color: var(--warning-color, #ffa502);
        }
        
        .btn-warning:hover {
            background: #e69500;
            border-color: #e69500;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--light-color, #f1f2f6);
            color: var(--dark-color, #2f3542);
            border: 1px solid var(--border-color, #e0e0e0);
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        /* Report Summary - Matching Preview Style */
        .report-summary {
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 2px solid var(--border-color, #e0e0e0);
        }
        
        .summary-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .summary-header i {
            color: var(--primary-color, #ff4757);
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .summary-stat {
            background: var(--white, #ffffff);
            padding: 1rem;
            border-radius: var(--radius-md, 12px);
            border: 1px solid var(--border-color, #e0e0e0);
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-light, #6c757d);
        }
        
        /* Chart Container - Matching Preview */
        .chart-container {
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 2px solid var(--border-color, #e0e0e0);
            height: 400px;
        }
        
        .chart-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .chart-header i {
            color: var(--primary-color, #ff4757);
        }
        
        /* Report Table - Enhanced */
        .report-table-container {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            overflow: hidden;
            margin-top: 1.5rem;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color, #e0e0e0);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .table-header h3 {
            font-size: 1.2rem;
            color: var(--dark-color, #2f3542);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }
        
        .table-header h3 i {
            color: var(--primary-color, #ff4757);
        }
        
        .table-body {
            padding: 1.5rem;
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .data-table th {
            text-align: left;
            padding: 1rem;
            background: var(--light-color, #f1f2f6);
            color: var(--dark-color, #2f3542);
            font-weight: 600;
            border-bottom: 2px solid var(--border-color, #e0e0e0);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color, #e0e0e0);
            color: var(--dark-color, #2f3542);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background: var(--light-color, #f1f2f6);
        }
        
        /* Status Badges - Matching Equipment Status */
        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: linear-gradient(to bottom right, rgba(46, 213, 115, 0.1), rgba(46, 213, 115, 0.05));
            color: var(--success-color, #2ed573);
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        .status-inactive {
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.1), rgba(255, 71, 87, 0.05));
            color: var(--danger-color, #ff4757);
            border: 1px solid rgba(255, 71, 87, 0.3);
        }
        
        .status-pending {
            background: linear-gradient(to bottom right, rgba(255, 165, 2, 0.1), rgba(255, 165, 2, 0.05));
            color: var(--warning-color, #ffa502);
            border: 1px solid rgba(255, 165, 2, 0.3);
        }
        
        .status-maintenance {
            background: linear-gradient(to bottom right, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05));
            color: #3498db;
            border: 1px solid rgba(52, 152, 219, 0.3);
        }
        
        /* Empty State - Matching */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-light, #6c757d);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--light-color, #f1f2f6);
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            font-size: 1.2rem;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        /* Loading animation */
        .fa-spinner.fa-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Required field indicator */
        .required::after {
            content: ' *';
            color: var(--danger-color, #ff4757);
        }
        
        /* Responsive Design - SAME AS EQUIPMENT PAGE */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 60px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .top-bar {
                flex-direction: column;
                gap: 0.75rem;
                padding: 0.75rem;
            }
            
            .search-bar {
                max-width: 100%;
                width: 100%;
                min-width: auto;
            }
            
            .top-bar-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .dashboard-content {
                padding: 1rem;
            }
            
            .report-controls,
            .report-table-container {
                margin: 0 -1rem;
                border-radius: 0;
            }
            
            .report-form {
                padding: 1.5rem;
            }
            
            .report-type-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .controls-grid {
                grid-template-columns: 1fr;
                padding: 1.5rem;
            }
            
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .page-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .quick-range-buttons {
                justify-content: center;
            }
            
            .chart-container {
                height: 300px;
                padding: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-content {
                padding: 0.75rem;
            }
            
            .report-form {
                padding: 1rem;
            }
            
            .report-type-cards {
                grid-template-columns: 1fr;
            }
            
            .summary-stat {
                padding: 0.75rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .quick-range-btn {
                width: 100%;
                justify-content: center;
            }
            
            .data-table {
                min-width: 600px;
            }
        }
        
        /* Scrollbar styling - SAME AS DASHBOARD */
        .dashboard-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .dashboard-content::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .dashboard-content::-webkit-scrollbar-thumb {
            background: var(--gray, #a4b0be);
            border-radius: 3px;
        }
        
        .dashboard-content::-webkit-scrollbar-thumb:hover {
            background: var(--text-light, #6c757d);
        }
        
        /* Fix for Firefox */
        @-moz-document url-prefix() {
            .dashboard-content {
                scrollbar-width: thin;
                scrollbar-color: var(--gray, #a4b0be) transparent;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Include Sidebar -->
        <?php include 'admin-sidebar.php'; ?>
        
        <!-- Main Content - EXACT SAME STRUCTURE AS DASHBOARD -->
        <div class="main-content">
            <!-- Top Bar - EXACTLY AS IN DASHBOARD -->
            <div class="top-bar">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search reports, members, transactions...">
                </div>
                <div class="top-bar-actions">
                    <button class="btn-notification">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <a href="admin-add.php" class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Add New
                    </a>
                </div>
            </div>
            
            <!-- Dashboard Content - SAME CONTAINER -->
            <div class="dashboard-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h1>
                        <i class="fas fa-chart-bar"></i>
                        Generate Reports
                    </h1>
                    <a href="admin-dashboard.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
                
                <!-- Report Controls -->
                <div class="report-controls">
                    <form method="GET" class="report-form" id="reportForm">
                        <div class="form-header">
                            <h2><i class="fas fa-chart-line"></i> Generate Report</h2>
                            <p>Select report type, date range, and format to generate detailed insights.</p>
                        </div>
                        
                        <input type="hidden" name="type" id="reportType" value="<?php echo htmlspecialchars($reportType); ?>">
                        
                        <!-- Report Type Selection -->
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
                        
                        <!-- Date Range & Format -->
                        <div class="controls-grid">
                            <div class="control-group">
                                <label for="start_date" class="required">Start Date</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required>
                            </div>
                            
                            <div class="control-group">
                                <label for="end_date" class="required">End Date</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required>
                            </div>
                            
                            <div class="control-group format-select-container">
                                <label for="format">Export Format</label>
                                <select id="format" name="format">
                                    <option value="html" <?php echo $format === 'html' ? 'selected' : ''; ?>>Web View (HTML)</option>
                                    <option value="csv" <?php echo $format === 'csv' ? 'selected' : ''; ?>>Spreadsheet (CSV)</option>
                                    <option value="pdf" <?php echo $format === 'pdf' ? 'selected' : ''; ?>>Document (PDF)</option>
                                </select>
                            </div>
                            
                            <div class="control-group">
                                <label>Quick Date Ranges</label>
                                <div class="quick-range-buttons">
                                    <button type="button" class="quick-range-btn" data-range="week">
                                        <i class="fas fa-calendar-week"></i>
                                        Last Week
                                    </button>
                                    <button type="button" class="quick-range-btn" data-range="month">
                                        <i class="fas fa-calendar-alt"></i>
                                        This Month
                                    </button>
                                    <button type="button" class="quick-range-btn" data-range="quarter">
                                        <i class="fas fa-chart-line"></i>
                                        Last Quarter
                                    </button>
                                    <button type="button" class="quick-range-btn" data-range="year">
                                        <i class="fas fa-calendar-year"></i>
                                        This Year
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
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
                        <i class="fas fa-chart-pie"></i>
                        Report Summary
                        <div style="margin-left: auto; font-size: 0.9rem; color: var(--text-light);">
                            <?php echo date('F j, Y', strtotime($startDate)); ?> to <?php echo date('F j, Y', strtotime($endDate)); ?>
                        </div>
                    </div>
                    <div class="summary-stats">
                        <?php foreach($reportSummary as $key => $value): 
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
                        ?>
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
                                    <?php echo $labels[$key] ?? ucwords(str_replace('_', ' ', $key)); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Chart Container -->
                <div class="chart-container">
                    <div class="chart-header">
                        <i class="fas fa-chart-line"></i>
                        <?php echo htmlspecialchars($reportTitle); ?> Visualization
                    </div>
                    <canvas id="reportChart"></canvas>
                </div>
                
                <!-- Report Table -->
                <div class="report-table-container">
                    <div class="table-header">
                        <h3>
                            <i class="fas fa-table"></i>
                            <?php echo htmlspecialchars($reportTitle); ?>
                        </h3>
                        <div style="color: var(--text-light); font-size: 0.9rem;">
                            Showing <?php echo count($reportRows); ?> records
                        </div>
                    </div>
                    
                    <div class="table-body">
                        <?php if(!empty($reportRows)): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <?php foreach($reportHeaders as $header): ?>
                                            <th><?php echo htmlspecialchars($header); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
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
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h4>No Data Found</h4>
                                <p>No records found for the selected criteria. Try adjusting your filters.</p>
                            </div>
                        <?php endif; ?>
                    </div>
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
                
                // Update date range suggestions
                updateDateRange(type);
            });
        });
        
        // Quick range buttons
        document.querySelectorAll('.quick-range-btn').forEach(button => {
            button.addEventListener('click', function() {
                const range = this.getAttribute('data-range');
                setQuickRange(range);
            });
        });
        
        // Update date range based on report type
        function updateDateRange(type) {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const today = new Date();
            
            endDateInput.value = today.toISOString().split('T')[0];
            
            switch(type) {
                case 'revenue':
                    // Last 30 days for revenue
                    const lastMonth = new Date(today);
                    lastMonth.setDate(today.getDate() - 30);
                    startDateInput.value = lastMonth.toISOString().split('T')[0];
                    break;
                case 'attendance':
                    // Current month for attendance
                    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                    startDateInput.value = firstDay.toISOString().split('T')[0];
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
                    break;
            }
        }
        
        // Set quick date ranges
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
                    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                    startDate.value = firstDay.toISOString().split('T')[0];
                    break;
                case 'quarter':
                    const quarterAgo = new Date(today);
                    quarterAgo.setMonth(today.getMonth() - 3);
                    startDate.value = quarterAgo.toISOString().split('T')[0];
                    break;
                case 'year':
                    const yearAgo = new Date(today.getFullYear(), 0, 1);
                    startDate.value = yearAgo.toISOString().split('T')[0];
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
            
            if(!startDate || !endDate) {
                alert('Please select both start and end dates');
                return;
            }
            
            if(new Date(endDate) < new Date(startDate)) {
                alert('End date must be after start date');
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
            window.print();
        }
        
        // Form validation
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if(!startDate || !endDate) {
                e.preventDefault();
                alert('Please select both start and end dates');
                return false;
            }
            
            if(new Date(endDate) < new Date(startDate)) {
                e.preventDefault();
                alert('End date must be after start date');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            submitBtn.disabled = true;
            
            return true;
        });
        
        // Chart.js Visualization
        const ctx = document.getElementById('reportChart').getContext('2d');
        
        // Sample chart data based on report type
        <?php if($reportType === 'membership'): ?>
            const chartData = {
                labels: ['Active', 'Inactive'],
                datasets: [{
                    label: 'Members by Status',
                    data: [
                        <?php echo $reportSummary['active_members'] ?? 0; ?>,
                        <?php echo $reportSummary['inactive_members'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(46, 213, 115, 0.7)',
                        'rgba(255, 71, 87, 0.7)'
                    ],
                    borderColor: [
                        'rgba(46, 213, 115, 1)',
                        'rgba(255, 71, 87, 1)'
                    ],
                    borderWidth: 1
                }]
            };
            const chartType = 'doughnut';
        <?php elseif($reportType === 'revenue'): ?>
            const chartData = {
                labels: ['Completed', 'Pending', 'Failed'],
                datasets: [{
                    label: 'Revenue by Status',
                    data: [
                        <?php echo $reportSummary['completed_revenue'] ?? 0; ?>,
                        500,
                        100
                    ],
                    backgroundColor: [
                        'rgba(46, 213, 115, 0.7)',
                        'rgba(255, 165, 2, 0.7)',
                        'rgba(255, 71, 87, 0.7)'
                    ]
                }]
            };
            const chartType = 'bar';
        <?php elseif($reportType === 'attendance'): ?>
            const chartData = {
                labels: ['Yoga', 'HIIT', 'Strength', 'Cardio'],
                datasets: [{
                    label: 'Class Attendance Rate',
                    data: [85, 92, 78, 88],
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }]
            };
            const chartType = 'bar';
        <?php elseif($reportType === 'equipment'): ?>
            const chartData = {
                labels: ['Active', 'Maintenance', 'Retired'],
                datasets: [{
                    label: 'Equipment Status',
                    data: [
                        <?php echo $reportSummary['active_equipment'] ?? 0; ?>,
                        <?php echo $reportSummary['maintenance_equipment'] ?? 0; ?>,
                        0
                    ],
                    backgroundColor: [
                        'rgba(46, 213, 115, 0.7)',
                        'rgba(255, 165, 2, 0.7)',
                        'rgba(255, 71, 87, 0.7)'
                    ]
                }]
            };
            const chartType = 'doughnut';
        <?php else: ?>
            const chartData = {
                labels: ['Data'],
                datasets: [{
                    label: 'Report Data',
                    data: [0],
                    backgroundColor: ['rgba(102, 126, 234, 0.7)']
                }]
            };
            const chartType = 'bar';
        <?php endif; ?>
        
        const reportChart = new Chart(ctx, {
            type: chartType,
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
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
                                } else if(context.parsed !== undefined) {
                                    label += context.parsed.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if(<?php echo $reportType === 'revenue' ? 'true' : 'false'; ?>) {
                                    return '$' + value.toLocaleString();
                                }
                                return value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Initialize with current month dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            if(!document.getElementById('start_date').value) {
                document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
            }
            
            if(!document.getElementById('end_date').value) {
                document.getElementById('end_date').value = today.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>