<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require_once "components/cw2db.php";
require_once "components/audit_functions.php";

$search_results = [];
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

if (isset($_GET['search_input']) && !empty($_GET['search_input'])) {
    try {
        $conn = mysqli_connect($servername, $username, $password, $dbname);
        if (!$conn) {
            throw new Exception(mysqli_connect_error());
        }

        $searchInput = trim($_GET['search_input']);
        
        // Input validation
        if (!is_numeric($searchInput)) {
            throw new Exception("Please enter a valid incident ID number");
        }
        
        $stmt = mysqli_prepare($conn, "
            SELECT 
                i.Incident_ID,
                i.Incident_Report,
                i.Incident_Date,
                v.Vehicle_plate,
                p.People_name AS Incident_Person_Name,
                p.People_licence,
                o.Officer_Fname,
                o.Officer_Lname,
                of.Offence_description,
                f.Fine_Amount,
                f.Fine_Points
            FROM Incident AS i
            LEFT JOIN Vehicle AS v ON i.Vehicle_ID = v.Vehicle_ID
            LEFT JOIN People AS p ON i.People_ID = p.People_ID
            LEFT JOIN Officer AS o ON i.Officer_ID = o.Officer_ID
            LEFT JOIN Offence AS of ON i.Offence_ID = of.Offence_ID
            LEFT JOIN Fines AS f ON i.Incident_ID = f.Incident_ID
            WHERE i.Incident_ID = ? OR o.Officer_ID = ? ORDER BY i.Incident_Date DESC
        ");
        
        mysqli_stmt_bind_param($stmt, "ii", $searchInput, $searchInput);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Handle null values
            foreach ($row as $key => $value) {
                $row[$key] = $value ?? "Unknown";
            }
            $search_results[] = $row;
            
            // Log the successful record lookup
            auditLog($conn, 'READ', 'Incident', $row['Incident_ID'], null,
                [
                    'lookup_method' => 'id_search',
                    'incident_id' => $searchInput,
                    'found' => true,
                    'accessed_fields' => [
                        'incident_details' => [
                            'date' => $row['Incident_Date'],
                            'report' => substr($row['Incident_Report'], 0, 100) . '...' // Truncate long reports
                        ],
                        'vehicle_info' => [
                            'plate' => $row['Vehicle_plate']
                        ],
                        'person_info' => [
                            'name' => $row['Incident_Person_Name'],
                            'licence' => $row['People_licence']
                        ],
                        'officer_info' => [
                            'name' => $row['Officer_Fname'] . ' ' . $row['Officer_Lname']
                        ],
                        'offence_info' => [
                            'description' => $row['Offence_description']
                        ],
                        'fine_info' => [
                            'amount' => $row['Fine_Amount'],
                            'points' => $row['Fine_Points']
                        ]
                    ]
                ]
            );
        }

        if (empty($search_results)) {
            $message = "No incident found with ID: " . htmlspecialchars($searchInput);
            $message_type = 'error';
            
            // Log unsuccessful search
            auditLog($conn, 'READ', 'Incident', null, null,
                [
                    'lookup_method' => 'id_search',
                    'incident_id' => $searchInput,
                    'found' => false,
                    'result' => 'No matches found'
                ]
            );
        }

    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
        error_log($e->getMessage());
    } finally {
        if (isset($stmt)) mysqli_stmt_close($stmt);
        if (isset($conn)) mysqli_close($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="British Traffic Department Incident Search">
    <title>Incident Search - BTD</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/incident.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Incident Search</h1></center>
            
            <form method="get" class="search-form">
                <div class="search-group">
                    <input type="number" name="search_input" value="<?php echo htmlspecialchars($_GET['search_input'] ?? ''); ?>"
                           placeholder="Enter incident ID or Officer ID" required autofocus>
                    <button type="submit" class="btn-search">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="currentColor">
                            <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/>
                        </svg>
                        Search
                    </button>
                </div>
            </form>

            <?php if ($message): ?>
                <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <?php if (!empty($search_results)): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Vehicle</th>
                                <th>Person</th>
                                <th>License</th>
                                <th>Officer</th>
                                <th>Offence</th>
                                <th>Fine Amount</th>
                                <th>Points</th>
                                <th>Report</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($search_results as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["Incident_ID"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Incident_Date"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Vehicle_plate"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Incident_Person_Name"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["People_licence"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Officer_Fname"]) . " " . 
                                             htmlspecialchars($row["Officer_Lname"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Offence_description"]); ?></td>
                                    <td><?php echo $row["Fine_Amount"] !== "Unknown" ? "£" . 
                                             htmlspecialchars($row["Fine_Amount"]) : "Unknown"; ?></td>
                                    <td><?php echo htmlspecialchars($row["Fine_Points"]); ?></td>
                                    <td class="incident-report" title="<?php echo htmlspecialchars($row["Incident_Report"]); ?>">
                                        <?php echo htmlspecialchars($row["Incident_Report"]); ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="edit_incident.php?id=<?php echo $row['Incident_ID']; ?>" 
                                           class="btn-edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor">
                                                <path d="M180-180h44l443-443-44-44-443 443v44Zm614-486L666-794l42-42q17-17 42-17t42 17l44 44q17 17 17 42t-17 42l-42 42Zm-42 42L248-120H120v-128l504-504 128 128Zm-107-21-22-22 44 44-22-22Z"/>
                                            </svg>
                                            Edit
                                        </a>
                                        <form action="delete_incident.php" method="post" 
                                              onsubmit="return confirm('Are you sure you want to delete this incident? This action cannot be undone.');"
                                              style="display: inline;">
                                            <input type="hidden" name="incident_id" value="<?php echo $row['Incident_ID']; ?>">
                                            <button type="submit" class="btn-delete">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor">
                                                    <path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/>
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>