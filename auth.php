<?php
session_start();
function require_login(array $roles_permitidos = []) {
    if (!isset($_SESSION['uid']) || !isset($_SESSION['rol']) || !isset($_SESSION['hospital_id'])) {
        header("Location: login.php");
        exit();
    }
    if (!empty($roles_permitidos) && !in_array($_SESSION['rol'], $roles_permitidos, true)) {
        die("❌ Acceso denegado. Tu rol no tiene permisos para acceder a esta sección.");
    }
}
?>
