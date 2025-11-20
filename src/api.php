<?php 
declare(strict_types=1); 
/** 
* API del Mini-CRUD 
* Acciones admitidas: list | create | delete 
* Persistencia: archivo JSON (data.json) en el mismo directorio. 
* 
* Nota didáctica: 
* - Este archivo se invoca desde el navegador mediante fetch() (AJAX). 
* - Siempre respondemos en JSON. 
* - Las validaciones mínimas se realizan en servidor, aunque el cliente valide. 
*/ 
// 1) Todas las respuestas serán JSON UTF-8 
header('Content-Type: application/json; charset=utf-8'); 
/** 
* Envía una respuesta de éxito con envoltura homogénea. 
* 
* @param mixed $contenidoDatos  Datos a devolver (ej: lista de usuarios). 
 * @param int   $codigoHttp      Código de estado HTTP (200 por defecto). 
 */ 
function responder_json_exito(mixed $contenidoDatos = [], int $codigoHttp = 200): void { 
    http_response_code($codigoHttp); 
    echo json_encode( 
        ['ok' => true, 'data' => $contenidoDatos], 
        JSON_UNESCAPED_UNICODE 
    ); 
    exit; 
} 
 
/** 
 * Envía una respuesta de error con envoltura homogénea. 
 * 
 * @param string $mensajeError   Mensaje de error legible para el cliente. 
 * @param int    $codigoHttp     Código de estado HTTP (400 por defecto). 
 */ 
function responder_json_error(string $mensajeError, int $codigoHttp = 400): void { 
    http_response_code($codigoHttp); 
    echo json_encode( 
        ['ok' => false, 'error' => $mensajeError], 
        JSON_UNESCAPED_UNICODE 
    ); 
    exit; 
} 

// 2) Ruta al archivo de persistencia (misma carpeta) 
$rutaArchivoDatosJson = __DIR__ . '/data.json'; 
 
// 2.1) Si no existe, lo creamos con un array JSON vacío ([]) 
if (!file_exists($rutaArchivoDatosJson)) { 
    file_put_contents($rutaArchivoDatosJson, json_encode([]) . "\n"); 
} 
 
// 2.2) Cargar su contenido como array asociativo de PHP 
$listaUsuarios = json_decode((string) file_get_contents($rutaArchivoDatosJson), true); 
 
// 2.3) Si por cualquier motivo no es un array, lo normalizamos a [] 
if (!is_array($listaUsuarios)) { 
    $listaUsuarios = []; 
} 

// 3) Método HTTP y acción (por querystring o formulario) 
//    - Por simplicidad: list en GET; create y delete por POST. 
//    - Si no llega 'action', usamos 'list' como valor por defecto. 
$metodoHttpRecibido = $_SERVER['REQUEST_METHOD'] ?? 'GET'; 
$accionSolicitada = $_GET['action'] ?? $_POST['action'] ?? 'list'; 

// 4) LISTAR usuarios: GET /api.php?action=list 
if ($metodoHttpRecibido === 'GET' && $accionSolicitada === 'list') { 
    // No devolvemos las contraseñas (hashes) al cliente
    responder_json_exito(sanitizarUsuarios($listaUsuarios)); // 200 OK 
}

