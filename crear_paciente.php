<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin', 'doctor']);

$hospital_id = $_SESSION['hospital_id'];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $edad = $_POST['edad'] ?? '';
    $sexo = $_POST['sexo'] ?? '';
    $estado = $_POST['estado'] ?? '';
    $diagnostico = $_POST['diagnostico'] ?? '';
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? date('Y-m-d');
    $doctor_uid = $_POST['doctor_uid'] ?? '';
    $dni = strtoupper(trim($_POST['dni'] ?? ''));

    try {
        if (empty($estado) || empty($diagnostico)) {
            $mensaje = "❌ El estado y el diagnóstico son campos obligatorios.";
        } elseif (!preg_match('/^\d{8}[A-Z]$/', $dni)) {
            $mensaje = "❌ DNI no válido. Debe tener 8 números seguidos de una letra.";
        } else {
            $duplicados = $firestore->collection('pacientes')
                ->where('hospital_id', '=', $hospital_id)
                ->where('dni', '=', $dni)
                ->documents();

            if (!$duplicados->isEmpty()) {
                $mensaje = "⚠️ Ya existe un paciente con este DNI registrado.";
            } else {
                $datosPaciente = [
                    'nombre' => $nombre,
                    'edad' => (int)$edad,
                    'sexo' => $sexo,
                    'estado' => $estado,
                    'diagnostico' => $diagnostico,
                    'fecha_ingreso' => $fecha_ingreso,
                    'hospital_id' => $hospital_id,
                    'doctor_uid' => $doctor_uid,
                    'dni' => $dni,
                    'activo' => true
                ];

                $firestore->collection('pacientes')->add($datosPaciente);

                registrar_log($firestore, 'crear_paciente', "Creación del paciente: $nombre");
                $mensaje = "✅ Paciente creado correctamente.";
            }
        }
    } catch (Exception $e) {
        $mensaje = "❌ Error al crear paciente: " . $e->getMessage();
    }
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>Crear Paciente</h1>

    <?php if ($mensaje): ?>
        <div class="alert <?= strpos($mensaje, '✅') !== false ? 'alert-success' : 'alert-danger' ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>Nombre:</label>
            <input type="text" name="nombre" required class="form-control" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label>Edad:</label>
            <input type="number" name="edad" class="form-control" value="<?= htmlspecialchars($_POST['edad'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label>DNI:</label>
            <input type="text" name="dni" required pattern="\d{8}[A-Za-z]" maxlength="9" class="form-control"
                value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label>Sexo:</label>
            <select name="sexo" class="form-select">
                <option value="">-- Seleccione --</option>
                <option value="Masculino" <?= ($_POST['sexo'] ?? '') === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                <option value="Femenino" <?= ($_POST['sexo'] ?? '') === 'Femenino' ? 'selected' : '' ?>>Femenino</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Estado:</label>
            <input type="text" name="estado" required class="form-control" value="<?= htmlspecialchars($_POST['estado'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label>Diagnóstico:</label>
            <textarea name="diagnostico" required class="form-control"><?= htmlspecialchars($_POST['diagnostico'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label>Fecha de Ingreso:</label>
            <input type="date" name="fecha_ingreso" class="form-control" value="<?= htmlspecialchars($_POST['fecha_ingreso'] ?? date('Y-m-d')) ?>">
        </div>

        <div class="mb-3">
            <label>Doctor asignado:</label>
            <select name="doctor_uid" class="form-select" required>
                <option value="">-- Seleccione doctor --</option>
                <?php
                $doctoresDocs = $firestore->collection('usuarios')
                    ->where('hospital_id', '=', $hospital_id)
                    ->where('rol', '=', 'doctor')
                    ->where('activo', '==', true)
                    ->documents();
                foreach ($doctoresDocs as $doc) {
                    $docData = $doc->data();
                    $selected = ($_POST['doctor_uid'] ?? '') === $doc->id() ? 'selected' : '';
                    echo "<option value=\"{$doc->id()}\" $selected>" . htmlspecialchars($docData['nombre'] ?? 'Sin nombre') . "</option>";
                }
                ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Crear Paciente</button>
    </form>
</div>
