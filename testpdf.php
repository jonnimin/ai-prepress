<?php
declare(strict_types=1);

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render_error_page(string $title, string $message, array $details = []): void {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title>';
    echo '<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans TC",sans-serif;background:#f6f2ea;color:#1f2937;margin:0;padding:32px} .card{max-width:760px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 12px 40px rgba(0,0,0,.08)} code,pre{background:#f3f4f6;border-radius:10px;padding:12px;display:block;overflow:auto} .btn{display:inline-block;margin-top:16px;padding:12px 18px;border-radius:999px;background:#111827;color:#fff;text-decoration:none}</style></head><body>';
    echo '<div class="card">';
    echo '<h1>' . h($title) . '</h1>';
    echo '<p>' . h($message) . '</p>';
    if ($details) {
        echo '<pre>' . h(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . '</pre>';
    }
    echo '<a class="btn" href="testpdf.php">返回測試頁</a>';
    echo '</div></body></html>';
    exit;
}

function build_test_pdf_bytes(): string {
    if (!extension_loaded('imagick')) {
        render_error_page('Imagick 未載入', '伺服器沒有啟用 Imagick 擴充。');
    }

    if (!class_exists('Imagick')) {
        render_error_page('Imagick 類別不存在', 'PHP 偵測不到 Imagick 類別。');
    }

    $pdfFormats = Imagick::queryFormats('PDF');
    if (!$pdfFormats) {
        render_error_page('PDF coder 不可用', 'Imagick 找不到 PDF 輸出格式。');
    }

    $canvas = new Imagick();
    $canvas->newImage(1240, 1754, new ImagickPixel('white'));
    $canvas->setImageFormat('png');

    $draw = new ImagickDraw();
    $draw->setFillColor(new ImagickPixel('#111827'));
    $draw->setStrokeColor(new ImagickPixel('#d97706'));
    $draw->setStrokeWidth(10);
    $draw->rectangle(80, 80, 1160, 1674);

    $draw->setStrokeWidth(0);
    $draw->setFillColor(new ImagickPixel('#f97316'));
    $draw->rectangle(120, 120, 1120, 220);

    $draw->setFillColor(new ImagickPixel('#ffffff'));
    $draw->setFontSize(54);
    $fontCandidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
        '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
    ];
    foreach ($fontCandidates as $fontPath) {
        if (is_file($fontPath)) {
            $draw->setFont($fontPath);
            break;
        }
    }
    $canvas->drawImage($draw);
    $canvas->annotateImage($draw, 150, 205, 0, 'Imagick PDF test');

    $draw2 = new ImagickDraw();
    $draw2->setFillColor(new ImagickPixel('#111827'));
    $draw2->setFontSize(28);
    foreach ($fontCandidates as $fontPath) {
        if (is_file($fontPath)) {
            $draw2->setFont($fontPath);
            break;
        }
    }

    $lines = [
        '這是一份由 testpdf.php 直接產生的 PDF。',
        '用途：確認 PHP Imagick 是否能輸出 PDF。',
        '時間：' . date('Y-m-d H:i:s'),
        'PHP：' . PHP_VERSION,
    ];
    $y = 340;
    foreach ($lines as $line) {
        $canvas->annotateImage($draw2, 140, $y, 0, $line);
        $y += 58;
    }

    $canvas->setImageResolution(300, 300);
    $canvas->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
    $canvas->setImageFormat('pdf');

    $tmp = tempnam(sys_get_temp_dir(), 'ai_pdf_test_');
    if ($tmp === false) {
        render_error_page('暫存檔建立失敗', '無法建立暫存檔。');
    }
    $pdfPath = $tmp . '.pdf';
    @unlink($tmp);

    try {
        $canvas->writeImages($pdfPath, true);
        $bytes = file_get_contents($pdfPath);
        if ($bytes === false || $bytes === '') {
            render_error_page('PDF 讀取失敗', 'PDF 已寫出，但無法讀取檔案內容。', ['path' => $pdfPath]);
        }
        register_shutdown_function(static function () use ($pdfPath): void {
            if (is_file($pdfPath)) {
                @unlink($pdfPath);
            }
        });
        return $bytes;
    } catch (Throwable $e) {
        if (is_file($pdfPath)) {
            @unlink($pdfPath);
        }
        render_error_page('PDF 產生失敗', $e->getMessage(), [
            'imagick_loaded' => extension_loaded('imagick'),
            'pdf_formats' => $pdfFormats,
        ]);
    }
}

