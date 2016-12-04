<?php
// Information used to connect to the database
define('DB_HOST', 'db.example.com');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_NAME', 'the_database');

// How often to run the update task (in seconds), and what command line to run
// as the update task
define('UPDATE_TASK_FREQUENCY', 24 * 60 * 60); // 1 day
define('UPDATE_TASK_COMMAND', '/bin/some-command with-args');

// What file to use for locking, to prevent multiple updates from being run at
// once. Note that this path must allow the web server to create files.
define('LOCK_FILE', '/tmp/bills.lock');

// How many bills should be packed into the same page
define('PAGE_SIZE', 42);
