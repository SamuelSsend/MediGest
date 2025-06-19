<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin']);

$hospitalId = $_SESSION['hospital_id'];
$uid_admin = $_SESSION['uid'];
$nombre_admin = $_SESSION['nombre'] ?? 'Administrador';
$mensaje = '';

// Obtener doctores disponibles en el hospital
$doctoresDisponibles = [];
$usuariosQuery = $firestore->collection('usuarios')
    ->where('hospital_id', '=', $hospitalId)
    ->where('rol', '=', 'doctor')
    ->documents();

foreach ($usuariosQuery as $userDoc) {
    $uid = $userDoc->id();
    $data = $userDoc->data();
    $doctoresDisponibles[$uid] = $data['nombre'] ?? $uid;
}

// Bloquear vista si no hay doctores disponibles
if (empty($doctoresDisponibles)) {
    $mensaje = "❌ No se pueden aceptar traslados porque no hay doctores disponibles en este hospital.";
}

// Obtener nombres de todos los hospitales
$nombresHospitales = [];
$hospitalDocs = $firestore->collection('hospitales')->documents();
foreach ($hospitalDocs as $hospDoc) {
    $hid = $hospDoc->id();
    $datos = $hospDoc->data();
    $nombresHospitales[$hid] = $datos['nombre'] ?? $hid;
}

// Aceptar o rechazar traslado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'], $_POST['traslado_id'])) {
    $trasladoId = $_POST['traslado_id'];
    $accion = $_POST['accion'];

    $trasladoRef = $firestore->collection('traslados')->document($trasladoId);
    $trasladoSnap = $trasladoRef->snapshot();

    if ($trasladoSnap->exists()) {
        $traslado = $trasladoSnap->data();

        if ($traslado['hospital_destino'] === $hospitalId && $traslado['estado'] === 'pendiente') {
            if ($accion === 'aceptar') {
                $pacienteId = $traslado['paciente_id'];
                $doctorAsignado = $_POST['doctor_uid'] ?? null;

                if (!$doctorAsignado || !isset($doctoresDisponibles[$doctorAsignado])) {
                    $mensaje = "❌ Debes seleccionar un doctor válido para asignar al paciente.";
                } else {
                    $firestore->collection('pacientes')->document($pacienteId)->update([
                        ['path' => 'hospital_id', 'value' => $hospitalId],
                        ['path' => 'doctor_uid', 'value' => $doctorAsignado]
                    ]);

                    $trasladoRef->update([
                        ['path' => 'estado', 'value' => 'aceptado'],
                        ['path' => 'fecha_aprobacion', 'value' => new \Google\Cloud\Core\Timestamp(new DateTime())]
                    ]);

                    registrar_log($firestore, 'aceptar_traslado', "Traslado aceptado por $nombre_admin para paciente $pacienteId (doctor asignado: $doctorAsignado)");
                    $mensaje = "✅ Traslado aceptado y paciente actualizado.";
                }
            } elseif ($accion === 'rechazar') {
                $trasladoRef->update([
                    ['path' => 'estado', 'value' => 'rechazado']
                ]);

                registrar_log($firestore, 'rechazar_traslado', "Traslado rechazado por $nombre_admin");
                $mensaje = "⚠️ Traslado rechazado correctamente.";
            }
        }
    }
}

// Obtener traslados pendientes recibidos
$traslados = $firestore->collection('traslados')
    ->where('hospital_destino', '=', $hospitalId)
    ->where('estado', '=', 'pendiente')
    ->documents();

// Obtener nombres de paciente y doctor
$nombresPacientes = [];
$nombresDoctores = [];
$trasladosLista = [];
$pacientesProcesados = [];

foreach ($traslados as $trasladoDoc) {
    $data = $trasladoDoc->data();
    $data['id'] = $trasladoDoc->id();

    $pid = $data['paciente_id'] ?? '';
    $did = $data['doctor_uid'] ?? '';
    $hid_origen = $data['hospital_origen'] ?? '';

    // Evitar duplicados por paciente
    if (in_array($pid, $pacientesProcesados)) continue;
    $pacientesProcesados[] = $pid;

    if (!isset($nombresPacientes[$pid])) {
        $pacienteDoc = $firestore->collection('pacientes')->document($pid)->snapshot();
        $nombresPacientes[$pid] = $pacienteDoc->exists() ? ($pacienteDoc['nombre'] ?? $pid) : $pid;
    }

    if (!isset($nombresDoctores[$did])) {
        $doctorDoc = $firestore->collection('usuarios')->document($did)->snapshot();
        $nombresDoctores[$did] = $doctorDoc->exists() ? ($doctorDoc['nombre'] ?? $did) : $did;
    }

    $data['nombre_paciente'] = $nombresPacientes[$pid];
    $data['nombre_doctor'] = $nombresDoctores[$did];
    $data['nombre_hospital_origen'] = $nombresHospitales[$hid_origen] ?? $hid_origen;

    $trasladosLista[] = $data;
}

include 'layout.php';
?>

<div class="main">
    <h1>🚑 Solicitudes de Traslado Recibidas</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-info"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if (empty($trasladosLista)): ?>
        <p>No hay solicitudes de traslado pendientes.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Paciente</th>
                        <th>Hospital Origen</th>
                        <th>Solicitado por</th>
                        <th>Fecha</th>
                        <th>Motivo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trasladosLista as $data): ?>
                        <tr>
                            <td><?= htmlspecialchars($data['nombre_paciente'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($data['nombre_hospital_origen'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($data['nombre_doctor'] ?? '-') ?></td>
                            <td>
                                <?php
                                try {
                                    $fecha = $data['fecha_solicitud']->get()->format('d/m/Y');
                                    echo htmlspecialchars($fecha);
                                } catch (Exception) {
                                    echo "-";
                                }
                                ?>
                            </td>
                            <td><?= nl2br(htmlspecialchars($data['motivo'] ?? '-')) ?></td>
                            <td>
                                <form method="POST" class="d-flex flex-wrap align-items-center gap-2">
                                    <input type="hidden" name="traslado_id" value="<?= htmlspecialchars($data['id']) ?>">
                                    <select name="doctor_uid" class="form-select form-select-sm" required>
                                        <?php foreach ($doctoresDisponibles as $uid => $nombre): ?>
                                            <option value="<?= htmlspecialchars($uid) ?>"><?= htmlspecialchars($nombre) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button name="accion" value="aceptar" class="btn btn-success btn-sm">Aceptar</button>
                                    <button name="accion" value="rechazar" class="btn btn-danger btn-sm">Rechazar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
