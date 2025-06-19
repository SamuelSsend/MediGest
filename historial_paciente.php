<?php
require 'vendor/autoload.php';
session_start();

use Kreait\Firebase\Factory;

if (!isset($_SESSION['uid']) || !isset($_GET['patient_id'])) {
    header("Location: login.php");
    exit();
}

$patientId = $_GET['patient_id'];
$mensaje = '';
$historial = [];

$factory = (new Factory)->withServiceAccount('firebase_config.json');
$firestore = $factory->createFirestore()->database();

$pacienteSnap = $firestore->collection('pacientes')->document($patientId)->snapshot();

if (!$pacienteSnap->exists()) {
    die("❌ No se ha podido encontrar el paciente solicitado. Verifica el ID o contacta con soporte.");
}

$paciente = $pacienteSnap->data();
$nombre = $paciente['nombre'] ?? 'Sin nombre';
$edad = $paciente['edad'] ?? '-';
$sexo = $paciente['sexo'] ?? '-';
$estado = $paciente['estado'] ?? '-';
$notas = $paciente['notas'] ?? [];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>📋 Historial Clínico de <?= htmlspecialchars($nombre) ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>📋 Historial Clínico de <?= htmlspecialchars($nombre) ?></h1>
    <p>Edad: <?= htmlspecialchars($edad) ?> | Sexo: <?= htmlspecialchars($sexo) ?> | Estado: <?= htmlspecialchars($estado) ?></p>

    <?php if (empty($notas)): ?>
        <p>No hay registros en el historial clínico.</p>
    <?php else: ?>
        <table border="1" cellpadding="8">
            <tr>
                <th>Fecha</th>
                <th>Autor</th>
                <th>Nota</th>
            </tr>
            <?php foreach (array_reverse($notas) as $nota): ?>
                <tr>
                    <td><?= htmlspecialchars($nota['fecha'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($nota['autor'] ?? '-') ?></td>
                    <td><?= nl2br(htmlspecialchars($nota['contenido'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <p><a href="detalle_paciente.php?id=<?= $patientId ?>">← Volver al detalle del paciente</a></p>
</body>
</html>
