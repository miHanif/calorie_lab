<?php
require_once 'koneksi.php';

$_SESSION = [];
session_destroy();

header('Location: index.php');
exit;