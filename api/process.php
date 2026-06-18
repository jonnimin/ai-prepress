<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('memory_limit', '768M');
set_time_limit(180);

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('OUTPUT_DIR', APP_ROOT . '/outputs');
define('MAX_FILE_SIZE', 80 * 1024 * 1024);

ensure_dir(UPLOAD_DIR);
ensure_dir(OUTPUT_DIR);

function ensure_dir(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!@mkdir($path, 0755, true) && !is_dir($path)) {
        fail('無法建立資料夾：' . $path);
    }
}

function ok(string $message, string $outputPath, array $extra = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success' => true,
        'message' => $message,
        'download_url' => 'outputs/' . rawurlencode(basename($outputPath)),
        'filename' => basename($outputPath),
        'bytes' => is_file($outputPath) ? filesize($outputPath) : 0,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, array $extra = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success' => false,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_imagick(): void
{
    if (!extension_loaded('imagick') || !class_exists('Imagick')) {
        fail('伺服器尚未啟用 PHP Imagick 擴充。');
    }
}

function upload(string $field = 'file'): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        fail('請先上傳檔案。');
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        fail('檔案上傳失敗，錯誤代碼：' . (string)($file['error'] ?? -1));
    }
    if (($file['size'] ?? 0) <= 0) {
        fail('檔案大小無效。');
    }
    if (($file['size'] ?? 0) > MAX_FILE_SIZE) {
        fail('檔案大小超過 80MB。');
    }

    return $file;
}

function has_upload(string $field): bool
{
    return !empty($_FILES[$field]) && (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK);
}

function ext(string $name): string
{
    return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

function assert_ext(string $extension, array $allowed): void
{
    if (!in_array($extension, $allowed, true)) {
        fail('不支援的檔案格式：' . strtoupper($extension));
    }
}

function case_no(): string
{
    return date('YmdHis') . '_' . bin2hex(random_bytes(4));
}

function save_upload(array $file, string $caseNo, string $extension): string
{
    $path = UPLOAD_DIR . '/' . $caseNo . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        fail('儲存上傳檔案失敗。');
    }
    return $path;
}

function num(string $key, float $default, float $min, float $max): float
{
    $value = isset($_POST[$key]) ? (float)$_POST[$key] : $default;
    if (!is_finite($value) || $value < 0) {
        $value = $default;
    }
    return max($min, min($max, $value));
}

function mm_px(float $mm, int $dpi): int
{
    return max(1, (int)round(($mm / 25.4) * $dpi));
}

function output_url(string $absolutePath): string
{
    return 'outputs/' . rawurlencode(basename($absolutePath));
}

function ensure_pdf_support(): void
{
    require_imagick();
    $formats = array_map('strtoupper', Imagick::queryFormats('PDF'));
    if (!in_array('PDF', $formats, true)) {
        fail('目前伺服器的 Imagick 沒有 PDF 輸出能力。');
    }
}

function load_source_image(string $path): Imagick
{
    require_imagick();

    $image = new Imagick();
    $image->readImage($path);

    if ($image->getNumberImages() > 1) {
        $image->setIteratorIndex(0);
        $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
    }

    if (method_exists($image, 'autoOrientImage')) {
        $image->autoOrientImage();
    }

    $image->setImageBackgroundColor(new ImagickPixel('white'));
    $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
    $image->setImageColorspace(Imagick::COLORSPACE_RGB);

    return $image;
}

function make_canvas(int $w, int $h, string $color = 'white'): Imagick
{
    $canvas = new Imagick();
    $canvas->newImage($w, $h, new ImagickPixel($color));
    $canvas->setImageColorspace(Imagick::COLORSPACE_RGB);
    return $canvas;
}

function resize_cover(Imagick $image, int $targetW, int $targetH): Imagick
{
    $srcW = max(1, $image->getImageWidth());
    $srcH = max(1, $image->getImageHeight());
    $scale = max($targetW / $srcW, $targetH / $srcH);
    $newW = max(1, (int)round($srcW * $scale));
    $newH = max(1, (int)round($srcH * $scale));

    $work = clone $image;
    $work->resizeImage($newW, $newH, Imagick::FILTER_LANCZOS, 1, false);

    $cropX = max(0, (int)floor(($newW - $targetW) / 2));
    $cropY = max(0, (int)floor(($newH - $targetH) / 2));
    $work->cropImage($targetW, $targetH, $cropX, $cropY);
    $work->setImagePage(0, 0, 0, 0);

    return $work;
}

