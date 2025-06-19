<?php
require_once 'auth.php';
require_once 'conexion.php';
require_login(['admin', 'doctor', 'nurse']);

$hospitalId = $_SESSION['hospital_id'];
$uidSesion = $_SESSION['uid'];
$rolSesion = $_SESSION['rol'];

$uidVista = $_GET['uid'] ?? $uidSesion;
$mensaje = '';

if ($rolSesion !== 'admin' && $uidVista !== $uidSesion) {
    die("❌ No tienes permisos para acceder a esta sección. Si crees que esto es un error, contacta con un administrador.");
}

try {
    $usuarioSnap = $firestore->collection('usuarios')->document($uidVista)->snapshot();

    if (!$usuarioSnap->exists()) {
        die("❌ La cuenta de usuario solicitada no se encuentra registrada en el sistema.");
    }

    $usuario = $usuarioSnap->data();
    $esPropio = ($uidVista === $uidSesion);
    $esDoctor = ($usuario['rol'] ?? '') === 'doctor';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Subida de imagen de perfil
        if (isset($_FILES['foto_perfil']) && ($esPropio || $rolSesion === 'admin')) {
            if (!empty($_FILES['foto_perfil']['tmp_name'])) {
                $nombre_archivo = uniqid('foto_') . "_" . preg_replace("/[^A-Za-z0-9\.\-_]/", '', $_FILES["foto_perfil"]["name"]);
                $ruta_archivo = "imagenes_perfil/" . $nombre_archivo;

                if (move_uploaded_file($_FILES["foto_perfil"]["tmp_name"], $ruta_archivo)) {
                    $firestore->collection('usuarios')->document($uidVista)->update([
                        ['path' => 'foto_url', 'value' => $ruta_archivo]
                    ]);
                    $usuario['foto_url'] = $ruta_archivo;
                    $mensaje = '✅ Foto de perfil actualizada.';
                } else {
                    $mensaje = '❌ Error al subir la imagen.';
                }
            }
        }

        // Cambiar contraseña (solo admin)
        if (isset($_POST['nueva_password']) && $rolSesion === 'admin') {
            require_once 'vendor/autoload.php';
            $factory = (new \Kreait\Firebase\Factory)->withServiceAccount('firebase_config.json');
            $auth = $factory->createAuth();

            $nuevaClave = trim($_POST['nueva_password']);
            if (strlen($nuevaClave) < 6) {
                $mensaje = "❌ La contraseña debe tener al menos 6 caracteres.";
            } else {
                try {
                    $auth->changeUserPassword($uidVista, $nuevaClave);
                    $mensaje = "✅ Contraseña actualizada correctamente.";
                } catch (Exception $e) {
                    $mensaje = "❌ Error al cambiar la contraseña: " . $e->getMessage();
                }
            }
        }
    }

    // Obtener pacientes asignados si es doctor
    $pacientesAsignados = [];
    if ($esDoctor) {
        $query = $firestore->collection('pacientes')
            ->where('hospital_id', '=', $hospitalId)
            ->where('doctor_uid', '=', $uidVista)
            ->where('activo', '==', true)
            ->documents();

        foreach ($query as $doc) {
            if ($doc->exists()) {
                $p = $doc->data();
                $p['id'] = $doc->id();
                $pacientesAsignados[] = $p;
            }
        }
    }
} catch (Exception $e) {
    $mensaje = "Error: " . $e->getMessage();
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>Ficha Personal</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-info"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="row g-0">
            <div class="col-md-4 d-flex align-items-center justify-content-center p-3">
                <div class="text-center">
                    <?php
                    $foto_url = $usuario['foto_url'] ?? '';
                    $ruta_final = ($foto_url && file_exists($foto_url)) ? $foto_url : 'imagenes_perfil/default_avatar.png';
                    ?>
                    <img src="<?= htmlspecialchars($ruta_final) ?>" class="img-fluid rounded-circle mb-2" style="max-width: 200px;" alt="Foto de perfil">

                    <?php if ($esPropio || $rolSesion === 'admin'): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="file" name="foto_perfil" accept="image/*" class="form-control mb-2">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Actualizar Foto</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card-body">
                    <h4><?= htmlspecialchars($usuario['nombre'] ?? '-') ?></h4>
                    <p><strong>Email:</strong> <?= htmlspecialchars($usuario['email'] ?? '-') ?></p>
                    <p><strong>Rol:</strong> <?= htmlspecialchars($usuario['rol'] ?? '-') ?></p>
                    <p><strong>Edad:</strong> <?= htmlspecialchars($usuario['edad'] ?? '-') ?></p>
                    <p><strong>Sexo:</strong> <?= htmlspecialchars($usuario['sexo'] ?? '-') ?></p>
                    <p><strong>Estado:</strong> <?= ($usuario['activo'] ?? true) ? 'Activo' : 'Inhabilitado' ?></p>
                    <?php if ($rolSesion === 'admin'): ?>
                        <hr>
                        <h5>🔒 Cambiar Contraseña</h5>
                        <form method="POST" class="mt-2" onsubmit="return validarPassword()">
                            <div class="mb-2">
                                <input type="password" name="nueva_password" id="nueva_password" class="form-control" required placeholder="Nueva contraseña (mínimo 6 caracteres)">
                            </div>
                            <button type="submit" class="btn btn-sm btn-danger">Actualizar contraseña</button>
                        </form>
                        <script>
                            function validarPassword() {
                                const clave = document.getElementById("nueva_password").value;
                                if (clave.length < 6) {
                                    alert("La contraseña debe tener al menos 6 caracteres.");
                                    return false;
                                }
                                return true;
                            }
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($esDoctor): ?>
        <div class="card p-4 shadow-sm mb-4">
            <h5>👥 Pacientes Asignados</h5>
            <?php if (empty($pacientesAsignados)): ?>
                <p>No tiene pacientes asignados.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($pacientesAsignados as $pac): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($pac['nombre'] ?? '-') ?></strong><br>
                                    <span class="text-muted">Estado: <?= htmlspecialchars($pac['estado'] ?? '-') ?></span>
                                </div>
                                <a href="detalle_paciente.php?id=<?= urlencode($pac['id']) ?>" class="btn btn-sm btn-primary">Ver</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($rolSesion === 'admin'): ?>
    <a href="ver_personal.php" class="btn btn-secondary">← Volver</a>
    <?php else: ?>
        <a href="dashboard.php" class="btn btn-secondary">← Volver</a>
    <?php endif; ?>

</div>
