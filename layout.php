
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>MediGest - Sistema Hospitalario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="favicon.png" type="image/png">

    <style>
        body {
            background-color: #f4f6f8;
            font-family: 'Segoe UI', sans-serif;
        }
        .sidebar {
            width: 220px;
            background-color: #ffffff;
            border-right: 1px solid #dee2e6;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 1.5rem 1rem;
        }
        .sidebar a {
            display: block;
            color: #2a4e9b;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 500;
        }
        .sidebar a:hover {
            background-color: #e9efff;
        }
        .sidebar .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2a4e9b;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .main {
            margin-left: 220px;
            padding: 2rem;
        }
        .card {
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <i class="bi bi-hospital"></i> MediGest
    </div>
        <a href="dashboard.php"><i class="bi bi-house-door"></i> Inicio</a>
    <?php if ($_SESSION['rol'] !== 'admin'): ?>
        <a href="ver_detalle_personal.php"><i class="bi bi-person-circle"></i> Mi Perfil</a>
    <?php endif; ?>
        <a href="mis_pacientes.php"><i class="bi bi-person-vcard"></i> Pacientes</a>
        <a href="ver_citas.php"><i class="bi bi-calendar-check"></i> Citas</a>
    <?php if ($_SESSION['rol'] === 'admin'): ?>
        <a href="ver_personal.php"><i class="bi bi-people"></i> Personal Médico</a>
    <?php endif; ?>
        <a href="configuracion.php"><i class="bi bi-gear"></i> Configuración</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a>
</div>
<div class="main">
<?php
?>
</div>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
