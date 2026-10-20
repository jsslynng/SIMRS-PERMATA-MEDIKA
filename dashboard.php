<?php
require_once __DIR__.'/includes/auth.php';
require_login();
header('Location: '.role_home(current_role()));
exit;
