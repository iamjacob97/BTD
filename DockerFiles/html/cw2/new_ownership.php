<?php
session_start();
require_once "components/cw2db.php";
require_once "components/audit_functions.php";

// ====== Constants ======
const PLATE_LENGTH = 6;

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

// ====== Vehicle Functions ======
function checkVehicleExists($conn, $plate_number) {
    $stmt = mysqli_prepare($conn, "SELECT Vehicle_ID FROM Vehicle WHERE Vehicle_plate = ?");
    mysqli_stmt_bind_param($stmt, "s", $plate_number);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $vehicle = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $vehicle ? $vehicle['Vehicle_ID'] : false;
}

function createVehicle($conn, $plate_number, $make, $model, $colour) {
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO Vehicle (Vehicle_plate, Vehicle_make, Vehicle_model, Vehicle_colour) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $plate_number, $make, $model, $colour);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception("Error adding vehicle");
    }
    
    $vehicle_id = mysqli_insert_id($conn);

    logVehicleCreation($conn, $vehicle_id, [
        'vehicle_plate' => $plate_number,
        'vehicle_make' => $make,
        'vehicle_model' => $model,
        'vehicle_colour' => $colour
    ]);
    
    mysqli_stmt_close($stmt);
    return $vehicle_id;
}

// ====== Owner Functions ======
function checkOwnerExists($conn, $licence_number) {
    $stmt = mysqli_prepare($conn, "SELECT People_ID FROM People WHERE People_licence = ?");
    mysqli_stmt_bind_param($stmt, "s", $licence_number);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $owner = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $owner ? $owner['People_ID'] : false;
}

function createOwner($conn, $owner_name, $licence_number, $owner_address) {
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO People (People_name, People_licence, People_address) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sss", $owner_name, $licence_number, $owner_address);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception("Error adding owner");
    }
    
    $owner_id = mysqli_insert_id($conn);

    logOwnerCreation($conn, $owner_id, [
        'name' => $owner_name,
        'licence' => $licence_number,
        'address' => $owner_address
    ]);
    
    mysqli_stmt_close($stmt);
    return $owner_id;
}

// ====== Ownership Functions ======
function checkCurrentOwnership($conn, $vehicle_id) {
    $stmt = mysqli_prepare($conn, "SELECT People_ID FROM Ownership WHERE Vehicle_ID = ?");
    mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ownership = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $ownership ? $ownership['People_ID'] : false;
}

function updateOwnership($conn, $vehicle_id, $owner_id) {
    $old_values = getOldValues($conn, 'Ownership', 'Vehicle_ID', $vehicle_id);
    
    $stmt = mysqli_prepare($conn, "UPDATE Ownership SET People_ID = ? WHERE Vehicle_ID = ?");
    mysqli_stmt_bind_param($stmt, "ii", $owner_id, $vehicle_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception("Error updating ownership");
    }

    logOwnershipUpdate($conn, $vehicle_id, $old_values, $owner_id);
    
    mysqli_stmt_close($stmt);
    return true;
}

function createOwnership($conn, $vehicle_id, $owner_id) {
    $stmt = mysqli_prepare($conn, "INSERT INTO Ownership (Vehicle_ID, People_ID) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $vehicle_id, $owner_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception("Error creating ownership");
    }

    logOwnershipCreation($conn, $vehicle_id, $owner_id);
    
    mysqli_stmt_close($stmt);
    return true;
}

// ====== Logging Functions ======
function logVehicleCreation($conn, $vehicle_id, $data) {
    auditLog($conn, 'CREATE', 'Vehicle', $vehicle_id, null,
        [
            'Vehicle_plate' => $data['vehicle_plate'],
            'Vehicle_make' => $data['vehicle_make'],
            'Vehicle_model' => $data['vehicle_model'],
            'Vehicle_colour' => $data['vehicle_colour'],
            'created_from' => 'ownership_registration'
        ]
    );
}

function logOwnerCreation($conn, $owner_id, $data) {
    auditLog($conn, 'CREATE', 'People', $owner_id, null,
        [
            'People_name' => $data['name'],
            'People_licence' => $data['licence'],
            'People_address' => $data['address'],
            'created_from' => 'ownership_registration'
        ]
    );
}

function logOwnershipUpdate($conn, $vehicle_id, $old_values, $new_owner_id) {
    auditLog($conn, 'UPDATE', 'Ownership', $vehicle_id, $old_values,
        [
            'Vehicle_ID' => $vehicle_id,
            'People_ID' => $new_owner_id,
            'transfer_type' => 'ownership_change'
        ]
    );
}

