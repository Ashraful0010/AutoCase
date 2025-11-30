<?php
// Start the session
session_start();

// Include the database connection file
require_once 'db_connect.php';

// Initialize variables
$name = "";
$email = "";
$password = "";
$errorMessage = "";

// This block executes only when the form is submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize user input
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = htmlspecialchars(trim($_POST['password']));

    // --- Validation Rules ---
    if (empty($name)) {
        $errorMessage = "Full name is required.";
    } elseif (empty($email)) {
        $errorMessage = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } elseif (empty($password)) {
        $errorMessage = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errorMessage = "Password must be at least 8 characters long.";
    } else {
        // Check if email already exists
        $conn = getConnection();
        $sql_check = "SELECT id FROM users WHERE email = ?";

        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("s", $param_email);
            $param_email = $email;
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $errorMessage = "An account with this email already exists.";
            }
            $stmt_check->close();
        } else {
            $errorMessage = "Database error during email check.";
        }
        $conn->close();

        // If no error so far, proceed with registration
        if (empty($errorMessage)) {
            // Hash the password for security
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $conn = getConnection();
            // Prepare an insert statement
            $sql_insert = "INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)";

            if ($stmt_insert = $conn->prepare($sql_insert)) {
                $stmt_insert->bind_param("sss", $param_name, $param_email, $param_password_hash);

                // Set parameters
                $param_name = $name;
                $param_email = $email;
                $param_password_hash = $hashedPassword;

                // Attempt to execute the prepared statement
                if ($stmt_insert->execute()) {
                    // Set a success message and redirect to the login page
                    $_SESSION['success_message'] = "Registration successful! Please log in.";
                    header("Location: login.php");
                    exit();
                } else {
                    $errorMessage = "Something went wrong. Please try again later. (" . $conn->error . ")";
                }

                // Close statement
                $stmt_insert->close();
            } else {
                $errorMessage = "Database error: Could not prepare insert statement.";
            }
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AI Test Case Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #5b95d0ff;
        }
    </style>
</head>

<body>
    <div class="flex items-center justify-center min-h-screen">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Create your Account</h1>
                <p class="text-gray-500">Join AutoCase to automate your testing workflow.</p>
            </div>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" id="name" name="name" required placeholder="John Doe"
                        value="<?php echo $name; ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="you@example.com"
                        value="<?php echo $email; ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                </div>

                <?php if (!empty($errorMessage)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4"
                        role="alert">
                        <span class="block sm:inline"><?php echo $errorMessage; ?></span>
                    </div>
                <?php endif; ?>

                <button type="submit"
                    class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">Create
                    Account</button>

                <p class="text-center text-sm text-gray-600 mt-6">
                    Already have an account?
                    <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">Sign in</a>
                </p>
            </form>
        </div>
    </div>
</body>

</html>
