<?php
require_once 'php/orm_finance.php';

class Bill {
    private $id;
    private $title;
    private $summary;
    private $committee;
    private $published;
    private $code;
    private $cbo_link;
    private $pdf_link;
    private $finances;

    private function __construct($id,
                                 $title,
                                 $summary,
                                 $committee,
                                 $published,
                                 $code,
                                 $cbo_link,
                                 $pdf_link) {
        $this->id = $id;
        $this->title = $title;
        $this->summary = $summary;
        $this->committee = $committee;
        $this->published = $published;
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
            SELECT title, summary, committee, published, code, cbo_url, pdf_url
            FROM Bills
            WHERE id = ?
        ');

        $query->bind_param('i', $id);

        $query->execute();
        $query->bind_result($out_title,
                            $out_summary,
                            $out_committee,
                            $out_published,
                            $out_code,
                            $out_cbo_url,
                            $out_pdf_url);

        // There should only ever be 0 or 1 rows (id is a PK), so there's no
        // danger of throwing away multiple objects here
        $obj = null;
        while ($query->fetch()) {
            $LOGGER->debug(
                'Bill({id}) => ({title}, {summary}, {committee}, {published}, {code}, {cbo}, {pdf})',
                array('id' => $id,
                      'title' => $out_title,
                      'summary' => $out_summary,
                      'committee' => $out_committee,
                      'published' => $out_published,
                      'code' => $out_code,
                      'cbo' => $out_cbo_url,
                      'pdf' => $out_pdf_url));

            $obj = new Bill($id,
                            $out_title,
                            $out_summary,
                            $out_committee,
                            $out_published,
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
     *           'start' => INTEGER)
     */
    public static function from_query($db, $order_param, $order_dir, $url_params, $page_size=25) {
        global $LOGGER;
        $conditions = array();
        $params = array();
        $param_types = '';

        $sql_order_col = null;
        switch ($order_param) {
        case 'date':
            $sql_order_col = 'published';
            break;
        case 'committee':
            $sql_order_col = 'committee';
            break;
        case 'cost':
            $sql_order_col = '(SELECT SUM(amount) FROM Finances WHERE Finances.bill = Bills.id)';
            break;
        }

        // Since these are already 'asc' and 'desc', we can keep these as is
        $sql_order_dir = $order_dir;

        $LOGGER->debug('Order info: {col}, {dir}', array(
            'col' => $sql_order_col,
            'dir' => $sql_order_dir
        ));

        if (isset($url_params['start'])) {
            $offset = $url_params['start'];
        } else {
            $offset = 0;
            $LOGGER->debug('Executing without "start" param');
        }

        // Transfer each of the URL parameters into a form that the DB can 
        // understand, so that they can be part of the query
        foreach ($url_params as $param_name => $param_value) {
            switch ($param_name) {
            case 'before':
                $LOGGER->debug('Condition: before {when}',
                               array('when' => $param_value));

                $before_ref = sqldatetime($param_value, false);
                array_push($conditions, 'published <= ?');
                $params[] =& $before_ref;
                $param_types = $param_types . 's';
                break;

            case 'after':
                $LOGGER->debug('Condition: after {when}',
                                array('when' => $param_value));

                $after_ref = sqldatetime($param_value, false);
                array_push($conditions, 'published >= ?');
                $params[] =& $after_ref;
                $param_types = $param_types . 's';
                break;

            case 'committee':
                $LOGGER->debug('Condition: by the {committee}',
                               array('committee' => $param_value));

                $committee_ref = $param_value;
                array_push($conditions, 'committee = ?');
                $params[] =& $committee_ref;
                $param_types = $param_types . 's';
                break;
            }
        }

        // To avoid having an empty WHERE clause, don't handle conditions if
        // none were provided
        if (count($params) > 0) {
            $full_sql = fmt_string(
                'SELECT id FROM Bills WHERE {conditions}
                ORDER BY {order_col} {order_dir} 
                LIMIT {page_size} OFFSET {offset}',
                array(
                    'conditions' => implode($conditions, ' AND '),
                    'order_col' => $sql_order_col,
                    'order_dir' => $sql_order_dir,
                    'page_size' => $page_size,
                    'offset' => $offset
                )
            );
        } else {
            $full_sql = fmt_string(
                'SELECT id FROM Bills
                 ORDER BY {order_col} {order_dir} 
                 LIMIT {page_size} OFFSET {offset}',
                array(
                    'order_col' => $sql_order_col,
                    'order_dir' => $sql_order_dir,
                    'page_size' => $page_size,
                    'offset' => $offset
                )
            );
        }

        $LOGGER->debug('Executing: {query}', array('query' => $full_sql));
        $LOGGER->debug(
            'Params({param_types}): {params}',
            array('params' => print_r($params, true),
                  'param_types' => $param_types));

        $stmt = $db->prepare($full_sql);

        // mysqli_stmt::bind_param won't accept zero parameters, so we can only
        // do this if parameters were given
        if (count($params) > 0) {
            call_user_func_array(
                array($stmt, 'bind_param'),
                array_merge(array($param_types), $params));
        }

        $ids = array();
        iter_stmt_result($stmt, function($row) use (&$ids, &$results) {
            array_push($ids, (int)$row['id']);
        });

        $results = array();
        foreach ($ids as $id) {
            $LOGGER->debug('Creating Bill({id})', array('id' => $id));
            array_push($results, Bill::from_id($db, $id));
        }

        return $results;
    }

    /*
     * Converts this object into an array, suitable for emission as JSON.
     */
    public function to_array() {
        $finance_array = array();
        foreach ($this->finances as $finance) {
            array_push($finance_array, $finance->to_array());
        }

        return array(
            'title' => $this->title,
            'code' => $this->code,
            'summary' => $this->summary,
            'committee' => $this->committee,
            'published' => $this->published,
            'cbo_url' => $this->cbo_link,
            'pdf_url' => $this->pdf_link,
            'finances' => $finance_array
        );
    }
}
