<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require_once "components/cw2db.php";
require_once "components/audit_functions.php";

$search_results = [];
$message = '';

if (isset($_GET['search_input'])) {
    try {
        $conn = mysqli_connect($servername, $username, $password, $dbname);
        if (!$conn) {
            throw new Exception(mysqli_connect_error());
        }

        $searchInput = trim($_GET['search_input']);
        
        $stmt = mysqli_prepare($conn, 
            "SELECT v.Vehicle_ID, v.Vehicle_plate, v.Vehicle_make, v.Vehicle_model, v.Vehicle_colour, p.People_name AS Owner_Name, p.People_licence, i.Incident_ID, i.Incident_Report, i.Incident_Date, ip.People_name AS Incident_Person_Name
            FROM Vehicle AS v
            LEFT JOIN Ownership AS o ON v.Vehicle_ID = o.Vehicle_ID
            LEFT JOIN People AS p ON o.People_ID = p.People_ID
            LEFT JOIN Incident AS i ON v.Vehicle_ID = i.Vehicle_ID
            LEFT JOIN People AS ip ON i.People_ID = ip.People_ID
            WHERE v.Vehicle_plate LIKE CONCAT('%', ?, '%')");
        
        mysqli_stmt_bind_param($stmt, "s", $searchInput);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Handle NULL values
            foreach ($row as $key => $value) {
                if (is_null($value) || $value === "") {
                    if (in_array($key, ["Incident_Report", "Incident_Date", "Incident_Person_Name"])) {
                        $row[$key] = "No report";
                    } else {
                        $row[$key] = "Unknown";
                    }
                }
            }
            $search_results[] = $row;

            auditLog($conn, 'READ', 'Vehicle', $row['Vehicle_ID'], null,
                [
                    'search_term' => $searchInput,
                    'matched_fields' => [
                        'plate' => $row['Vehicle_plate'],
                        'owner' => $row['Owner_Name'],
                        'incident' => $row['Incident_Report'] !== 'No report' ? 'Yes' : 'No'
                    ]
                ]
            );
        }

        if (empty($search_results)) {
            $message = "No results found for '" . htmlspecialchars($searchInput) . "'";
            // Log unsuccessful search
            auditLog($conn, 'READ', 'Vehicle', null, null,
                [
                    'search_term' => $searchInput,
                    'result' => 'No matches found'
                ]
            );
        }

        mysqli_stmt_close($stmt);
        mysqli_close($conn);
    } catch (Exception $e) {
        $message = "An error occurred while searching";
        error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Search - BTD</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/vehicle.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Vehicle Search</h1></center>
            
            <form method="get" class="search-form">
                <div class="search-group">
                    <input type="text" name="search_input" value="<?php echo htmlspecialchars($_GET['search_input'] ?? ''); ?>"
                           placeholder="Enter plate number" maxlength=6 required autofocus>
                    <button type="submit" class="btn-search">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="currentColor">
                            <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/>
                        </svg>
                        Search
                    </button>
                </div>
            </form>

            <?php if ($message): ?>
                <p class="message"><?php echo $message; ?></p>
            <?php endif; ?>

            <?php if (!empty($search_results)): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Plate Number</th>
                                <th>Make</th>
                                <th>Model</th>
                                <th>Colour</th>
                                <th>Owner</th>
                                <th>Owner License</th>
                                <th>Incident Report</th>
                                <th>Incident Date</th>
                                <th>Incident Person</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($search_results as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["Vehicle_plate"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Vehicle_make"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Vehicle_model"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Vehicle_colour"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Owner_Name"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["People_licence"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Incident_Report"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Incident_Date"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Incident_Person_Name"]); ?></td>
                                    <td>
                                        <a href="edit_vehicle.php?id=<?php echo $row['Vehicle_ID']; ?>" 
                                           class="btn-edit" 
                                           title="Edit Vehicle Details">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor">
                                                <path d="M180-180h44l443-443-44-44-443 443v44Zm614-486L666-794l42-42q17-17 42-17t42 17l44 44q17 17 17 42t-17 42l-42 42Zm-42 42L248-120H120v-128l504-504 128 128Zm-107-21-22-22 44 44-22-22Z"/>
                                            </svg>
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('[name="search_input"]').addEventListener('input', function(e) {
                this.value = this.value.toUpperCase();
            });
        });
    </script>
</body>
</html>


