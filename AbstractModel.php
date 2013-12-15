<?php
/**
 * Description of AbstractModel
 * 
 * Designed for MySQL 5.6  (should work with earlier versions, as not much version specific syntax is used)
 *
 * @author Daryl Lyn <daryllynpublishing@gmail.com>
 */
abstract class AbstractModel {
    /**
     * Database Connection Settings
     */
    private $dbHost = 'localhost';
    private $dbUser = 'paradigm';
    private $dbPass = 'p@r@d1gm';
    private $dbSchema = 'paradigm';
    private $dbPort = NULL;
    private $dbSocket = NULL;
    public function __construct() {
        if (!isset($this->_table) || !isset($this->_pk))
        {
            $this->errorReport(__LINE__, 'Something went really wrong.  The calling class should not have done this.');
            exit();
        }
        // Initialize DB connection
        $this->sqlQuery();
    }
    public function save() {
        if (!isset($this->loadedDATA))
        {
            $this->errorReport(__LINE__, 'Save attempted without data loaded.  Nothing done.');
            return FALSE;
        }
        $this->sql = '';
        $this->sql .= 'DESCRIBE ';
        $this->sql .= '`' . mysqli_escape_string($this->dbLink, $this->_table) . '`';
        $this->sql .= ';';
        if ( $this->sqlQuery() )
        {
            unset( $this->sql ); // Query worked, so unset it.
            $tableFields = array();
            foreach ( $this->result as $row ) {
                if ( $row['Field'] == $this->_pk )
                {
                    // Dont do anything
                }
                else
                {
                    $tableFields[ $row['Field'] ] = $row['Type'];
                }
            }
            unset($row); // Done with row
            unset( $this->result ); // Done with result
            unset( $this->result_numrows ); // Done with result
        }
        else
        {
            $this->errorReport(__LINE__, 'Describe Query Failed.');
            return FALSE;
        }
        //var_dump($tableFields);
        //exit();
        if ( isset( $this->loadedDATA[$this->_pk] ) )
        {
            // Update existing record
            $this->sql = 'UPDATE ';
            $this->sql .= '`' . mysqli_escape_string($this->dbLink, $this->_table) . '`';
            $this->sql .= ' SET ';
            $first = TRUE;
            foreach ($tableFields as $key => $dataType) {
                if ( isset($this->loadedDATA[$key]) )
                {
                    if ($first)
                    {
                        $first = FALSE;
                    }
                    else
                    {
                        $this->sql .= ',';
                    }
                    $this->sql .= '`' . mysqli_escape_string($this->dbLink, $key) . '`=\'' . mysqli_escape_string($this->dbLink, $this->loadedDATA[$key]) . '\'';
                }
            }
            unset($key);
            unset($dataType);
            unset($first);
            $this->sql .= ' WHERE ';
            $this->sql .= '`' . mysqli_escape_string($this->dbLink, $this->_pk) . '`=' . mysqli_escape_string($this->dbLink, $this->loadedDATA[$this->_pk]);
            $this->sql .= ';';
            if ( $this->sqlQuery() )
            {
                //print $this->sql;
                unset($this->sql);
                if ( $this->result_numrows == 1 )
                {
                    return TRUE;
                }
                else
                {
                    $this->errorReport(__LINE__, 'Query Failed');
                    return FALSE;
                }
            }
            $this->errorReport(__LINE__, 'Query Failed');
            return FALSE;
        }
        else
        {
            // Create new record
            $inClauses = array();
            $this->sql = 'INSERT INTO ';
            $this->sql .= '`' . mysqli_escape_string($this->dbLink, $this->_table) . '`';
            $this->sql .= ' SET ';
            $first = TRUE;
            foreach ($tableFields as $key => $dataType) {
                if ( isset($this->loadedDATA[$key]) )
                {
                    if ($first)
                    {
                        $first = FALSE;
                    }
                    else
                    {
                        $this->sql .= ',';
                    }
                    $snip = '`' . mysqli_escape_string($this->dbLink, $key) . '`=\'' . mysqli_escape_string($this->dbLink, $this->loadedDATA[$key]) . '\'';
                    $inClauses[] = $snip;
                    $this->sql .= $snip;
                    unset($snip);
                }
            }
            unset($key);
            unset($dataType);
            unset($first);
            $this->sql .= ';';
            if ( $this->sqlQuery() )
            {
                unset($this->sql);
                if ( $this->result_numrows == 1 )
                {
                    $this->sql = '';
                    $this->sql .= 'SELECT ';
                    $this->sql .= '`' . mysqli_escape_string($this->dbLink, $this->_pk) . '`';
                    $this->sql .= ' FROM ';
                    $this->sql .= '`' . mysqli_escape_string($this->dbLink, $this->_table) . '`';
                    $this->sql .= ' WHERE ';
                    $first = TRUE;
                    foreach ( $inClauses as $inClause ) {
                        if ($first)
                        {
                            $first = FALSE;
                        }
                        else
                        {
                            $this->sql .= ' AND ';
                        }
                        $this->sql .= $inClause;
                    }
                    $this->sql .= ';';
                    if ( $this->sqlQuery() )
                    {
                        if ($this->result_numrows > 0)
                        {
                            /**
                             * NOTE:  This will work unless multiple clients are inserting rows 
                             *              with the same data at almost the same time.
                             */
                            $this->loadedDATA[$this->_pk] = $this->result[($this->result_numrows - 1)][$this->_pk];
                            return TRUE;
                        }
                        else
                        {
                            $this->errorReport(__LINE__, 'Insert seems to have worked, but failed to retrieve new primary key');
                            return FALSE;
                        }
                    }
                    else
                    {
                        $this->errorReport(__LINE__, 'Insert seems to have worked, but failed to retrieve new primary key');
                        return FALSE;
                    }
                }
                else
                {
                    $this->errorReport(__LINE__, 'Query Failed');
                    return FALSE;
                }
            }
            $this->errorReport(__LINE__, 'Query Failed');
            return FALSE;
        }
    }
    /**
     * The data of the loaded ID
     * @var array
     */
    private $loadedDATA;
    /**
     * Takes an ID number as parameter, and loads the data.
     * 
     * @param int $id
     * @uses $loadedDATA Where the data is stored
     * @return boolean
     */
    public function load($id = NULL) {
        if (!isset($id))
        {
            $this->errorReport(__LINE__, 'ID not set');
            return FALSE;
        }
        $this->sql = '';
        $this->sql .= 'SELECT ';
        $this->sql .= '*';
        $this->sql .= ' FROM ';
        $this->sql .= '`' . mysqli_escape_string($this->dbLink, $this->_table) . '`';
        $this->sql .= ' WHERE ';
        $this->sql .= '`' . mysqli_escape_string($this->dbLink, $this->_pk) . '`=' . (int) $id;
        $this->sql .= ';';
        if ( $this->sqlQuery() )
        {
            unset( $this->sql ); // Query worked, so unset it.
            $retDATA = array();
            foreach ($this->result[0] as $key => $value) {
                $this->loadedDATA[$key] = $value;
            }
            unset( $this->result ); // Done with result
            unset( $this->result_numrows ); // Done with result
            return TRUE;
        }
        $this->errorReport(__LINE__, 'Query Failed');
        return FALSE;
    }
    /**
     * Deletes an entry based on ID or deletes the loaded record.
     * 
     * @param type $id
     * @return boolean Success
     */
    public function delete($id = NULL) {
        if (!isset($id))
        {
            if ( isset($this->loadedDATA[$this->_pk]) && is_numeric($this->loadedDATA[$this->_pk]) )
            {
                $id = $this->loadedDATA[$this->_pk];
            }
            else
            {
                $this->errorReport(__LINE__, 'ID not set');
                return FALSE;
            }
        }
        $this->sql = '';
        $this->sql .= 'DELETE FROM ';
        $this->sql .= '`' . mysqli_escape_string($this->dbLink, $this->_table) . '`';
        $this->sql .= ' WHERE ';
        $this->sql .= '`' . mysqli_escape_string($this->dbLink, $this->_pk) . '`=' . (int) $id;
        $this->sql .= ';';
        if ( $this->sqlQuery() )
        {
            unset($this->sql);
            if ( $this->result_numrows > 0 )
            {
                // Worked!
                return TRUE;
            }
            else
            {
                // Failed to delete anything.
                // TODO: Should this be an error? (or maybe it should return TRUE also)
                return FALSE;
            }
        }
        $this->errorReport(__LINE__, 'Query Failed');
        return FALSE;
    }
    /**
     * Returns the indicated key's value, or the entire dataset if empty.
     * 
     * @param type $key
     * @return string
     */
    public function getData($key = FALSE) {
        if ( $key === FALSE )
        {
            return $this->loadedDATA;
        }
        elseif ( isset ( $this->loadedDATA[$key] ) )
        {
            return $this->loadedDATA[$key];
        }
        else
        {
            return '';
        }
        $this->errorReport(__LINE__, 'Serious Application Error, This should not happen');
        exit();
    }
    /**
     * Sets a value to the indicated field.
     * 
     * @param string $arr
     * @param string $value
     * @return boolean Success
     */
    public function setData($arr = NULL, $value = FALSE) {
        if ( !isset($arr) )
        {
            $this->errorReport(__LINE__, 'Key not provided. Cannot set anything.');
            return FALSE;
        }
        if ( $value === FALSE )
        {
            $value = '';
        }
        if ( is_array($arr) )
        {
            foreach ($arr as $key => $value) {
                $this->loadedDATA[$key] = $value;
            }
            return TRUE;
        }
        else
        {
            $this->loadedDATA[$arr] = $value;
            return TRUE;
        }
        $this->errorReport(__LINE__, 'Serious Application Error.  This should not happen.');
        exit();
    }
    /**
     * The SQL statement to be executed.
     * IMPORTANT: All strings should be escaped before they get to this point!
     * 
     * @var string
     */
    private $sql = NULL;
    /**
     * The results of the previous query.
     * 
     * @var array
     */
    private $result = NULL;
    /**
     * The number of rows in the result set, or the number of affected rows.
     * @var int
     */
    private $result_numrows = 0;
    /**
     * The mode that the query function will operate in.
     * @var 'connect'|'query'
     */
    private $queryMode = 'connect';
    /**
     * The link to the database.
     * 
     * @var object
     */
    private $dbLink = FALSE;
    /**
     * Runs a sql query.
     * 
     * @return boolean Success
     */
    private function sqlQuery() {
        switch ($this->queryMode) {
            case 'connect':
                if (isset($this->dbLink) && is_object($this->dbLink))
                {
                    $this->errorReport(__LINE__, 'Connection attempted with a live DB link.');
                    return FALSE;
                }
                $this->dbLink = mysqli_connect
                (
                    $this->dbHost, 
                    $this->dbUser, 
                    $this->dbPass, 
                    $this->dbSchema, 
                    $this->dbPort, 
                    $this->dbSocket
                );
                if ( mysqli_connect_errno() === 0 )
                {
                    /**
                     * Connection Worked
                     */
                    $this->queryMode = 'query';
                    return TRUE;
                }
                else
                {
                    /**
                     * There was an error
                     */
                    $this->errorReport(__LINE__, 'SQL Connect Error: ' . mysqli_connect_errno() . ' -- ' . mysqli_connect_error());
                    return FALSE;
                }
                break;

            case 'query':
                if ( !isset($this->sql) )
                {
                    $this->errorReport(__LINE__, 'SQL Query not set.');
                }
                unset($this->result);
                unset($this->result_numrows);
                $result = mysqli_query($this->dbLink, $this->sql);
                if ($result === FALSE)
                {
                    $this->errorReport(__LINE__, 'SQL Query Failed: ' . mysqli_errno($this->dbLink) . ' -- ' . mysqli_error($this->dbLink) . ' -- Base64 Encoded Query: ' . base64_encode($this->sql) );
                    return FALSE;
                }
                else
                {
                    $queryType = strtoupper( substr( trim( $this->sql ), 0, 4) ); // Remove whitespace, take first 4 chars ( the length of the shortest option "SHOW" ), convert to uppercase
                    if (
                            $queryType === 'SELE' || // SELECT
                            $queryType === 'SHOW' || //SHOW
                            $queryType === 'DESC' || // DESCRIBE
                            $queryType === 'EXPL' // EXPLAIN
                        )
                    {
                        /**
                         * This query should be returning a result object.
                         */
                        $this->result = array();
                        while ( $row = mysqli_fetch_assoc($result) )
                        {
                            $this->result[] = $row;
                        }
                        $this->result_numrows = count( $this->result );
                        return TRUE;
                    }
                    elseif
                        (
                            $queryType === 'INSE' || // INSERT
                            $queryType === 'UPDA' || // UPDATE
                            $queryType === 'REPL' || // REPLACE
                            $queryType === 'DELE' //DELETE
                        )
                    {
                        /**
                         * This query should have affected rows.
                         */
                        if ( mysqli_affected_rows($this->dbLink) >= 0 )
                        {
                            $this->result_numrows = mysqli_affected_rows($this->dbLink);
                            return TRUE;
                        }
                        else
                        {
                            /**
                             * Error
                             */
                            $this->errorReport(__LINE__, 'Affected rows = -1, Base64 Encoded Query: ' . base64_encode($this->sql) );
                        }
                    }
                    else
                    {
                        $this->errorReport(__LINE__, 'Unsupported SQL query type.');
                        return FALSE;
                    }
                }
                break;

            default:
                $this->errorReport(__LINE__, 'Invalid query mode requested.');
                return FALSE;
                break;
        }
        $this->errorReport(__LINE__, 'Serious application error.  This should not happen.');
        exit();
    }
    /**
     * Reports an error and logs or displays it.
     * 
     * @param int $line
     * @param string $errorMessage
     * @return boolean Success
     */
    protected function errorReport($line = NULL, $errorMessage = NULL) {
        $logText = '';
        $logText .= 'Class:' . __CLASS__ . ' -- Time:' . time() .  ' -- Message: ';
        if (!isset($line))
        {
            $logText .= 'There was an error in calling the error handler.  Look for instances of "errorReport()" in the code.';
        }
        else
        {
            $logText .= 'On Line:  ' . $line . ', ';
        }
        if (!isset($errorMessage))
        {
            $logText .= 'an unspecified error happened.';
        }
        else
        {
            $logText .= $errorMessage;
        }
        /**
         * This would be where the application specific code goes.
         */
        echo $logText . "\n";
        return TRUE;
    }
}

?>