$download = isset($_GET['download']) && $_GET['download'] === '1';
$info = [
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'imagick_loaded' => extension_loaded('imagick'),
    'imagick_class' => class_exists('Imagick'),
    'pdf_coder' => class_exists('Imagick') ? (bool) Imagick::queryFormats('PDF') : false,
];

if ($download) {
    $pdf = build_test_pdf_bytes();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="imagick-test.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Imagick PDF 測試</title>
  <style>
    body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans TC", sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 32px; }
    .card { max-width: 900px; margin: 0 auto; background: #111827; border: 1px solid #334155; border-radius: 20px; padding: 28px; box-shadow: 0 24px 80px rgba(0,0,0,.28); }
    h1 { margin: 0 0 12px; font-size: 32px; }
    p { line-height: 1.8; }
    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 20px 0; }
    .item { background: #0b1220; border: 1px solid #334155; border-radius: 14px; padding: 14px 16px; }
    .label { display: block; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px; }
    .value { font-size: 16px; color: #f8fafc; word-break: break-word; }
    .btn { display: inline-block; margin-top: 18px; padding: 14px 20px; border-radius: 999px; background: linear-gradient(135deg, #f97316, #fb7185); color: #fff; text-decoration: none; font-weight: 700; }
    .btn + .btn { margin-left: 10px; }
    code, pre { background: #0b1220; border: 1px solid #334155; border-radius: 14px; padding: 14px; overflow: auto; display: block; color: #cbd5e1; }
    .note { color: #cbd5e1; margin-top: 18px; }
    @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } .card { padding: 20px; } h1 { font-size: 26px; } }
  </style>
</head>
<body>
  <div class="card">
    <h1>Imagick PDF 測試頁</h1>
    <p>這個頁面不走 <code>/opt/imagemagick/bin/magick</code>，只測 PHP 的 <code>imagick</code> 擴充能不能直接產生 PDF。</p>
    <div class="grid">
      <div class="item"><span class="label">PHP Version</span><span class="value"><?=h((string)$info['php_version'])?></span></div>
      <div class="item"><span class="label">SAPI</span><span class="value"><?=h((string)$info['sapi'])?></span></div>
      <div class="item"><span class="label">Imagick Loaded</span><span class="value"><?= $info['imagick_loaded'] ? 'yes' : 'no' ?></span></div>
      <div class="item"><span class="label">Imagick Class</span><span class="value"><?= $info['imagick_class'] ? 'yes' : 'no' ?></span></div>
      <div class="item"><span class="label">PDF Coder</span><span class="value"><?= $info['pdf_coder'] ? 'yes' : 'no' ?></span></div>
      <div class="item"><span class="label">Test Action</span><span class="value">產生並下載 PDF</span></div>
    </div>
    <?php if ($info['imagick_loaded'] && $info['pdf_coder']): ?>
    <a class="btn" href="?download=1">下載測試 PDF</a>
    <?php else: ?>
    <p class="note">目前這台伺服器尚未偵測到可用的 Imagick / PDF coder，所以先不顯示下載按鈕。</p>
    <?php endif; ?>
    <a class="btn" href="index.html">回到工具首頁</a>
    <p class="note">如果點下載後成功拿到 PDF，表示伺服器上的 Imagick 可用。之後就可以把正式流程改成不依賴 CLI `magick`。</p>
    <pre><?php echo h(json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)); ?></pre>
  </div>
</body>
</html>
