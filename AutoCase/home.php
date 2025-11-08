<?php
// Start the session
session_start();

// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Get user data from session and sanitize for display
$userName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User';
$userEmail = htmlspecialchars($_SESSION['user_email']);

// Get the test case count from the session, defaulting to 0 if not set
$testCaseCount = $_SESSION['test_case_count'] ?? 0;

// Create initials from the user's name for the avatar
$userInitials = '';
$nameParts = explode(' ', $userName);
if (count($nameParts) > 1) {
    $userInitials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
} else {
    $userInitials = strtoupper(substr($userName, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AutoCase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .sidebar {
            transition: transform 0.3s ease-in-out;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background-color: #2563eb;
            color: white;
        }

        .sidebar-link.active svg,
        .sidebar-link:hover svg {
            color: white;
        }

        .file-drop-zone {
            background-image: url("data:image/svg+xml,%3csvg width='100%25' height='100%25' xmlns='http://www.w3.org/2000/svg'%3e%3crect width='100%25' height='100%25' fill='none' rx='12' ry='12' stroke='%23d1d5db' stroke-width='2' stroke-dasharray='6%2c 10'/%3e%3c/svg%3e");
        }

        .file-drop-zone:hover {
            background-image: url("data:image/svg+xml,%3csvg width='100%25' height='100%25' xmlns='http://www.w3.org/2000/svg'%3e%3crect width='100%25' height='100%25' fill='none' rx='12' ry='12' stroke='%233b82f6' stroke-width='2' stroke-dasharray='6%2c 10'/%3e%3c/svg%3e");
        }

        .btn-primary {
            background-image: linear-gradient(to right, #3b82f6, #60a5fa);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px 0 rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px 0 rgba(59, 130, 246, 0.4);
        }

        .logo {
            height: 40px;
            width: 40px;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen bg-gray-100">
        <!-- Sidebar -->
        <aside class="sidebar w-64 flex-shrink-0 bg-white border-r flex flex-col">
            <div class="h-20 flex items-center justify-center border-b">
                <div class="flex items-center space-x-3">
                    <img src="Logo/BlackLogo.png" alt="AutoCorrect Logo" class="logo">
                    <span class="text-xl font-bold text-gray-800">AutoCase</span>
                </div>
            </div>
            <nav class="flex-1 px-4 py-6 space-y-2">
                <a href="#"
                    class="sidebar-link active flex items-center px-4 py-2.5 rounded-lg text-sm font-medium"><svg
                        class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>Dashboard</a>
                <a href="#"
                    class="sidebar-link flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-gray-600"><svg
                        class="w-5 h-5 mr-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>History</a>
                <a href="#"
                    class="sidebar-link flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-gray-600"><svg
                        class="w-5 h-5 mr-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0 3.35a1.724 1.724 0 001.066 2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.096 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>Settings</a>
            </nav>
            <div class="px-4 py-6 border-t">
                <div class="flex items-center space-x-3">
                    <img class="h-10 w-10 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold"
                        src="https://placehold.co/256x256/E2E8F0/475569?text=<?php echo $userInitials; ?>"
                        alt="User avatar">
                    <div>
                        <p class="text-sm font-semibold text-gray-800"><?php echo $userName; ?></p>
                        <a href="logout.php" class="text-xs text-red-500 hover:underline">Logout</a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="h-20 flex items-center justify-between px-8 border-b bg-white">
                <h1 class="text-xl font-bold text-gray-800">Dashboard</h1>
            </header>

            <!-- Updated Main Section -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                    <!-- Left Section -->
                    <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-md">
                        <h2 class="text-lg font-bold text-gray-800 mb-4">Welcome,
                            <?php echo explode(' ', $userName)[0]; ?>!
                        </h2>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg">
                                <div>
                                    <p class="text-sm text-gray-500">Plan</p>
                                    <p class="text-base font-bold text-blue-800">Pro Version</p>
                                </div>
                                <div class="text-blue-500">
                                    <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 18.333A9.333 9.333 0 102.667 9a9.333 9.333 0 009.333 9.333zM12 5v7l4.667 2.333" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex justify-between items-center p-4 bg-green-50 rounded-lg">
                                <div>
                                    <p class="text-sm text-gray-500">Test Cases Generated</p>
                                    <p class="text-base font-bold text-green-800">
                                        <?php echo number_format($testCaseCount); ?>
                                    </p>
                                </div>
                                <div class="text-green-500">
                                    <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Section -->
                    <div
                        class="lg:col-span-2 bg-white p-8 rounded-xl shadow-md flex flex-col items-center justify-center text-center">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">
                            Click the button to proceed to Generate new test case.
                        </h2>
                        <a href="upload.html"
                            class="btn-primary inline-flex items-center justify-center px-6 py-3 text-white font-semibold rounded-lg shadow-md hover:scale-105 transition-transform duration-300">
                            Generate new Test case
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const style = document.createElement('style');
        style.innerHTML = `@keyframes spin { to { transform: rotate(360deg); } }`;
        document.head.appendChild(style);
    </script>
</body>

</html>