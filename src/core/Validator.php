<?php
/**
 * Validaciones básicas para el módulo de Facturación.
 */
class Validator
{
    /**
     * Valida los datos mínimos requeridos para crear/actualizar una factura.
     * Devuelve array de errores (vacío = válido).
     */
    public static function validarFactura(array $data): array
    {
        $errors = [];

        if (empty($data['Id_Canal'])) {
            $errors[] = 'El canal es obligatorio';
        }
        if (empty($data['Fecha'])) {
            $errors[] = 'La fecha es obligatoria';
        }
        if (empty($data['Id_Forma_Pago'])) {
            $errors[] = 'La forma de pago es obligatoria';
        }
        if (empty($data['Tipo_Documento'])) {
            $errors[] = 'El tipo de documento es obligatorio';
        }
        if (empty($data['lineas']) || !is_array($data['lineas'])) {
            $errors[] = 'Se requiere al menos una línea';
        }

        return $errors;
    }

    /**
     * Valida los datos mínimos requeridos para crear/actualizar un albarán.
     * Devuelve array de errores (vacío = válido).
     */
    public static function validarAlbaran(array $data): array
    {
        $errors = [];

        if (empty($data['Id_Canal'])) {
            $errors[] = 'El canal es obligatorio';
        }
        if (empty($data['Fecha'])) {
            $errors[] = 'La fecha es obligatoria';
        }
        if (empty($data['Id_Cliente'])) {
            $errors[] = 'El cliente es obligatorio';
        }
        if (empty($data['lineas']) || !is_array($data['lineas'])) {
            $errors[] = 'Se requiere al menos una línea';
        }

        return $errors;
    }

    /**
     * Valida el formato de un NIF/CIF español.
     * Devuelve null si es válido, o string con el error.
     */
    public static function validarFormatoNif(string $nif): ?string
    {
        $nif = strtoupper(trim($nif));

        if ($nif === '') {
            return 'El NIF no puede estar vacío';
        }

        // NIF persona física (8 dígitos + letra)
        if (preg_match('/^[0-9]{8}[TRWAGMYFPDXBNJZSQVHLCKE]$/', $nif)) {
            return null;
        }

        // CIF entidad (letra + 7 dígitos + letra/dígito)
        if (preg_match('/^[ABCDEFGHJKLMNPQRSUVW][0-9]{7}[0-9A-J]$/', $nif)) {
            return null;
        }

        // NIE extranjero (X/Y/Z + 7 dígitos + letra)
        if (preg_match('/^[XYZ][0-9]{7}[TRWAGMYFPDXBNJZSQVHLCKE]$/', $nif)) {
            return null;
        }

        // NIF extranjero genérico (alfanumérico, 5-20 chars)
        if (preg_match('/^[0-9A-Z]{5,20}$/', $nif)) {
            return null;
        }

        return 'Formato de NIF inválido';
    }
}
