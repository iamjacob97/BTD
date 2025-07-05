<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require_once "components/cw2db.php";
require_once "components/audit_functions.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['incident_id'])) {
    $_SESSION['message'] = "Invalid request";
    $_SESSION['message_type'] = "error";
    header("Location: incidents.php");
    exit();
}

try {
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        throw new Exception(mysqli_connect_error());
    }

    $incidentId = trim($_POST['incident_id']);
    
    // Fetch incident details with related fine
    $fetch_stmt = mysqli_prepare($conn, "
        SELECT 
            i.*, v.Vehicle_plate, p.People_licence, o.Officer_Credentials, of.Offence_description,
            f.Fine_ID,
            f.Fine_Amount,
            f.Fine_Points
        FROM Incident i
        LEFT JOIN Vehicle v ON i.Vehicle_ID = v.Vehicle_ID
        LEFT JOIN People p ON i.People_ID = p.People_ID
        LEFT JOIN Officer o ON i.Officer_ID = o.Officer_ID
        LEFT JOIN Offence of ON i.Offence_ID = of.Offence_ID
        LEFT JOIN Fines f ON i.Incident_ID = f.Incident_ID
        WHERE i.Incident_ID = ?
    ");
    mysqli_stmt_bind_param($fetch_stmt, "i", $incidentId);
    mysqli_stmt_execute($fetch_stmt);
    $result = mysqli_stmt_get_result($fetch_stmt);
    $data = mysqli_fetch_assoc($result);

    if (!$data) {
        $_SESSION['message'] = "Incident not found";
        $_SESSION['message_type'] = "error";
        header("Location: incidents.php");
        exit();
    }
    mysqli_stmt_close($fetch_stmt);

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Log fine deletion first if fine exists
        if ($data['Fine_ID']) {
            auditLog($conn, 'DELETE', 'Fines', $data['Fine_ID'],
                [
                    'fine_details' => [
                        'amount' => $data['Fine_Amount'],
                        'points' => $data['Fine_Points'],
                        'incident_id' => $incidentId
                    ],
                    'incident_info' => [
                        'date' => $data['Incident_Date'],
                        'vehicle_plate' => $data['Vehicle_plate'],
                        'person_licence' => $data['People_licence']
                    ],
                    'deletion_reason' => 'Cascade delete from Incident deletion'
                ],
                null
            );
        }

        // Delete the incident (fine will be deleted via cascade)
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM Incident WHERE Incident_ID = ?");
        mysqli_stmt_bind_param($delete_stmt, "i", $incidentId);

        if ($delete_stmt->execute()) {
            // Log the incident deletion
            auditLog(
                $conn,
                'DELETE',
                'Incident',
                $incidentId,
                [
                    'incident_details' => [
                        'date' => $data['Incident_Date'],
                        'report' => substr($data['Incident_Report'], 0, 100) . '...',
                        'officer' => $data['Officer_Credentials']
                    ],
                    'vehicle_info' => [
                        'plate' => $data['Vehicle_plate']
                    ],
                    'person_info' => [
                        'licence' => $data['People_licence']
                    ],
                    'offence_info' => [
                        'description' => $data['Offence_description']
                    ],
                    'related_fine_deleted' => $data['Fine_ID'] ? 'Yes' : 'No'
                ],
                null
            );

            mysqli_commit($conn);
            $_SESSION['message'] = "Incident and associated fine successfully deleted";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("Error deleting incident");
        }

        mysqli_stmt_close($delete_stmt);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

    mysqli_close($conn);
    
    header("Location: incidents.php");
    exit();

} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['message'] = "Error deleting incident";
    $_SESSION['message_type'] = "error";
    header("Location: incidents.php");
    exit();
}
?>