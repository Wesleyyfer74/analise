<?php
/**
 * Normalizacao de imagens para JPEG compativel com visao e edicao.
 */

require_once __DIR__ . '/config.php';

class ImageProcessor {
    private const MAX_PIXELS = 40000000;

    public static function validate_upload($file, $label) {
        if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'])) {
            throw new Exception("Selecione $label.");
        }

        $errors = [
            UPLOAD_ERR_INI_SIZE => 'A imagem excede o limite configurado no servidor.',
            UPLOAD_ERR_FORM_SIZE => 'A imagem excede o limite permitido.',
            UPLOAD_ERR_PARTIAL => 'O upload da imagem foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_FILE => "Selecione $label.",
            UPLOAD_ERR_NO_TMP_DIR => 'O servidor esta sem pasta temporaria para uploads.',
            UPLOAD_ERR_CANT_WRITE => 'O servidor nao conseguiu salvar o upload.',
            UPLOAD_ERR_EXTENSION => 'Uma extensao do servidor bloqueou o upload.',
        ];

        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($errors[(int)$file['error']] ?? 'Falha desconhecida no upload.');
        }
        if ((int)$file['size'] <= 0) {
            throw new Exception("O arquivo de $label esta vazio.");
        }
        if ((int)$file['size'] > MAX_UPLOAD_SIZE) {
            $limit = round(MAX_UPLOAD_SIZE / 1024 / 1024);
            throw new Exception("A imagem excede o limite de {$limit} MB.");
        }
        if (!is_file($file['tmp_name']) || !is_readable($file['tmp_name'])) {
            throw new Exception('O servidor nao conseguiu ler a imagem enviada.');
        }
    }

    public static function sniff_bytes($raw) {
        if (strncmp($raw, "\xff\xd8\xff", 3) === 0) {
            return 'jpeg';
        }
        if (strncmp($raw, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return 'png';
        }
        if (strncmp($raw, 'GIF87a', 6) === 0 || strncmp($raw, 'GIF89a', 6) === 0) {
            return 'gif';
        }
        if (strncmp($raw, 'BM', 2) === 0) {
            return 'bmp';
        }
        if (strncmp($raw, 'RIFF', 4) === 0 && substr($raw, 8, 4) === 'WEBP') {
            return 'webp';
        }
        if (strlen($raw) >= 12 && substr($raw, 4, 4) === 'ftyp') {
            $brand = strtolower(substr($raw, 8, 4));
            $brands = strtolower(substr($raw, 8, 24));
            if (in_array($brand, ['avif', 'avis'], true) || strpos($brands, 'avif') !== false) {
                return 'avif';
            }
            if (in_array($brand, ['heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1'], true)) {
                return 'heic';
            }
        }
        return 'unknown';
    }

    public static function detect_format($file_path) {
        $raw = file_get_contents($file_path, false, null, 0, 32);
        if ($raw === false) {
            throw new Exception('Nao foi possivel ler a imagem.');
        }

        $format = self::sniff_bytes($raw);
        if ($format === 'unknown' && function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_path);
            finfo_close($finfo);
            $format = [
                'image/jpeg' => 'jpeg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/bmp' => 'bmp',
                'image/x-ms-bmp' => 'bmp',
                'image/gif' => 'gif',
                'image/avif' => 'avif',
                'image/heic' => 'heic',
                'image/heif' => 'heic',
            ][$mime] ?? 'unknown';
        }

        if ($format === 'unknown') {
            throw new Exception('Formato de imagem invalido. Use JPG, PNG, WebP, BMP, GIF, AVIF ou HEIC.');
        }
        return $format;
    }

    private static function load_with_imagick($file_path) {
        if (!class_exists('Imagick')) {
            throw new Exception(
                'Esta foto HEIC/HEIF nao pode ser convertida neste servidor. '
                . 'Ative a extensao Imagick na Hostinger ou envie a foto como JPG.'
            );
        }

        try {
            $imagick = new Imagick();
            $imagick->readImage($file_path);
            $imagick->setIteratorIndex(0);
            if (method_exists($imagick, 'autoOrientImage')) {
                $imagick->autoOrientImage();
            }
            $imagick->setImageBackgroundColor('white');
            $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $imagick->setImageFormat('jpeg');
            $blob = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();

            $image = @imagecreatefromstring($blob);
            if ($image === false) {
                throw new Exception('Falha ao converter a imagem HEIC/HEIF.');
            }
            return $image;
        } catch (Throwable $e) {
            throw new Exception('Nao foi possivel converter a imagem HEIC/HEIF: ' . $e->getMessage());
        }
    }

    private static function load_gd_image($file_path, $format) {
        switch ($format) {
            case 'jpeg':
                $image = @imagecreatefromjpeg($file_path);
                break;
            case 'png':
                $image = @imagecreatefrompng($file_path);
                break;
            case 'webp':
                $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file_path) : false;
                break;
            case 'bmp':
                $image = function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($file_path) : false;
                break;
            case 'gif':
                $image = @imagecreatefromgif($file_path);
                break;
            case 'avif':
                $image = function_exists('imagecreatefromavif') ? @imagecreatefromavif($file_path) : false;
                if ($image === false) {
                    return self::load_with_imagick($file_path);
                }
                break;
            case 'heic':
                return self::load_with_imagick($file_path);
            default:
                $image = false;
        }

        if ($image === false) {
            throw new Exception(
                'O formato foi reconhecido, mas nao e suportado pelas extensoes de imagem deste servidor.'
            );
        }
        return $image;
    }

    private static function apply_exif_orientation($image, $file_path, $format) {
        if ($format !== 'jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file_path);
        $orientation = (int)($exif['Orientation'] ?? 1);
        $rotated = null;
        if ($orientation === 2) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 3) {
            $rotated = imagerotate($image, 180, 0);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        } elseif ($orientation === 5) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
            $rotated = imagerotate($image, -90, 0);
        } elseif ($orientation === 6) {
            $rotated = imagerotate($image, -90, 0);
        } elseif ($orientation === 7) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
            $rotated = imagerotate($image, 90, 0);
        } elseif ($orientation === 8) {
            $rotated = imagerotate($image, 90, 0);
        }

        if ($rotated !== null && $rotated !== false) {
            imagedestroy($image);
            return $rotated;
        }
        return $image;
    }

    private static function normalize_image($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 300 || $height < 300) {
            imagedestroy($image);
            throw new Exception('Use uma foto com pelo menos 300 x 300 pixels.');
        }
        if ($width * $height > self::MAX_PIXELS) {
            imagedestroy($image);
            throw new Exception('A resolucao da imagem e muito alta. Reduza a foto antes de enviar.');
        }

        $ratio = min(1, MAX_IMAGE_SIDE / $width, MAX_IMAGE_SIDE / $height);
        $new_width = max(1, (int)round($width * $ratio));
        $new_height = max(1, (int)round($height * $ratio));
        $normalized = imagecreatetruecolor($new_width, $new_height);
        $white = imagecolorallocate($normalized, 255, 255, 255);
        imagefill($normalized, 0, 0, $white);
        imagecopyresampled(
            $normalized,
            $image,
            0,
            0,
            0,
            0,
            $new_width,
            $new_height,
            $width,
            $height
        );
        imagedestroy($image);
        return $normalized;
    }

    public static function save_uploaded_image($file, $destination_folder, $stem, $label) {
        self::validate_upload($file, $label);
        $format = self::detect_format($file['tmp_name']);
        $image = self::load_gd_image($file['tmp_name'], $format);
        $image = self::apply_exif_orientation($image, $file['tmp_name'], $format);
        $image = self::normalize_image($image);

        if (!is_dir($destination_folder)
            && !mkdir($destination_folder, 0755, true)
            && !is_dir($destination_folder)) {
            imagedestroy($image);
            throw new Exception('Nao foi possivel criar a pasta das imagens.');
        }

        $destination = $destination_folder . '/' . $stem . '.jpg';
        $saved = imagejpeg($image, $destination, JPEG_QUALITY);
        imagedestroy($image);
        if (!$saved || !is_file($destination) || filesize($destination) === 0) {
            throw new Exception('Nao foi possivel salvar a imagem processada.');
        }
        return $destination;
    }

    public static function prepare_image_for_vision($image_path) {
        if (!is_file($image_path)) {
            throw new Exception('Imagem nao encontrada.');
        }
        $data = file_get_contents($image_path);
        if ($data === false || $data === '') {
            throw new Exception('Nao foi possivel ler a imagem processada.');
        }
        return 'data:image/jpeg;base64,' . base64_encode($data);
    }
}
