<?php
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['hotel_staff']);
session_destroy();
header('Location: /travelix/hotel_portal/login.php');
exit;
