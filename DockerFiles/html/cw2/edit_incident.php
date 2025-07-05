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

function getOffences($conn) {
    return $conn->query("SELECT Offence_ID, Offence_description FROM Offence ORDER BY Offence_description");
}

function getIncidentData($conn, $incident_id) {
    $stmt = $conn->prepare("
        SELECT 
            i.*,
            v.Vehicle_plate, v.Vehicle_make, v.Vehicle_model, v.Vehicle_colour,
            p.People_name, p.People_licence, p.People_address,
            o.Officer_ID,
            of.Offence_description
        FROM Incident i
        LEFT JOIN Vehicle v ON i.Vehicle_ID = v.Vehicle_ID
        LEFT JOIN People p ON i.People_ID = p.People_ID
        LEFT JOIN Officer o ON i.Officer_ID = o.Officer_ID
        LEFT JOIN Offence of ON i.Offence_ID = of.Offence_ID
        WHERE i.Incident_ID = ?
    ");
    
    $stmt->bind_param("i", $incident_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        logIncidentView($conn, $incident_id, $row);
        return $row;
    }
    
    throw new Exception("Incident not found");
}

// ====== Data Processing Functions ======
function processVehicleData($conn, $post_data) {
    if (empty($post_data['vehicle_plate'])) {
        return null;
    }

    $stmt = $conn->prepare("SELECT Vehicle_ID FROM Vehicle WHERE Vehicle_plate = ?");
    $stmt->bind_param("s", $post_data['vehicle_plate']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['Vehicle_ID'];
    }

    // Create new vehicle
    $create_stmt = $conn->prepare(
        "INSERT INTO Vehicle (Vehicle_plate, Vehicle_make, Vehicle_model, Vehicle_colour) VALUES (?, ?, ?, ?)"
    );
    $create_stmt->bind_param("ssss", 
        $post_data['vehicle_plate'],
        $post_data['vehicle_make'],
        $post_data['vehicle_model'],
        $post_data['vehicle_colour']
    );
    
    if (!$create_stmt->execute()) {
        throw new Exception("Error creating vehicle record");
    }

    $vehicle_id = $create_stmt->insert_id;
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
    
    $create_stmt = $conn->prepare(
        "INSERT INTO People (People_name, People_licence, People_address) VALUES (?, ?, ?)"
    );
    $create_stmt->bind_param("sss", 
        $post_data['person_name'],
        $post_data['licence_number'],
        $post_data['person_address']
    );
    
    if (!$create_stmt->execute()) {
        throw new Exception("Error creating person record");
    }

    $people_id = $create_stmt->insert_id;
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

// ====== Logging Functions ======
function logIncidentView($conn, $incident_id, $data) {
    auditLog($conn, 'READ', 'Incident', $incident_id, null,
        [
            'action' => 'edit_view',
            'accessed_data' => [
                'incident_details' => [
                    'date' => $data['Incident_Date'],
                    'report' => substr($data['Incident_Report'], 0, 100) . '...'
                ],
                'vehicle_info' => [
                    'plate' => $data['Vehicle_plate'],
                    'make' => $data['Vehicle_make'],
                    'model' => $data['Vehicle_model'],
                    'colour' => $data['Vehicle_colour']
                ],
                'person_info' => [
                    'name' => $data['People_name'],
                    'licence' => $data['People_licence'],
                    'address' => $data['People_address']
                ],
                'offence_info' => [
                    'description' => $data['Offence_description']
                ]
            ]
        ]
    );
}

function logVehicleLookup($conn, $vehicle, $plate) {
    auditLog($conn, 'READ', 'Vehicle', $vehicle['Vehicle_ID'], null,
        [
            'lookup_method' => 'plate_search',
            'vehicle_plate' => $plate,
            'context' => 'incident_edit'
        ]
    );
}

function logPersonLookup($conn, $person, $licence) {
    auditLog($conn, 'READ', 'People', $person['People_ID'], null,
        [
            'lookup_method' => 'licence_search',
            'licence_number' => $licence,
            'context' => 'incident_edit'
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
            'created_from' => 'incident_edit'
        ]
    );
}

function logPersonCreation($conn, $people_id, $data) {
    auditLog($conn, 'CREATE', 'People', $people_id, null,
        [
            'People_name' => $data['person_name'],
            'People_licence' => $data['licence_number'],
            'People_address' => $data['person_address'],
            'created_from' => 'incident_edit'
        ]
    );
}

// ====== Form Handling Functions ======
function handleFormSubmission($conn, $incident_id, $post_data) {
    // Store form data in session for potential re-display
    $_SESSION['edit_incident_data'] = $post_data;
    
    try {
        $vehicle_id = processVehicleData($conn, $post_data);
        $people_id = processPersonData($conn, $post_data);
        $offence_id = processOffenceData($conn, $post_data);
        
        $old_values = getOldValues($conn, 'Incident', 'Incident_ID', $incident_id);
        
        $stmt = $conn->prepare(
            "UPDATE Incident 
             SET Vehicle_ID = ?, People_ID = ?, Incident_Date = ?, Incident_Report = ?, Offence_ID = ?
             WHERE Incident_ID = ?"
        );
        
        $stmt->bind_param(
            "iisssi",
            $vehicle_id,
            $people_id,
            $post_data['incident_date'],
            $post_data['incident_report'],
            $offence_id,
            $incident_id
        );

        if ($stmt->execute()) {
            // Log the update
            auditLog($conn, 'UPDATE', 'Incident', $incident_id, $old_values,
                [
                    'Vehicle_ID' => $vehicle_id,
                    'People_ID' => $people_id,
                    'Incident_Date' => $post_data['incident_date'],
                    'Incident_Report' => $post_data['incident_report'],
                    'Offence_ID' => $offence_id
                ]
            );

            // Clear form data and set success message
            unset($_SESSION['edit_incident_data']);
            $_SESSION['message'] = "Incident updated successfully";
            $_SESSION['message_type'] = "success";
            
            // Redirect to incidents list
            header("Location: incidents.php?search_input=" . $incident_id);
            exit();
        } else {
            throw new Exception("Error updating incident");
        }
    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $incident_id);
        exit();
    }
}

// ====== Main Execution ======
function handleRequest() {
    try {
        checkAuthentication();
        $conn = getDatabaseConnection();
        
        if (isset($_GET['action'])) {
            handleAjaxRequest($conn);
        }
        
        if (!isset($_GET['id'])) {
            throw new Exception("No incident ID provided");
        }
        
        $incident_id = $_GET['id'];
        
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            handleFormSubmission($conn, $incident_id, $_POST);
        }

        // Initialize data, using stored form data if it exists
        $GLOBALS['incident_data'] = getIncidentData($conn, $incident_id);
        
        // Merge with any stored form data from failed submission
        if (isset($_SESSION['edit_incident_data'])) {
            $GLOBALS['incident_data'] = array_merge($GLOBALS['incident_data'], $_SESSION['edit_incident_data']);
            unset($_SESSION['edit_incident_data']);
        }

        $GLOBALS['message'] = $_SESSION['message'] ?? '';
        $GLOBALS['message_type'] = $_SESSION['message_type'] ?? '';
        $GLOBALS['offences'] = getOffences($conn);
        $GLOBALS['officer_id'] = $_SESSION['officer_id'] ?? null;

        // Clear any stored messages
        unset($_SESSION['message'], $_SESSION['message_type']);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: incidents.php");
        exit();
    } finally {
        if (isset($conn)) {
            $conn->close();
        }
    }
}

