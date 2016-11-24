<?php
class Bill {
    private $id;
    private $title;
    private $summary;
    private $code;
    private $cbo_link;
    private $pdf_link;
    private $finances;

    private function __construct($id,
                                 $title,
                                 $summary,
                                 $code,
                                 $cbo_link,
                                 $pdf_link) {
        $this->id = $id;
        $this->title = $title;
        $this->summary = $summary;
        $this->code = $code;
        $this->cbo_link = $cbo_link;
        $this->pdf_link = $pdf_link;
        $this->finances = array();
    }

    public function get_id() { return $this->id; }

    /*
     * Returns the Bill associated with the given ID, or null if no such Bill
     * exists.
     */
    public static function from_id($db, $id) {
        $query = $db->prepare('
            SELECT title, summary, code, cbo_url, pdf_url
            FROM Bills
            WHERE id = ?
        ');

        $query->bind_param('i', $id);

        $query->execute();
        $query->bind_result($out_title,
                            $out_summary,
                            $out_code,
                            $out_cbo_url,
                            $out_pdf_url);

        // There should only ever be 0 or 1 rows (id is a PK), so there's no
        // danger of throwing away multiple objects here
        $obj = null;
        while ($query->fetch()) {
            $obj = new Bill($id,
                            $out_summary,
                            $out_code,
                            $out_cbo_url,
                            $out_pdf_url);
        }

        $query->close();
        if ($obj == null) {
            return null;
        }

        // Make sure that all the Finance records is also associated with the
        // Bill
        $query = $db->prepare('SELECT id FROM Finances WHERE bill = ?');
        $query->bind_param('i', $id);

        iter_stmt_result($query, function($row) use (&$db, &$obj) {
            array_push($obj->finances, Finance::from_id($db, $row['id']));
        });

        return $obj;
    }

    /*
     * Returns an array of Bills that match a given set of conditions.
     *
     * Conditions should be an array of the form (though all of the parts are optional)
     *
     *     array('before' => DATE,
     *           'after' => DATE,
     *           'committee' => STRING,
     *           'start_id' => INTEGER)
     */
    public static function from_query($db, $params, $page_size=25) {
        $sql = 'SELECT id FROM Bills';
        $sql_suffix = ' ORDER BY id DESC LIMIT ' . $page_size;
        $conditions = array('id < ?');
        $params = array($last_id);
        $param_types = 'i';

        for ($params as $param_name) {
            $param_value = $params[$param_name];

            switch ($param_name) {
            case 'before':
                array_push($conditions, 'published <= ?');
                array_push($params, sqldatetime($param_value));
                $param_types = $param_types . 's';
                break;

            case 'after':
                array_push($conditions, 'published >= ?');
                array_push($params, sqldatetime($param_value));
                $param_types = $param_types . 's';
                break;

            case 'committee':
                array_push($conditions, 'committee = ?');
                array_push($params, $param_value);
                $param_types = $param_types . 's';
                break;
            }
        }

        $stmt= $db->prepare($sql . ' WHERE ' . implode($conditions, ' AND ') . $sql_suffix);

        call_user_func_array(
            array($stmt, 'bind_param'),
            array_merge(array($param_types), $params));

        $ids = array();
        iter_stmt_result($stmt, function($row) use (&$results) {
            array_push($ids, $row['id']);
        });

        $results = array();
        foreach ($ids as $id) {
            array_push($results, Bill::from_id($db, $id));
        }

        return $results;
    }

    /*
     * Converts this object into an array, suitable for emission as JSON.
     */
    public function to_array() {
        return array(
            'title' => $this->title,
            'code' => $this->code,
            'summary' => $this->summary,
            'cbo_url' => $this->cbo_url,
            'pdf_url' => $this->pdf_url,
            'finances' => $this->finances->to_array()
        );
    }
}