<?php
// Start a session to store login state and user data
session_start();

// If user is already logged in, redirect to home page
if (isset($_SESSION['user_email'])) {
    header("Location: home.php");
    exit();
}

// Check for a registration success message
$successMessage = "";
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying it
}

// Initialize variables
$email = "";
$password = "";
$errorMessage = "";

// This block executes only when the form is submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize user input to prevent XSS
    $email = htmlspecialchars(trim($_POST['email']));
    $password = htmlspecialchars(trim($_POST['password']));

    // --- Validation Rules ---
    if (empty($email)) {
        $errorMessage = "Email address is required.";
    } elseif (empty($password)) {
        $errorMessage = "Password is required.";
    }
    // --- Authentication Logic ---
    else {
        // Check if the user exists and the password is correct
        if (isset($_SESSION['users'][$email]) && password_verify($password, $_SESSION['users'][$email]['password'])) {
            // Authentication successful!
            $_SESSION['user_name'] = $_SESSION['users'][$email]['name'];
            $_SESSION['user_email'] = $email;
            // Load the user's test case count into the active session
            $_SESSION['test_case_count'] = $_SESSION['users'][$email]['test_case_count'] ?? 0;

            header("Location: home.php");
            exit();
        } else {
            // Authentication failed
            $errorMessage = "Invalid email or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AI Test Case Generator</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8;
            overflow: hidden;
        }

        .main-container {
            display: flex;
            width: 100vw;
            height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-panel {
            display: flex;
            width: 100%;
            max-width: 1024px;
            height: auto;
            max-height: 90vh;
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .brand-section {
            width: 50%;
            padding: 4rem;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            position: relative;
        }

        .logo-container {
            margin-bottom: 1.5rem;
        }

        .logo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
        }

        .brand-section h1 {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .brand-section p {
            font-size: 1rem;
            margin-top: 1rem;
            opacity: 0.8;
            max-width: 350px;
        }

        .form-section {
            width: 50%;
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-section h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .form-section .subtitle {
            color: #6b7280;
            margin-bottom: 2.5rem;
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-group .icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .input-field {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .submit-button {
            width: 100%;
            padding: 1rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .submit-button:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .signup-link {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #4b5563;
        }

        .signup-link a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {

            .brand-section,
            .form-section {
                padding: 3rem;
            }

            .brand-section h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .login-panel {
                flex-direction: column;
                max-height: none;
                height: auto;
            }

            .brand-section,
            .form-section {
                width: 100%;
            }

            .brand-section {
                padding: 3rem 2rem;
                text-align: center;
                align-items: center;
            }

            .form-section {
                padding: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="login-panel">
            <div class="brand-section">
                <div class="logo-container">
                    <img src="Logo/WhiteLogo.png" alt="Company Logo" class="logo">
                </div>
                <h1>AI Test Case Generator</h1>
                <p>Automated Software Test Case Generation Using Natural Language Processing and Machine Learning</p>
            </div>
            <div class="form-section">
                <h2>Welcome To AutoCase</h2>
                <p class="subtitle">Please enter your details to sign in.</p>

                <!-- Display Success Message after registration -->
                <?php if (!empty($successMessage)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-6"
                        role="alert">
                        <span class="block sm:inline"><?php echo $successMessage; ?></span>
                    </div>
                <?php endif; ?>

                <form id="loginForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>"
                    novalidate>
                    <div class="input-group">
                        <span class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                        </span>
                        <input type="email" id="email" name="email" required placeholder="you@example.com"
                            value="<?php echo htmlspecialchars($email); ?>" class="input-field">
                    </div>
                    <div class="input-group">
                        <span class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" required placeholder="••••••••"
                            class="input-field">
                    </div>
                    <?php if (!empty($errorMessage)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6"
                            role="alert">
                            <span class="block sm:inline"><?php echo $errorMessage; ?></span>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="submit-button">Sign In</button>
                    <p class="signup-link">
                        Don't have an account?
                        <a href="register.php">Sign up for free</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</body>

</html>