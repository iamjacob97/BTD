<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require_once "components/cw2db.php";
require_once "components/audit_functions.php";

$message = '';
$message_type = '';
$vehicle = null;

// Fetch vehicle details
if (isset($_GET['id'])) {
    try {
        $conn = mysqli_connect($servername, $username, $password, $dbname);
        if (!$conn) {
            throw new Exception(mysqli_connect_error());
        }

        $stmt = mysqli_prepare($conn, 
            "SELECT Vehicle_make, Vehicle_model, Vehicle_colour, Vehicle_plate 
             FROM Vehicle WHERE Vehicle_ID = ?");
        
        mysqli_stmt_bind_param($stmt, "i", $_GET['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($vehicle = mysqli_fetch_assoc($result)) {
            // Vehicle found
        } else {
            header("Location: vehicles.php");
            exit();
        }
        
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $message = "Error loading vehicle details";
        $message_type = 'error';
        error_log($e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($conn)) {
            $conn = mysqli_connect($servername, $username, $password, $dbname);
        }

        // Validate input
        $make = trim($_POST['make'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $colour = trim($_POST['colour'] ?? '');
        $plate = trim($_POST['plate'] ?? '');

        if (empty($make)) {
            $message = "Make cannot be empty";
            $message_type = 'error';
        } elseif (empty($model)) {
            $message = "Model cannot be empty";
            $message_type = 'error';
        } elseif (empty($plate)) {
            $message = "Plate number cannot be empty";
            $message_type = 'error';
        } else {
            // Get old values before update
            $old_values = getOldValues($conn, 'Vehicle', 'Vehicle_ID', $_GET['id']);

            // Check if plate exists for different vehicle
            $check_stmt = mysqli_prepare($conn, 
                "SELECT Vehicle_ID FROM Vehicle WHERE Vehicle_plate = ? AND Vehicle_ID != ?");
            
            mysqli_stmt_bind_param($check_stmt, "si", $plate, $_GET['id']);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                $message = "Plate number already exists";
                $message_type = 'error';
            } else {
                $update_stmt = mysqli_prepare($conn, 
                    "UPDATE Vehicle 
                     SET Vehicle_make = ?, Vehicle_model = ?, 
                         Vehicle_colour = ?, Vehicle_plate = ? 
                     WHERE Vehicle_ID = ?");
                
                mysqli_stmt_bind_param($update_stmt, "ssssi", 
                    $make, $model, $colour, $plate, $_GET['id']);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    // Prepare new values for audit log
                    $new_values = [
                        'Vehicle_make' => $make,
                        'Vehicle_model' => $model,
                        'Vehicle_colour' => $colour,
                        'Vehicle_plate' => $plate
                    ];

                    // Log the update operation
                    auditLog(
                        $conn,'UPDATE', 'Vehicle', $_GET['id'], $old_values, $new_values);

                    $message = "Vehicle details updated successfully";
                    $message_type = 'success';
                    $vehicle = $new_values;
                } else {
                    throw new Exception("Error updating record");
                }
            }
        }
    } catch (Exception $e) {
        $message = "Error updating vehicle details";
        $message_type = 'error';
        error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vehicle - BTD</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/edit_vehicle.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Edit Vehicle Details</h1></center>
            
            <?php if ($vehicle): ?>
                <form method="POST" class="edit-form">
                    <div class="form-group">
                        <label for="plate">Plate Number</label>
                        <input type="text" id="plate" name="plate" 
                               value="<?php echo htmlspecialchars($vehicle['Vehicle_plate']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="make">Make</label>
                        <input type="text" id="make" name="make" 
                                value="<?php echo htmlspecialchars($vehicle['Vehicle_make']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="model">Model</label>
                        <input type="text" id="model" name="model" 
                                value="<?php echo htmlspecialchars($vehicle['Vehicle_model']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="colour">Colour</label>
                        <input type="text" id="colour" name="colour" 
                                value="<?php echo htmlspecialchars($vehicle['Vehicle_colour']); ?>" required>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-submit">Update Vehicle</button>
                        <a href="vehicles.php" class="btn-cancel">Cancel</a>
                    </div>

                    <?php if ($message): ?>
                        <div class="message <?php echo $message_type; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>