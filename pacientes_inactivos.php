<?php
require_once 'auth.php';
require_once 'conexion.php';
require_login(['admin', 'doctor', 'nurse']);

$hospitalId = $_SESSION['hospital_id'];
$mensaje = '';
$pacientes = [];

// Obtener pacientes inhabilitados en la colección raíz
try {
    $pacientesRef = $firestore->collection('pacientes')
        ->where('hospital_id', '=', $hospitalId)
        ->where('activo', '==', false)
        ->documents();

    foreach ($pacientesRef as $doc) {
        if ($doc->exists()) {
            $data = $doc->data();
            $data['id'] = $doc->id();
            $pacientes[] = $data;
        }
    }
} catch (Exception $e) {
    $mensaje = "❌ Error al cargar pacientes inactivos: " . $e->getMessage();
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>Pacientes Inhabilitados</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <a href="mis_pacientes.php" class="btn btn-secondary mb-3">⬅ Volver a Pacientes Activos</a>

    <?php if (empty($pacientes)): ?>
        <p>No hay pacientes inhabilitados actualmente.</p>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Edad</th>
                    <th>Sexo</th>
                    <th>Estado</th>
                    <th>Diagnóstico</th>
                    <th>Fecha Ingreso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pacientes as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['edad'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['sexo'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['estado'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['diagnostico'] ?? '-') ?></td>
                        <td>
                            <?php
                                if (!empty($p['fecha_ingreso'])) {
                                    $fecha = date_create($p['fecha_ingreso']);
                                    echo $fecha ? date_format($fecha, 'd/m/Y') : '-';
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                        <td>
                            <a href="habilitar_paciente.php?id=<?= urlencode($p['id']) ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Deseas habilitar este paciente?');">🔁 Habilitar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
