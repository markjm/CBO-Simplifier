<?php
require_once 'php/orm_finance.php';

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
        global $LOGGER;

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
            $LOGGER->warning('No such Bill with id {id}', array('id' => $id));
            return null;
        }

        // Make sure that all the Finance records is also associated with the
        // Bill
        $query = $db->prepare('SELECT id FROM Finances WHERE bill = ?');
        $query->bind_param('i', $id);

        iter_stmt_result($query, function($row) use (&$db, &$obj) {
            array_push($obj->finances, Finance::from_id($db, $row['id']));
        });

        $LOGGER->debug(
            'Bill[{id}] had {entries} finance entries',
            array('id' => $id, 'entries' => count($obj->finances)));

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
    public static function from_query($db, $url_params, $page_size=25) {
        global $LOGGER;

        if (isset($params['start'])) {
            $conditions = array('id < ?');
            $params = array($params['start']);
            $param_types = 'i';
        } else {
            $LOGGER->debug('Executing without "start" param');

            // Wihout a last load (for example, on a load at the top of
            // the home page), we don't have any base conditions. However,
            // we don't want to create a special case.
            $conditions = array('1 = ?');
            $params = array(1);
            $param_types = 'i';
        }

        foreach ($url_params as $param_name => $param_value) {
            switch ($param_name) {
            case 'before':
                $LOGGER->debug('Condition: before {when}',
                               array('when' => $param_value));

                array_push($conditions, 'published <= ?');
                array_push($params, sqldatetime($param_value));
                $param_types = $param_types . 's';
                break;

            case 'after':
                $LOGGER->debug('Condition: after {when}',
                                array('when' => $param_value));

                array_push($conditions, 'published >= ?');
                array_push($params, sqldatetime($param_value));
                $param_types = $param_types . 's';
                break;

            case 'committee':
                $LOGGER->debug('Condition: by the {committee}',
                               array('committee' => $param_value));

                array_push($conditions, 'committee = ?');
                array_push($params, $param_value);
                $param_types = $param_types . 's';
                break;
            }
        }

        $full_sql = fmt_string(
            'SELECT id FROM Bills WHERE {conditions}
             ORDER BY id DESC LIMIT {page_size}',
            array(
                'conditions' => implode($conditions, ' AND '),
                'page_size' => $page_size
            )
        );

        $LOGGER->debug('Executing: {query}', array('query' => $full_sql));
        $LOGGER->debug(
            'Params({param_types}): {params}',
            array('params' => print_r($params, true),
                  'param_types' => $param_types));

        $stmt = $db->prepare($full_sql);

        call_user_func_array(
            array($stmt, 'bind_param'),
            array_merge(array($param_types), $params));

        $ids = array();
        iter_stmt_result($stmt, function($row) use (&$ids, &$results) {
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
