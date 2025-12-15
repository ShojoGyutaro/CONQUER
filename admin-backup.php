<?php
session_start();
require_once 'config/database.php';
try {
    $pdo = Database::getInstance()->getConnection();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Pass pdo to sidebar
$sidebarPdo = $pdo;
include 'admin-sidebar.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$successMessage = '';
$errorMessage = '';
$backupFiles = [];
$backupPath = __DIR__ . '/backups/';

// Create backup directory if it doesn't exist
if(!file_exists($backupPath)) {
    mkdir($backupPath, 0755, true);
}

// Handle backup creation
if(isset($_POST['create_backup'])) {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Get all tables
        $tables = [];
        $result = $pdo->query("SHOW TABLES");
        while($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        if(empty($tables)) {
            throw new Exception("No tables found in database");
        }
        
        // Create backup SQL
        $backupSQL = "-- CONQUER Gym Database Backup\n";
        $backupSQL .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $backupSQL .= "-- Database: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n\n";
        
        foreach($tables as $table) {
            // Drop table if exists
            $backupSQL .= "DROP TABLE IF EXISTS `$table`;\n\n";
            
            // Create table structure
            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $backupSQL .= $createTable[1] . ";\n\n";
            
            // Get table data
            $result = $pdo->query("SELECT * FROM `$table`");
            $rowCount = $result->rowCount();
            
            if($rowCount > 0) {
                $backupSQL .= "-- Dumping data for table `$table`\n";
                $backupSQL .= "INSERT INTO `$table` VALUES\n";
                
                $rows = [];
                while($row = $result->fetch(PDO::FETCH_NUM)) {
                    // Escape values
                    $escapedValues = array_map(function($value) use ($pdo) {
                        if($value === null) return 'NULL';
                        return $pdo->quote($value);
                    }, $row);
                    
                    $rows[] = "(" . implode(',', $escapedValues) . ")";
                }
                
                $backupSQL .= implode(",\n", $rows) . ";\n\n";
            }
        }
        
        // Save backup file
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupPath . $filename;
        
        if(file_put_contents($filepath, $backupSQL)) {
            $successMessage = "Backup created successfully: $filename";
            
            // Compress backup
            if(function_exists('gzencode')) {
                $compressed = gzencode($backupSQL, 9);
                $compressedFilename = 'backup_' . date('Y-m-d_H-i-s') . '.sql.gz';
                $compressedFilepath = $backupPath . $compressedFilename;
                
                if(file_put_contents($compressedFilepath, $compressed)) {
                    $successMessage .= " (Compressed version also created)";
                }
            }
        } else {
            throw new Exception("Failed to write backup file");
        }
        
    } catch(Exception $e) {
        $errorMessage = "Backup failed: " . $e->getMessage();
    }
}

// Handle backup restore
if(isset($_POST['restore_backup']) && isset($_POST['backup_file'])) {
    $backupFile = $_POST['backup_file'];
    $filepath = $backupPath . $backupFile;
    
    if(file_exists($filepath)) {
        try {
            $pdo = Database::getInstance()->getConnection();
            
            // Read backup file
            $backupSQL = file_get_contents($filepath);
            
            // Check if file is gzipped
            if(pathinfo($filepath, PATHINFO_EXTENSION) === 'gz') {
                if(function_exists('gzdecode')) {
                    $backupSQL = gzdecode($backupSQL);
                } else {
                    throw new Exception("Cannot decompress gzipped file. gzdecode() not available.");
                }
            }
            
            // Execute backup SQL
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            $queries = explode(";\n", $backupSQL);
            foreach($queries as $query) {
                $query = trim($query);
                if(!empty($query) && !preg_match('/^--/', $query)) {
                    try {
                        $pdo->exec($query);
                    } catch(PDOException $e) {
                        // Skip errors for DROP TABLE IF EXISTS on non-existent tables
                        if(!preg_match('/DROP TABLE IF EXISTS/', $query)) {
                            throw $e;
                        }
                    }
                }
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $successMessage = "Backup restored successfully from: $backupFile";
            
        } catch(Exception $e) {
            $errorMessage = "Restore failed: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Backup file not found: $backupFile";
    }
}

// Handle backup deletion
if(isset($_POST['delete_backup']) && isset($_POST['backup_file'])) {
    $backupFile = $_POST['backup_file'];
    $filepath = $backupPath . $backupFile;
    
    if(file_exists($filepath)) {
        if(unlink($filepath)) {
            $successMessage = "Backup deleted successfully: $backupFile";
        } else {
            $errorMessage = "Failed to delete backup file";
        }
    } else {
        $errorMessage = "Backup file not found: $backupFile";
    }
}

// List backup files
$backupFiles = [];
if(file_exists($backupPath)) {
    $files = scandir($backupPath);
    foreach($files as $file) {
        if($file !== '.' && $file !== '..' && (strpos($file, 'backup_') === 0)) {
            $filepath = $backupPath . $file;
            $backupFiles[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'modified' => filemtime($filepath),
                'is_compressed' => (pathinfo($file, PATHINFO_EXTENSION) === 'gz')
            ];
        }
    }
    
    // Sort by modification time (newest first)
    usort($backupFiles, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

$adminName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup | CONQUER Gym Admin</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
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
        
        .message.info {
            border-left-color: var(--info-color, #3498db);
            background: rgba(52, 152, 219, 0.1);
            color: var(--info-color, #3498db);
        }
        
        /* Backup Controls - Matching Report Controls */
        .backup-controls {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .backup-form {
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
        
        /* Control Cards - Matching Report Type Cards */
        .control-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .control-card {
            border: 2px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-align: center;
            background: var(--white, #ffffff);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .control-card:hover {
            border-color: var(--primary-color, #ff4757);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md, 0 4px 20px rgba(0,0,0,0.15));
        }
        
        .control-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color, #ff4757);
            display: inline-block;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(255, 71, 87, 0.1) 0%, rgba(255, 71, 87, 0.05) 100%);
            border-radius: 12px;
        }
        
        .control-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.75rem;
        }
        
        .control-card p {
            color: var(--text-light, #6c757d);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
            flex-grow: 1;
        }
        
        .control-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin-top: auto;
        }
        
        /* Database Info - Matching Summary Stats */
        .database-info {
            background: var(--light-color, #f1f2f6);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 2px solid var(--border-color, #e0e0e0);
        }
        
        .info-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color, #2f3542);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-header i {
            color: var(--primary-color, #ff4757);
        }
        
        .info-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .info-stat {
            background: var(--white, #ffffff);
            padding: 1rem;
            border-radius: var(--radius-md, 12px);
            border: 1px solid var(--border-color, #e0e0e0);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .info-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-light, #6c757d);
            font-weight: 500;
        }
        
        /* Backup Files - Matching Report Table */
        .backup-files {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            overflow: hidden;
            margin-top: 1.5rem;
        }
        
        .files-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color, #e0e0e0);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .files-header h3 {
            font-size: 1.2rem;
            color: var(--dark-color, #2f3542);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }
        
        .files-header h3 i {
            color: var(--primary-color, #ff4757);
        }
        
        .files-body {
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
            white-space: nowrap;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color, #e0e0e0);
            color: var(--dark-color, #2f3542);
            font-size: 0.95rem;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background: var(--light-color, #f1f2f6);
        }
        
        /* File Icon */
        .file-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color, #ff4757) 0%, var(--primary-dark, #ff2e43) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 1rem;
        }
        
        .file-info {
            display: flex;
            align-items: center;
        }
        
        .file-name {
            font-weight: 600;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.25rem;
        }
        
        .file-size {
            font-size: 0.85rem;
            color: var(--text-light, #6c757d);
        }
        
        .compression-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            background: var(--info-color, #3498db);
            color: white;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        /* Button Styles */
        .btn {
            padding: 0.75rem 1.5rem;
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
        }
        
        .btn-success:hover {
            background: #25c464;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 213, 115, 0.3);
        }
        
        .btn-warning {
            background: var(--warning-color, #ffa502);
            color: white;
        }
        
        .btn-warning:hover {
            background: #e69500;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 165, 2, 0.3);
        }
        
        .btn-danger {
            background: var(--danger-color, #ff4757);
            color: white;
        }
        
        .btn-danger:hover {
            background: #ff2e43;
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
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .file-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        /* Empty State */
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            margin-bottom: 1rem;
        }
        
        .modal-header h3 {
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-body {
            margin-bottom: 1.5rem;
            color: var(--dark-color, #2f3542);
            line-height: 1.6;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        /* Loading animation */
        .fa-spinner.fa-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive Design */
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
            
            .backup-controls,
            .backup-files {
                margin: 0 -1rem;
                border-radius: 0;
            }
            
            .backup-form {
                padding: 1.5rem;
            }
            
            .control-cards {
                grid-template-columns: 1fr;
            }
            
            .info-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .control-actions {
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
            
            .files-header {
                flex-direction: column;
                text-align: center;
            }
            
            .file-actions {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-content {
                padding: 0.75rem;
            }
            
            .backup-form {
                padding: 1rem;
            }
            
            .info-stats {
                grid-template-columns: 1fr;
            }
            
            .info-stat {
                padding: 0.75rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
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
                    <input type="text" placeholder="Search backup files...">
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
                        <i class="fas fa-database"></i>
                        Database Backup
                    </h1>
                    <a href="admin-dashboard.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
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
                
                <!-- Warning Message -->
                <div class="message warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Important:</strong> Regular database backups are essential for data protection. 
                        Always test your backups and store them in a secure location.
                    </div>
                </div>
                
                <!-- Database Information -->
                <?php
                try {
                    $pdo = Database::getInstance()->getConnection();
                    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
                    $tableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
                    $totalSize = $pdo->query("
                        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
                        FROM information_schema.tables 
                        WHERE table_schema = DATABASE()
                    ")->fetchColumn();
                ?>
                <div class="database-info">
                    <div class="info-header">
                        <i class="fas fa-info-circle"></i>
                        Database Information
                    </div>
                    <div class="info-stats">
                        <div class="info-stat">
                            <div class="stat-value"><?php echo htmlspecialchars($dbName); ?></div>
                            <div class="stat-label">Database Name</div>
                        </div>
                        <div class="info-stat">
                            <div class="stat-value"><?php echo $tableCount; ?></div>
                            <div class="stat-label">Total Tables</div>
                        </div>
                        <div class="info-stat">
                            <div class="stat-value"><?php echo $totalSize; ?> MB</div>
                            <div class="stat-label">Database Size</div>
                        </div>
                        <div class="info-stat">
                            <div class="stat-value"><?php echo count($backupFiles); ?></div>
                            <div class="stat-label">Backups Available</div>
                        </div>
                    </div>
                </div>
                <?php } catch(Exception $e) { ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i>
                        Unable to retrieve database information: <?php echo $e->getMessage(); ?>
                    </div>
                <?php } ?>
                
                <!-- Backup Controls -->
                <div class="backup-controls">
                    <div class="backup-form">
                        <div class="form-header">
                            <h2><i class="fas fa-database"></i> Database Backup & Restore</h2>
                            <p>Create, restore, or manage database backups</p>
                        </div>
                        
                        <div class="control-cards">
                            <!-- Create Backup -->
                            <div class="control-card">
                                <div class="control-icon">
                                    <i class="fas fa-save"></i>
                                </div>
                                <div class="control-name">Create New Backup</div>
                                <p>Create a complete backup of the entire database. This may take a few moments depending on database size.</p>
                                <form method="POST" onsubmit="return confirmCreateBackup()">
                                    <div class="control-actions">
                                        <button type="submit" name="create_backup" class="btn btn-primary">
                                            <i class="fas fa-plus-circle"></i>
                                            Create Backup
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Auto Backup Settings -->
                            <div class="control-card">
                                <div class="control-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="control-name">Auto Backup Settings</div>
                                <p>Configure automatic backup schedules (requires cron job setup on server).</p>
                                <div class="control-actions">
                                    <button type="button" class="btn btn-secondary" onclick="showAutoBackupSettings()">
                                        <i class="fas fa-cog"></i>
                                        Configure
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="showCronInstructions()">
                                        <i class="fas fa-info-circle"></i>
                                        Instructions
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Download All -->
                            <div class="control-card">
                                <div class="control-icon">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="control-name">Download Archive</div>
                                <p>Download all backup files as a single compressed archive for safe keeping.</p>
                                <div class="control-actions">
                                    <button type="button" class="btn btn-success" onclick="downloadAllBackups()">
                                        <i class="fas fa-file-archive"></i>
                                        Download All
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Backup Files -->
                <div class="backup-files">
                    <div class="files-header">
                        <h3>
                            <i class="fas fa-folder"></i>
                            Available Backups
                        </h3>
                        <div style="color: var(--text-light); font-size: 0.9rem;">
                            Backup path: <?php echo htmlspecialchars($backupPath); ?>
                        </div>
                    </div>
                    
                    <div class="files-body">
                        <?php if(empty($backupFiles)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h4>No Backup Files Found</h4>
                                <p>Create your first backup to protect your database.</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Backup File</th>
                                        <th>Size</th>
                                        <th>Modified</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($backupFiles as $file): ?>
                                        <tr>
                                            <td>
                                                <div class="file-info">
                                                    <div class="file-icon">
                                                        <i class="fas fa-database"></i>
                                                    </div>
                                                    <div>
                                                        <div class="file-name">
                                                            <?php echo htmlspecialchars($file['name']); ?>
                                                            <?php if($file['is_compressed']): ?>
                                                                <span class="compression-badge">GZ</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="file-size">
                                                            <?php 
                                                            $size = $file['size'];
                                                            if($size < 1024) {
                                                                echo $size . ' B';
                                                            } elseif($size < 1048576) {
                                                                echo round($size / 1024, 2) . ' KB';
                                                            } else {
                                                                echo round($size / 1048576, 2) . ' MB';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($file['is_compressed']): ?>
                                                    <span style="color: var(--info-color); font-weight: 500;">Compressed</span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-light);">SQL</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('Y-m-d H:i:s', $file['modified']); ?>
                                            </td>
                                            <td>
                                                <div class="file-actions">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="downloadBackup('<?php echo htmlspecialchars($file['name']); ?>')">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                        <button type="submit" name="restore_backup" class="btn btn-warning btn-sm" onclick="return confirmRestore('<?php echo htmlspecialchars($file['name']); ?>')">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                        <button type="submit" name="delete_backup" class="btn btn-danger btn-sm" onclick="return confirmDelete('<?php echo htmlspecialchars($file['name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal" id="confirmationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Confirm Action</h3>
            </div>
            <div class="modal-body" id="modalMessage">
                Are you sure you want to proceed?
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn" id="modalConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
    
    <!-- Auto Backup Settings Modal -->
    <div class="modal" id="autoBackupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-clock"></i> Auto Backup Settings</h3>
            </div>
            <div class="modal-body">
                <p>Configure automatic backup schedules (requires cron job setup):</p>
                
                <div style="margin: 1.5rem 0;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--dark-color);">Backup Frequency</label>
                    <select id="backupFrequency" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--white); color: var(--dark-color);">
                        <option value="daily">Daily (at midnight)</option>
                        <option value="weekly">Weekly (Sunday at midnight)</option>
                        <option value="monthly">Monthly (1st of month at midnight)</option>
                    </select>
                </div>
                
                <div style="margin: 1.5rem 0;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--dark-color);">Keep Backups For</label>
                    <select id="retentionPeriod" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--white); color: var(--dark-color);">
                        <option value="7">7 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="90">90 days</option>
                        <option value="365">1 year</option>
                        <option value="0">Keep forever</option>
                    </select>
                </div>
                
                <div style="background: var(--light-color); padding: 1rem; border-radius: var(--radius-md); margin-top: 1rem;">
                    <strong style="color: var(--dark-color);">Cron Command:</strong>
                    <code id="cronCommand" style="display: block; margin-top: 0.5rem; padding: 0.75rem; background: var(--white); border-radius: var(--radius-md); font-family: monospace; color: var(--dark-color); border: 1px solid var(--border-color);">
                        <?php echo "0 0 * * * php " . __DIR__ . "/backup-cron.php >> " . __DIR__ . "/backup-log.txt 2>&1"; ?>
                    </code>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAutoBackupSettings()">Save Settings</button>
            </div>
        </div>
    </div>
    
    <!-- Cron Instructions Modal -->
    <div class="modal" id="cronInstructionsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Cron Job Instructions</h3>
            </div>
            <div class="modal-body">
                <p>To set up automatic backups, configure a cron job on your server:</p>
                
                <div style="margin: 1rem 0;">
                    <h4 style="margin-bottom: 0.5rem; color: var(--dark-color);">1. SSH into your server</h4>
                    <code style="display: block; padding: 0.75rem; background: var(--light-color); border-radius: var(--radius-md); font-family: monospace; color: var(--dark-color);">
                        ssh username@yourserver.com
                    </code>
                </div>
                
                <div style="margin: 1rem 0;">
                    <h4 style="margin-bottom: 0.5rem; color: var(--dark-color);">2. Edit crontab</h4>
                    <code style="display: block; padding: 0.75rem; background: var(--light-color); border-radius: var(--radius-md); font-family: monospace; color: var(--dark-color);">
                        crontab -e
                    </code>
                </div>
                
                <div style="margin: 1rem 0;">
                    <h4 style="margin-bottom: 0.5rem; color: var(--dark-color);">3. Add cron command</h4>
                    <code style="display: block; padding: 0.75rem; background: var(--light-color); border-radius: var(--radius-md); font-family: monospace; color: var(--dark-color); white-space: pre-wrap;">
# Daily backup at midnight
0 0 * * * php <?php echo __DIR__; ?>/backup-cron.php >> <?php echo __DIR__; ?>/backup-log.txt 2>&1

# Weekly backup on Sunday at midnight
0 0 * * 0 php <?php echo __DIR__; ?>/backup-cron.php weekly >> <?php echo __DIR__; ?>/backup-log.txt 2>&1
                    </code>
                </div>
                
                <div style="background: var(--warning-color, rgba(255, 165, 2, 0.1)); padding: 1rem; border-radius: var(--radius-md); margin-top: 1rem; border-left: 4px solid var(--warning-color, #ffa502);">
                    <strong>Note:</strong> Make sure the backup directory (<code><?php echo $backupPath; ?></code>) is writable.
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-primary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Modal functions
        let currentAction = '';
        let currentFile = '';
        
        function showModal(title, message, confirmText, confirmClass, action, file = '') {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalMessage').innerHTML = message;
            const confirmBtn = document.getElementById('modalConfirmBtn');
            confirmBtn.textContent = confirmText;
            confirmBtn.className = 'btn ' + confirmClass;
            currentAction = action;
            currentFile = file;
            document.getElementById('confirmationModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('confirmationModal').style.display = 'none';
            document.getElementById('autoBackupModal').style.display = 'none';
            document.getElementById('cronInstructionsModal').style.display = 'none';
            currentAction = '';
            currentFile = '';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if(event.target === modal) {
                    closeModal();
                }
            });
        });
        
        // Handle modal confirmation
        document.getElementById('modalConfirmBtn').addEventListener('click', function() {
            switch(currentAction) {
                case 'create_backup':
                    document.querySelector('form[onsubmit="return confirmCreateBackup()"]').submit();
                    break;
                case 'restore':
                    const restoreForm = document.querySelector(`form button[name="restore_backup"][onclick*="${currentFile}"]`).closest('form');
                    restoreForm.submit();
                    break;
                case 'delete':
                    const deleteForm = document.querySelector(`form button[name="delete_backup"][onclick*="${currentFile}"]`).closest('form');
                    deleteForm.submit();
                    break;
            }
            closeModal();
        });
        
        // Confirmation functions
        function confirmCreateBackup() {
            showModal(
                'Create Backup',
                '<div class="message info" style="margin: 1rem 0;">' +
                '<i class="fas fa-info-circle"></i> Creating a new database backup. This may take a few moments.' +
                '</div>' +
                '<p>Are you sure you want to proceed?</p>',
                'Create Backup',
                'btn-primary',
                'create_backup'
            );
            return false;
        }
        
        function confirmRestore(filename) {
            showModal(
                'Restore Backup',
                `<div class="message warning" style="margin: 1rem 0;">` +
                `<i class="fas fa-exclamation-triangle"></i> This will overwrite ALL current data!` +
                `</div>` +
                `<p>Restore from backup file: <code>${filename}</code></p>` +
                `<div class="message info" style="margin: 1rem 0;">` +
                `<i class="fas fa-info-circle"></i> Make sure you have a recent backup before proceeding.` +
                `</div>`,
                'Restore Backup',
                'btn-warning',
                'restore',
                filename
            );
            return false;
        }
        
        function confirmDelete(filename) {
            showModal(
                'Delete Backup',
                `<p>Delete backup file: <code>${filename}</code></p>` +
                `<div class="message warning" style="margin: 1rem 0;">` +
                `<i class="fas fa-exclamation-triangle"></i> This action cannot be undone.` +
                `</div>`,
                'Delete Backup',
                'btn-danger',
                'delete',
                filename
            );
            return false;
        }
        
        // Download backup file
        function downloadBackup(filename) {
            window.location.href = `download-backup.php?file=${encodeURIComponent(filename)}`;
        }
        
        // Show auto backup settings
        function showAutoBackupSettings() {
            document.getElementById('autoBackupModal').style.display = 'flex';
        }
        
        // Show cron instructions
        function showCronInstructions() {
            document.getElementById('cronInstructionsModal').style.display = 'flex';
        }
        
        // Save auto backup settings
        function saveAutoBackupSettings() {
            const frequency = document.getElementById('backupFrequency').value;
            const retention = document.getElementById('retentionPeriod').value;
            
            // Update cron command based on frequency
            let cronCommand = '';
            switch(frequency) {
                case 'daily':
                    cronCommand = `0 0 * * * php <?php echo __DIR__; ?>/backup-cron.php >> <?php echo __DIR__; ?>/backup-log.txt 2>&1`;
                    break;
                case 'weekly':
                    cronCommand = `0 0 * * 0 php <?php echo __DIR__; ?>/backup-cron.php weekly >> <?php echo __DIR__; ?>/backup-log.txt 2>&1`;
                    break;
                case 'monthly':
                    cronCommand = `0 0 1 * * php <?php echo __DIR__; ?>/backup-cron.php monthly >> <?php echo __DIR__; ?>/backup-log.txt 2>&1`;
                    break;
            }
            
            document.getElementById('cronCommand').textContent = cronCommand;
            
            // Show success message
            alert('Settings saved! Remember to configure the cron job on your server.');
            closeModal();
        }
        
        // Download all backups as archive
        function downloadAllBackups() {
            if(<?php echo count($backupFiles); ?> === 0) {
                alert('No backup files to download.');
                return;
            }
            
            showModal(
                'Download All Backups',
                `Download all ${<?php echo count($backupFiles); ?>} backup files as a ZIP archive?<br><br>` +
                `<div class="message info" style="margin: 1rem 0;">` +
                `<i class="fas fa-info-circle"></i> This will create a compressed archive for easy storage.` +
                `</div>`,
                'Download Archive',
                'btn-success',
                'download_all'
            );
            
            // For demo purposes
            document.getElementById('modalConfirmBtn').onclick = function() {
                alert('In production, this would download a ZIP archive of all backups.');
                closeModal();
            };
        }
        
        // Update cron command when frequency changes
        document.getElementById('backupFrequency').addEventListener('change', function() {
            const frequency = this.value;
            let cronCommand = '';
            
            switch(frequency) {
                case 'daily':
                    cronCommand = `0 0 * * * php <?php echo __DIR__; ?>/backup-cron.php >> <?php echo __DIR__; ?>/backup-log.txt 2>&1`;
                    break;
                case 'weekly':
                    cronCommand = `0 0 * * 0 php <?php echo __DIR__; ?>/backup-cron.php weekly >> <?php echo __DIR__; ?>/backup-log.txt 2>&1`;
                    break;
                case 'monthly':
                    cronCommand = `0 0 1 * * php <?php echo __DIR__; ?>/backup-cron.php monthly >> <?php echo __DIR__; ?>/backup-log.txt 2>&1`;
                    break;
            }
            
            document.getElementById('cronCommand').textContent = cronCommand;
        });
        
        // Check backup status
        function checkBackupStatus() {
            const backupFiles = <?php echo json_encode($backupFiles); ?>;
            
            if(backupFiles.length === 0) {
                document.querySelector('.message.warning').innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Warning:</strong> No database backups found! Create your first backup now.
                    </div>
                `;
            } else {
                const latestBackup = backupFiles[0];
                const oneWeekAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
                
                if(latestBackup.modified * 1000 < oneWeekAgo) {
                    document.querySelector('.message.warning').innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Notice:</strong> Last backup was ${new Date(latestBackup.modified * 1000).toLocaleDateString()}. 
                            Consider creating a new backup.
                        </div>
                    `;
                }
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            checkBackupStatus();
            
            // Auto-refresh indicator
            setInterval(() => {
                const countElement = document.querySelector('.info-stat:last-child .stat-value');
                if(countElement) {
                    countElement.style.color = 'var(--primary-color)';
                    setTimeout(() => {
                        countElement.style.color = 'var(--dark-color)';
                    }, 500);
                }
            }, 30000);
        });
    </script>
</body>
</html>