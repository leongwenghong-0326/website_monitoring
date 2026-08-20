<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (file_exists(__DIR__ . '/install.lock')) {
    redirect('admin/login.php');
}
header('Location: install.php');
exit;