// Start the application
handleRequest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Edit Incident Report - British Traffic Department">
    <title>Edit Incident - BTD</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/edit_incident.css">
    <script src="components/incident-scripts.js" defer></script>
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Edit Incident Report</h1></center>
            
            <form method="post" class="incident-form" novalidate>
                <input type="hidden" name="incident_id" value="<?php echo htmlspecialchars($incident_data['Incident_ID']); ?>">
                
                <div class="form-section">
                    <h2>Incident Details</h2>
                    
                    <div class="form-group">
                        <label>Incident ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($incident_data['Incident_ID']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Incident Date<span class="required">*</span></label>
                        <input type="date" name="incident_date" required
                               max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($incident_data['Incident_Date']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Officer ID</label>
                        <input type="text" name="officer_id" 
                            value="<?php echo $incident_data['Officer_ID'] ? htmlspecialchars($incident_data['Officer_ID']) : 'Not an officer'; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Offence Description</label>
                        <select name="offence_description">
                            <option value="">Select Offence</option>
                            <?php
                            while ($offence = $offences->fetch_assoc()) {
                                $selected = ($incident_data['Offence_description'] === $offence['Offence_description']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($offence['Offence_description']) . "' $selected>" . 
                                     htmlspecialchars($offence['Offence_description']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Vehicle Information</h2>
                    
                    <div class="form-group">
                        <label>Vehicle Plate Number</label>
                        <input type="text" name="vehicle_plate" value="<?php echo htmlspecialchars($incident_data['Vehicle_plate'] ?? ''); ?>" 
                        maxlength="6" placeholder="Enter vehicle plate number">
                    </div>

                    <div class="form-group">
                        <label>Make</label>
                        <input type="text" name="vehicle_make" value="<?php echo htmlspecialchars($incident_data['Vehicle_make'] ?? ''); ?>"
                        placeholder="Required for new vehicles">
                    </div>

                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="vehicle_model" value="<?php echo htmlspecialchars($incident_data['Vehicle_model'] ?? ''); ?>"
                        placeholder="Required for new vehicles">
                    </div>

                    <div class="form-group">
                        <label>Colour</label>
                        <input type="text" name="vehicle_colour" value="<?php echo htmlspecialchars($incident_data['Vehicle_colour'] ?? ''); ?>"
                        placeholder="Required for new vehicles">
                    </div>
                </div>

                <div class="form-section">
                    <h2>Person Information</h2>
                    
                    <div class="form-group">
                        <label>Licence Number</label>
                        <input type="text" name="licence_number" value="<?php echo htmlspecialchars($incident_data['People_licence'] ?? ''); ?>"
                            maxlength="20" placeholder="Enter licence number">
                    </div>

                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="person_name" value="<?php echo htmlspecialchars($incident_data['People_name'] ?? ''); ?>"
                            placeholder="Required for new people">
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="person_address" value="<?php echo htmlspecialchars($incident_data['People_address'] ?? ''); ?>"
                            placeholder="Optional for new people">
                    </div>
                </div>

                <div class="form-section">
                    <h2>Report Details</h2>
                    
                    <div class="form-group">
                        <label>Incident Report<span class="required">*</span></label>
                        <textarea name="incident_report" rows="4" required 
                                  placeholder="Enter incident details"><?php echo htmlspecialchars($incident_data['Incident_Report'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-submit">Update Report</button>
                    <a href="incidents.php" class="btn-cancel">Cancel</a>
                </div>

                <?php if ($message): ?>
                    <div class="message <?php echo htmlspecialchars($message_type); ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </main>
</body>
</html>