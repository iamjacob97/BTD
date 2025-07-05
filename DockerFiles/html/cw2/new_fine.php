<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Redirect if not admin
if ($_SESSION['role'] !== "admin") {
    header("Location: dashboard.php");
    exit();
}

require_once "components/cw2db.php";
require_once "components/audit_functions.php";  

// Initialize variables
$form_data = $_SESSION['fine_form_data'] ?? [];
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['fine_form_data'], $_SESSION['message'], $_SESSION['message_type']);

try {
    // Establish database connection
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        throw new Exception(mysqli_connect_error());
    }

    // Fetch all incidents for dropdown
    $incidents_query = "SELECT i.Incident_ID, i.Offence_ID, 
                              o.Offence_maxFine, o.Offence_maxPoints, o.Offence_description,
                              v.Vehicle_plate, p.People_licence
                       FROM Incident i 
                       LEFT JOIN Offence o ON i.Offence_ID = o.Offence_ID
                       LEFT JOIN Vehicle v ON i.Vehicle_ID = v.Vehicle_ID
                       LEFT JOIN People p ON i.People_ID = p.People_ID
                       WHERE NOT EXISTS (SELECT 1 FROM Fines f WHERE f.Incident_ID = i.Incident_ID)";
    $incidents_result = $conn->query($incidents_query);

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $incident_id = $_POST['Incident_ID'];
        $fine_amount = floatval($_POST['Fine_Amount']);
        $fine_points = intval($_POST['Fine_Points']);

        // Fetch incident and offence details
        $check_stmt = $conn->prepare("
            SELECT i.Offence_ID, o.Offence_maxFine, o.Offence_maxPoints, v.Vehicle_plate, p.People_licence, o.Offence_description
            FROM Incident i
            LEFT JOIN Offence o ON i.Offence_ID = o.Offence_ID
            LEFT JOIN Vehicle v ON i.Vehicle_ID = v.Vehicle_ID
            LEFT JOIN People p ON i.People_ID = p.People_ID
            WHERE i.Incident_ID = ?
        ");
        $check_stmt->bind_param("i", $incident_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $incident_data = $result->fetch_assoc();
        $check_stmt->close();

        // Validate against offence maximums if offence exists
        if ($incident_data['Offence_ID']) {
            if ($fine_amount > $incident_data['Offence_maxFine']) {
                throw new Exception("Fine amount cannot exceed maximum fine (£{$incident_data['Offence_maxFine']}) for this offence!");
            }
            if ($fine_points > $incident_data['Offence_maxPoints']) {
                throw new Exception("Points cannot exceed maximum points ({$incident_data['Offence_maxPoints']}) for this offence!");
            }
        }

        // Global validation
        if ($fine_points > 12) {
            throw new Exception("Points cannot exceed 12!");
        }

        // Insert fine
        $stmt = $conn->prepare("INSERT INTO Fines (Fine_Amount, Fine_Points, Incident_ID) VALUES (?, ?, ?)");
        $stmt->bind_param("dii", $fine_amount, $fine_points, $incident_id);

        if ($stmt->execute()) {
            $fine_id = $stmt->insert_id;
            
            // Log the fine creation
            auditLog($conn, 'CREATE', 'Fines', $fine_id, null,
                [
                    'Fine_Amount' => $fine_amount,
                    'Fine_Points' => $fine_points,
                    'Incident_ID' => $incident_id,
                    'related_info' => [
                        'vehicle_plate' => $incident_data['Vehicle_plate'],
                        'person_licence' => $incident_data['People_licence'],
                        'offence_description' => $incident_data['Offence_description']
                    ],
                    'created_by' => $_SESSION['username'],
                    'creation_type' => 'admin_creation'
                ]
            );

            $_SESSION['message'] = "Fine successfully created!";
            $_SESSION['message_type'] = 'success';
        } else {
            throw new Exception("Error creating fine!");
        }

        $stmt->close();
        
        header("Location: new_fine.php");
        exit();
    }
} catch (Exception $e) {
    $_SESSION['message'] = $e->getMessage();
    $_SESSION['message_type'] = 'error';
    $_SESSION['fine_form_data'] = $_POST ?? [];
    error_log($e->getMessage());
    
    if (!isset($_POST['Incident_ID'])) {
        $message = "An error occurred while preparing the form";
        $message_type = 'error';
    } else {
        header("Location: new_fine.php");
        exit();
    }
} finally {
    if (isset($conn) && !isset($incidents_result)) {
        mysqli_close($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create new fine - British Traffic Department">
    <title>BTD - Create Fine</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/new_fine.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Create New Fine</h1></center>
            
            <form method="POST" id="fineForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <label for="Incident_ID">Incident *</label>
                <select id="Incident_ID" name="Incident_ID" required onchange="updateMaximums()">
                    <option value="">Select Incident</option>
                    <?php
                    if ($incidents_result) {
                        while ($incident = $incidents_result->fetch_assoc()) {
                            $selected = ($form_data['Incident_ID'] ?? '') == $incident['Incident_ID'] ? 'selected' : '';
                            $description = $incident['Offence_description'] 
                                ? " - " . htmlspecialchars($incident['Offence_description'])
                                : " (No offence associated)";
                            echo "<option value='" . $incident['Incident_ID'] . "' 
                                  data-max-fine='" . ($incident['Offence_maxFine'] ?? '') . "'
                                  data-max-points='" . ($incident['Offence_maxPoints'] ?? '') . "'
                                  $selected>ID: " . 
                                  htmlspecialchars($incident['Incident_ID']) . $description . 
                                  "</option>";
                        }
                        mysqli_close($conn);
                    }
                    ?>
                </select>

                <label for="Fine_Amount">Fine Amount (£) *</label>
                <input type="number" id="Fine_Amount" name="Fine_Amount" step="0.01" min="0" required
                       value="<?= htmlspecialchars($form_data['Fine_Amount'] ?? '') ?>">
                <div id="max_fine_info" class="info-text"></div>

                <label for="Fine_Points">Points *</label>
                <input type="number" id="Fine_Points" name="Fine_Points" min="0" max="12" required
                       value="<?= htmlspecialchars($form_data['Fine_Points'] ?? '') ?>">
                <div id="max_points_info" class="info-text"></div>

                <button type="submit">Create Fine</button>
            </form>

            <p class="note-text">Fines will be automatically deleted if the related incident is removed.</p>

            <?php if ($message): ?>
                <div class="message <?= $message_type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    function updateMaximums() {
        const selectedIncident = document.getElementById('Incident_ID').selectedOptions[0];
        const maxFine = selectedIncident.dataset.maxFine;
        const maxPoints = selectedIncident.dataset.maxPoints;
        const fineInput = document.getElementById('Fine_Amount');
        const pointsInput = document.getElementById('Fine_Points');
        const maxFineInfo = document.getElementById('max_fine_info');
        const maxPointsInfo = document.getElementById('max_points_info');

        if (maxFine) {
            maxFineInfo.textContent = `Maximum fine allowed: £${maxFine}`;
            fineInput.max = maxFine;
        } else {
            maxFineInfo.textContent = 'No maximum fine restriction for this incident';
            fineInput.removeAttribute('max');
        }

        if (maxPoints) {
            maxPointsInfo.textContent = `Maximum points allowed: ${maxPoints}`;
            pointsInput.max = Math.min(12, maxPoints);
        } else {
            maxPointsInfo.textContent = 'Maximum 12 points allowed';
            pointsInput.max = 12;
        }
    }

    document.getElementById('fineForm').onsubmit = function(e) {
        const selectedIncident = document.getElementById('Incident_ID').selectedOptions[0];
        const maxFine = selectedIncident.dataset.maxFine;
        const maxPoints = selectedIncident.dataset.maxPoints;
        const fineAmount = parseFloat(document.getElementById('Fine_Amount').value);
        const points = parseInt(document.getElementById('Fine_Points').value);

        const fineStr = fineAmount.toString();
        const pointsStr = points.toString();

        for (let i = 0; i < fineStr.length; i++) {
            if (isNaN(fineStr[i]) && fineStr[i] !== '.') {
                alert('Invalid fine amount!');
                e.preventDefault();
                return false;
            }
        }

        for (let i = 0; i < pointsStr.length; i++) {
            if (isNaN(pointsStr[i])) {
                alert('Invalid points value!');
                e.preventDefault();
                return false;
            }
        }

        if (maxFine && fineAmount > parseFloat(maxFine)) {
            alert(`Fine amount cannot exceed £${maxFine} for this offence!`);
            e.preventDefault();
            return false;
        }

        if (maxPoints && points > parseInt(maxPoints)) {
            alert(`Points cannot exceed ${maxPoints} for this offence!`);
            e.preventDefault();
            return false;
        }

        if (points > 12) {
            alert('Points cannot exceed 12!');
            e.preventDefault();
            return false;
        }

        if (fineAmount < 0) {
            alert('Fine amount cannot be negative!');
            e.preventDefault();
            return false;
        }

        if (points < 0) {
            alert('Points cannot be negative!');
            e.preventDefault();
            return false;
        }

        return true;
    };

    document.addEventListener('DOMContentLoaded', updateMaximums);
    </script>
</body>
</html>