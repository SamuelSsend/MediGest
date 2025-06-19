<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin', 'doctor', 'nurse']);

$paciente_id = $_GET['id'] ?? null;
$hospital_id = $_SESSION['hospital_id'];

if (!$paciente_id) {
    die("❌ El sistema no ha recibido un ID de paciente válido. Intenta acceder nuevamente desde la lista de pacientes.");
}

try {
    $pacienteRef = $firestore->collection('pacientes')->document($paciente_id);
    $pacienteSnap = $pacienteRef->snapshot();

    if (!$pacienteSnap->exists()) {
        throw new Exception("❌ El paciente no existe en la base de datos.");
    }

    if (($pacienteSnap['hospital_id'] ?? null) !== $hospital_id) {
        throw new Exception("❌ El paciente no pertenece a este hospital.");
    }

    $pacienteRef->update([
        ['path' => 'activo', 'value' => true]
    ]);

    registrar_log($firestore, 'habilitar_paciente', "Se habilitó el paciente $paciente_id");
    header("Location: mis_pacientes.php?msg=habilitado");
    exit;
} catch (Exception $e) {
    die("❌ Error al habilitar paciente: " . $e->getMessage());
}
