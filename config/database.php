<?php
/**
 * Database configuration.
 * On Hostinger: set $host, $db_name, $username, $password to your hosting panel values.
 * To see the real error behind HTTP 500, temporarily add at the very top of this file (after <?php):
 *   ini_set('display_errors', 1); error_reporting(E_ALL);
 * Then remove those lines after fixing.
 */
class Database
{
    private $host = "localhost";
    private $db_name = "denrdb";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("DENR DB Connection Error: " . $e->getMessage());
            if (php_sapi_name() !== 'cli' && (ini_get('display_errors') || getenv('DENR_DEBUG'))) {
                echo "Connection Error: " . htmlspecialchars($e->getMessage());
            }
        }
        return $this->conn;
    }
}
