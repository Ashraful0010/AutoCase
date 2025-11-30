<?php
// Start the session
session_start();

// Redirect if user is not logged in (now checks for user_id which is set upon DB login)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include the database connection file
require_once 'db_connect.php'; // <<< ADDED CONNECTION

// --- Configuration ---
$uploads_dir = 'uploads/';
$outputs_dir = 'outputs/';
// REVERTED to 'python' to avoid Windows 'App execution aliases' issue
$python_executable = 'python';
$python_script = 'test_case_generator.py';
// Updated allowed file extensions to include docx and doc
$allowed_extensions = ['csv', 'xlsx', 'xls', 'docx', 'doc'];

if (!is_dir($uploads_dir))
    mkdir($uploads_dir, 0777, true);
if (!is_dir($outputs_dir))
    mkdir($outputs_dir, 0777, true);

// --- Helper Functions ---

/**
 * Parses the summary sections from the Python script output.
 */
function parse_summary_from_output($output)
{
    $summaries = ['performance' => null, 'coverage' => null];
    // Regex to capture the performance summary block
    if (preg_match('/(📊 Model Performance Summary:.*?)(?=✅|\n\n)/s', $output, $matches)) {
        $summaries['performance'] = trim($matches[1]);
    }
    // Regex to capture the coverage summary block
    if (preg_match('/(📈 COVERAGE SUMMARY.*)/s', $output, $matches)) {
        $summaries['coverage'] = trim($matches[1]);
    }
    return $summaries;
}

/**
 * Displays a formatted error message and a link to try again.
 */
function echo_error($message)
{
    echo "<div class='message error'><strong>Error:</strong> " . htmlspecialchars($message) . "</div>";
    echo "<a href='upload.html' class='back-link'>← Try Again</a>"; // Changed index.html to upload.html
}

/**
 * Displays the results page, including download link and charts.
 */
function display_results($output, $original_name) // <<< REMOVED $conn ARG, uses getConnection() internally
{
    global $outputs_dir;
    $csv_file = $outputs_dir . 'generated_test_cases.csv';
    $chart1 = $outputs_dir . 'category_distribution.png';
    $chart2 = $outputs_dir . 'priority_distribution.png';
    $chart3 = $outputs_dir . 'coverage_chart.png';

    if (file_exists($csv_file)) {
        $summaries = parse_summary_from_output($output);

        // ⭐ DATABASE/SESSION UPDATE LOGIC ⭐
        $generated_count = 0;
        // Parse the total test case count from the Python script's delimited output
        if (preg_match('/---TEST_CASE_COUNT_DELIMITER---\s*(\d+)/', $output, $matches)) {
            $generated_count = (int) $matches[1];

            // Update Database (Permanent Record)
            $user_id = $_SESSION['user_id'];

            $conn = getConnection(); // <<< GET NEW CONNECTION

            // Add the new count to the existing total count in the database
            $sql = "UPDATE users SET test_case_count = test_case_count + ? WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $generated_count, $user_id);
                $stmt->execute();
                $stmt->close();
            }
            $conn->close(); // <<< CLOSE CONNECTION

            // Update Session (Active Count)
            $_SESSION['test_case_count'] = ($_SESSION['test_case_count'] ?? 0) + $generated_count;
            $total_count = $_SESSION['test_case_count'];
        } else {
            $total_count = $_SESSION['test_case_count'] ?? 0;
        }
        // ⭐ END OF DATABASE/SESSION UPDATE LOGIC ⭐


        echo "<h1>Test Case Generation Results</h1>";
        echo "<p>Results for <strong>" . htmlspecialchars($original_name) . "</strong> have been generated successfully.</p>";
        echo "<p class='font-bold text-lg text-green-300'>✨ Generated " . number_format($generated_count) . " Test Cases (Cumulative Total: " . number_format($total_count) . ")</p>"; // Display new count

        echo "<section class='outputs-section'>";
        echo "<h2>Execution Summary</h2>";

        if (!empty($summaries['performance'])) {
            echo "<div class='summary-container'>";
            echo "<h3>Model Performance</h3>";
            echo "<pre>" . htmlspecialchars($summaries['performance']) . "</pre>";
            echo "</div>";
        }

        if (!empty($summaries['coverage'])) {
            echo "<div class='summary-container'>";
            echo "<h3>Coverage & Distribution</h3>";
            echo "<pre>" . htmlspecialchars($summaries['coverage']) . "</pre>";
            echo "</div>";
        }

        // Also display the full script output
        echo "<h3>Full Script Output:</h3>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";

        echo "</section>";

        echo "<section class='outputs-section'>";
        echo "<h2>Download Test Cases</h2>";
        echo "<a href='" . htmlspecialchars($csv_file) . "' class='download-link' download>📄 Download generated_test_cases.csv</a>";

        echo "<h2>Analysis Charts</h2>";
        echo "<div class='charts-grid'>";
        if (file_exists($chart1))
            echo "<div class='chart-container'><h3>Category Distribution</h3><img src='" . htmlspecialchars($chart1) . "?t=" . time() . "' alt='Category Chart'></div>";
        if (file_exists($chart2))
            echo "<div class='chart-container'><h3>Priority Distribution</h3><img src='" . htmlspecialchars($chart2) . "?t=" . time() . "' alt='Priority Chart'></div>";
        if (file_exists($chart3))
            echo "<div class='chart-container'><h3>Requirements Coverage</h3><img src='" . htmlspecialchars($chart3) . "?t=" . time() . "' alt='Coverage Chart'></div>";
        echo "</div></section>";

        echo "<a href='upload.html' class='back-link'>← Process Another File</a>";

    } else {
        echo_error("Python script executed, but output files were not found. Please check the script for errors.");
        echo "<h3>Script Output / Errors:</h3>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
}


