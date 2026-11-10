<?php
namespace DT\Documents;

/** Explicitly uploaded new files are isolated from unrelated /upload/doc files. */
final class Upload
{
    const MAX_SIZE = 20971520;

    public static function accept($field, $kind)
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return null;
        }
        $file = $_FILES[$field];
        if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Ошибка загрузки файла (код '.(int)$file['error'].').');
        }
        if ((int)$file['size'] <= 0 || (int)$file['size'] > self::MAX_SIZE ||
            !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Файл пустой, слишком большой или загружен некорректно.');
        }
        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($kind === 'document') {
            if ($extension !== 'pdf' || $mime !== 'application/pdf' ||
                file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') {
                throw new \RuntimeException('Документ должен быть настоящим PDF.');
            }
        } elseif ($kind === 'image') {
            if (!in_array($extension, array('jpg','jpeg','png','webp'), true)) {
                throw new \RuntimeException('Превью должно быть JPG, PNG или WebP.');
            }
            $info = @getimagesize($file['tmp_name']);
            $allowed = array('jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp');
            if (!$info || $mime !== $allowed[$extension]) {
                throw new \RuntimeException('Неверный формат изображения.');
            }
        } else {
            throw new \RuntimeException('Неизвестный тип загружаемого файла.');
        }
        $dir = $_SERVER['DOCUMENT_ROOT'].'/upload/doc/dt_documents';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Невозможно создать каталог загрузок dt_documents.');
        }
        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $destination = $dir.'/'.$name;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Не удалось сохранить файл.');
        }
        @chmod($destination, 0644);
        return '/upload/doc/dt_documents/'.$name;
    }
}
