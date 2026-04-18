<?php
/**
 * Excepción lanzada cuando el NIF de un cliente no es válido.
 */
class NifInvalidoException extends RuntimeException
{
    public string $idCliente;
    public string $nifActual;

    public function __construct(string $message, string $idCliente = '', string $nifActual = '')
    {
        parent::__construct($message);
        $this->idCliente = $idCliente;
        $this->nifActual = $nifActual;
    }
}
