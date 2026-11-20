<?php /* Cabecera común (RQ25). Variables: $titulo */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'CCA') ?> · Climate Change Analyzer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="d-flex flex-column min-vh-100">
<header>
    <nav class="navbar navbar-expand navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?= URL_BASE ?>index.php">Climate Change Analyzer</a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>index.php">Inicio</a></li>
                <?php if (hay_sesion()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>ciudades.php">Estudios</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>perfil.php">Perfil</a></li>
                    <?php if (es_administrador()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>administración.php">Administración</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>logout.php">Cerrar sesión (<?= e($_SESSION['nick']) ?>)</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>login.php">Iniciar sesión</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= URL_BASE ?>registro.php">Registrarse</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
</header>
<main class="container my-4 flex-grow-1">