function resize_contain(Imagick $image, int $targetW, int $targetH): Imagick
{
    $srcW = max(1, $image->getImageWidth());
    $srcH = max(1, $image->getImageHeight());
    $scale = min($targetW / $srcW, $targetH / $srcH);
    $newW = max(1, (int)round($srcW * $scale));
    $newH = max(1, (int)round($srcH * $scale));

    $work = clone $image;
    $work->resizeImage($newW, $newH, Imagick::FILTER_LANCZOS, 1, false);

    if ($newW !== $targetW || $newH !== $targetH) {
        $canvas = make_canvas($targetW, $targetH);
        $offsetX = (int)floor(($targetW - $newW) / 2);
        $offsetY = (int)floor(($targetH - $newH) / 2);
        $canvas->compositeImage($work, Imagick::COMPOSITE_OVER, $offsetX, $offsetY);
        $work = $canvas;
    }

    $work->setImagePage(0, 0, 0, 0);
    return $work;
}

function save_pdf(Imagick $image, string $outputPath, int $dpi): void
{
    ensure_pdf_support();
    $image->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
    $image->setImageResolution($dpi, $dpi);
    $image->setImageFormat('pdf');
    $image->writeImages($outputPath, true);
}

function save_jpeg(Imagick $image, string $outputPath, int $quality = 92): void
{
    $image->setImageBackgroundColor(new ImagickPixel('white'));
    $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
    $image->setImageFormat('jpeg');
    $image->setImageCompressionQuality($quality);
    $image->writeImage($outputPath);
}

function require_fpdf(): void
{
    if (class_exists('FPDF')) {
        return;
    }

    foreach ([
        '/usr/share/php/fpdf/fpdf.php',
        '/usr/share/php/FPDF/fpdf.php',
        '/usr/share/php/fpdf.php',
    ] as $candidate) {
        if (is_file($candidate)) {
            require_once $candidate;
            if (class_exists('FPDF')) {
                return;
            }
        }
    }

    fail('伺服器尚未安裝 FPDF，無法產生真正的向量裁切線 PDF。');
}

function create_print_bitmap(string $inputPath, int $canvasW, int $canvasH): string
{
    $source = load_source_image($inputPath);
    $art = resize_cover($source, $canvasW, $canvasH);
    $art->setImageBackgroundColor(new ImagickPixel('white'));
    $art->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
    $art->setImageColorspace(Imagick::COLORSPACE_RGB);
    $art->setImageFormat('jpeg');
    $art->setImageCompressionQuality(92);

    $tmp = tempnam(sys_get_temp_dir(), 'ai_print_');
    if ($tmp === false) {
        fail('無法建立暫存檔。');
    }

    $jpgPath = $tmp . '.jpg';
    if (!@rename($tmp, $jpgPath)) {
        @unlink($tmp);
        fail('無法建立圖片暫存檔。');
    }

    $art->writeImage($jpgPath);
    $source->clear();
    $art->clear();

    return $jpgPath;
}

function draw_crop_marks_pdf(FPDF $pdf, array $meta): void
{
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);

    $x1 = (float)$meta['trim_x_mm'];
    $y1 = (float)$meta['trim_y_mm'];
    $x2 = $x1 + (float)$meta['trim_w_mm'];
    $y2 = $y1 + (float)$meta['trim_h_mm'];
    $markLen = 5.0;
    $gap = 2.0;

    $pdf->Line($x1 - $gap - $markLen, $y1, $x1 - $gap, $y1);
    $pdf->Line($x1, $y1 - $gap - $markLen, $x1, $y1 - $gap);
    $pdf->Line($x2 + $gap, $y1, $x2 + $gap + $markLen, $y1);
    $pdf->Line($x2, $y1 - $gap - $markLen, $x2, $y1 - $gap);
    $pdf->Line($x1 - $gap - $markLen, $y2, $x1 - $gap, $y2);
    $pdf->Line($x1, $y2 + $gap, $x1, $y2 + $gap + $markLen);
    $pdf->Line($x2 + $gap, $y2, $x2 + $gap + $markLen, $y2);
    $pdf->Line($x2, $y2 + $gap, $x2, $y2 + $gap + $markLen);
}

