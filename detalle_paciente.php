<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';
require_login(['admin', 'doctor', 'nurse']);

$patientId = $_GET['id'] ?? null;
$mensaje = '';

if (!$patientId) die("❌ El sistema no ha recibido un ID de paciente válido. Intenta acceder nuevamente desde la lista de pacientes.");

$hospitalId = $_SESSION['hospital_id'];
$rol = $_SESSION['rol'];
$uid = $_SESSION['uid'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

// Hospitales para traslado
$hospitales = [];
$docs = $firestore->collection('hospitales')->documents();
foreach ($docs as $hosp) {
    if ($hosp->id() !== $hospitalId) {
        $data = $hosp->data();
        $hospitales[] = [
            'id' => $hosp->id(),
            'nombre' => $data['nombre'] ?? $hosp->id()
        ];
    }
}

try {
    $pacienteRef = $firestore->collection('pacientes')->document($patientId);
    $doc = $pacienteRef->snapshot();

    if (!$doc->exists()) die("❌ No se ha podido encontrar el paciente solicitado. Verifica el ID o contacta con soporte.");
    $paciente = $doc->data();

    // Subida de imagen de perfil local (cualquier rol puede hacerlo)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_perfil'])) {
        if (!empty($_FILES['foto_perfil']['tmp_name'])) {
            $nombre_archivo = uniqid('foto_') . "_" . preg_replace("/[^A-Za-z0-9\.\-_]/", '', $_FILES["foto_perfil"]["name"]);
            $ruta_archivo = "imagenes_perfil/" . $nombre_archivo;

            if (move_uploaded_file($_FILES["foto_perfil"]["tmp_name"], $ruta_archivo)) {
                $pacienteRef->update([['path' => 'foto_url', 'value' => $ruta_archivo]]);
                $paciente['foto_url'] = $ruta_archivo;
                $mensaje = '✅ Foto de perfil actualizada.';
            } else {
                $mensaje = '❌ Error al subir la imagen.';
            }
        }
    }

    if (($paciente['activo'] ?? true) === false && $rol !== 'admin') {
        die("❌ Este paciente ha sido marcado como inactivo y no se puede gestionar actualmente.");
    }

    if ($rol === 'doctor' && $paciente['doctor_uid'] !== $uid) die("❌ No tienes autorización para ver o editar esta información. Solicita acceso al administrador.");
    if ($paciente['hospital_id'] !== $hospitalId) die("❌ Este paciente está asignado a otro hospital. No puedes acceder a su ficha desde este centro.");

    // Obtener nombre del doctor asignado
    $doctorAsignado = '-';
    if (!empty($paciente['doctor_uid'])) {
        $doctorDoc = $firestore->collection('usuarios')->document($paciente['doctor_uid'])->snapshot();
        if ($doctorDoc->exists()) {
            $doctorAsignado = $doctorDoc->data()['nombre'] ?? $paciente['doctor_uid'];
        }
    }

    $trasladoPendiente = !$firestore->collection('traslados')
        ->where('paciente_id', '=', $patientId)
        ->where('estado', '=', 'pendiente')
        ->documents()
        ->isEmpty();

    $notas = $paciente['notas'] ?? [];
    usort($notas, function ($a, $b) {
        return strtotime($b['fecha'] ?? '') <=> strtotime($a['fecha'] ?? '');
    });


    // Agregar nota médica
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_nota'])) {
        $nuevaNota = trim($_POST['nueva_nota']);
        if ($nuevaNota) {
            $nota = [
                'autor' => $nombre_usuario,
                'fecha' => (new DateTime())->format(DateTime::ATOM),
                'contenido' => $nuevaNota
            ];
            $notas[] = $nota;
            $pacienteRef->update([['path' => 'notas', 'value' => $notas]]);
            header("Location: detalle_paciente.php?id=" . urlencode($patientId));
            exit();
        }
    }

    // Editar diagnóstico/estado (solo doctor)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_diagnostico']) && $rol === 'doctor') {
        $updates = [];
        $estado = trim($_POST['estado'] ?? '');
        $diag = trim($_POST['diagnostico'] ?? '');
        if (empty($estado) || empty($diag)) {
            $mensaje = "❌ El estado y el diagnóstico no pueden estar vacíos.";
        } else {
            $pacienteRef->update([
                ['path' => 'estado', 'value' => $estado],
                ['path' => 'diagnostico', 'value' => $diag]
            ]);
            header("Location: detalle_paciente.php?id=" . urlencode($patientId));
            exit();
        }
    }

    // Solicitar traslado (solo doctor)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_traslado']) && $rol === 'doctor') {
        $destino = $_POST['hospital_destino'] ?? '';
        $motivo = trim($_POST['motivo_traslado'] ?? '');
        $pendiente = $firestore->collection('traslados')
            ->where('paciente_id', '=', $patientId)
            ->where('estado', '=', 'pendiente')
            ->documents();
        if (!$pendiente->isEmpty()) {
            $mensaje = "⚠️ Ya existe una solicitud de traslado.";
        } elseif ($destino && $destino !== $hospitalId) {
            $firestore->collection('traslados')->add([
                'doctor_uid' => $uid,
                'estado' => 'pendiente',
                'fecha_solicitud' => new \Google\Cloud\Core\Timestamp(new DateTime()),
                'hospital_origen' => $hospitalId,
                'hospital_destino' => $destino,
                'paciente_id' => $patientId,
                'motivo' => $motivo
            ]);
            $mensaje = "✅ Solicitud enviada correctamente.";
        } else {
            $mensaje = "❌ Selecciona un hospital válido.";
        }
    }

    // Reasignar doctor (solo admin)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reasignar_doctor']) && $rol === 'admin') {
        $nuevoDoctorUid = $_POST['nuevo_doctor_uid'] ?? '';
        if ($nuevoDoctorUid) {
            $pacienteRef->update([
                ['path' => 'doctor_uid', 'value' => $nuevoDoctorUid]
            ]);
            registrar_log($firestore, 'reasignar_doctor', "Paciente $patientId reasignado al doctor $nuevoDoctorUid");
            header("Location: detalle_paciente.php?id=" . urlencode($patientId));
            exit();
        } else {
            $mensaje = "❌ Debes seleccionar un doctor válido.";
        }
    }

} catch (Exception $e) {
    $mensaje = "Error: " . $e->getMessage();
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1 class="mb-4">🩺 Ficha del Paciente</h1>

    <?php if ($trasladoPendiente): ?>
        <div class="alert alert-warning">🚑 Solicitud de traslado pendiente.</div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <div class="alert alert-info"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="row g-0">
            <div class="col-md-4 d-flex align-items-center justify-content-center p-3">
                <div class="text-center">
                    <?php
                    $foto_url = $paciente['foto_url'] ?? '';
                    $ruta_final = ($foto_url && file_exists($foto_url)) ? $foto_url : 'imagenes_perfil/default_avatar.png';
                    ?>
                    <img src="<?= htmlspecialchars($ruta_final) ?>" class="img-fluid rounded mb-2" style="max-width: 200px;" alt="Foto del paciente">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="file" name="foto_perfil" accept="image/*" class="form-control mb-2">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Actualizar Foto</button>
                    </form>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card-body">
                    <h4><?= htmlspecialchars($paciente['nombre'] ?? '-') ?></h4>
                    <p><strong>Edad:</strong> <?= htmlspecialchars($paciente['edad'] ?? '-') ?></p>
                    <p><strong>Sexo:</strong> <?= htmlspecialchars($paciente['sexo'] ?? '-') ?></p>
                    <p><strong>DNI:</strong> <?= htmlspecialchars($paciente['dni'] ?? 'No registrado') ?></p>
                    <p><strong>Fecha de ingreso:</strong> <?= htmlspecialchars(is_string($paciente['fecha_ingreso'] ?? null) ? $paciente['fecha_ingreso'] : '-') ?></p>
                    <p><strong>Doctor asignado:</strong> <?= htmlspecialchars($doctorAsignado) ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($rol === 'doctor'): ?>
        <div class="card p-4 mb-4 shadow-sm">
            <h5>🧾 Diagnóstico y Estado</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Estado:</label>
                    <input type="text" name="estado" class="form-control" value="<?= htmlspecialchars($paciente['estado'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Diagnóstico:</label>
                    <textarea name="diagnostico" class="form-control"><?= htmlspecialchars($paciente['diagnostico'] ?? '') ?></textarea>
                </div>
                <button type="submit" name="editar_diagnostico" class="btn btn-warning">✏️ Guardar Cambios</button>
            </form>
        </div>

        <div class="card p-4 mb-4 shadow-sm">
            <h5>🚑 Solicitar Traslado</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Hospital destino:</label>
                    <select name="hospital_destino" class="form-select" required>
                        <option value="">Selecciona un hospital</option>
                        <?php foreach ($hospitales as $h): ?>
                            <option value="<?= htmlspecialchars($h['id']) ?>"><?= htmlspecialchars($h['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Motivo del traslado:</label>
                    <textarea name="motivo_traslado" class="form-control" rows="3" required placeholder="Describe brevemente el motivo del traslado..."></textarea>
                </div>

                <button type="submit" name="solicitar_traslado" class="btn btn-danger">Enviar solicitud</button>
            </form>
        </div>

    <?php else: ?>
        <p><strong>Estado:</strong> <?= htmlspecialchars($paciente['estado'] ?? '-') ?></p>
        <p><strong>Diagnóstico:</strong> <?= htmlspecialchars($paciente['diagnostico'] ?? '') ?></p>
    <?php endif; ?>

    <?php if ($rol === 'admin'): ?>
        <div class="card p-4 mb-4 shadow-sm">
            <h5>🔁 Reasignar Doctor</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Seleccionar nuevo doctor:</label>
                    <select name="nuevo_doctor_uid" class="form-select" required>
                        <option value="">Selecciona un doctor</option>
                        <?php
                        $doctores = $firestore->collection('usuarios')
                            ->where('hospital_id', '=', $hospitalId)
                            ->where('rol', '=', 'doctor')
                            ->where('activo', '==', true)
                            ->documents();

                        foreach ($doctores as $doc) {
                            if ($doc->exists()) {
                                $d = $doc->data();
                                $doctorId = $doc->id();
                                $selected = ($paciente['doctor_uid'] ?? '') === $doctorId ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($doctorId) . "\" $selected>" . htmlspecialchars($d['nombre'] ?? $doctorId) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" name="reasignar_doctor" class="btn btn-info">Guardar cambios</button>
            </form>
        </div>
    <?php endif; ?>

    <hr>
    <h4 class="mb-3">📝 Notas Médicas</h4>
    <?php if (count($notas) > 0): ?>
        <ul class="list-group mb-3">
            <?php foreach ($notas as $nota): ?>
                <li class="list-group-item">
                    <strong><?= htmlspecialchars($nota['autor'] ?? 'Anónimo') ?></strong>
                    (<?= isset($nota['fecha']) ? (new DateTime($nota['fecha']))->format('d/m/Y') : '' ?>):<br>
                    <?= htmlspecialchars($nota['contenido'] ?? '') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No hay notas médicas registradas.</p>
    <?php endif; ?>

    <div class="card p-4 shadow-sm">
        <form method="POST">
            <label class="form-label">Agregar nota médica:</label>
            <textarea name="nueva_nota" rows="3" class="form-control mb-3" required></textarea>
            <button type="submit" class="btn btn-primary">Guardar Nota</button>
        </form>
    </div>

    <p class="mt-4"><a href="mis_pacientes.php" class="btn btn-secondary">Volver a pacientes</a></p>
</div>
