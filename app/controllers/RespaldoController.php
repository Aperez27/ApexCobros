<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Auditoria;
use App\Services\Respaldo;

/**
 * Respaldo de la base de datos en un archivo .sql descargable (no depende de mysqldump).
 */
class RespaldoController extends Controller
{
    public function __construct()
    {
        $this->requireRole('admin');
    }

    public function index(): void
    {
        $db = Database::connection();
        $tablas = $db->query('SELECT table_name AS nombre, table_rows AS filas, ROUND((data_length + index_length) / 1024) AS kb
            FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name')->fetchAll();
        $ultimo = $db->query("SELECT a.created_at, u.nombre FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.accion = 'respaldo' ORDER BY a.id DESC LIMIT 1")->fetch() ?: null;
        $this->view('respaldo/index', ['title' => 'Respaldo de datos', 'tablas' => $tablas, 'ultimo' => $ultimo]);
    }

    public function descargar(): void
    {
        $this->requirePost();
        $nombre = 'apex_respaldo_' . date('Y-m-d_His') . '.sql';
        Auditoria::log('respaldo', 'Descarga de respaldo completo: ' . $nombre);

        @set_time_limit(300);
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Cache-Control: private, no-store');
        $out = fopen('php://output', 'w');
        Respaldo::volcar($out, $nombre);
        fclose($out);
        exit;
    }
}
