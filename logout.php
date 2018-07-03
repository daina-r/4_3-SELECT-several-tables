<?php
session_start();

if (empty($_SESSION)) {
    header('Location: login.php');
    exit;
}

session_destroy();
header('Location: login.php');
