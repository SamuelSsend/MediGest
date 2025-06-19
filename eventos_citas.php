<?php
require_once 'auth.php';
require_login(['admin', 'doctor', 'nurse']);

use Kreait\Firebase\Factory;

$uid = $_SESSION['uid'];
$rol = $_SESSION['rol'];
$hospitalId = $_SESSION['hospital_id'];

$factory = (new Factory)->withServiceAccount('firebase_config.json');
$firestore = $factory->createFirestore()->database();

// Obtener citas desde Firestore
$citasQuery = $firestore->collection('citas')
    ->where('hospital_id', '=', $hospitalId);

if ($rol === 'doctor') {
    $citasQuery = $citasQuery->where('doctor_uid', '=', $uid);
}

$citas = $citasQuery->documents();
$eventos = [];
$hoy = date('Y-m-d');

foreach ($citas as $cita) {
    if (!$cita->exists()) continue;

    $data = $cita->data();

    // Filtrar canceladas o pasadas
    $cancelada = $data['cancelada'] ?? false;
    $fecha = $data['fecha'] ?? null;

    if ($cancelada || !$fecha || strtotime($fecha) < strtotime($hoy)) {
        continue;
    }

    $horaInicio = $data['hora_inicio'] ?? '00:00';
    $horaFin = $data['hora_fin'] ?? '00:00';

    $eventos[] = [
        'title' => $data['paciente_nombre'] ?? 'Cita médica',
        'start' => $fecha . 'T' . $horaInicio,
        'end' => $fecha . 'T' . $horaFin,
        'backgroundColor' => '#007bff',
        'borderColor' => '#007bff',
        'textColor' => '#fff'
    ];
}

header('Content-Type: application/json');
echo json_encode($eventos);
