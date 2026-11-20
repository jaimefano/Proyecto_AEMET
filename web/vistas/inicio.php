<?php require __DIR__ . '/plantilla/header.php'; ?>

<h1>Climate Change Analyzer</h1>
<p class="lead">Estudios climáticos a partir de datos reales de AEMET.</p>

<?php if ($sesion_activa): ?>
    <p>Hola, <?= e($_SESSION['nick']) ?>. Elige una ciudad para empezar un estudio.</p>
    <a class="btn btn-primary" href="<?= URL_BASE ?>ciudades.php">Seleccionar ciudad</a>
<?php else: ?>
    <p>El contenido solo está disponible para usuarios registrados.</p>
    <a class="btn btn-primary" href="<?= URL_BASE ?>login.php">Iniciar sesión</a>
    <a class="btn btn-outline-primary" href="<?= URL_BASE ?>registro.php">Registrarse</a>
<?php endif; ?>

<?php require __DIR__ . '/plantilla/footer.php'; ?>
