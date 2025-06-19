<?php
require_once 'conexion.php';
require_once 'auth.php';
require_login(['admin', 'doctor', 'enfermero', 'enfermera']);

$rol = $_SESSION['rol'];
$uid = $_SESSION['uid'];
$hospital_id = $_SESSION['hospital_id'];

$pacientes = [];

try {
    $pacientesRef = $firestore->collection('pacientes')->where('hospital_id', '=', $hospital_id)->documents();

    foreach ($pacientesRef as $doc) {
        $data = $doc->data();
        $data['id'] = $doc->id();

        if ($rol === 'admin') {
            $pacientes[] = $data;
        } elseif ($rol === 'doctor' && ($data['doctor_uid'] ?? null) === $uid) {
            $pacientes[] = $data;
        } elseif (in_array($rol, ['enfermero', 'enfermera'])) {
            $pacientes[] = $data;
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error al obtener pacientes: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pacientes del Hospital</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>👨‍⚕️ Lista de Pacientes</h1>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Nombre</th>
            <th>Edad</th>
            <th>Sexo</th>
            <th>Estado</th>
            <th>Doctor asignado</th>
            <th>Acciones</th>
        </tr>

        <?php foreach ($pacientes as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['nombre'] ?? 'Sin Nombre') ?></td>
                <td><?= htmlspecialchars($p['edad'] ?? '---') ?></td>
                <td><?= htmlspecialchars($p['sexo'] ?? '---') ?></td>
                <td><?= htmlspecialchars($p['estado'] ?? '---') ?></td>
                <td><?= htmlspecialchars($p['doctor_nombre'] ?? 'No asignado') ?></td>
                <td>
                    <a href="detalle_paciente.php?id=<?= $p['id'] ?>">🔍 Ver</a>
                    |
                    <a href="historial_paciente.php?patient_id=<?= $p['id'] ?>">📋 Historial</a>
                    <?php if ($rol === 'admin' || ($rol === 'doctor' && ($p['doctor_uid'] ?? '') === $uid)): ?>
                        |
                        <a href="editar_paciente.php?id=<?= $p['id'] ?>">✏️ Editar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
