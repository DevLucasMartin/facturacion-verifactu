<?php
session_start();
session_destroy();
header('Location: /SistemaGestionFacturas/src/views/login.php');
exit;
