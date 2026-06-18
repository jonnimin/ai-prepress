<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';

ensure_imagick();

try {
    $file = get_upload();
    $ext = file_ext($file['name']);
    $allowed = ['jpg','jpeg','png','webp','pdf','ai','eps','svg'];
    assert_ext($ext, $allowed);

    $widthMm = clean_number((float)($_POST['width_mm'] ?? 90), 5, 2000, 90);
    $heightMm = clean_number((float)($_POST['height_mm'] ?? 54), 5, 2000, 54);
    $bleedMm = clean_number((float)($_POST['bleed_mm'] ?? 2), 0, 20, 2);
    $targetDpi = (int)clean_number((float)($_POST['dpi'] ?? 300), 72, 600, 300);

    $caseNo = make_case_no();
    $input = save_upload($file, $caseNo, $ext);

    $report = [
        'filename' => $file['name'],
        'format' => strtoupper($ext),
        'file_size_mb' => round($file['size'] / 1024 / 1024, 2),
        'trim_size' => $widthMm . ' x ' . $heightMm . ' mm',
        'bleed_mm' => $bleedMm,
        'bleed_size' => ($widthMm + $bleedMm * 2) . ' x ' . ($heightMm + $bleedMm * 2) . ' mm',
        'checks' => [],
        'suggested_actions' => [],
        'risk_level' => 'medium',
    ];

    if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        $img = load_flat_image($input);
        $pxW = $img->getImageWidth();
        $pxH = $img->getImageHeight();
        $dpiX = $pxW / ($widthMm / 25.4);
        $dpiY = $pxH / ($heightMm / 25.4);
        $dpi = (int)floor(min($dpiX, $dpiY));
        $ratioDiff = abs(($pxW / max(1, $pxH)) - ($widthMm / $heightMm)) / ($widthMm / $heightMm);

        $report['image_px'] = $pxW . ' x ' . $pxH . ' px';
        $report['estimated_dpi'] = $dpi;
        $report['ratio_diff_percent'] = round($ratioDiff * 100, 1);

        $report['checks'][] = ['label'=>'圖片解析度', 'status'=>$dpi >= $targetDpi ? 'ok' : ($dpi >= 150 ? 'warn' : 'bad'), 'text'=>$dpi . ' DPI'];
        $report['checks'][] = ['label'=>'比例差異', 'status'=>$ratioDiff <= 0.03 ? 'ok' : ($ratioDiff <= 0.08 ? 'warn' : 'bad'), 'text'=>round($ratioDiff * 100, 1) . '%'];
        $report['checks'][] = ['label'=>'出血狀態', 'status'=>$bleedMm > 0 ? 'warn' : 'bad', 'text'=>$bleedMm > 0 ? '已設定出血值，但仍需產生出血區' : '未設定出血'];
        $report['checks'][] = ['label'=>'色彩模式', 'status'=>'warn', 'text'=>'圖片需正式轉 CMYK 才適合印刷'];

        if ($dpi < $targetDpi) { $report['suggested_actions'][] = '放大圖片或改用高解析圖'; }
        if ($bleedMm > 0) { $report['suggested_actions'][] = 'AI 智慧出血延伸 / 加出血'; }
        $report['suggested_actions'][] = '轉印刷檔';
        $report['suggested_actions'][] = '轉 CMYK';

        if ($dpi < 150 || $ratioDiff > 0.08) {
            $report['risk_level'] = 'high';
        } elseif ($dpi >= $targetDpi && $ratioDiff <= 0.03) {
            $report['risk_level'] = 'low';
        }

        $img->clear();
    } elseif ($ext === 'pdf') {
        $report['checks'][] = ['label'=>'PDF 檔案', 'status'=>'warn', 'text'=>'可進行 PDF 預檢，字型、裁切線、出血需更深入解析'];
        $report['checks'][] = ['label'=>'文字轉外框', 'status'=>'warn', 'text'=>'若 PDF 含字型，建議做文字轉外框或人工完稿'];
        $report['suggested_actions'][] = '文字轉外框 VIP';
        $report['suggested_actions'][] = 'PDF 深度檢查';
        $report['risk_level'] = 'medium';
    } else {
        $report['checks'][] = ['label'=>'向量 / 設計檔', 'status'=>'warn', 'text'=>'AI / EPS / SVG 需檢查字型、連結圖、透明效果與轉曲狀態'];
        $report['suggested_actions'][] = '文字轉外框 VIP';
        $report['suggested_actions'][] = '人工完稿';
        $report['risk_level'] = 'medium';
    }

    json_response(true, '線上檔案檢查完成。', ['report'=>$report]);
} catch (Throwable $e) {
    fail($e->getMessage());
}
