<?php
require_once 'vendor/autoload.php';
require_once 'conexion.php';
session_start();

use Kreait\Firebase\Factory;

$mensaje = '';

if (!isset($_SESSION['hospital_id'])) {
    die('❌ No se ha seleccionado un hospital.');
}

$hospital_id_seleccionado = $_SESSION['hospital_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        try {
            $factory = (new Factory)->withServiceAccount('firebase_config.json');
            $auth = $factory->createAuth();

            // Autenticación con Firebase
            $signInResult = $auth->signInWithEmailAndPassword($email, $password);
            $idToken = $signInResult->idToken();
            $verifiedIdToken = $auth->verifyIdToken($idToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            // Obtener usuario de Firestore
            $userDoc = $firestore->collection('usuarios')->document($uid)->snapshot();
            if (!$userDoc->exists()) {
                throw new Exception("El usuario no está registrado en Firestore.");
            }

            $userData = $userDoc->data();
            $rol = $userData['rol'] ?? null;
            $hospital_id_usuario = $userData['hospital_id'] ?? null;

            // Comparar hospital seleccionado vs hospital del usuario
            if ($hospital_id_usuario !== $hospital_id_seleccionado) {
                throw new Exception("⛔ Este usuario no pertenece al hospital seleccionado.");
            }

            // Guardar en sesión
            $_SESSION['uid'] = $uid;
            $_SESSION['rol'] = $rol;
            $_SESSION['hospital_id'] = $hospital_id_usuario;

            header("Location: dashboard.php");
            exit();

        } catch (\Throwable $e) {
            $mensaje = "❌ Error: " . $e->getMessage();
        }
    } else {
        $mensaje = "❌ Debes ingresar email y contraseña.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
</head>
<body>
    <h1>Login de Usuario</h1>

    <?php if ($mensaje): ?>
        <p style="color:red;"><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Contraseña:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Acceder</button>
    </form>
</body>
</html>
