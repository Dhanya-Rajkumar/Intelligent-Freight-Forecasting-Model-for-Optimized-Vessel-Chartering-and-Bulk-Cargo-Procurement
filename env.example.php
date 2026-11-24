<?php
/**
 * Example environment configuration.
 * Copy to env.php (gitignored in a real deployment) and set putenv() calls,
 * or configure these as real OS/webserver environment variables instead.
 * db.php reads getenv() first and only falls back to XAMPP defaults.
 */
putenv('FIQ_DB_HOST=localhost');
putenv('FIQ_DB_NAME=freightiq_marine');
putenv('FIQ_DB_USER=root');
putenv('FIQ_DB_PASS=');
