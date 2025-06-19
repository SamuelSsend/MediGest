<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin', 'doctor', 'nurse']);

$hospitalId = $_SESSION['hospital_id'];
$uid = $_SESSION['uid'];
$rol = $_SESSION['rol'] ?? '';
$mensaje = '';
$editar_id = $_GET['editar_id'] ?? null;

// Procesar inhabilitación
if (isset($_GET['inhabilitar_id'])) {
    try {
        $pacienteId = $_GET['inhabilitar_id'];

        // Inhabilitar paciente
        $firestore->collection('pacientes')->document($pacienteId)->update([
            ['path' => 'activo', 'value' => false]
        ]);

        // Cancelar citas activas del paciente
        $citas = $firestore->collection('citas')
            ->where('paciente_id', '=', $pacienteId)
            ->where('cancelada', '==', false)
            ->documents();

        foreach ($citas as $cita) {
            if ($cita->exists()) {
                $firestore->collection('citas')->document($cita->id())->update([
                    ['path' => 'cancelada', 'value' => true]
                ]);
            }
        }

        registrar_log($firestore, 'inhabilitar_paciente', "Inhabilitado paciente ID: $pacienteId y canceladas sus citas");
        $mensaje = '⚠️ Paciente inhabilitado y citas canceladas.';
    } catch (Exception $e) {
        $mensaje = '❌ Error al inhabilitar paciente: ' . $e->getMessage();
    }
}

// Procesar edición (solo admin)
if ($rol === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $edad = $_POST['edad'] ?? '';
    $sexo = $_POST['sexo'] ?? '';
    $estado = $_POST['estado'] ?? '';
    $diagnostico = $_POST['diagnostico'] ?? '';
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? date('Y-m-d');
    $doctor_uid = $_POST['doctor_uid'] ?? '';

    $datosPaciente = [
        'nombre' => $nombre,
        'edad' => $edad,
        'sexo' => $sexo,
        'estado' => $estado,
        'diagnostico' => $diagnostico,
        'fecha_ingreso' => $fecha_ingreso,
        'hospital_id' => $hospitalId,
        'doctor_uid' => $doctor_uid
    ];

    try {
        if ($id) {
            $updateData = [];
            foreach ($datosPaciente as $key => $value) {
                $updateData[] = ['path' => $key, 'value' => $value];
            }
            $firestore->collection('pacientes')->document($id)->update($updateData);
            registrar_log($firestore, 'editar_paciente', "Editado paciente ID: $id");
            $mensaje = '✅ Paciente actualizado correctamente.';
        }
    } catch (Exception $e) {
        $mensaje = '❌ Error al guardar paciente: ' . $e->getMessage();
    }
}

// Obtener pacientes activos
$query = $firestore->collection('pacientes')
    ->where('hospital_id', '=', $hospitalId)
    ->where('activo', '==', true);

if ($rol === 'doctor') {
    $query = $query->where('doctor_uid', '=', $uid);
}

$pacientes = $query->documents();

// Obtener doctores
$doctoresMap = [];
$docsDoc = $firestore->collection('usuarios')
    ->where('hospital_id', '=', $hospitalId)
    ->where('rol', '=', 'doctor')
    ->where('activo', '==', true)
    ->documents();
foreach ($docsDoc as $d) {
    $doctoresMap[$d->id()] = $d->data()['nombre'] ?? 'Sin nombre';
}

$editar_paciente = null;
if ($editar_id && $rol === 'admin') {
    foreach ($pacientes as $p) {
        if ($p->id() === $editar_id) {
            $editar_paciente = $p->data();
            $editar_paciente['id'] = $p->id();
            break;
        }
    }
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>🩺 Pacientes</h1>

    <?php if ($mensaje): ?>
        <p class="alert alert-info"><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($rol === 'admin' || $rol === 'doctor'): ?>
        <a href="crear_paciente.php" class="btn btn-success mb-3">➕ Crear Nuevo Paciente</a>
        <a href="pacientes_inactivos.php" class="btn btn-outline-secondary mb-3">Ver Pacientes Inhabilitados</a>
    <?php endif; ?>

    <?php if ($pacientes->isEmpty()): ?>
        <p class="text-muted">No hay pacientes registrados.</p>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Edad</th>
                    <th>Sexo</th>
                    <th>Estado</th>
                    <th>Diagnóstico</th>
                    <th>Fecha Ingreso</th>
                    <th>Doctor Asignado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pacientes as $p): ?>
                    <?php if ($p->exists()):
                        $data = $p->data();
                        $id = $p->id();

                        $fechaFormateada = '-';
                        if (!empty($data['fecha_ingreso'])) {
                            $fechaObj = date_create($data['fecha_ingreso']);
                            if ($fechaObj) {
                                $fechaFormateada = date_format($fechaObj, 'd/m/Y');
                            }
                        }

                        $nombreDoctor = $doctoresMap[$data['doctor_uid']] ?? $data['doctor_uid'] ?? '-';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($data['nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($data['edad'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($data['sexo'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($data['estado'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($data['diagnostico'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($fechaFormateada) ?></td>
                        <td><?= htmlspecialchars($nombreDoctor) ?></td>
                        <td>
                            <a href="detalle_paciente.php?id=<?= urlencode($id) ?>" class="btn btn-sm btn-info">🔍 Ver</a>
                            <?php if ($rol === 'admin'): ?>
                                <a href="editar_paciente_admin.php?id=<?= urlencode($id) ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                            <?php endif; ?>
                            <a href="?inhabilitar_id=<?= $id ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Inhabilitar paciente?');">🚫 Inhabilitar</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($rol === 'admin' && $editar_paciente): ?>
        <h2>Editar Paciente</h2>
        <form method="POST" class="mb-5">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editar_paciente['id']) ?>">
            <div class="mb-3">
                <label>Nombre:</label>
                <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($editar_paciente['nombre'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Edad:</label>
                <input type="number" name="edad" class="form-control" value="<?= htmlspecialchars($editar_paciente['edad'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Sexo:</label>
                <select name="sexo" class="form-select">
                    <option value="Masculino" <?= (isset($editar_paciente['sexo']) && $editar_paciente['sexo'] === 'Masculino') ? 'selected' : '' ?>>Masculino</option>
                    <option value="Femenino" <?= (isset($editar_paciente['sexo']) && $editar_paciente['sexo'] === 'Femenino') ? 'selected' : '' ?>>Femenino</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Estado:</label>
                <input type="text" name="estado" class="form-control" value="<?= htmlspecialchars($editar_paciente['estado'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Diagnóstico:</label>
                <textarea name="diagnostico" class="form-control"><?= htmlspecialchars($editar_paciente['diagnostico'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label>Fecha de Ingreso:</label>
                <input type="date" name="fecha_ingreso" class="form-control" value="<?= htmlspecialchars($editar_paciente['fecha_ingreso'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Doctor asignado:</label>
                <select name="doctor_uid" class="form-select">
                    <option value="">-- Seleccione doctor --</option>
                    <?php foreach ($doctoresMap as $uidDoc => $nombreDoc): ?>
                        <option value="<?= htmlspecialchars($uidDoc) ?>" <?= (isset($editar_paciente['doctor_uid']) && $editar_paciente['doctor_uid'] === $uidDoc) ? 'selected' : '' ?>><?= htmlspecialchars($nombreDoc) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
    <?php endif; ?>
</div>
