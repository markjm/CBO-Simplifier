<?php
require_once 'php/log.php';
require_once 'php/orm_bill.php';

header("Content-Type: text/plain");

$tests = 0;
$success = 0;

//--------------------------------------
echo "[TEST] Creating a Bill from an array without financial info\r\n";

$pre_arr = array(
    'title' => 'Bi-Partisan Cheese Act of 2016',
    'code' => 'H.R. 42',
    'summary' => 'Provides for new types of cheese',
    'committee' => 'Cheese Committee',
    'cbo_url' => 'http://example.com/cbo/cheese',
    'pdf_url' => 'http://example.com/cbo/cheese.pdf',
    'published' => '2016-12-25'
);

$bill = Bill::from_array($pre_arr);
$post_arr = $bill->to_array();

$tests++;
if ($pre_arr == $post_arr) {
    $success++;
    echo "[SUCCESS]\r\n";
} else {
    echo "[FAILURE] Input array and Bill::to_array were not equal\r\n";
    echo "[EXPECTED]\r\n";
    print_r($pre_arr);
    echo "[RECEIVED]\r\n";
    print_r($post_arr);
}

//--------------------------------------
echo "[TEST] Creating a Bill from an array with financial info\r\n";

$pre_arr = array(
    'title' => 'Bi-Partisan Cheese Act of 2016',
    'code' => 'H.R. 42',
    'summary' => 'Provides for new types of cheese',
    'committee' => 'Cheese Committee',
    'cbo_url' => 'http://example.com/cbo/cheese',
    'pdf_url' => 'http://example.com/cbo/cheese.pdf',
    'published' => '2016-12-25',
    'financial' => array(
        array(
            'amount' => -5000,
            'timespan' => 1,
        ),
        array(
            'amount' => 10000,
            'timespan' => 5,
        )
    )
);

$bill = Bill::from_array($pre_arr);
$post_arr = $bill->to_array();

$tests++;
if ($pre_arr == $post_arr) {
    $success++;
    echo "[SUCCESS]\r\n";
} else {
    echo "[FAILURE] Input array and Bill::to_array were not equal\r\n";
    echo "[EXPECTED]\r\n";
    print_r($pre_arr);
    echo "[RECEIVED]\r\n";
    print_r($post_arr);
}

//--------------------------------------
echo "[TEST] Creating a Bill from a mistyped array\r\n";

$pre_arr = array(
    'title' => 'Bi-Partisan Cheese Act of 2016',
    'code' => 42,
    'summary' => 'Provides for new types of cheese',
    'committee' => 'Cheese Committee',
    'cbo_url' => 'http://example.com/cbo/cheese',
    'pdf_url' => 'http://example.com/cbo/cheese.pdf',
    'published' => '2016-12-25'
);

$bill = Bill::from_array($pre_arr);

$tests++;
if ($bill === null) {
    $success++;
    echo "[SUCCESS]\r\n";
} else {
    echo "[FAILURE] Bill::from_array accepted bogus input\r\n";
    echo "[RECEIVED]\r\n";
    print_r($bill->to_array());
}
