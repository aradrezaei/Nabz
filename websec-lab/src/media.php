<?php
declare(strict_types=1);

function extract_jpeg_comment(string $bytes): string
{
    if (!str_starts_with($bytes, "\xFF\xD8")) {
        return '';
    }
    $comments = [];
    $length = strlen($bytes);
    $index = 2;
    while ($index < $length - 4) {
        if ($bytes[$index] !== "\xFF") {
            break;
        }
        while ($index < $length && $bytes[$index] === "\xFF") {
            $index++;
        }
        if ($index >= $length) {
            break;
        }
        $marker = ord($bytes[$index]);
        $index++;
        if ($marker === 0xD9 || $marker === 0xDA) {
            break;
        }
        if ($index + 2 > $length) {
            break;
        }
        $segmentLength = (ord($bytes[$index]) << 8) + ord($bytes[$index + 1]);
        if ($segmentLength < 2 || $index + $segmentLength > $length) {
            break;
        }
        if ($marker === 0xFE) {
            $comments[] = substr($bytes, $index + 2, $segmentLength - 2);
        }
        $index += $segmentLength;
    }
    return trim(implode("\n", $comments));
}

function extract_png_text(string $bytes): string
{
    if (!str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
        return '';
    }
    $parts = [];
    $length = strlen($bytes);
    $index = 8;
    while ($index + 12 <= $length) {
        $chunkLength = unpack('N', substr($bytes, $index, 4));
        $size = is_array($chunkLength) ? (int) $chunkLength[1] : 0;
        $type = substr($bytes, $index + 4, 4);
        if ($index + 12 + $size > $length) {
            break;
        }
        $data = substr($bytes, $index + 8, $size);
        if ($type === 'tEXt' || $type === 'zTXt' || $type === 'iTXt') {
            $split = explode("\x00", $data, 2);
            if (isset($split[1]) && $split[1] !== '') {
                $parts[] = $split[1];
            }
        }
        if ($type === 'IEND') {
            break;
        }
        $index += 12 + $size;
    }
    return trim(implode("\n", $parts));
}

function extract_image_metadata(string $path): string
{
    $bytes = file_get_contents($path);
    if (!is_string($bytes) || $bytes === '') {
        return '';
    }
    $chunks = [];
    $jpeg = extract_jpeg_comment($bytes);
    if ($jpeg !== '') {
        $chunks[] = $jpeg;
    }
    $png = extract_png_text($bytes);
    if ($png !== '') {
        $chunks[] = $png;
    }
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        if (is_array($exif)) {
            foreach (['ImageDescription', 'UserComment', 'Comments'] as $key) {
                if (isset($exif[$key]) && is_string($exif[$key]) && trim($exif[$key]) !== '') {
                    $chunks[] = trim($exif[$key]);
                }
            }
            if (isset($exif['COMPUTED']['UserComment']) && is_string($exif['COMPUTED']['UserComment'])) {
                $chunks[] = trim($exif['COMPUTED']['UserComment']);
            }
        }
    }
    return trim(implode("\n", array_unique($chunks)));
}

function image_type_ok(string $path): int
{
    $info = @getimagesize($path);
    if (!is_array($info)) {
        return 0;
    }
    $type = (int) ($info[2] ?? 0);
    $width = (int) ($info[0] ?? 0);
    $height = (int) ($info[1] ?? 0);
    if ($width < 1 || $height < 1 || $width > 4000 || $height > 4000) {
        return 0;
    }
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
        return 0;
    }
    return $type;
}

function reencode_image(string $source, string $dest): bool
{
    $type = image_type_ok($source);
    $image = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
        IMAGETYPE_PNG => @imagecreatefrompng($source),
        IMAGETYPE_GIF => @imagecreatefromgif($source),
        default => false,
    };
    if (!$image instanceof GdImage) {
        return false;
    }
    $width = imagesx($image);
    $height = imagesy($image);
    $scale = min(640 / $width, 640 / $height, 1);
    $targetWidth = max(1, (int) floor($width * $scale));
    $targetHeight = max(1, (int) floor($height * $scale));
    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$canvas instanceof GdImage) {
        imagedestroy($image);
        return false;
    }
    $white = imagecolorallocate($canvas, 255, 255, 255);
    if ($white !== false) {
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
    }
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    $saved = imagejpeg($canvas, $dest, 85);
    imagedestroy($image);
    imagedestroy($canvas);
    return $saved;
}

function mime_for_extension(string $ext): ?string
{
    return match ($ext) {
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        default => null,
    };
}
