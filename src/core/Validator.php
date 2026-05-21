<?php
/**
 * Clase Validator - Validación de datos de entrada
 * Módulo de Facturación
 */
class Validator {
    private array $errors = [];
    private array $data   = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public static function make(array $data): self {
        return new self($data);
    }

    // ─── Métodos encadenables ─────────────────────────────────────────────────

    public function required(string $field, ?string $message = null): self {
        if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
            $this->errors[$field][] = $message ?? "El campo {$field} es obligatorio";
        }
        return $this;
    }

    public function nif(string $field, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value === '') return $this;
        $error = self::validarFormatoNif($value);
        if ($error !== null) {
            $this->errors[$field][] = $message ?? $error;
        }
        return $this;
    }

    public function email(string $field, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message ?? 'El email no es válido';
        }
        return $this;
    }

    public function numeric(string $field, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field][] = $message ?? "El campo {$field} debe ser numérico";
        }
        return $this;
    }

    public function decimal(string $field, int $decimals = 2, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value !== '') {
            if (!preg_match('/^-?\d+(\.\d{1,' . $decimals . '})?$/', (string)$value)) {
                $this->errors[$field][] = $message ?? "El campo {$field} debe tener máximo {$decimals} decimales";
            }
        }
        return $this;
    }

    public function between(string $field, float $min, float $max, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value !== '') {
            $num = (float)$value;
            if ($num < $min || $num > $max) {
                $this->errors[$field][] = $message ?? "El campo {$field} debe estar entre {$min} y {$max}";
            }
        }
        return $this;
    }

    public function min(string $field, int $min, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if (strlen($value) < $min) {
            $this->errors[$field][] = $message ?? "El campo {$field} debe tener al menos {$min} caracteres";
        }
        return $this;
    }

    public function max(string $field, int $max, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if (strlen($value) > $max) {
            $this->errors[$field][] = $message ?? "El campo {$field} debe tener máximo {$max} caracteres";
        }
        return $this;
    }

    public function in(string $field, array $values, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !in_array($value, $values, true)) {
            $this->errors[$field][] = $message ?? 'El valor seleccionado no es válido';
        }
        return $this;
    }

    public function date(string $field, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && strtotime($value) === false) {
            $this->errors[$field][] = $message ?? 'La fecha no es válida';
        }
        return $this;
    }

    public function pastOrToday(string $field, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && strtotime($value) > strtotime(date('Y-m-d'))) {
            $this->errors[$field][] = $message ?? 'La fecha no puede ser futura';
        }
        return $this;
    }

    public function regex(string $field, string $pattern, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !preg_match($pattern, $value)) {
            $this->errors[$field][] = $message ?? "El formato del campo {$field} no es válido";
        }
        return $this;
    }

    public function boolean(string $field, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !in_array($value, ['1', '0', 'true', 'false', 'S', 'N', 's', 'n'], true)) {
            $this->errors[$field][] = $message ?? "El campo {$field} debe ser Sí/No";
        }
        return $this;
    }

    public function positive(string $field, ?string $message = null): self {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && (float)$value < 0) {
            $this->errors[$field][] = $message ?? 'El importe debe ser positivo';
        }
        return $this;
    }

    public function motivoRectificacion(string $field, ?string $message = null): self {
        if (($this->data['Tipo_Documento'] ?? '') === 'RECTIFICATIVA' && trim($this->data[$field] ?? '') === '') {
            $this->errors[$field][] = $message ?? 'El motivo de rectificación es obligatorio para abonos';
        }
        return $this;
    }

    public function facturaOrigen(string $field, ?string $message = null): self {
        if (($this->data['Tipo_Documento'] ?? '') === 'RECTIFICATIVA' && trim($this->data[$field] ?? '') === '') {
            $this->errors[$field][] = $message ?? 'Debe indicar la factura de origen para el abono';
        }
        return $this;
    }

    // ─── Resultados ───────────────────────────────────────────────────────────

    public function passes(): bool { return empty($this->errors); }
    public function fails():  bool { return !$this->passes(); }
    public function errors(): array { return $this->errors; }

    public function firstError(string $field): ?string {
        return $this->errors[$field][0] ?? null;
    }

    public function allErrors(): string {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $e) {
                $messages[] = $e;
            }
        }
        return implode('. ', $messages);
    }

    // ─── Validación de NIF/NIE/CIF español con dígito de control ─────────────

    /**
     * Valida el formato de NIF/NIE/CIF español.
     * Devuelve null si es válido, o string con el mensaje de error.
     *
     * Formatos aceptados (todos 9 caracteres):
     *   DNI  [0-9]{8}[A-Z]          — personas físicas españolas
     *   NIE  [XYZ][0-9]{7}[A-Z]     — extranjeros con NIE
     *   Esp  [KLM][0-9]{7}[A-Z]     — menores, residentes exterior, extranjeros sin NIE
     *   CIF  [ABCDEFGHJNPQRSUVW][0-9]{7}[A-Z0-9] — personas jurídicas
     */
    public static function validarFormatoNif(string $nif): ?string
    {
        $nif = strtoupper(trim($nif));

        if (strlen($nif) !== 9) {
            return 'El NIF debe tener exactamente 9 caracteres (tiene ' . strlen($nif) . ')';
        }

        $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';

        // DNI: 8 dígitos + letra de control (mod 23)
        if (preg_match('/^[0-9]{8}[A-Z]$/', $nif)) {
            $numero        = (int) substr($nif, 0, 8);
            $letra         = $nif[8];
            $letraCorrecta = $letras[$numero % 23];
            if ($letra !== $letraCorrecta) {
                return "DNI inválido: la letra de control debería ser '{$letraCorrecta}'";
            }
            return null;
        }

        // NIE: X/Y/Z + 7 dígitos + letra de control
        if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $nif)) {
            $mapa   = ['X' => 0, 'Y' => 1, 'Z' => 2];
            $numero = $mapa[$nif[0]] * 10_000_000 + (int) substr($nif, 1, 7);
            $letra  = $nif[8];
            $letraCorrecta = $letras[$numero % 23];
            if ($letra !== $letraCorrecta) {
                return "NIE inválido: la letra de control debería ser '{$letraCorrecta}'";
            }
            return null;
        }

        // NIF especial K/L/M — solo verificación estructural
        if (preg_match('/^[KLM][0-9]{7}[A-Z]$/', $nif)) {
            return null;
        }

        // CIF: letra de tipo + 7 dígitos + carácter de control (Luhn modificado)
        if (preg_match('/^[ABCDEFGHJNPQRSUVW][0-9]{7}[A-Z0-9]$/', $nif)) {
            $digitos      = substr($nif, 1, 7);
            $control      = $nif[8];
            $primeraLetra = $nif[0];

            $suma = 0;
            for ($i = 0; $i < 7; $i++) {
                $d = (int) $digitos[$i];
                if (($i + 1) % 2 === 1) {
                    $doble = $d * 2;
                    $suma += $doble >= 10 ? $doble - 9 : $doble;
                } else {
                    $suma += $d;
                }
            }
            $controlDigito = (10 - ($suma % 10)) % 10;
            $controlLetra  = 'JABCDEFGHI'[$controlDigito];

            $soloDigito = in_array($primeraLetra, ['A', 'B', 'E', 'H'], true);
            $soloLetra  = in_array($primeraLetra, ['P', 'Q', 'R', 'S', 'W'], true);

            if ($soloDigito) {
                if ($control !== (string) $controlDigito) {
                    return "CIF inválido: el dígito de control debería ser '{$controlDigito}'";
                }
            } elseif ($soloLetra) {
                if ($control !== $controlLetra) {
                    return "CIF inválido: la letra de control debería ser '{$controlLetra}'";
                }
            } else {
                if ($control !== (string) $controlDigito && $control !== $controlLetra) {
                    return "CIF inválido: el carácter de control debería ser '{$controlDigito}' (dígito) o '{$controlLetra}' (letra)";
                }
            }
            return null;
        }

        return 'Formato de NIF/NIE/CIF no reconocido. Se esperan 9 caracteres: DNI (8 dígitos + letra), NIE (X/Y/Z + 7 dígitos + letra), o CIF (letra entidad + 7 dígitos + control)';
    }

    // ─── Métodos estáticos de validación de documentos ───────────────────────

    /**
     * Valida los datos mínimos requeridos para crear/actualizar una factura.
     * Devuelve array plano de errores (vacío = válido).
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
     * Devuelve array plano de errores (vacío = válido).
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
     * Valida los campos de una línea de factura/albarán.
     * Devuelve array asociativo de errores por campo.
     */
    public static function validarLinea(array $linea): array
    {
        return self::make($linea)
            ->required('Id_Articulo', 'El artículo es obligatorio')
            ->required('Cantidad',    'La cantidad es obligatoria')
            ->required('Precio',      'El precio es obligatorio')
            ->numeric('Cantidad',     'La cantidad debe ser numérica')
            ->numeric('Precio',       'El precio debe ser numérico')
            ->positive('Precio',      'El precio debe ser positivo')
            ->decimal('Descuento', 2, 'El descuento debe tener máximo 2 decimales')
            ->errors();
    }
}
