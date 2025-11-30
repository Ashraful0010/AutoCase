<?php
// Start the session
session_start();

// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Include the database connection file
require_once 'db_connect.php';

// Get user data from session
$userName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User';
$userEmail = htmlspecialchars($_SESSION['user_email']);

// Get the test case count
$testCaseCount = $_SESSION['test_case_count'] ?? 0;

// Create initials
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            /* Very light blue-grey */
        }

        /* Sidebar & Navigation */
        .sidebar-link {
            transition: all 0.2s ease-in-out;
        }

        .sidebar-link.active {
            background-color: #2563eb;
            /* Blue-600 */
            color: white;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }

        .sidebar-link:hover:not(.active) {
            background-color: #eff6ff;
            /* Blue-50 */
            color: #1e40af;
            /* Blue-800 */
        }

        /* Buttons */
        .btn-primary {
            background-color: #2563eb;
            /* Blue-600 */
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            /* Blue-700 */
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }

        /* Custom Scrollbar for the main content */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Code snippet style */
        .code-block {
            font-family: 'Monaco', 'Consolas', monospace;
            background-color: #1e293b;
            color: #e2e8f0;
        }
    </style>
</head>

<body class="text-slate-700 antialiased">
    <div class="flex h-screen overflow-hidden">

        <aside class="w-72 bg-white border-r border-slate-200 flex flex-col hidden md:flex z-20">
            <div class="h-20 flex items-center px-8 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <img src="Logo/BlackLogo.png" alt="AutoCase Logo" class="h-8 w-auto">
                    <span class="text-xl font-bold tracking-tight text-slate-900">AutoCase</span>
                </div>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-1">
                <a href="#" class="sidebar-link active flex items-center px-4 py-3 rounded-xl text-sm font-medium">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    Dashboard
                </a>
                <a href="#"
                    class="sidebar-link flex items-center px-4 py-3 rounded-xl text-sm font-medium text-slate-500 hover:text-blue-700">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    History
                </a>
                <a href="#"
                    class="sidebar-link flex items-center px-4 py-3 rounded-xl text-sm font-medium text-slate-500 hover:text-blue-700">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0 3.35a1.724 1.724 0 001.066 2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.096 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                </a>
            </nav>

            <div class="p-4 border-t border-slate-100">
                <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-50 transition-colors">
                    <img class="h-10 w-10 rounded-full object-cover ring-2 ring-blue-100 bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs"
                        src="https://placehold.co/256x256/eff6ff/2563eb?text=<?php echo $userInitials; ?>"
                        alt="User avatar">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-900 truncate"><?php echo $userName; ?></p>
                        <p class="text-xs text-slate-500 truncate"><?php echo $userEmail; ?></p>
                    </div>
                    <a href="logout.php" class="text-slate-400 hover:text-red-500 transition-colors" title="Logout">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                    </a>
                </div>
            </div>
        </aside>

        <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50 relative">

            <header
                class="h-20 flex items-center justify-between px-8 bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-10">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Dashboard</h1>
                </div>
            </header>

            <div class="flex-1 overflow-x-hidden overflow-y-auto p-8">
                <div class="max-w-6xl mx-auto space-y-12">

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-1 space-y-6">
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                                <h2 class="text-xl font-bold text-slate-800 mb-1">
                                    Hello, <?php echo explode(' ', $userName)[0]; ?>!
                                </h2>
                                <p class="text-slate-500 text-sm">Welcome back to your workspace.</p>
                            </div>

                            <div
                                class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 relative overflow-hidden group hover:shadow-md transition-all">
                                <div
                                    class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                                    <svg class="h-24 w-24 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M12 2L2 7l10 5 10-5-10-5zm0 9l2.5-1.25L12 8.5l-2.5 1.25L12 11zm0 2.5l-5-2.5-5 2.5L12 22l10-8.5-5-2.5-5 2.5z" />
                                    </svg>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-500">Current Plan</p>
                                        <p class="text-lg font-bold text-slate-900">Pro Version</p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 hover:shadow-md transition-all">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 bg-green-50 text-green-600 rounded-xl">
                                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-500">Total Test Cases</p>
                                        <p class="text-2xl font-bold text-slate-900">
                                            <?php echo number_format($testCaseCount); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-2">
                            <div
                                class="bg-white rounded-2xl shadow-sm border border-slate-200 h-full flex flex-col items-center justify-center p-12 text-center relative overflow-hidden">
                                <div class="relative z-10 max-w-md">
                                    <div
                                        class="mb-6 inline-flex p-4 bg-blue-50 text-blue-600 rounded-full ring-8 ring-blue-50/50">
                                        <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                    </div>
                                    <h2 class="text-2xl font-bold text-slate-900 mb-3">Generate New Test Cases</h2>
                                    <p class="text-slate-500 mb-8 leading-relaxed">
                                        Upload your requirements document and let our AI generate comprehensive test
                                        scenarios, steps, and expected results instantly.
                                    </p>
                                    <a href="upload.html"
                                        class="btn-primary inline-flex items-center justify-center px-8 py-4 text-white font-semibold rounded-xl shadow-lg shadow-blue-200 hover:shadow-blue-300 text-base">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Start Generation
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-10">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="h-8 w-1 bg-blue-600 rounded-full"></div>
                            <h3 class="text-2xl font-bold text-slate-800">User Guide & Documentation</h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                            <div
                                class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:border-blue-200 transition-colors">
                                <span
                                    class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-blue-100 text-blue-600 font-bold mb-4">1</span>
                                <h4 class="text-lg font-bold text-slate-900 mb-2">Prepare</h4>
                                <p class="text-slate-500 text-sm leading-relaxed">
                                    Create a requirement file (Text or Docx). Ensure every requirement has a unique ID
                                    (e.g., R1, R2).
                                </p>
                            </div>
                            <div
                                class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:border-blue-200 transition-colors">
                                <span
                                    class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-blue-100 text-blue-600 font-bold mb-4">2</span>
                                <h4 class="text-lg font-bold text-slate-900 mb-2">Upload</h4>
                                <p class="text-slate-500 text-sm leading-relaxed">
                                    Click "Generate New Test Case" above. Drag and drop your file. The system processes
                                    the input immediately.
                                </p>
                            </div>
                            <div
                                class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:border-blue-200 transition-colors">
                                <span
                                    class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-blue-100 text-blue-600 font-bold mb-4">3</span>
                                <h4 class="text-lg font-bold text-slate-900 mb-2">Result</h4>
                                <p class="text-slate-500 text-sm leading-relaxed">
                                    Download your Excel file. It contains Test IDs, Descriptions, Pre-conditions, Steps,
                                    and Expected Results.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">

                            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                                <div class="bg-slate-50 px-6 py-4 border-b border-slate-100">
                                    <h4 class="font-bold text-slate-800">Standard Requirement Format</h4>
                                </div>
                                <div class="p-6">
                                    <p class="text-sm text-slate-600 mb-4">For the best results, structure your input
                                        file using the <strong>ID: Statement</strong> format.</p>

                                    <div class="code-block p-4 rounded-lg text-sm mb-4">
                                        R1: Verify that a user can log in with valid credentials.<br>
                                        R2: Verify that the system locks the account after 3 failed attempts.<br>
                                        R3: Check that the 'Forgot Password' link sends a recovery email.
                                    </div>

                                    <div
                                        class="text-xs text-slate-500 bg-blue-50 p-3 rounded-lg border border-blue-100 flex gap-2">
                                        <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span><strong>Pro Tip:</strong> Keep requirements atomic. Don't combine multiple
                                            logic flows (e.g., Login AND Signup) in a single line.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                                <div class="bg-slate-50 px-6 py-4 border-b border-slate-100">
                                    <h4 class="font-bold text-slate-800">Writing Requirements: Do's & Don'ts</h4>
                                </div>
                                <div class="p-0">
                                    <table class="w-full text-sm text-left">
                                        <thead class="bg-slate-50 text-slate-500 font-medium border-b border-slate-100">
                                            <tr>
                                                <th class="px-6 py-3 w-1/2">❌ Bad Example</th>
                                                <th class="px-6 py-3 w-1/2">✅ Good Example</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr>
                                                <td class="px-6 py-4 text-slate-500">"Login page check." <br><span
                                                        class="text-xs text-red-500">(Too vague)</span></td>
                                                <td class="px-6 py-4 text-slate-800">"User enters valid email/pass and
                                                    clicks Login."</td>
                                            </tr>
                                            <tr>
                                                <td class="px-6 py-4 text-slate-500">"Make sure the cart works and
                                                    payment works." <br><span class="text-xs text-red-500">(Multiple
                                                        actions)</span></td>
                                                <td class="px-6 py-4 text-slate-800">"Verify item is added to cart when
                                                    'Add' is clicked."</td>
                                            </tr>
                                            <tr>
                                                <td class="px-6 py-4 text-slate-500">"System should be fast." <br><span
                                                        class="text-xs text-red-500">(Not measurable)</span></td>
                                                <td class="px-6 py-4 text-slate-800">"Page load time must be under 2
                                                    seconds."</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-900 rounded-2xl p-8 text-white relative overflow-hidden">
                            <div
                                class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 rounded-full bg-blue-800 opacity-50 blur-3xl">
                            </div>

                            <h4 class="text-xl font-bold mb-6 relative z-10">Understanding Your Output</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 relative z-10">
                                <div>
                                    <div class="text-blue-300 text-xs font-bold uppercase tracking-wider mb-2">Column 1
                                    </div>
                                    <h5 class="font-bold text-white mb-2">Test Case ID</h5>
                                    <p class="text-blue-100 text-sm">Unique identifier (e.g., TC001) linked to your
                                        original requirement ID.</p>
                                </div>
                                <div>
                                    <div class="text-blue-300 text-xs font-bold uppercase tracking-wider mb-2">Column 2
                                    </div>
                                    <h5 class="font-bold text-white mb-2">Test Steps</h5>
                                    <p class="text-blue-100 text-sm">Actionable, step-by-step instructions for the
                                        tester to perform.</p>
                                </div>
                                <div>
                                    <div class="text-blue-300 text-xs font-bold uppercase tracking-wider mb-2">Column 3
                                    </div>
                                    <h5 class="font-bold text-white mb-2">Expected Result</h5>
                                    <p class="text-blue-100 text-sm">What the system <em>should</em> do if the feature
                                        works correctly.</p>
                                </div>
                                <div>
                                    <div class="text-blue-300 text-xs font-bold uppercase tracking-wider mb-2">Quality
                                        Tip</div>
                                    <h5 class="font-bold text-white mb-2">Reducing Repetition</h5>
                                    <p class="text-blue-100 text-sm">If results seem repetitive, try splitting complex
                                        requirements into smaller, distinct lines.</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