function render_print_page_pdf(FPDF $pdf, string $inputPath, bool $bleed, bool $crop): array
{
    $widthMm = num('width_mm', 90, 5, 2000);
    $heightMm = num('height_mm', 54, 5, 2000);
    $bleedMm = $bleed ? num('bleed_mm', 2, 0, 20) : 0;
    $dpi = (int)num('dpi', 300, 72, 600);

    $trimW = mm_px($widthMm, $dpi);
    $trimH = mm_px($heightMm, $dpi);
    $bleedPx = mm_px($bleedMm, $dpi);
    $canvasW = $trimW + ($bleedPx * 2);
    $canvasH = $trimH + ($bleedPx * 2);
    $markSpaceMm = $crop ? 8.0 : 0.0;
    $pageW = $widthMm + ($bleedMm * 2) + ($markSpaceMm * 2);
    $pageH = $heightMm + ($bleedMm * 2) + ($markSpaceMm * 2);
    $trimX = $markSpaceMm + $bleedMm;
    $trimY = $markSpaceMm + $bleedMm;
    $orientation = $pageW >= $pageH ? 'L' : 'P';

    $bitmap = create_print_bitmap($inputPath, $canvasW, $canvasH);
    $pdf->AddPage($orientation, [$pageW, $pageH]);
    $pdf->Image($bitmap, $markSpaceMm, $markSpaceMm, $widthMm + ($bleedMm * 2), $heightMm + ($bleedMm * 2), 'JPG');

    if ($crop) {
        draw_crop_marks_pdf($pdf, [
            'trim_x_mm' => $trimX,
            'trim_y_mm' => $trimY,
            'trim_w_mm' => $widthMm,
            'trim_h_mm' => $heightMm,
        ]);
    }

    return [
        $bitmap,
        [
            'trim_size' => $widthMm . ' x ' . $heightMm . ' mm',
            'bleed_size' => ($widthMm + ($bleedMm * 2)) . ' x ' . ($heightMm + ($bleedMm * 2)) . ' mm',
            'dpi' => $dpi,
            'page_w_mm' => $pageW,
            'page_h_mm' => $pageH,
            'canvas_w_px' => $canvasW,
            'canvas_h_px' => $canvasH,
            'mark_space_mm' => $markSpaceMm,
            'trim_x_mm' => $trimX,
            'trim_y_mm' => $trimY,
            'trim_w_mm' => $widthMm,
            'trim_h_mm' => $heightMm,
        ],
    ];
}

function draw_crop_marks(Imagick $canvas, int $trimX, int $trimY, int $trimW, int $trimH, int $markLen, int $gap, int $stroke = 2): void
{
    $draw = new ImagickDraw();
    $draw->setStrokeColor(new ImagickPixel('black'));
    $draw->setStrokeWidth($stroke);
    $draw->setFillColor(new ImagickPixel('transparent'));

    $x1 = $trimX;
    $y1 = $trimY;
    $x2 = $trimX + $trimW;
    $y2 = $trimY + $trimH;

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

function build_print_page(string $inputPath, bool $bleed, bool $crop): array
{
    $widthMm = num('width_mm', 90, 5, 2000);
    $heightMm = num('height_mm', 54, 5, 2000);
    $bleedMm = $bleed ? num('bleed_mm', 2, 0, 20) : 0;
    $dpi = (int)num('dpi', 300, 72, 600);

    $trimW = mm_px($widthMm, $dpi);
    $trimH = mm_px($heightMm, $dpi);
    $bleedPx = mm_px($bleedMm, $dpi);
    $canvasW = $trimW + ($bleedPx * 2);
    $canvasH = $trimH + ($bleedPx * 2);
    $markSpace = $crop ? mm_px(8, $dpi) : 0;
    $pageW = $canvasW + ($markSpace * 2);
    $pageH = $canvasH + ($markSpace * 2);
    $trimX = $markSpace + $bleedPx;
    $trimY = $markSpace + $bleedPx;

    $source = load_source_image($inputPath);
    $art = resize_cover($source, $canvasW, $canvasH);
    $page = make_canvas($pageW, $pageH);
    $page->compositeImage($art, Imagick::COMPOSITE_OVER, $markSpace, $markSpace);

    if ($crop) {
        $stroke = max(1, (int)round($dpi / 180));
        $markLen = mm_px(5, $dpi);
        $gap = mm_px(2, $dpi);
        draw_crop_marks($page, $trimX, $trimY, $trimW, $trimH, $markLen, $gap, $stroke);
    }

    $page->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
    $page->setImageResolution($dpi, $dpi);
    $page->setImagePage(0, 0, 0, 0);

    return [
        $page,
        [
            'trim_size' => $widthMm . ' x ' . $heightMm . ' mm',
            'bleed_size' => ($widthMm + ($bleedMm * 2)) . ' x ' . ($heightMm + ($bleedMm * 2)) . ' mm',
            'dpi' => $dpi,
        ],
    ];
}

function merge_pdf_pages(array $pdfPaths, string $outputPath): void
{
    ensure_pdf_support();

    $doc = new Imagick();
    foreach ($pdfPaths as $pdfPath) {
        $doc->readImage($pdfPath);
    }
    $doc->setImageFormat('pdf');
    $doc->writeImages($outputPath, true);
}

function create_gray_plate(string $inputPath, int $widthMm, int $heightMm, int $dpi, string $outputPath): void
{
    $trimW = mm_px($widthMm, $dpi);
    $trimH = mm_px($heightMm, $dpi);

    $source = load_source_image($inputPath);
    $art = resize_cover($source, $trimW, $trimH);
    $art->setImageColorspace(Imagick::COLORSPACE_GRAY);

    $range = Imagick::getQuantumRange();
    $quantum = isset($range['quantumRangeLong']) ? (int)$range['quantumRangeLong'] : 65535;
    $art->thresholdImage((int)round($quantum * 0.92));
    $art->negateImage(false);
    $art->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
    $art->setImageResolution($dpi, $dpi);
    $art->setImageFormat('pdf');
    $art->writeImages($outputPath, true);
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'convert_pdf':
            convert_pdf();
            break;
        case 'add_bleed':
            add_bleed(false);
            break;
        case 'add_crop_marks':
            crop_marks();
            break;
        case 'bleed_crop':
            add_bleed(true);
            break;
        case 'print_ready':
            print_ready();
            break;
        case 'upscale':
            upscale();
            break;
        case 'enhance_pdf':
            enhance();
            break;
        case 'convert_cmyk':
            cmyk();
            break;
        case 'coating_plate':
            coating();
            break;
        default:
            fail('不支援的動作：' . $action);
    }
} catch (Throwable $e) {
    fail($e->getMessage());
}

