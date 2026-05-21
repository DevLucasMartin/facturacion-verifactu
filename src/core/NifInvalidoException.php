<?php

/**
 * Excepción lanzada cuando el NIF de un cliente no supera la validación de formato.
 *
 * Extiende InvalidArgumentException para que los catch existentes la capturen si no
 * hay un bloque específico, pero añade el código del cliente y el NIF inválido para
 * que el controlador pueda devolver una respuesta estructurada al frontend.
 */
class NifInvalidoException extends \InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly string $idCliente,
        public readonly string $nifActual
    ) {
        parent::__construct($message);
    }
}
