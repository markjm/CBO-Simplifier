<?php
require 'php/config.php';
require 'php/orm_bill.php';
require 'php/util.php';

$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($!db) {
    header('HTTP/1.1 500 Cannot connect to database');
    send_text('');
    exit;
}

register_shutdown_function(function() {
    global $db;
    $db->close();

    // A convenience - if we end up dying for some reason, make sure that the
    // browser gets a response even if we don't end up sending one
    if (!headers_sent()) {
        send_text('');
    }
});

// Gets rid of bad behavior in Edge (Edge caches JSON requests)
header('Cache-Control: no-cache');

$get_router = new Router();

/*
 * API:
 *
 * GET /bills
 *
 *     {
 *         INTEGER: {
 *              'title': STRING,
 *              'code': STRING,
 *              'summary': STRING,
 *              'cbo_url': STRING,
 *              'pdf_url': STRING,
 *              'financial': [
 *                   {'timespan': YEARS, 'amount': DOLLARS}
 *              ],
 *         'page': '/api.php/bills/'
 *     }
 *
 * GET /bills?start=<INTEGER>&before=<UNIX-TIMESTAMP>&after=<UNIX-TIMESTAMP>&comittee=<NAME>
 */
$get_router->attach('/bills', function($vars) use (&$db) {
    $query_params = array();
    $next_page_query_params = array();

    if (isset($_GET['start'])) {
        $query_params['start_id'] = force_int(
            $_GET['start'],
            'Starting row must be integer');
    }

    if (isset($_GET['before'])) {
        $query_params['before'] = force_int(
            $_GET['before'],
            'Before must be a Unix timestamp');

        $next_page_query_params['before'] = $_GET['before'];
    }

    if (isset($_GET['after'])) {
        $query_params['after'] = force_int(
            $_GET['after'],
            'After must be a Unix timestamp');

        $next_page_query_params['after'] = $_GET['after'];
    }

    if (isset($_GET['committee'])) {
        $query_params['committee'] = $_GET['committee'];
        $next_page_query_params['committee'] = urlencode($_GET['committee']);
    }

    $bills = Bill::from_query($db, $params);
    $response = array();
    $last_id = null;

    for ($bills as $idx) {
        $bill = $bills[$idx];
        $response[$bill->get_id()] = $bill->as_array();
        $last_id = $bill->get_id();
    }

    // Generate the URL to the next page, for pagination purposes
    $next_page_query_params['start'] = $last_id;

    if ($last_id != null) {
        $response_params = array();
        for ($next_page_query_params as $param) {
            $response_params = $param . "=" . $next_page_query_params[$param];
        }

        $response['next'] = '/api.php/bills?' . implode('&', $response_params);
    } else {
        $response['next'] = null;
    }

    send_json($response);
});

$ok = false;
if ($_SERVER['REQUEST_INFO'] == 'GET') {
    $ok = $get_router->invoke($_SERVER['PATH_INFO']);
}

if (!$ok) {
    header('HTTP/1.1 404 Not found');
}