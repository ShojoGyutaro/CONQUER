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

$successMessage = '';
$errorMessage = '';

// Fetch members for dropdown
$members = [];
try {
    $members = $pdo->query("
        SELECT u.*, gm.MembershipPlan, gm.MembershipStatus 
        FROM users u 
        LEFT JOIN gym_members gm ON u.email = gm.Email 
        WHERE u.user_type = 'member' 
        AND u.is_active = 1
        ORDER BY u.full_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Fetch members error: " . $e->getMessage());
}

// Fetch payment methods
$paymentMethods = ['Credit Card', 'Debit Card', 'Bank Transfer', 'Cash', 'Check', 'PayPal', 'Other'];

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = $_POST['member_id'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? '';
    $paymentDate = $_POST['payment_date'] ?? '';
    $dueDate = $_POST['due_date'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'completed';
    $transactionId = $_POST['transaction_id'] ?? '';
    
    // Validate inputs
    $errors = [];
    
    if(empty($memberId)) $errors[] = 'Member is required';
    if(empty($amount) || $amount <= 0) $errors[] = 'Valid amount is required';
    if(empty($paymentMethod)) $errors[] = 'Payment method is required';
    if(empty($paymentDate)) $errors[] = 'Payment date is required';
    if(empty($status)) $errors[] = 'Status is required';
    
    // Validate dates
    if($dueDate && strtotime($dueDate) < strtotime($paymentDate)) {
        $errors[] = 'Due date cannot be before payment date';
    }
    
    if(empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Get member details
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$member) {
                throw new Exception("Member not found");
            }
            
            // Insert into payments table
            $stmt = $pdo->prepare("
                INSERT INTO payments (
                    user_id, amount, payment_method, payment_date, due_date,
                    description, status, transaction_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $memberId,
                $amount,
                $paymentMethod,
                $paymentDate,
                $dueDate ?: null,
                $description,
                $status,
                $transactionId ?: null
            ]);
            
            $paymentId = $pdo->lastInsertId();
            
            // If payment is completed, update gym_members table
            if($status === 'completed') {
                $stmt = $pdo->prepare("
                    UPDATE gym_members 
                    SET LastPaymentDate = ?, MembershipStatus = 'Active'
                    WHERE Email = ?
                ");
                $stmt->execute([$paymentDate, $member['email']]);
            }
            
            $pdo->commit();
            
            $successMessage = "Payment of $" . number_format($amount, 2) . " recorded successfully! Payment ID: $paymentId";
            
            // Clear form
            $_POST = [];
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $errorMessage = 'Error recording payment: ' . $e->getMessage();
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}

$adminName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment | CONQUER Gym Admin</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Full screen layout - EXACT SAME AS DASHBOARD */
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
        
        /* Add Form Container */
        .add-form-container {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .add-form {
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
        
        .form-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 2rem;
            position: relative;
        }
        
        .form-steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 2px;
            background: var(--light-color, #f1f2f6);
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--light-color, #f1f2f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-light, #6c757d);
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
        }
        
        .step.active .step-number {
            background: var(--primary-color, #ff4757);
            color: white;
            transform: scale(1.1);
        }
        
        .step span {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-light, #6c757d);
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
        }
        
        .step.active span {
            color: var(--primary-color, #ff4757);
            font-weight: 600;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color, #e0e0e0);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .form-section h3 {
            font-size: 1.1rem;
            color: var(--dark-color, #2f3542);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }
        
        .form-section h3 i {
            color: var(--primary-color, #ff4757);
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color, #2f3542);
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
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
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color, #ff4757);
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: var(--text-light, #6c757d);
            font-size: 0.8rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        /* Payment Method Cards */
        .payment-method-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .payment-method-card {
            border: 2px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .payment-method-card:hover {
            border-color: var(--primary-color, #ff4757);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md, 0 4px 20px rgba(0,0,0,0.15));
        }
        
        .payment-method-card.selected {
            border-color: var(--primary-color, #ff4757);
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.05), rgba(255, 71, 87, 0.02));
        }
        
        .payment-method-card.selected::before {
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
        
        .payment-method-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: var(--primary-color, #ff4757);
        }
        
        .payment-method-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .payment-method-card p {
            color: var(--text-light, #6c757d);
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        /* Status Cards */
        .status-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .status-card {
            border: 2px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .status-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md, 0 4px 20px rgba(0,0,0,0.15));
        }
        
        .status-card.selected {
            border-width: 3px;
        }
        
        .status-card.completed {
            border-color: var(--success-color, #2ed573);
            background: linear-gradient(to bottom right, rgba(46, 213, 115, 0.05), rgba(46, 213, 115, 0.02));
        }
        
        .status-card.completed.selected::before {
            content: 'SELECTED';
            position: absolute;
            top: 10px;
            right: -25px;
            background: var(--success-color, #2ed573);
            color: white;
            padding: 0.25rem 2rem;
            font-size: 0.7rem;
            font-weight: 600;
            transform: rotate(45deg);
        }
        
        .status-card.pending {
            border-color: var(--warning-color, #ffa502);
            background: linear-gradient(to bottom right, rgba(255, 165, 2, 0.05), rgba(255, 165, 2, 0.02));
        }
        
        .status-card.pending.selected::before {
            content: 'SELECTED';
            position: absolute;
            top: 10px;
            right: -25px;
            background: var(--warning-color, #ffa502);
            color: white;
            padding: 0.25rem 2rem;
            font-size: 0.7rem;
            font-weight: 600;
            transform: rotate(45deg);
        }
        
        .status-card.failed {
            border-color: var(--danger-color, #ff4757);
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.05), rgba(255, 71, 87, 0.02));
        }
        
        .status-card.failed.selected::before {
            content: 'SELECTED';
            position: absolute;
            top: 10px;
            right: -25px;
            background: var(--danger-color, #ff4757);
            color: white;
            padding: 0.25rem 2rem;
            font-size: 0.7rem;
            font-weight: 600;
            transform: rotate(45deg);
        }
        
        .status-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }
        
        .status-card.completed .status-icon {
            color: var(--success-color, #2ed573);
        }
        
        .status-card.pending .status-icon {
            color: var(--warning-color, #ffa502);
        }
        
        .status-card.failed .status-icon {
            color: var(--danger-color, #ff4757);
        }
        
        .status-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .status-desc {
            color: var(--text-light, #6c757d);
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        /* Amount Input */
        .amount-input {
            position: relative;
        }
        
        .amount-input:before {
            content: '$';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light, #6c757d);
            font-weight: 500;
            font-size: 1.1rem;
        }
        
        .amount-input input {
            padding-left: 30px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        /* Member Select Styling */
        .member-select-container {
            position: relative;
        }
        
        .member-select-container select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            padding-right: 2.5rem;
        }
        
        /* Payment Preview */
        .payment-preview {
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            margin-top: 1.5rem;
            border: 2px solid var(--border-color, #e0e0e0);
        }
        
        .preview-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .preview-header i {
            color: var(--primary-color, #ff4757);
        }
        
        .preview-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .preview-item {
            background: var(--white, #ffffff);
            padding: 1rem;
            border-radius: var(--radius-md, 12px);
            border: 1px solid var(--border-color, #e0e0e0);
        }
        
        .preview-label {
            font-size: 0.8rem;
            color: var(--text-light, #6c757d);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .preview-label i {
            font-size: 0.8rem;
        }
        
        .preview-value {
            font-weight: 600;
            color: var(--dark-color, #2f3542);
            font-size: 1rem;
            line-height: 1.4;
        }
        
        /* Description Character Counter */
        .description-counter {
            text-align: right;
            font-size: 0.8rem;
            color: var(--text-light, #6c757d);
            margin-top: 0.25rem;
        }
        
        /* Form Actions */
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
        
        .btn-secondary {
            background: var(--light-color, #f1f2f6);
            color: var(--dark-color, #2f3542);
            border: 1px solid var(--border-color, #e0e0e0);
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        /* Required field indicator */
        .required::after {
            content: ' *';
            color: var(--danger-color, #ff4757);
        }
        
        /* Loading animation */
        .fa-spinner.fa-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Member Information Display */
        .member-info {
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            padding: 1.25rem;
            margin-top: 1rem;
            border: 1px solid var(--border-color, #e0e0e0);
            display: none;
        }
        
        .member-info.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .member-info-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .member-info-header h4 {
            font-size: 1rem;
            color: var(--dark-color, #2f3542);
            margin: 0;
        }
        
        .member-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .member-detail {
            display: flex;
            flex-direction: column;
        }
        
        .member-label {
            font-size: 0.8rem;
            color: var(--text-light, #6c757d);
            margin-bottom: 0.25rem;
        }
        
        .member-value {
            font-weight: 600;
            color: var(--dark-color, #2f3542);
            font-size: 0.9rem;
        }
        
        /* Responsive Design - SAME AS MEMBER PAGE */
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
            
            .add-form-container {
                margin: 0 -1rem;
                border-radius: 0;
            }
            
            .add-form {
                padding: 1.5rem;
            }
            
            .form-steps {
                gap: 1rem;
            }
            
            .form-steps::before {
                width: 90%;
            }
            
            .payment-method-cards,
            .status-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .preview-details,
            .member-details {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
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
        }
        
        @media (max-width: 480px) {
            .form-steps {
                flex-direction: column;
                align-items: center;
                gap: 1.5rem;
            }
            
            .form-steps::before {
                display: none;
            }
            
            .step {
                flex-direction: row;
                width: 100%;
                justify-content: flex-start;
            }
            
            .dashboard-content {
                padding: 0.75rem;
            }
            
            .add-form {
                padding: 1rem;
            }
            
            .payment-method-cards,
            .status-cards {
                grid-template-columns: 1fr;
            }
            
            .preview-item {
                padding: 0.75rem;
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
                    <input type="text" placeholder="Search payments, members, transactions...">
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
                        <i class="fas fa-credit-card"></i>
                        Record Payment
                    </h1>
                    <a href="admin-add.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                        Back to Add Menu
                    </a>
                </div>
                
                <?php if($successMessage): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($errorMessage): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Add Form Container -->
                <div class="add-form-container">
                    <form method="POST" class="add-form" id="addPaymentForm">
                        <div class="form-header">
                            <h2><i class="fas fa-credit-card"></i> Record Payment</h2>
                            <p>Record a new payment for a gym member. All fields marked with * are required.</p>
                        </div>
                        
                        <div class="form-steps">
                            <div class="step active">
                                <div class="step-number">1</div>
                                <span>Member Info</span>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <span>Payment Details</span>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <span>Confirmation</span>
                            </div>
                        </div>
                        
                        <!-- Section 1: Member Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user"></i> Member Information</h3>
                            
                            <div class="form-group member-select-container">
                                <label for="member_id" class="required">Select Member</label>
                                <select id="member_id" name="member_id" required onchange="updateMemberInfo()">
                                    <option value="">-- Select a Member --</option>
                                    <?php foreach($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>" 
                                            <?php echo ($_POST['member_id'] ?? '') == $member['id'] ? 'selected' : ''; ?>
                                            data-email="<?php echo htmlspecialchars($member['email']); ?>"
                                            data-plan="<?php echo htmlspecialchars($member['MembershipPlan'] ?? 'N/A'); ?>"
                                            data-status="<?php echo htmlspecialchars($member['MembershipStatus'] ?? 'N/A'); ?>"
                                            data-name="<?php echo htmlspecialchars($member['full_name']); ?>">
                                            <?php echo htmlspecialchars($member['full_name']); ?> 
                                            (<?php echo htmlspecialchars($member['MembershipPlan'] ?? 'No Plan'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Select the member making the payment</small>
                            </div>
                            
                            <!-- Member Information Display -->
                            <div class="member-info" id="memberInfo">
                                <div class="member-info-header">
                                    <i class="fas fa-user-circle"></i>
                                    <h4 id="memberName">Member Details</h4>
                                </div>
                                <div class="member-details">
                                    <div class="member-detail">
                                        <div class="member-label">Email</div>
                                        <div class="member-value" id="memberEmail">-</div>
                                    </div>
                                    <div class="member-detail">
                                        <div class="member-label">Membership Plan</div>
                                        <div class="member-value" id="memberPlan">-</div>
                                    </div>
                                    <div class="member-detail">
                                        <div class="member-label">Current Status</div>
                                        <div class="member-value" id="memberStatus">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 2: Payment Details -->
                        <div class="form-section">
                            <h3><i class="fas fa-money-bill-wave"></i> Payment Details</h3>
                            
                            <div class="form-row">
                                <div class="form-group amount-input">
                                    <label for="amount" class="required">Amount ($)</label>
                                    <input type="number" id="amount" name="amount" 
                                           value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" 
                                           step="0.01" min="0.01" placeholder="0.00" required>
                                    <small>Enter the payment amount in dollars</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="transaction_id">Transaction ID</label>
                                    <input type="text" id="transaction_id" name="transaction_id" 
                                           value="<?php echo htmlspecialchars($_POST['transaction_id'] ?? ''); ?>" 
                                           placeholder="e.g., TXN-2023-001234">
                                    <small>Optional: Bank or payment processor reference</small>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="payment_date" class="required">Payment Date</label>
                                    <input type="date" id="payment_date" name="payment_date" 
                                           value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>" required>
                                    <small>Date when payment was made</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="due_date">Due Date</label>
                                    <input type="date" id="due_date" name="due_date" 
                                           value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>">
                                    <small>Optional: Next payment due date</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Payment Method</label>
                                <input type="hidden" id="payment_method" name="payment_method" 
                                       value="<?php echo htmlspecialchars($_POST['payment_method'] ?? ''); ?>" required>
                                <div class="payment-method-cards" id="paymentMethodCards">
                                    <div class="payment-method-card" data-method="Credit Card">
                                        <div class="payment-method-icon">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <div class="payment-method-name">Credit Card</div>
                                        <p>Visa, Mastercard, American Express</p>
                                    </div>
                                    <div class="payment-method-card" data-method="Debit Card">
                                        <div class="payment-method-icon">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <div class="payment-method-name">Debit Card</div>
                                        <p>Bank debit card transactions</p>
                                    </div>
                                    <div class="payment-method-card" data-method="Bank Transfer">
                                        <div class="payment-method-icon">
                                            <i class="fas fa-university"></i>
                                        </div>
                                        <div class="payment-method-name">Bank Transfer</div>
                                        <p>Direct bank transfer or ACH</p>
                                    </div>
                                    <div class="payment-method-card" data-method="Cash">
                                        <div class="payment-method-icon">
                                            <i class="fas fa-money-bill"></i>
                                        </div>
                                        <div class="payment-method-name">Cash</div>
                                        <p>Physical cash payment</p>
                                    </div>
                                    <div class="payment-method-card" data-method="Check">
                                        <div class="payment-method-icon">
                                            <i class="fas fa-money-check"></i>
                                        </div>
                                        <div class="payment-method-name">Check</div>
                                        <p>Personal or business check</p>
                                    </div>
                                    <div class="payment-method-card" data-method="PayPal">
                                        <div class="payment-method-icon">
                                            <i class="fab fa-paypal"></i>
                                        </div>
                                        <div class="payment-method-name">PayPal</div>
                                        <p>Online payment through PayPal</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="3" 
                                          placeholder="Enter payment description (e.g., Monthly membership, Personal training, Late fee)..."
                                          oninput="updateDescriptionCounter()"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                <div class="description-counter">
                                    <span id="descriptionCount">0</span>/200 characters
                                </div>
                                <small>Optional: Purpose or notes about this payment</small>
                            </div>
                        </div>
                        
                        <!-- Section 3: Status & Confirmation -->
                        <div class="form-section">
                            <h3><i class="fas fa-check-circle"></i> Status & Confirmation</h3>
                            
                            <div class="form-group">
                                <label class="required">Payment Status</label>
                                <input type="hidden" id="status" name="status" 
                                       value="<?php echo htmlspecialchars($_POST['status'] ?? 'completed'); ?>" required>
                                <div class="status-cards" id="statusCards">
                                    <div class="status-card completed" data-status="completed">
                                        <div class="status-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="status-name">Completed</div>
                                        <div class="status-desc">Payment successfully processed and confirmed</div>
                                    </div>
                                    <div class="status-card pending" data-status="pending">
                                        <div class="status-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="status-name">Pending</div>
                                        <div class="status-desc">Payment initiated but not yet confirmed</div>
                                    </div>
                                    <div class="status-card failed" data-status="failed">
                                        <div class="status-icon">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div class="status-name">Failed</div>
                                        <div class="status-desc">Payment was unsuccessful or declined</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Preview -->
                            <div class="payment-preview" id="paymentPreview">
                                <div class="preview-header">
                                    <i class="fas fa-eye"></i>
                                    Payment Preview
                                </div>
                                <div class="preview-details">
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="fas fa-user"></i>
                                            Member
                                        </div>
                                        <div class="preview-value" id="previewMember">Not selected</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="fas fa-dollar-sign"></i>
                                            Amount
                                        </div>
                                        <div class="preview-value" id="previewAmount">$0.00</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="fas fa-calendar-alt"></i>
                                            Date
                                        </div>
                                        <div class="preview-value" id="previewDate">Today</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="fas fa-check-circle"></i>
                                            Status
                                        </div>
                                        <div class="preview-value" id="previewStatus">Completed</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-credit-card"></i>
                                Record Payment
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin-payments.php'">
                                <i class="fas fa-times"></i>
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Member selection handler
        function updateMemberInfo() {
            const select = document.getElementById('member_id');
            const selectedOption = select.options[select.selectedIndex];
            const memberInfo = document.getElementById('memberInfo');
            
            if(selectedOption.value) {
                // Show member info
                memberInfo.classList.add('active');
                
                // Update member details
                document.getElementById('memberName').textContent = selectedOption.getAttribute('data-name');
                document.getElementById('memberEmail').textContent = selectedOption.getAttribute('data-email');
                document.getElementById('memberPlan').textContent = selectedOption.getAttribute('data-plan');
                document.getElementById('memberStatus').textContent = selectedOption.getAttribute('data-status');
                
                // Update preview
                document.getElementById('previewMember').textContent = selectedOption.getAttribute('data-name');
            } else {
                // Hide member info
                memberInfo.classList.remove('active');
                document.getElementById('previewMember').textContent = 'Not selected';
            }
            
            updateStepIndicator(2);
            updatePreview();
        }
        
        // Payment method selection
        document.querySelectorAll('.payment-method-card').forEach(card => {
            card.addEventListener('click', function() {
                const method = this.getAttribute('data-method');
                document.getElementById('payment_method').value = method;
                
                // Update UI
                document.querySelectorAll('.payment-method-card').forEach(c => {
                    c.classList.remove('selected');
                });
                this.classList.add('selected');
                
                updateStepIndicator(3);
                updatePreview();
            });
        });
        
        // Auto-select previously selected payment method
        const selectedMethod = "<?php echo $_POST['payment_method'] ?? ''; ?>";
        if(selectedMethod) {
            document.querySelectorAll('.payment-method-card').forEach(card => {
                if(card.getAttribute('data-method') === selectedMethod) {
                    card.classList.add('selected');
                }
            });
        } else {
            // Default select first card
            setTimeout(() => {
                document.querySelector('.payment-method-card').click();
            }, 100);
        }
        
        // Status selection
        document.querySelectorAll('.status-card').forEach(card => {
            card.addEventListener('click', function() {
                const status = this.getAttribute('data-status');
                document.getElementById('status').value = status;
                
                // Update UI
                document.querySelectorAll('.status-card').forEach(c => {
                    c.classList.remove('selected');
                });
                this.classList.add('selected');
                
                updateStepIndicator(3);
                updatePreview();
            });
        });
        
        // Auto-select previously selected status
        const selectedStatus = "<?php echo $_POST['status'] ?? 'completed'; ?>";
        document.querySelectorAll('.status-card').forEach(card => {
            if(card.getAttribute('data-status') === selectedStatus) {
                card.classList.add('selected');
            }
        });
        
        // Update payment preview
        function updatePreview() {
            const memberSelect = document.getElementById('member_id');
            const memberName = memberSelect.options[memberSelect.selectedIndex]?.getAttribute('data-name') || 'Not selected';
            const amount = document.getElementById('amount').value;
            const paymentDate = document.getElementById('payment_date').value;
            const status = document.getElementById('status').value;
            const paymentMethod = document.getElementById('payment_method').value;
            
            // Update member
            document.getElementById('previewMember').textContent = memberName;
            
            // Update amount
            if(amount) {
                document.getElementById('previewAmount').textContent = '$' + parseFloat(amount).toFixed(2);
            } else {
                document.getElementById('previewAmount').textContent = '$0.00';
            }
            
            // Update date
            if(paymentDate) {
                const date = new Date(paymentDate);
                const today = new Date();
                const isToday = date.toDateString() === today.toDateString();
                
                if(isToday) {
                    document.getElementById('previewDate').textContent = 'Today';
                } else {
                    document.getElementById('previewDate').textContent = date.toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric',
                        year: 'numeric'
                    });
                }
            } else {
                document.getElementById('previewDate').textContent = 'Today';
            }
            
            // Update status with color coding
            const statusMap = {
                'completed': '<span style="color: var(--success-color); font-weight: 600;">● Completed</span>',
                'pending': '<span style="color: var(--warning-color); font-weight: 600;">● Pending</span>',
                'failed': '<span style="color: var(--danger-color); font-weight: 600;">● Failed</span>'
            };
            document.getElementById('previewStatus').innerHTML = statusMap[status] || 'Completed';
        }
        
        // Add event listeners for preview updates
        document.getElementById('amount').addEventListener('input', updatePreview);
        document.getElementById('payment_date').addEventListener('change', updatePreview);
        document.getElementById('status').addEventListener('change', updatePreview);
        
        // Description character counter
        function updateDescriptionCounter() {
            const description = document.getElementById('description');
            const counter = document.getElementById('descriptionCount');
            const charCount = description.value.length;
            counter.textContent = charCount;
            
            if(charCount > 200) {
                description.value = description.value.substring(0, 200);
                counter.textContent = 200;
                counter.style.color = 'var(--danger-color)';
            } else if(charCount > 180) {
                counter.style.color = 'var(--warning-color)';
            } else {
                counter.style.color = 'var(--text-light)';
            }
        }
        
        // Initialize description counter
        updateDescriptionCounter();
        
        // Form validation
        document.getElementById('addPaymentForm').addEventListener('submit', function(e) {
            const memberId = document.getElementById('member_id').value;
            const amount = document.getElementById('amount').value;
            const paymentMethod = document.getElementById('payment_method').value;
            const paymentDate = document.getElementById('payment_date').value;
            const dueDate = document.getElementById('due_date').value;
            const status = document.getElementById('status').value;
            
            // Check required fields
            if(!memberId) {
                e.preventDefault();
                alert('Please select a member!');
                return false;
            }
            
            if(!amount || parseFloat(amount) <= 0) {
                e.preventDefault();
                alert('Please enter a valid payment amount!');
                return false;
            }
            
            if(!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method!');
                return false;
            }
            
            if(!paymentDate) {
                e.preventDefault();
                alert('Please select a payment date!');
                return false;
            }
            
            // Validate dates
            if(dueDate && paymentDate) {
                const due = new Date(dueDate);
                const payment = new Date(paymentDate);
                
                if(due < payment) {
                    e.preventDefault();
                    alert('Due date cannot be before payment date!');
                    return false;
                }
            }
            
            // Validate amount format
            if(isNaN(amount) || parseFloat(amount) <= 0) {
                e.preventDefault();
                alert('Amount must be a valid positive number!');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recording Payment...';
            submitBtn.disabled = true;
            
            return true;
        });
        
        // Update step indicator based on form completion
        function updateStepIndicator(step) {
            document.querySelectorAll('.step').forEach((s, index) => {
                if(index < step) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        }
        
        // Auto-update step indicator as user fills form
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', function() {
                // Determine which step is being completed
                if(document.getElementById('member_id').value) {
                    updateStepIndicator(2);
                }
                if(this.id === 'amount' || this.id === 'payment_date' || document.getElementById('payment_method').value) {
                    updateStepIndicator(3);
                }
            });
        });
        
        // Initialize step indicator based on form state
        document.addEventListener('DOMContentLoaded', function() {
            // Check if any fields in step 2 are filled
            const step2Filled = document.getElementById('member_id').value !== '';
            
            // Check if any fields in step 3 are filled
            const step3Filled = document.getElementById('amount').value !== '' || 
                               document.getElementById('payment_date').value !== '' || 
                               document.getElementById('payment_method').value !== '';
            
            if(step3Filled) {
                updateStepIndicator(3);
            } else if(step2Filled) {
                updateStepIndicator(2);
            } else {
                updateStepIndicator(1);
            }
            
            // Auto-set due date to one month from today if empty
            if(!document.getElementById('due_date').value) {
                const today = new Date();
                const nextMonth = new Date(today);
                nextMonth.setMonth(today.getMonth() + 1);
                const formatted = nextMonth.toISOString().split('T')[0];
                document.getElementById('due_date').value = formatted;
            }
            
            // Initialize preview
            updatePreview();
            
            // Update member info if member is already selected
            if(document.getElementById('member_id').value) {
                updateMemberInfo();
            }
        });
        
        // Format amount input on blur
        document.getElementById('amount').addEventListener('blur', function() {
            if(this.value) {
                const value = parseFloat(this.value);
                if(!isNaN(value)) {
                    this.value = value.toFixed(2);
                    updatePreview();
                }
            }
        });
        
        // Auto-generate transaction ID if empty
        document.getElementById('transaction_id').addEventListener('focus', function() {
            if(!this.value) {
                const now = new Date();
                const timestamp = now.getFullYear().toString().substr(-2) + 
                                ('0' + (now.getMonth() + 1)).slice(-2) + 
                                ('0' + now.getDate()).slice(-2) + 
                                ('0' + now.getHours()).slice(-2) + 
                                ('0' + now.getMinutes()).slice(-2);
                const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
                this.value = 'TXN-' + timestamp + '-' + random;
            }
        });
    </script>
</body>
</html>