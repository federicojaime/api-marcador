<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app->post('/empleado/registro', function (Request $request, Response $response, array $args) {
    $directory = __DIR__ . '/../uploads/'; // Directorio donde se subirán las imágenes

    // Obtenemos los datos del empleado
    $parsedBody = $request->getParsedBody();
    $nombre = $parsedBody['nombre'];
    $email = $parsedBody['email'];

    // Subir la imagen
    $uploadedFiles = $request->getUploadedFiles();
    if (isset($uploadedFiles['imagen'])) {
        $imagen = $uploadedFiles['imagen'];
        if ($imagen->getError() === UPLOAD_ERR_OK) {
            $filename = moveUploadedFile($directory, $imagen);
            // Guardar el registro del empleado en una base de datos con su imagen.
            // Aquí deberías insertar el empleado en la base de datos
            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'Empleado registrado exitosamente',
                'imagen_url' => "/uploads/$filename"
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Error al subir la imagen']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
});

function moveUploadedFile($directory, $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // Nombre aleatorio para el archivo
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

    return $filename;
}

$app->post('/empleado/registro-entrada', function (Request $request, Response $response, array $args) {
    $directory = __DIR__ . '/../uploads/'; // Donde están las imágenes almacenadas
    $uploadedFiles = $request->getUploadedFiles();

    if (isset($uploadedFiles['imagen'])) {
        $imagen = $uploadedFiles['imagen'];
        if ($imagen->getError() === UPLOAD_ERR_OK) {
            $filename = moveUploadedFile($directory, $imagen);
            // Aquí realizarías la lógica de comparación facial usando la imagen subida
            // Con face-api.js o cualquier otra herramienta para comparar rostros.

            // Supongamos que la comparación es correcta:
            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'Entrada registrada exitosamente',
                'imagen_url' => "/uploads/$filename"
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Error al subir la imagen']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(202);
});
