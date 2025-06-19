<?php
require_once 'auth.php';
require_once 'conexion.php';
require_login(['admin', 'doctor', 'nurse']);

$hospitalId = $_SESSION['hospital_id'];
$rol = $_SESSION['rol'];
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

            if (!$fecha || !$hora_fin || $cancelada) {
                continue;
            }

            // Ignorar citas finalizadas
            $fin_timestamp = strtotime("$fecha $hora_fin");
            if ($fin_timestamp < $ahora) {
                continue;
            }

            // Solo citas del doctor conectado
            if ($rol === 'doctor' && ($cita['doctor_uid'] ?? '') !== $uid) {
                continue;
            }

            // Verificar si el paciente está activo
            $pacienteId = $cita['paciente_id'] ?? null;
            if (!$pacienteId) continue;

            $pacienteSnap = $firestore->collection('pacientes')->document($pacienteId)->snapshot();
            if (!$pacienteSnap->exists()) continue;

            $pacienteData = $pacienteSnap->data();
            if (!($pacienteData['activo'] ?? true)) continue;

            $citas[] = $cita;
        }
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error al obtener citas: " . $e->getMessage() . "</div>";
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1 class="mb-4">📅 Citas Médicas</h1>
    <div class="container mb-3">
        <a href="crear_citas.php" class="btn btn-success">➕ Crear nueva cita</a>
    </div>

    <?php if (count($citas) > 0): ?>
        <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>Paciente</th>
                <th>Fecha</th>
                <th>Hora Inicio</th>
                <th>Hora Fin</th>
                <th>Doctor</th>
                <th>Acciones</th>
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
                    <td>
                        <a href="editar_cita.php?id=<?= $cita['id'] ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                        <a href="cancelar_cita.php?id=<?= $cita['id'] ?>" class="btn btn-sm btn-danger"
                        onclick="return confirm('¿Seguro que deseas cancelar esta cita?');">❌ Cancelar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="alert alert-info">No hay citas próximas disponibles.</div>
    <?php endif; ?>
</div>
