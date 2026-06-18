<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('memory_limit','768M');
set_time_limit(180);

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('OUTPUT_DIR', APP_ROOT . '/outputs');
define('MAX_FILE_SIZE', 80 * 1024 * 1024);
define('MAGICK_BIN', '/opt/imagemagick/bin/magick');

if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR,0755,true); }
if (!is_dir(OUTPUT_DIR)) { mkdir(OUTPUT_DIR,0755,true); }

function ok(string $message, string $outputPath, array $extra=[]): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success'=>true,
        'message'=>$message,
        'download_url'=>'outputs/' . rawurlencode(basename($outputPath)),
        'filename'=>basename($outputPath),
        'bytes'=>is_file($outputPath) ? filesize($outputPath) : 0,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $message, array $extra=[]): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success'=>false,'message'=>$message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function magick(): string {
    if (!is_file(MAGICK_BIN)) { fail('找不到 ImageMagick 指令：' . MAGICK_BIN); }
    return MAGICK_BIN;
}
function run_cmd(array $parts): void {
    $cmd = implode(' ', array_map('escapeshellarg', $parts)) . ' 2>&1';
    $out=[]; $code=0;
    exec($cmd, $out, $code);
    if ($code !== 0) { fail('ImageMagick 處理失敗：' . implode("\n", $out), ['cmd'=>$cmd]); }
}
function run_shell(string $cmd): void {
    $out=[]; $code=0;
    exec($cmd . ' 2>&1', $out, $code);
    if ($code !== 0) { fail('ImageMagick 處理失敗：' . implode("\n", $out), ['cmd'=>$cmd]); }
}
function upload(string $field='file'): array {
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) { fail('請上傳檔案。'); }
    $f=$_FILES[$field];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { fail('檔案上傳失敗，錯誤代碼：' . (string)$f['error']); }
    if (($f['size'] ?? 0) <= 0 || $f['size'] > MAX_FILE_SIZE) { fail('檔案大小異常或超過 80MB。'); }
    return $f;
}
function has_upload(string $field): bool { return !empty($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK; }
function ext(string $name): string { return strtolower(pathinfo($name, PATHINFO_EXTENSION)); }
function assert_ext(string $ext, array $allowed): void { if (!in_array($ext,$allowed,true)) fail('不支援此檔案格式：' . strtoupper($ext)); }
function case_no(): string { return date('YmdHis') . '_' . bin2hex(random_bytes(4)); }
function save_upload(array $file, string $caseNo, string $ext): string {
    $path = UPLOAD_DIR . '/' . $caseNo . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $path)) { fail('無法儲存上傳檔案，請檢查 uploads 權限。'); }
    return $path;
}
function num(string $key, float $default, float $min, float $max): float {
    $v = isset($_POST[$key]) ? (float)$_POST[$key] : $default;
    if (!is_finite($v) || $v < 0) { $v=$default; }
    return max($min, min($max, $v));
}
function mm_px(float $mm, int $dpi): int { return max(1, (int)round(($mm/25.4)*$dpi)); }
function file_ref(string $path): string {
    $e = ext($path);
    return in_array($e, ['gif','svg'], true) ? $path . '[0]' : $path;
}
function draw_lines(int $trimX, int $trimY, int $trimW, int $trimH, int $markLen, int $gap): string {
    $x1=$trimX; $y1=$trimY; $x2=$trimX+$trimW; $y2=$trimY+$trimH;
    $lines=[];
    $lines[]="line " . ($x1-$gap-$markLen) . ",$y1 " . ($x1-$gap) . ",$y1";
    $lines[]="line $x1," . ($y1-$gap-$markLen) . " $x1," . ($y1-$gap);
    $lines[]="line " . ($x2+$gap) . ",$y1 " . ($x2+$gap+$markLen) . ",$y1";
    $lines[]="line $x2," . ($y1-$gap-$markLen) . " $x2," . ($y1-$gap);
    $lines[]="line " . ($x1-$gap-$markLen) . ",$y2 " . ($x1-$gap) . ",$y2";
    $lines[]="line $x1," . ($y2+$gap) . " $x1," . ($y2+$gap+$markLen);
    $lines[]="line " . ($x2+$gap) . ",$y2 " . ($x2+$gap+$markLen) . ",$y2";
    $lines[]="line $x2," . ($y2+$gap) . " $x2," . ($y2+$gap+$markLen);
    return implode(' ', $lines);
}
function render_pdf(string $input, string $output, bool $bleed, bool $crop): array {
    $widthMm=num('width_mm',90,5,2000); $heightMm=num('height_mm',54,5,2000); $bleedMm=$bleed?num('bleed_mm',2,0,20):0; $dpi=(int)num('dpi',300,72,600);
    $trimW=mm_px($widthMm,$dpi); $trimH=mm_px($heightMm,$dpi); $bleedPx=mm_px($bleedMm,$dpi);
    $canvasW=$trimW+($bleedPx*2); $canvasH=$trimH+($bleedPx*2); $markSpace=$crop?mm_px(8,$dpi):0;
    $pageW=$canvasW+($markSpace*2); $pageH=$canvasH+($markSpace*2); $trimX=$markSpace+$bleedPx; $trimY=$markSpace+$bleedPx;
    $imgref=file_ref($input);
    $cmd = escapeshellarg(magick()) . ' -size ' . escapeshellarg($pageW . 'x' . $pageH) . ' xc:white ' .
        '\( ' . escapeshellarg($imgref) . ' -auto-orient -background white -alpha remove -alpha off -resize ' . escapeshellarg($canvasW . 'x' . $canvasH . '^') . ' -gravity center -extent ' . escapeshellarg($canvasW . 'x' . $canvasH) . ' \) ' .
        ' -geometry ' . escapeshellarg('+' . $markSpace . '+' . $markSpace) . ' -compose over -composite ';
    if ($crop) {
        $stroke=max(1,(int)round($dpi/180)); $markLen=mm_px(5,$dpi); $gap=mm_px(2,$dpi);
        $cmd .= ' -stroke black -strokewidth ' . escapeshellarg((string)$stroke) . ' -draw ' . escapeshellarg(draw_lines($trimX,$trimY,$trimW,$trimH,$markLen,$gap)) . ' ';
    }
    $cmd .= ' -units PixelsPerInch -density ' . escapeshellarg((string)$dpi) . ' ' . escapeshellarg($output);
    run_shell($cmd);
    return ['trim_size'=>$widthMm . ' x ' . $heightMm . ' mm', 'bleed_size'=>($widthMm+$bleedMm*2) . ' x ' . ($heightMm+$bleedMm*2) . ' mm'];
}

