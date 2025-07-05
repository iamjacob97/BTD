<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}
if ($_SESSION['role'] !== "admin") {
    header("Location: dashboard.php");
    exit();
}

require_once "components/cw2db.php";
require_once "components/audit_functions.php";  // Added audit functions

// Initialize variables
$form_data = $_SESSION['offence_form_data'] ?? [];
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['offence_form_data'], $_SESSION['message'], $_SESSION['message_type']);

try {
    // Establish database connection
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        throw new Exception(mysqli_connect_error());
    }

    // Generate the next Offence ID
    $offence_id_query = "SELECT COALESCE(MAX(Offence_ID), 0) + 1 AS next_offence_id FROM Offence";
    $offence_id_result = $conn->query($offence_id_query);
    $next_offence_id = $offence_id_result->fetch_assoc()['next_offence_id'] ?? 1;

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $offence_description = trim($_POST['Offence_description']);
        $offence_maxFine = floatval($_POST['Offence_maxFine']);
        $offence_maxPoints = intval($_POST['Offence_maxPoints']);

        // Validate offence points
        if ($offence_maxPoints > 12) {
            throw new Exception("Maximum points cannot exceed 12!");
        }

        // Check if offence description already exists
        $check_stmt = $conn->prepare("SELECT Offence_ID FROM Offence WHERE Offence_description = ?");
        $check_stmt->bind_param("s", $offence_description);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("This offence already exists!");
        }
        $check_stmt->close();

        // Insert new offence
        $stmt = $conn->prepare("INSERT INTO Offence (Offence_ID, Offence_description, Offence_maxFine, Offence_maxPoints) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isdi", 
            $next_offence_id,
            $offence_description,
            $offence_maxFine,
            $offence_maxPoints
        );

        if ($stmt->execute()) {
            // Log the offence creation
            auditLog($conn, 'CREATE', 'Offence', $next_offence_id, null,
                [
                    'Offence_description' => $offence_description,
                    'Offence_maxFine' => $offence_maxFine,
                    'Offence_maxPoints' => $offence_maxPoints,
                    'created_by' => $_SESSION['username'],
                    'creation_type' => 'admin_creation'
                ]
            );

            $_SESSION['message'] = "Offence successfully created!";
            $_SESSION['message_type'] = 'success';
        } else {
            throw new Exception("Error creating offence!");
        }
        
        $stmt->close();
        header("Location: new_offence.php");
        exit();
    }
} catch (Exception $e) {
    $_SESSION['message'] = $e->getMessage();
    $_SESSION['message_type'] = 'error';
    $_SESSION['offence_form_data'] = $_POST ?? [];
    error_log($e->getMessage());
    
    if (!isset($_POST['Offence_description'])) {
        $message = "An error occurred while preparing the form";
        $message_type = 'error';
    } else {
        header("Location: new_offence.php");
        exit();
    }
} finally {
    if (isset($conn)) mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create new offence - British Traffic Department">
    <title>BTD - Create Offence</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/new_offence.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>Create New Offence</h1></center>
            
            <form method="POST" id="offenceForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <label for="offence_id">Offence ID</label>
                <input type="text" id="offence_id" name="offence_id" value="<?php echo htmlspecialchars($next_offence_id); ?>" 
                       readonly>

                <label for="Offence_description">Offence Description *</label>
                <textarea id="Offence_description" 
                         name="Offence_description" rows="3" required 
                         maxlength="255"><?= htmlspecialchars($form_data['Offence_description'] ?? '') ?></textarea>

                <label for="Offence_maxFine">Maximum Fine (£) *</label>
                <input type="number" id="Offence_maxFine" name="Offence_maxFine" 
                       step="0.01" min="0" required value="<?= htmlspecialchars($form_data['Offence_maxFine'] ?? '') ?>">

                <label for="Offence_maxPoints">Maximum Points (0-12) *</label>
                <input type="number" id="Offence_maxPoints" name="Offence_maxPoints" min="0" max="12" required
                       value="<?= htmlspecialchars($form_data['Offence_maxPoints'] ?? '') ?>">

                <button type="submit">Create Offence</button>
            </form>

            <?php if ($message): ?>
                <div class="message <?= $message_type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    document.getElementById('offenceForm').onsubmit = function(e) {
        const maxPoints = parseInt(document.getElementById('Offence_maxPoints').value);
        const maxFine = parseFloat(document.getElementById('Offence_maxFine').value);
        const description = document.getElementById('Offence_description').value.trim();

        for (let i = 0; i < description.length; i++) {
            if (description[i] === '') {
                alert('Please provide an offence description!');
                e.preventDefault();
                return false;
            }
        }

        if (maxPoints > 12 || maxPoints < 0) {
            alert('Points must be between 0 and 12!');
            e.preventDefault();
            return false;
        }

        if (maxFine < 0) {
            alert('Fine cannot be negative!');
            e.preventDefault();
            return false;
        }

        return true;
    };
    </script>
</body>
</html>