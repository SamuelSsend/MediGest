<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin', 'doctor', 'nurse']);

$hospital_id = $_SESSION['hospital_id'];
$uid = $_SESSION['uid'];
$rol = $_SESSION['rol'] ?? '';
$mensaje = '';

$id = $_GET['id'] ?? null;
$citaExistente = null;

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

// Cargar cita existente
if ($id) {
    $snap = $firestore->collection('citas')->document($id)->snapshot();
    if ($snap->exists()) {
        $citaExistente = $snap->data();
        if ($rol === 'doctor' && ($citaExistente['doctor_uid'] ?? '') !== $uid) {
            die("❌ No autorizado.");
        }
    } else {
        die("❌ Cita no encontrada.");
    }
}

// Procesar edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $citaExistente) {
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

                $doctor_uid = $dataPaciente['doctor_uid'] ?? null;
                if (!$doctor_uid) {
                    $mensaje = '❌ El paciente no tiene un doctor asignado.';
                } else {
                    $doctorDoc = $firestore->collection('usuarios')->document($doctor_uid)->snapshot();
                    $doctor_nombre = $doctorDoc->exists() ? ($doctorDoc->data()['nombre'] ?? 'Doctor') : 'Doctor';

                    $firestore->collection('citas')->document($id)->update([
                        ['path' => 'paciente_id', 'value' => $paciente_id],
                        ['path' => 'paciente_nombre', 'value' => $nombrePaciente],
                        ['path' => 'fecha', 'value' => $fecha],
                        ['path' => 'hora_inicio', 'value' => $hora_inicio],
                        ['path' => 'hora_fin', 'value' => $hora_fin],
                        ['path' => 'estado', 'value' => $estado],
                        ['path' => 'doctor_uid', 'value' => $doctor_uid],
                        ['path' => 'doctor_nombre', 'value' => $doctor_nombre],
                    ]);

                    registrar_log($firestore, 'editar_cita', "Cita actualizada ID: $id");
                    header("Location: ver_citas.php?msg=editada");
                    exit;
                }
            } catch (Exception $e) {
                $mensaje = '❌ Error al editar cita: ' . $e->getMessage();
            }
        }
    } else {
        $mensaje = '❌ Todos los campos son obligatorios.';
    }
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>✏️ Editar Cita</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($citaExistente): ?>
        <form method="POST" class="mb-5">
            <div class="mb-3">
                <label>Paciente:</label>
                <select name="paciente_id" class="form-select" required>
                    <option value="">-- Seleccione Paciente --</option>
                    <?php foreach ($pacientes as $p): ?>
                        <option value="<?= htmlspecialchars($p['id']) ?>" <?= ($citaExistente['paciente_id'] === $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label>Fecha:</label>
                <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($citaExistente['fecha'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label>Hora de Inicio:</label>
                <input type="time" name="hora_inicio" class="form-control" value="<?= htmlspecialchars($citaExistente['hora_inicio'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label>Hora de Fin:</label>
                <input type="time" name="hora_fin" class="form-control" value="<?= htmlspecialchars($citaExistente['hora_fin'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label>Estado:</label>
                <select name="estado" class="form-select">
                    <option value="Pendiente" <?= ($citaExistente['estado'] === 'Pendiente') ? 'selected' : '' ?>>Pendiente</option>
                    <option value="Completada" <?= ($citaExistente['estado'] === 'Completada') ? 'selected' : '' ?>>Completada</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </form>
    <?php endif; ?>
</div>
