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

try {
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        throw new Exception(mysqli_connect_error());
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        $input = [
            'name' => trim($_POST['name']),
            'address' => trim($_POST['address']),
            'licence_number' => trim($_POST['licence_number'])
        ];

        // Validate input
        if (empty($input['name'])) {
            $message = "Name is required.";
            $message_class = "error";
        } elseif (empty($input['licence_number'])) {
            $message = "Licence number is required.";
            $message_class = "error";
        } else {
            // Check if person exists
            $stmt = $conn->prepare("SELECT People_ID FROM People WHERE People_licence = ?");
            $stmt->bind_param("s", $input['licence_number']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = "Person with this licence number already exists!";
                $message_class = "error";
            } else {
                // Insert new person
                $stmt = $conn->prepare("INSERT INTO People (People_name, People_address, People_licence) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $input['name'], $input['address'], $input['licence_number']);

                if ($stmt->execute()) {
                    $new_person_id = $stmt->insert_id;
                    
                    // Log the creation
                    auditLog($conn, 'CREATE', 'People', $new_person_id, null,  // No old values for creation
                        [
                            'People_name' => $input['name'],
                            'People_address' => $input['address'],
                            'People_licence' => $input['licence_number']
                        ]
                    );

                    $message = "Person successfully added!";
                    $message_class = "success";
                } else {
                    $message = "Error adding person: " . $stmt->error;
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
    <meta name="description" content="British Traffic Department - Create Person">
    <title>BTD - Create Person</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/create_person.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include "components/sidebar.php" ?>
    <main>
        <div class="container">
            <center><h1>New Person</h1></center>
            <div class="create-form">
                <form id="personForm" method="POST" action="" novalidate>
                    <div class="form-group">
                        <label for="name">
                            Person's Name<span class="required">*</span>
                        </label>
                        <input type="text" id="name" name="name" 
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div><br>

                    <div class="form-group">
                        <label for="licence_number">
                            Licence Number<span class="required">*</span>
                        </label>
                        <input type="text" id="licence_number" name="licence_number" 
                               value="<?= htmlspecialchars($_POST['licence_number'] ?? '') ?>" required>
                    </div><br>

                    <div class="form-group">
                        <label for="address">Person's Address</label>
                        <input type="text" id="address" name="address" 
                            value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-submit">Add Person</button>
                        <a href="dashboard.php" class="btn-cancel">Cancel</a>
                    </div>
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
        document.querySelector('[name="licence_number"]').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>