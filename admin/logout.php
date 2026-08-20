<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
logout_admin();
flash('success', 'You have been logged out.');
redirect('admin/login.php');
