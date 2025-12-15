<?php
session_start();
require_once 'config/database.php';

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f5f7fa;
            color: #333;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar - Same as before */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1a1f2e 0%, #2d3748 100%);
            color: white;
            position: fixed;
            height: 100vh;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .sidebar-logo i {
            color: #ff4757;
        }
        
        .admin-badge {
            background: #ff4757;
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-avatar.admin {
            background: #667eea;
        }
        
        .user-details h4 {
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        
        .user-details p {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 3px solid #ff4757;
        }
        
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 3px solid #ff4757;
        }
        
        .sidebar-nav a i {
            width: 20px;
            margin-right: 0.8rem;
        }
        
        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            position: absolute;
            bottom: 0;
            width: 100%;
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 2rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background: #e2e8f0;
            color: #4a5568;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        
        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .message.error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        
        .message.warning {
            background: #feebc8;
            color: #744210;
            border: 1px solid #f6ad55;
        }
        
        .message.info {
            background: #bee3f8;
            color: #2c5282;
            border: 1px solid #90cdf4;
        }
        
        /* Backup Controls */
        .backup-controls {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .controls-header {
            margin-bottom: 1.5rem;
        }
        
        .controls-header h2 {
            color: #2d3748;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .controls-header p {
            color: #718096;
        }
        
        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .control-card {
            background: #f7fafc;
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .control-card h3 {
            color: #2d3748;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .control-card p {
            color: #718096;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        
        .control-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-success {
            background: #38a169;
            color: white;
        }
        
        .btn-success:hover {
            background: #2f855a;
        }
        
        .btn-danger {
            background: #e53e3e;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c53030;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .btn-warning {
            background: #d69e2e;
            color: white;
        }
        
        .btn-warning:hover {
            background: #b7791f;
        }
        
        /* Backup Files */
        .backup-files {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .files-header {
            margin-bottom: 1.5rem;
        }
        
        .files-header h3 {
            color: #2d3748;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .files-header p {
            color: #718096;
        }
        
        .files-list {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        table th {
            text-align: left;
            padding: 1rem;
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }
        
        table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        table tr:hover {
            background: #f7fafc;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #667eea;
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
            font-weight: 500;
            color: #2d3748;
        }
        
        .file-size {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .file-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }
        
        /* Database Info */
        .database-info {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            text-align: center;
            padding: 1rem;
            background: #f7fafc;
            border-radius: 8px;
        }
        
        .info-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #718096;
        }
        
        /* Modal for confirmation */
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
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            margin-bottom: 1rem;
        }
        
        .modal-header h3 {
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .modal-body {
            margin-bottom: 1.5rem;
            color: #4a5568;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .sidebar-logo span:not(:first-child),
            .sidebar .user-details,
            .sidebar-nav a span,
            .logout-btn span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 1rem;
            }
            
            .controls-grid {
                grid-template-columns: 1fr;
            }
            
            .control-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .file-actions {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-dumbbell"></i>
                    <span>CONQUER</span>
                    <span class="admin-badge">ADMIN</span>
                </div>
                <div class="user-profile">
                    <div class="user-avatar admin">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($adminName); ?></h4>
                        <p>System Administrator</p>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="admin-dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="admin-members.php">
                    <i class="fas fa-users"></i>
                    <span>Members</span>
                </a>
                <a href="admin-trainers.php">
                    <i class="fas fa-user-tie"></i>
                    <span>Trainers</span>
                </a>
                <a href="admin-classes.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Classes</span>
                </a>
                <a href="admin-payments.php">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
                <a href="admin-stories.php">
                    <i class="fas fa-trophy"></i>
                    <span>Success Stories</span>
                </a>
                <a href="admin-equipment.php">
                    <i class="fas fa-dumbbell"></i>
                    <span>Equipment</span>
                </a>
                <a href="admin-messages.php">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
                <a href="admin-reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="admin-settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
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
            
            <!-- Warning about backup -->
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
                <h3 style="margin-bottom: 1rem; color: #2d3748; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-database"></i> Database Information
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-value"><?php echo htmlspecialchars($dbName); ?></div>
                        <div class="info-label">Database Name</div>
                    </div>
                    <div class="info-item">
                        <div class="info-value"><?php echo $tableCount; ?></div>
                        <div class="info-label">Total Tables</div>
                    </div>
                    <div class="info-item">
                        <div class="info-value"><?php echo $totalSize; ?> MB</div>
                        <div class="info-label">Database Size</div>
                    </div>
                    <div class="info-item">
                        <div class="info-value"><?php echo count($backupFiles); ?></div>
                        <div class="info-label">Backups Available</div>
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
                <div class="controls-header">
                    <h2><i class="fas fa-database"></i> Database Backup & Restore</h2>
                    <p>Create, restore, or manage database backups</p>
                </div>
                
                <div class="controls-grid">
                    <!-- Create Backup -->
                    <div class="control-card">
                        <h3><i class="fas fa-plus-circle"></i> Create New Backup</h3>
                        <p>Create a complete backup of the entire database. This may take a few moments depending on database size.</p>
                        <form method="POST" onsubmit="return confirmCreateBackup()">
                            <div class="control-actions">
                                <button type="submit" name="create_backup" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Create Backup Now
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Auto Backup Settings -->
                    <div class="control-card">
                        <h3><i class="fas fa-clock"></i> Auto Backup Settings</h3>
                        <p>Configure automatic backup schedules (requires cron job setup on server).</p>
                        <div class="control-actions">
                            <button type="button" class="btn btn-secondary" onclick="showAutoBackupSettings()">
                                <i class="fas fa-cog"></i>
                                Configure Schedule
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="showCronInstructions()">
                                <i class="fas fa-info-circle"></i>
                                View Instructions
                            </button>
                        </div>
                    </div>
                    
                    <!-- Download All -->
                    <div class="control-card">
                        <h3><i class="fas fa-download"></i> Download All Backups</h3>
                        <p>Download all backup files as a single compressed archive.</p>
                        <div class="control-actions">
                            <button type="button" class="btn btn-success" onclick="downloadAllBackups()">
                                <i class="fas fa-file-archive"></i>
                                Download Archive
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Backup Files -->
            <div class="backup-files">
                <div class="files-header">
                    <h3><i class="fas fa-folder"></i> Available Backups</h3>
                    <p>Manage existing backup files. Backups are stored in: <?php echo htmlspecialchars($backupPath); ?></p>
                </div>
                
                <?php if(empty($backupFiles)): ?>
                    <div style="text-align: center; padding: 3rem;">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: #cbd5e0; margin-bottom: 1rem;"></i>
                        <div style="color: #718096;">No backup files found. Create your first backup above.</div>
                    </div>
                <?php else: ?>
                    <div class="files-list">
                        <table>
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
                                                            <span style="font-size: 0.75rem; background: #4fd1c5; color: white; padding: 0.1rem 0.4rem; border-radius: 4px; margin-left: 0.5rem;">GZ</span>
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
                                            <?php 
                                            if($file['is_compressed']) {
                                                echo '<span style="color: #38a169;">Compressed</span>';
                                            } else {
                                                echo '<span style="color: #718096;">SQL</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo date('Y-m-d H:i:s', $file['modified']); ?>
                                        </td>
                                        <td>
                                            <div class="file-actions">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                    <button type="submit" name="download_backup" class="btn btn-secondary btn-sm" onclick="event.preventDefault(); downloadBackup('<?php echo htmlspecialchars($file['name']); ?>')">
                                                        <i class="fas fa-download"></i>
                                                        Download
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                    <button type="submit" name="restore_backup" class="btn btn-warning btn-sm" onclick="return confirmRestore('<?php echo htmlspecialchars($file['name']); ?>')">
                                                        <i class="fas fa-undo"></i>
                                                        Restore
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                                    <button type="submit" name="delete_backup" class="btn btn-danger btn-sm" onclick="return confirmDelete('<?php echo htmlspecialchars($file['name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
                <p>Automatic backups require cron job setup on your server. Configure the schedule below:</p>
                
                <div style="margin: 1.5rem 0;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Backup Frequency</label>
                    <select id="backupFrequency" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="daily">Daily (at midnight)</option>
                        <option value="weekly">Weekly (Sunday at midnight)</option>
                        <option value="monthly">Monthly (1st of month at midnight)</option>
                    </select>
                </div>
                
                <div style="margin: 1.5rem 0;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Keep Backups For</label>
                    <select id="retentionPeriod" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="7">7 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="90">90 days</option>
                        <option value="365">1 year</option>
                        <option value="0">Keep forever</option>
                    </select>
                </div>
                
                <div style="background: #f7fafc; padding: 1rem; border-radius: 6px; margin-top: 1rem;">
                    <strong>Cron Command:</strong>
                    <code id="cronCommand" style="display: block; margin-top: 0.5rem; padding: 0.5rem; background: white; border-radius: 4px; font-family: monospace;">
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
                <p>To set up automatic backups, you need to configure a cron job on your server:</p>
                
                <div style="margin: 1.5rem 0;">
                    <h4 style="margin-bottom: 0.5rem;">1. SSH into your server</h4>
                    <code style="display: block; padding: 0.5rem; background: #f7fafc; border-radius: 4px; font-family: monospace;">
                        ssh username@yourserver.com
                    </code>
                </div>
                
                <div style="margin: 1.5rem 0;">
                    <h4 style="margin-bottom: 0.5rem;">2. Edit crontab</h4>
                    <code style="display: block; padding: 0.5rem; background: #f7fafc; border-radius: 4px; font-family: monospace;">
                        crontab -e
                    </code>
                </div>
                
                <div style="margin: 1.5rem 0;">
                    <h4 style="margin-bottom: 0.5rem;">3. Add the cron command</h4>
                    <code style="display: block; padding: 0.5rem; background: #f7fafc; border-radius: 4px; font-family: monospace; white-space: pre-wrap;">
# Daily backup at midnight
0 0 * * * php <?php echo __DIR__; ?>/backup-cron.php >> <?php echo __DIR__; ?>/backup-log.txt 2>&1

# Weekly backup on Sunday at midnight
0 0 * * 0 php <?php echo __DIR__; ?>/backup-cron.php weekly >> <?php echo __DIR__; ?>/backup-log.txt 2>&1
                    </code>
                </div>
                
                <div style="margin: 1.5rem 0;">
                    <h4 style="margin-bottom: 0.5rem;">4. Save and exit</h4>
                    <p>Press <kbd>Ctrl + X</kbd>, then <kbd>Y</kbd>, then <kbd>Enter</kbd> to save.</p>
                </div>
                
                <div style="background: #feebc8; padding: 1rem; border-radius: 6px; margin-top: 1rem;">
                    <strong>Note:</strong> Make sure the backup directory (<code><?php echo $backupPath; ?></code>) is writable by the web server.
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
                'Are you sure you want to create a new database backup?<br><br>' +
                '<div class="message info" style="margin: 1rem 0;">' +
                '<i class="fas fa-info-circle"></i> The backup process may take a few moments. Do not close this page.' +
                '</div>',
                'Create Backup',
                'btn-primary',
                'create_backup'
            );
            return false;
        }
        
        function confirmRestore(filename) {
            showModal(
                'Restore Backup',
                `<strong>Warning:</strong> This will restore the database from backup file:<br><br>` +
                `<code>${filename}</code><br><br>` +
                `<div class="message warning" style="margin: 1rem 0;">` +
                `<i class="fas fa-exclamation-triangle"></i> This will overwrite all current data! Make sure you have a recent backup before proceeding.` +
                `</div>` +
                `<div class="message info" style="margin: 1rem 0;">` +
                `<i class="fas fa-info-circle"></i> The restore process may take several minutes. Do not close this page.` +
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
                `Are you sure you want to delete backup file:<br><br>` +
                `<code>${filename}</code><br><br>` +
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
            
            // In a real application, you would save these settings to a configuration file or database
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
                'Download all backup files as a single ZIP archive?<br><br>' +
                '<div class="message info" style="margin: 1rem 0;">' +
                `<i class="fas fa-info-circle"></i> This will create an archive containing ${<?php echo count($backupFiles); ?>} backup files.` +
                '</div>',
                'Download Archive',
                'btn-success',
                'download_all'
            );
            
            // For demo purposes, we'll just show an alert
            // In production, this would trigger a server-side script to create and serve the ZIP
            document.getElementById('modalConfirmBtn').onclick = function() {
                alert('In a production environment, this would download a ZIP archive of all backups.');
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
        
        // Backup status check
        function checkBackupStatus() {
            const backupFiles = <?php echo json_encode($backupFiles); ?>;
            
            if(backupFiles.length === 0) {
                // No backups - show warning
                document.querySelector('.message.warning').innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Warning:</strong> No database backups found! Create your first backup now to protect your data.
                    </div>
                `;
            } else {
                // Check if backup is recent (within 7 days)
                const latestBackup = backupFiles[0];
                const oneWeekAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
                
                if(latestBackup.modified * 1000 < oneWeekAgo) {
                    document.querySelector('.message.warning').innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Notice:</strong> Your last backup was on ${new Date(latestBackup.modified * 1000).toLocaleDateString()}. 
                            Consider creating a new backup.
                        </div>
                    `;
                }
            }
        }
        
        // Initialize backup status check
        document.addEventListener('DOMContentLoaded', checkBackupStatus);
        
        // Auto-refresh backup list every 30 seconds
        setInterval(() => {
            // In a real app, this would fetch updated backup list via AJAX
            // For now, we'll just show a subtle indicator
            const backupCount = <?php echo count($backupFiles); ?>;
            const countElement = document.querySelector('.info-value:last-child');
            if(countElement && parseInt(countElement.textContent) !== backupCount) {
                countElement.style.color = '#667eea';
                countElement.style.transition = 'color 0.3s';
                setTimeout(() => {
                    countElement.style.color = '#2d3748';
                }, 1000);
            }
        }, 30000);
    </script>
</body>
</html>