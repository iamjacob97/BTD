<?php
// Common function to be used whenever logging into audit table
function auditLog($conn, $action_type, $table_name, $record_id, $old_values = null, $new_values = null) {
    // Get values from session variables
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $stmt = $conn->prepare(
        "INSERT INTO Audit (User_ID, Username, Action_Type, Table_Name, Record_ID, Old_Values, New_Values, IP_Address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    // Convert arrays to JSON strings for storage
    $old_values = $old_values ? json_encode($old_values) : null;
    $new_values = $new_values ? json_encode($new_values) : null;

    $stmt->bind_param(
        "isssisss",
        $user_id,
        $username,
        $action_type,
        $table_name,
        $record_id,
        $old_values,
        $new_values,
        $ip_address
    );

    $stmt->execute();
    $stmt->close();
}

function getOldValues($conn, $table, $id_field, $id) {
    $stmt = $conn->prepare("SELECT * FROM $table WHERE $id_field = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_values = $result->fetch_assoc();
    $stmt->close();
    return $old_values;
}
?>
