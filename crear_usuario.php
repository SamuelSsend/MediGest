<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin']);

use Kreait\Firebase\Factory;

$mensaje = '';
$hospital_id = $_SESSION['hospital_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $edad = $_POST['edad'] ?? '';
    $sexo = $_POST['sexo'] ?? '';

    try {
        if (!preg_match('/@medigest\\.com$/', $email)) {
            throw new Exception("El correo debe terminar en @medigest.com");
        }

        if (!in_array($rol, ['admin', 'doctor', 'nurse'])) {
            throw new Exception("Rol no válido.");
        }

        if (strlen($password) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres.");
        }

        $factory = (new Factory)->withServiceAccount('firebase_config.json');
        $auth = $factory->createAuth();

        $user = $auth->createUser([
            'email' => $email,
            'password' => $password,
        ]);
        $uid_creado = $user->uid;

        $docExistente = $firestore->collection('usuarios')->document($uid_creado)->snapshot();
        if ($docExistente->exists()) {
            throw new Exception("Ya existe un documento en Firestore para este usuario.");
        }

        $datosUsuario = [
            'email' => $email,
            'rol' => $rol,
            'nombre' => $nombre,
            'hospital_id' => $hospital_id,
            'foto_url' => '',
            'activo' => true,
            'creado_en' => new \Google\Cloud\Core\Timestamp(new \DateTime())
        ];

        if ($rol !== 'admin') {
            $datosUsuario['edad'] = is_numeric($edad) ? (int)$edad : null;
            $datosUsuario['sexo'] = in_array($sexo, ['Masculino', 'Femenino']) ? $sexo : '';
        }

        $firestore->collection('usuarios')->document($uid_creado)->set($datosUsuario);

        registrar_log($firestore, "Crear Usuario", "Se creó el usuario $nombre ($uid_creado)");
        $mensaje = "✅ Usuario creado correctamente.";
    } catch (Exception $e) {
        $mensaje = "❌ Error: " . $e->getMessage();
    }
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>Registrar Nuevo Usuario</h1>

    <?php if ($mensaje): ?>
        <div class="alert <?= strpos($mensaje, '✅') !== false ? 'alert-success' : 'alert-danger' ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>Nombre completo*:</label>
            <input type="text" name="nombre" required class="form-control" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label>Email(@medigest.com)*:</label>
            <input type="email" name="email" required class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" pattern=".+@medigest\.com" title="El email debe terminar en @medigest.com">
        </div>

        <div class="mb-3">
            <label>Contraseña*:</label>
            <input type="password" name="password" required class="form-control">
        </div>

        <div class="mb-3">
            <label>Rol*:</label>
            <select name="rol" id="rolUsuario" required class="form-select">
                <option value="">-- Seleccione --</option>
                <option value="admin" <?= (isset($_POST['rol']) && $_POST['rol'] === 'admin') ? 'selected' : '' ?>>Administrador</option>
                <option value="doctor" <?= (isset($_POST['rol']) && $_POST['rol'] === 'doctor') ? 'selected' : '' ?>>Doctor</option>
                <option value="nurse" <?= (isset($_POST['rol']) && $_POST['rol'] === 'nurse') ? 'selected' : '' ?>>Enfermero/a</option>
            </select>
        </div>

        <div id="extrasUsuario" style="display:none;">
            <div class="mb-3">
                <label>Edad:</label>
                <input type="number" name="edad" class="form-control" value="<?= htmlspecialchars($_POST['edad'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Sexo:</label>
                <select name="sexo" class="form-select">
                    <option value="">-- Seleccione --</option>
                    <option value="Masculino" <?= (isset($_POST['sexo']) && $_POST['sexo'] === 'Masculino') ? 'selected' : '' ?>>Masculino</option>
                    <option value="Femenino" <?= (isset($_POST['sexo']) && $_POST['sexo'] === 'Femenino') ? 'selected' : '' ?>>Femenino</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">💾 Registrar Usuario</button>
    </form>
</div>

<script>
    const rolSelect = document.getElementById('rolUsuario');
    const extrasDiv = document.getElementById('extrasUsuario');

    function toggleExtras() {
        extrasDiv.style.display = (rolSelect.value === 'admin') ? 'none' : 'block';
    }

    rolSelect.addEventListener('change', toggleExtras);
    toggleExtras();
</script>
