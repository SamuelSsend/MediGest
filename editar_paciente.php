<?php
require_once 'conexion.php';
require_once 'auth.php';
require_once 'funciones.php';
require_login(['admin', 'doctor']);

$pacienteId = $_GET['id'] ?? null;
if (!$pacienteId) {
    echo "ID de paciente no proporcionado.";
    exit;
}

$docRef = $firestore->collection('pacientes')->document($pacienteId);
$snapshot = $docRef->snapshot();

if (!$snapshot->exists()) {
    echo "Paciente no encontrado.";
    exit;
}

$paciente = $snapshot->data();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $edad = (int)$_POST['edad'];
    $sexo = $_POST['sexo'];
    $estado = $_POST['estado'];
    $diagnostico = $_POST['diagnostico'];
    $tratamiento = $_POST['tratamiento'];

    try {
        $docRef->update([
            ['path' => 'nombre', 'value' => $nombre],
            ['path' => 'edad', 'value' => $edad],
            ['path' => 'sexo', 'value' => $sexo],
            ['path' => 'estado', 'value' => $estado],
            ['path' => 'diagnostico', 'value' => $diagnostico],
            ['path' => 'tratamiento', 'value' => $tratamiento]
        ]);

        // Registrar log de edición
        $uid_actual = $_SESSION['uid'];
        $rol_actual = $_SESSION['rol'];
        registrar_log($firestore, 'editar_paciente', "Paciente $nombre ($pacienteId) actualizado", $paciente['hospital_id'], $uid_actual, $rol_actual);

        echo "<p>✅ Paciente actualizado correctamente.</p>";
        echo "<a href='dashboard.php'>Volver al Dashboard</a>";
        exit;
    } catch (Exception $e) {
        echo "<p>❌ Error al actualizar paciente: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Editar Paciente</title>
    <meta charset="UTF-8">
</head>
<body>
    <h2>Editar Paciente</h2>
    <form method="POST">
        Nombre: <input type="text" name="nombre" value="<?php echo htmlspecialchars($paciente['nombre']); ?>"><br>
        Edad: <input type="number" name="edad" value="<?php echo htmlspecialchars($paciente['edad']); ?>"><br>
        Sexo:
        <select name="sexo">
            <option value="Hombre" <?php if ($paciente['sexo'] === 'Hombre') echo 'selected'; ?>>Hombre</option>
            <option value="Mujer" <?php if ($paciente['sexo'] === 'Mujer') echo 'selected'; ?>>Mujer</option>
        </select><br>
        Estado: <input type="text" name="estado" value="<?php echo htmlspecialchars($paciente['estado']); ?>"><br>
        Diagnóstico:<br>
        <textarea name="diagnostico" rows="4" cols="50"><?php echo htmlspecialchars($paciente['diagnostico']); ?></textarea><br>
        Tratamiento:<br>
        <textarea name="tratamiento" rows="4" cols="50"><?php echo htmlspecialchars($paciente['tratamiento']); ?></textarea><br>
        <input type="submit" value="Guardar Cambios">
    </form>
</body>
</html>
