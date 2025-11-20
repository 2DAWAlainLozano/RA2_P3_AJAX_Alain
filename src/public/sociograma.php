<?php
require_once __DIR__ . '/../auth.php';
require_role('user');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sociograma</title>
</head>
<body>
    <h1>Bienvenido al Sociograma</h1>
    <p>Hola, <?= htmlspecialchars($_SESSION['user']['nombre']) ?>. Tienes acceso de usuario.</p>
    <p><a href="Logout.php">Cerrar Sesión</a></p>
</body>
</html>
