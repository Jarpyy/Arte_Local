<?php

const EXTENSIONES_PERMITIDAS = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
const TAMANO_MAXIMO_IMAGEN = 5 * 1024 * 1024;
const MEGAPIXELES_MAXIMOS = 25_000_000;
const TIPOS_PERMITIDOS = ['digital', 'fisica'];
const ANCHO_MAXIMO_PUBLICO = 1400;

function procesarImagenObra(string $tmpPath, string $extension): array
{
    if (!function_exists('imagecreatetruecolor')) {
        return ['ok' => false, 'error' => 'El servidor no tiene soporte de procesamiento de imágenes (GD) habilitado.'];
    }
}

function validarArchivoImagen(array $archivo, int $tamanoMaximo, array $extensionesPermitidas): array
{
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Ocurrió un error al subir el archivo.', 'extension' => null];
    }
    if ($archivo['size'] > $tamanoMaximo) {
        return ['ok' => false, 'error' => 'El archivo no puede superar los 5 MB.', 'extension' => null];
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    $extensionValida = array_key_exists($extension, $extensionesPermitidas);
    $mimeValido = $extensionValida && $mimeReal === $extensionesPermitidas[$extension];

    if (!$extensionValida || !$mimeValido) {
        return ['ok' => false, 'error' => 'El archivo debe ser JPG, PNG o WEBP.', 'extension' => null];
    }
    return ['ok' => true, 'error' => null, 'extension' => $extension];
}

function validarDimensionesImagen(string $tmpPath, int $megapixelesMaximos): array
{
    $infoImagen = @getimagesize($tmpPath);
    if ($infoImagen === false) {
        return ['ok' => false, 'error' => 'El archivo no es una imagen válida o está dañado.', 'ancho' => null, 'alto' => null];
    }
    if (($infoImagen[0] * $infoImagen[1]) > $megapixelesMaximos) {
        return ['ok' => false, 'error' => 'La imagen tiene una resolución demasiado grande.', 'ancho' => $infoImagen[0], 'alto' => $infoImagen[1]];
    }
    return ['ok' => true, 'error' => null, 'ancho' => $infoImagen[0], 'alto' => $infoImagen[1]];
}