function logOwnershipCreation($conn, $vehicle_id, $owner_id) {
    auditLog($conn, 'CREATE', 'Ownership', $vehicle_id, null,
        [
            'Vehicle_ID' => $vehicle_id,
            'People_ID' => $owner_id,
            'registration_type' => 'new_ownership'
        ]
    );
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
        "SELECT v.*, p.People_licence, p.People_name, v.Vehicle_ID 
         FROM Vehicle v 
         LEFT JOIN Ownership o ON v.Vehicle_ID = o.Vehicle_ID 
         LEFT JOIN People p ON o.People_ID = p.People_ID 
         WHERE v.Vehicle_plate = ?");
    
    mysqli_stmt_bind_param($stmt, "s", $plate);
    mysqli_stmt_execute($stmt);
    $vehicle = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($vehicle) {
        auditLog($conn, 'READ', 'Vehicle', $vehicle['Vehicle_ID'], null,
            [
                'lookup_method' => 'plate_search',
                'plate_number' => $plate,
                'context' => 'ownership_check'
            ]
        );

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
        "SELECT People_ID, People_name, People_address FROM People WHERE People_licence = ?");
    
    mysqli_stmt_bind_param($stmt, "s", $licence);
    mysqli_stmt_execute($stmt);
    $person = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($person) {
        auditLog($conn, 'READ', 'People', $person['People_ID'], null,
            [
                'lookup_method' => 'licence_search',
                'licence_number' => $licence,
                'context' => 'ownership_check'
            ]
        );

        echo json_encode([
            'exists' => true,
            'name' => $person['People_name'],
            'address' => $person['People_address']
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
}

// ====== Form Processing Functions ======
function validateFormData($data) {
    if (strlen($data['plate_number']) !== PLATE_LENGTH) {
        return ["Plate number must be exactly " . PLATE_LENGTH . " characters", 'error'];
    }
    
    if (!$data['vehicle_id'] && (empty($data['make']) || empty($data['model']) || empty($data['colour']))) {
        return ["All vehicle details are required for new vehicles", 'error'];
    }
    
    if (!$data['owner_id'] && empty($data['owner_name'])) {
        return ["Owner name is required for new owners", 'error'];
    }
    
    return [null, null];
}

function handleFormSubmission($conn, $post_data) {
    // Save form data to session initially
    $_SESSION['ownership_data'] = $post_data;

    try {
        // Sanitize inputs
        $plate_number = trim($post_data['plate_number']);
        $make = trim($post_data['make']);
        $model = trim($post_data['model']);
        $colour = trim($post_data['colour']);
        $licence_number = trim($post_data['licence_number']);
        $owner_name = trim($post_data['owner_name']);
        $owner_address = trim($post_data['owner_address']);

        // Process vehicle
        $vehicle_id = checkVehicleExists($conn, $plate_number);
        if (!$vehicle_id) {
            $vehicle_id = createVehicle($conn, $plate_number, $make, $model, $colour);
        }

        // Process owner
        $owner_id = checkOwnerExists($conn, $licence_number);
        if (!$owner_id) {
            $owner_id = createOwner($conn, $owner_name, $licence_number, $owner_address);
        }

        // Process ownership
        $current_owner_id = checkCurrentOwnership($conn, $vehicle_id);
        if ($current_owner_id) {
            if ($current_owner_id == $owner_id) {
                throw new Exception("This person is already registered as the owner of this vehicle");
            }
            updateOwnership($conn, $vehicle_id, $owner_id);
            $message = "Vehicle ownership has been transferred successfully";
        } else {
            createOwnership($conn, $vehicle_id, $owner_id);
            $message = "New vehicle ownership has been registered successfully";
        }

        // Clear form data and set success message
        unset($_SESSION['ownership_data']);
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'success';
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['message_type'] = 'error';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ====== Main Execution ======
function handleRequest() {
    try {
        checkAuthentication();
        $conn = getDatabaseConnection();
        
        $GLOBALS['message'] = $_SESSION['message'] ?? '';
        $GLOBALS['message_type'] = $_SESSION['message_type'] ?? '';
        unset($_SESSION['message'], $_SESSION['message_type']);
        
        if (isset($_GET['action'])) {
            handleAjaxRequest($conn);
        }
        
        if ($_SERVER["REQUEST_METHOD"] === 'POST') {
            handleFormSubmission($conn, $_POST);
        }
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        $GLOBALS['message'] = "System error occurred. Please try again.";
        $GLOBALS['message_type'] = 'error';
    } finally {
        if (isset($conn)) {
            mysqli_close($conn);
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
    <title>Vehicle Ownership - BTD</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/ownership.css">
    <script src="components/ownership-scripts.js" defer></script>
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Vehicle Ownership Registration</h1></center>
            
            <form method="post" class="ownership-form">
                <div class="form-section">
                    <h2>Vehicle Details</h2>
                    
                    <div class="form-group">
                        <label>
                            Plate Number
                            <span class="required">*</span>
                        </label>
                        <input type="text" name="plate_number" required value="<?php echo htmlspecialchars($_SESSION['ownership_data']['plate_number'] ?? ''); ?>"
                               placeholder="Enter 6 character plate number" maxlength=6>
                        <span class="hint-text">Must be exactly 6 characters</span>
                    </div>

                    <div class="form-group">
                        <label>Make</label>
                        <input type="text" name="make" 
                               value="<?php echo htmlspecialchars($_SESSION['ownership_data']['make'] ?? ''); ?>"
                               placeholder="Required for new vehicles">
                    </div>

                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="model" 
                               value="<?php echo htmlspecialchars($_SESSION['ownership_data']['model'] ?? ''); ?>"
                               placeholder="Required for new vehicles">
                    </div>

                    <div class="form-group">
                        <label>Colour</label>
                        <input type="text" name="colour" 
                               value="<?php echo htmlspecialchars($_SESSION['ownership_data']['colour'] ?? ''); ?>"
                               placeholder="Required for new vehicles">
                    </div>
                </div>

                <div class="form-section">
                    <h2>Owner Details</h2>
                    
                    <div class="form-group">
                        <label>
                            Licence Number
                            <span class="required">*</span>
                        </label>
                        <input type="text" name="licence_number" required 
                               value="<?php echo htmlspecialchars($_SESSION['ownership_data']['licence_number'] ?? ''); ?>"
                               placeholder="Enter owner's licence number" maxlength=20> 
                    </div>

                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="owner_name"
                               value="<?php echo htmlspecialchars($_SESSION['ownership_data']['owner_name'] ?? ''); ?>"
                               placeholder="Required for new owners">
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="owner_address" 
                               value="<?php echo htmlspecialchars($_SESSION['ownership_data']['owner_address'] ?? ''); ?>"
                               placeholder="Required for new owners">
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-submit">
                        Register Ownership
                    </button>
                    <a href="dashboard.php" class="btn-cancel">Cancel</a>
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