<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
redirect('admin/dashboard.php');
