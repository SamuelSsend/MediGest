<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php'; // ✅ Añadido para usar registrar_log
require_login(['admin']);

$usuario_id = $_GET['id'] ?? null;

if (!$usuario_id) {
    die("❌ ID no proporcionado.");
}

try {
    $usuario = $firestore->collection('usuarios')->document($usuario_id)->snapshot();
    if (!$usuario->exists()) {
        throw new Exception("Usuario no encontrado.");
    }

    $firestore->collection('usuarios')->document($usuario_id)->update([
        ['path' => 'activo', 'value' => true]
    ]);

    registrar_log($firestore, 'habilitar_usuario', "Se habilitó el usuario $usuario_id");
    header("Location: ver_personal.php?msg=habilitado");
    exit;
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
