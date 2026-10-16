<?php
/**
 * Front controller: todas las peticiones pasan por aquí.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

(new App\Core\App())->run($_GET['url'] ?? '');
