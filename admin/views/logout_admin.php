<?php
// /admin/logout_admin.php
require_once __DIR__ . '/includes/auth_admin.php';
startAdminSession();
adminLogout();
header('Location: login.php');
exit;