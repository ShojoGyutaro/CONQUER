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

// Fetch trainers for dropdown
$trainers = [];
try {
    $trainers = $pdo->query("
        SELECT t.*, u.full_name 
        FROM trainers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.is_active = 1 
        ORDER BY u.full_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Fetch trainers error: " . $e->getMessage());
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $className = $_POST['class_name'] ?? '';
    $trainerId = $_POST['trainer_id'] ?? '';
    $schedule = $_POST['schedule'] ?? '';
    $duration = $_POST['duration_minutes'] ?? '';
    $maxCapacity = $_POST['max_capacity'] ?? '';
    $location = $_POST['location'] ?? '';
    $classType = $_POST['class_type'] ?? '';
    $difficulty = $_POST['difficulty_level'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validate inputs
    $errors = [];
    
    if(empty($className)) $errors[] = 'Class name is required';
    if(empty($schedule)) $errors[] = 'Schedule is required';
    if(empty($duration) || $duration < 15) $errors[] = 'Duration must be at least 15 minutes';
    if(empty($maxCapacity) || $maxCapacity < 1) $errors[] = 'Max capacity must be at least 1';
    if(empty($location)) $errors[] = 'Location is required';
    if(empty($classType)) $errors[] = 'Class type is required';
    
    // Check if schedule is in the future
    if($schedule && strtotime($schedule) <= time()) {
        $errors[] = 'Schedule must be in the future';
    }
    
    if(empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO classes (
                    class_name, trainer_id, schedule, duration_minutes, 
                    max_capacity, location, class_type, difficulty_level, 
                    description, status, current_enrollment
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 0)
            ");
            
            $stmt->execute([
                $className,
                $trainerId ?: null,
                $schedule,
                $duration,
                $maxCapacity,
                $location,
                $classType,
                $difficulty,
                $description
            ]);
            
            $classId = $pdo->lastInsertId();
            $successMessage = "Class '$className' created successfully! Class ID: $classId";
            
            // Clear form
            $_POST = [];
            
        } catch(PDOException $e) {
            $errorMessage = 'Error creating class: ' . $e->getMessage();
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
    <title>Create New Class | CONQUER Gym Admin</title>
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
        
        /* Class Type Cards */
        .class-type-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .class-type-card {
            border: 2px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .class-type-card:hover {
            border-color: var(--primary-color, #ff4757);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md, 0 4px 20px rgba(0,0,0,0.15));
        }
        
        .class-type-card.selected {
            border-color: var(--primary-color, #ff4757);
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.05), rgba(255, 71, 87, 0.02));
        }
        
        .class-type-card.selected::before {
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
        
        .class-type-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: var(--primary-color, #ff4757);
        }
        
        .class-type-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .class-type-card p {
            color: var(--text-light, #6c757d);
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        /* Schedule Preview */
        .schedule-preview {
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
        
        /* Difficulty Levels */
        .difficulty-levels {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .difficulty-level {
            flex: 1;
            min-width: 120px;
            padding: 1.25rem;
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            border: 2px solid transparent;
        }
        
        .difficulty-level:hover {
            border-color: var(--primary-color, #ff4757);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
        }
        
        .difficulty-level.selected {
            border-color: var(--primary-color, #ff4757);
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.05), rgba(255, 71, 87, 0.02));
        }
        
        .difficulty-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .difficulty-desc {
            font-size: 0.85rem;
            color: var(--text-light, #6c757d);
            line-height: 1.4;
        }
        
        /* Color-coded difficulty levels */
        .difficulty-level[data-level="Beginner"] .difficulty-name {
            color: var(--success-color, #2ed573);
        }
        
        .difficulty-level[data-level="Intermediate"] .difficulty-name {
            color: var(--warning-color, #ffa502);
        }
        
        .difficulty-level[data-level="Advanced"] .difficulty-name {
            color: var(--danger-color, #ff4757);
        }
        
        /* Trainer Select Styling */
        .trainer-select-container {
            position: relative;
        }
        
        .trainer-select-container select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            padding-right: 2.5rem;
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
        
        /* DateTime Input Styling */
        input[type="datetime-local"] {
            position: relative;
        }
        
        input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            background: transparent;
            bottom: 0;
            color: transparent;
            cursor: pointer;
            height: auto;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            width: auto;
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
            
            .class-type-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .difficulty-levels {
                flex-direction: column;
            }
            
            .difficulty-level {
                min-width: 100%;
            }
            
            .preview-details {
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
            
            .class-type-cards {
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
                    <input type="text" placeholder="Search classes, trainers, members...">
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
                        <i class="fas fa-calendar-plus"></i>
                        Create New Class
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
                    <form method="POST" class="add-form" id="addClassForm">
                        <div class="form-header">
                            <h2><i class="fas fa-calendar-plus"></i> Create New Class</h2>
                            <p>Schedule a new fitness class with all necessary details. All fields marked with * are required.</p>
                        </div>
                        
                        <div class="form-steps">
                            <div class="step active">
                                <div class="step-number">1</div>
                                <span>Basic Info</span>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <span>Schedule & Capacity</span>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <span>Class Details</span>
                            </div>
                        </div>
                        
                        <!-- Section 1: Basic Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="class_name" class="required">Class Name</label>
                                    <input type="text" id="class_name" name="class_name" 
                                           value="<?php echo htmlspecialchars($_POST['class_name'] ?? ''); ?>" 
                                           placeholder="e.g., Morning Yoga, HIIT Blast, Strength Training" required>
                                </div>
                                
                                <div class="form-group trainer-select-container">
                                    <label for="trainer_id">Trainer</label>
                                    <select id="trainer_id" name="trainer_id">
                                        <option value="">No Trainer (Self-guided)</option>
                                        <?php foreach($trainers as $trainer): ?>
                                            <option value="<?php echo $trainer['id']; ?>" <?php echo ($_POST['trainer_id'] ?? '') == $trainer['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($trainer['full_name']); ?> - <?php echo htmlspecialchars($trainer['specialty']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small>Optional: Select a certified trainer for this class</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Class Type</label>
                                <input type="hidden" id="class_type" name="class_type" 
                                       value="<?php echo htmlspecialchars($_POST['class_type'] ?? ''); ?>" required>
                                <div class="class-type-cards" id="classTypeCards">
                                    <div class="class-type-card" data-type="Yoga">
                                        <div class="class-type-icon">
                                            <i class="fas fa-spa"></i>
                                        </div>
                                        <div class="class-type-name">Yoga</div>
                                        <p>Mind-body practice for flexibility and relaxation</p>
                                    </div>
                                    <div class="class-type-card" data-type="HIIT">
                                        <div class="class-type-icon">
                                            <i class="fas fa-running"></i>
                                        </div>
                                        <div class="class-type-name">HIIT</div>
                                        <p>High-intensity interval training for maximum burn</p>
                                    </div>
                                    <div class="class-type-card" data-type="Strength">
                                        <div class="class-type-icon">
                                            <i class="fas fa-dumbbell"></i>
                                        </div>
                                        <div class="class-type-name">Strength</div>
                                        <p>Weight training for muscle building and tone</p>
                                    </div>
                                    <div class="class-type-card" data-type="Cardio">
                                        <div class="class-type-icon">
                                            <i class="fas fa-heartbeat"></i>
                                        </div>
                                        <div class="class-type-name">Cardio</div>
                                        <p>Aerobic exercises for heart health and endurance</p>
                                    </div>
                                    <div class="class-type-card" data-type="CrossFit">
                                        <div class="class-type-icon">
                                            <i class="fas fa-fire"></i>
                                        </div>
                                        <div class="class-type-name">CrossFit</div>
                                        <p>Functional movements performed at high intensity</p>
                                    </div>
                                    <div class="class-type-card" data-type="Pilates">
                                        <div class="class-type-icon">
                                            <i class="fas fa-circle"></i>
                                        </div>
                                        <div class="class-type-name">Pilates</div>
                                        <p>Core strengthening and postural improvement</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 2: Schedule & Capacity -->
                        <div class="form-section">
                            <h3><i class="fas fa-clock"></i> Schedule & Capacity</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="schedule" class="required">Schedule Date & Time</label>
                                    <input type="datetime-local" id="schedule" name="schedule" 
                                           value="<?php echo htmlspecialchars($_POST['schedule'] ?? ''); ?>" required>
                                    <small>Class must be scheduled in the future</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="duration_minutes" class="required">Duration (minutes)</label>
                                    <input type="number" id="duration_minutes" name="duration_minutes" 
                                           value="<?php echo htmlspecialchars($_POST['duration_minutes'] ?? '60'); ?>" 
                                           min="15" max="180" step="5" required>
                                    <small>Between 15-180 minutes (in 5-minute increments)</small>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="max_capacity" class="required">Max Capacity</label>
                                    <input type="number" id="max_capacity" name="max_capacity" 
                                           value="<?php echo htmlspecialchars($_POST['max_capacity'] ?? '20'); ?>" 
                                           min="1" max="100" required>
                                    <small>Maximum number of participants (1-100)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="location" class="required">Location</label>
                                    <input type="text" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                           placeholder="e.g., Main Studio, Yoga Room, Pool Area" required>
                                    <small>Where the class will take place</small>
                                </div>
                            </div>
                            
                            <!-- Schedule Preview -->
                            <div class="schedule-preview" id="schedulePreview">
                                <div class="preview-header">
                                    <i class="fas fa-eye"></i>
                                    Class Preview
                                </div>
                                <div class="preview-details">
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="far fa-calendar-alt"></i>
                                            Date & Time
                                        </div>
                                        <div class="preview-value" id="previewDateTime">Not set</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="far fa-clock"></i>
                                            Duration
                                        </div>
                                        <div class="preview-value" id="previewDuration">60 minutes</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="fas fa-users"></i>
                                            Capacity
                                        </div>
                                        <div class="preview-value" id="previewCapacity">20 spots</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="fas fa-map-marker-alt"></i>
                                            Location
                                        </div>
                                        <div class="preview-value" id="previewLocation">Not set</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 3: Class Details -->
                        <div class="form-section">
                            <h3><i class="fas fa-sliders-h"></i> Class Details</h3>
                            
                            <div class="form-group">
                                <label>Difficulty Level</label>
                                <input type="hidden" id="difficulty_level" name="difficulty_level" 
                                       value="<?php echo htmlspecialchars($_POST['difficulty_level'] ?? 'Intermediate'); ?>">
                                <div class="difficulty-levels" id="difficultyLevels">
                                    <div class="difficulty-level" data-level="Beginner">
                                        <div class="difficulty-name">Beginner</div>
                                        <div class="difficulty-desc">Suitable for all fitness levels. No prior experience required.</div>
                                    </div>
                                    <div class="difficulty-level" data-level="Intermediate">
                                        <div class="difficulty-name">Intermediate</div>
                                        <div class="difficulty-desc">Some experience recommended. Moderate intensity.</div>
                                    </div>
                                    <div class="difficulty-level" data-level="Advanced">
                                        <div class="difficulty-name">Advanced</div>
                                        <div class="difficulty-desc">High intensity for experienced participants only.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Class Description</label>
                                <textarea id="description" name="description" rows="4" 
                                          placeholder="Describe what participants can expect from this class, equipment needed, and any prerequisites..."
                                          oninput="updateDescriptionCounter()"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                <div class="description-counter">
                                    <span id="descriptionCount">0</span>/500 characters
                                </div>
                                <small>This will be displayed on the class schedule and member portal</small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calendar-plus"></i>
                                Create Class
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin-classes.php'">
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
        // Class type selection
        document.querySelectorAll('.class-type-card').forEach(card => {
            card.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                document.getElementById('class_type').value = type;
                
                // Update UI
                document.querySelectorAll('.class-type-card').forEach(c => {
                    c.classList.remove('selected');
                });
                this.classList.add('selected');
                
                updateStepIndicator(3);
                updatePreview();
                
                // Auto-suggest location based on class type
                if(!document.getElementById('location').value) {
                    switch(type) {
                        case 'Yoga':
                        case 'Pilates':
                            document.getElementById('location').value = 'Yoga Studio';
                            break;
                        case 'HIIT':
                        case 'Cardio':
                        case 'CrossFit':
                            document.getElementById('location').value = 'Main Studio';
                            break;
                        case 'Strength':
                            document.getElementById('location').value = 'Weight Room';
                            break;
                    }
                    updatePreview();
                }
            });
        });
        
        // Auto-select previously selected class type
        const selectedType = "<?php echo $_POST['class_type'] ?? ''; ?>";
        if(selectedType) {
            document.querySelectorAll('.class-type-card').forEach(card => {
                if(card.getAttribute('data-type') === selectedType) {
                    card.classList.add('selected');
                }
            });
        } else {
            // Default select first card
            setTimeout(() => {
                document.querySelector('.class-type-card').click();
            }, 100);
        }
        
        // Difficulty level selection
        document.querySelectorAll('.difficulty-level').forEach(level => {
            level.addEventListener('click', function() {
                const difficulty = this.getAttribute('data-level');
                document.getElementById('difficulty_level').value = difficulty;
                
                // Update UI
                document.querySelectorAll('.difficulty-level').forEach(l => {
                    l.classList.remove('selected');
                });
                this.classList.add('selected');
                
                updateStepIndicator(3);
            });
        });
        
        // Auto-select previously selected difficulty
        const selectedDifficulty = "<?php echo $_POST['difficulty_level'] ?? 'Intermediate'; ?>";
        document.querySelectorAll('.difficulty-level').forEach(level => {
            if(level.getAttribute('data-level') === selectedDifficulty) {
                level.classList.add('selected');
            }
        });
        
        // Update schedule preview in real-time
        function updatePreview() {
            const schedule = document.getElementById('schedule').value;
            const duration = document.getElementById('duration_minutes').value;
            const capacity = document.getElementById('max_capacity').value;
            const location = document.getElementById('location').value;
            const className = document.getElementById('class_name').value;
            const classType = document.getElementById('class_type').value;
            
            // Update date/time
            if(schedule) {
                const date = new Date(schedule);
                const options = { 
                    weekday: 'short', 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                document.getElementById('previewDateTime').textContent = date.toLocaleDateString('en-US', options);
            }
            
            // Update duration
            document.getElementById('previewDuration').textContent = duration + ' minutes';
            
            // Update capacity
            document.getElementById('previewCapacity').textContent = capacity + ' spots';
            
            // Update location
            document.getElementById('previewLocation').textContent = location || 'Not set';
        }
        
        // Add event listeners for preview updates
        document.getElementById('schedule').addEventListener('change', updatePreview);
        document.getElementById('duration_minutes').addEventListener('input', updatePreview);
        document.getElementById('max_capacity').addEventListener('input', updatePreview);
        document.getElementById('location').addEventListener('input', updatePreview);
        document.getElementById('class_name').addEventListener('input', updatePreview);
        
        // Description character counter
        function updateDescriptionCounter() {
            const description = document.getElementById('description');
            const counter = document.getElementById('descriptionCount');
            const charCount = description.value.length;
            counter.textContent = charCount;
            
            if(charCount > 500) {
                description.value = description.value.substring(0, 500);
                counter.textContent = 500;
                counter.style.color = 'var(--danger-color)';
            } else if(charCount > 450) {
                counter.style.color = 'var(--warning-color)';
            } else {
                counter.style.color = 'var(--text-light)';
            }
            
            updateStepIndicator(3);
        }
        
        // Initialize description counter
        updateDescriptionCounter();
        
        // Form validation
        document.getElementById('addClassForm').addEventListener('submit', function(e) {
            const schedule = document.getElementById('schedule').value;
            const scheduleDate = new Date(schedule);
            const now = new Date();
            
            // Check if schedule is in the future
            if(scheduleDate <= now) {
                e.preventDefault();
                alert('Schedule must be in the future! Please select a future date and time.');
                return false;
            }
            
            // Check if class type is selected
            const classType = document.getElementById('class_type').value;
            if(!classType) {
                e.preventDefault();
                alert('Please select a class type!');
                return false;
            }
            
            // Check capacity
            const capacity = document.getElementById('max_capacity').value;
            if(capacity < 1 || capacity > 100) {
                e.preventDefault();
                alert('Capacity must be between 1 and 100!');
                return false;
            }
            
            // Check duration
            const duration = document.getElementById('duration_minutes').value;
            if(duration < 15 || duration > 180) {
                e.preventDefault();
                alert('Duration must be between 15 and 180 minutes!');
                return false;
            }
            
            // Check if duration is in 5-minute increments
            if(duration % 5 !== 0) {
                e.preventDefault();
                alert('Duration must be in 5-minute increments (15, 20, 25, etc.)!');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Class...';
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
                if(this.id === 'class_name' || document.getElementById('class_type').value) {
                    updateStepIndicator(2);
                }
                if(this.id === 'schedule' || this.id === 'max_capacity' || this.id === 'location') {
                    updateStepIndicator(3);
                }
            });
        });
        
        // Initialize step indicator based on form state
        document.addEventListener('DOMContentLoaded', function() {
            // Check if any fields in step 2 are filled
            const step2Filled = document.getElementById('class_name').value.trim() !== '' || 
                               document.getElementById('class_type').value !== '';
            
            // Check if any fields in step 3 are filled
            const step3Filled = document.getElementById('schedule').value !== '' || 
                               document.getElementById('max_capacity').value !== '' || 
                               document.getElementById('location').value.trim() !== '';
            
            if(step3Filled) {
                updateStepIndicator(3);
            } else if(step2Filled) {
                updateStepIndicator(2);
            } else {
                updateStepIndicator(1);
            }
            
            // Auto-set default schedule to next hour if not set
            if(!document.getElementById('schedule').value) {
                const now = new Date();
                now.setHours(now.getHours() + 1);
                now.setMinutes(0);
                now.setSeconds(0);
                
                // Format for datetime-local input
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                
                const formatted = `${year}-${month}-${day}T${hours}:${minutes}`;
                document.getElementById('schedule').value = formatted;
                updatePreview();
            }
        });
        
        // Validate duration input to be in 5-minute increments
        document.getElementById('duration_minutes').addEventListener('change', function() {
            let duration = parseInt(this.value);
            if(isNaN(duration)) return;
            
            if(duration < 15) duration = 15;
            if(duration > 180) duration = 180;
            
            // Round to nearest 5 minutes
            duration = Math.round(duration / 5) * 5;
            this.value = duration;
            updatePreview();
        });
        
        // Set minimum datetime to current time
        document.getElementById('schedule').min = new Date().toISOString().slice(0, 16);
    </script>
</body>
</html>