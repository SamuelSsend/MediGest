<?php
require_once 'auth.php';
require_once 'conexion.php';
require_login(['admin', 'doctor', 'nurse']);

$hospitalId = $_SESSION['hospital_id'];
$uid = $_SESSION['uid'];

$citas = [];

try {
    $docs = $firestore->collection('citas')
        ->where('hospital_id', '=', $hospitalId)
        ->documents();

    $ahora = strtotime(date('Y-m-d H:i'));

    foreach ($docs as $doc) {
        if ($doc->exists()) {
            $cita = $doc->data();
            $cita['id'] = $doc->id();

            $fecha = $cita['fecha'] ?? '';
            $hora_fin = $cita['hora_fin'] ?? '';
            $cancelada = $cita['cancelada'] ?? false;

            if (!$fecha || !$hora_fin || $cancelada) continue;

            $fin_timestamp = strtotime("$fecha $hora_fin");
            if ($fin_timestamp >= $ahora) continue; // Solo citas pasadas

            $citas[] = $cita;
        }
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error al obtener citas: " . $e->getMessage() . "</div>";
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1 class="mb-4">🕓 Citas Finalizadas</h1>

    <?php if (count($citas) > 0): ?>
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>Paciente</th>
                    <th>Fecha</th>
                    <th>Hora Inicio</th>
                    <th>Hora Fin</th>
                    <th>Doctor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($citas as $cita): ?>
                    <tr>
                        <td><?= htmlspecialchars($cita['paciente_nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($cita['fecha'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($cita['hora_inicio'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($cita['hora_fin'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($cita['doctor_nombre'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-info">No hay citas finalizadas registradas.</div>
    <?php endif; ?>
</div>
