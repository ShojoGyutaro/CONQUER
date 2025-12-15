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
    $equipmentName = $_POST['equipment_name'] ?? '';
    $equipmentType = $_POST['equipment_type'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $model = $_POST['model'] ?? '';
    $serialNumber = $_POST['serial_number'] ?? '';
    $purchaseDate = $_POST['purchase_date'] ?? '';
    $purchasePrice = $_POST['purchase_price'] ?? '';
    $lastMaintenance = $_POST['last_maintenance'] ?? '';
    $nextMaintenance = $_POST['next_maintenance'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $location = $_POST['location'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // Validate inputs
    $errors = [];
    
    if(empty($equipmentName)) $errors[] = 'Equipment name is required';
    if(empty($equipmentType)) $errors[] = 'Equipment type is required';
    if(empty($location)) $errors[] = 'Location is required';
    if(empty($status)) $errors[] = 'Status is required';
    
    // Validate maintenance dates
    if($lastMaintenance && $nextMaintenance) {
        if(strtotime($nextMaintenance) <= strtotime($lastMaintenance)) {
            $errors[] = 'Next maintenance must be after last maintenance';
        }
    }
    
    // Validate purchase price if provided
    if($purchasePrice && !is_numeric($purchasePrice)) {
        $errors[] = 'Purchase price must be a valid number';
    }
    
    if(empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO equipment (
                    equipment_name, equipment_type, brand, model, serial_number,
                    purchase_date, purchase_price, last_maintenance, 
                    next_maintenance, status, location, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $equipmentName,
                $equipmentType,
                $brand,
                $model,
                $serialNumber,
                $purchaseDate ?: null,
                $purchasePrice ?: null,
                $lastMaintenance ?: null,
                $nextMaintenance ?: null,
                $status,
                $location,
                $notes
            ]);
            
            $equipmentId = $pdo->lastInsertId();
            $successMessage = "Equipment '$equipmentName' added successfully! Equipment ID: $equipmentId";
            
            // Clear form
            $_POST = [];
            
        } catch(PDOException $e) {
            $errorMessage = 'Error adding equipment: ' . $e->getMessage();
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
    <title>Add New Equipment | CONQUER Gym Admin</title>
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
        
        .message.warning {
            border-left-color: var(--warning-color, #ffa502);
            background: rgba(255, 165, 2, 0.1);
            color: var(--warning-color, #ffa502);
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
        
        /* Equipment Type Cards */
        .equipment-type-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .equipment-type-card {
            border: 2px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            cursor: pointer;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .equipment-type-card:hover {
            border-color: var(--primary-color, #ff4757);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md, 0 4px 20px rgba(0,0,0,0.15));
        }
        
        .equipment-type-card.selected {
            border-color: var(--primary-color, #ff4757);
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.05), rgba(255, 71, 87, 0.02));
        }
        
        .equipment-type-card.selected::before {
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
        
        .equipment-type-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: var(--primary-color, #ff4757);
        }
        
        .equipment-type-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
        }
        
        .equipment-type-card p {
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
        
        .status-card.active {
            border-color: var(--success-color, #2ed573);
            background: linear-gradient(to bottom right, rgba(46, 213, 115, 0.05), rgba(46, 213, 115, 0.02));
        }
        
        .status-card.active.selected::before {
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
        
        .status-card.maintenance {
            border-color: var(--warning-color, #ffa502);
            background: linear-gradient(to bottom right, rgba(255, 165, 2, 0.05), rgba(255, 165, 2, 0.02));
        }
        
        .status-card.maintenance.selected::before {
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
        
        .status-card.retired {
            border-color: var(--danger-color, #ff4757);
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.05), rgba(255, 71, 87, 0.02));
        }
        
        .status-card.retired.selected::before {
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
        
        .status-card.active .status-icon {
            color: var(--success-color, #2ed573);
        }
        
        .status-card.maintenance .status-icon {
            color: var(--warning-color, #ffa502);
        }
        
        .status-card.retired .status-icon {
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
        
        /* Location Tags */
        .location-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .location-tag {
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
        
        .location-tag:hover {
            background: var(--primary-color, #ff4757);
            color: white;
            transform: translateY(-2px);
            border-color: var(--primary-color, #ff4757);
        }
        
        .location-tag.selected {
            background: var(--primary-color, #ff4757);
            color: white;
            border-color: var(--primary-color, #ff4757);
        }
        
        .location-tag i {
            font-size: 0.8rem;
        }
        
        /* Equipment Preview */
        .equipment-preview {
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
        
        /* Price Input Styling */
        .price-input {
            position: relative;
        }
        
        .price-input:before {
            content: '$';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light, #6c757d);
            font-weight: 500;
        }
        
        .price-input input {
            padding-left: 30px;
        }
        
        /* Notes Character Counter */
        .notes-counter {
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
            
            .equipment-type-cards,
            .status-cards {
                grid-template-columns: repeat(2, 1fr);
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
            
            .location-tags {
                justify-content: center;
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
            
            .equipment-type-cards,
            .status-cards {
                grid-template-columns: 1fr;
            }
            
            .preview-item {
                padding: 0.75rem;
            }
            
            .location-tag {
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
                    <input type="text" placeholder="Search equipment, serial numbers, locations...">
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
                        <i class="fas fa-dumbbell"></i>
                        Add New Equipment
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
                
                <!-- Maintenance Warning -->
                <div class="message warning" id="maintenanceWarning" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="warningText"></span>
                </div>
                
                <!-- Add Form Container -->
                <div class="add-form-container">
                    <form method="POST" class="add-form" id="addEquipmentForm">
                        <div class="form-header">
                            <h2><i class="fas fa-dumbbell"></i> Add New Equipment</h2>
                            <p>Register new gym equipment with maintenance schedule. All fields marked with * are required.</p>
                        </div>
                        
                        <div class="form-steps">
                            <div class="step active">
                                <div class="step-number">1</div>
                                <span>Basic Info</span>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <span>Details & Location</span>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <span>Maintenance & Status</span>
                            </div>
                        </div>
                        
                        <!-- Section 1: Basic Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-info-circle"></i> Equipment Details</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="equipment_name" class="required">Equipment Name</label>
                                    <input type="text" id="equipment_name" name="equipment_name" 
                                           value="<?php echo htmlspecialchars($_POST['equipment_name'] ?? ''); ?>" 
                                           placeholder="e.g., Treadmill Pro 5000, Leg Press Machine" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="brand">Brand</label>
                                    <input type="text" id="brand" name="brand" 
                                           value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>" 
                                           placeholder="e.g., LifeFitness, Hammer Strength">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Equipment Type</label>
                                <input type="hidden" id="equipment_type" name="equipment_type" 
                                       value="<?php echo htmlspecialchars($_POST['equipment_type'] ?? ''); ?>" required>
                                <div class="equipment-type-cards" id="equipmentTypeCards">
                                    <div class="equipment-type-card" data-type="Cardio">
                                        <div class="equipment-type-icon">
                                            <i class="fas fa-running"></i>
                                        </div>
                                        <div class="equipment-type-name">Cardio</div>
                                        <p>Treadmills, ellipticals, bikes, rowing machines</p>
                                    </div>
                                    <div class="equipment-type-card" data-type="Strength">
                                        <div class="equipment-type-icon">
                                            <i class="fas fa-dumbbell"></i>
                                        </div>
                                        <div class="equipment-type-name">Strength</div>
                                        <p>Weight machines, cable systems, racks</p>
                                    </div>
                                    <div class="equipment-type-card" data-type="Free Weights">
                                        <div class="equipment-type-icon">
                                            <i class="fas fa-weight"></i>
                                        </div>
                                        <div class="equipment-type-name">Free Weights</div>
                                        <p>Dumbbells, barbells, plates, kettlebells</p>
                                    </div>
                                    <div class="equipment-type-card" data-type="Functional">
                                        <div class="equipment-type-icon">
                                            <i class="fas fa-cogs"></i>
                                        </div>
                                        <div class="equipment-type-name">Functional</div>
                                        <p>Bands, balls, TRX, foam rollers, mats</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 2: Details & Location -->
                        <div class="form-section">
                            <h3><i class="fas fa-map-marker-alt"></i> Details & Location</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="model">Model Number</label>
                                    <input type="text" id="model" name="model" 
                                           value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>" 
                                           placeholder="e.g., TR5000, LS700">
                                </div>
                                
                                <div class="form-group">
                                    <label for="serial_number">Serial Number</label>
                                    <input type="text" id="serial_number" name="serial_number" 
                                           value="<?php echo htmlspecialchars($_POST['serial_number'] ?? ''); ?>" 
                                           placeholder="e.g., SN-2023-001234">
                                    <small>Unique identifier for tracking</small>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="purchase_date">Purchase Date</label>
                                    <input type="date" id="purchase_date" name="purchase_date" 
                                           value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? ''); ?>">
                                    <small>When the equipment was purchased</small>
                                </div>
                                
                                <div class="form-group price-input">
                                    <label for="purchase_price">Purchase Price ($)</label>
                                    <input type="number" id="purchase_price" name="purchase_price" 
                                           value="<?php echo htmlspecialchars($_POST['purchase_price'] ?? ''); ?>" 
                                           step="0.01" min="0" placeholder="0.00">
                                    <small>Original purchase cost (optional)</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="location" class="required">Location</label>
                                <input type="text" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                       placeholder="e.g., Cardio Zone, Weight Room" required>
                                <div class="location-tags" id="locationTags">
                                    <div class="location-tag" data-location="Cardio Zone">
                                        <i class="fas fa-running"></i>
                                        Cardio Zone
                                    </div>
                                    <div class="location-tag" data-location="Weight Room">
                                        <i class="fas fa-dumbbell"></i>
                                        Weight Room
                                    </div>
                                    <div class="location-tag" data-location="Free Weights Area">
                                        <i class="fas fa-weight"></i>
                                        Free Weights
                                    </div>
                                    <div class="location-tag" data-location="Functional Zone">
                                        <i class="fas fa-cogs"></i>
                                        Functional Zone
                                    </div>
                                    <div class="location-tag" data-location="Studio A">
                                        <i class="fas fa-video"></i>
                                        Studio A
                                    </div>
                                    <div class="location-tag" data-location="Studio B">
                                        <i class="fas fa-video"></i>
                                        Studio B
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 3: Maintenance & Status -->
                        <div class="form-section">
                            <h3><i class="fas fa-tools"></i> Maintenance & Status</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="last_maintenance">Last Maintenance Date</label>
                                    <input type="date" id="last_maintenance" name="last_maintenance" 
                                           value="<?php echo htmlspecialchars($_POST['last_maintenance'] ?? ''); ?>">
                                    <small>Leave blank if no maintenance performed yet</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="next_maintenance">Next Maintenance Due</label>
                                    <input type="date" id="next_maintenance" name="next_maintenance" 
                                           value="<?php echo htmlspecialchars($_POST['next_maintenance'] ?? ''); ?>">
                                    <small>Recommended: 6 months from last maintenance</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Current Status</label>
                                <input type="hidden" id="status" name="status" 
                                       value="<?php echo htmlspecialchars($_POST['status'] ?? 'active'); ?>" required>
                                <div class="status-cards" id="statusCards">
                                    <div class="status-card active" data-status="active">
                                        <div class="status-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="status-name">Active</div>
                                        <div class="status-desc">In use and fully functional. Available for members.</div>
                                    </div>
                                    <div class="status-card maintenance" data-status="maintenance">
                                        <div class="status-icon">
                                            <i class="fas fa-tools"></i>
                                        </div>
                                        <div class="status-name">Maintenance</div>
                                        <div class="status-desc">Currently being serviced or repaired. Not available for use.</div>
                                    </div>
                                    <div class="status-card retired" data-status="retired">
                                        <div class="status-icon">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div class="status-name">Retired</div>
                                        <div class="status-desc">No longer in use. May be sold or disposed of.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" rows="3" 
                                          placeholder="Additional information, special instructions, or repair history..."
                                          oninput="updateNotesCounter()"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                                <div class="notes-counter">
                                    <span id="notesCount">0</span>/500 characters
                                </div>
                                <small>Internal notes for staff reference</small>
                            </div>
                            
                            <!-- Equipment Preview -->
                            <div class="equipment-preview" id="equipmentPreview">
                                <div class="preview-header">
                                    <i class="fas fa-eye"></i>
                                    Equipment Preview
                                </div>
                                <div class="preview-details">
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="fas fa-dumbbell"></i>
                                            Equipment
                                        </div>
                                        <div class="preview-value" id="previewEquipmentName">Not set</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="fas fa-map-marker-alt"></i>
                                            Location
                                        </div>
                                        <div class="preview-value" id="previewLocation">Not set</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="fas fa-tools"></i>
                                            Maintenance
                                        </div>
                                        <div class="preview-value" id="previewMaintenance">Not scheduled</div>
                                    </div>
                                    <div class="preview-item">
                                        <div class="preview-label">
                                            <i class="fas fa-check-circle"></i>
                                            Status
                                        </div>
                                        <div class="preview-value" id="previewStatus">Active</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-dumbbell"></i>
                                Add Equipment
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='admin-equipment.php'">
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
        // Equipment type selection
        document.querySelectorAll('.equipment-type-card').forEach(card => {
            card.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                document.getElementById('equipment_type').value = type;
                
                // Update UI
                document.querySelectorAll('.equipment-type-card').forEach(c => {
                    c.classList.remove('selected');
                });
                this.classList.add('selected');
                
                updateStepIndicator(3);
                updatePreview();
            });
        });
        
        // Auto-select previously selected equipment type
        const selectedType = "<?php echo $_POST['equipment_type'] ?? ''; ?>";
        if(selectedType) {
            document.querySelectorAll('.equipment-type-card').forEach(card => {
                if(card.getAttribute('data-type') === selectedType) {
                    card.classList.add('selected');
                }
            });
        } else {
            // Default select first card
            setTimeout(() => {
                document.querySelector('.equipment-type-card').click();
            }, 100);
        }
        
        // Location tag selection
        document.querySelectorAll('.location-tag').forEach(tag => {
            tag.addEventListener('click', function() {
                const location = this.getAttribute('data-location');
                document.getElementById('location').value = location;
                
                // Update UI
                document.querySelectorAll('.location-tag').forEach(t => {
                    t.classList.remove('selected');
                });
                this.classList.add('selected');
                
                updateStepIndicator(3);
                updatePreview();
            });
        });
        
        // Auto-select previously selected location
        const selectedLocation = "<?php echo $_POST['location'] ?? ''; ?>";
        if(selectedLocation) {
            document.querySelectorAll('.location-tag').forEach(tag => {
                if(tag.getAttribute('data-location') === selectedLocation) {
                    tag.classList.add('selected');
                }
            });
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
        const selectedStatus = "<?php echo $_POST['status'] ?? 'active'; ?>";
        document.querySelectorAll('.status-card').forEach(card => {
            if(card.getAttribute('data-status') === selectedStatus) {
                card.classList.add('selected');
            }
        });
        
        // Update equipment preview
        function updatePreview() {
            const equipmentName = document.getElementById('equipment_name').value;
            const location = document.getElementById('location').value;
            const status = document.getElementById('status').value;
            const nextMaintenance = document.getElementById('next_maintenance').value;
            const lastMaintenance = document.getElementById('last_maintenance').value;
            
            // Update equipment name
            document.getElementById('previewEquipmentName').textContent = equipmentName || 'Not set';
            
            // Update location
            document.getElementById('previewLocation').textContent = location || 'Not set';
            
            // Update status
            const statusMap = {
                'active': '<span style="color: var(--success-color); font-weight: 600;">● Active</span>',
                'maintenance': '<span style="color: var(--warning-color); font-weight: 600;">● Under Maintenance</span>',
                'retired': '<span style="color: var(--danger-color); font-weight: 600;">● Retired</span>'
            };
            document.getElementById('previewStatus').innerHTML = statusMap[status] || 'Active';
            
            // Update maintenance info
            if(nextMaintenance) {
                const nextDate = new Date(nextMaintenance);
                const today = new Date();
                const diffTime = nextDate - today;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                let maintenanceText = `Due: ${nextDate.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric' 
                })}`;
                
                if(diffDays < 0) {
                    maintenanceText += ` <span style="color: var(--danger-color);">(OVERDUE)</span>`;
                } else if(diffDays <= 7) {
                    maintenanceText += ` <span style="color: var(--warning-color);">(${diffDays} days)</span>`;
                } else {
                    maintenanceText += ` (${diffDays} days)`;
                }
                
                document.getElementById('previewMaintenance').innerHTML = maintenanceText;
                
                // Show/hide maintenance warning
                const warningDiv = document.getElementById('maintenanceWarning');
                const warningText = document.getElementById('warningText');
                
                if(diffDays <= 0) {
                    warningText.textContent = 'Maintenance is OVERDUE! Please schedule service immediately.';
                    warningDiv.style.display = 'flex';
                } else if(diffDays <= 7) {
                    warningText.textContent = `Maintenance due in ${diffDays} days. Consider scheduling service soon.`;
                    warningDiv.style.display = 'flex';
                } else {
                    warningDiv.style.display = 'none';
                }
            } else {
                document.getElementById('previewMaintenance').textContent = 'Not scheduled';
                document.getElementById('maintenanceWarning').style.display = 'none';
            }
        }
        
        // Add event listeners for preview updates
        document.getElementById('equipment_name').addEventListener('input', updatePreview);
        document.getElementById('location').addEventListener('input', updatePreview);
        document.getElementById('status').addEventListener('change', updatePreview);
        document.getElementById('last_maintenance').addEventListener('change', updatePreview);
        document.getElementById('next_maintenance').addEventListener('change', updatePreview);
        
        // Auto-set next maintenance to 6 months later when last maintenance is set
        document.getElementById('last_maintenance').addEventListener('change', function() {
            if(this.value && !document.getElementById('next_maintenance').value) {
                const lastDate = new Date(this.value);
                const nextDate = new Date(lastDate);
                nextDate.setMonth(nextDate.getMonth() + 6);
                
                const nextFormatted = nextDate.toISOString().split('T')[0];
                document.getElementById('next_maintenance').value = nextFormatted;
                updatePreview();
            }
        });
        
        // Notes character counter
        function updateNotesCounter() {
            const notes = document.getElementById('notes');
            const counter = document.getElementById('notesCount');
            const charCount = notes.value.length;
            counter.textContent = charCount;
            
            if(charCount > 500) {
                notes.value = notes.value.substring(0, 500);
                counter.textContent = 500;
                counter.style.color = 'var(--danger-color)';
            } else if(charCount > 450) {
                counter.style.color = 'var(--warning-color)';
            } else {
                counter.style.color = 'var(--text-light)';
            }
        }
        
        // Initialize notes counter
        updateNotesCounter();
        
        // Form validation
        document.getElementById('addEquipmentForm').addEventListener('submit', function(e) {
            const equipmentName = document.getElementById('equipment_name').value;
            const equipmentType = document.getElementById('equipment_type').value;
            const location = document.getElementById('location').value;
            const status = document.getElementById('status').value;
            const purchasePrice = document.getElementById('purchase_price').value;
            const lastMaintenance = document.getElementById('last_maintenance').value;
            const nextMaintenance = document.getElementById('next_maintenance').value;
            
            // Check required fields
            if(!equipmentName.trim()) {
                e.preventDefault();
                alert('Equipment name is required!');
                return false;
            }
            
            if(!equipmentType) {
                e.preventDefault();
                alert('Please select an equipment type!');
                return false;
            }
            
            if(!location.trim()) {
                e.preventDefault();
                alert('Location is required!');
                return false;
            }
            
            // Check purchase price format
            if(purchasePrice && (isNaN(purchasePrice) || parseFloat(purchasePrice) < 0)) {
                e.preventDefault();
                alert('Purchase price must be a valid positive number!');
                return false;
            }
            
            // Check maintenance dates
            if(lastMaintenance && nextMaintenance) {
                const lastDate = new Date(lastMaintenance);
                const nextDate = new Date(nextMaintenance);
                
                if(nextDate <= lastDate) {
                    e.preventDefault();
                    alert('Next maintenance date must be after last maintenance date!');
                    return false;
                }
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Equipment...';
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
                if(this.id === 'equipment_name' || document.getElementById('equipment_type').value) {
                    updateStepIndicator(2);
                }
                if(this.id === 'location' || this.id === 'model' || this.id === 'serial_number') {
                    updateStepIndicator(3);
                }
            });
        });
        
        // Initialize step indicator based on form state
        document.addEventListener('DOMContentLoaded', function() {
            // Check if any fields in step 2 are filled
            const step2Filled = document.getElementById('equipment_name').value.trim() !== '' || 
                               document.getElementById('equipment_type').value !== '';
            
            // Check if any fields in step 3 are filled
            const step3Filled = document.getElementById('location').value.trim() !== '' || 
                               document.getElementById('model').value.trim() !== '' || 
                               document.getElementById('serial_number').value.trim() !== '';
            
            if(step3Filled) {
                updateStepIndicator(3);
            } else if(step2Filled) {
                updateStepIndicator(2);
            } else {
                updateStepIndicator(1);
            }
            
            // Auto-fill purchase date to today if empty
            if(!document.getElementById('purchase_date').value) {
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('purchase_date').value = today;
            }
            
            // Initialize preview
            updatePreview();
        });
        
        // Auto-suggest brand based on equipment name
        document.getElementById('equipment_name').addEventListener('input', function() {
            const name = this.value.toLowerCase();
            const brandInput = document.getElementById('brand');
            
            if(!brandInput.value) {
                if(name.includes('treadmill') || name.includes('elliptical') || name.includes('bike') || name.includes('cardio')) {
                    brandInput.value = 'LifeFitness';
                } else if(name.includes('press') || name.includes('rack') || name.includes('machine') || name.includes('strength')) {
                    brandInput.value = 'Hammer Strength';
                } else if(name.includes('dumbbell') || name.includes('barbell') || name.includes('plate') || name.includes('kettlebell')) {
                    brandInput.value = 'Rogue';
                } else if(name.includes('band') || name.includes('trx') || name.includes('functional')) {
                    brandInput.value = 'TRX';
                }
            }
        });
        
        // Add click handlers for location input to clear selected tags
        document.getElementById('location').addEventListener('focus', function() {
            document.querySelectorAll('.location-tag').forEach(tag => {
                tag.classList.remove('selected');
            });
        });
    </script>
</body>
</html>