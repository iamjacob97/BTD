<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once "components/cw2db.php";
    
    try {
        $conn = mysqli_connect($servername, $username, $password, $dbname);
        
        if (!$conn) {
            throw new Exception(mysqli_connect_error());
        }

        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        // Validate required fields
        if (empty($current_password)) {
            throw new Exception("Current password is required");
        }
        if (empty($new_password)) {
            throw new Exception("New password is required");
        }
        if (empty($confirm_password)) {
            throw new Exception("Password confirmation is required");
        }

        // Verify current password
        $stmt = mysqli_prepare($conn, "SELECT Password FROM User WHERE Username = ?");
        mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            if ($user['Password'] === $current_password) {
                if ($new_password === $confirm_password) {
                    $update_stmt = mysqli_prepare($conn, 
                        "UPDATE User SET Password = ? WHERE Username = ?");
                    mysqli_stmt_bind_param($update_stmt, "ss", $new_password, $_SESSION['username']);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $message = "Password successfully updated!";
                        $message_type = 'success';
                    } else {
                        throw new Exception("Error updating password");
                    }
                } else {
                    throw new Exception("New passwords do not match");
                }
            } else {
                throw new Exception("Current password is incorrect");
            }
        } else {
            throw new Exception("User not found");
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
        error_log($e->getMessage());
    } finally {
        if (isset($stmt)) mysqli_stmt_close($stmt);
        if (isset($update_stmt)) mysqli_stmt_close($update_stmt);
        if (isset($conn)) mysqli_close($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Change Password - British Traffic Department">
    <title>BTD - Change Password</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/password.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Change Password</h1></center>
            
            <form method="POST" class="password-form" novalidate>
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" id="new_password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-submit">Update Password</button>
                    <a href="dashboard.php" class="btn-cancel">Cancel</a>
                </div>

                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </main>
</body>
</html>