// --- Main Execution Logic (remains unchanged) ---
$output = null;
$original_name = null;
$is_success = false;
$error_message = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["requirements_file"])) {
    $file = $_FILES["requirements_file"];
    $file_error = $file['error'];
    $original_name = basename($file["name"]);

    if ($file_error == UPLOAD_ERR_OK) {
        $tmp_name = $file["tmp_name"];
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_extensions)) {
            $error_message = "Invalid file type. Please upload a CSV, DOCX, DOC, or Excel file (XLS/XLSX).";
        } else {
            // Generate a safe, unique filename
            $unique_name = uniqid("REQ_") . '_' . preg_replace("/[^a-zA-Z0-9.\-_]/", "", $original_name);
            $uploaded_file_path = $uploads_dir . $unique_name;

            if (move_uploaded_file($tmp_name, $uploaded_file_path)) {
                putenv('PYTHONIOENCODING=UTF-8');

                // Construct the command using the configured executable and escaped path
                $command = escapeshellcmd($python_executable . " " . $python_script . " " . escapeshellarg($uploaded_file_path)) . " 2>&1";

                // Execute the command
                $output = shell_exec($command);

                if ($output === null) {
                    $error_message = "Python execution failed. Check server permissions or Python path (using $python_executable).";
                } else {
                    $is_success = true;
                }
            } else {
                $error_message = "Failed to move the uploaded file. Check directory permissions.";
            }
        }
    } else {
        $error_message = "File upload failed with error code: " . $file_error;
    }
} else {
    // Check for potential error if the request was POST but the file wasn't set (e.g., file too large)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $error_message = "No file uploaded or file was too large (check server limits).";
    } else {
        $error_message = "No file uploaded or invalid request.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Case Generation Results</title>

    <style>
        /* ===== Global Styling ===== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #1f2937, #111827);
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ===== Container ===== */
        .container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            max-width: 950px;
            width: 90%;
            margin-top: 40px;
            padding: 40px;
            border-radius: 18px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
        }

        /* ===== Header (Logo and Button) ===== */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            /* Added padding to separate from content */
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header img {
            width: 160px;
        }

        .header button {
            background: #3b82f6;
            border: none;
            padding: 10px 20px;
            color: #fff;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
        }

        .header button:hover {
            background: #2563eb;
            transform: scale(1.05);
        }

        /* ===== Section Titles and Content Area ===== */
        h1,
        h2,
        h3 {
            color: #f9fafb;
            margin-bottom: 10px;
        }

        h1 {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 10px;
        }

        h2 {
            color: #93c5fd;
        }

        /* ===== Links and Buttons ===== */
        .download-link,
        .back-link {
            display: inline-block;
            background-color: #22c55e;
            color: white;
            padding: 10px 20px;
            margin: 10px 0;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
        }

        .download-link:hover {
            background-color: #16a34a;
        }

        .back-link {
            background-color: #f59e0b;
        }

        .back-link:hover {
            background-color: #d97706;
        }

        /* ===== Summary Section ===== */
        .outputs-section {
            background-color: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
        }

        pre {
            background-color: rgba(0, 0, 0, 0.4);
            color: #e5e7eb;
            padding: 15px;
            border-radius: 10px;
            overflow-x: auto;
        }

        /* ===== Charts Section ===== */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .chart-container {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }

        .chart-container img {
            width: 100%;
            border-radius: 10px;
            background-color: white;
        }

        /* ===== Error Message ===== */
        .message.error {
            background-color: rgba(239, 68, 68, 0.2);
            color: #fecaca;
            padding: 15px;
            border-radius: 10px;
            text-align: left;
            font-weight: bold;
        }

        /* ===== Footer ===== */
        footer {
            margin-top: 30px;
            color: #9ca3af;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <!-- LOGO is here, at the top of the container -->
            <img src="Logo/WhiteLogo.png" alt="Tool Logo">
            <button onclick="window.location.href='home.php'">🏠 Go to Home</button>
        </div>

        <!-- DISPLAY RESULTS/ERRORS HERE (below the header) -->
        <?php
        if ($is_success) {
            display_results($output, $original_name);
        } elseif ($error_message !== null) {
            echo_error($error_message);
            // If Python execution failed, show the attempted command for debugging
            if ($output !== null) {
                echo "<h3>Script Output / Errors:</h3>";
                echo "<pre>" . htmlspecialchars($output) . "</pre>";
            }
        }
        ?>

        <footer>
            <p>© 2025 AI Test Suggestion Tool — Automated Software Testing Powered by AI</p>
        </footer>
    </div>
</body>

</html>
