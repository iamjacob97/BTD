<?php
session_start();

$message = "";
if (isset($_POST['username'], $_POST['password']) && 
    !empty($_POST['username']) && !empty($_POST['password'])) {
    
    require_once "components/cw2db.php";
    
    try {
        $conn = mysqli_connect($servername, $username, $password, $dbname);
        
        if (!$conn) {
            throw new Exception("Connection failed: " . mysqli_connect_error());
        }
        
        // Prevent SQL injection using prepared statements. Although we are not concerened about security, this approach also makes it easier to bind a parameter as a certain type i.e, string or integer)
        $stmt = mysqli_prepare($conn, "SELECT * FROM User WHERE Username = ?");
        mysqli_stmt_bind_param($stmt, "s", $_POST['username']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            if ($row['Password'] === $_POST['password']) {
                $_SESSION['user_id'] = $row['User_ID'];
                $_SESSION['username'] = $row['Username'];
                $_SESSION['role'] = $row['Role'];
                
                // Check for officer status. Not every user is an officer.
                $stmt = mysqli_prepare($conn, "SELECT Officer_ID FROM Officer WHERE User_ID = ?");
                mysqli_stmt_bind_param($stmt, "s", $row['User_ID']);
                mysqli_stmt_execute($stmt);
                $officer_result = mysqli_stmt_get_result($stmt);
                
                if ($officer_row = mysqli_fetch_assoc($officer_result)) {
                    $_SESSION['officer_id'] = $officer_row['Officer_ID'];
                }
                
                header("Location: dashboard.php");
                exit();
            } else {
                $message = "Invalid password";
            }
        } else {
            $message = "No such user!";
        }
    } catch (Exception $e) {
        $message = "System error. Please try again later.";
        error_log($e->getMessage());
    } finally {
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
        if (isset($conn)) {
            mysqli_close($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login Page</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/login.css">
    <script>
        function hideMessage() {
            const messageContainer = document.getElementById('message');
            if (messageContainer !== '') {
                setTimeout(() => {
                    messageContainer.style.opacity = '0';
                    setTimeout(() => {
                        messageContainer.style.display = 'none';
                    }, 300);
                }, 3000);
            }
        }
        window.addEventListener('DOMContentLoaded', hideMessage);
    </script>
</head>
<body>
    <div>
        <center><img src="components/BTD_logo.png" alt="British Traffic Department Logo"></center>
        <center><h1>BRITISH TRAFFIC DEPARTMENT</h1></center>
        <form method="POST">
            <label for="username">
                Username 
                <input type="text" id="username" name="username" 
                       placeholder="Enter your username" required autofocus>
            </label>
            <label for="password">
                Password 
                <input type="password" id="password" name="password" 
                       placeholder="Enter your password" required>
            </label>
            <button type="submit">Login</button>
            <center><p id="message">
                <?php echo htmlspecialchars($message); ?></center>
            </p>
        </form>
    </div>
</body>
</html>