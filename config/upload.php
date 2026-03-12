<?php
// Helpers para subida segura de archivos
function secure_upload($fileField, $destDir, $options = []) {
    $defaults = [
        'max_size' => 2 * 1024 * 1024, // 2MB
        'allowed_types' => ['image/jpeg','image/png','image/webp','image/gif']
    ];
    $opt = array_merge($defaults, $options);

    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'code' => 'no_file'];
    }

    $f = $_FILES[$fileField];
    if ($f['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'msg' => 'Upload error code: '.$f['error']];
    if ($f['size'] > $opt['max_size']) return ['ok' => false, 'msg' => 'El archivo supera el tamaño máximo permitido.'];

    // Validate MIME using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $f['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $opt['allowed_types'])) return ['ok' => false, 'msg' => 'Tipo de archivo no permitido.'];

    // Ensure dest dir exists
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) return ['ok' => false, 'msg' => 'No se pudo crear directorio de destino.'];
    }

    // Generate safe filename
    $ext = '';
    switch($mime) {
        case 'image/jpeg': $ext = '.jpg'; break;
        case 'image/png': $ext = '.png'; break;
        case 'image/webp': $ext = '.webp'; break;
        case 'image/gif': $ext = '.gif'; break;
        default: $ext = '';
    }
    $name = bin2hex(random_bytes(8)) . '_' . time() . $ext;
    $destPath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($f['tmp_name'], $destPath)) return ['ok' => false, 'msg' => 'No se pudo mover el archivo.'];

    // Set restrictive permissions
    @chmod($destPath, 0644);

    return ['ok' => true, 'filename' => $name, 'mime' => $mime, 'size' => $f['size']];
}

?>