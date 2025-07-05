<?php
// config.php
define('RECORDS_PER_PAGE', 20);
define('VALID_TABLES', ['Vehicle', 'People', 'Incident', 'Offence', 'Fines', 'Ownership', 'User', 'Officer']);
define('VALID_ACTIONS', ['CREATE', 'READ', 'UPDATE', 'DELETE']);

// auth.php
session_start();

function checkAuth() {
    if (!isset($_SESSION['username'])) {
        header("Location: index.php");
        exit();
    }
    if ($_SESSION['role'] !== "admin") {
        header("Location: dashboard.php");
        exit();
    }
}

// database.php
function connectDB($servername, $username, $password, $dbname) {
    try {
        $conn = mysqli_connect($servername, $username, $password, $dbname);
        if (!$conn) {
            throw new Exception(mysqli_connect_error());
        }
        return $conn;
    } catch (Exception $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

function getTotalRecords($conn, $query, $types, $params) {
    $count_query = str_replace("SELECT *", "SELECT COUNT(*)", $query);
    $stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_row()[0];
}

function getAuditRecords($conn, $filters, $page) {
    $query = "SELECT * FROM Audit WHERE 1=1";
    $params = [];
    $types = "";
    
    // Build query based on filters
    if (!empty($filters['username'])) {
        $query .= " AND Username LIKE ?";
        $username = "%" . $filters['username'] . "%";
        $params[] = &$username;
        $types .= "s";
    }
    
    if (!empty($filters['table'])) {
        $query .= " AND Table_Name = ?";
        $params[] = &$filters['table'];
        $types .= "s";
    }
    
    if (!empty($filters['action'])) {
        $query .= " AND Action_Type = ?";
        $params[] = &$filters['action'];
        $types .= "s";
    }
    
    if (!empty($filters['date_from'])) {
        $query .= " AND Action_Date >= ?";
        $date_from = $filters['date_from'] . ' 00:00:00';
        $params[] = &$date_from;
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND Action_Date <= ?";
        $date_to = $filters['date_to'] . ' 23:59:59';
        $params[] = &$date_to;
        $types .= "s";
    }
    
    // Calculate pagination
    $offset = ($page - 1) * RECORDS_PER_PAGE;
    $total_records = getTotalRecords($conn, $query, $types, $params);
    $total_pages = ceil($total_records / RECORDS_PER_PAGE);
    
    // Add pagination to query
    $query .= " ORDER BY Action_Date DESC LIMIT ? OFFSET ?";
    $types .= "ii";
    $limit = RECORDS_PER_PAGE;
    $params[] = &$limit;
    $params[] = &$offset;
    
    // Execute query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    
    return [
        'result' => $stmt->get_result(),
        'total_pages' => $total_pages
    ];
}

// filters.php
function getFilter($name) {
    $value = isset($_GET[$name]) ? htmlspecialchars(trim($_GET[$name])) : '';
    
    // Validate dates to prevent future dates
    if ($name === 'date_from' || $name === 'date_to') {
        if ($value > date('Y-m-d')) {
            return date('Y-m-d');
        }
    }
    return $value;
}

function getAllFilters() {
    return [
        'username' => getFilter('username'),
        'table' => getFilter('table'),
        'action' => getFilter('action'),
        'date_from' => getFilter('date_from'),
        'date_to' => getFilter('date_to')
    ];
}

function getCurrentPage() {
    return filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1;
}

// audit.php
require_once "components/cw2db.php";
checkAuth();

try {
    $filters = getAllFilters();
    $page = getCurrentPage();
    $conn = connectDB($servername, $username, $password, $dbname);
    $auditData = getAuditRecords($conn, $filters, $page);
    $result = $auditData['result'];
    $total_pages = $auditData['total_pages'];
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!-- HTML Template -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Audit Trail - British Traffic Department">
    <title>Audit Trail - BTD</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/audit.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Audit Trail</h1></center>

            <!-- Filters Form -->
            <form method="get" class="filters-form">
                <div class="filters-grid">
                    <?php 
                    $filterFields = [
                        ['name' => 'username', 'type' => 'text', 'label' => 'Username', 'placeholder' => 'Filter by username'],
                        ['name' => 'table', 'type' => 'select', 'label' => 'Table', 'options' => VALID_TABLES, 'default' => 'All Tables'],
                        ['name' => 'action', 'type' => 'select', 'label' => 'Action', 'options' => VALID_ACTIONS, 'default' => 'All Actions'],
                        ['name' => 'date_from', 'type' => 'date', 'label' => 'From Date', 'max' => date('Y-m-d')],
                        ['name' => 'date_to', 'type' => 'date', 'label' => 'To Date', 'max' => date('Y-m-d')]
                    ];
                    
                    foreach ($filterFields as $field): ?>
                        <div class="form-group">
                            <label><?= $field['label'] ?></label>
                            <?php if ($field['type'] === 'select'): ?>
                                <select name="<?= $field['name'] ?>">
                                    <option value=""><?= $field['default'] ?></option>
                                    <?php foreach ($field['options'] as $option): ?>
                                        <option value="<?= $option ?>" 
                                            <?= $filters[$field['name']] === $option ? 'selected' : '' ?>>
                                            <?= $option ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="<?= $field['type'] ?>" name="<?= $field['name'] ?>" 
                                       value="<?= $filters[$field['name']] ?>"
                                       <?= isset($field['placeholder']) ? "placeholder=\"{$field['placeholder']}\"" : '' ?>
                                       <?= isset($field['max']) ? "max=\"{$field['max']}\"" : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="form-group">
                        <button type="submit" class="btn-filter">Apply Filters</button>
                        <a href="audit.php" class="btn-clear">Clear Filters</a>
                    </div>
                </div>
            </form>

            <!-- Results Table -->
            <?php if (isset($result) && $result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Record ID</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['Action_Date']) ?></td>
                                    <td><?= htmlspecialchars($row['Username']) ?></td>
                                    <td class="action-cell <?= strtolower($row['Action_Type']) ?>">
                                        <?= htmlspecialchars($row['Action_Type']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['Table_Name']) ?></td>
                                    <td><?= htmlspecialchars($row['Record_ID'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($row['New_Values'] || ($row['Action_Type'] === 'DELETE' && $row['Old_Values'])): ?>
                                            <button onclick="showDetails(<?= htmlspecialchars(json_encode([
                                                'action' => $row['Action_Type'],
                                                'old' => json_decode($row['Old_Values'] ?? '{}', true),
                                                'new' => json_decode($row['New_Values'] ?? '{}', true)
                                            ])) ?>)" class="btn-details">
                                                <?= $row['Action_Type'] === 'DELETE' ? 'View Deleted Record' : 
                                                   ($row['Action_Type'] === 'READ' ? 'View Details' : 'View Changes') ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['IP_Address']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        function generatePageUrl($pageNum, $filters) {
                            $url = "?page=" . $pageNum;
                            foreach ($filters as $key => $value) {
                                if (!empty($value)) {
                                    $url .= "&{$key}=" . urlencode($value);
                                }
                            }
                            return $url;
                        }

                        // Show first page
                        if ($page > 1): ?>
                            <a href="<?= generatePageUrl(1, $filters) ?>" class="page-link">First</a>
                        <?php endif; ?>

                        <!-- Previous page -->
                        <?php if ($page > 1): ?>
                            <a href="<?= generatePageUrl($page - 1, $filters) ?>" class="page-link">Previous</a>
                        <?php endif; ?>

                        <!-- Page numbers -->
                        <?php
                        $range = 2; // Number of pages to show before and after current page
                        for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++): ?>
                            <a href="<?= generatePageUrl($i, $filters) ?>" 
                               class="page-link <?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Next page -->
                        <?php if ($page < $total_pages): ?>
                            <a href="<?= generatePageUrl($page + 1, $filters) ?>" class="page-link">Next</a>
                        <?php endif; ?>

                        <!-- Last page -->
                        <?php if ($page < $total_pages): ?>
                            <a href="<?= generatePageUrl($total_pages, $filters) ?>" class="page-link">Last</a>
                        <?php endif; ?>

                        <!-- Page info -->
                        <span class="page-info">
                            Page <?= $page ?> of <?= $total_pages ?>
                        </span>
                    </div>
                <?php endif; ?>

            <?php elseif (isset($result)): ?>
                <p class="no-results">No audit records found matching the specified criteria.</p>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal for showing details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Change Details</h2>
            <div class="changes-grid">
                <div class="old-values">
                    <h3>Previous Values</h3>
                    <pre id="oldValues"></pre>
                </div>
                <div class="new-values">
                    <h3>Current Values</h3>
                    <pre id="newValues"></pre>
                </div>
            </div>
        </div>
    </div>

    <script>
    const modal = document.getElementById('detailsModal');
    const closeBtn = document.querySelector('.close');

    function showDetails(data) {
        const oldValuesContainer = document.querySelector('.old-values');
        const newValuesContainer = document.querySelector('.new-values');
        const oldValuesElem = document.getElementById('oldValues');
        const newValuesElem = document.getElementById('newValues');
        const modalTitle = document.querySelector('.modal-content h2');
        const changesGrid = document.querySelector('.changes-grid');
        
        function formatJson(obj) {
            if (!obj || Object.keys(obj).length === 0) return 'No data';
            return JSON.stringify(obj, null, 2);
        }

        // Reset layout
        changesGrid.style.gridTemplateColumns = '1fr 1fr';
        oldValuesContainer.style.display = 'block';
        newValuesContainer.style.display = 'block';

        const actionConfigs = {
            'READ': { title: 'Viewed Information', showOld: false, showNew: true },
            'CREATE': { title: 'Created Record', showOld: false, showNew: true },
            'UPDATE': { title: 'Changed Values', showOld: true, showNew: true },
            'DELETE': { title: 'Deleted Record Details', showOld: true, showNew: false }
        };

        const config = actionConfigs[data.action];
        modalTitle.textContent = config.title;
        oldValuesContainer.style.display = config.showOld ? 'block' : 'none';
        newValuesContainer.style.display = config.showNew ? 'block' : 'none';

        if (data.action === 'DELETE') {
            changesGrid.style.gridTemplateColumns = '1fr';
            oldValuesContainer.querySelector('h3').textContent = 'Deleted Record Information';
        }

        oldValuesElem.textContent = formatJson(data.old);
        newValuesElem.textContent = formatJson(data.new);
        modal.style.display = 'block';
    }

    // Event Listeners
    closeBtn.onclick = () => modal.style.display = 'none';
    window.onclick = (event) => {
        if (event.target === modal) modal.style.display = 'none';
    };
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.style.display === 'block') {
            modal.style.display = 'none';
        }
    });
    </script>
</body>
</html>