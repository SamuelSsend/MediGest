<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin', 'doctor', 'nurse']);

$hospital_id = $_SESSION['hospital_id'];
$uid = $_SESSION['uid'];
$rol = $_SESSION['rol'] ?? '';
$mensaje = '';

// Obtener lista de pacientes activos del hospital
$pacientesSnap = $firestore->collection('pacientes')
    ->where('hospital_id', '=', $hospital_id)
    ->where('activo', '==', true);

if ($rol === 'doctor') {
    $pacientesSnap = $pacientesSnap->where('doctor_uid', '=', $uid);
}

$pacientesDocs = $pacientesSnap->documents();
$pacientes = [];
foreach ($pacientesDocs as $doc) {
    $data = $doc->data();
    $pacientes[] = [
        'id' => $doc->id(),
        'nombre' => $data['nombre'] ?? 'Sin nombre'
    ];
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paciente_id = $_POST['paciente_id'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $estado = $_POST['estado'] ?? 'Pendiente';

    if ($paciente_id && $fecha && $hora_inicio && $hora_fin) {
        if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
            $mensaje = '❌ La fecha no puede ser anterior al día actual.';
        } elseif (strtotime($hora_inicio) >= strtotime($hora_fin)) {
            $mensaje = '❌ La hora de inicio debe ser menor que la hora de fin.';
        } else {
            try {
                $pacienteDoc = $firestore->collection('pacientes')->document($paciente_id)->snapshot();
                $dataPaciente = $pacienteDoc->data();
                $nombrePaciente = $dataPaciente['nombre'] ?? 'Paciente';
                $doctorUid = $dataPaciente['doctor_uid'] ?? null;

                if (!$doctorUid) {
                    $mensaje = '❌ El paciente seleccionado no tiene un doctor asignado.';
                } else {
                    $doctorDoc = $firestore->collection('usuarios')->document($doctorUid)->snapshot();
                    $doctorNombre = $doctorDoc->exists() ? ($doctorDoc->data()['nombre'] ?? 'Doctor') : 'Doctor';

                    // Verificar si hay solapamiento con otras citas del mismo doctor ese día
                    $citasSnap = $firestore->collection('citas')
                        ->where('doctor_uid', '=', $doctorUid)
                        ->where('fecha', '=', $fecha)
                        ->documents();

                    $haySolapamiento = false;
                    $nuevoInicio = strtotime($hora_inicio);
                    $nuevoFin = strtotime($hora_fin);

                    foreach ($citasSnap as $cita) {
                        $citaData = $cita->data();
                        $inicioExistente = strtotime($citaData['hora_inicio']);
                        $finExistente = strtotime($citaData['hora_fin']);

                        if ($nuevoInicio < $finExistente && $nuevoFin > $inicioExistente) {
                            $haySolapamiento = true;
                            break;
                        }
                    }

                    if ($haySolapamiento) {
                        $mensaje = '❌ Ya existe una cita para este doctor en ese horario.';
                    } else {
                        $firestore->collection('citas')->add([
                            'hospital_id' => $hospital_id,
                            'paciente_id' => $paciente_id,
                            'paciente_nombre' => $nombrePaciente,
                            'doctor_uid' => $doctorUid,
                            'doctor_nombre' => $doctorNombre,
                            'fecha' => $fecha,
                            'hora_inicio' => $hora_inicio,
                            'hora_fin' => $hora_fin,
                            'estado' => $estado,
                            'cancelada' => false
                        ]);

                        registrar_log($firestore, 'crear_cita', "Cita creada para paciente $paciente_id");
                        header("Location: ver_citas.php?msg=cita_creada");
                        exit;
                    }
                }
            } catch (Exception $e) {
                $mensaje = '❌ Error al crear cita: ' . $e->getMessage();
            }
        }
    } else {
        $mensaje = '❌ Todos los campos son obligatorios.';
    }
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>📅 Crear Cita Médica</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" class="mb-5">
        <div class="mb-3">
            <label>Paciente:</label>
            <select name="paciente_id" class="form-select" required>
                <option value="">-- Seleccione Paciente --</option>
                <?php foreach ($pacientes as $p): ?>
                    <option value="<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Fecha:</label>
            <input type="date" name="fecha" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Hora de Inicio:</label>
            <input type="time" name="hora_inicio" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Hora de Fin:</label>
            <input type="time" name="hora_fin" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">Crear Cita</button>
    </form>
</div>
