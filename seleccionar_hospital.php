<?php
require_once 'conexion.php';

$hospitales = [];
$firestoreRef = $firestore->collection('hospitales');
$hospitalDocs = $firestoreRef->documents();

foreach ($hospitalDocs as $hospital) {
    if ($hospital->exists()) {
        $hospital_id = $hospital->id();
        $nombre = $hospital['nombre'] ?? $hospital_id;

        $usuariosSnap = $firestore->collection('usuarios')
            ->where('hospital_id', '=', $hospital_id)
            ->documents();

        $pacientesSnap = $firestore->collection('pacientes')
            ->where('hospital_id', '=', $hospital_id)
            ->documents();

        $doctores = 0;
        $enfermeros = 0;
        $pacientes = 0;

        foreach ($usuariosSnap as $usuario) {
            if (!$usuario->exists()) continue;
            $rol = $usuario['rol'] ?? '';
            if ($rol === 'doctor') $doctores++;
            if ($rol === 'nurse') $enfermeros++;
        }

        foreach ($pacientesSnap as $paciente) {
            if ($paciente->exists()) $pacientes++;
        }

        $hospitales[] = [
            'id' => $hospital_id,
            'nombre' => $nombre,
            'doctores' => $doctores,
            'enfermeros' => $enfermeros,
            'pacientes' => $pacientes
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seleccionar Hospital</title>
    <link rel="icon" href="favicon.png" type="image/png">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom right, #e9f1fa, #ffffff);
            margin: 0;
            padding: 40px 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 40px;
            color: #333;
        }
        .container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
        }
        .hospital-card {
            width: 280px;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .hospital-card h3 {
            margin-top: 0;
            color: #007bff;
        }
        .hospital-card p {
            margin: 8px 0;
            font-size: 15px;
        }
        .hospital-card button {
            margin-top: 10px;
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .hospital-card button:hover {
            background-color: #0056b3;
        }
        .asistente {
            text-align: center;
            margin-top: 40px;
        }
        .asistente a {
            text-decoration: none;
            background-color: #6c757d;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            display: inline-block;
        }
        .asistente a:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>

<h1>🏥 Seleccionar Hospital</h1>

<div class="container">
    <?php foreach ($hospitales as $h): ?>
        <form method="POST" action="login.php" class="hospital-card">
            <input type="hidden" name="hospital_id" value="<?= htmlspecialchars($h['id']) ?>">
            <h3><?= htmlspecialchars($h['nombre']) ?></h3>
            <p>🧑‍⚕️ Doctores: <?= $h['doctores'] ?></p>
            <p>🧑‍🔬 Enfermeros/as: <?= $h['enfermeros'] ?></p>
            <p>🧍 Pacientes: <?= $h['pacientes'] ?></p>
            <button type="submit">Acceder</button>
        </form>
    <?php endforeach; ?>
</div>

<div class="asistente">
    <a href="setup_wizard.php">🛠️ Asistente de configuración de hospital</a>
</div>

</body>
</html>
