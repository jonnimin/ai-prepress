<?php
header('Content-Type: application/json; charset=utf-8');
$upload = dirname(__DIR__) . '/uploads';
$output = dirname(__DIR__) . '/outputs';
echo json_encode([
  'success' => true,
  'php_version' => PHP_VERSION,
  'imagick_loaded' => extension_loaded('imagick'),
  'uploads_writable' => is_writable($upload),
  'outputs_writable' => is_writable($output),
  'upload_dir' => $upload,
  'output_dir' => $output,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
