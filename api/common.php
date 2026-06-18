<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('memory_limit', '768M');
set_time_limit(120);

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('OUTPUT_DIR', APP_ROOT . '/outputs');
define('ICC_DIR', APP_ROOT . '/icc');
define('MAX_FILE_SIZE', 80 * 1024 * 1024);

if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0755, true); }
if (!is_dir(OUTPUT_DIR)) { mkdir(OUTPUT_DIR, 0755, true); }

function json_response(bool $success, string $message, array $extra = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success'=>$success,'message'=>$message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $message, array $extra = []): void { json_response(false, $message, $extra); }
function ensure_imagick(): void { if (!extension_loaded('imagick')) { fail('伺服器尚未安裝 PHP Imagick 擴充。'); } }
function get_upload(string $field = 'file'): array {
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) { fail('請上傳檔案。'); }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { fail('檔案上傳失敗，錯誤代碼：' . (string)$file['error']); }
    if (($file['size'] ?? 0) <= 0) { fail('檔案大小異常。'); }
    if ($file['size'] > MAX_FILE_SIZE) { fail('檔案不可超過 80MB。'); }
    return $file;
}
function file_ext(string $filename): string { return strtolower(pathinfo($filename, PATHINFO_EXTENSION)); }
function assert_ext(string $ext, array $allowed): void { if (!in_array($ext, $allowed, true)) { fail('不支援此檔案格式：' . strtoupper($ext)); } }
function make_case_no(): string { return date('YmdHis') . '_' . bin2hex(random_bytes(4)); }
function save_upload(array $file, string $caseNo, string $ext): string {
    $path = UPLOAD_DIR . '/' . $caseNo . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $path)) { fail('無法儲存上傳檔案，請檢查 uploads 權限。'); }
    return $path;
}
function public_url(string $absolutePath): string { return 'outputs/' . rawurlencode(basename($absolutePath)); }
function clean_number(float $value, float $min, float $max, float $default): float { if ($value < 0) { return $default; } return max($min, min($max, $value)); }
function mm_to_px(float $mm, int $dpi): int { return max(1, (int)round(($mm / 25.4) * $dpi)); }
function fit_cover_size(int $srcW, int $srcH, int $targetW, int $targetH): array {
    $srcRatio = $srcW / max(1, $srcH);
    $targetRatio = $targetW / max(1, $targetH);
    if ($srcRatio > $targetRatio) { return [(int)round($targetH * $srcRatio), $targetH]; }
    return [$targetW, (int)round($targetW / $srcRatio)];
}
function fit_contain_size(int $srcW, int $srcH, int $targetW, int $targetH): array {
    $scale = min($targetW / max(1, $srcW), $targetH / max(1, $srcH));
    return [max(1, (int)round($srcW * $scale)), max(1, (int)round($srcH * $scale))];
}
function make_canvas(int $w, int $h, string $color = 'white'): Imagick {
    $canvas = new Imagick();
    $canvas->newImage($w, $h, new ImagickPixel($color));
    $canvas->setImageColorspace(Imagick::COLORSPACE_RGB);
    return $canvas;
}
function load_flat_image(string $path): Imagick {
    $img = new Imagick($path);
    if ($img->getNumberImages() > 1) { $img->setIteratorIndex(0); }
    $img->setImageBackgroundColor('white');
    $img = $img->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
    $img->setImageColorspace(Imagick::COLORSPACE_RGB);
    $img->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
    return $img;
}
function write_pdf(Imagick $image, string $outputPath, int $dpi): void {
    $image->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
    $image->setImageResolution($dpi, $dpi);
    $image->setImageFormat('pdf');
    $image->writeImages($outputPath, true);
}
function draw_crop_marks(Imagick $canvas, int $trimX, int $trimY, int $trimW, int $trimH, int $markLen, int $gap, int $stroke = 2): void {
    $draw = new ImagickDraw();
    $draw->setStrokeColor(new ImagickPixel('black'));
    $draw->setStrokeWidth($stroke);
    $draw->setFillColor(new ImagickPixel('transparent'));
    $x1 = $trimX; $y1 = $trimY; $x2 = $trimX + $trimW; $y2 = $trimY + $trimH;
    $draw->line($x1 - $gap - $markLen, $y1, $x1 - $gap, $y1);
    $draw->line($x1, $y1 - $gap - $markLen, $x1, $y1 - $gap);
    $draw->line($x2 + $gap, $y1, $x2 + $gap + $markLen, $y1);
    $draw->line($x2, $y1 - $gap - $markLen, $x2, $y1 - $gap);
    $draw->line($x1 - $gap - $markLen, $y2, $x1 - $gap, $y2);
    $draw->line($x1, $y2 + $gap, $x1, $y2 + $gap + $markLen);
    $draw->line($x2 + $gap, $y2, $x2 + $gap + $markLen, $y2);
    $draw->line($x2, $y2 + $gap, $x2, $y2 + $gap + $markLen);
    $canvas->drawImage($draw);
}
function image_response(string $message, string $outputPath, array $extra = []): void {
    json_response(true, $message, array_merge(['download_url'=>public_url($outputPath),'filename'=>basename($outputPath)], $extra));
}
