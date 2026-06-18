<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';

try {
    $file = get_upload();
    $ext = file_ext($file['name']);
    assert_ext($ext, ['pdf','svg','ai','eps']);

    if ($ext === 'pdf') {
        fail('文字轉外框需要伺服器安裝 Ghostscript，建議下一階段接 gs -dNoOutputFonts。此功能應列為 VIP 或人工完稿。');
    }

    if ($ext === 'svg') {
        fail('SVG 文字轉路徑需要伺服器安裝 Inkscape，建議下一階段接 inkscape --export-text-to-path。此功能應列為 VIP。');
    }

    fail('AI / EPS 自動文字轉外框風險較高，建議人工完稿確認。');
} catch (Throwable $e) {
    fail($e->getMessage());
}
