<?php
require 'php/config.php';
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

// The data from the home page, in array form for feeding to the DB
$bills = array(
    array(
        'title' => 'Postal Service Financial Improvement Act',
        'summary' => 'This bill requires the Department of the Treasury to: (1) invest a specified percentage of the Postal Service Retiree Health Benefits Fund, using one or more qualified professional asset managers, in index funds modeled after those established for Thrift Savings Fund investments; and (2) ensure that the investment replicates the performance of the longest-term target date asset allocation investment fund established by the Federal Retirement Thrift Investment Board.',
        'cbo_url' => 'https://www.cbo.gov/publication/52130',
        'pdf_url' => 'https://www.cbo.gov/sites/default/files/114th-congress-2015-2016/costestimate/hr5707.pdf',
        'financial' => array(
            array('timespan' => 10, 'amount' => -4.6e9)
        )
    ),

    array(
        'title' => 'ADA Education and Reform Act of 2016',
        'code' => 'H.R. 5707',
        'summary' => 'H.R. 3765 would require the Department of Justice (DOJ) to establish a program to educate state and local governments and property owners on public accommodations for persons with disabilities.'
        'cbo_url' => 'https://www.cbo.gov/publication/52131',
        'pdf_url' => 'https://www.cbo.gov/sites/default/files/114th-congress-2015-2016/costestimate/hr3765.pdf',
        'financial' => array(
            array('timespan' => 5, 'amount' => -15e6)
        )
    ),

    array(
        'title' => 'Access for Sportfishing Act',
        'code' => 'S. 3099',
        'summary' => 'S. 3099 would prohibit the National Park Service (NPS) from implementing or enforcing restrictions on fishing in Biscayne National Park in Florida, unless the restrictions are developed in coordination with the Florida Fish and Wildlife Conservation Commission (FWC) and are the least restrictive measures necessary for effective management of the fishery. The bill also would require the National Oceanic and Atmospheric Administration (NOAA) to issue a final regulation implementing the Billfish Conservation Act of 2012, which prohibits the sale of billfish, within 45 days of enactment of the bill. Finally, S. 3099 would amend the Magnuson-Stevens Fishery Conservation and Management Act to prohibit people from feeding sharks in federal waters.',
        'cbo_url' => 'https://www.cbo.gov/publication/52132',
        'pdf_url' => 'https://www.cbo.gov/sites/default/files/114th-congress-2015-2016/costestimate/s3099.pdf',
        'financial' => array(
            array('timespan' => 0, 'amount' => -500e3)
        )
    ),

    array(
        'title' => 'Small Business Subcontracting Transparency Act',
        'code' => 'S. 2138',
        'summary' => 'S. 2138 would authorize Small Business Administration (SBA) employees that review federal contracts to delay the acceptance of a subcontracting plan for up to 30 days if the plan fails to maximize the participation of small businesses. The bill also would require the SBA to issue regulations that provide guidance on how to comply with the requirement to maximize small business participation.',
        'cbo_url' => 'https://www.cbo.gov/publication/52134',
        'pdf_url' => 'https://www.cbo.gov/sites/default/files/114th-congress-2015-2016/costestimate/s2138.pdf',
        'financial' => array(
            array('timespan' => 5, 'amount' => -11e6)
        )
    ),

    array(
        'title' => 'Veterans First Act',
        'code' => 'S. 2921',
        'summary' => 'To amend title 38, United States Code, to improve the accountability of employees of the Department of Veterans Affairs, to improve health care and benefits for veterans, and for other purposes.',
        'cbo_url' => 'https://www.cbo.gov/publication/52133',
        'pdf_url' => 'https://www.cbo.gov/sites/default/files/114th-congress-2015-2016/costestimate/s2921.pdf',
        'financial' => array(
            array('timespan' => 10, 'amount' => 3.9e9),
            array('timespan' => 5, 'amount' => 3.5e9)
        )
    ),

    array(
        'title' => 'Military Residency Choice Act',
        'code' => 'H.R. 5248',
        'summary' => 'H.R. 5428 would allow spouses of service members to claim the same state of residence as the service member for those purposes, regardless of whether the spouse had ever resided in that state.',
        'cbo_url' => 'https://www.cbo.gov/publication/52135',
        'pdf_url' => 'https://www.cbo.gov/sites/default/files/114th-congress-2015-2016/costestimate/hr5428.pdf',
        'financial' => array()
    )
);

// Wiping out old test data, and initializing the schema
$db->execute('DROP TABLE IF EXISTS Bills');
$db->execute('DROP TABLE IF EXISTS Finances');

$db->execute('
    CREATE TABLE Bills(
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(128) NOT NULL,
        summary VARCHAR(1024) NOT NULL,
        code VARCHAR(16) NOT NULL,
        cbo_url VARCHAR(256) NOT NULL,
        pdf_url VARCHAR(256) NOT NULL
    )
');

$db->execute('
    CREATE TABLE Finances(
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        bill INTEGER NOT NULL,
        timespan INTEGER NOT NULL,
        amount DOUBLE NOT NULL,

        FOREIGN KEY (bill) REFERENCES Bills(id)
    )
');

for ($bills as $bill) {
    $stmt = $db->prepare('
        INSERT INTO Bills(title, summary, code, cbo_url, pdf_url)
        VALUES (?, ?, ?, ?, ?)
    ');

    $stmt->bind_param(
        'sssss',
        $bill['title'],
        $bill['summary'],
        $bill['code'],
        $bill['cbo_url'],
        $bill['pdf_url']);

    $stmt->execute();

    $bill_id = $mysqli->insert_id;
    foreach ($bill['financial'] as $finance) {
        $stmt = $db->prepare('
            INSERT INTO Finances(bill, timespan, amount)
            VALUES (?, ?, ?)
        ');

        $stmt->bind_param(
            'isd',
            $bill_id,
            $finance['timespan'],
            $finance['amount']);

        $stmt->execute();
    }
}