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
$form_data = $_SESSION['user_form_data'] ?? [];
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';

// Clear session data
unset($_SESSION['user_form_data'], $_SESSION['message'], $_SESSION['message_type']);

try {
    // Establish database connection
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        throw new Exception(mysqli_connect_error());
    }

    // Generate the next User ID
    $user_id_query = "SELECT COALESCE(MAX(User_ID), 0) + 1 AS next_user_id FROM User";
    $user_id_result = $conn->query($user_id_query);
    $next_user_id = $user_id_result->fetch_assoc()['next_user_id'] ?? 1;

    // Generate the next Officer ID
    $officer_id_query = "SELECT COALESCE(MAX(Officer_ID), 0) + 1 AS next_officer_id FROM Officer";
    $officer_id_result = $conn->query($officer_id_query);
    $next_officer_id = $officer_id_result->fetch_assoc()['next_officer_id'] ?? 1;

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Check if username already exists
        $username_check = $conn->prepare("SELECT User_ID FROM User WHERE Username = ?");
        $username_check->bind_param("s", $_POST['Username']);
        $username_check->execute();
        $result = $username_check->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['message'] = "Username already exists!";
            $_SESSION['message_type'] = 'error';
            header("Location: create_user.php");
            exit();
        }
        $username_check->close();
    
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert User
            $user_stmt = $conn->prepare("INSERT INTO User (User_ID, Username, Password, Role) VALUES (?, ?, ?, ?)");
            $user_stmt->bind_param("isss", 
                $next_user_id, $_POST['Username'], $_POST['Password'], $_POST['Role']);
        
            if ($user_stmt->execute()) {
                // Log user creation (excluding password)
                auditLog($conn, 'CREATE', 'User', $next_user_id, null,
                    [
                        'Username' => $_POST['Username'],
                        'Role' => $_POST['Role'],
                        'created_by' => $_SESSION['username'],
                        'creation_type' => 'direct_admin_creation'
                    ]
                );

                // If user is an officer, insert officer details
                if (isset($_POST['is_officer'])) {
                    $officer_stmt = $conn->prepare(
                        "INSERT INTO Officer (Officer_ID, Officer_Fname, Officer_Lname, Officer_Credentials, 
                        Officer_Email, Officer_PhoneNo, User_ID) VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    
                    $officer_stmt->bind_param("isssssi",
                        $next_officer_id, $_POST['Officer_Fname'], $_POST['Officer_Lname'], $_POST['Officer_Credentials'], $_POST['Officer_Email'], $_POST['Officer_PhoneNo'], $next_user_id
                    );
        
                    if ($officer_stmt->execute()) {
                        // Log officer creation
                        auditLog($conn, 'CREATE', 'Officer', $next_officer_id, null,
                            [
                                'Officer_Fname' => $_POST['Officer_Fname'],
                                'Officer_Lname' => $_POST['Officer_Lname'],
                                'Officer_Credentials' => $_POST['Officer_Credentials'],
                                'Officer_Email' => $_POST['Officer_Email'],
                                'Officer_PhoneNo' => $_POST['Officer_PhoneNo'],
                                'User_ID' => $next_user_id,
                                'created_by' => $_SESSION['username'],
                                'creation_type' => 'user_officer_creation'
                            ]
                        );

                        $_SESSION['message'] = "User and Officer details successfully created!";
                        $_SESSION['message_type'] = 'success';
                    } else {
                        throw new Exception("Error creating officer details!");
                    }
                    $officer_stmt->close();
                } else {
                    $_SESSION['message'] = "User successfully created!";
                    $_SESSION['message_type'] = 'success';
                }
            } else {
                throw new Exception("Error creating user!");
            }
            $user_stmt->close();
            
            mysqli_commit($conn);
            header("Location: create_user.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['message'] = $e->getMessage();
            $_SESSION['message_type'] = 'error';
            header("Location: create_user.php");
            exit();
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['message'] = "System error occurred. Please try again.";
    $_SESSION['message_type'] = 'error';
    header("Location: create_user.php");
    exit();
} finally {
    if (isset($conn)) mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create new user - British Traffic Department">
    <title>BTD - Create User</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/create_user.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include "components/sidebar.php" ?>
    
    <main>
        <div class="container">
            <center><h1>Create New User</h1></center>
            
            <form method="POST" id="userForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <label for="user_id">User ID</label>
                <input type="text" id="user_id" name="user_id" 
                       value="<?php echo htmlspecialchars($next_user_id); ?>" readonly>

                <label for="Username">Username *</label>
                <input type="text" id="Username" name="Username" maxlength="50" required
                       value="<?= htmlspecialchars($form_data['Username'] ?? '') ?>">

                <label for="Password">Password *</label>
                <input type="password" id="Password" name="Password" maxlength="50" required>

                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" maxlength="50" required>

                <label for="Role">Role *</label>
                <select id="Role" name="Role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>

                <div class="checkbox-wrapper">
                    <input type="checkbox" id="is_officer" name="is_officer" onchange="toggleOfficerDetails()">
                    <label for="is_officer">This user is an officer</label>
                </div>

                <div id="officer_details" style="display: none;">
                    <label for="officer_id">Officer ID</label>
                    <input type="text" id="officer_id" name="officer_id" 
                           value="<?php echo htmlspecialchars($next_officer_id); ?>" readonly>

                    <label for="Officer_Fname">First Name *</label>
                    <input type="text" id="Officer_Fname" name="Officer_Fname" maxlength="50"
                            value="<?= htmlspecialchars($form_data['Officer_Fname'] ?? '') ?>">

                    <label for="Officer_Lname">Last Name *</label>
                    <input type="text" id="Officer_Lname" name="Officer_Lname" maxlength="50"
                           value="<?= htmlspecialchars($form_data['Officer_Lname'] ?? '') ?>">

                    <label for="Officer_Credentials">Credentials *</label>
                    <input type="text" id="Officer_Credentials" name="Officer_Credentials" maxlength="20"
                           value="<?= htmlspecialchars($form_data['Officer_Credentials'] ?? '') ?>">

                    <label for="Officer_Email">Email *</label>
                    <input type="email" id="Officer_Email" name="Officer_Email" maxlength="50"
                           value="<?= htmlspecialchars($form_data['Officer_Email'] ?? '') ?>">

                    <label for="Officer_PhoneNo">Phone Number *</label>
                    <input type="tel" id="Officer_PhoneNo" name="Officer_PhoneNo" minlength="10" maxlength="15"
                           value="<?= htmlspecialchars($form_data['Officer_PhoneNo'] ?? '') ?>">
                </div>

                <button type="submit">Create User</button>
            </form>

            <?php if ($message): ?>
                <div class="message <?= $message_type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleOfficerDetails() {
            const isOfficer = document.getElementById('is_officer').checked;
            const officerDetails = document.getElementById('officer_details');
            officerDetails.style.display = isOfficer ? 'block' : 'none';
            
            const officerFields = ['Officer_Fname', 'Officer_Lname', 'Officer_Credentials', 'Officer_Email', 'Officer_PhoneNo'];
            officerFields.forEach(field => {
                document.getElementById(field).required = isOfficer;
            });
        }

        document.getElementById('userForm').onsubmit = function(e) {
            const password = document.getElementById('Password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password.length !== confirmPassword.length) {
                alert('Passwords do not match!');
                e.preventDefault();
                return false;
            }

            for (let i = 0; i < password.length; i++) {
                if (password.charAt(i) !== confirmPassword.charAt(i)) {
                    alert('Passwords do not match!');
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        };
    </script>
</body>
</html>