// 5) CREAR usuario: POST /api.php?action=create 
//    Body JSON esperado: { "nombre": "...", "email": "..." } 
// 5) CREAR usuario: POST /api.php?action=create 
//    Body JSON esperado: { "nombre": "...", "email": "..." } 
if ($metodoHttpRecibido === 'POST' && $accionSolicitada === 'create') { 
    $cuerpoBruto = (string) file_get_contents('php://input'); 
    $datosDecodificados = $cuerpoBruto !== '' ? (json_decode($cuerpoBruto, true) ?? []) : []; 
 
    // Extraemos datos y normalizamos 
    $nombreUsuarioNuevo = trim((string) ($datosDecodificados['nombre'] ?? $_POST['nombre'] ?? '')); 
    $correoUsuarioNuevo = trim((string) ($datosDecodificados['email']  ?? $_POST['email']  ?? ''));
    $contrasenaPlano = (string) ($datosDecodificados['password'] ?? $_POST['password'] ?? '');
    $correoUsuarioNormalizado = mb_strtolower($correoUsuarioNuevo); 
 
    // Validación mínima en servidor 
    if ($nombreUsuarioNuevo === '' || $correoUsuarioNuevo === '') { 
        responder_json_error('Los campos "nombre" y "email" son obligatorios.', 422); 
    } 
    if ($contrasenaPlano === '') {
        responder_json_error('El campo "password" es obligatorio.', 422);
    }
    if (!filter_var($correoUsuarioNuevo, FILTER_VALIDATE_EMAIL)) { 
        responder_json_error('El campo "email" no tiene un formato válido.', 422); 
    } 
 
    // Límites razonables para este ejercicio 
    if (mb_strlen($nombreUsuarioNuevo) > 60) { 
        responder_json_error('El campo "nombre" excede los 60 caracteres.', 422); 
    } 
    if (mb_strlen($correoUsuarioNuevo) > 120) { 
        responder_json_error('El campo "email" excede los 120 caracteres.', 422); 
    } 
    if (mb_strlen($contrasenaPlano) < 6) {
        responder_json_error('El campo "password" debe tener al menos 6 caracteres.', 422);
    }
 
    // Evitar duplicados por email 
    if (existeEmailDuplicado($listaUsuarios, $correoUsuarioNormalizado)) { 
        responder_json_error('Ya existe un usuario con ese email.', 409); 
    } 
 
    // Hasheamos la contraseña y almacenamos usuario
    $hashPassword = password_hash($contrasenaPlano, PASSWORD_DEFAULT);
    $listaUsuarios[] = [ 
        'nombre' => $nombreUsuarioNuevo, 
        'email'  => $correoUsuarioNormalizado, 
        'password' => $hashPassword,
    ]; 
 
    file_put_contents( 
        $rutaArchivoDatosJson, 
        json_encode($listaUsuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n" 
    ); 
 
    responder_json_exito(sanitizarUsuarios($listaUsuarios), 201); 
}

// 6) ELIMINAR usuario: POST /api.php?action=delete 
//    Body JSON esperado: { "index": 0 } 
//    Nota: podríamos usar método DELETE; aquí lo simplificamos a POST. 
if (($metodoHttpRecibido === 'POST' || $metodoHttpRecibido === 'DELETE') && $accionSolicitada === 
'delete') { 
    // 6.1) Intentamos obtener el índice por distintos canales 
    $indiceEnQuery = $_GET['index'] ?? null; 
 
    if ($indiceEnQuery === null) { 
        $cuerpoBruto = (string) file_get_contents('php://input'); 
        if ($cuerpoBruto !== '') { 
            $datosDecodificados = json_decode($cuerpoBruto, true) ?? []; 
            $indiceEnQuery = $datosDecodificados['index'] ?? null; 
        } else { 
            $indiceEnQuery = $_POST['index'] ?? null; 
        } 
    } 
 
    // 6.2) Validaciones de existencia del parámetro 
    if ($indiceEnQuery === null) { 
        responder_json_error('Falta el parámetro "index" para eliminar.', 422); 
    } 
 
    $indiceUsuarioAEliminar = (int) $indiceEnQuery; 
 
    if (!isset($listaUsuarios[$indiceUsuarioAEliminar])) { 
        responder_json_error('El índice indicado no existe.', 404); 
    } 
 
    // 6.3) Eliminamos y reindexamos para mantener la continuidad 
    unset($listaUsuarios[$indiceUsuarioAEliminar]); 
    $listaUsuarios = array_values($listaUsuarios); 
 
    // 6.4) Guardamos el nuevo estado en disco 
    file_put_contents( 
        $rutaArchivoDatosJson, 
        json_encode($listaUsuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n" 
    ); 
    // 6.5) Devolvemos el listado actualizado 
    responder_json_exito(sanitizarUsuarios($listaUsuarios)); // 200 OK 
}

// update user (POST action=update)
// body: { index, nombre, email }
if ($metodoHttpRecibido === 'POST' && $accionSolicitada === 'update') {
    // parse body
    $cuerpoBruto = (string) file_get_contents('php://input');
    $datosDecodificados = $cuerpoBruto !== '' ? (json_decode($cuerpoBruto, true) ?? []) : [];

    $indice = isset($datosDecodificados['index']) ? (int) $datosDecodificados['index'] : null;
    $nombreNuevo = trim((string) ($datosDecodificados['nombre'] ?? $_POST['nombre'] ?? ''));
    $emailNuevo = trim((string) ($datosDecodificados['email'] ?? $_POST['email'] ?? ''));
    $contrasenaPlano = isset($datosDecodificados['password']) ? (string) $datosDecodificados['password'] : (isset($_POST['password']) ? (string) $_POST['password'] : '');
    $emailNormalizado = mb_strtolower($emailNuevo);

    // validate index
    if ($indice === null || !is_int($indice)) {
        responder_json_error('Falta el parámetro "index" para actualizar.', 422);
    }

    if (!isset($listaUsuarios[$indice])) {
        responder_json_error('El índice indicado no existe.', 404);
    }

    // validate fields
    if ($nombreNuevo === '' || $emailNuevo === '') {
        responder_json_error('Los campos "nombre" y "email" son obligatorios.', 422);
    }
    if (!filter_var($emailNuevo, FILTER_VALIDATE_EMAIL)) {
        responder_json_error('El campo "email" no tiene un formato válido.', 422);
    }

    if (mb_strlen($nombreNuevo) > 60) {
        responder_json_error('El campo "nombre" excede los 60 caracteres.', 422);
    }
    if (mb_strlen($emailNuevo) > 120) {
        responder_json_error('El campo "email" excede los 120 caracteres.', 422);
    }

    // check duplicate
    foreach ($listaUsuarios as $idx => $u) {
        if ($idx === $indice) continue;
        if (isset($u['email']) && is_string($u['email']) && mb_strtolower($u['email']) === $emailNormalizado) {
            responder_json_error('Ya existe un usuario con ese email.', 409);
        }
    }

    // update (si no se proporciona password, conservamos la existente)
    $usuarioExistente = $listaUsuarios[$indice];
    $nuevoHash = $usuarioExistente['password'] ?? '';
    if ($contrasenaPlano !== '') {
        if (mb_strlen($contrasenaPlano) < 6) {
            responder_json_error('El campo "password" debe tener al menos 6 caracteres.', 422);
        }
        $nuevoHash = password_hash($contrasenaPlano, PASSWORD_DEFAULT);
    }

    $listaUsuarios[$indice] = [
        'nombre' => $nombreNuevo,
        'email' => $emailNormalizado,
        'password' => $nuevoHash,
    ];

    file_put_contents(
        $rutaArchivoDatosJson,
        json_encode($listaUsuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
    );

    responder_json_exito(sanitizarUsuarios($listaUsuarios));
}

// LOGIN: POST /api.php?action=login  body: { email, password }
if ($metodoHttpRecibido === 'POST' && $accionSolicitada === 'login') {
    $cuerpoBruto = (string) file_get_contents('php://input');
    $datosDecodificados = $cuerpoBruto !== '' ? (json_decode($cuerpoBruto, true) ?? []) : [];
    $email = trim((string) ($datosDecodificados['email'] ?? $_POST['email'] ?? ''));
    $password = (string) ($datosDecodificados['password'] ?? $_POST['password'] ?? '');
    $emailNormalizado = mb_strtolower($email);

    if ($email === '' || $password === '') {
        responder_json_error('Email y password son obligatorios.', 422);
    }

    // buscar usuario
    $usuarioEncontrado = null;
    foreach ($listaUsuarios as $u) {
        if (isset($u['email']) && is_string($u['email']) && mb_strtolower($u['email']) === $emailNormalizado) {
            $usuarioEncontrado = $u;
            break;
        }
    }

    if ($usuarioEncontrado === null) {
        responder_json_error('Credenciales incorrectas.', 401);
    }

    $hashAlmacenado = $usuarioEncontrado['password'] ?? '';
    if (!is_string($hashAlmacenado) || $hashAlmacenado === '' || !password_verify($password, $hashAlmacenado)) {
        responder_json_error('Credenciales incorrectas.', 401);
    }

    // OK: devolvemos usuario sin password
    $usuarioSaneado = $usuarioEncontrado;
    unset($usuarioSaneado['password']);
    responder_json_exito($usuarioSaneado);
}

// 7) Si llegamos aquí, la acción solicitada no está soportada 
responder_json_error('Acción no soportada. Use list | create | delete', 400); 

/** 
 * Comprueba si ya existe un usuario con el email dado (comparación exacta). 
 * 
 * @param array  $usuarios Lista actual en memoria. 
 * @param string $emailNormalizado Email normalizado en minúsculas. 
 */ 
function existeEmailDuplicado(array $usuarios, string $emailNormalizado): bool { 
    foreach ($usuarios as $u) { 
        if (isset($u['email']) && is_string($u['email']) && mb_strtolower($u['email']) === 
$emailNormalizado) { 
            return true; 
        } 
    } 
    return false; 
} 

/**
 * Devuelve una copia de la lista de usuarios sin el campo 'password' para
 * no exponer hashes al cliente.
 *
 * @param array $usuarios
 * @return array
 */
function sanitizarUsuarios(array $usuarios): array {
    $out = [];
    foreach ($usuarios as $u) {
        if (is_array($u)) {
            $v = $u;
            // indicamos si el usuario tiene password configurada (no devolvemos el hash)
            $v['hasPassword'] = isset($u['password']) && $u['password'] !== '';
            if (isset($v['password'])) unset($v['password']);
            $out[] = $v;
        }
    }
    return $out;
}