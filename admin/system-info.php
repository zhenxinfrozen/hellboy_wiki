<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ui.php';
admin_require_login();

\SharedAdmin\AdminCore::handleBuiltinPage('system-info');
