<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin']);

$hospitalId = $_SESSION['hospital_id'];
$mensaje = '';
$editar_id = $_GET['id'] ?? null;

if (!$editar_id) {
    die("❌ No se proporcionó un ID de paciente válido.");
}

try {
    $docSnap = $firestore->collection('pacientes')->document($editar_id)->snapshot();
    if (!$docSnap->exists()) {
        die("❌ El paciente no existe.");
    }
    $paciente = $docSnap->data();
    $paciente['id'] = $editar_id;

    // Obtener doctores activos del hospital
    $doctoresMap = [];
    $docsDoc = $firestore->collection('usuarios')
        ->where('hospital_id', '=', $hospitalId)
        ->where('rol', '=', 'doctor')
        ->where('activo', '==', true)
        ->documents();
    foreach ($docsDoc as $d) {
        $doctoresMap[$d->id()] = $d->data()['nombre'] ?? 'Sin nombre';
    }

    // Procesar actualización
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $edad = trim($_POST['edad'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $diagnostico = trim($_POST['diagnostico'] ?? '');
        $fecha_ingreso = $_POST['fecha_ingreso'] ?? date('Y-m-d');
        $doctor_uid = trim($_POST['doctor_uid'] ?? '');

        if ($nombre === '' || $edad === '' || $sexo === '' || $estado === '' || $doctor_uid === '') {
            $mensaje = '❌ Por favor, complete todos los campos obligatorios (incluido el doctor asignado).';
        } else {
            $updateData = [
                ['path' => 'nombre', 'value' => $nombre],
                ['path' => 'edad', 'value' => $edad],
                ['path' => 'sexo', 'value' => $sexo],
                ['path' => 'estado', 'value' => $estado],
                ['path' => 'diagnostico', 'value' => $diagnostico],
                ['path' => 'fecha_ingreso', 'value' => $fecha_ingreso],
                ['path' => 'doctor_uid', 'value' => $doctor_uid]
            ];

            $firestore->collection('pacientes')->document($editar_id)->update($updateData);
            registrar_log($firestore, 'editar_paciente_admin', "Paciente $editar_id editado por administrador.");
            $mensaje = '✅ Paciente actualizado correctamente.';

            // Refrescar datos locales
            $paciente = array_merge($paciente, [
                'nombre' => $nombre,
                'edad' => $edad,
                'sexo' => $sexo,
                'estado' => $estado,
                'diagnostico' => $diagnostico,
                'fecha_ingreso' => $fecha_ingreso,
                'doctor_uid' => $doctor_uid
            ]);
        }
    }

} catch (Exception $e) {
    $mensaje = '❌ Error: ' . $e->getMessage();
}
?>

<?php include 'layout.php'; ?>
<div class="main">
    <h1>✏️ Editar Paciente (Admin)</h1>
    <?php if ($mensaje): ?>
        <p class="alert alert-info"><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>
    <form method="POST" class="mb-5">
        <div class="mb-3">
            <label>Nombre:</label>
            <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($paciente['nombre'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label>Edad:</label>
            <input type="number" name="edad" class="form-control" required value="<?= htmlspecialchars($paciente['edad'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label>Sexo:</label>
            <select name="sexo" class="form-select" required>
                <option value="Masculino" <?= ($paciente['sexo'] ?? '') === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                <option value="Femenino" <?= ($paciente['sexo'] ?? '') === 'Femenino' ? 'selected' : '' ?>>Femenino</option>
            </select>
        </div>
        <div class="mb-3">
            <label>Estado:</label>
            <input type="text" name="estado" class="form-control" required value="<?= htmlspecialchars($paciente['estado'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label>Diagnóstico:</label>
            <textarea name="diagnostico" class="form-control"><?= htmlspecialchars($paciente['diagnostico'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
            <label>Fecha de Ingreso:</label>
            <input type="date" name="fecha_ingreso" class="form-control" value="<?= htmlspecialchars($paciente['fecha_ingreso'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label>Doctor asignado:</label>
            <select name="doctor_uid" class="form-select" required>
                <option value="">-- Seleccione doctor --</option>
                <?php foreach ($doctoresMap as $uidDoc => $nombreDoc): ?>
                    <option value="<?= htmlspecialchars($uidDoc) ?>" <?= ($paciente['doctor_uid'] ?? '') === $uidDoc ? 'selected' : '' ?>>
                        <?= htmlspecialchars($nombreDoc) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Actualizar</button>
        <a href="mis_pacientes.php" class="btn btn-secondary">Volver</a>
    </form>
</div>
