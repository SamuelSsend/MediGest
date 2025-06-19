<?php
require_once 'conexion.php';
require_once 'auth.php';
require_login(['admin', 'doctor']);

$mensaje = '';
$rol = $_SESSION['rol'];
$uid = $_SESSION['uid'];
$hospital_id = $_SESSION['hospital_id'];

$esAdmin = $rol === 'admin';
$doctores = [];

if ($esAdmin) {
    // Cargar doctores del mismo hospital
    $usuarios = $firestore->collection('usuarios')->where('hospital_id', '=', $hospital_id)->where('rol', '=', 'doctor')->documents();
    foreach ($usuarios as $doc) {
        $data = $doc->data();
        $doctores[] = [
            'uid' => $doc->id(),
            'nombre' => $data['nombre'] ?? 'Sin nombre'
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $edad = $_POST['edad'] ?? '';
    $sexo = $_POST['genero'] ?? '';
    $diagnostico = $_POST['motivo'] ?? '';
    $tratamiento = $_POST['tratamiento'] ?? 'Pendiente de definir';
    $estado = 'en tratamiento';
    $fecha_ingreso = new \Google\Cloud\Core\Timestamp(new \DateTime());

    if ($esAdmin) {
        $doctor_uid = $_POST['doctor_uid'] ?? '';
        $doctor_nombre = $_POST['doctor_nombre'] ?? '';
    } else {
        // Si es doctor, él mismo será asignado
        $doctor_uid = $uid;
        $userDoc = $firestore->collection('usuarios')->document($uid)->snapshot();
        $doctor_nombre = $userDoc->exists() ? ($userDoc->data()['nombre'] ?? 'Doctor') : 'Doctor';
    }

    try {
        $firestore->collection('pacientes')->add([
            'nombre' => $nombre,
            'edad' => (int)$edad,
            'sexo' => $sexo,
            'estado' => $estado,
            'hospital_id' => $hospital_id,
            'doctor_uid' => $doctor_uid,
            'doctor_nombre' => $doctor_nombre,
            'fecha_ingreso' => $fecha_ingreso,
            'diagnostico' => $diagnostico,
            'tratamiento' => $tratamiento,
            'notas' => []
        ]);

        // Registrar log de creación de paciente
        registrar_log($firestore, 'crear_paciente', "Paciente $nombre registrado por $rol", $hospital_id, $uid, $rol);
        $mensaje = "✅ Paciente registrado correctamente.";
    } catch (Exception $e) {
        $mensaje = "❌ Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="styles.css">
    <title>Registrar Paciente</title>
</head>
<body>
    <h1>Registrar Paciente</h1>
    <?php if ($mensaje): ?><p><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>

    <form method="POST">
        <label>Nombre completo:</label><br>
        <input type="text" name="nombre" required><br><br>

        <label>Edad:</label><br>
        <input type="number" name="edad" required><br><br>

        <label>Género:</label><br>
        <select name="genero" required>
            <option value="masculino">Masculino</option>
            <option value="femenino">Femenino</option>
            <option value="otro">Otro</option>
        </select><br><br>

        <?php if ($esAdmin): ?>
            <label>Doctor responsable:</label><br>
            <select name="doctor_uid" required>
                <option value="">-- Selecciona doctor --</option>
                <?php foreach ($doctores as $doc): ?>
                    <option value="<?= $doc['uid'] ?>"><?= htmlspecialchars($doc['nombre']) ?></option>
                <?php endforeach; ?>
            </select><br><br>
            <label>Nombre del doctor (visible):</label><br>
            <input type="text" name="doctor_nombre" required><br><br>
        <?php endif; ?>

        <label>Diagnóstico:</label><br>
        <textarea name="motivo" required></textarea><br><br>

        <label>Tratamiento:</label><br>
        <textarea name="tratamiento"></textarea><br><br>

        <button type="submit">Guardar Paciente</button>
    </form>

    <p><a href="gestionar_pacientes.php">📋 Ver lista de pacientes</a></p>
</body>
</html>
