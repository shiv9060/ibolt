<?php
declare(strict_types=1);

/**
 * Admin Logout Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/auth.php';

init_session();
logout_admin();

header("Location: login.php?logged_out=1");
exit;
