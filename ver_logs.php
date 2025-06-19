<?php
require_once 'conexion.php';
require_once 'auth.php';
require_login(['admin']);

$hospital_id = $_SESSION['hospital_id'];
$logs = [];

try {
    // Acceder a los logs dentro de la colección del hospital para evitar iterar todos globales
    $docs = $firestore
        ->collection('hospitales')
        ->document($hospital_id)
        ->collection('logs')
        ->documents();

    foreach ($docs as $doc) {
        if ($doc->exists()) {
            $log = $doc->data();

            // Obtener nombre del usuario que hizo la acción
            $usuario_uid = $log['admin_uid'] ?? $log['doctor_uid'] ?? $log['usuario_uid'] ?? null;
            $usuario_nombre = $usuario_uid;

            if ($usuario_uid) {
                $userSnap = $firestore->collection('usuarios')->document($usuario_uid)->snapshot();
                if ($userSnap->exists()) {
                    $usuario_nombre = $userSnap->data()['nombre'] ?? $usuario_uid;
                }
            }

            $log['usuario'] = $usuario_nombre;
            $logs[] = $log;
        }
    }

    // Ordenar logs por fecha descendente, si la fecha es Timestamp convertirla
    usort($logs, function ($a, $b) {
        $fechaA = null;
        $fechaB = null;

        if (isset($a['fecha']) && $a['fecha'] instanceof \Google\Cloud\Core\Timestamp) {
            $fechaA = $a['fecha']->toDateTime();
        } elseif (isset($a['fecha'])) {
            try {
                $fechaA = new DateTime($a['fecha']);
            } catch (Exception $e) {
                $fechaA = new DateTime('2000-01-01');
            }
        } else {
            $fechaA = new DateTime('2000-01-01');
        }

        if (isset($b['fecha']) && $b['fecha'] instanceof \Google\Cloud\Core\Timestamp) {
            $fechaB = $b['fecha']->toDateTime();
        } elseif (isset($b['fecha'])) {
            try {
                $fechaB = new DateTime($b['fecha']);
            } catch (Exception $e) {
                $fechaB = new DateTime('2000-01-01');
            }
        } else {
            $fechaB = new DateTime('2000-01-01');
        }

        return $fechaB <=> $fechaA;
    });
} catch (Exception $e) {
    die("❌ Error al cargar logs: " . $e->getMessage());
}
?>

<?php include 'layout.php'; ?>

<div class="main">
    <h1>🧾 Historial de Auditoría</h1>

    <?php if (empty($logs)): ?>
        <p>No hay registros de acciones recientes para este hospital.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Acción</th>
                        <th>Detalle</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <?php
                                try {
                                    if ($log['fecha'] instanceof \Google\Cloud\Core\Timestamp) {
                                        echo $log['fecha']->toDateTime()->format('d/m/Y H:i');
                                    } else {
                                        $fechaObj = new DateTime($log['fecha']);
                                        echo $fechaObj->format('d/m/Y H:i');
                                    }
                                } catch (Exception $e) {
                                    echo 'Sin fecha';
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($log['tipo'] ?? 'Acción') ?></td>
                            <td>
                                <?php
                                // Detalle dinámico según tipo
                                switch ($log['tipo']) {
                                    case 'traslado_aprobado':
                                        echo 'Traslado aprobado para paciente ' . htmlspecialchars($log['paciente_id'] ?? 'desconocido');
                                        break;
                                    case 'traslado_rechazado':
                                        echo 'Traslado rechazado (ID: ' . htmlspecialchars($log['traslado_id'] ?? '-') . ')';
                                        break;
                                    default:
                                        echo htmlspecialchars($log['detalle'] ?? '-');
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($log['usuario']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p><a href="dashboard.php" class="btn btn-secondary mt-3">← Volver al Panel</a></p>
</div>
