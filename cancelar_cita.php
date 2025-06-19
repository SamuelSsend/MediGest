<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin', 'doctor', 'nurse']);

$hospital_id = $_SESSION['hospital_id'];
$uid = $_SESSION['uid'];
$rol = $_SESSION['rol'] ?? '';
$id = $_GET['id'] ?? null;

if (!$id) {
    die("❌ ID no proporcionado.");
}

try {
    $ref = $firestore->collection('citas')->document($id);
    $snap = $ref->snapshot();

    if (!$snap->exists()) {
        die("❌ La cita no existe.");
    }

    $cita = $snap->data();

    if (($cita['hospital_id'] ?? null) !== $hospital_id) {
        die("❌ Esta cita no pertenece a tu hospital.");
    }

    if ($rol === 'doctor' && ($cita['doctor_uid'] ?? '') !== $uid) {
        die("❌ No autorizado para cancelar esta cita.");
    }

    $ref->update([
        ['path' => 'cancelada', 'value' => true]
    ]);

    registrar_log($firestore, 'cancelar_cita', "Cita cancelada ID: $id");

    header("Location: ver_citas.php?msg=cancelada");
    exit;
} catch (Exception $e) {
    die("❌ Error al cancelar cita: " . $e->getMessage());
}
