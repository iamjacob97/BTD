<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require_once "components/cw2db.php";
require_once "components/audit_functions.php";

$search_results = [];
$message = '';

if (isset($_GET['search_input'])) {
    try {
        $conn = mysqli_connect($servername, $username, $password, $dbname);
        if (!$conn) {
            throw new Exception(mysqli_connect_error());
        }

        $searchInput = trim($_GET['search_input']);
        $stmt = mysqli_prepare($conn, 
            "SELECT People_ID, People_name, People_address, People_licence 
             FROM People 
             WHERE People_name LIKE CONCAT('%', ?, '%') 
             OR People_licence LIKE CONCAT('%', ?, '%')");
        
        mysqli_stmt_bind_param($stmt, "ss", $searchInput, $searchInput);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $search_results[] = $row;
            // Log each viewed record
            auditLog($conn, 'READ', 'People', $row['People_ID'], null,
                [
                    'search_term' => $searchInput,
                    'matched_fields' => [
                        'name' => $row['People_name'],
                        'licence' => $row['People_licence']
                    ]
                ]
            );
        }

        if (empty($search_results)) {
            $message = "No results found for '" . htmlspecialchars($searchInput) . "'";
            // Log unsuccessful search
            auditLog($conn, 'READ', 'People', null, null,
                [
                    'search_term' => $searchInput,
                    'result' => 'No matches found'
                ]
            );
        }

        mysqli_stmt_close($stmt);
        mysqli_close($conn);
    } catch (Exception $e) {
        $message = "An error occurred while searching";
        error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>People Search - BTD</title>
    <link rel="icon" type="image/x-icon" href="components/favicon.ico">
    <link rel="stylesheet" href="components/style.css">
    <link rel="stylesheet" href="components/people.css">
    <script src="components/app.js" defer></script>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    
    <main>
        <div class="container">
            <center><h1>People Search</h1></center>
            
            <form method="get" class="search-form">
                <div class="search-group">
                    <input type="text" name="search_input" value="<?php echo htmlspecialchars($_GET['search_input'] ?? ''); ?>"
                           placeholder="Enter name or licence number" required autofocus>
                    <button type="submit" class="btn-search">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="currentColor">
                            <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/>
                        </svg>
                        Search
                    </button>
                </div>
            </form>

            <?php if ($message): ?>
                <p class="message"><?php echo $message; ?></p>
            <?php endif; ?>

            <?php if (!empty($search_results)): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Address</th>
                                <th>License</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($search_results as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["People_name"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["People_address"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["People_licence"]); ?></td>
                                    <td>
                                        <a href="edit_person.php?id=<?php echo $row['People_ID']; ?>" 
                                           class="btn-edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor">
                                                <path d="M180-180h44l443-443-44-44-443 443v44Zm614-486L666-794l42-42q17-17 42-17t42 17l44 44q17 17 17 42t-17 42l-42 42Zm-42 42L248-120H120v-128l504-504 128 128Zm-107-21-22-22 44 44-22-22Z"/>
                                            </svg>
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>