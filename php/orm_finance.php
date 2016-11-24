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
                               $bill,
                               (int)$out_timespan,
                               (double)$out_amount);
        }

        $query->close();
        return $obj;
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