function convert_pdf(): void
{
    $file = upload();
    $extension = ext($file['name']);
    assert_ext($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

    $case = case_no();
    $input = save_upload($file, $case, $extension);
    $output = OUTPUT_DIR . '/' . $case . '_print.pdf';

    $image = load_source_image($input);
    $dpi = (int)num('dpi', 300, 72, 600);
    save_pdf($image, $output, $dpi);

    ok('PDF 已產生。', $output, [
        'action' => 'convert_pdf',
    ]);
}

function add_bleed(bool $crop): void
{
    $file = upload();
    $extension = ext($file['name']);
    assert_ext($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

    $case = case_no();
    $input = save_upload($file, $case, $extension);
    $output = OUTPUT_DIR . '/' . $case . ($crop ? '_bleed_crop.pdf' : '_bleed.pdf');

    require_fpdf();
    $pdf = new FPDF();
    $pdf->SetAutoPageBreak(false);
    $pdf->SetCompression(false);
    [$bitmap, $meta] = render_print_page_pdf($pdf, $input, true, $crop);
    $pdf->Output('F', $output);
    @unlink($bitmap);

    ok($crop ? '已產生含出血與裁切線的 PDF。' : '已產生含出血的 PDF。', $output, array_merge([
        'action' => $crop ? 'bleed_crop' : 'add_bleed',
    ], $meta));
}

function crop_marks(): void
{
    $file = upload();
    $extension = ext($file['name']);
    assert_ext($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

    $case = case_no();
    $input = save_upload($file, $case, $extension);
    $output = OUTPUT_DIR . '/' . $case . '_crop_marks.pdf';

    require_fpdf();
    $pdf = new FPDF();
    $pdf->SetAutoPageBreak(false);
    $pdf->SetCompression(false);
    [$bitmap, $meta] = render_print_page_pdf($pdf, $input, false, true);
    $pdf->Output('F', $output);
    @unlink($bitmap);

    ok('已產生裁切線 PDF。', $output, array_merge([
        'action' => 'add_crop_marks',
    ], $meta));
}

function print_ready(): void
{
    $file = upload();
    $extension = ext($file['name']);
    assert_ext($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

    $case = case_no();
    $input = save_upload($file, $case, $extension);
    require_fpdf();
    $pdf = new FPDF();
    $pdf->SetAutoPageBreak(false);
    $pdf->SetCompression(false);
    $tempFiles = [];

    if (has_upload('back_file')) {
        $backFile = upload('back_file');
        $backExt = ext($backFile['name']);
        assert_ext($backExt, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

        $backInput = save_upload($backFile, $case . '_back', $backExt);
        $output = OUTPUT_DIR . '/' . $case . '_duplex_print_ready.pdf';

        [$frontBitmap, $meta] = render_print_page_pdf($pdf, $input, true, true);
        $tempFiles[] = $frontBitmap;

        [$backBitmap, $backMeta] = render_print_page_pdf($pdf, $backInput, true, true);
        $tempFiles[] = $backBitmap;

        $pdf->Output('F', $output);
        foreach ($tempFiles as $tempFile) {
            @unlink($tempFile);
        }

        ok('正反兩面已合併成同一份印刷 PDF。', $output, [
            'action' => 'print_ready_duplex',
            'dpi' => $meta['dpi'],
        ]);
    }

    $output = OUTPUT_DIR . '/' . $case . '_print_ready.pdf';
    [$bitmap, $meta] = render_print_page_pdf($pdf, $input, true, true);
    $pdf->Output('F', $output);
    @unlink($bitmap);

    ok('印刷版 PDF 已產生。', $output, array_merge([
        'action' => 'print_ready',
        'notice' => '已預設加入出血與裁切線。',
    ], $meta));
}

function upscale(): void
{
    $file = upload();
    $extension = ext($file['name']);
    assert_ext($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

    $case = case_no();
    $input = save_upload($file, $case, $extension);
    $output = OUTPUT_DIR . '/' . $case . '_upscale.jpg';

    $image = load_source_image($input);
    $image->resizeImage(
        $image->getImageWidth() * 2,
        $image->getImageHeight() * 2,
        Imagick::FILTER_LANCZOS,
        1,
        false
    );
    save_jpeg($image, $output, 92);

    ok('已產生放大 JPG。', $output, [
        'action' => 'upscale',
        'scale' => 2,
    ]);
}

function enhance(): void
{
    $file = upload();
    $extension = ext($file['name']);
    assert_ext($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

    $case = case_no();
    $input = save_upload($file, $case, $extension);
    $output = OUTPUT_DIR . '/' . $case . '_enhanced.jpg';

    $image = load_source_image($input);
    $image->unsharpMaskImage(0, 1.2, 1.0, 0.02);
    $image->contrastStretchImage(0.01, 0.01);
    save_jpeg($image, $output, 92);

    ok('已產生增強 JPG。', $output, [
        'action' => 'enhance_pdf',
    ]);
}

function cmyk(): void
{
    $file = upload();
    $extension = ext($file['name']);
    assert_ext($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'tif', 'tiff']);

    $case = case_no();
    $input = save_upload($file, $case, $extension);
    $output = OUTPUT_DIR . '/' . $case . '_cmyk.jpg';

    $image = load_source_image($input);
    $image->transformImageColorspace(Imagick::COLORSPACE_CMYK);
    save_jpeg($image, $output, 92);

    ok('已轉成 CMYK JPG。', $output, [
        'action' => 'convert_cmyk',
        'notice' => '這是以 Imagick 直接轉換，不再依賴 CLI magick。',
    ]);
}

function coating(): void
{
    $file = upload();
    $extension = ext($file['name']);
    assert_ext($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

    $case = case_no();
    $input = save_upload($file, $case, $extension);
    $output = OUTPUT_DIR . '/' . $case . '_coating_black_plate.pdf';
    $dpi = (int)num('dpi', 300, 72, 600);
    $widthMm = num('width_mm', 90, 5, 2000);
    $heightMm = num('height_mm', 54, 5, 2000);

    $widthPx = mm_px($widthMm, $dpi);
    $heightPx = mm_px($heightMm, $dpi);

    $image = load_source_image($input);
    $image = resize_cover($image, $widthPx, $heightPx);
    $image->setImageColorspace(Imagick::COLORSPACE_GRAY);

    $range = Imagick::getQuantumRange();
    $quantum = isset($range['quantumRangeLong']) ? (int)$range['quantumRangeLong'] : 65535;
    $image->thresholdImage((int)round($quantum * 0.92));
    $image->negateImage(false);
    save_pdf($image, $output, $dpi);

    ok('已產生上光黑版 PDF。', $output, [
        'action' => 'coating_plate',
    ]);
}
