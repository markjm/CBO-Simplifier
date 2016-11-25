<?php
require 'php/config.php';
require 'php/log.php';
require 'php/orm_bill.php';
require 'php/util.php';

$LOGGER = get_error_logger('api.php');
$LOGGER->set_level(LOG_DEBUG);

$LOGGER->debug('----- CONNECTION -----');

$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
    header('HTTP/1.1 500 Cannot connect to database');
    send_text('');
    exit;
}

register_shutdown_function(function() {
    global $db;
    $LOGGER->debug('Closing database');
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
$get_router->attach('/bills', function($vars) use (&$LOGGER, &$db) {
    $query_params = array();
    $next_page_query_params = array();

    if (isset($_GET['start'])) {
        $LOGGER->debug('start = {start}', $_GET);

        $query_params['start_id'] = force_int(
            $_GET['start'],
            'Starting row must be integer');
    }

    if (isset($_GET['before'])) {
        $LOGGER->debug('before = {before}', $_GET);

        $query_params['before'] = force_int(
            $_GET['before'],
            'Before must be a Unix timestamp');

        $next_page_query_params['before'] = $_GET['before'];
    }

    if (isset($_GET['after'])) {
        $LOGGER->debug('after = {after}', $_GET);

        $query_params['after'] = force_int(
            $_GET['after'],
            'After must be a Unix timestamp');

        $next_page_query_params['after'] = $_GET['after'];
    }

    if (isset($_GET['committee'])) {
        $LOGGER->debug('committee = {committee}', $_GET);

        $query_params['committee'] = $_GET['committee'];
        $next_page_query_params['committee'] = urlencode($_GET['committee']);
    }

    $bills = Bill::from_query($db, $query_params);
    $response = array();
    $last_id = null;

    foreach ($bills as $idx) {
        $bill = $bills[$idx];
        $response[$bill->get_id()] = $bill->as_array();
        $last_id = $bill->get_id();
    }

    $LOGGER->debug('Last ID was {last_id}', array('last_id' => $last_id));

    // Generate the URL to the next page, for pagination purposes
    $next_page_query_params['start'] = $last_id;

    if ($last_id != null) {
        $response_params = array();
        foreach ($next_page_query_params as $key => $value) {
            $response_params = $key . "=" . $value;
        }

        $response['next'] = '/api.php/bills?' . implode('&', $response_params);
    } else {
        $response['next'] = null;
    }

    $LOGGER->debug('Next page URL: {next_url}', array('next_url' => $response['next']));

    send_json($response);
});

$ok = false;
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $ok = $get_router->invoke($_SERVER['PATH_INFO']);
}

if (!$ok) {
    header('HTTP/1.1 404 Not found');
}
