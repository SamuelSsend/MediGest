<?php
require 'vendor/autoload.php';

use Kreait\Firebase\Factory;

session_start();

// 🟦 Establecer hospital_id si viene por POST
if (isset($_POST['hospital_id'])) {
    $_SESSION['hospital_id'] = $_POST['hospital_id'];
}

// 🛑 Verificar que esté definido
if (!isset($_SESSION['hospital_id'])) {
    header("Location: seleccionar_hospital.php");
    exit();
}

$factory = (new Factory)->withServiceAccount('firebase_config.json');
$auth = $factory->createAuth();
$firestore = $factory->createFirestore()->database();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    try {
        $signInResult = $auth->signInWithEmailAndPassword($email, $password);
        $uid = $signInResult->firebaseUserId();

        $userDoc = $firestore->collection('usuarios')->document($uid)->snapshot();
        if ($userDoc->exists()) {
            $userData = $userDoc->data();

            if ($userData['hospital_id'] === $_SESSION['hospital_id']) {
                $_SESSION['uid'] = $uid;
                $_SESSION['email'] = $email;
                $_SESSION['rol'] = $userData['rol'];
                $_SESSION['nombre'] = $userData['nombre'];

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "⚠️ Usuario no pertenece al hospital seleccionado.";
            }
        } else {
            $error = "⚠️ Usuario no encontrado.";
        }
    } catch (Throwable $e) {
        $error = "❌ Error de inicio de sesión: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - MediGest</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f8;
            font-family: 'Segoe UI', sans-serif;
        }
        .login-card {
            max-width: 400px;
            margin: auto;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .brand-title {
            font-size: 1.8rem;
            color: #2a4e9b;
            font-weight: 600;
        }
        .form-control {
            border-radius: 0.4rem;
        }
        .btn-primary {
            background-color: #2a4e9b;
            border: none;
            border-radius: 0.4rem;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="login-card mt-5">
        <div class="text-center mb-4">
            <div class="brand-title">MediGest</div>
            <p class="text-muted">Sistema de Gestión Hospitalaria</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Correo Electrónico</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="d-grid">
                <button class="btn btn-primary" type="submit">Iniciar Sesión</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
