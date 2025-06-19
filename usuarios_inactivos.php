<?php
require_once 'auth.php';
require_once 'conexion.php';
require_login(['admin']);

$hospitalId = $_SESSION['hospital_id'];
$mensaje = '';
$usuarios = [];

function mostrarRolUsuario($rol) {
    return [
        'admin' => 'Administrador',
        'doctor' => 'Doctor',
        'nurse' => 'Enfermero/a'
    ][$rol] ?? $rol;
}

try {
    $docs = $firestore->collection('usuarios')
        ->where('hospital_id', '=', $hospitalId)
        ->where('activo', '==', false)
        ->documents();

    foreach ($docs as $doc) {
        if ($doc->exists()) {
            $usuario = $doc->data();
            $usuario['id'] = $doc->id();
            $usuarios[] = $usuario;
        }
    }
} catch (Exception $e) {
    $mensaje = "❌ Error al cargar usuarios inhabilitados: " . $e->getMessage();
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>Usuarios Inhabilitados</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <a href="ver_personal.php" class="btn btn-secondary mb-3">⬅ Volver a Personal Activo</a>

    <?php if (empty($usuarios)): ?>
        <p>No hay usuarios inhabilitados actualmente.</p>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Edad</th>
                    <th>Sexo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(mostrarRolUsuario($u['rol'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars($u['edad'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($u['sexo'] ?? '-') ?></td>
                        <td>
                            <a href="habilitar_usuario.php?id=<?= urlencode($u['id']) ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Deseas habilitar este usuario?');">🔁 Habilitar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
