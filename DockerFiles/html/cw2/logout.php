<?php
session_start(); 
session_unset(); // Unsets all session variables
session_destroy(); // Destroy the session


header("Location: index.php");
exit();
?>