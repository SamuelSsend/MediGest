<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php'; // ✅ Para usar registrar_log
require_login(['admin']);

$usuario_id = $_GET['id'] ?? null;
$hospital_id = $_SESSION['hospital_id'];

if (!$usuario_id) {
    die("❌ ID no proporcionado.");
}

try {
    $usuario = $firestore->collection('usuarios')->document($usuario_id)->snapshot();
    if (!$usuario->exists()) {
        throw new Exception("Usuario no encontrado.");
    }

    $datos = $usuario->data();
    $rol = $datos['rol'] ?? '';

    // Evitar deshabilitar al último administrador
    if ($rol === 'admin') {
        $adminsActivos = $firestore->collection('usuarios')
            ->where('hospital_id', '==', $hospital_id)
            ->where('rol', '==', 'admin')
            ->where('activo', '==', true)
            ->documents();

        if ($adminsActivos->size() <= 1) {
            header("Location: ver_personal.php?error=ultimo_admin");
            exit;
        }
    }

    // Evitar deshabilitar a doctores con pacientes asignados
    if ($rol === 'doctor') {
        $pacientesAsignados = $firestore->collection('pacientes')
            ->where('hospital_id', '==', $hospital_id)
            ->where('doctor_uid', '==', $usuario_id)
            ->where('activo', '==', true)
            ->documents();

        if ($pacientesAsignados->size() > 0) {
            header("Location: ver_personal.php?error=doctor_con_pacientes");
            exit;
        }
    }

    $firestore->collection('usuarios')->document($usuario_id)->update([
        ['path' => 'activo', 'value' => false]
    ]);

    registrar_log($firestore, 'inhabilitar_usuario', "Se inhabilitó el usuario $usuario_id");
    header("Location: ver_personal.php?msg=inhabilitado");
    exit;
} catch (Exception $e) {
    header("Location: ver_personal.php?error=" . urlencode($e->getMessage()));
    exit;
}

