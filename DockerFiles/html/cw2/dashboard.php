<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$message = "";
$officer_info = [
    'credentials' => '',
    'fname' => '',
    'lname' => ''
];
$recent_incidents = [];
$stats = [
    'total_incidents' => 0,
    'total_fines' => 0,
    'total_points' => 0,
    'pending_fines' => 0
];

require_once "components/cw2db.php";

try {
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    
    if (!$conn) {
        throw new Exception(mysqli_connect_error());
    }
    
    // Fetch officer details if officer_id exists
    if (isset($_SESSION['officer_id'])) {
        $stmt = mysqli_prepare($conn, "SELECT Officer_Credentials, Officer_Fname, Officer_Lname FROM Officer WHERE Officer_ID = ?");
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['officer_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $officer_info = [
                'credentials' => $row['Officer_Credentials'],
                'fname' => $row['Officer_Fname'],
                'lname' => $row['Officer_Lname']
            ];
        }
        mysqli_stmt_close($stmt);
    }

    // Fetch recent incidents
    $incidents_stmt = mysqli_prepare($conn, 
        "SELECT i.Incident_Date, i.Incident_Report, v.Vehicle_plate, p.People_name, o.Offence_description, f.Fine_Amount
         FROM Incident i
         LEFT JOIN Vehicle v ON i.Vehicle_ID = v.Vehicle_ID
         LEFT JOIN People p ON i.People_ID = p.People_ID
         LEFT JOIN Offence o ON i.Offence_ID = o.Offence_ID
         LEFT JOIN Fines f ON i.Incident_ID = f.Incident_ID
         ORDER BY i.Incident_Date DESC LIMIT 5");
    
    mysqli_stmt_execute($incidents_stmt);
    $incidents_result = mysqli_stmt_get_result($incidents_stmt);
    
    while ($row = mysqli_fetch_assoc($incidents_result)) {
        $recent_incidents[] = $row; 
    }
    mysqli_stmt_close($incidents_stmt);

    // Total incidents
    $stats_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM Incident");
    mysqli_stmt_execute($stats_stmt);
    $stats['total_incidents'] = mysqli_stmt_get_result($stats_stmt)->fetch_assoc()['count'];
    mysqli_stmt_close($stats_stmt);

    // Total fines amount
    $fines_stmt = mysqli_prepare($conn, "SELECT SUM(Fine_Amount) as total FROM Fines");
    mysqli_stmt_execute($fines_stmt);
    $stats['total_fines'] = mysqli_stmt_get_result($fines_stmt)->fetch_assoc()['total'] ?? 0;
    mysqli_stmt_close($fines_stmt);

    // Total points issued
    $points_stmt = mysqli_prepare($conn, "SELECT SUM(Fine_Points) as total FROM Fines");
    mysqli_stmt_execute($points_stmt);
    $stats['total_points'] = mysqli_stmt_get_result($points_stmt)->fetch_assoc()['total'] ?? 0;
    mysqli_stmt_close($points_stmt);

    // Incidents without fines (pending)
    $pending_stmt = mysqli_prepare($conn, 
        "SELECT COUNT(*) as count 
         FROM Incident i 
         LEFT JOIN Fines f ON i.Incident_ID = f.Incident_ID 
         WHERE f.Fine_ID IS NULL");
    mysqli_stmt_execute($pending_stmt);
    $stats['pending_fines'] = mysqli_stmt_get_result($pending_stmt)->fetch_assoc()['count'];
    mysqli_stmt_close($pending_stmt);

} catch (Exception $e) {
    $message = "Error: Unable to fetch system information";
    error_log($e->getMessage());
} finally {
    if (isset($conn)) mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="British Traffic Department Dashboard">
    <title>BTD - Dashboard</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <?php if (isset($_SESSION['officer_id']) && empty($message)): ?>
                <p class="officer-info">
                    <?php echo htmlspecialchars("{$officer_info['credentials']} {$officer_info['fname']} {$officer_info['lname']} on duty!"); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="container incidents-section">
            <h2>Recent Incidents</h2>
            <div class="incidents-grid">
                <?php if (empty($recent_incidents)): ?>
                    <p>No recent incidents to display.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Vehicle</th>
                                    <th>Person</th>
                                    <th>Offence</th>
                                    <th>Fine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_incidents as $incident): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($incident['Incident_Date']) ?></td>
                                        <td><?= htmlspecialchars($incident['Vehicle_plate'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($incident['People_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($incident['Offence_description'] ?? 'N/A') ?></td>
                                        <td><?= $incident['Fine_Amount'] ? '£' . htmlspecialchars($incident['Fine_Amount']) : 'Pending' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="container stats-section">
            <h2>Quick Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24">
                        <path d="M200-160v-80h64l79-263q8-26 29.5-41.5T420-560h120q26 0 47.5 15.5T617-503l79 263h64v80H200Zm148-80h264l-72-240H420l-72 240Zm92-400v-200h80v200h-80Zm238 99-57-57 142-141 56 56-141 142Zm42 181v-80h200v80H720ZM282-541 141-683l56-56 142 141-57 57ZM40-360v-80h200v80H40Zm440 120Z"/>
                    </svg>
                    <span class="stat-value"><?= number_format($stats['total_incidents']) ?></span>
                    <span class="stat-label">Total Incidents</span>
                </div>
                <div class="stat-card">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24">
                        <path d="M560-440q-50 0-85-35t-35-85q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35ZM280-320q-33 0-56.5-23.5T200-400v-320q0-33 23.5-56.5T280-800h560q33 0 56.5 23.5T920-720v320q0 33-23.5 56.5T840-320H280Zm80-80h400q0-33 23.5-56.5T840-480v-160q-33 0-56.5-23.5T760-720H280q0 33-23.5 56.5T200-640v160q33 0 56.5 23.5T280-400h80Zm440 240H40q-17 0-28.5-11.5T0-200q0-17 11.5-28.5T40-240h760q17 0 28.5 11.5T840-200q0 17-11.5 28.5T800-160Z"/>
                    </svg>
                    <span class="stat-value">£<?= number_format($stats['total_fines'], 2) ?></span>
                    <span class="stat-label">Total Fines</span>
                </div>
                <div class="stat-card">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24">
                        <path d="M480-80q-85 0-158-30.5T195-195q-54-54-84.5-127T80-480q0-84 30.5-157T195-764q54-54 127-85t158-31q75 0 140 24t117 66l-43 43q-44-35-98-54t-116-19q-145 0-242.5 97.5T140-480q0 145 97.5 242.5T480-140q145 0 242.5-97.5T820-480q0-30-4.5-58.5T802-594l46-46q16 37 24 77t8 83q0 85-31 158t-85 127q-54 54-127 84.5T480-80Zm-40-120v-120H320v-80h120v-120h80v120h120v80H520v120h-80ZM424-600v-280h80v280h-80Zm40 20q-17 0-28.5-11.5T424-620q0-17 11.5-28.5T464-660q17 0 28.5 11.5T504-620q0 17-11.5 28.5T464-580Z"/>
                    </svg>
                    <span class="stat-value"><?= number_format($stats['total_points']) ?></span>
                    <span class="stat-label">Points Issued</span>
                </div>
                <div class="stat-card">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24">
                        <path d="M680-320q-50 0-85-35t-35-85q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35ZM440-160v-123q0-21 10-39.5t28-29.5q35-21 73.5-33.5T640-400q38 0 76.5 12t74.5 34q18 11 28 29.5t10 39.5v125H440Zm-320 0v-80q0-17 8.5-32t23.5-25q57-35 107.5-45t120.5-10h26q-4 14-6.5 33t-2.5 39v120H120Zm160-280q-50 0-85-35t-35-85q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35Z"/>
                    </svg>
                    <span class="stat-value"><?= number_format($stats['pending_fines']) ?></span>
                    <span class="stat-label">Pending Fines</span>
                </div>
            </div>
        </div>
    </main>
</body>
</html>