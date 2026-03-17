<?php
// Incluye este archivo al inicio de cada página protegida:
// require_once 'auth.php';
//
// Ejemplo en stats.php, fax.php, box.php, editfax.php, mov.php:
//   <?php
//   require_once 'auth.php';
//   require_once 'mySQLi.php';
//   ...

session_start();

if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header('Location: index.php');
    exit;
}
