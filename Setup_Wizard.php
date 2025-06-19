<?php
require_once 'conexion.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hospital_nombre = $_POST['hospital_nombre'] ?? '';
    $admin_email = $_POST['admin_email'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_nombre = $_POST['admin_nombre'] ?? '';

    try {
        if (!preg_match('/@medigest\.com$/', $admin_email)) {
            throw new Exception("El correo debe terminar en @medigest.com");
        }

        if (strlen($admin_password) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres.");
        }

        // Verificar si ya existe un hospital con el mismo nombre
        $repetido = $firestore->collection('hospitales')
            ->where('nombre', '=', $hospital_nombre)
            ->documents();

        if (!$repetido->isEmpty()) {
            throw new Exception("Ya existe un hospital con ese nombre.");
        }

        $auth = (new \Kreait\Firebase\Factory)->withServiceAccount('firebase_config.json')->createAuth();

        $user = $auth->createUser([
            'email' => $admin_email,
            'password' => $admin_password,
        ]);
        $uid = $user->uid;

        $hospital_id = strtolower(str_replace(' ', '_', $hospital_nombre));
        $firestore->collection('hospitales')->document($hospital_id)->set([
            'nombre' => $hospital_nombre,
            'creado_en' => new \Google\Cloud\Core\Timestamp(new \DateTime())
        ]);

        $firestore->collection('usuarios')->document($uid)->set([
            'email' => $admin_email,
            'rol' => 'admin',
            'nombre' => $admin_nombre,
            'hospital_id' => $hospital_id,
            'foto_url' => '',
            'creado_en' => new \Google\Cloud\Core\Timestamp(new \DateTime())
        ]);

        $mensaje = "✅ Hospital y administrador creados correctamente.";
    } catch (Exception $e) {
        $mensaje = "❌ Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistente de Configuración de Hospital</title>
    <link rel="icon" href="favicon.png" type="image/png">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom right, #e9f1fa, #ffffff);
            margin: 0;
            padding: 40px 20px;
        }
        .wizard {
            background: white;
            border: 1px solid #ccc;
            border-radius: 12px;
            padding: 30px;
            width: 360px;
            margin: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: center;
        }
        h1 {
            color: #007bff;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        .btn {
            margin-top: 20px;
            width: 100%;
            background-color: #007bff;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .message {
            margin-top: 20px;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        .back-link {
            margin-top: 20px;
            display: block;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="wizard">
    <h1>🛠️ Configurar Hospital</h1>

    <?php if ($mensaje): ?>
        <div class="message <?= strpos($mensaje, '✅') !== false ? 'success' : 'error' ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Nombre del Hospital:</label>
            <input type="text" name="hospital_nombre" required>
        </div>

        <div class="form-group">
            <label>Nombre del Administrador:</label>
            <input type="text" name="admin_nombre" required>
        </div>

        <div class="form-group">
            <label>Email del Administrador (@medigest.com):</label>
            <input type="email" name="admin_email" required pattern=".+@medigest\.com" title="Debe usar un correo @medigest.com">
        </div>

        <div class="form-group">
            <label>Contraseña:</label>
            <input type="password" name="admin_password" required>
        </div>

        <button type="submit" class="btn">Crear Hospital</button>
    </form>

    <a href="seleccionar_hospital.php" class="btn btn-secondary back-link"> Volver a selección de hospital</a>
</div>
</body>
</html>
