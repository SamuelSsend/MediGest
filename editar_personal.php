<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin']);

use Kreait\Firebase\Factory;

$hospitalId = $_SESSION['hospital_id'];
$usuarioId = $_GET['id'] ?? null;
$mensaje = '';
$usuario = null;

$factory = (new Factory)->withServiceAccount('firebase_config.json');
$firestore = $factory->createFirestore()->database();
$usuariosCol = $firestore->collection('usuarios');

if ($usuarioId) {
    $docRef = $usuariosCol->document($usuarioId);
    $snap = $docRef->snapshot();
    if ($snap->exists() && $snap['hospital_id'] === $hospitalId) {
        $data = $snap->data();
        $usuario = [
            'nombre' => $data['nombre'] ?? '',
            'edad' => $data['edad'] ?? '',
            'sexo' => $data['sexo'] ?? '',
            'rol' => $data['rol'] ?? '',
        ];
    } else {
        $mensaje = "❌ Usuario no encontrado o no pertenece a este hospital.";
    }
} else {
    $mensaje = "❌ ID de usuario no proporcionado.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuarioId) {
    $nuevoNombre = trim($_POST['name'] ?? '');
    $nuevaEdad = trim($_POST['age'] ?? '');
    $nuevoSexo = trim($_POST['sex'] ?? '');
    $nuevoRol = trim($_POST['role'] ?? '');

    if ($nuevoNombre === '' || $nuevaEdad === '' || $nuevoSexo === '' || $nuevoRol === '') {
        $mensaje = "❌ Todos los campos son obligatorios.";
    } else {
        try {
            $usuariosCol->document($usuarioId)->update([
                ['path' => 'nombre', 'value' => $nuevoNombre],
                ['path' => 'edad', 'value' => (int)$nuevaEdad],
                ['path' => 'sexo', 'value' => $nuevoSexo],
                ['path' => 'rol', 'value' => $nuevoRol],
            ]);

            $uid_actual = $_SESSION['uid'];
            $rol_actual = $_SESSION['rol'];
            registrar_log($firestore, "Editar Usuario", "Se editaron los datos del usuario $nuevoNombre ($usuarioId)");

            $mensaje = "✅ Usuario actualizado correctamente.";
            $usuario['nombre'] = $nuevoNombre;
            $usuario['edad'] = (int)$nuevaEdad;
            $usuario['sexo'] = $nuevoSexo;
            $usuario['rol'] = $nuevoRol;
        } catch (Exception $e) {
            $mensaje = "❌ Error al actualizar usuario: " . $e->getMessage();
        }
    }
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1 class="mb-4">✏️ Editar Usuario</h1>

    <?php if ($mensaje): ?>
        <div class="alert <?= str_starts_with($mensaje, '✅') ? 'alert-success' : 'alert-danger' ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($usuario): ?>
    <div class="d-flex justify-content-center">
        <form method="POST" class="card p-4 shadow-sm" style="max-width: 600px; width: 100%;">
            <div class="mb-3">
                <label class="form-label">Nombre:</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Edad:</label>
                <input type="number" name="age" class="form-control" value="<?= htmlspecialchars($usuario['edad']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Sexo:</label>
                <select name="sex" class="form-select" required>
                    <option value="" disabled <?= $usuario['sexo'] === '' ? 'selected' : '' ?>>Selecciona sexo</option>
                    <option value="Masculino" <?= $usuario['sexo'] === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                    <option value="Femenino" <?= $usuario['sexo'] === 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Rol:</label>
                <select name="role" class="form-select" required>
                    <option value="" disabled <?= $usuario['rol'] === '' ? 'selected' : '' ?>>Selecciona rol</option>
                    <option value="doctor" <?= $usuario['rol'] === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                    <option value="nurse" <?= $usuario['rol'] === 'nurse' ? 'selected' : '' ?>>Enfermero/a</option>
                </select>
            </div>

            <div class="d-flex justify-content-between">
                <a href="ver_personal.php" class="btn btn-secondary">← Volver</a>
                <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>
