<?php
/**
 * Alias de compatibilidad: las APIs referencian /../core/Database.php
 * pero la clase real vive en /../config/database.php
 */
require_once __DIR__ . '/../config/database.php';
