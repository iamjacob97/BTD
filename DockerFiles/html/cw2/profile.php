<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$officer_info = [];
$message = "";

if (isset($_SESSION['officer_id'])) {
    require_once "components/cw2db.php";
    
    try {
        $conn = mysqli_connect($servername, $username, $password, $dbname);
        
        if (!$conn) {
            throw new Exception(mysqli_connect_error());
        }
        
        $stmt = mysqli_prepare($conn, 
            "SELECT Officer_Credentials, Officer_Fname, Officer_Lname, Officer_Email, Officer_PhoneNo 
             FROM Officer 
             WHERE Officer_ID = ?");
             
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['officer_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $officer_info = $row;
        } else {
            $message = "Officer information not found";
        }
    } catch (Exception $e) {
        $message = "Error: Unable to fetch officer information";
        error_log($e->getMessage());
    } finally {
        if (isset($stmt)) mysqli_stmt_close($stmt);
        if (isset($conn)) mysqli_close($conn);
    }
} else {
    $message = "No Officer ID found in session";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Change Password - British Traffic Department">
    <title>BTD - Officer Profile</title>
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/profile.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Officer Profile</h1></center>
            
            <?php if (!empty($message)): ?>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <?php if (!empty($officer_info)): ?>
                <div class="profile-info">
                    <div class="profile-item">
                        <label>Credentials:</label>
                        <span><?php echo htmlspecialchars($officer_info['Officer_Credentials']); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>First Name:</label>
                        <span><?php echo htmlspecialchars($officer_info['Officer_Fname']); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Last Name:</label>
                        <span><?php echo htmlspecialchars($officer_info['Officer_Lname']); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($officer_info['Officer_Email'] ?? 'Not provided'); ?></span>
                    </div>
                    <div class="profile-item">
                        <label>Phone Number:</label>
                        <span><?php echo htmlspecialchars($officer_info['Officer_PhoneNo'] ?? 'Not provided'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <div class="action-buttons">
                <a href="change_password.php" class="btn-change-password">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="M280-400q-33 0-56.5-23.5T200-480q0-33 23.5-56.5T280-560q33 0 56.5 23.5T360-480q0 33-23.5 56.5T280-400Zm200 0q-33 0-56.5-23.5T400-480q0-33 23.5-56.5T480-560q33 0 56.5 23.5T560-480q0 33-23.5 56.5T480-400Zm200 0q-33 0-56.5-23.5T600-480q0-33 23.5-56.5T680-560q33 0 56.5 23.5T760-480q0 33-23.5 56.5T680-400Z"/>
                    </svg>
                    Change Password
                </a>
            </div>
        </div>
    </main>
</body>
</html>