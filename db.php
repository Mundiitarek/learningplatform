<?php
/**
 * Database Connection Handler
 * Uses PDO with prepared statements for security
 */

defined('APP_INIT') or define('APP_INIT', true);
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Log query if debugging enabled
            if (LOG_SQL_QUERIES && APP_ENV === 'development') {
                $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['LoggedPDOStatement', [$this->pdo]]);
            }

        } catch (PDOException $e) {
            // Log error securely (don't expose to user)
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection error. Please contact support.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Execute a SELECT query with parameters
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError($sql, $params, $e);
            throw $e;
        }
    }

    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute INSERT/UPDATE/DELETE with parameters
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            return $result;
        } catch (PDOException $e) {
            $this->logError($sql, $params, $e);
            throw $e;
        }
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }

    /**
     * Log database errors
     */
    private function logError($sql, $params, $exception) {
        $logMessage = date('Y-m-d H:i:s') . " - Database Error\n";
        $logMessage .= "Query: " . $sql . "\n";
        $logMessage .= "Params: " . json_encode($params) . "\n";
        $logMessage .= "Error: " . $exception->getMessage() . "\n";
        $logMessage .= "Trace: " . $exception->getTraceAsString() . "\n\n";

        if (!file_exists(LOG_PATH)) {
            mkdir(LOG_PATH, 0755, true);
        }

        file_put_contents(
            LOG_PATH . '/db_errors_' . date('Y-m-d') . '.log',
            $logMessage,
            FILE_APPEND
        );
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Global database instance getter
function db() {
    return Database::getInstance();
}
