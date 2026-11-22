<?php

class ImageUploader
{
    private const MAX_SIZE = 5242880;
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public static function upload($archivo, $directorio, $maxBytes = self::MAX_SIZE)
    {
        if (!is_array($archivo)
            || ($archivo['error'] ?? null) !== UPLOAD_ERR_OK
            || !isset($archivo['size'], $archivo['tmp_name'])
            || !is_string($archivo['tmp_name'])
            || !is_uploaded_file($archivo['tmp_name'])
            || !is_numeric($archivo['size'])
            || (int) $archivo['size'] < 0
            || (int) $archivo['size'] > $maxBytes
            || !is_string($directorio)
            || $directorio === '') {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $tipoReal = $finfo->file($archivo['tmp_name']);

        if (!isset(self::ALLOWED_TYPES[$tipoReal])) {
            return null;
        }

        $nombreUnico = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_TYPES[$tipoReal];

        if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            return null;
        }

        if (!move_uploaded_file($archivo['tmp_name'], rtrim($directorio, '/\\') . DIRECTORY_SEPARATOR . $nombreUnico)) {
            return null;
        }

        return $nombreUnico;
    }
}
