<?php
define('DB_HOST',     'localhost');
define('DB_PORT',     '5432');
define('DB_NAME',     'invoice_tracker');
define('DB_USER',     get_current_user());   // uses your macOS username automatically
define('DB_PASS',     '');

define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');  // https://aistudio.google.com/apikey
define('GEMINI_MODEL',   'gemini-2.5-flash');

define('UPLOADS_DIR',  __DIR__ . '/../uploads/');
define('UPLOADS_URL',  '/uploads/');
define('SCRIPTS_DIR',  __DIR__ . '/../scripts/');
define('NODE_BIN',     '/usr/local/bin/node');
