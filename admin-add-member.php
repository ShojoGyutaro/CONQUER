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

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $age = $_POST['age'] ?? '';
    $contactNumber = $_POST['contact_number'] ?? '';
    $membershipPlan = $_POST['membership_plan'] ?? '';
    $membershipStatus = $_POST['membership_status'] ?? 'Active';
    
    // Validate inputs
    $errors = [];
    
    if(empty($fullName)) $errors[] = 'Full name is required';
    if(empty($email)) $errors[] = 'Email is required';
    if(empty($username)) $errors[] = 'Username is required';
    if(empty($password)) $errors[] = 'Password is required';
    if(empty($age) || $age < 16 || $age > 100) $errors[] = 'Valid age (16-100) is required';
    if(empty($contactNumber)) $errors[] = 'Contact number is required';
    if(empty($membershipPlan)) $errors[] = 'Membership plan is required';
    
    if(empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, full_name, user_type, is_active) 
                VALUES (?, ?, ?, ?, 'member', 1)
            ");
            $stmt->execute([$username, $email, $passwordHash, $fullName]);
            $userId = $pdo->lastInsertId();
            
            // Insert into gym_members table
            $stmt = $pdo->prepare("
                INSERT INTO gym_members (Name, Age, MembershipPlan, ContactNumber, Email, MembershipStatus) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fullName, $age, $membershipPlan, $contactNumber, $email, $membershipStatus]);
            
            $pdo->commit();
            
            $successMessage = "Member '$fullName' added successfully!";
            
            // Clear form
            $_POST = [];
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $errorMessage = 'Error adding member: ' . $e->getMessage();
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
    <title>Add New Member | CONQUER Gym Admin</title>
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
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .strength-weak {
            color: var(--danger-color, #ff4757);
        }
        
        .strength-medium {
            color: var(--warning-color, #ffa502);
        }
        
        .strength-strong {
            color: var(--success-color, #2ed573);
        }
        
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
        
        /* Membership Plans */
        .membership-plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .plan-card {
            border: 2px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .plan-card:hover {
            border-color: var(--primary-color, #ff4757);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md, 0 4px 20px rgba(0,0,0,0.15));
        }
        
        .plan-card.selected {
            border-color: var(--primary-color, #ff4757);
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.05), rgba(255, 71, 87, 0.02));
        }
        
        .plan-card.selected::before {
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
        
        .plan-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .plan-price {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary-color, #ff4757);
            margin-bottom: 0.5rem;
        }
        
        .plan-price span {
            font-size: 0.9rem;
            font-weight: 400;
            color: var(--text-light, #6c757d);
        }
        
        .plan-card p {
            color: var(--text-light, #6c757d);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .plan-features {
            list-style: none;
            text-align: left;
            margin-top: 1rem;
        }
        
        .plan-features li {
            padding: 0.4rem 0;
            color: var(--text-light, #6c757d);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .plan-features li i {
            font-size: 0.8rem;
        }
        
        .plan-features li i.fa-check {
            color: var(--success-color, #2ed573);
        }
        
        .plan-features li i.fa-times {
            color: var(--danger-color, #ff4757);
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
        
        /* Password match indicator */
        #passwordMatch {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        #passwordMatch i {
            font-size: 0.8rem;
        }
        
        /* Responsive Design - SAME AS DASHBOARD */
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
            
            .membership-plans {
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
                    <input type="text" placeholder="Search members, classes, equipment...">
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
                        <i class="fas fa-user-plus"></i>
                        Add New Member
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
                    <form method="POST" class="add-form" id="addMemberForm">
                        <div class="form-header">
                            <h2><i class="fas fa-user-plus"></i> Add New Member</h2>
                            <p>Complete the form below to register a new gym member. All fields marked with * are required.</p>
                        </div>
                        
                        <div class="form-steps">
                            <div class="step active">
                                <div class="step-number">1</div>
                                <span>Basic Info</span>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <span>Account Setup</span>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <span>Membership</span>
                            </div>
                        </div>
                        
                        <!-- Section 1: Basic Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user"></i> Personal Information</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="full_name" class="required">Full Name</label>
                                    <input type="text" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                           placeholder="Enter full name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="age" class="required">Age</label>
                                    <input type="number" id="age" name="age" 
                                           value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>" 
                                           min="16" max="100" placeholder="Enter age" required>
                                    <small>Must be between 16-100 years</small>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email" class="required">Email Address</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           placeholder="Enter email address" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contact_number" class="required">Contact Number</label>
                                    <input type="tel" id="contact_number" name="contact_number" 
                                           value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>" 
                                           placeholder="Enter contact number" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 2: Account Setup -->
                        <div class="form-section">
                            <h3><i class="fas fa-key"></i> Account Setup</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username" class="required">Username</label>
                                    <input type="text" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                           placeholder="Enter username" required>
                                    <small>This will be used for login</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password" class="required">Password</label>
                                    <input type="password" id="password" name="password" 
                                           value="<?php echo htmlspecialchars($_POST['password'] ?? ''); ?>" 
                                           placeholder="Enter password" required>
                                    <div class="password-strength" id="passwordStrength"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="required">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       value="<?php echo htmlspecialchars($_POST['confirm_password'] ?? ''); ?>" 
                                       placeholder="Confirm password" required>
                                <div id="passwordMatch" style="margin-top: 0.5rem; font-size: 0.85rem;"></div>
                            </div>
                        </div>
                        
                        <!-- Section 3: Membership -->
                        <div class="form-section">
                            <h3><i class="fas fa-id-card"></i> Membership Details</h3>
                            
                            <div class="form-group">
                                <label class="required">Select Membership Plan</label>
                                <div class="membership-plans" id="membershipPlans">
                                    <div class="plan-card" data-plan="Warrior" data-price="29.99">
                                        <div class="plan-name">Warrior</div>
                                        <div class="plan-price">$29.99<span>/month</span></div>
                                        <p>Basic access for beginners</p>
                                        <ul class="plan-features">
                                            <li><i class="fas fa-check"></i> Gym Access</li>
                                            <li><i class="fas fa-check"></i> Locker Room</li>
                                            <li><i class="fas fa-times"></i> Group Classes</li>
                                            <li><i class="fas fa-times"></i> Personal Training</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="plan-card" data-plan="Champion" data-price="49.99">
                                        <div class="plan-name">Champion</div>
                                        <div class="plan-price">$49.99<span>/month</span></div>
                                        <p>Most popular choice</p>
                                        <ul class="plan-features">
                                            <li><i class="fas fa-check"></i> Gym Access</li>
                                            <li><i class="fas fa-check"></i> Locker Room</li>
                                            <li><i class="fas fa-check"></i> Group Classes</li>
                                            <li><i class="fas fa-times"></i> Personal Training</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="plan-card" data-plan="Legend" data-price="79.99">
                                        <div class="plan-name">Legend</div>
                                        <div class="plan-price">$79.99<span>/month</span></div>
                                        <p>Premium package</p>
                                        <ul class="plan-features">
                                            <li><i class="fas fa-check"></i> Gym Access</li>
                                            <li><i class="fas fa-check"></i> Locker Room</li>
                                            <li><i class="fas fa-check"></i> Group Classes</li>
                                            <li><i class="fas fa-check"></i> Personal Training</li>
                                        </ul>
                                    </div>
                                </div>
                                <input type="hidden" id="membership_plan" name="membership_plan" 
                                       value="<?php echo htmlspecialchars($_POST['membership_plan'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="membership_status" class="required">Membership Status</label>
                                    <select id="membership_status" name="membership_status" required>
                                        <option value="Active" <?php echo ($_POST['membership_status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo ($_POST['membership_status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="Suspended" <?php echo ($_POST['membership_status'] ?? '') === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i>
                                Add Member
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin-members.php'">
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
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            let strength = 0;
            let message = '';
            let strengthClass = '';
            
            if(password.length >= 8) strength++;
            if(/[A-Z]/.test(password)) strength++;
            if(/[0-9]/.test(password)) strength++;
            if(/[^A-Za-z0-9]/.test(password)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    message = 'Weak';
                    strengthClass = 'strength-weak';
                    break;
                case 2:
                case 3:
                    message = 'Medium';
                    strengthClass = 'strength-medium';
                    break;
                case 4:
                    message = 'Strong';
                    strengthClass = 'strength-strong';
                    break;
            }
            
            strengthDiv.innerHTML = `<i class="fas fa-shield-alt"></i> <span class="${strengthClass}">${message} password</span>`;
        });
        
        // Password confirmation check
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if(confirmPassword === '') {
                matchDiv.innerHTML = '';
                matchDiv.style.color = '';
            } else if(password === confirmPassword) {
                matchDiv.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
                matchDiv.style.color = 'var(--success-color)';
            } else {
                matchDiv.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
                matchDiv.style.color = 'var(--danger-color)';
            }
        });
        
        // Membership plan selection
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                document.querySelectorAll('.plan-card').forEach(c => {
                    c.classList.remove('selected');
                });
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Update hidden input value
                const plan = this.getAttribute('data-plan');
                document.getElementById('membership_plan').value = plan;
                
                // Update step indicator
                updateStepIndicator(3);
            });
        });
        
        // Auto-select previously selected plan
        const selectedPlan = "<?php echo $_POST['membership_plan'] ?? ''; ?>";
        if(selectedPlan) {
            document.querySelectorAll('.plan-card').forEach(card => {
                if(card.getAttribute('data-plan') === selectedPlan) {
                    card.classList.add('selected');
                }
            });
        }
        
        // Form validation
        document.getElementById('addMemberForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const membershipPlan = document.getElementById('membership_plan').value;
            
            // Check password match
            if(password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            // Check password strength
            if(password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            // Check membership plan selected
            if(!membershipPlan) {
                e.preventDefault();
                alert('Please select a membership plan!');
                return false;
            }
            
            // Check username availability (simulated)
            const username = document.getElementById('username').value;
            if(username.length < 3) {
                e.preventDefault();
                alert('Username must be at least 3 characters long!');
                return false;
            }
            
            // Check age
            const age = document.getElementById('age').value;
            if(age < 16 || age > 100) {
                e.preventDefault();
                alert('Age must be between 16 and 100!');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Member...';
            submitBtn.disabled = true;
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
        document.querySelectorAll('input, select').forEach(field => {
            field.addEventListener('input', function() {
                // Determine which step is being completed
                if(this.id === 'full_name' || this.id === 'age' || this.id === 'email' || this.id === 'contact_number') {
                    updateStepIndicator(2);
                } else if(this.id === 'username' || this.id === 'password' || this.id === 'confirm_password') {
                    updateStepIndicator(3);
                }
            });
        });
        
        // Initialize step indicator based on form state
        document.addEventListener('DOMContentLoaded', function() {
            // Check if any fields in step 2 are filled
            const step2Fields = ['full_name', 'age', 'email', 'contact_number'];
            const step2Filled = step2Fields.some(id => {
                const field = document.getElementById(id);
                return field && field.value.trim() !== '';
            });
            
            // Check if any fields in step 3 are filled
            const step3Fields = ['username', 'password', 'confirm_password'];
            const step3Filled = step3Fields.some(id => {
                const field = document.getElementById(id);
                return field && field.value.trim() !== '';
            });
            
            if(step3Filled) {
                updateStepIndicator(3);
            } else if(step2Filled) {
                updateStepIndicator(2);
            } else {
                updateStepIndicator(1);
            }
        });
    </script>
</body>
</html>