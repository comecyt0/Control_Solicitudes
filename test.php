<?php
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_nombre'] = "Admin Prueba";
$_SESSION['admin_email'] = "prueba@test.com";
echo session_id();
