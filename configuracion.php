<?php
require_once 'auth.php';
require_once 'conexion.php';
require_login(['admin', 'doctor', 'nurse', 'enfermero', 'enfermera']);

$uid = $_SESSION['uid'];
$rol = $_SESSION['rol'] ?? '';
$mensaje = '';
$usuario = $firestore->collection('usuarios')->document($uid)->snapshot();

if (!$usuario->exists()) {
    die("❌ La cuenta de usuario solicitada no se encuentra registrada en el sistema.");
}

$datos = $usuario->data();
$nombre_actual = $datos['nombre'] ?? '';
$telefono_actual = $datos['telefono'] ?? '';
$email_actual = $datos['email'] ?? '';

// Manejar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_nombre = trim($_POST['nombre'] ?? '');
    $nuevo_telefono = trim($_POST['telefono'] ?? '');
    $nueva_clave = trim($_POST['password'] ?? '');

    try {
        $actualizaciones = [];

        if ($nuevo_nombre !== $nombre_actual) {
            $actualizaciones[] = ['path' => 'nombre', 'value' => $nuevo_nombre];
            $_SESSION['nombre'] = $nuevo_nombre;
        }

        if ($rol !== 'admin' && $nuevo_telefono !== $telefono_actual) {
            $actualizaciones[] = ['path' => 'telefono', 'value' => $nuevo_telefono];
        }

        if (!empty($nueva_clave)) {
            $auth = (new \Kreait\Firebase\Factory)->withServiceAccount('firebase_config.json')->createAuth();
            $auth->changeUserPassword($uid, $nueva_clave);
        }

        if (!empty($actualizaciones)) {
            $firestore->collection('usuarios')->document($uid)->update($actualizaciones);
        }

        $mensaje = '✅ Información actualizada correctamente.';
    } catch (Exception $e) {
        $mensaje = '❌ Error al actualizar: ' . $e->getMessage();
    }
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>⚙️ Configuración de Perfil</h1>

    <?php if ($mensaje): ?>
        <p class="alert alert-info"><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>Correo electrónico (no editable):</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($email_actual) ?>" readonly>
        </div>
        <div class="mb-3">
            <label>Nombre completo:</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($nombre_actual) ?>" required>
        </div>

        <?php if ($rol !== 'admin'): ?>
        <div class="mb-3">
            <label>Número de teléfono (máx. 9 dígitos):</label>
            <input type="number" name="telefono" class="form-control" 
                value="<?= htmlspecialchars($telefono_actual) ?>"
                maxlength="9"
                oninput="if(this.value.length > 9) this.value = this.value.slice(0, 9);">
        </div>
        <?php endif; ?>

        <div class="mb-3">
            <label>Nueva contraseña:</label>
            <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar">
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="dashboard.php" class="btn btn-secondary">Volver al panel</a>
        </div>
    </form>
</div>
