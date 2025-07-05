<?php
session_start();
require_once "components/cw2db.php";
require_once "components/audit_functions.php";

// ====== Authentication Functions ======
function checkAuthentication() {
    if (!isset($_SESSION['username'])) {
        header("Location: index.php");
        exit();
    }
}

// ====== Database Functions ======
function getDatabaseConnection() {
    global $servername, $username, $password, $dbname;
    try {
        $conn = mysqli_connect($servername, $username, $password, $dbname);
        if (!$conn) {
            throw new Exception(mysqli_connect_error());
        }
        return $conn;
    } catch (Exception $e) {
        error_log($e->getMessage());
        throw new Exception("Database connection failed");
    }
}

function getNextIncidentId($conn) {
    $result = $conn->query("SELECT COALESCE(MAX(Incident_ID), 0) + 1 AS next_incident_id FROM Incident");
    return $result->fetch_assoc()['next_incident_id'] ?? 1;
}

function getOffences($conn) {
    return $conn->query("SELECT Offence_ID, Offence_description FROM Offence ORDER BY Offence_description");
}

// ====== Message Handling Functions ======
function getStoredMessage() {
    if (isset($_SESSION['message'])) {
        $message = [
            'text' => $_SESSION['message'],
            'type' => $_SESSION['message_type']
        ];
        unset($_SESSION['message'], $_SESSION['message_type']);
        return $message;
    }
    return ['text' => '', 'type' => ''];
}

function setSuccessMessage($message = "Incident successfully recorded") {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = "success";
}

function setErrorMessage($message) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = "error";
}

// ====== Data Processing Functions ======
function processVehicleData($conn, $post_data) {
    if (empty($post_data['vehicle_plate'])) {
        return null;
    }

    $vehicle_stmt = $conn->prepare("SELECT Vehicle_ID FROM Vehicle WHERE Vehicle_plate = ?");
    $vehicle_stmt->bind_param("s", $post_data['vehicle_plate']);
    $vehicle_stmt->execute();
    $result = $vehicle_stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['Vehicle_ID'];
    }

    // Create new vehicle
    if (empty($post_data['vehicle_make']) || empty($post_data['vehicle_model']) || empty($post_data['vehicle_colour'])) {
        throw new Exception("All vehicle details are required for new vehicles");
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO Vehicle (Vehicle_plate, Vehicle_make, Vehicle_model, Vehicle_colour) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("ssss", 
        $post_data['vehicle_plate'],
        $post_data['vehicle_make'],
        $post_data['vehicle_model'],
        $post_data['vehicle_colour']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Error creating vehicle record");
    }

    $vehicle_id = $stmt->insert_id;
    logVehicleCreation($conn, $vehicle_id, $post_data);
    return $vehicle_id;
}

function processPersonData($conn, $post_data) {
    if (empty($post_data['licence_number'])) {
        return null;
    }

    $stmt = $conn->prepare("SELECT People_ID FROM People WHERE People_licence = ?");
    $stmt->bind_param("s", $post_data['licence_number']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['People_ID'];
    }

    // Create new person
    if (empty($post_data['person_name'])) {
        throw new Exception("Name is required for new people records");
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO People (People_name, People_licence, People_address) 
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("sss", 
        $post_data['person_name'],
        $post_data['licence_number'],
        $post_data['person_address']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Error creating person record");
    }

    $people_id = $stmt->insert_id;
    logPersonCreation($conn, $people_id, $post_data);
    return $people_id;
}

function processOffenceData($conn, $post_data) {
    if (empty($post_data['offence_description'])) {
        return null;
    }

    $stmt = $conn->prepare("SELECT Offence_ID FROM Offence WHERE Offence_description = ?");
    $stmt->bind_param("s", $post_data['offence_description']);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result->num_rows > 0) ? $result->fetch_assoc()['Offence_ID'] : null;
}

