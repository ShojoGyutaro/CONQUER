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
    $specialty = $_POST['specialty'] ?? '';
    $certification = $_POST['certification'] ?? '';
    $yearsExperience = $_POST['years_experience'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $rating = $_POST['rating'] ?? 0;
    
    // Validate inputs
    $errors = [];
    
    if(empty($fullName)) $errors[] = 'Full name is required';
    if(empty($email)) $errors[] = 'Email is required';
    if(empty($username)) $errors[] = 'Username is required';
    if(empty($password)) $errors[] = 'Password is required';
    if(empty($specialty)) $errors[] = 'Specialty is required';
    if(empty($certification)) $errors[] = 'Certification is required';
    
    if(empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into users table
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, full_name, user_type, is_active) 
                VALUES (?, ?, ?, ?, 'trainer', 1)
            ");
            $stmt->execute([$username, $email, $passwordHash, $fullName]);
            $userId = $pdo->lastInsertId();
            
            // Insert into trainers table
            $stmt = $pdo->prepare("
                INSERT INTO trainers (user_id, specialty, certification, years_experience, bio, rating) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $specialty, $certification, $yearsExperience, $bio, $rating]);
            
            $pdo->commit();
            
            $successMessage = "Trainer '$fullName' added successfully!";
            
            // Clear form
            $_POST = [];
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $errorMessage = 'Error adding trainer: ' . $e->getMessage();
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
    <title>Add New Trainer | CONQUER Gym Admin</title>
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
        
        /* Specialty Tags */
        .specialty-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .specialty-tag {
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
        
        .specialty-tag:hover {
            background: var(--primary-color, #ff4757);
            color: white;
            transform: translateY(-2px);
            border-color: var(--primary-color, #ff4757);
        }
        
        .specialty-tag.selected {
            background: var(--primary-color, #ff4757);
            color: white;
            border-color: var(--primary-color, #ff4757);
        }
        
        .specialty-tag i {
            font-size: 0.8rem;
        }
        
        /* Certification Cards */
        .certification-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .certification-card {
            border: 2px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .certification-card:hover {
            border-color: var(--primary-color, #ff4757);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md, 0 4px 20px rgba(0,0,0,0.15));
        }
        
        .certification-card.selected {
            border-color: var(--primary-color, #ff4757);
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.05), rgba(255, 71, 87, 0.02));
        }
        
        .certification-card.selected::before {
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
        
        .certification-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .certification-card p {
            color: var(--text-light, #6c757d);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .certification-features {
            list-style: none;
            text-align: left;
            margin-top: 1rem;
        }
        
        .certification-features li {
            padding: 0.4rem 0;
            color: var(--text-light, #6c757d);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .certification-features li i {
            font-size: 0.8rem;
            color: var(--primary-color, #ff4757);
        }
        
        /* Rating Stars */
        .rating-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .rating-stars {
            display: flex;
            gap: 0.25rem;
        }
        
        .rating-star {
            font-size: 1.5rem;
            color: #e0e0e0;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .rating-star.active {
            color: #ffd700;
        }
        
        .rating-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color, #2f3542);
            min-width: 40px;
        }
        
        .rating-input-group {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .rating-input-group input {
            width: 80px;
            text-align: center;
        }
        
        /* Experience Level */
        .experience-levels {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .experience-level {
            padding: 0.75rem 1.5rem;
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            border: 2px solid transparent;
            color: var(--text-light, #6c757d);
            font-weight: 500;
            min-width: 120px;
        }
        
        .experience-level:hover {
            background: var(--primary-color, #ff4757);
            color: white;
            transform: translateY(-2px);
            border-color: var(--primary-color, #ff4757);
        }
        
        .experience-level.selected {
            background: var(--primary-color, #ff4757);
            color: white;
            border-color: var(--primary-color, #ff4757);
        }
        
        .experience-level span {
            display: block;
            font-size: 0.8rem;
            opacity: 0.8;
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
        
        /* Bio Character Counter */
        .bio-counter {
            text-align: right;
            font-size: 0.8rem;
            color: var(--text-light, #6c757d);
            margin-top: 0.25rem;
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
            
            .certification-cards,
            .specialty-tags,
            .experience-levels {
                grid-template-columns: 1fr;
                justify-items: center;
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
            
            .rating-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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
            
            .specialty-tag,
            .experience-level {
                width: 100%;
                justify-content: center;
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
                    <input type="text" placeholder="Search trainers, members, classes...">
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
                        <i class="fas fa-user-tie"></i>
                        Add New Trainer
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
                    <form method="POST" class="add-form" id="addTrainerForm">
                        <div class="form-header">
                            <h2><i class="fas fa-user-tie"></i> Add New Trainer</h2>
                            <p>Complete the form below to register a new certified trainer. All fields marked with * are required.</p>
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
                                <span>Qualifications</span>
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
                                    <label for="email" class="required">Email Address</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           placeholder="Enter email address" required>
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
                        
                        <!-- Section 3: Qualifications -->
                        <div class="form-section">
                            <h3><i class="fas fa-graduation-cap"></i> Professional Qualifications</h3>
                            
                            <div class="form-group">
                                <label class="required">Select Specialty</label>
                                <div class="specialty-tags" id="specialtyTags">
                                    <div class="specialty-tag" data-specialty="Strength Training">
                                        <i class="fas fa-dumbbell"></i> Strength Training
                                    </div>
                                    <div class="specialty-tag" data-specialty="Yoga">
                                        <i class="fas fa-spa"></i> Yoga
                                    </div>
                                    <div class="specialty-tag" data-specialty="Cardio">
                                        <i class="fas fa-running"></i> Cardio
                                    </div>
                                    <div class="specialty-tag" data-specialty="CrossFit">
                                        <i class="fas fa-fire"></i> CrossFit
                                    </div>
                                    <div class="specialty-tag" data-specialty="Nutrition">
                                        <i class="fas fa-apple-alt"></i> Nutrition
                                    </div>
                                    <div class="specialty-tag" data-specialty="Weight Loss">
                                        <i class="fas fa-weight"></i> Weight Loss
                                    </div>
                                    <div class="specialty-tag" data-specialty="Senior Fitness">
                                        <i class="fas fa-user-friends"></i> Senior Fitness
                                    </div>
                                    <div class="specialty-tag" data-specialty="Sports Specific">
                                        <i class="fas fa-baseball-ball"></i> Sports Specific
                                    </div>
                                </div>
                                <input type="text" id="specialty" name="specialty" 
                                       value="<?php echo htmlspecialchars($_POST['specialty'] ?? ''); ?>" 
                                       placeholder="Or enter custom specialty" style="margin-top: 1rem;">
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Select Certification</label>
                                <div class="certification-cards" id="certificationCards">
                                    <div class="certification-card" data-certification="NASM Certified">
                                        <div class="certification-name">NASM Certified</div>
                                        <p>National Academy of Sports Medicine</p>
                                        <ul class="certification-features">
                                            <li><i class="fas fa-check"></i> CPR/AED Certified</li>
                                            <li><i class="fas fa-check"></i> Exercise Science</li>
                                            <li><i class="fas fa-check"></i> Nutrition Basics</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="certification-card" data-certification="ACE Certified">
                                        <div class="certification-name">ACE Certified</div>
                                        <p>American Council on Exercise</p>
                                        <ul class="certification-features">
                                            <li><i class="fas fa-check"></i> CPR/AED Certified</li>
                                            <li><i class="fas fa-check"></i> Group Fitness</li>
                                            <li><i class="fas fa-check"></i> Health Coach</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="certification-card" data-certification="ISSA Certified">
                                        <div class="certification-name">ISSA Certified</div>
                                        <p>International Sports Sciences Association</p>
                                        <ul class="certification-features">
                                            <li><i class="fas fa-check"></i> Strength Training</li>
                                            <li><i class="fas fa-check"></i> Nutrition Coach</li>
                                            <li><i class="fas fa-check"></i> Online Training</li>
                                        </ul>
                                    </div>
                                </div>
                                <input type="text" id="certification" name="certification" 
                                       value="<?php echo htmlspecialchars($_POST['certification'] ?? ''); ?>" 
                                       placeholder="Or enter custom certification" style="margin-top: 1rem;">
                            </div>
                            
                            <div class="form-group">
                                <label>Experience Level</label>
                                <div class="experience-levels" id="experienceLevels">
                                    <div class="experience-level" data-years="1">
                                        Beginner
                                        <span>1-2 years</span>
                                    </div>
                                    <div class="experience-level" data-years="3">
                                        Intermediate
                                        <span>3-5 years</span>
                                    </div>
                                    <div class="experience-level" data-years="6">
                                        Advanced
                                        <span>6-10 years</span>
                                    </div>
                                    <div class="experience-level" data-years="11">
                                        Expert
                                        <span>10+ years</span>
                                    </div>
                                </div>
                                <div class="rating-input-group">
                                    <input type="number" id="years_experience" name="years_experience" 
                                           value="<?php echo htmlspecialchars($_POST['years_experience'] ?? ''); ?>" 
                                           min="0" max="50" placeholder="Enter years">
                                    <span>years of experience</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Rating</label>
                                <div class="rating-container">
                                    <div class="rating-stars" id="ratingStars">
                                        <i class="fas fa-star rating-star" data-value="1"></i>
                                        <i class="fas fa-star rating-star" data-value="2"></i>
                                        <i class="fas fa-star rating-star" data-value="3"></i>
                                        <i class="fas fa-star rating-star" data-value="4"></i>
                                        <i class="fas fa-star rating-star" data-value="5"></i>
                                    </div>
                                    <div class="rating-value" id="ratingValue">0.0</div>
                                </div>
                                <input type="hidden" id="rating" name="rating" 
                                       value="<?php echo htmlspecialchars($_POST['rating'] ?? '0'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="bio">Biography</label>
                                <textarea id="bio" name="bio" rows="6" 
                                          placeholder="Tell us about the trainer's background, philosophy, and achievements..."
                                          oninput="updateBioCounter()"><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
                                <div class="bio-counter">
                                    <span id="bioCount">0</span>/500 characters
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-tie"></i>
                                Add Trainer
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin-trainers.php'">
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
        
        // Specialty tags selection
        document.querySelectorAll('.specialty-tag').forEach(tag => {
            tag.addEventListener('click', function() {
                // Remove selected class from all tags
                document.querySelectorAll('.specialty-tag').forEach(t => {
                    t.classList.remove('selected');
                });
                
                // Add selected class to clicked tag
                this.classList.add('selected');
                
                // Update specialty input value
                const specialty = this.getAttribute('data-specialty');
                document.getElementById('specialty').value = specialty;
                
                // Update step indicator
                updateStepIndicator(3);
            });
        });
        
        // Certification cards selection
        document.querySelectorAll('.certification-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                document.querySelectorAll('.certification-card').forEach(c => {
                    c.classList.remove('selected');
                });
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Update certification input value
                const certification = this.getAttribute('data-certification');
                document.getElementById('certification').value = certification;
                
                // Update step indicator
                updateStepIndicator(3);
            });
        });
        
        // Experience level selection
        document.querySelectorAll('.experience-level').forEach(level => {
            level.addEventListener('click', function() {
                // Remove selected class from all levels
                document.querySelectorAll('.experience-level').forEach(l => {
                    l.classList.remove('selected');
                });
                
                // Add selected class to clicked level
                this.classList.add('selected');
                
                // Update years experience input
                const years = this.getAttribute('data-years');
                document.getElementById('years_experience').value = years;
                
                // Update step indicator
                updateStepIndicator(3);
            });
        });
        
        // Auto-select previously selected values
        const selectedSpecialty = "<?php echo $_POST['specialty'] ?? ''; ?>";
        if(selectedSpecialty) {
            document.querySelectorAll('.specialty-tag').forEach(tag => {
                if(tag.getAttribute('data-specialty') === selectedSpecialty) {
                    tag.classList.add('selected');
                }
            });
        }
        
        const selectedCertification = "<?php echo $_POST['certification'] ?? ''; ?>";
        if(selectedCertification) {
            document.querySelectorAll('.certification-card').forEach(card => {
                if(card.getAttribute('data-certification') === selectedCertification) {
                    card.classList.add('selected');
                }
            });
        }
        
        const selectedYears = "<?php echo $_POST['years_experience'] ?? ''; ?>";
        if(selectedYears) {
            document.querySelectorAll('.experience-level').forEach(level => {
                if(level.getAttribute('data-years') === selectedYears) {
                    level.classList.add('selected');
                }
            });
        }
        
        // Star rating system
        const stars = document.querySelectorAll('.rating-star');
        const ratingInput = document.getElementById('rating');
        const ratingValue = document.getElementById('ratingValue');
        
        function updateStars(rating) {
            stars.forEach(star => {
                const value = parseInt(star.getAttribute('data-value'));
                if(value <= rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
            ratingValue.textContent = rating.toFixed(1);
            ratingInput.value = rating;
        }
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const value = parseInt(this.getAttribute('data-value'));
                updateStars(value);
            });
            
            star.addEventListener('mouseenter', function() {
                const value = parseInt(this.getAttribute('data-value'));
                updateStars(value);
            });
            
            star.addEventListener('mouseleave', function() {
                const currentRating = parseFloat(ratingInput.value);
                updateStars(currentRating);
            });
        });
        
        // Initialize stars based on current value
        const initialRating = parseFloat(ratingInput.value) || 0;
        updateStars(initialRating);
        
        // Bio character counter
        function updateBioCounter() {
            const bio = document.getElementById('bio');
            const counter = document.getElementById('bioCount');
            const charCount = bio.value.length;
            counter.textContent = charCount;
            
            if(charCount > 500) {
                bio.value = bio.value.substring(0, 500);
                counter.textContent = 500;
                counter.style.color = 'var(--danger-color)';
            } else if(charCount > 450) {
                counter.style.color = 'var(--warning-color)';
            } else {
                counter.style.color = 'var(--text-light)';
            }
        }
        
        // Initialize bio counter
        updateBioCounter();
        
        // Form validation
        document.getElementById('addTrainerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const specialty = document.getElementById('specialty').value;
            const certification = document.getElementById('certification').value;
            
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
            
            // Check specialty
            if(!specialty.trim()) {
                e.preventDefault();
                alert('Please select or enter a specialty!');
                return false;
            }
            
            // Check certification
            if(!certification.trim()) {
                e.preventDefault();
                alert('Please select or enter a certification!');
                return false;
            }
            
            // Check username availability (simulated)
            const username = document.getElementById('username').value;
            if(username.length < 3) {
                e.preventDefault();
                alert('Username must be at least 3 characters long!');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Trainer...';
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
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', function() {
                // Determine which step is being completed
                if(this.id === 'full_name' || this.id === 'email') {
                    updateStepIndicator(2);
                } else if(this.id === 'username' || this.id === 'password' || this.id === 'confirm_password') {
                    updateStepIndicator(3);
                } else if(this.id === 'specialty' || this.id === 'certification') {
                    updateStepIndicator(4);
                }
            });
        });
        
        // Initialize step indicator based on form state
        document.addEventListener('DOMContentLoaded', function() {
            // Check if any fields in step 2 are filled
            const step2Fields = ['full_name', 'email'];
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
            
            // Check if any fields in step 4 are filled
            const step4Fields = ['specialty', 'certification', 'years_experience', 'bio'];
            const step4Filled = step4Fields.some(id => {
                const field = document.getElementById(id);
                return field && field.value.trim() !== '';
            });
            
            if(step4Filled) {
                updateStepIndicator(4);
            } else if(step3Filled) {
                updateStepIndicator(3);
            } else if(step2Filled) {
                updateStepIndicator(2);
            } else {
                updateStepIndicator(1);
            }
        });
        
        // Add click handlers for specialty input to clear selected tags
        document.getElementById('specialty').addEventListener('focus', function() {
            document.querySelectorAll('.specialty-tag').forEach(tag => {
                tag.classList.remove('selected');
            });
        });
        
        // Add click handlers for certification input to clear selected cards
        document.getElementById('certification').addEventListener('focus', function() {
            document.querySelectorAll('.certification-card').forEach(card => {
                card.classList.remove('selected');
            });
        });
    </script>
</body>
</html>