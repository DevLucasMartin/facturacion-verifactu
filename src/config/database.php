<?php
/**
 * Clase Database - Conexión PDO MySQL (Singleton)
 */

class Database {

    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dsn = 'mysql:host=localhost;port=3307;dbname=Verifactu;charset=utf8mb4';
        $this->pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Ejecuta una consulta y devuelve el PDOStatement. */
    public function query(string $sql, array $params = []): \PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Devuelve una única fila o null si no existe. */
    public function fetch(string $sql, array $params = []): ?array {
        $row = $this->query($sql, $params)->fetch();
        return $row !== false ? $row : null;
    }

    /** Devuelve todas las filas. */
    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Devuelve el valor de la primera columna de la primera fila. */
    public function fetchCell(string $sql, array $params = []): mixed {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row !== false ? $row[0] : null;
    }

    /** Inserta una fila. Devuelve el Codigo (VARCHAR PK) o el LAST_INSERT_ID. */
    public function insert(string $table, array $data): int|string {
        $cols = array_keys($data);
        $sql  = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $cols),
            implode(', ', array_fill(0, count($cols), '?'))
        );
        $this->query($sql, array_values($data));
        return isset($data['Codigo']) ? $data['Codigo'] : (int)$this->pdo->lastInsertId();
    }

    /** Actualiza filas. Devuelve el número de filas afectadas. */
    public function update(string $table, array $data, string $where, array $whereParams = []): int {
        $sets = array_map(fn($col) => "`{$col}` = ?", array_keys($data));
        $sql  = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return $this->query($sql, array_merge(array_values($data), $whereParams))->rowCount();
    }

    /** Elimina filas. Devuelve el número de filas afectadas. */
    public function delete(string $table, string $where, array $whereParams = []): int {
        return $this->query("DELETE FROM `{$table}` WHERE {$where}", $whereParams)->rowCount();
    }
}
