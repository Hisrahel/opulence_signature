<?php

/**
 * config/upload.php
 *
 * Image upload helpers used by admin product pages.
 * Requires APP_ROOT to already be defined (e.g. in your bootstrap/_layout.php)
 * as the absolute filesystem path to your project root.
 */

if (!defined('APP_ROOT')) {
    throw new RuntimeException('APP_ROOT must be defined before including upload.php');
}

const UPLOAD_MAX_BYTES   = 5 * 1024 * 1024; // 5MB
const UPLOAD_ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
const UPLOAD_ALLOWED_MIME = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png'  => ['png'],
    'image/webp' => ['webp'],
    'image/gif'  => ['gif'],
];

/**
 * Handle an uploaded product image.
 *
 * @param array  $file          The $_FILES['xxx'] entry (may be empty/missing).
 * @param string $subdir        Subfolder under /uploads/ to store the file in (e.g. 'products').
 * @param string $prefix        Filename prefix (e.g. 'lux').
 * @param string $existingImage Relative path (under /uploads/) of the image currently on file, if any.
 *
 * @return array{ok: bool, error: string, path: ?string}
 *         path is:
 *           - null if no new file was uploaded (caller should keep $existingImage)
 *           - the new relative path (e.g. "products/lux-65f...-a1b2.jpg") on success
 */
function handleProductImageUpload(array $file, string $subdir, string $prefix, string $existingImage = ''): array
{
    // No file selected at all - nothing to do, keep existing image.
    if (empty($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'error' => '', 'path' => null];
    }

    // Upload-level errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The image is too large.',
            UPLOAD_ERR_FORM_SIZE  => 'The image is too large.',
            UPLOAD_ERR_PARTIAL    => 'The image upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server error: could not write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'The upload was stopped by a server extension.',
        ];
        return ['ok' => false, 'error' => $messages[$file['error']] ?? 'Image upload failed.', 'path' => null];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.', 'path' => null];
    }

    if ($file['size'] <= 0) {
        return ['ok' => false, 'error' => 'The uploaded file is empty.', 'path' => null];
    }

    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Image must be smaller than 5MB.', 'path' => null];
    }

    // Verify the real MIME type (don't trust the client-supplied one)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset(UPLOAD_ALLOWED_MIME[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, WEBP, and GIF images are allowed.', 'path' => null];
    }

    $ext = UPLOAD_ALLOWED_MIME[$mime][0];

    // Build destination
    $uploadRoot = rtrim(APP_ROOT, '/\\') . DIRECTORY_SEPARATOR . 'uploads';
    $destDir    = $uploadRoot . DIRECTORY_SEPARATOR . trim($subdir, '/\\');

    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return ['ok' => false, 'error' => 'Server error: could not create upload folder.', 'path' => null];
        }
    }

    $filename = $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $destDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'error' => 'Server error: could not save the uploaded image.', 'path' => null];
    }

    // Relative path stored in DB / used in <img src="../uploads/{path}">
    $relativePath = trim($subdir, '/\\') . '/' . $filename;

    // Clean up the old image now that the new one is safely saved
    if ($existingImage !== '' && $existingImage !== $relativePath) {
        deleteProductImage($existingImage);
    }

    return ['ok' => true, 'error' => '', 'path' => $relativePath];
}

/**
 * Delete a product image from disk given its relative path (as stored in the DB).
 */
function deleteProductImage(?string $relativePath): bool
{
    if (!$relativePath) {
        return false;
    }

    // Guard against path traversal
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (str_contains($relativePath, '..')) {
        return false;
    }

    $fullPath = rtrim(APP_ROOT, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (is_file($fullPath)) {
        return @unlink($fullPath);
    }

    return false;
}
