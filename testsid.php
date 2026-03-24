<?php
session_start();
$_SESSION['admin_id'] = 1;
echo session_id();
