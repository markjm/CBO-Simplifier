<?php
/*
 * Stores the given timestamp in the database, marking that timestamp as the
 * most recent time the update task was run.
 */
function update_task_timestamp($db, $timestamp) {
    global $LOGGER;
    $LOGGER->debug(
        'Setting last-update timestamp to {timestamp}', 
        array('timestamp' => $timestamp));

    // MySQL has a wacky "upsert" syntax, see:
    // http://stackoverflow.com/questions/4205181/insert-into-a-mysql-table-or-update-if-exists
    $stmt = $db->prepare("
        INSERT INTO Attributes(attr, value) 
        VALUES ('last_updated', ?)
        ON DUPLICATE KEY UPDATE value=?
    ");

    $stmt->bind_param('ss', $timestamp, $timestamp);
    $stmt->execute();
    $stmt->close();
}

/*
 * Returns true if the update task needs to be run, false otherwise.
 */
function should_run_update_task($db) {
    global $LOGGER;

    $LOGGER->debug('Checking last-update timestamp');

    $now = time();
    $result = $db->query("
        SELECT CAST(value AS UNSIGNED INTEGER) AS timestamp 
        FROM Attributes
        WHERE attr = 'last_updated'
    ");

    $row = $result->fetch_assoc();

    $should_update = false;
    if ($row === FALSE) {
        $LOGGER->debug('Needs to run - timestamp not present');
        $should_update = true;
    } else if ($now - $row['timestamp'] >= UPDATE_TASK_FREQUENCY) {
        $LOGGER->debug('Needs to run - timestamp too old');
        $should_update = true;
    } else {
        $LOGGER->debug('Run not required - timestamp fresh');
    }

    $result->free();
    return $should_update;
}

/*
 * Runs the update task.
 */
function run_update_task($db) {
    global $LOGGER;

    $now = time();
    update_task_timestamp($db, $now);

    $LOGGER->debug('Executing update task');
    exec(UPDATE_TASK_COMMAND);
}
