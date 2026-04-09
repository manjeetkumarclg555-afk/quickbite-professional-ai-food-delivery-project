<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

session_destroy();
session_start();
flash('success', 'You have been logged out.');
redirect('login.php');
?>
