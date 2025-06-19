<?php
function registrar_log($firestore, $accion, $detalle) {
    if (!isset($_SESSION['uid'], $_SESSION['rol'], $_SESSION['hospital_id'])) {
        return; // Evita registrar si no hay sesión activa
    }

    try {
        $firestore->collection('logs')->add([
            'usuario_uid' => $_SESSION['uid'],
            'usuario_rol' => $_SESSION['rol'],
            'hospital_id' => $_SESSION['hospital_id'],
            'accion' => $accion,
            'detalle' => $detalle,
            'fecha' => new \Google\Cloud\Core\Timestamp(new \DateTime())
        ]);
    } catch (Exception $e) {
        error_log("Error registrando log: " . $e->getMessage());
    }
}
?>
