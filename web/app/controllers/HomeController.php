<?php
declare(strict_types=1);

// Página de inicio (privada: solo usuarios registrados). Muestra el menú principal y, de momento,
// la lista de ciudades con datos, que es también una comprobación de la conexión con la base de datos.
final class HomeController
{
    public function index(): void
    {
        Sesion::exigirIdentificado();
        $filas = [];
        $numCiudades = 0;
        $lecturas = 0;
        $error = '';
        try {
            foreach (Ciudad::todas() as $c) {
                $filas[] = [
                    'nombre'     => $c['nombre'],
                    'indicativo' => $c['indicativo'],
                    'altitud'    => $c['altitud'] ?? '—',
                ];
            }
            $numCiudades = count($filas);
            $lecturas = number_format(Lectura::contar(), 0, ',', '.');
        } catch (PDOException $e) {
            error_log('Error de base de datos: ' . $e->getMessage());
            $error = 'No se pudo conectar con la base de datos.';
        }
        Vista::render('home/index', [
            'ciudades'    => $filas,
            'numCiudades' => $numCiudades,
            'lecturas'    => $lecturas,
            'error'       => $error,
        ], 'Inicio');
    }
}
