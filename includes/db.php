<?php
/**
 * SiteCatalogo - Conexão com Banco de Dados (PDO/MySQL)
 */

// Define ROOT_PATH apenas se ainda não existir
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__FILE__)));
}

// Carrega config.php apenas se as constantes do banco ainda não existirem
if (!defined('DB_HOST') && file_exists(ROOT_PATH . '/config.php')) {
    require_once ROOT_PATH . '/config.php';
}

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {

            if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
                die(
                    '<h2 style="font-family:sans-serif;color:#dc2626;padding:40px;">
                        Sistema não configurado.
                        <a href="/install/">Clique aqui para instalar</a>.
                    </h2>'
                );
            }

            try {
                self::$instance = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]
                );

            } catch (PDOException $e) {
                die('Erro de conexão com o banco de dados: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function prefix(string $table): string {
        return (defined('DB_PREFIX') ? DB_PREFIX : 'sc_') . $table;
    }

    private function __clone() {}
}

function db(): PDO {
    return Database::getInstance();
}

function table(string $name): string {
    return Database::prefix($name);
}