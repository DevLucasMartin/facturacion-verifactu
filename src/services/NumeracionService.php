<?php
/**
 * Servicio de Numeración - Asignación de números a documentos
 * Módulo de Facturación - Versión 3.2
 *
 * Patrón código: AAAACCNNNNNNNNNNNNN (19 chars)
 *   AAAA = ejercicio | CC = canal/serie | N×13 = secuencial con ceros
 */

class NumeracionService
{
    private Database $db;
    private array $config;

    public const TIPO_FACTURA        = 'FACTURA';
    public const TIPO_SIMPLIFICADA   = 'SIMPLIFICADA';
    public const TIPO_RECTIFICATIVA  = 'RECTIFICATIVA';
    public const TIPO_RECAPITULATIVA = 'RECAPITULATIVA';
    public const TIPO_TICKET         = 'TICKET';
    public const TIPO_ALBARAN        = 'ALBARAN';

    public const TIPOS_DOCUMENTO = [
        self::TIPO_FACTURA,
        self::TIPO_SIMPLIFICADA,
        self::TIPO_RECTIFICATIVA,
        self::TIPO_RECAPITULATIVA,
        self::TIPO_TICKET,
        self::TIPO_ALBARAN,
    ];

    public function __construct()
    {
        $this->db     = Database::getInstance();
        $this->config = require __DIR__ . '/../config/app.php';
    }

    /**
     * Asignar el siguiente número a un documento (dentro de transacción propia).
     *
     * @return array ['numero' => int, 'codigo' => string, 'ejercicio' => int, ...]
     * @throws Exception
     */
    public function asignarNumero(string $idCanal, int $ejercicio, string $tipoDocumento): array
    {
        if (!in_array($tipoDocumento, self::TIPOS_DOCUMENTO, true)) {
            throw new Exception("Tipo de documento no válido: {$tipoDocumento}");
        }

        $this->db->beginTransaction();
        try {
            $tabla = $this->getTablaDocumento($tipoDocumento);

            // FOR UPDATE bloquea el rango para reducir problemas de concurrencia
            $fila = $this->db->fetch(
                "SELECT IFNULL(MAX(Numero), 0) AS Ultimo
                 FROM `{$tabla}`
                 WHERE Id_Canal = ?
                   AND LEFT(Codigo, 4) = ?
                 FOR UPDATE",
                [$idCanal, (string)$ejercicio]
            );

            $siguiente = (int)($fila['Ultimo'] ?? 0) + 1;
            $this->db->commit();

            return [
                'numero'         => $siguiente,
                'codigo'         => $this->generarCodigo($ejercicio, $idCanal, $siguiente),
                'ejercicio'      => $ejercicio,
                'canal'          => $idCanal,
                'tipo_documento' => $tipoDocumento,
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception('Error al asignar número: ' . $e->getMessage());
        }
    }

    private function getTablaDocumento(string $tipoDocumento): string
    {
        return $tipoDocumento === self::TIPO_ALBARAN
            ? 'Albaranes_Clientes'
            : 'Facturas_Clientes';
    }

    /** Generar código de documento (4+2+13 = 19 chars). */
    public function generarCodigo(int $ejercicio, string $canal, int $numero): string
    {
        return $ejercicio
             . str_pad(substr($canal, 0, 2), 2, ' ', STR_PAD_RIGHT)
             . str_pad($numero, 13, '0', STR_PAD_LEFT);
    }

    public static function extraerEjercicio(string $codigo): int    { return (int)substr($codigo, 0, 4); }
    public static function extraerCanal(string $codigo): string     { return trim(substr($codigo, 4, 2)); }
    public static function extraerNumero(string $codigo): int       { return (int)substr($codigo, 6, 13); }
    public static function validarCodigo(string $codigo): bool      { return (bool)preg_match('/^\d{4}[A-Z0-9 ]{2}\d{13}$/', $codigo); }

    public function getUltimoNumero(string $idCanal, int $ejercicio, string $tipoDocumento): int
    {
        if (!in_array($tipoDocumento, self::TIPOS_DOCUMENTO, true)) {
            throw new Exception("Tipo de documento no válido: {$tipoDocumento}");
        }
        $tabla = $this->getTablaDocumento($tipoDocumento);
        $fila  = $this->db->fetch(
            "SELECT IFNULL(MAX(Numero), 0) AS Ultimo
             FROM `{$tabla}`
             WHERE Id_Canal = ? AND LEFT(Codigo, 4) = ?",
            [$idCanal, (string)$ejercicio]
        );
        return (int)($fila['Ultimo'] ?? 0);
    }

    public function getSiguienteNumero(string $idCanal, int $ejercicio, string $tipoDocumento): int
    {
        return $this->getUltimoNumero($idCanal, $ejercicio, $tipoDocumento) + 1;
    }

    public function getSiguienteCodigo(string $idCanal, int $ejercicio, string $tipoDocumento): string
    {
        return $this->generarCodigo(
            $ejercicio,
            $idCanal,
            $this->getSiguienteNumero($idCanal, $ejercicio, $tipoDocumento)
        );
    }

    /** Estado de numeración — requiere tabla Numeracion_Canales (opcional). */
    public function getEstadoNumeracion(?int $ejercicio = null): array
    {
        $ejercicio = $ejercicio ?? (int)date('Y');
        return $this->db->fetchAll(
            'SELECT NC.Id_Canal, NC.Ejercicio, NC.Tipo_Documento, NC.Ultimo_Numero,
                    C.Descripcion AS Canal_Descripcion
             FROM Numeracion_Canales NC
             LEFT JOIN Canales C ON C.Codigo = NC.Id_Canal
             WHERE NC.Ejercicio = ?
             ORDER BY NC.Id_Canal, NC.Tipo_Documento',
            [$ejercicio]
        );
    }
}