// ====== AJAX Handling Functions ======
function handleAjaxRequest($conn) {
    header('Content-Type: application/json');
    
    try {
        if ($_GET['action'] === 'fetch_vehicle') {
            handleVehicleLookup($conn);
        } elseif ($_GET['action'] === 'fetch_person') {
            handlePersonLookup($conn);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

function handleVehicleLookup($conn) {
    $plate = trim($_GET['plate']);
    $stmt = mysqli_prepare($conn, 
        "SELECT v.*, p.People_ID, p.People_licence, p.People_name 
         FROM Vehicle v 
         LEFT JOIN Ownership o ON v.Vehicle_ID = o.Vehicle_ID 
         LEFT JOIN People p ON o.People_ID = p.People_ID 
         WHERE v.Vehicle_plate = ?"
    );
    
    mysqli_stmt_bind_param($stmt, "s", $plate);
    mysqli_stmt_execute($stmt);
    $vehicle = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($vehicle) {
        logVehicleLookup($conn, $vehicle, $plate);
        echo json_encode([
            'exists' => true,
            'make' => $vehicle['Vehicle_make'],
            'model' => $vehicle['Vehicle_model'],
            'colour' => $vehicle['Vehicle_colour'],
            'currentOwner' => [
                'licence' => $vehicle['People_licence'],
                'name' => $vehicle['People_name']
            ]
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
}

function handlePersonLookup($conn) {
    $licence = trim($_GET['licence']);
    $stmt = mysqli_prepare($conn, 
        "SELECT People_ID, People_name, People_address FROM People WHERE People_licence = ?"
    );
    
    mysqli_stmt_bind_param($stmt, "s", $licence);
    mysqli_stmt_execute($stmt);
    $person = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($person) {
        logPersonLookup($conn, $person, $licence);
        echo json_encode([
            'exists' => true,
            'name' => $person['People_name'],
            'address' => $person['People_address']
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
}

// ====== Validation Functions ======
function validateFormData($post_data) {
    $errors = [];
    
    if (empty($post_data['incident_date'])) {
        $errors[] = "Incident date is required";
    } elseif (strtotime($post_data['incident_date']) > time()) {
        $errors[] = "Incident date cannot be in the future";
    }
    
    if (!empty($post_data['vehicle_plate']) && strlen($post_data['vehicle_plate']) !== 6) {
        $errors[] = "Vehicle plate must be exactly 6 characters";
    }
    
    if (!empty($post_data['licence_number']) && strlen($post_data['licence_number']) > 20) {
        $errors[] = "License number cannot exceed 20 characters";
    }
    
    if (empty($post_data['incident_report'])) {
        $errors[] = "Incident report is required";
    }
    
    return $errors;
}

// ====== Logging Functions ======
function logVehicleLookup($conn, $vehicle, $plate) {
    auditLog($conn, 'READ', 'Vehicle', $vehicle['Vehicle_ID'], null,
        [
            'lookup_method' => 'plate_search',
            'vehicle_plate' => $plate
        ]
    );
}

function logPersonLookup($conn, $person, $licence) {
    auditLog($conn, 'READ', 'People', $person['People_ID'], null,
        [
            'lookup_method' => 'licence_search',
            'licence_number' => $licence
        ]
    );
}

function logVehicleCreation($conn, $vehicle_id, $data) {
    auditLog($conn, 'CREATE', 'Vehicle', $vehicle_id, null,
        [
            'Vehicle_plate' => $data['vehicle_plate'],
            'Vehicle_make' => $data['vehicle_make'],
            'Vehicle_model' => $data['vehicle_model'],
            'Vehicle_colour' => $data['vehicle_colour'],
            'created_from' => 'incident_report'
        ]
    );
}

function logPersonCreation($conn, $people_id, $data) {
    auditLog($conn, 'CREATE', 'People', $people_id, null,
        [
            'People_name' => $data['person_name'],
            'People_licence' => $data['licence_number'],
            'People_address' => $data['person_address'],
            'created_from' => 'incident_report'
        ]
    );
}

function logIncidentCreation($conn, $incident_id, $data) {
    auditLog($conn, 'CREATE', 'Incident', $incident_id, null,
        [
            'Incident_Date' => $data['incident_date'],
            'Vehicle_ID' => $data['vehicle_id'],
            'People_ID' => $data['people_id'],
            'Offence_ID' => $data['offence_id'],
            'Officer_ID' => $data['officer_id'],
            'Report_Summary' => substr($data['incident_report'], 0, 100) . '...'
        ]
    );
}

// ====== Main Process Functions ======
function createIncident($conn, $data) {
    $stmt = $conn->prepare(
        "INSERT INTO Incident (Incident_ID, Vehicle_ID, People_ID, Incident_Date, Incident_Report, Offence_ID, Officer_ID) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    
    $stmt->bind_param(
        "iissssi",
        $data['incident_id'],
        $data['vehicle_id'],
        $data['people_id'],
        $data['incident_date'],
        $data['incident_report'],
        $data['offence_id'],
        $data['officer_id']
    );

    if (!$stmt->execute()) {
        throw new Exception("Error recording incident");
    }

    logIncidentCreation($conn, $data['incident_id'], $data);
}

function handleFormSubmission($conn, $data) {
    // Store form data in session for potential re-display
    $_SESSION['incident_data'] = $_POST;
    $errors = validateFormData($_POST);
    
    if (empty($errors)) {
        try {
            $vehicle_id = processVehicleData($conn, $_POST);
            $people_id = processPersonData($conn, $_POST);
            $offence_id = processOffenceData($conn, $_POST);
            
            createIncident($conn, [
                'incident_id' => $data['next_incident_id'],
                'vehicle_id' => $vehicle_id,
                'people_id' => $people_id,
                'offence_id' => $offence_id,
                'officer_id' => $data['officer_id'],
                'incident_date' => $_POST['incident_date'],
                'incident_report' => $_POST['incident_report']
            ]);
            
            // Clear form data and set success message
            unset($_SESSION['incident_data']);
            $_SESSION['message'] = "Incident successfully recorded";
            $_SESSION['message_type'] = "success";
            
            // Redirect to clear the form
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (Exception $e) {
            // Store error message and redirect
            $_SESSION['message'] = $e->getMessage();
            $_SESSION['message_type'] = "error";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        // Store error message and redirect
        $_SESSION['message'] = implode(". ", $errors);
        $_SESSION['message_type'] = "error";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ====== Page Initialization ======
function initializePageData($conn) {
    return [
        'message' => getStoredMessage(),
        'form_data' => $_SESSION['incident_data'] ?? [],
        'officer_id' => $_SESSION['officer_id'] ?? null,
        'next_incident_id' => getNextIncidentId($conn),
        'offences' => getOffences($conn)
    ];
}

// ====== Main Execution ======
try {
    checkAuthentication();
    $conn = getDatabaseConnection();
    $data = initializePageData($conn);
    
    if (isset($_GET['action'])) {
        handleAjaxRequest($conn);
    }
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        handleFormSubmission($conn, $data);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['message'] = "Error: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Incident - BTD</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/new_incident.css">
    <script src="components/incident-scripts.js" defer></script>
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>New Incident Report</h1></center>
            
            <form method="post" class="incident-form" novalidate>
                <!-- Incident Details Section -->
                <div class="form-section">
                    <h2>Incident Details</h2>
                    
                    <div class="form-group">
                        <label>Incident ID</label>
                        <input type="text" name="incident_id" 
                               value="<?php echo htmlspecialchars($data['next_incident_id']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Incident Date<span class="required">*</span></label>
                        <input type="date" name="incident_date" required max="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo htmlspecialchars($data['form_data']['incident_date'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Officer ID</label>
                        <input type="text" name="officer_id" value="<?php echo isset($data['officer_id']) ? htmlspecialchars($data['officer_id']) : 'Not an officer'; ?>" 
                            readonly>
                    </div>

                    <div class="form-group">
                        <label>Offence Description</label>
                        <select name="offence_description">
                            <option value="">Select Offence</option>
                            <?php
                            while ($offence = $data['offences']->fetch_assoc()) {
                                $selected = ($data['form_data']['offence_description'] ?? '') === $offence['Offence_description'] ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($offence['Offence_description']) . "' $selected>" . 
                                     htmlspecialchars($offence['Offence_description']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Vehicle Information Section -->
                <div class="form-section">
                    <h2>Vehicle Information</h2>
                    
                    <div class="form-group">
                        <label>Vehicle Plate Number</label>
                        <input type="text" name="vehicle_plate" value="<?php echo htmlspecialchars($data['form_data']['vehicle_plate'] ?? ''); ?>" 
                        maxlength="6" placeholder="Enter vehicle plate number">
                    </div>

                    <div class="form-group">
                        <label>Make</label>
                        <input type="text" name="vehicle_make" value="<?php echo htmlspecialchars($data['form_data']['vehicle_make'] ?? ''); ?>"
                               placeholder="Required for new vehicles">
                    </div>

                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="vehicle_model" value="<?php echo htmlspecialchars($data['form_data']['vehicle_model'] ?? ''); ?>"
                            placeholder="Required for new vehicles">
                    </div>

                    <div class="form-group">
                        <label>Colour</label>
                        <input type="text" name="vehicle_colour" value="<?php echo htmlspecialchars($data['form_data']['vehicle_colour'] ?? ''); ?>"
                               placeholder="Required for new vehicles">
                    </div>
                </div>

                <!-- Person Information Section -->
                <div class="form-section">
                    <h2>Person Information</h2>
                    
                    <div class="form-group">
                        <label>Licence Number</label>
                        <input type="text" name="licence_number" value="<?php echo htmlspecialchars($data['form_data']['licence_number'] ?? ''); ?>"
                               maxlength="20" placeholder="Enter licence number">
                    </div>

                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="person_name" value="<?php echo htmlspecialchars($data['form_data']['person_name'] ?? ''); ?>"
                               placeholder="Required for new people">
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="person_address" value="<?php echo htmlspecialchars($data['form_data']['person_address'] ?? ''); ?>"
                               placeholder="Optional for new people">
                    </div>
                </div>

                <!-- Report Details Section -->
                <div class="form-section">
                    <h2>Report Details</h2>
                    
                    <div class="form-group">
                        <label>Incident Report<span class="required">*</span></label>
                        <textarea name="incident_report" rows="4" required 
                                  placeholder="Enter incident details"><?php echo htmlspecialchars($data['form_data']['incident_report'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Form Controls -->
                <div class="button-group">
                    <button type="submit" class="btn-submit">Submit Report</button>
                    <a href="dashboard.php" class="btn-cancel">Cancel</a>
                </div>

                <!-- Message Display -->
                <?php if ($data['message']['text']): ?>
                    <div class="message <?php echo htmlspecialchars($data['message']['type']); ?>">
                        <?php echo htmlspecialchars($data['message']['text']); ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>