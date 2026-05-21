<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['usuario'])) {
    header('Location: /SistemaGestionFacturas/src/views/facturas/listado.php');
} else {
    header('Location: /SistemaGestionFacturas/src/views/login.php');
}
exit;
