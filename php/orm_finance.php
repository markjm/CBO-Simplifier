<?php
class Finance {
    private $id;
    private $timespan;
    private $amount;

    private function __construct($id, $timespan, $amount) {
        $this->id = $id;
        $this->timespan = $timespan;
        $this->amount = $amount;
    }

    /*
     * Retrieves a new Finance object from the database that has a matching ID.
     *
     * Returns null if there is no matching Finance entry.
     */
    public static function from_id($db, $id) {
        global $LOGGER;

        $query = $db->prepare('
            SELECT timespan, amount
            FROM Finances
            WHERE id = ?
        ');

        $query->bind_param('i', $id);

        $query->execute();
        $query->bind_result($out_timespan, $out_amount);

        // There should only ever be 0 or 1 rows (id is a PK), so there's no
        // danger of throwing away multiple objects here
        $obj = null;
        while ($query->fetch()) {
            $obj = new Finance($id,
                               (int)$out_timespan,
                               (double)$out_amount);
        }

        if ($obj == null) {
            $LOGGER->warning(
                'No such Finance entry for id {id}',
                array('id' => $id));
        }

        $query->close();
        return $obj;
    }

    /*
     * Constructs a Finance object from an array.
     */
    public static function from_array($array) {
        $id = null;
        $timespan = $array['timespan'];
        $amount = $array['amount'];

        if (!is_int($timespan) || $timespan < 0) return null;
        if (!is_int($amount)) return null;

        return new Finance($id, $timespan, $amount);
    }

    /*
     * Inserts this Finance entry into the database, if it isn't there already,
     * and returns the ID that it was inserted under.
     */
    public function insert($db, $bill_id) {
        if ($this->id !== null) return $this->id;

        $stmt = $db->prepare('
            INSERT INTO Finances(bill, timespan, amount)
            VALUES (?, ?, ?)
        ');

        $stmt->bind_param(
            'isd',
            $bill_id,
            $this->timespan,
            $this->amount);

        $stmt->execute();
        $stmt->close();

        $this->id = $db->insert_id;
        return $this->id;
    }

    /*
     * Converts this object into an array, suitable for emission as JSON.
     */
    public function to_array() {
        return array(
            'timespan' => $this->timespan,
            'amount' => $this->amount
        );
    }
}
