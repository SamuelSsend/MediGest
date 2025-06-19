<?php
require_once 'auth.php';
require_once 'conexion.php';
require_login(['admin', 'doctor', 'nurse']);

$rol = $_SESSION['rol'];
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$hospital_id = $_SESSION['hospital_id'];
$mensaje = '';

try {
    // Cargar pacientes
    $pacientesQuery = $firestore->collection('pacientes')
        ->where('hospital_id', '=', $hospital_id)
        ->where('activo', '==', true)
        ->documents();

    $pacientes = [];
    foreach ($pacientesQuery as $doc) {
        if ($doc->exists()) {
            $pacientes[] = $doc->data();
        }
    }

    if ($rol === 'doctor') {
        $uid = $_SESSION['uid'];
        $pacientes = array_filter($pacientes, fn($p) => ($p['doctor_uid'] ?? '') === $uid);
    }

    // Cargar próximas citas
    $citasQuery = $firestore->collection('citas')
        ->where('hospital_id', '=', $hospital_id)
        ->documents();

    $citas = [];
    $ahora = strtotime(date('Y-m-d H:i'));
    foreach ($citasQuery as $doc) {
        if ($doc->exists()) {
            $cita = $doc->data();
            $fecha = $cita['fecha'] ?? '';
            $hora_fin = $cita['hora_fin'] ?? '';
            $cancelada = $cita['cancelada'] ?? false;

            if (!$fecha || !$hora_fin || $cancelada) continue;

            $fin_ts = strtotime("$fecha $hora_fin");
            if ($fin_ts >= $ahora) {
                $citas[] = $cita;
            }
        }
    }

    // Cargar traslados pendientes solo para admin
    $traslados = [];
    $pacientesUnicos = [];
    if ($rol === 'admin') {
        $trasladosQuery = $firestore->collection('traslados')
            ->where('hospital_destino', '=', $hospital_id)
            ->where('estado', '=', 'pendiente')
            ->documents();

        foreach ($trasladosQuery as $doc) {
            if ($doc->exists()) {
                $traslado = $doc->data();
                $pid = $traslado['paciente_id'] ?? '';
                if (!in_array($pid, $pacientesUnicos)) {
                    $traslados[] = $traslado;
                    $pacientesUnicos[] = $pid;
                }
            }
        }

        // Mapear nombres de pacientes y doctores
        $nombresPacientes = [];
        $nombresDoctores = [];
        foreach ($traslados as &$tras) {
            $pid = $tras['paciente_id'];
            $did = $tras['doctor_uid'];

            if (!isset($nombresPacientes[$pid])) {
                $pdoc = $firestore->collection('pacientes')->document($pid)->snapshot();
                $nombresPacientes[$pid] = $pdoc->exists() ? ($pdoc['nombre'] ?? $pid) : $pid;
            }

            if (!isset($nombresDoctores[$did])) {
                $ddoc = $firestore->collection('usuarios')->document($did)->snapshot();
                $nombresDoctores[$did] = $ddoc->exists() ? ($ddoc['nombre'] ?? $did) : $did;
            }

            $tras['nombre_paciente'] = $nombresPacientes[$pid];
            $tras['nombre_doctor'] = $nombresDoctores[$did];
        }
    }
} catch (Exception $e) {
    $mensaje = "Error cargando datos: " . $e->getMessage();
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1 class="mb-4">Bienvenido, <?= htmlspecialchars($nombre) ?></h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <section class="mb-5">
        <h2 class="h4 mb-3">📋 <?= $rol === 'doctor' ? 'Mis Pacientes' : 'Pacientes Registrados' ?> (<?= count($pacientes) ?>)</h2>
        <?php if (empty($pacientes)): ?>
            <p>No hay pacientes registrados en tu hospital.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($pacientes as $pac): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($pac['nombre'] ?? 'Sin nombre') ?>
                        <span class="badge bg-info">Estado: <?= htmlspecialchars($pac['estado'] ?? '-') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($rol === 'admin'): ?>
        <section class="mb-5">
            <h2 class="h4 mb-3">🚨 Traslados Pendientes</h2>
            <?php if (empty($traslados)): ?>
                <p>No hay traslados pendientes.</p>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 g-3">
                    <?php foreach ($traslados as $tra): ?>
                        <div class="col">
                            <div class="card border-warning shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($tra['nombre_paciente']) ?></h5>
                                    <p class="card-text">
                                        Solicitado por: <strong><?= htmlspecialchars($tra['nombre_doctor']) ?></strong><br>
                                        Fecha: <?= htmlspecialchars($tra['fecha_solicitud']->get()->format('d/m/Y') ?? '-') ?>
                                    </p>
                                    <a href="traslados_recibidos.php" class="btn btn-sm btn-warning">Gestionar</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h4">📅 Próximas Citas Médicas</h2>
            <a href="citas_finalizadas.php" class="btn btn-outline-secondary btn-sm">📂 Ver citas finalizadas</a>
        </div>
        <?php if (empty($citas)): ?>
            <p>No hay citas próximas.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($citas as $cita): ?>
                    <li class="list-group-item">
                        <?= htmlspecialchars($cita['fecha'] ?? '-') ?> – 
                        <?= htmlspecialchars($cita['hora_inicio'] ?? '') ?> a <?= htmlspecialchars($cita['hora_fin'] ?? '') ?> – 
                        Paciente: <?= htmlspecialchars($cita['paciente_nombre'] ?? '-') ?> – 
                        Doctor: <?= htmlspecialchars($cita['doctor_nombre'] ?? '-') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
