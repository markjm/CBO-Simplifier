<?php
require_once 'php/config.php';
require_once 'php/log.php';
require_once 'php/orm_bill.php';
require_once 'php/util.php';

$LOGGER = get_error_logger('api.php');
$LOGGER->set_level(ULOG_DEBUG);

$LOGGER->debug('----- CONNECTION -----');

$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
    header('HTTP/1.1 500 Cannot connect to database');
    send_text('');
    exit;
}

register_shutdown_function(function() {
    global $LOGGER;
    $LOGGER->debug('Closing database');

    global $db;
    $db->close();

    // A convenience - if we end up dying for some reason, make sure that the
    // browser gets a response even if we don't end up sending one
    global $RESPONSE_SENT;
    if (!$RESPONSE_SENT) {
        $LOGGER->debug('Sending emergency text/plain empty response');
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

    if (!isset($_GET['order'])) {
        http404('order parameter is required');
    }

    /*
     * The order parameter allows the frontend to request ordering from the
     * backend - something it couldn't do otherwise because of the limits
     * imposed by pagination.
     */

    // The format of order is 'param dir', where 'param' could be 'date',
    // 'committee', or 'cost' and order could be 'asc' or 'desc'
    $order_param_dir = explode(' ', $_GET['order']);
    if (count($order_param_dir) != 2) {
        http404('order parameter is malformed');
    }

    $order_param = $order_param_dir[0];
    $order_dir = $order_param_dir[1];

    if (!in_array($order_param, array("date", "committee", "cost"))) {
        http404('order must order by date, committee or cost');
    }

    if (!in_array($order_dir, array("asc", "desc"))) {
        http404('order must order as asc or desc');
    }

    $LOGGER->debug('order = {order}', $_GET);

    // Load up all the URL parameters we care about, so that the ORM will 
    // consider them when we do our querying
    if (isset($_GET['start'])) {
        $LOGGER->debug('start = {start}', $_GET);

        $query_params['start'] = force_int(
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

    // The reason for PAGE_SIZE+1 is to simplify the API - it lets us do a
    // check to see if we're on the last page before the frontend requets it,
    // by seeing if we get all the elements back we expect or not.
    //
    // The drawback is that we throw away the final element; we can't include
    // it since we're bound to the configured PAGE_SIZE
    $bills = Bill::from_query($db, $order_param, $order_dir, $query_params, PAGE_SIZE + 1);
    $bill_array = array();
    $response = array();

    // We need this to figure out where this page ends, so we can make a URL
    // for the next page
    $last_id = null;
    $generate_next_page = false;

    foreach ($bills as $bill) {
        // If this is the case, we're looking at our sentinel "+1 bill", which
        // we can't return
        if (count($bill_array) == PAGE_SIZE) {
            $generate_next_page = true;
            break;
        }

        array_push($bill_array, $bill->to_array());
        $last_id = $bill->get_id();
    }

    $LOGGER->debug('Last ID was {last_id}', array('last_id' => $last_id));
    $response['bills'] = $bill_array;

    // Generate the URL to the next page, for pagination purposes
    $next_page_query_params['start'] = $last_id;

    if ($generate_next_page) {
        $response_params = array();
        foreach ($next_page_query_params as $key => $value) {
            array_push($response_params, $key . "=" . $value);
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
