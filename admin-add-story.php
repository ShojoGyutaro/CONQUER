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

// Fetch members for success stories
$members = [];
try {
    $members = $pdo->query("
        SELECT u.*, gm.MembershipPlan, gm.JoinDate 
        FROM users u 
        LEFT JOIN gym_members gm ON u.email = gm.Email 
        WHERE u.user_type = 'member' 
        AND u.is_active = 1
        ORDER BY u.full_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Fetch members error: " . $e->getMessage());
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = $_POST['member_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $story = $_POST['story'] ?? '';
    $beforeWeight = $_POST['before_weight'] ?? '';
    $afterWeight = $_POST['after_weight'] ?? '';
    $beforePhoto = ''; // Will handle file upload
    $afterPhoto = '';  // Will handle file upload
    $duration = $_POST['duration'] ?? '';
    $category = $_POST['category'] ?? '';
    $achievements = $_POST['achievements'] ?? '';
    $approved = isset($_POST['approved']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    
    if(empty($memberId)) $errors[] = 'Member is required';
    if(empty($title)) $errors[] = 'Story title is required';
    if(empty($story)) $errors[] = 'Success story is required';
    if(strlen($story) < 100) $errors[] = 'Story must be at least 100 characters';
    if(empty($category)) $errors[] = 'Category is required';
    
    // Handle file uploads
    $uploadDir = 'uploads/success_stories/';
    if(!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Handle before photo upload
    if(isset($_FILES['before_photo']) && $_FILES['before_photo']['error'] === UPLOAD_ERR_OK) {
        $beforeFile = $_FILES['before_photo'];
        $beforeExt = strtolower(pathinfo($beforeFile['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if(in_array($beforeExt, $allowedExts)) {
            $beforeFilename = uniqid('before_', true) . '.' . $beforeExt;
            $beforePath = $uploadDir . $beforeFilename;
            
            if(move_uploaded_file($beforeFile['tmp_name'], $beforePath)) {
                $beforePhoto = $beforeFilename;
            } else {
                $errors[] = 'Failed to upload before photo';
            }
        } else {
            $errors[] = 'Before photo must be JPG, PNG, GIF, or WebP';
        }
    }
    
    // Handle after photo upload
    if(isset($_FILES['after_photo']) && $_FILES['after_photo']['error'] === UPLOAD_ERR_OK) {
        $afterFile = $_FILES['after_photo'];
        $afterExt = strtolower(pathinfo($afterFile['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if(in_array($afterExt, $allowedExts)) {
            $afterFilename = uniqid('after_', true) . '.' . $afterExt;
            $afterPath = $uploadDir . $afterFilename;
            
            if(move_uploaded_file($afterFile['tmp_name'], $afterPath)) {
                $afterPhoto = $afterFilename;
            } else {
                $errors[] = 'Failed to upload after photo';
            }
        } else {
            $errors[] = 'After photo must be JPG, PNG, GIF, or WebP';
        }
    }
    
    if(empty($errors)) {
        try {
            // Get member details
            $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$member) {
                throw new Exception("Member not found");
            }
            
            // Insert into success_stories table
            $stmt = $pdo->prepare("
                INSERT INTO success_stories (
                    member_id, member_name, member_email, title, story,
                    before_weight, after_weight, before_photo, after_photo,
                    duration, category, achievements, approved, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $memberId,
                $member['full_name'],
                $member['email'],
                $title,
                $story,
                $beforeWeight ?: null,
                $afterWeight ?: null,
                $beforePhoto,
                $afterPhoto,
                $duration ?: null,
                $category,
                $achievements ?: null,
                $approved
            ]);
            
            $storyId = $pdo->lastInsertId();
            $successMessage = "Success story '$title' added successfully! Story ID: $storyId";
            
            // Clear form
            $_POST = [];
            $_FILES = [];
            
        } catch(Exception $e) {
            $errorMessage = 'Error adding success story: ' . $e->getMessage();
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
    <title>Add Success Story | CONQUER Gym Admin</title>
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
        
        /* Category Cards */
        .category-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .category-card {
            border: 2px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .category-card:hover {
            border-color: var(--primary-color, #ff4757);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md, 0 4px 20px rgba(0,0,0,0.15));
        }
        
        .category-card.selected {
            border-color: var(--primary-color, #ff4757);
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.05), rgba(255, 71, 87, 0.02));
        }
        
        .category-card.selected::before {
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
        
        .category-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: var(--primary-color, #ff4757);
        }
        
        .category-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .category-card p {
            color: var(--text-light, #6c757d);
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        /* Weight Comparison */
        .weight-comparison {
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            margin-top: 1.5rem;
            border: 2px solid var(--border-color, #e0e0e0);
        }
        
        .weight-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .weight-header i {
            color: var(--primary-color, #ff4757);
        }
        
        .weight-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .weight-item {
            background: var(--white, #ffffff);
            padding: 1rem;
            border-radius: var(--radius-md, 12px);
            border: 1px solid var(--border-color, #e0e0e0);
        }
        
        .weight-label {
            font-size: 0.9rem;
            color: var(--text-light, #6c757d);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .weight-label i {
            font-size: 0.9rem;
        }
        
        .weight-value {
            font-weight: 600;
            color: var(--dark-color, #2f3542);
            font-size: 1.1rem;
            line-height: 1.4;
        }
        
        /* Photo Upload */
        .photo-upload-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .photo-upload-box {
            border: 2px dashed var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            position: relative;
            overflow: hidden;
        }
        
        .photo-upload-box:hover {
            border-color: var(--primary-color, #ff4757);
            background: var(--light-color, #f1f2f6);
        }
        
        .photo-upload-box.has-photo {
            border-style: solid;
            border-color: var(--success-color, #2ed573);
            background: rgba(46, 213, 115, 0.05);
        }
        
        .photo-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--text-light, #6c757d);
        }
        
        .photo-upload-box:hover .photo-icon {
            color: var(--primary-color, #ff4757);
        }
        
        .photo-upload-box.has-photo .photo-icon {
            color: var(--success-color, #2ed573);
        }
        
        .photo-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .photo-upload-box p {
            color: var(--text-light, #6c757d);
            font-size: 0.85rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        
        .photo-preview {
            display: none;
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: var(--radius-md, 8px);
            margin-bottom: 1rem;
        }
        
        .photo-upload-box.has-photo .photo-preview {
            display: block;
        }
        
        /* Story Character Counter */
        .story-counter {
            text-align: right;
            font-size: 0.85rem;
            color: var(--text-light, #6c757d);
            margin-top: 0.25rem;
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
        
        /* Story Preview */
        .story-preview {
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
        
        .preview-content {
            background: var(--white, #ffffff);
            padding: 1.5rem;
            border-radius: var(--radius-md, 12px);
            border: 1px solid var(--border-color, #e0e0e0);
        }
        
        .preview-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 1rem;
            line-height: 1.3;
        }
        
        .preview-story {
            color: var(--text-light, #6c757d);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            max-height: 150px;
            overflow-y: auto;
        }
        
        .preview-category {
            display: inline-block;
            background: var(--primary-color, #ff4757);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 1rem;
        }
        
        /* Achievements List */
        .achievements-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .achievements-list li {
            padding: 0.5rem 0;
            color: var(--text-light, #6c757d);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .achievements-list li i {
            color: var(--success-color, #2ed573);
            font-size: 0.8rem;
        }
        
        /* Approval Switch */
        .approval-switch {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            padding: 1rem;
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            border: 2px solid var(--border-color, #e0e0e0);
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--success-color, #2ed573);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .approval-label {
            font-weight: 600;
            color: var(--dark-color, #2f3542);
            font-size: 0.9rem;
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
            
            .category-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .photo-upload-container {
                grid-template-columns: 1fr;
            }
            
            .weight-details,
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
            
            .category-cards {
                grid-template-columns: 1fr;
            }
            
            .weight-item {
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
                    <input type="text" placeholder="Search stories, members, categories...">
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
                        <i class="fas fa-trophy"></i>
                        Add Success Story
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
                    <form method="POST" class="add-form" id="addStoryForm" enctype="multipart/form-data">
                        <div class="form-header">
                            <h2><i class="fas fa-trophy"></i> Add Success Story</h2>
                            <p>Share an inspiring success story from one of our members. All fields marked with * are required.</p>
                        </div>
                        
                        <div class="form-steps">
                            <div class="step active">
                                <div class="step-number">1</div>
                                <span>Member & Title</span>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <span>Story Details</span>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <span>Photos & Stats</span>
                            </div>
                        </div>
                        
                        <!-- Section 1: Member & Title -->
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
                                            data-joindate="<?php echo htmlspecialchars($member['JoinDate'] ?? 'N/A'); ?>"
                                            data-name="<?php echo htmlspecialchars($member['full_name']); ?>">
                                            <?php echo htmlspecialchars($member['full_name']); ?> 
                                            (<?php echo htmlspecialchars($member['MembershipPlan'] ?? 'No Plan'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Select the member whose success story you want to share</small>
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
                                        <div class="member-label">Join Date</div>
                                        <div class="member-value" id="memberJoinDate">-</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="title" class="required">Story Title</label>
                                <input type="text" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                       placeholder="e.g., From Couch to 5K: My 50lb Weight Loss Journey" required>
                                <small>Create an inspiring and descriptive title</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Category</label>
                                <input type="hidden" id="category" name="category" 
                                       value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>" required>
                                <div class="category-cards" id="categoryCards">
                                    <div class="category-card" data-category="Weight Loss">
                                        <div class="category-icon">
                                            <i class="fas fa-weight"></i>
                                        </div>
                                        <div class="category-name">Weight Loss</div>
                                        <p>Inspiring stories of weight loss and transformation</p>
                                    </div>
                                    <div class="category-card" data-category="Strength Gain">
                                        <div class="category-icon">
                                            <i class="fas fa-dumbbell"></i>
                                        </div>
                                        <div class="category-name">Strength Gain</div>
                                        <p>Building muscle and increasing strength</p>
                                    </div>
                                    <div class="category-card" data-category="Fitness Journey">
                                        <div class="category-icon">
                                            <i class="fas fa-running"></i>
                                        </div>
                                        <div class="category-name">Fitness Journey</div>
                                        <p>Overall fitness improvement and lifestyle change</p>
                                    </div>
                                    <div class="category-card" data-category="Mental Health">
                                        <div class="category-icon">
                                            <i class="fas fa-brain"></i>
                                        </div>
                                        <div class="category-name">Mental Health</div>
                                        <p>How fitness improved mental wellbeing</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 2: Story Details -->
                        <div class="form-section">
                            <h3><i class="fas fa-book-open"></i> Story Details</h3>
                            
                            <div class="form-group">
                                <label for="story" class="required">Success Story</label>
                                <textarea id="story" name="story" rows="8" 
                                          placeholder="Tell the inspiring story of this member's journey. Include challenges, turning points, and achievements..."
                                          oninput="updateStoryCounter()" required><?php echo htmlspecialchars($_POST['story'] ?? ''); ?></textarea>
                                <div class="story-counter">
                                    <span id="storyCount">0</span>/2000 characters (Minimum: 100)
                                </div>
                                <small>Share the inspiring journey in detail</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="duration">Duration</label>
                                <input type="text" id="duration" name="duration" 
                                       value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>" 
                                       placeholder="e.g., 6 months, 1 year, 3 years">
                                <small>How long did this transformation take?</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="achievements">Key Achievements</label>
                                <textarea id="achievements" name="achievements" rows="3" 
                                          placeholder="List key achievements (one per line). Example:&#10;- Lost 50 pounds&#10;- Ran first marathon&#10;- Lowered blood pressure"><?php echo htmlspecialchars($_POST['achievements'] ?? ''); ?></textarea>
                                <small>List major milestones and achievements (one per line)</small>
                            </div>
                        </div>
                        
                        <!-- Section 3: Photos & Stats -->
                        <div class="form-section">
                            <h3><i class="fas fa-images"></i> Photos & Statistics</h3>
                            
                            <!-- Photo Upload -->
                            <div class="photo-upload-container">
                                <div class="photo-upload-box" id="beforePhotoBox">
                                    <div class="photo-icon">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                    <div class="photo-title">Before Photo</div>
                                    <p>Upload a "before" photo showing the starting point</p>
                                    <img src="" alt="Before" class="photo-preview" id="beforePreview">
                                    <input type="file" id="before_photo" name="before_photo" accept="image/*" style="display: none;" onchange="handleBeforePhoto(event)">
                                    <label for="before_photo" class="btn-secondary" style="cursor: pointer;">
                                        <i class="fas fa-upload"></i> Choose File
                                    </label>
                                </div>
                                
                                <div class="photo-upload-box" id="afterPhotoBox">
                                    <div class="photo-icon">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                    <div class="photo-title">After Photo</div>
                                    <p>Upload an "after" photo showing the amazing results</p>
                                    <img src="" alt="After" class="photo-preview" id="afterPreview">
                                    <input type="file" id="after_photo" name="after_photo" accept="image/*" style="display: none;" onchange="handleAfterPhoto(event)">
                                    <label for="after_photo" class="btn-secondary" style="cursor: pointer;">
                                        <i class="fas fa-upload"></i> Choose File
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Weight Comparison -->
                            <div class="weight-comparison" id="weightComparison">
                                <div class="weight-header">
                                    <i class="fas fa-balance-scale"></i>
                                    Weight Comparison
                                </div>
                                <div class="weight-details">
                                    <div class="weight-item">
                                        <div class="weight-label">
                                            <i class="fas fa-weight"></i>
                                            Before Weight
                                        </div>
                                        <div class="weight-value">
                                            <input type="number" id="before_weight" name="before_weight" 
                                                   value="<?php echo htmlspecialchars($_POST['before_weight'] ?? ''); ?>" 
                                                   placeholder="Enter weight" step="0.1" min="0" style="width: 100%; border: none; background: transparent; outline: none; font-size: 1.1rem;">
                                            <small style="font-size: 0.8rem; color: var(--text-light);">lbs/kg</small>
                                        </div>
                                    </div>
                                    <div class="weight-item">
                                        <div class="weight-label">
                                            <i class="fas fa-weight"></i>
                                            After Weight
                                        </div>
                                        <div class="weight-value">
                                            <input type="number" id="after_weight" name="after_weight" 
                                                   value="<?php echo htmlspecialchars($_POST['after_weight'] ?? ''); ?>" 
                                                   placeholder="Enter weight" step="0.1" min="0" style="width: 100%; border: none; background: transparent; outline: none; font-size: 1.1rem;">
                                            <small style="font-size: 0.8rem; color: var(--text-light);">lbs/kg</small>
                                        </div>
                                    </div>
                                    <div class="weight-item">
                                        <div class="weight-label">
                                            <i class="fas fa-percentage"></i>
                                            Weight Change
                                        </div>
                                        <div class="weight-value" id="weightChange">
                                            <span style="color: var(--text-light);">Enter both weights</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Story Preview -->
                            <div class="story-preview" id="storyPreview">
                                <div class="preview-header">
                                    <i class="fas fa-eye"></i>
                                    Story Preview
                                </div>
                                <div class="preview-content">
                                    <div class="preview-title" id="previewTitle">Story Title Will Appear Here</div>
                                    <div class="preview-story" id="previewStory">
                                        Your success story will be previewed here. Start typing in the story field above to see how it will appear to readers.
                                    </div>
                                    <div class="preview-category" id="previewCategory">Category</div>
                                </div>
                            </div>
                            
                            <!-- Approval Switch -->
                            <div class="approval-switch">
                                <div class="switch">
                                    <input type="checkbox" id="approved" name="approved" value="1" <?php echo isset($_POST['approved']) ? 'checked' : 'checked'; ?>>
                                    <span class="slider"></span>
                                </div>
                                <div class="approval-label">
                                    <span id="approvalStatus">Approved for publication</span>
                                    <small style="display: block; color: var(--text-light); font-weight: normal;">
                                        Toggle to approve or hold this story for review
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-trophy"></i>
                                Add Success Story
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin-stories.php'">
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
                
                // Format join date
                const joinDate = selectedOption.getAttribute('data-joindate');
                if(joinDate && joinDate !== 'N/A') {
                    const date = new Date(joinDate);
                    document.getElementById('memberJoinDate').textContent = date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                } else {
                    document.getElementById('memberJoinDate').textContent = 'N/A';
                }
            } else {
                // Hide member info
                memberInfo.classList.remove('active');
            }
            
            updateStepIndicator(2);
            updatePreview();
        }
        
        // Category selection
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                document.getElementById('category').value = category;
                
                // Update UI
                document.querySelectorAll('.category-card').forEach(c => {
                    c.classList.remove('selected');
                });
                this.classList.add('selected');
                
                updateStepIndicator(3);
                updatePreview();
            });
        });
        
        // Auto-select previously selected category
        const selectedCategory = "<?php echo $_POST['category'] ?? ''; ?>";
        if(selectedCategory) {
            document.querySelectorAll('.category-card').forEach(card => {
                if(card.getAttribute('data-category') === selectedCategory) {
                    card.classList.add('selected');
                }
            });
        } else {
            // Default select first card
            setTimeout(() => {
                document.querySelector('.category-card').click();
            }, 100);
        }
        
        // Photo upload handlers
        function handleBeforePhoto(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('beforePreview');
            const box = document.getElementById('beforePhotoBox');
            
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    box.classList.add('has-photo');
                }
                reader.readAsDataURL(file);
            }
        }
        
        function handleAfterPhoto(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('afterPreview');
            const box = document.getElementById('afterPhotoBox');
            
            if(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    box.classList.add('has-photo');
                }
                reader.readAsDataURL(file);
            }
        }
        
        // Click photo boxes to trigger file input
        document.getElementById('beforePhotoBox').addEventListener('click', function(e) {
            if(e.target.tagName !== 'INPUT' && e.target.tagName !== 'LABEL') {
                document.getElementById('before_photo').click();
            }
        });
        
        document.getElementById('afterPhotoBox').addEventListener('click', function(e) {
            if(e.target.tagName !== 'INPUT' && e.target.tagName !== 'LABEL') {
                document.getElementById('after_photo').click();
            }
        });
        
        // Update weight comparison
        function updateWeightComparison() {
            const beforeWeight = parseFloat(document.getElementById('before_weight').value);
            const afterWeight = parseFloat(document.getElementById('after_weight').value);
            const weightChange = document.getElementById('weightChange');
            
            if(!isNaN(beforeWeight) && !isNaN(afterWeight)) {
                const difference = afterWeight - beforeWeight;
                const percentage = ((difference / beforeWeight) * 100).toFixed(1);
                
                if(difference < 0) {
                    // Weight loss
                    weightChange.innerHTML = `<span style="color: var(--success-color); font-weight: 600;">↓ ${Math.abs(difference).toFixed(1)} lbs (${Math.abs(percentage)}% loss)</span>`;
                } else if(difference > 0) {
                    // Weight gain (muscle)
                    weightChange.innerHTML = `<span style="color: var(--warning-color); font-weight: 600;">↑ ${difference.toFixed(1)} lbs (${percentage}% gain)</span>`;
                } else {
                    weightChange.innerHTML = `<span style="color: var(--text-light);">No change</span>`;
                }
            } else {
                weightChange.innerHTML = `<span style="color: var(--text-light);">Enter both weights</span>`;
            }
        }
        
        // Add event listeners for weight inputs
        document.getElementById('before_weight').addEventListener('input', updateWeightComparison);
        document.getElementById('after_weight').addEventListener('input', updateWeightComparison);
        
        // Story character counter
        function updateStoryCounter() {
            const story = document.getElementById('story');
            const counter = document.getElementById('storyCount');
            const charCount = story.value.length;
            counter.textContent = charCount;
            
            if(charCount > 2000) {
                story.value = story.value.substring(0, 2000);
                counter.textContent = 2000;
                counter.style.color = 'var(--danger-color)';
            } else if(charCount < 100) {
                counter.style.color = 'var(--warning-color)';
            } else {
                counter.style.color = 'var(--text-light)';
            }
            
            updatePreview();
        }
        
        // Initialize story counter
        updateStoryCounter();
        
        // Update story preview
        function updatePreview() {
            const title = document.getElementById('title').value;
            const story = document.getElementById('story').value;
            const category = document.getElementById('category').value;
            
            // Update title
            document.getElementById('previewTitle').textContent = title || 'Story Title Will Appear Here';
            
            // Update story (show first 300 characters)
            if(story) {
                const previewText = story.length > 300 ? story.substring(0, 300) + '...' : story;
                document.getElementById('previewStory').textContent = previewText;
            } else {
                document.getElementById('previewStory').textContent = 'Your success story will be previewed here. Start typing in the story field above to see how it will appear to readers.';
            }
            
            // Update category
            document.getElementById('previewCategory').textContent = category || 'Category';
        }
        
        // Add event listeners for preview updates
        document.getElementById('title').addEventListener('input', updatePreview);
        document.getElementById('story').addEventListener('input', updatePreview);
        document.getElementById('category').addEventListener('change', updatePreview);
        
        // Approval switch handler
        document.getElementById('approved').addEventListener('change', function() {
            const status = document.getElementById('approvalStatus');
            status.textContent = this.checked ? 'Approved for publication' : 'Pending review';
        });
        
        // Form validation
        document.getElementById('addStoryForm').addEventListener('submit', function(e) {
            const memberId = document.getElementById('member_id').value;
            const title = document.getElementById('title').value;
            const story = document.getElementById('story').value;
            const category = document.getElementById('category').value;
            
            // Check required fields
            if(!memberId) {
                e.preventDefault();
                alert('Please select a member!');
                return false;
            }
            
            if(!title.trim()) {
                e.preventDefault();
                alert('Please enter a story title!');
                return false;
            }
            
            if(!story.trim() || story.length < 100) {
                e.preventDefault();
                alert('Success story must be at least 100 characters!');
                return false;
            }
            
            if(!category) {
                e.preventDefault();
                alert('Please select a category!');
                return false;
            }
            
            // Validate file sizes (max 5MB)
            const beforePhoto = document.getElementById('before_photo');
            const afterPhoto = document.getElementById('after_photo');
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if(beforePhoto.files.length > 0 && beforePhoto.files[0].size > maxSize) {
                e.preventDefault();
                alert('Before photo must be less than 5MB!');
                return false;
            }
            
            if(afterPhoto.files.length > 0 && afterPhoto.files[0].size > maxSize) {
                e.preventDefault();
                alert('After photo must be less than 5MB!');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Story...';
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
                if(document.getElementById('member_id').value || document.getElementById('title').value.trim()) {
                    updateStepIndicator(2);
                }
                if(document.getElementById('story').value.trim() || document.getElementById('duration').value.trim()) {
                    updateStepIndicator(3);
                }
            });
        });
        
        // Initialize step indicator based on form state
        document.addEventListener('DOMContentLoaded', function() {
            // Check if any fields in step 2 are filled
            const step2Filled = document.getElementById('member_id').value !== '' || 
                               document.getElementById('title').value.trim() !== '';
            
            // Check if any fields in step 3 are filled
            const step3Filled = document.getElementById('story').value.trim() !== '' || 
                               document.getElementById('duration').value.trim() !== '';
            
            if(step3Filled) {
                updateStepIndicator(3);
            } else if(step2Filled) {
                updateStepIndicator(2);
            } else {
                updateStepIndicator(1);
            }
            
            // Update member info if member is already selected
            if(document.getElementById('member_id').value) {
                updateMemberInfo();
            }
            
            // Update weight comparison
            updateWeightComparison();
            
            // Initialize preview
            updatePreview();
            
            // Auto-focus story field if member is selected
            if(document.getElementById('member_id').value && !document.getElementById('story').value.trim()) {
                setTimeout(() => {
                    document.getElementById('story').focus();
                }, 500);
            }
        });
        
        // Format achievements list on blur
        document.getElementById('achievements').addEventListener('blur', function() {
            if(this.value.trim()) {
                // Split by new lines, trim each line, filter empty lines
                const achievements = this.value.split('\n')
                    .map(line => line.trim())
                    .filter(line => line.length > 0)
                    .map(line => line.startsWith('-') ? line : '- ' + line)
                    .join('\n');
                this.value = achievements;
            }
        });
        
        // Auto-suggest duration based on weight change timeline
        document.getElementById('before_weight').addEventListener('blur', function() {
            const beforeWeight = parseFloat(this.value);
            const afterWeight = parseFloat(document.getElementById('after_weight').value);
            const durationField = document.getElementById('duration');
            
            if(!isNaN(beforeWeight) && !isNaN(afterWeight)) {
                const difference = Math.abs(afterWeight - beforeWeight);
                
                if(difference >= 50) {
                    durationField.value = '6-12 months';
                } else if(difference >= 30) {
                    durationField.value = '3-6 months';
                } else if(difference >= 15) {
                    durationField.value = '2-3 months';
                } else if(difference >= 5) {
                    durationField.value = '1-2 months';
                }
            }
        });
    </script>
</body>
</html> 