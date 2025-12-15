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

// Get members with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1; // Ensure page is at least 1

$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$membershipPlan = isset($_GET['plan']) ? $_GET['plan'] : '';

$whereClauses = ["u.user_type = 'member'"];
$params = [];

if($search) {
    $whereClauses[] = "(u.full_name LIKE ? OR u.email LIKE ? OR gm.MembershipPlan LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if($membershipPlan) {
    $whereClauses[] = "gm.MembershipPlan = ?";
    $params[] = $membershipPlan;
}

$whereSQL = !empty($whereClauses) ? implode(' AND ', $whereClauses) : '1=1';

try {
    // Count total members
    $countSQL = "SELECT COUNT(*) FROM users u LEFT JOIN gym_members gm ON u.email = gm.Email WHERE $whereSQL";
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute($params);
    $totalMembers = $countStmt->fetchColumn();

    // Calculate total pages
    $totalPages = ceil($totalMembers / $limit);
    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages; // Adjust page if out of bounds
        $offset = ($page - 1) * $limit;
    }

    // Get members
    $membersSql = "
        SELECT 
            u.*, 
            gm.*,
            (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.status = 'completed') as total_payments,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.user_id = u.id AND p.status = 'completed') as total_spent
        FROM users u 
        LEFT JOIN gym_members gm ON u.email = gm.Email 
        WHERE $whereSQL 
        ORDER BY u.created_at DESC 
        LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($membersSql);
    $stmt->execute($params);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique membership plans
    $plans = $pdo->query("SELECT DISTINCT MembershipPlan FROM gym_members WHERE MembershipPlan IS NOT NULL AND MembershipPlan != ''")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $members = [];
    $totalMembers = 0;
    $totalPages = 0;
    $plans = [];
    error_log("Members query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members | Admin Dashboard</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Additional styles for members management */
        .members-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .members-table th {
            background: #f8f9fa;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }
        
        .members-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .members-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Filters */
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 200px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2f3542;
            font-size: 0.9rem;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #ff4757;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        /* Member status badges */
        .member-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
        }
        
        .status-inactive {
            background: rgba(255, 71, 87, 0.2);
            color: #ff4757;
        }
        
        .status-pending {
            background: rgba(255, 165, 2, 0.2);
            color: #ffa502;
        }
        
        .status-suspended {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }
        
        /* Button styles */
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #e9ecef;
            color: #495057;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-sm:hover {
            background: #dee2e6;
            text-decoration: none;
        }
        
        .btn-sm.btn-danger {
            background: #ff4757;
            color: white;
        }
        
        .btn-sm.btn-danger:hover {
            background: #ff2e43;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0.5rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #495057;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
            cursor: pointer;
        }
        
        .page-link:hover {
            background: #e9ecef;
            text-decoration: none;
        }
        
        .page-link.active {
            background: #ff4757;
            color: white;
            border-color: #ff4757;
        }
        
        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .page-info {
            margin: 0 1rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Responsive table */
        @media (max-width: 768px) {
            .members-table {
                min-width: 800px;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                min-width: auto;
            }
            
            .pagination {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .page-info {
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search members..." value="<?php echo htmlspecialchars($search); ?>" id="searchInput">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="window.location.href='admin-add-member.php'">
                    <i class="fas fa-plus"></i> Add Member
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Manage Members</h1>
                    <p>View, edit, and manage all gym members</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $totalMembers; ?></h3>
                        <p>Total Members</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $totalPages; ?></h3>
                        <p>Total Pages</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $page; ?></h3>
                        <p>Current Page</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="filters">
                <input type="hidden" name="page" value="1"> <!-- Always reset to page 1 when filtering -->
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Name, email, or plan" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label>Membership Plan</label>
                    <select name="plan">
                        <option value="">All Plans</option>
                        <?php foreach($plans as $plan): ?>
                            <option value="<?php echo htmlspecialchars($plan['MembershipPlan']); ?>" <?php echo $membershipPlan == $plan['MembershipPlan'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($plan['MembershipPlan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Apply Filters</button>
                <button type="button" class="btn-secondary" onclick="window.location.href='admin-members.php'">Clear</button>
            </form>

            <!-- Members Table -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Members List</h3>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <span class="page-info">
                            Showing <?php echo ($totalMembers > 0) ? ($offset + 1) : 0; ?> - <?php echo min($offset + $limit, $totalMembers); ?> of <?php echo $totalMembers; ?> members
                        </span>
                        <a href="admin-export-members.php" class="btn-secondary btn-sm">
                            <i class="fas fa-download"></i> Export CSV
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="members-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Membership Plan</th>
                                    <th>Join Date</th>
                                    <th>Total Spent</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($members) > 0): ?>
                                    <?php foreach($members as $member): ?>
                                        <tr>
                                            <td>#<?php echo $member['id']; ?></td>
                                            <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                                            <td><?php echo htmlspecialchars($member['MembershipPlan'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($member['JoinDate'] ?? $member['created_at'])); ?></td>
                                            <td>$<?php echo number_format($member['total_spent'] ?? 0, 2); ?></td>
                                            <td>
                                                <span class="member-status status-<?php echo strtolower($member['status'] ?? 'active'); ?>">
                                                    <?php echo ucfirst($member['status'] ?? 'active'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <a href="admin-member-view.php?id=<?php echo $member['id']; ?>" class="btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="admin-edit-member.php?id=<?php echo $member['id']; ?>" class="btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button class="btn-sm btn-danger" onclick="confirmDelete(<?php echo $member['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 2rem;">
                                            <p style="color: #6c757d; font-style: italic;">No members found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            // Previous button
                            $prevClass = ($page <= 1) ? 'disabled' : '';
                            $prevPage = ($page > 1) ? $page - 1 : 1;
                            ?>
                            <a href="?page=<?php echo $prevPage; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($membershipPlan); ?>" 
                               class="page-link <?php echo $prevClass; ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                            
                            <?php 
                            // Show page numbers with ellipsis
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            // Always show first page
                            if($startPage > 1): ?>
                                <a href="?page=1&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($membershipPlan); ?>" 
                                   class="page-link <?php echo (1 == $page) ? 'active' : ''; ?>">
                                    1
                                </a>
                                <?php if($startPage > 2): ?>
                                    <span class="page-link disabled">...</span>
                                <?php endif;
                            endif;
                            
                            // Show page range
                            for($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($membershipPlan); ?>" 
                                   class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor;
                            
                            // Always show last page
                            if($endPage < $totalPages): 
                                if($endPage < $totalPages - 1): ?>
                                    <span class="page-link disabled">...</span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($membershipPlan); ?>" 
                                   class="page-link <?php echo ($totalPages == $page) ? 'active' : ''; ?>">
                                    <?php echo $totalPages; ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            // Next button
                            $nextClass = ($page >= $totalPages) ? 'disabled' : '';
                            $nextPage = ($page < $totalPages) ? $page + 1 : $totalPages;
                            ?>
                            <a href="?page=<?php echo $nextPage; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($membershipPlan); ?>" 
                               class="page-link <?php echo $nextClass; ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(memberId) {
            if(confirm('Are you sure you want to delete this member?')) {
                window.location.href = 'admin-delete-member.php?id=' + memberId;
            }
        }

        // Live search
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if(e.key === 'Enter') {
                const search = this.value;
                window.location.href = 'admin-members.php?page=1&search=' + encodeURIComponent(search);
            }
        });

        // Auto-submit filter form when select changes
        document.querySelector('select[name="plan"]').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>