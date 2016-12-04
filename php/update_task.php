<?php
/*
 * There's one big goal that's important to achieve via locking - avoiding
 * running more than one update at once due to a race condition.
 *
 *
 * Consider the following:
 *
 * +-----+ +-----+ +-----+       +---------+
 * | C1  | | C2  | | C3  |       | Server  |
 * +-----+ +-----+ +-----+       +---------+
 *    |       |       |               |
 *    | GET /bills    |               |
 *    |------------------------------>|
 *    |       |       |               |
 *    |       | GET /bills            |
 *    |       |---------------------->|
 *    |       |       |               | ----------------------------------------------------------\
 *    |       |       |               |-| Server gets the timestamp, which is stale in both cases |
 *    |       |       |               | |---------------------------------------------------------|
 *    |       |       |               |
 *    |       |      Update requested |
 *    |<------------------------------|
 *    |       |       |               |
 *    |       |      Update requested |
 *    |       |<----------------------|
 *    |       |       |               |
 *    | POST /update  |               |
 *    |------------------------------>|
 *    |       |       |               |
 *    |       | POST /update          |
 *    |       |---------------------->|
 *    |       |       |               | ----------------------------------------\
 *    |       |       |               |-| Bad! Only one update can run at once. |
 *    |       |       |               | |---------------------------------------|
 *    |       |       |               |
 *
 * To avoid this, we lock when an /update comes in until it finishes.
 */

/*
 * A very primitive file-based lock, using fopen(..., "x") as its atomic
 * operation.
 */
class FileLock {
    private $filename;
    private $holding_lock;

    public function __construct($filename) {
        $this->filename = $filename;
        $this->holding_lock = false;
    }

    /*
     * Invokes the lock by trying to create the lockfile.
     *
     * On successful acquisition, returns true. On failure, returns false.
     *
     * Note that repeated locking attempts will succeed if you already hold the
     * lock.
     */
    public function lock() {
        global $LOGGER;
        if ($this->holding_lock) {
            $LOGGER->debug('(FileLock::lock) Lock already acquired');
            return true;
        }

        $handle = fopen($this->filename, 'x');
        if ($handle === false) {
            $LOGGER->debug('(FileLock::lock) Failed to take lock');
            return false;
        }

        // Note that we don't care about the contents of the file, so we can
        // get rid of the file descriptor immediately.
        $LOGGER->debug('(FileLock::lock) Took lock, closing file');
        fclose($handle);
        $this->holding_lock = true;
        return true;
    }

    /*
     * Releases the lock by trying to delete the file.
     *
     * On successful release, returns true. On failure, returns false.
     *
     * Note that this will succeed if the lock was never acquired to begin
     * with, to avoid stealing the lock from another process.
     */
    public function unlock() {
        if (!$this->holding_lock) {
            $LOGGER->debug('(FileLock::unlock) Never took lock');
            return true;
        }

        $this->holding_lock = false;
        $LOGGER->debug('(FileLock::unlock) Removing lock file');
        return unlink($this->filename);
    }
}

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
