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
$person = null;

// Fetch person details
if (isset($_GET['id'])) {
    try {
        $conn = mysqli_connect($servername, $username, $password, $dbname);
        if (!$conn) {
            throw new Exception(mysqli_connect_error());
        }

        $stmt = mysqli_prepare($conn, 
            "SELECT * FROM People WHERE People_ID = ?");  
        
        mysqli_stmt_bind_param($stmt, "i", $_GET['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($person = mysqli_fetch_assoc($result)) {
            // Log the view operation
            auditLog($conn, 'READ', 'People', $_GET['id'], null, $person);
        } else {
            header("Location: people.php");
            exit();
        }
        
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $message = "Error loading person details";
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

        // Trim and validate inputs
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $licence = trim($_POST['licence'] ?? '');

        // Validate required fields
        if (empty($name)) {
            $message = "Name cannot be empty";
            $message_type = 'error';
        } elseif (empty($licence)) {
            $message = "License number cannot be empty";
            $message_type = 'error';
        } else {
            // Get old values before update for audit log
            $old_values = getOldValues($conn, 'People', 'People_ID', $_GET['id']);

            // Check if license already exists for different person
            $check_stmt = mysqli_prepare($conn, 
                "SELECT People_ID FROM People WHERE People_licence = ? AND People_ID != ?");
            
            mysqli_stmt_bind_param($check_stmt, "si", $licence, $_GET['id']);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                $message = "License number already exists";
                $message_type = 'error';
            } else {
                $update_stmt = mysqli_prepare($conn, 
                    "UPDATE People SET People_name = ?, People_address = ?, People_licence = ? WHERE People_ID = ?");
                
                mysqli_stmt_bind_param($update_stmt, "sssi", 
                    $name,
                    $address,
                    $licence,
                    $_GET['id']
                );
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $message = "Person details updated successfully";
                    $message_type = 'success';
                    
                    // Log the update operation
                    $new_values = [
                        'People_name' => $name,
                        'People_address' => $address,
                        'People_licence' => $licence
                    ];
                    
                    auditLog($conn, 'UPDATE', 'People', $_GET['id'], $old_values, $new_values);

                    $person = $new_values;
                } else {
                    throw new Exception("Error updating record");
                }
                
                mysqli_stmt_close($update_stmt);
            }
            
            mysqli_stmt_close($check_stmt);
        }
    } catch (Exception $e) {
        $message = "Error updating person details";
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
    <title>Edit Person - BTD</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/edit_person.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Edit Person Details</h1></center>
            
            <?php if ($person): ?>
                <form method="POST" class="edit-form">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars($person['People_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" 
                               value="<?php echo htmlspecialchars($person['People_address']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="licence">License Number</label>
                        <input type="text" id="licence" name="licence" 
                               value="<?php echo htmlspecialchars($person['People_licence']); ?>" required>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-submit">Update Details</button>
                        <a href="people.php" class="btn-cancel">Cancel</a>
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