$action = $_POST['action'] ?? '';
try {
    switch ($action) {
        case 'convert_pdf': convert_pdf(); break;
        case 'add_bleed': add_bleed(false); break;
        case 'add_crop_marks': crop_marks(); break;
        case 'bleed_crop': add_bleed(true); break;
        case 'print_ready': print_ready(); break;
        case 'upscale': upscale(); break;
        case 'enhance_pdf': enhance(); break;
        case 'convert_cmyk': cmyk(); break;
        case 'coating_plate': coating(); break;
        default: fail('未知的處理功能：' . $action);
    }
} catch (Throwable $e) { fail($e->getMessage()); }

function convert_pdf(): void {
    $f=upload(); $e=ext($f['name']); assert_ext($e,['jpg','jpeg','png','webp','gif','svg']); $case=case_no(); $in=save_upload($f,$case,$e); $out=OUTPUT_DIR.'/'.$case.'_print.pdf';
    run_cmd([magick(), file_ref($in), '-auto-orient', '-background', 'white', '-alpha', 'remove', '-alpha', 'off', '-units', 'PixelsPerInch', '-density', (string)((int)num('dpi',300,72,600)), $out]);
    ok('PDF 產生完成。',$out,['action'=>'convert_pdf']);
}
function add_bleed(bool $crop): void {
    $f=upload(); $e=ext($f['name']); assert_ext($e,['jpg','jpeg','png','webp','gif','svg']); $case=case_no(); $in=save_upload($f,$case,$e); $out=OUTPUT_DIR.'/'.$case.($crop?'_bleed_crop.pdf':'_bleed.pdf'); $meta=render_pdf($in,$out,true,$crop);
    ok($crop?'已產生含出血與裁切線的 PDF。':'已產生含出血的 PDF。',$out,array_merge(['action'=>$crop?'bleed_crop':'add_bleed'], $meta));
}
function crop_marks(): void {
    $f=upload(); $e=ext($f['name']); assert_ext($e,['jpg','jpeg','png','webp','gif','svg']); $case=case_no(); $in=save_upload($f,$case,$e); $out=OUTPUT_DIR.'/'.$case.'_crop_marks.pdf'; $meta=render_pdf($in,$out,false,true);
    ok('已產生含裁切線的 PDF。',$out,array_merge(['action'=>'add_crop_marks'],$meta));
}
function print_ready(): void {
    $f=upload(); $e=ext($f['name']); assert_ext($e,['jpg','jpeg','png','webp','gif','svg']); $case=case_no(); $in=save_upload($f,$case,$e);
    if (has_upload('back_file')) {
        $bf=upload('back_file'); $be=ext($bf['name']); assert_ext($be,['jpg','jpeg','png','webp','gif','svg']); $bin=save_upload($bf,$case.'_back',$be); $tmp1=OUTPUT_DIR.'/'.$case.'_front.pdf'; $tmp2=OUTPUT_DIR.'/'.$case.'_back.pdf'; render_pdf($in,$tmp1,true,true); render_pdf($bin,$tmp2,true,true); $out=OUTPUT_DIR.'/'.$case.'_duplex_print_ready.pdf'; run_cmd([magick(), $tmp1, $tmp2, $out]); @unlink($tmp1); @unlink($tmp2); ok('正反兩面已合併成同一份印刷 PDF。',$out,['action'=>'print_ready_duplex']);
    }
    $out=OUTPUT_DIR.'/'.$case.'_print_ready.pdf'; $meta=render_pdf($in,$out,true,true);
    ok('印刷檔 PDF 已完成。',$out,array_merge(['action'=>'print_ready','notice'=>'PDF 已保留 RGB 顯示色，避免線上預覽色彩反轉；正式印刷仍建議人工確認色彩。'],$meta));
}
function upscale(): void {
    $f=upload(); $e=ext($f['name']); assert_ext($e,['jpg','jpeg','png','webp','gif','svg']); $case=case_no(); $in=save_upload($f,$case,$e); $out=OUTPUT_DIR.'/'.$case.'_upscale.jpg';
    run_cmd([magick(), file_ref($in), '-auto-orient', '-background', 'white', '-alpha', 'remove', '-alpha', 'off', '-resize', '200%', '-quality', '92', $out]);
    ok('圖片放大完成。',$out,['action'=>'upscale','scale'=>2]);
}
function enhance(): void {
    $f=upload(); $e=ext($f['name']); assert_ext($e,['jpg','jpeg','png','webp','gif','svg']); $case=case_no(); $in=save_upload($f,$case,$e); $out=OUTPUT_DIR.'/'.$case.'_enhanced.jpg';
    run_cmd([magick(), file_ref($in), '-auto-orient', '-background', 'white', '-alpha', 'remove', '-alpha', 'off', '-unsharp', '0x1.2+1.0+0.02', '-contrast-stretch', '1%x1%', '-quality', '92', $out]);
    ok('圖片變清晰處理完成。',$out,['action'=>'enhance_pdf']);
}
function cmyk(): void {
    $f=upload(); $e=ext($f['name']); assert_ext($e,['jpg','jpeg','png','webp','gif','svg','tif','tiff']); $case=case_no(); $in=save_upload($f,$case,$e); $out=OUTPUT_DIR.'/'.$case.'_cmyk.jpg';
    run_cmd([magick(), file_ref($in), '-auto-orient', '-background', 'white', '-alpha', 'remove', '-alpha', 'off', '-colorspace', 'CMYK', '-quality', '92', $out]);
    ok('RGB 轉 CMYK 圖檔完成。',$out,['action'=>'convert_cmyk','notice'=>'輸出為 CMYK JPG；不同瀏覽器預覽可能與印刷輸出不同。']);
}
function coating(): void {
    $f=upload(); $e=ext($f['name']); assert_ext($e,['jpg','jpeg','png','webp','gif','svg']); $case=case_no(); $in=save_upload($f,$case,$e); $out=OUTPUT_DIR.'/'.$case.'_coating_black_plate.pdf'; $dpi=(int)num('dpi',300,72,600); $w=mm_px(num('width_mm',90,5,2000),$dpi); $h=mm_px(num('height_mm',54,5,2000),$dpi);
    $cmd = escapeshellarg(magick()) . ' ' . escapeshellarg(file_ref($in)) . ' -auto-orient -background white -alpha remove -alpha off -resize ' . escapeshellarg($w.'x'.$h.'^') . ' -gravity center -extent ' . escapeshellarg($w.'x'.$h) . ' -colorspace Gray -threshold 92% -negate -units PixelsPerInch -density ' . escapeshellarg((string)$dpi) . ' ' . escapeshellarg($out);
    run_shell($cmd);
    ok('已產生亮P / 霧P 用黑版 PDF。',$out,['action'=>'coating_plate']);
}
