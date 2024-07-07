<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tailwind CSS Generator</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body.dark-mode {
            background-color: #121212;
            color: #e0e0e0;
        }
        .dark-mode .console {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        .console {
            background-color: #f5f5f5;
            color: #000;
            height: 300px;
            overflow-y: scroll;
            padding: 10px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="dark-mode">
    <div class="container mt-5">
        <div class="logo">FLD.WTF</div>
        <h1 class="mb-4">Tailwind CSS Generator</h1>
        <button id="mode-toggle" class="btn btn-light mb-4">Toggle Dark Mode</button>
        <button id="install-btn" class="btn btn-primary mb-4">Install Tailwind CSS</button>
        <button id="generate-btn" class="btn btn-success mb-4">Generate CSS</button>
        <div id="console" class="console"></div>
    </div>

    <script>
        document.getElementById('mode-toggle').addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
        });

        document.getElementById('install-btn').addEventListener('click', function() {
            runCommand('install');
        });

        document.getElementById('generate-btn').addEventListener('click', function() {
            runCommand('generate');
        });

        function runCommand(action) {
            const consoleElement = document.getElementById('console');
            consoleElement.innerHTML = ''; // Clear console

            const xhr = new XMLHttpRequest();
            xhr.open('GET', `generate-tailwind.php?action=${action}`, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            consoleElement.innerHTML = response.output.join('<br>');
                            if (response.success) {
                                consoleElement.innerHTML += '<br>Operation completed successfully.';
                            } else {
                                consoleElement.innerHTML += '<br>Operation failed.';
                                consoleElement.innerHTML += `<br>Suggestions: ${response.suggestions.join('<br>')}`;
                            }
                        } catch (e) {
                            consoleElement.innerHTML = 'Error parsing JSON response: ' + e.message + '<br>Response: ' + xhr.responseText;
                        }
                    } else {
                        consoleElement.innerHTML = 'Error: ' + xhr.statusText;
                    }
                    consoleElement.scrollTop = consoleElement.scrollHeight; // Scroll to bottom
                }
            };
            xhr.send();
        }
    </script>
</body>
</html>

<?php
function executeCommand($command, $errorMessage, $suggestion) {
    global $output, $success, $suggestions;
    $cmdOutput = shell_exec($command . ' 2>&1');
    if ($cmdOutput === null) {
        $success = false;
        $output[] = $errorMessage;
        $suggestions[] = $suggestion;
    } else {
        $output = array_merge($output, explode("\n", $cmdOutput));
    }
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $output = [];
    $success = true;
    $suggestions = [];

    ob_start(); // Start output buffering

    switch ($action) {
        case 'install':
            // Prüfe, ob npm installiert ist
            $npmCheck = shell_exec('npm -v');
            if ($npmCheck === null) {
                $output[] = 'npm is not installed.';
                $suggestions[] = 'Please install npm from https://www.npmjs.com/get-npm';
                $success = false;
                break;
            } else {
                $output[] = 'npm is already installed: ' . $npmCheck;
            }

            // Prüfe, ob npx installiert ist
            $npxCheck = shell_exec('npx -v');
            if ($npxCheck === null) {
                executeCommand('npm install -g npx', 'Error installing npx.', 'Ensure npm is installed and try again.');
            } else {
                $output[] = 'npx is already installed: ' . $npxCheck;
            }

            if (!file_exists('package.json')) {
                executeCommand('npm init -y', 'Error initializing npm.', 'Ensure npm is installed and try again.');
            } else {
                $output[] = 'package.json already exists. Skipping npm init.';
            }
            executeCommand('npm install tailwindcss', 'Error installing Tailwind CSS.', 'Ensure npm is installed and try again.');
            if (!file_exists('tailwind.config.js')) {
                executeCommand('npx tailwindcss init', 'Error initializing Tailwind CSS config.', 'Ensure npx is installed and try again.');
            } else {
                $output[] = 'tailwind.config.js already exists. Skipping Tailwind CSS init.';
            }

            // Basiskonfiguration erstellen, falls noch nicht vorhanden
            $tailwindConfig = 'tailwind.config.js';
            if (!file_exists($tailwindConfig)) {
                $configContent = "module.exports = {
                    content: ['./tailwind-new/**/*.{html,php}'],
                    theme: {
                        extend: {},
                    },
                    plugins: [],
                }";
                if (file_put_contents($tailwindConfig, $configContent) === false) {
                    $output[] = 'Error creating Tailwind CSS configuration file.';
                    $suggestions[] = 'Ensure PHP has write permissions to the directory.';
                    $success = false;
                }
            } else {
                $output[] = 'Tailwind CSS configuration file already exists.';
            }
            break;
        
        case 'generate':
            $tailwindCSSContent = "@tailwind base;\n@tailwind components;\n@tailwind utilities;";
            if (file_put_contents('tailwind.css', $tailwindCSSContent) === false) {
                $output[] = 'Error creating tailwind.css file.';
                $suggestions[] = 'Ensure PHP has write permissions to the directory.';
                $success = false;
            } else {
                executeCommand('npx tailwindcss -i tailwind.css -o tailwind-new/tailwind-output.css', 'Error generating Tailwind CSS output.', 'Ensure npx is installed and the command is correct.');
            }
            break;
        
        default:
            $output[] = 'Invalid action specified.';
            $suggestions[] = 'Specify a valid action: install or generate.';
            $success = false;
            break;
    }

    ob_end_clean(); // End output buffering and discard the buffer contents

    echo json_encode(['success' => $success, 'output' => $output, 'suggestions' => $suggestions]);
    exit;
}
?>
