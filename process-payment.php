<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if(!isset($_SESSION['user_id'], $_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$success = false;

try {
    require_once 'config/database.php';
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    
    // Check if form was submitted
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Validate required fields
        $required = ['payment_method', 'amount', 'subscription_period', 'reference_number'];
        $missing = [];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                $missing[] = $field;
            }
        }
        
        if(!empty($missing)) {
            throw new Exception("Missing required fields: " . implode(', ', $missing));
        }
        
        // Sanitize inputs
        $payment_method = htmlspecialchars(trim($_POST['payment_method']));
        $amount = floatval($_POST['amount']);
        $subscription_period = htmlspecialchars(trim($_POST['subscription_period']));
        $reference_number = htmlspecialchars(trim($_POST['reference_number']));
        $notes = isset($_POST['notes']) ? htmlspecialchars(trim($_POST['notes'])) : '';
        $bank_name = isset($_POST['bank_name']) ? htmlspecialchars(trim($_POST['bank_name'])) : '';
        
        // Validate amount
        if($amount <= 0) {
            throw new Exception("Invalid amount. Please enter a positive number.");
        }
        
        // Handle file upload if required
        $receipt_image = null;
        if(in_array($payment_method, ['gcash', 'paymaya', 'bank_transfer']) && isset($_FILES['receipt_image'])) {
            $file = $_FILES['receipt_image'];
            
            // Check if file was uploaded without errors
            if($file['error'] === UPLOAD_ERR_OK) {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/receipts/';
                if(!is_dir($upload_dir)) {
                    if(!mkdir($upload_dir, 0755, true)) {
                        throw new Exception("Failed to create upload directory.");
                    }
                }
                
                // Generate unique filename
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp'];
                
                if(!in_array($file_ext, $allowed_ext)) {
                    throw new Exception("Invalid file type. Only JPG, PNG, GIF, PDF, and WEBP are allowed.");
                }
                
                // Validate file size (5MB max)
                $max_size = 5 * 1024 * 1024; // 5MB in bytes
                if($file['size'] > $max_size) {
                    throw new Exception("File is too large. Maximum size is 5MB.");
                }
                
                // Create unique filename
                $filename = 'receipt_' . time() . '_' . $user_id . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                $destination = $upload_dir . $filename;
                
                // Move uploaded file
                if(move_uploaded_file($file['tmp_name'], $destination)) {
                    $receipt_image = $destination;
                } else {
                    throw new Exception("Failed to move uploaded file.");
                }
            } elseif($file['error'] === UPLOAD_ERR_NO_FILE) {
                // No file uploaded for methods that require it
                if(in_array($payment_method, ['gcash', 'paymaya', 'bank_transfer'])) {
                    throw new Exception("Please upload a receipt/screenshot for " . $payment_method . " payment.");
                }
            } else {
                throw new Exception("File upload error: " . $file['error']);
            }
        }
        
        // For cash payments, receipt will be provided later by staff
        if($payment_method === 'cash') {
            $receipt_image = 'cash_payment_' . time();
        }
        
        // For credit card, simulate payment gateway
        if($payment_method === 'credit_card') {
            $receipt_image = 'credit_card_payment_' . time();
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status)
            )";
            $pdo->exec($sql);
        }
        
        // Insert payment into database
        $stmt = $pdo->prepare("
            INSERT INTO payments (
                user_id, 
                reference_number, 
                payment_method, 
                amount, 
                subscription_period,
                bank_name,
                receipt_image,
                notes,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $result = $stmt->execute([
            $user_id,
            $reference_number,
            $payment_method,
            $amount,
            $subscription_period,
            $bank_name,
            $receipt_image,
            $notes
        ]);
        
        if($result) {
            $payment_id = $pdo->lastInsertId();
            
            $message = "Payment submitted successfully! Reference: $reference_number. Your payment is pending verification.";
            $success = true;
            
            $_SESSION['payment_success'] = $message;
            
        } else {
            throw new Exception("Failed to save payment to database.");
        }
        
    } else {
        throw new Exception("Invalid request method. Expected POST.");
    }
    
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $_SESSION['payment_error'] = $message;
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $_SESSION['payment_error'] = $message;
}

// Redirect back to payments page
header('Location: payments.php');
exit();
?>