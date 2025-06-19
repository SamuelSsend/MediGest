<?php
require_once 'auth.php';
require_once 'conexion.php';
require_login(['admin']);

$hospital_id = $_SESSION['hospital_id'];
$mensaje = '';
$usuarios = [];

// Mostrar mensajes desde la URL
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'doctor_con_pacientes') {
        $mensaje = "❌ No puedes inhabilitar un doctor con pacientes asignados. Reasigna primero sus pacientes.";
    } elseif ($_GET['error'] === 'ultimo_admin') {
        $mensaje = "❌ No puedes inhabilitar al último administrador activo.";
    } else {
        $mensaje = "❌ Error: " . htmlspecialchars($_GET['error']);
    }
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'inhabilitado') {
    $mensaje = "✅ Usuario inhabilitado correctamente.";
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'habilitado') {
    $mensaje = "✅ Usuario habilitado correctamente.";
}

// Función para mostrar rol
function mostrarRolUsuario($rolBackend) {
    $mapaRoles = [
        'admin' => 'Administrador',
        'doctor' => 'Doctor',
        'nurse' => 'Enfermero/a',
    ];
    return $mapaRoles[$rolBackend] ?? $rolBackend;
}

try {
    $docs = $firestore->collection('usuarios')
        ->where('hospital_id', '=', $hospital_id)
        ->where('rol', 'in', ['doctor', 'nurse', 'admin'])
        ->documents();

    foreach ($docs as $doc) {
        if ($doc->exists()) {
            $usuario = $doc->data();
            $usuario['id'] = $doc->id();

            // Contar pacientes asignados si es doctor
            $numPacientes = 0;
            if ($usuario['rol'] === 'doctor') {
                $pacientesQuery = $firestore->collection('pacientes')
                    ->where('hospital_id', '=', $hospital_id)
                    ->where('doctor_uid', '=', $usuario['id'])
                    ->where('activo', '==', true)
                    ->documents();
                $numPacientes = iterator_count($pacientesQuery);
            }

            $usuario['numPacientes'] = $numPacientes;
            $usuarios[] = $usuario;
        }
    }
} catch (Exception $e) {
    $mensaje = "Error al cargar personal: " . $e->getMessage();
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>👥 Personal Médico y Enfermería</h1>

    <?php if ($mensaje): ?>
        <div class="alert <?= str_starts_with($mensaje, '✅') ? 'alert-success' : 'alert-danger' ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <a href="crear_usuario.php" class="btn btn-success mb-3">➕ Añadir Nuevo Usuario</a>

    <?php if (empty($usuarios)): ?>
        <p>No hay personal registrado en este hospital.</p>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Edad</th>
                    <th>Sexo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Pacientes Asignados</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($u['edad'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($u['sexo'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(mostrarRolUsuario($u['rol'] ?? '-')) ?></td>
                        <td><?= ($u['activo'] ?? true) ? '✅ Activo' : '⛔ Inhabilitado' ?></td>
                        <td><?= $u['numPacientes'] ?></td>
                        <td>
                            <?php if ($u['rol'] !== 'admin'): ?>
                                <a href="ver_detalle_personal.php?uid=<?= urlencode($u['id']) ?>" class="btn btn-sm btn-info mb-1">🔍 Ver</a>
                                <a href="editar_personal.php?id=<?= urlencode($u['id']) ?>" class="btn btn-sm btn-warning mb-1">✏️ Editar</a>
                                <?php if ($u['activo'] ?? true): ?>
                                    <a href="inhabilitar_usuario.php?id=<?= urlencode($u['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Deseas inhabilitar este usuario?');">⛔ Inhabilitar</a>
                                <?php else: ?>
                                    <a href="habilitar_usuario.php?id=<?= urlencode($u['id']) ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Deseas habilitar este usuario?');">✅ Habilitar</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">No editable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
