<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// =============================================
// HANDLE FORM SUBMISSION ON SAME PAGE
// =============================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reference_number'])) {
    $user_id = $_SESSION['user_id'];
    $message = '';
    $success = false;
    
    try {
        require_once 'config/database.php';
        $database = Database::getInstance();
        $pdo = $database->getConnection();
        
        // Debug log
        error_log("=== PAYMENT FORM SUBMITTED ON SAME PAGE ===");
        
        // Get form data
        $payment_method = $_POST['payment_method'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $subscription_period = $_POST['subscription_period'] ?? '';
        $reference_number = $_POST['reference_number'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $bank_name = $_POST['bank_name'] ?? '';
        
        // Validate
        if(empty($payment_method) || empty($reference_number) || $amount <= 0) {
            throw new Exception("Invalid form data");
        }
        
        // Handle file upload
        $receipt_image = null;
        if(isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['receipt_image'];
            $upload_dir = 'uploads/receipts/';
            
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp'];
            
            if(in_array($file_ext, $allowed_ext)) {
                $filename = 'receipt_' . time() . '_' . $user_id . '.' . $file_ext;
                $destination = $upload_dir . $filename;
                
                if(move_uploaded_file($file['tmp_name'], $destination)) {
                    $receipt_image = $destination;
                }
            }
        }
        
        // Create payments table if it doesn't exist
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'payments'");
        if($tableCheck->rowCount() == 0) {
            $sql = "CREATE TABLE payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                reference_number VARCHAR(50) NOT NULL UNIQUE,
                payment_method VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                subscription_period VARCHAR(100),
                bank_name VARCHAR(100),
                receipt_image VARCHAR(255),
                notes TEXT,
                status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
                payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
        }
        
        // Insert payment
        $stmt = $pdo->prepare("
            INSERT INTO payments (
                user_id, reference_number, payment_method, amount, 
                subscription_period, bank_name, receipt_image, notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $result = $stmt->execute([
            $user_id, $reference_number, $payment_method, $amount,
            $subscription_period, $bank_name, $receipt_image, $notes
        ]);
        
        if($result) {
            $success = true;
            $message = "Payment submitted successfully! Reference: $reference_number";
            $_SESSION['payment_success'] = $message;
        } else {
            throw new Exception("Failed to save payment");
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $_SESSION['payment_error'] = $message;
    }
    
    // Redirect to same page to show message
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
// =============================================
// END FORM HANDLING
// =============================================

// Check if user is logged in
if(!isset($_SESSION['user_id'], $_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

// Initialize all variables with default values
$message = '';
$success = false;
$user_id = $_SESSION['user_id'];
$user = null;
$member = null;
$payments = [];
$totalPaid = 0;
$pendingAmount = 0;
$completedCount = 0;
$pendingCount = 0;
$dueAmount = 0;
$paymentRef = 'CONQ' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

// Define GCash and PayMaya QR codes and account details
$gcash_qr_code = 'assets/qr-codes/gcash-qr.png';
$paymaya_qr_code = 'assets/qr-codes/paymaya-qr.png';

// Bank account details
$bank_accounts = [
    'BDO' => [
        'account_name' => 'CONQUER GYM INC.',
        'account_number' => '1234-5678-9012',
        'branch' => 'SM Megamall Branch'
    ],
    'BPI' => [
        'account_name' => 'CONQUER GYM INC.',
        'account_number' => '9876-5432-1098',
        'branch' => 'Ortigas Center Branch'
    ],
    'Metrobank' => [
        'account_name' => 'CONQUER GYM INC.',
        'account_number' => '8765-4321-0987',
        'branch' => 'Makati Branch'
    ],
    'UnionBank' => [
        'account_name' => 'CONQUER GYM INC.',
        'account_number' => '7654-3210-9876',
        'branch' => 'Bonifacio Global City'
    ]
];

// Check for success/error messages
if(isset($_SESSION['payment_success'])) {
    $message = $_SESSION['payment_success'];
    $success = true;
    unset($_SESSION['payment_success']);
}

if(isset($_SESSION['payment_error'])) {
    $message = $_SESSION['payment_error'];
    $success = false;
    unset($_SESSION['payment_error']);
}

// Try to connect to database
try {
    require_once 'config/database.php';
    $database = Database::getInstance();
    $pdo = $database->getConnection();

    // Get user info
    $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$user) {
        // User not found, but continue with session
        $user = ['full_name' => 'Member', 'email' => ''];
    }
    
    // Get member info (skip if table doesn't exist)
    try {
        $memberStmt = $pdo->prepare("SELECT * FROM gym_members WHERE Email = ? LIMIT 1");
        $memberStmt->execute([$user['email'] ?? '']);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // Table might not exist
        $member = null;
    }
    
    // Get all payments (check if table exists)
    try {
        $paymentsStmt = $pdo->prepare("
            SELECT * FROM payments 
            WHERE user_id = ? 
            ORDER BY payment_date DESC
        ");
        $paymentsStmt->execute([$user_id]);
        $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // Table might not exist
        $payments = [];
    }
    
    // Calculate totals safely
    if(is_array($payments)) {
        foreach($payments as $payment) {
            if(isset($payment['status']) && $payment['status'] == 'completed') {
                $totalPaid += floatval($payment['amount'] ?? 0);
                $completedCount++;
            } elseif(isset($payment['status']) && $payment['status'] == 'pending') {
                $pendingAmount += floatval($payment['amount'] ?? 0);
                $pendingCount++;
            }
        }
    }
    
    // Calculate due amount based on membership plan
    if($member && isset($member['MembershipPlan'])) {
        $planPrice = 0;
        $planName = $member['MembershipPlan'];
        if(strpos($planName, 'Legend') !== false) {
            $planPrice = 49.99;
        } elseif(strpos($planName, 'Champion') !== false) {
            $planPrice = 79.99;
        } else {
            $planPrice = 29.99;
        }
        
        // Check if user has paid for current month
        $currentMonth = date('Y-m');
        $paidThisMonth = false;
        foreach($payments as $payment) {
            if(isset($payment['status']) && $payment['status'] == 'completed' && 
               isset($payment['payment_date']) &&
               date('Y-m', strtotime($payment['payment_date'])) == $currentMonth) {
                $paidThisMonth = true;
                break;
            }
        }
        
        if(!$paidThisMonth) {
            $dueAmount = $planPrice;
        }
    }
    
} catch(PDOException $e) {
    $message = "Database error. Please try again later.";
    error_log("Payments page error: " . $e->getMessage());
} catch(Exception $e) {
    $message = "An error occurred. Please try again.";
    error_log("General error: " . $e->getMessage());
}

// Get user name safely
$user_full_name = $user['full_name'] ?? 'Member';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    
    <style>
        .payments-content {
            padding: 1.5rem;
        }
        
        .payments-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            margin: 0 0 1rem 0;
            color: #333;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #ff4757;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-top: 4px solid #ff4757;
        }
        
        .stat-card.total {
            border-color: #2ed573;
        }
        
        .stat-card.pending {
            border-color: #ffa502;
        }
        
        .stat-card.due {
            border-color: #ff4757;
        }
        
        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            color: #333;
            font-size: 1.75rem;
        }
        
        .stat-card p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Payment Methods */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .method-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 140px;
        }
        
        .method-card:hover, .method-card.active {
            border-color: #ff4757;
            background: rgba(255, 71, 87, 0.05);
            transform: translateY(-2px);
        }
        
        .method-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .method-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #ff4757;
        }
        
        .method-card h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
            font-size: 1.1rem;
        }
        
        .method-card p {
            margin: 0;
            color: #666;
            font-size: 0.85rem;
        }
        
        /* Payment Fields */
        .payment-fields {
            display: none;
            animation: fadeIn 0.3s ease;
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1.5rem 0;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff4757;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        /* QR Code Display */
        .qr-container {
            text-align: center;
            padding: 1.5rem;
            background: white;
            border-radius: 10px;
            border: 2px dashed #e0e0e0;
            margin: 1rem 0;
        }
        
        .qr-code {
            width: 200px;
            height: 200px;
            margin: 0 auto 1rem;
            background: white;
            padding: 10px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }
        
        .qr-code img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .payment-instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
        
        .payment-instructions ol {
            margin: 0.5rem 0 0 1.5rem;
            padding: 0;
        }
        
        .payment-instructions li {
            margin-bottom: 0.5rem;
        }
        
        /* Bank Details */
        .bank-details {
            background: #e8f4fd;
            border: 1px solid #b3e0ff;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        
        .bank-info {
            margin-bottom: 1rem;
        }
        
        .bank-info p {
            margin: 0.25rem 0;
            font-size: 0.95rem;
        }
        
        .reference-number {
            background: #333;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 1.1rem;
            text-align: center;
            margin: 1rem 0;
            letter-spacing: 1px;
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn-primary {
            background: #ff4757;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary:hover {
            background: #ff2e43;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
        }
        
        .btn-secondary {
            background: white;
            color: #333;
            border: 1px solid #ddd;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #ccc;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            margin: 1.5rem 0;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            color: #333;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
            font-size: 0.9rem;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
            font-size: 0.9rem;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-completed {
            background: rgba(46, 213, 115, 0.1);
            color: #2ed573;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffa502;
        }
        
        .status-failed {
            background: rgba(255, 56, 56, 0.1);
            color: #ff3838;
        }
        
        /* Action Buttons */
        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            text-decoration: none;
        }
        
        .action-btn.view {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .action-btn.view:hover {
            background: #e9ecef;
        }
        
        .action-btn.download {
            background: #3498db;
            color: white;
        }
        
        .action-btn.download:hover {
            background: #2980b9;
        }
        
        /* Receipt Modal */
        .receipt-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #ff4757;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .close-modal:hover {
            color: #ff4757;
        }
        
        .receipt-details {
            margin: 1.5rem 0;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        
        .receipt-item strong {
            color: #333;
        }
        
        .receipt-item span {
            color: #666;
        }
        
        .receipt-image-container {
            margin: 1.5rem 0;
            text-align: center;
        }
        
        .receipt-image {
            max-width: 100%;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .alert-success {
            background: rgba(46, 213, 115, 0.1);
            border: 1px solid rgba(46, 213, 115, 0.2);
            color: #2ed573;
        }
        
        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.2);
            color: #ffa502;
        }
        
        .alert-danger {
            background: rgba(255, 56, 56, 0.1);
            border: 1px solid rgba(255, 56, 56, 0.2);
            color: #ff3838;
        }
        
        /* Empty State */
        .no-payments {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }
        
        .no-payments i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }
        
        /* Filter Buttons */
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        
        .filter-btn.active {
            background: #ff4757;
            color: white;
            border-color: #ff4757;
        }
        
        .filter-btn:hover:not(.active) {
            background: #f8f9fa;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
            }
            
            .action-btn {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
            
            .qr-code {
                width: 150px;
                height: 150px;
            }
        }
        
        @media (max-width: 480px) {
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .method-card {
                min-height: 120px;
                padding: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions button {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Print Styles for Receipt */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .modal-content,
            .modal-content * {
                visibility: visible;
            }
            
            .modal-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                border: none;
            }
            
            .close-modal,
            .modal-actions {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'user-sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search payments..." id="searchPayments">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification">
                    <i class="fas fa-bell"></i>
                    <?php if($pendingCount > 0): ?>
                        <span class="notification-badge"><?php echo $pendingCount; ?></span>
                    <?php endif; ?>
                </button>
                <button class="btn-primary" onclick="showPaymentForm()">
                    <i class="fas fa-credit-card"></i> Make Payment
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>My Payments 💳</h1>
                    <p>View your payment history and manage billing</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3>$<?php echo number_format($totalPaid, 2); ?></h3>
                        <p>Total Paid</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $completedCount; ?></h3>
                        <p>Payments</p>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <?php if($pendingCount > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                You have <?php echo $pendingCount; ?> pending payment(s) awaiting confirmation.
            </div>
            <?php endif; ?>
            
            <?php if($dueAmount > 0): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                You have a payment due of $<?php echo number_format($dueAmount, 2); ?> for this month.
            </div>
            <?php endif; ?>
            
            <?php if($message): ?>
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="payments-content">
                <!-- Payment Overview -->
                <div class="payments-section">
                    <h3 class="section-title">Payment Overview</h3>
                    <div class="stats-grid">
                        <div class="stat-card total">
                            <h3>$<?php echo number_format($totalPaid, 2); ?></h3>
                            <p>Total Paid</p>
                        </div>
                        <div class="stat-card pending">
                            <h3>$<?php echo number_format($pendingAmount, 2); ?></h3>
                            <p>Pending Balance</p>
                        </div>
                        <div class="stat-card due">
                            <h3>$<?php echo number_format($dueAmount, 2); ?></h3>
                            <p>Amount Due</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo is_array($payments) ? count($payments) : 0; ?></h3>
                            <p>Total Transactions</p>
                        </div>
                    </div>
                </div>

                <!-- Make Payment Form -->
                <div class="payments-section" id="paymentFormSection" style="display: none;">
                    <h3 class="section-title">Make a Payment</h3>
                    <form id="paymentForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
                        <!-- Payment Methods -->
                        <div class="payment-methods">
                            <label class="method-card" data-method="credit_card">
                                <input type="radio" name="payment_method" value="credit_card">
                                <i class="fas fa-credit-card"></i>
                                <h4>Credit Card</h4>
                                <p>Visa, MasterCard, Amex</p>
                            </label>
                            
                            <label class="method-card" data-method="gcash">
                                <input type="radio" name="payment_method" value="gcash">
                                <i class="fas fa-mobile-alt"></i>
                                <h4>GCash</h4>
                                <p>Scan QR Code</p>
                            </label>
                            
                            <label class="method-card" data-method="paymaya">
                                <input type="radio" name="payment_method" value="paymaya">
                                <i class="fas fa-wallet"></i>
                                <h4>PayMaya</h4>
                                <p>Scan QR Code</p>
                            </label>
                            
                            <label class="method-card" data-method="bank_transfer">
                                <input type="radio" name="payment_method" value="bank_transfer">
                                <i class="fas fa-university"></i>
                                <h4>Bank Transfer</h4>
                                <p>Direct bank deposit</p>
                            </label>
                            
                            <label class="method-card" data-method="cash">
                                <input type="radio" name="payment_method" value="cash" checked>
                                <i class="fas fa-money-bill-wave"></i>
                                <h4>Cash at Reception</h4>
                                <p>Pay with printed receipt</p>
                            </label>
                        </div>
                        
                        <!-- Payment Amount -->
                        <div class="form-group">
                            <label for="amount">Amount ($) *</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="1" required 
                                   value="<?php echo $dueAmount > 0 ? $dueAmount : '29.99'; ?>">
                        </div>
                        
                        <!-- Payment Description -->
                        <div class="form-group">
                            <label for="subscription_period">Payment For *</label>
                            <select id="subscription_period" name="subscription_period" required>
                                <option value="">Select what you're paying for</option>
                                <option value="Monthly Membership" selected>Monthly Membership</option>
                                <option value="Annual Membership">Annual Membership</option>
                                <option value="Personal Training">Personal Training</option>
                                <option value="Class Package">Class Package</option>
                                <option value="Other Services">Other Services</option>
                            </select>
                        </div>
                        
                        <!-- Payment Reference Number -->
                        <div class="reference-number">
                            Reference: <?php echo $paymentRef; ?>
                            <input type="hidden" name="reference_number" value="<?php echo $paymentRef; ?>">
                        </div>
                        
                        <!-- Credit Card Fields -->
                        <div id="creditCardFields" class="payment-fields">
                            <h4 style="margin-bottom: 1rem; color: #333;">Credit Card Details</h4>
                            <div class="form-group">
                                <label for="card_number">Card Number</label>
                                <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="expiry_date">Expiry Date (MM/YY)</label>
                                    <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" maxlength="5">
                                </div>
                                <div class="form-group">
                                    <label for="cvv">CVV</label>
                                    <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="cardholder_name">Cardholder Name</label>
                                <input type="text" id="cardholder_name" name="cardholder_name" placeholder="As shown on card">
                            </div>
                        </div>
                        
                        <!-- GCash QR Code -->
                        <div id="gcashFields" class="payment-fields">
                            <div class="qr-container">
                                <h4 style="margin-bottom: 1rem; color: #333;">Scan GCash QR Code</h4>
                                <div class="qr-code">
                                    <?php if(file_exists($gcash_qr_code)): ?>
                                        <img src="<?php echo $gcash_qr_code; ?>" alt="GCash QR Code">
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                                            <i class="fas fa-qrcode" style="font-size: 3rem;"></i>
                                            <span style="margin-left: 10px;">GCash QR</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="payment-instructions">
                                    <strong>Instructions:</strong>
                                    <ol>
                                        <li>Open GCash app</li>
                                        <li>Tap "Scan QR"</li>
                                        <li>Scan the QR code above</li>
                                        <li>Enter amount: $<span id="gcashAmount"><?php echo $dueAmount > 0 ? $dueAmount : '29.99'; ?></span></li>
                                        <li>Add reference number: <strong><?php echo $paymentRef; ?></strong></li>
                                        <li>Complete the payment</li>
                                        <li>Upload screenshot of receipt below</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="gcash_receipt">Upload Payment Screenshot *</label>
                                <input type="file" id="gcash_receipt" name="receipt_image" accept="image/*">
                                <small style="color: #666;">Required for verification. Upload screenshot of successful payment.</small>
                            </div>
                        </div>
                        
                        <!-- PayMaya QR Code -->
                        <div id="paymayaFields" class="payment-fields">
                            <div class="qr-container">
                                <h4 style="margin-bottom: 1rem; color: #333;">Scan PayMaya QR Code</h4>
                                <div class="qr-code">
                                    <?php if(file_exists($paymaya_qr_code)): ?>
                                        <img src="<?php echo $paymaya_qr_code; ?>" alt="PayMaya QR Code">
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                                            <i class="fas fa-qrcode" style="font-size: 3rem;"></i>
                                            <span style="margin-left: 10px;">PayMaya QR</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="payment-instructions">
                                    <strong>Instructions:</strong>
                                    <ol>
                                        <li>Open PayMaya app</li>
                                        <li>Tap "Scan to Pay"</li>
                                        <li>Scan the QR code above</li>
                                        <li>Enter amount: $<span id="paymayaAmount"><?php echo $dueAmount > 0 ? $dueAmount : '29.99'; ?></span></li>
                                        <li>Add reference number: <strong><?php echo $paymentRef; ?></strong></li>
                                        <li>Complete the payment</li>
                                        <li>Upload screenshot of receipt below</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="paymaya_receipt">Upload Payment Screenshot *</label>
                                <input type="file" id="paymaya_receipt" name="receipt_image" accept="image/*">
                                <small style="color: #666;">Required for verification. Upload screenshot of successful payment.</small>
                            </div>
                        </div>
                        
                        <!-- Bank Transfer Details -->
                        <div id="bankTransferFields" class="payment-fields">
                            <div class="bank-details">
                                <h4 style="margin-bottom: 1rem; color: #333;">Bank Transfer Details</h4>
                                <div class="bank-info">
                                    <p><strong>Account Name:</strong> CONQUER GYM INC.</p>
                                    <div class="form-group">
                                        <label for="bank_name">Select Bank</label>
                                        <select id="bank_name" name="bank_name">
                                            <option value="">Choose your bank</option>
                                            <?php foreach($bank_accounts as $bank => $details): ?>
                                                <option value="<?php echo $bank; ?>"><?php echo $bank; ?></option>
                                            <?php endforeach; ?>
                                            <option value="Other">Other Bank</option>
                                        </select>
                                    </div>
                                    <div id="bankDetails" style="display: none;">
                                        <p><strong>Account Number:</strong> <span id="accountNumber"></span></p>
                                        <p><strong>Branch:</strong> <span id="branch"></span></p>
                                    </div>
                                </div>
                                <div class="payment-instructions">
                                    <strong>Instructions:</strong>
                                    <ol>
                                        <li>Go to your bank's online/app banking</li>
                                        <li>Send payment to account details above</li>
                                        <li>Enter amount: $<span id="bankAmount"><?php echo $dueAmount > 0 ? $dueAmount : '29.99'; ?></span></li>
                                        <li>Add reference number: <strong><?php echo $paymentRef; ?></strong></li>
                                        <li>Complete the transfer</li>
                                        <li>Upload screenshot of transaction below</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="bank_receipt">Upload Transaction Screenshot *</label>
                                <input type="file" id="bank_receipt" name="receipt_image" accept="image/*">
                                <small style="color: #666;">Required for verification. Upload screenshot of successful transfer.</small>
                            </div>
                        </div>
                        
                        <!-- Cash Payment -->
                        <div id="cashFields" class="payment-fields">
                            <div class="payment-instructions" style="background: #d4edda; border-color: #c3e6cb;">
                                <h4 style="color: #155724; margin-top: 0;">Cash Payment Instructions</h4>
                                <p><strong>Reference Number:</strong> <span style="font-family: monospace;"><?php echo $paymentRef; ?></span></p>
                                <ol>
                                    <li>Save or print this reference number</li>
                                    <li>Go to CONQUER Gym reception</li>
                                    <li>Present the reference number to staff</li>
                                    <li>Pay exact amount: $<span id="cashAmount"><?php echo $dueAmount > 0 ? $dueAmount : '29.99'; ?></span></li>
                                    <li>Receive official receipt from staff</li>
                                    <li>Payment will be marked as completed within 24 hours</li>
                                </ol>
                            </div>
                            <div class="form-group">
                                <label for="notes">Additional Notes (Optional)</label>
                                <textarea id="notes" name="notes" rows="3" placeholder="Any special instructions or notes for the staff..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> Submit Payment
                            </button>
                            <button type="button" class="btn-secondary" onclick="hidePaymentForm()">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Payment History -->
                <div class="payments-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 class="section-title" style="margin: 0;">Payment History</h3>
                    </div>
                    
                    <!-- Filter Buttons -->
                    <div class="filter-buttons">
                        <button class="filter-btn active" onclick="filterPayments('all')">All</button>
                        <button class="filter-btn" onclick="filterPayments('completed')">Completed</button>
                        <button class="filter-btn" onclick="filterPayments('pending')">Pending</button>
                        <button class="filter-btn" onclick="filterPayments('failed')">Failed</button>
                    </div>
                    
                    <?php if(is_array($payments) && count($payments) > 0): ?>
                        <div class="table-container">
                            <table id="paymentsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payments as $payment): 
                                        $statusClass = '';
                                        $status = $payment['status'] ?? 'pending';
                                        switch($status) {
                                            case 'completed': $statusClass = 'status-completed'; break;
                                            case 'pending': $statusClass = 'status-pending'; break;
                                            case 'failed': $statusClass = 'status-failed'; break;
                                            default: $statusClass = 'status-pending';
                                        }
                                        
                                        // Format payment method
                                        $method = $payment['payment_method'] ?? '';
                                        $methodName = ucwords(str_replace('_', ' ', $method));
                                        if($method == 'gcash') $methodName = 'GCash';
                                        if($method == 'paymaya') $methodName = 'PayMaya';
                                        
                                        // Format date
                                        $paymentDate = isset($payment['payment_date']) ? date('M j, Y', strtotime($payment['payment_date'])) : date('M j, Y');
                                    ?>
                                        <tr class="payment-row" data-status="<?php echo $status; ?>">
                                            <td><?php echo $paymentDate; ?></td>
                                            <td><code style="font-size: 0.85rem;"><?php echo $payment['reference_number'] ?? $paymentRef; ?></code></td>
                                            <td><?php echo htmlspecialchars($payment['subscription_period'] ?? 'Membership Fee'); ?></td>
                                            <td><strong>$<?php echo isset($payment['amount']) ? number_format($payment['amount'], 2) : '0.00'; ?></strong></td>
                                            <td><?php echo $methodName; ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <button class="action-btn view" onclick="viewPaymentDetails(<?php echo htmlspecialchars(json_encode($payment)); ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <?php if(isset($payment['receipt_image']) && !empty($payment['receipt_image']) && $payment['status'] == 'completed'): ?>
                                                        <button class="action-btn download" onclick="viewReceipt('<?php echo $payment['receipt_image']; ?>', '<?php echo $payment['reference_number']; ?>')">
                                                            <i class="fas fa-receipt"></i> Receipt
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-payments">
                            <i class="fas fa-credit-card"></i>
                            <h3>No payment history</h3>
                            <p>Your payment history will appear here once you make your first payment.</p>
                            <button class="btn-primary" style="margin-top: 1rem;" onclick="showPaymentForm()">
                                <i class="fas fa-plus"></i> Make Your First Payment
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Due Section -->
                <?php if($dueAmount > 0): ?>
                <div class="payments-section">
                    <h3 class="section-title">Payment Due</h3>
                    <div style="background: linear-gradient(135deg, #ff4757, #ff6b81); color: white; padding: 1.5rem; border-radius: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <h4 style="margin: 0 0 0.5rem 0; color: white;"><?php echo $member['MembershipPlan'] ?? 'Membership'; ?> Fee</h4>
                                <p style="margin: 0; opacity: 0.9;">Due by <?php echo date('F j, Y', strtotime('+7 days')); ?></p>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">
                                    <i class="fas fa-info-circle"></i> Late payments may result in membership suspension
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <h2 style="margin: 0; color: white;">$<?php echo number_format($dueAmount, 2); ?></h2>
                                <button class="btn-primary" style="margin-top: 1rem; background: white; color: #ff4757; border: none;" onclick="showPaymentForm()">
                                    <i class="fas fa-dollar-sign"></i> Pay Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div id="receiptModal" class="receipt-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Payment Receipt</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="receiptDetails" class="receipt-details">
                <!-- Receipt details will be loaded here -->
            </div>
            <div id="receiptImageContainer" class="receipt-image-container" style="display: none;">
                <h4>Receipt Image</h4>
                <img id="receiptImage" class="receipt-image" src="" alt="Receipt">
            </div>
            <div class="modal-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                <button class="btn-secondary" onclick="closeModal()">Close</button>
                <button class="btn-primary" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Details Modal -->
    <div id="paymentDetailsModal" class="receipt-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Payment Details</h3>
                <button class="close-modal" onclick="closePaymentDetails()">&times;</button>
            </div>
            <div id="paymentDetailsContent" class="receipt-details">
                <!-- Payment details will be loaded here -->
            </div>
            <div class="modal-actions" style="margin-top: 2rem; display: flex; justify-content: flex-end;">
                <button class="btn-secondary" onclick="closePaymentDetails()">Close</button>
            </div>
        </div>
    </div>

    <script>
    // Show/hide payment form
    function showPaymentForm() {
        document.getElementById('paymentFormSection').style.display = 'block';
        window.scrollTo({
            top: document.getElementById('paymentFormSection').offsetTop - 20,
            behavior: 'smooth'
        });
        
        // Activate cash method by default
        setTimeout(() => {
            const cashCard = document.querySelector('.method-card[data-method="cash"]');
            if(cashCard) {
                cashCard.classList.add('active');
                document.getElementById('cashFields').style.display = 'block';
            }
        }, 100);
    }
    
    function hidePaymentForm() {
        document.getElementById('paymentFormSection').style.display = 'none';
        document.querySelectorAll('.method-card').forEach(card => {
            card.classList.remove('active');
        });
        document.querySelectorAll('.payment-fields').forEach(field => {
            field.style.display = 'none';
        });
    }
    
    // Payment method selection
    document.querySelectorAll('.method-card').forEach(card => {
        card.addEventListener('click', function() {
            // Remove active class from all cards
            document.querySelectorAll('.method-card').forEach(c => {
                c.classList.remove('active');
            });
            
            // Add active class to clicked card
            this.classList.add('active');
            
            // Get selected method
            const method = this.getAttribute('data-method');
            
            // Hide all payment fields
            document.querySelectorAll('.payment-fields').forEach(field => {
                field.style.display = 'none';
            });
            
            // Show relevant fields based on method
            if(method === 'credit_card') {
                document.getElementById('creditCardFields').style.display = 'block';
            } else if(method === 'gcash') {
                document.getElementById('gcashFields').style.display = 'block';
                // Update amount in instructions
                document.getElementById('gcashAmount').textContent = document.getElementById('amount').value;
            } else if(method === 'paymaya') {
                document.getElementById('paymayaFields').style.display = 'block';
                document.getElementById('paymayaAmount').textContent = document.getElementById('amount').value;
            } else if(method === 'bank_transfer') {
                document.getElementById('bankTransferFields').style.display = 'block';
                document.getElementById('bankAmount').textContent = document.getElementById('amount').value;
            } else if(method === 'cash') {
                document.getElementById('cashFields').style.display = 'block';
                document.getElementById('cashAmount').textContent = document.getElementById('amount').value;
            }
            
            // Check the radio button
            const radio = this.querySelector('input[type="radio"]');
            if(radio) radio.checked = true;
        });
    });
    
    // Update amount in instructions when amount changes
    const amountInput = document.getElementById('amount');
    if(amountInput) {
        amountInput.addEventListener('input', function() {
            const amount = this.value;
            document.getElementById('gcashAmount').textContent = amount;
            document.getElementById('paymayaAmount').textContent = amount;
            document.getElementById('bankAmount').textContent = amount;
            document.getElementById('cashAmount').textContent = amount;
        });
    }
    
    // Format card number input
    const cardNumberInput = document.getElementById('card_number');
    if(cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})/g, '$1 ').trim();
            e.target.value = value.substring(0, 19);
        });
    }
    
    // Format expiry date input
    const expiryDateInput = document.getElementById('expiry_date');
    if(expiryDateInput) {
        expiryDateInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if(value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value.substring(0, 5);
        });
    }
    
    // Show bank details when bank is selected
    const bankSelect = document.getElementById('bank_name');
    if(bankSelect) {
        bankSelect.addEventListener('change', function() {
            const bankDetails = document.getElementById('bankDetails');
            const bank = this.value;
            
            if(bank && bank !== 'Other') {
                bankDetails.style.display = 'block';
                // Set example bank details
                document.getElementById('accountNumber').textContent = '1234-5678-9012';
                document.getElementById('branch').textContent = 'Main Branch';
            } else {
                bankDetails.style.display = 'none';
            }
        });
    }
    
    // Search payments
    const searchInput = document.getElementById('searchPayments');
    if(searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#paymentsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
    
    // Filter payments by status
    function filterPayments(status) {
        const rows = document.querySelectorAll('.payment-row');
        const buttons = document.querySelectorAll('.filter-btn');
        
        // Update active button
        buttons.forEach(btn => {
            btn.classList.remove('active');
            if(btn.textContent.toLowerCase().includes(status)) {
                btn.classList.add('active');
            }
        });
        
        // Filter rows
        rows.forEach(row => {
            if(status === 'all') {
                row.style.display = '';
            } else {
                const rowStatus = row.getAttribute('data-status');
                row.style.display = rowStatus === status ? '' : 'none';
            }
        });
    }
    
    // View payment details
    function viewPaymentDetails(payment) {
        console.log('Viewing payment details:', payment);
        
        const detailsHtml = `
            <div class="receipt-item">
                <strong>Reference Number:</strong>
                <span>${payment.reference_number || 'N/A'}</span>
            </div>
            <div class="receipt-item">
                <strong>Date:</strong>
                <span>${new Date(payment.payment_date).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                })}</span>
            </div>
            <div class="receipt-item">
                <strong>Payment Method:</strong>
                <span>${formatPaymentMethod(payment.payment_method)}</span>
            </div>
            <div class="receipt-item">
                <strong>Amount:</strong>
                <span>$${parseFloat(payment.amount || 0).toFixed(2)}</span>
            </div>
            <div class="receipt-item">
                <strong>Description:</strong>
                <span>${payment.subscription_period || 'Membership Fee'}</span>
            </div>
            <div class="receipt-item">
                <strong>Status:</strong>
                <span class="status-badge ${getStatusClass(payment.status)}">${payment.status || 'pending'}</span>
            </div>
            ${payment.bank_name ? `
            <div class="receipt-item">
                <strong>Bank Name:</strong>
                <span>${payment.bank_name}</span>
            </div>
            ` : ''}
            ${payment.notes ? `
            <div class="receipt-item">
                <strong>Notes:</strong>
                <span>${payment.notes}</span>
            </div>
            ` : ''}
        `;
        
        document.getElementById('paymentDetailsContent').innerHTML = detailsHtml;
        document.getElementById('paymentDetailsModal').style.display = 'flex';
    }
    
    function closePaymentDetails() {
        document.getElementById('paymentDetailsModal').style.display = 'none';
    }
    
    // View receipt
    function viewReceipt(imagePath, referenceNumber) {
        console.log('Viewing receipt:', imagePath);
        
        // Show receipt details
        document.getElementById('receiptDetails').innerHTML = `
            <div class="receipt-item">
                <strong>Reference Number:</strong>
                <span>${referenceNumber}</span>
            </div>
            <div class="receipt-item">
                <strong>Issued Date:</strong>
                <span>${new Date().toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                })}</span>
            </div>
            <div class="receipt-item">
                <strong>CONQUER GYM INC.</strong>
                <span></span>
            </div>
            <div class="receipt-item">
                <strong>Official Receipt</strong>
                <span></span>
            </div>
            <hr style="margin: 1.5rem 0;">
            <p style="text-align: center; color: #666; font-style: italic;">
                This is your official receipt for payment made to CONQUER GYM.
                Please keep this for your records.
            </p>
        `;
        
        // Show receipt image if it exists and is a valid image path
        const receiptImageContainer = document.getElementById('receiptImageContainer');
        const receiptImage = document.getElementById('receiptImage');
        
        if(imagePath && !imagePath.includes('cash_payment_') && !imagePath.includes('credit_card_payment_') && !imagePath.includes('no_receipt_')) {
            receiptImage.src = imagePath;
            receiptImageContainer.style.display = 'block';
        } else {
            receiptImageContainer.style.display = 'none';
        }
        
        document.getElementById('receiptModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('receiptModal').style.display = 'none';
        document.getElementById('receiptImage').src = '';
    }
    
    function printReceipt() {
        window.print();
    }
    
    // Helper functions
    function formatPaymentMethod(method) {
        if(!method) return 'N/A';
        if(method === 'gcash') return 'GCash';
        if(method === 'paymaya') return 'PayMaya';
        if(method === 'bank_transfer') return 'Bank Transfer';
        if(method === 'credit_card') return 'Credit Card';
        if(method === 'cash') return 'Cash';
        return method.replace('_', ' ');
    }
    
    function getStatusClass(status) {
        switch(status) {
            case 'completed': return 'status-completed';
            case 'pending': return 'status-pending';
            case 'failed': return 'status-failed';
            default: return 'status-pending';
        }
    }
    
    // Form validation and submission - SIMPLIFIED
    const paymentForm = document.getElementById('paymentForm');
    if(paymentForm) {
        console.log('Form found! Action URL:', paymentForm.action);
        
        paymentForm.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            console.log('Form action:', this.action);
            console.log('Form method:', this.method);
            
            // Get form data
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            const amount = parseFloat(document.getElementById('amount').value);
            
            console.log('Payment method checked:', paymentMethod);
            console.log('Amount:', amount);
            
            // Basic validation
            if(!paymentMethod) {
                alert('Please select a payment method');
                e.preventDefault();
                return false;
            }
            
            if(!amount || amount <= 0) {
                alert('Please enter a valid amount');
                e.preventDefault();
                return false;
            }
            
            // Show loading animation
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            // Allow form to submit normally
            console.log('Allowing form submission...');
            return true; // Let it submit!
        });
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    
    // Initialize amount in instructions on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Payments page loaded');
        const amountValue = document.getElementById('amount')?.value || '29.99';
        document.getElementById('gcashAmount').textContent = amountValue;
        document.getElementById('paymayaAmount').textContent = amountValue;
        document.getElementById('bankAmount').textContent = amountValue;
        document.getElementById('cashAmount').textContent = amountValue;
    });
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const receiptModal = document.getElementById('receiptModal');
        const paymentDetailsModal = document.getElementById('paymentDetailsModal');
        
        if(event.target == receiptModal) {
            closeModal();
        }
        
        if(event.target == paymentDetailsModal) {
            closePaymentDetails();
        }
    };
</script>
</body>
</html>