<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require_once "components/cw2db.php";
require_once "components/audit_functions.php";

// Initialize variables
$message = "";
$message_class = "";
$errors = [];

try {
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        throw new Exception(mysqli_connect_error());
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize input
        $input = [
            'plate_number' => trim($_POST['plate_number']),
            'make' => trim($_POST['make']),
            'model' => trim($_POST['model']),
            'color' => trim($_POST['colour'])
        ];

        // Validate input
        if (empty($input['plate_number'])) $errors[] = "Vehicle Plate Number is required.";
        if (empty($input['make'])) $errors[] = "Vehicle Make is required.";
        if (empty($input['model'])) $errors[] = "Vehicle Model is required.";
        if (empty($input['color'])) $errors[] = "Vehicle Colour is required.";

        define('VEHICLE_PLATE_MAX_LENGTH', 6);
        if (strlen($input['plate_number']) !== VEHICLE_PLATE_MAX_LENGTH) {
            $errors[] = "The Plate Number must be exactly " . VEHICLE_PLATE_MAX_LENGTH . " characters.";
        }

        if (empty($errors)) {
            // Check if vehicle exists
            $stmt = $conn->prepare("SELECT Vehicle_ID FROM Vehicle WHERE Vehicle_plate = ?");
            $stmt->bind_param("s", $input['plate_number']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = "Vehicle with this plate number already exists!";
                $message_class = "error";
            } else {
                // Insert new vehicle
                $stmt = $conn->prepare("INSERT INTO Vehicle (Vehicle_plate, Vehicle_make, Vehicle_model, Vehicle_colour) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $input['plate_number'], $input['make'], $input['model'], $input['color']
                );

                if ($stmt->execute()) {
                    $new_vehicle_id = $stmt->insert_id;
                    
                    // Log the creation
                    auditLog($conn, 'CREATE', 'Vehicle', $new_vehicle_id, null,  
                        [
                            'Vehicle_plate' => $input['plate_number'],
                            'Vehicle_make' => $input['make'],
                            'Vehicle_model' => $input['model'],
                            'Vehicle_colour' => $input['color']
                        ]
                    );

                    $message = "Vehicle successfully added!";
                    $message_class = "success";
                } else {
                    $message = "Error adding vehicle: " . $stmt->error;
                    $message_class = "error";
                }
            }
        }
    }
} catch (Exception $e) {
    $message = "Error: Unable to process request";
    $message_class = "error";
    error_log($e->getMessage());
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="British Traffic Department - Create Vehicle">
    <title>BTD - Create Vehicle</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/create_vehicle.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include "components/sidebar.php" ?>
    <main>
        <div class="container">
            <center><h1>New Vehicle</h1></center>
            <div class="create-form">
                <form id="vehicleForm" method="POST" action="" novalidate>
                    <div class="form-group">
                        <label for="plate_number">
                            Vehicle Plate Number<span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="plate_number" name="plate_number" 
                               value="<?= htmlspecialchars($_POST['plate_number'] ?? '') ?>" required 
                               maxlength="6" minlength="6">
                    </div><br>

                    <div class="form-group">
                        <label for="make">
                            Vehicle Make<span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="make" name="make" 
                               value="<?= htmlspecialchars($_POST['make'] ?? '') ?>" required>
                    </div><br>

                    <div class="form-group">
                        <label for="model">
                            Vehicle Model<span class="required">*</span>
                        </label>
                        <input type="text" id="model" name="model" 
                               value="<?= htmlspecialchars($_POST['model'] ?? '') ?>" required>
                    </div><br>

                    <div class="form-group">
                        <label for="colour">
                            Vehicle Colour<span class="required">*</span>
                        </label>
                        <input type="text" id="colour" name="colour" 
                               value="<?= htmlspecialchars($_POST['colour'] ?? '') ?>" required>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-submit">Create Vehicle</button>
                        <a href="dashboard.php" class="btn-cancel">Cancel</a>
                    </div>
                    <?php if (!empty($errors)): ?>
                        <div class="message error">
                            <?php foreach ($errors as $error): ?>
                                <p><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($message): ?>
                        <div class="message <?= $message_class ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </main>
    <script>
        document.querySelector('[name="plate_number"]').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>