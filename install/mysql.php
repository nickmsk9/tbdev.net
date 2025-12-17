<?php
/**
 * ���� ���������� ������ {@link CMySQL}.
 *
 * @package person
 * @subpackage sysclasses
 * 
 */

///////////////////////////////////////////////////////
/**
 * ������ ������������ � ��������� ������, ������ ������ �������� -
 * ������������� ������ ���� "����" => "��������".
 * ������������ � ������ {@link CMySQL::fetch_all()}.
 */
define("DB_FETCH_ALL_INDEX_KEYS",  1);

/**
 * ������ ������������ � ��������� ������, ������ ������ �������� -
 * ������������� ������ ���� "����" => "��������". �������� ���� ����������
 * ��������� � ����������� ������ ������� �����.
 * ������������ � ������ {@link CMySQL::fetch_all()}.
 */
define("DB_FETCH_ALL_VALUE_KEYS", 2);

/**
 * ���������� ������ ���� "��������_�������_����" => "��������_�������_����".
 * ������������ � ������ {@link CMySQL::fetch_all()}.
 */
define("DB_FETCH_ALL_VECTOR", 3);

/**
 * ������� ������������ ��� ������ ���� "����" => "��������".
 * ������������ � ������ {@link CMySQL::fetch_row()}.
 */
define("DB_FETCH_ASSOC", MYSQL_ASSOC);

/**
 * ������� ������������ ��� ������ �������� � ��������� �������.
 * ������������ � ������ {@link CMySQL::fetch_row()}.
 */
define("DB_FETCH_NUM", MYSQL_NUM);
///////////////////////////////////////////////////////

/**
 * ����� ��� ������ � ���� MySQL.
 * 
 * ����� �������� ������, ������� �����
 * ����� ������������ ��� ������ � ��:
 * - {@link CMySQL::query()} - ���������� ����� ��������
 * - {@link CMySQL::get_one()} - ������� �������� ������� ������� � ������ ������ ����������
 * - {@link CMySQL::get_first()} - ������� ������ ������ ����������
 * - {@link CMySQL::get_list()},
 *   {@link CMySQL::get_key_list()},
 *   {@link CMySQL::get_vector()},
 *   {@link CMySQL::fetch_all()} - ��������� ���� ����� ���������� ������� � ���� ��������� �������� ������
 * - {@link CMySQL::insert()} - ������� ������ � �������
 * - {@link CMySQL::update()} - ��������� ����� �������
 * - {@link CMySQL::delete()} - �������� ����� �������
 * 
 * �������, ��� ����� ������, ��� ������������� ������������������ ��������.
 * ����������� � ���� �������, ���������� ������ � ��, ���� �������� $params (������
 * ������, ����� ������ �������). �� ����� ���� ��� ������������� �������� (�������� => ��������),
 * ��� � ������ ���������, � ������, ����� � ������� ��������� ������ ���� ��������.
 * ����� ���������� (placeholders) � ������ ������� ����������� � ����� ������ ������ "%".
 * �������� ���������� ������������� ������������ (��. {@link CMySQL::params_quoted()}).
 * ������� �������������:
 * 
 * <code>
 *     ...
 *     $params = array(
 *         "foo1" => "'qwerty'",
 *         "foo2" => 3
 *     );
 *     $db->query("SELECT * FROM foo WHERE foo1 = %foo1% AND foo2 = %foo2%", $params);
 *     // �������������� ������:
 *     // SELECT * FROM foo WHERE foo1 = '\'qwerty\'' AND foo2 = '3'
 *     ...
 *     $foo_id = 12;
 *     $db->query("SELECT field FROM foo WHERE foo_id = %id%", $foo_id);
 *     // �������������� ������:
 *     // SELECT field FROM foo WHERE foo_id = '12'
 *     ...
 * </code>
 *
 * @package person
 * @subpackage sysclasses
 */
class mysql {
// private:
/**#@+
 * @access private
 */
    /**
     * ����� �������
     * @var string
     */
    var $_db_host;
    /**
     * ���� �������
     * @var string
     */
    var $_db_port;
    /**
     * ������������
     * @var string
     */
    var $_db_user;
    /**
     * ������
     * @var string
     */
    var $_db_password;
    /**
     * ����� ��� ������ ��� ������� �����������
     * @var array
     */
    var $_db_names;
    /**
     * ��������� ��� ������ ��� ������� �����������
     * @var string
     */
    var $_charset;
    /**
     * ������ ����������
     * @var resource
     */
     
    var $_cur_db_name;
     
    var $_db_link;

    /**
     * ��� ����� ��� ������� ���� ������
     * @var string
     */
    var $_error_log_file;
    /**
     * ����� �� ��� ������
     * @var bool
     */
    var $_error_log = false;

    var $_db_error = false;
    var $_query_log_verbose = false;
    var $_query_log_file = "";
    var $_query_log = false;
    var $_query_log_only_updates = false;

    var $_param_char = "%";
    var $_db_char = "#";

    var $_field_quote = "`";

    var $_reset_error_handler = true;
    /**
     * ��������� ������ � ��
     */
    var $_last_query = "";
    /**
     * ��������� ������
     */
    var $_last_error = "";
    /**
     * ����, ��������� � ���, ������������ ��
     * ������� � ������������ � ������ ���������� ��� ���.
     */
    var $_params_already_quoted = false;

///////////////////////////////////////////////////////////////////////////////////
    function __connect($host, $user, $passwd, $dbname=null)
    {
        return @mysql_connect($host, $user, $passwd, true);
    }
    ///////////////////////////////////////////////////////////////////////////////
    function __select_db($db_name, $link)
    {
        return @mysql_select_db($db_name, $link);
    }
    ///////////////////////////////////////////////////////////////////////////////
    function __close($link)
    {
        return @mysql_close($link);
    }
    ///////////////////////////////////////////////////////////////////////////////
    function &__query($query, $link = null)
    {
        return @mysql_query($query, $link);
    }
    ///////////////////////////////////////////////////////////////////////////////
    function &__fetch_assoc(&$res)
    {
        return mysqli_fetch_assoc($res);
    }
    ///////////////////////////////////////////////////////////////////////////////
    function &__fetch_num(&$res)
    {
        return mysql_fetch_row($res);
    }
    ///////////////////////////////////////////////////////////////////////////////
    function __error($link = null)
    {
        return @mysql_error($link);
    }
    ///////////////////////////////////////////////////////////////////////////////
    function __free_result(&$res)
    {
        return mysql_free_result($res);
    }
    ///////////////////////////////////////////////////////////////////////////////
    function __escape_string($value)
    {
        return mysql_escape_string($value); 
    }
    ///////////////////////////////////////////////////////////////////////////////
    function __data_seek(&$res, $pos)
    {
        if ($this->__num_rows($res) > 0) {
            return mysql_data_seek($res, $pos);
        } else {
            return true;
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    function __num_rows(&$res)
    {
        return mysqli_num_rows($res);
    }
    ///////////////////////////////////////////////////////////////////////////////
    function __affected_rows()
    {
        return mysql_affected_rows($this->_db_link);
    }
    ///////////////////////////////////////////////////////////////////////////////
    function __log_connect($db_host, $db_user)
    {
        return "-- connect with " . $db_user . " on ". $db_host;
    }
    ///////////////////////////////////////////////////////////////////////////////
    function __log_selecting_db($db_name)
    {
        return "USE $db_name";
    }
    ///////////////////////////////////////////////////////////////////////////////
    function &__table_fields($table_name, $db_name = null)
    {
        $table_name = addslashes($table_name);
        $sql = "SHOW COLUMNS FROM `" . $table_name . "`";
        $result = $this->get_first($sql, null, $db_name);
        return $result;
    }
///////////////////////////////////////////////////////////////////////////////////
    /**
     * ������� ��������� ������
     */
    function _dbErrorHandler($errno, $errstr, $errfile, $errline)
    {
        if (error_reporting() & $errno) {
            $err_text = "<br />\n";
            switch ($errno) {
                case E_USER_ERROR:
                    $err_text .= "<b>FATAL</b> [$errno] $errstr<br />\n";
                break;
                case E_USER_WARNING:
                    $err_text .= "<b>WARNING</b> [$errno] $errstr<br />\n";
                break;
                case E_NOTICE:
                case E_USER_NOTICE:
                    $err_text .= "<b>NOTICE</b> [$errno] $errstr<br />\n";
                break;
                default:
                    $err_text .= "Unkown error type: [$errno] $errstr<br />\n";
                break;
            }
            $err_text .= "<br />\n";
            if (!array_key_exists("SERVER_NAME", $_SERVER)) {
                $err_text = strip_tags($err_text);
            }
            echo $err_text;
            //if ($errno == E_USER_ERROR) {
                exit(1);
            //}
        }
    }
    ///////////////////////////////////////////////////////
    /**
     * ���������� �������� ��� ��.
     * @param string $db_name ���������� ��� 
     */
    function _get_real_db_name($db_name)
    {
        if (@array_key_exists($db_name, $this->_db_names)) {
            $db_name = $this->_db_names[$db_name];
        }
        return $db_name;
    }
    
    ///////////////////////////////////////////////////////
    function _log_query($entry, $type = "normal", $start_run_time = 0, $end_run_time = 0)
    {
        $logging = true;
        if ($this->_query_log_only_updates && !$this->_is_update_query($entry) && $type == "normal") {
            $logging = false;
        }
        if ($logging) {
            $fp = fopen($this->_query_log_file, "at");
            fputs($fp, $entry.";\n");
            if ($this->_query_log_verbose) {
                $bt = debug_backtrace();
                $i = 0;
                while ($bt[$i]["file"] == __FILE__) $i++;
                $file = $bt[$i]["file"];
                $line = $bt[$i]["line"];
                fputs($fp, "-- URI: ".$_SERVER["REQUEST_URI"]."\n");
                fputs($fp, "-- File: $file (line: $line)\n");
                $gen_time = $end_run_time - $start_run_time;
                fputs($fp, "-- Run-time: ".sprintf("%.4f", $gen_time)."\n\n");
            }
            fclose($fp);
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    function _log_connect($db_host, $db_user)
    {
        $this->_log_query($this->__log_connect($db_host, $db_user), "connect");
    }
    ///////////////////////////////////////////////////////////////////////////////
    function _log_selecting_db($db_name)
    {
        $this->_log_query($this->__log_selecting_db($db_name), "db");
    }
    ///////////////////////////////////////////////////////////////////////////////
    function _error($errstr, $errtype = E_USER_WARNING)
    {
        $this->_db_error = true;
        $bt = debug_backtrace();
        $i = 0;
        while ($bt[$i]["function"] == $bt[0]["function"] || $bt[$i]["file"] == __FILE__) {
            $i++;
        }
        $file = $bt[$i]["file"];
        $line = $bt[$i]["line"];
        $errstr = "$errstr<br />\nin file <b>$file</b> on line <b>$line</b><br />\n";
        $errstr .= "<b>Details:</b><br />\nHost: " . $this->_db_host . "<br />\nUser: " . $this->_db_user . "<br />\n";
        if (is_array($this->_cur_db_name)) {
            $errstr .= "DB name: " . $this->_cur_db_name . "<br />\n";
        }
        if ($this->_reset_error_handler) {
            $old_error_handler = set_error_handler(array($this, "_dbErrorHandler"));
        }
        if ($this->_error_log) {
            error_log("\n[".date("Y-m-d H:i:s")."]\n".strip_tags($errstr), 3, $this->_error_log_file);
        }
        if (!array_key_exists("SERVER_NAME", $_SERVER)) {
            $errstr = strip_tags($errstr);
        }
        trigger_error($errstr, $errtype);
        if ($this->_reset_error_handler) {
            restore_error_handler();
        }
    }
    ///////////////////////////////////////////////////////////////////////////////
    function _is_update_query($query)
    {
        $updates = 'INSERT |UPDATE |DELETE |' .
                   'REPLACE |CREATE |DROP |' .
                   'SET |BEGIN|COMMIT|ROLLBACK|START|END' .
                   'ALTER |GRANT |REVOKE |'.'LOCK |UNLOCK ';
        if (preg_match('/^\s*"?('.$updates.')/i', $query)) {
            return true;
        }
        return false;
    }
/**#@-*/

/**#@+
 * @access public
 */
    /**
     * �����������.
     * �� ������� ������ �� ������ (����������� {@link get_instance()}).
     */
    function MySQL($host, $port, $user, $passwd, $db_names, $charset = '')
    {
        $this->_db_host = $host;
        $this->_db_port = $port;
        $this->_db_user = $user;
        $this->_db_password = $passwd;
        $this->_db_names = $db_names;
        $this->_charset = $charset;
    }
 
    /**
     * ���������� ������ �� ������ ������ (���������).
     * ����������� ����� ������.
     *  
     * ���� ������ �� ������, ������� ���.
     * ��� ������� ����������� ����� ��������� ���� ������.
     * ����� ������ ������ ����� �������� ������ ������� ���������� ����������� � ����
     * � ����� �� ��-��������� ��� ������� ����������� (��. {@link config()}).
     * ������ ������������ ������ ������ ������� � �������, �� ��������� ���������� ����������
     * ��� �������� �������:
     * 
     * <code>
     * function some_func()
     * {
     *     $db =& MySQL::get_instance("portal_r");
     * }
     * </code>
     * 
     * ������ ��������� ���� ��� ��� ������ ������ ������, � � ����������, ��������� ����������
     * � ������ � ���� �� ������� � ������ ��� ������������� ���������� ����������.
     * 
     * @static
     * @see config()
     * 
     * @param array $connect_name ��� �����������, ����� ������������ � ������� {@link config()}
     * @return object ������ ������ ������
     */
    static function &get_instance($connect_name = null)
    {
        static $instance;
        if (!isset($connect_name) || empty($connect_name)) {
           $connect_name = "DEFAULT_DB";
        }

        if (!isset($instance[$connect_name])) {

            $connect_data = mysql::config($connect_name, $GLOBALS['_DB_CONFIG']);
            if ($connect_data) {
                $instance[$connect_name] = new MySQL(
                    $connect_data["host"]
                    , isset($connect_data["port"]) && !empty($connect_data["port"])  ? $connect_data["port"] : 3306
                    , $connect_data["user"]
                    , $connect_data["passwd"]
                    , $connect_data["db_names"]
                    , $connect_data["charset"]
                );
                $instance[$connect_name]->connect();
                $db_keys = array_keys($connect_data["db_names"]);
                $instance[$connect_name]->select_db($db_keys[0]);
                $charset = $connect_data["charset"];
                if (!empty($charset))
                	if (!function_exists('mysql_set_charset') || !mysql_set_charset($charset))
						$instance[$connect_name]->query("SET NAMES $charset");
            } else {
                return false;
            }
        }
        return $instance[$connect_name];
    }

    /**
     * ������������� �����.
     * ����������� ����� ������.
     *  
     * ������������� ����� ����� ������ ���������� � ������� ����������.
     * ��� ������� ������ $connect_name ���������� ������ ����������.
     * 
     * @static
     * @param string $connect_name ��� ����������
     * @param array  $connect_data ������ ����
     * <code>
     *     $connect_data = array(
     *         "host"     => ���� �������,
     *         "user"     => ��� ������������ ����,
     *         "passwd"   => ������ ������������,
     *         "db_names" => array(
     *             // ����� ��, ������� ����� ��������������
     *             // � ���� �����������
     *             "main" => "portal_person"
     *         )
     *         "charset"  => ��������� ��� ������
     *     );
     * </code>
     * ������ �� � ������ "db_names" ���������� ��� ������������� �������������
     * ����� ������ {@link get_instnce()}. ���� � ������� "db_names" - ���������� ��� ��,
     * �������� - ���������� ��� ��. �������� (����������) ����� �� ����� ���� ��������� ��� �������������,
     * ����� ������ ��������� ������� � ����������� ��. ������ ���������� ����, ��� ������ ������ ����� ������������
     * ����� ��, ����������� ���� �������.
     * @return array
     */
    static function &config($connect_name, $connect_data = null)
    {
        static $config = array();
        if (!isset($config[$connect_name]) && is_array($connect_data)) {
            $config[$connect_name] = $connect_data;
        }
        return $config[$connect_name];
    }

    /**
     * ������������� ��� ���������� ���������� ���������� ������.
     * 
     * @param bool $enable true - ����������, false - ��������
     */
    function reset_error_handler($enable = true)
    {
        $this->_reset_error_handler = $enable;
    }

    /**
     * ���������� ��������� ��������� ������ � ��.
     * 
     * @return string ������ � ��� ����, � ������� �� ���� �� ������
     */
    function get_last_query()
    {
        return $this->_last_query;
    }

    /**
     * ���������� ��������� ������ ��� ���������� �������.
     * 
     * @return string ��������� �� ������
     */
    function get_last_error()
    {
        return $this->_last_error;
    }

    /**
     * ��������� �������, ����������� � �������� ���������� ������ � �����������
     * ({@link query()}, {@link insert()}, {@link update()} � ��.), ������������ �� �������� ���������� �� �������� ����� ��� ���
     * (��������, � ������ ����������� ����� ������������ magic_quotes_gpc, ����� ���������� ������ ������,
     * ��������� �� $_GET, $_POST, $_COOKIE (GPC)).
     * 
     * �������� ���������� ����� �������������� � ����� ������, �� �������� �� ����, � ����� ��������� ��������� ��������� ���� �����
     * � � ����� ��������� ��������� ������������� magic_quotes_gpc. ������ ����� ���� �������� �������������� �������,
     * ��� ��������� ���������� ������� ��������� �������, ����� �������� "������� ������" (�������� �������������).
     * 
     * � ������, ����� ������ ����� ���������� � ���������� $flag, ������ true, ������������ "������" ������ ����������� �������,
     * ��� ��������� ��� ������������, ��� ������ ���������� ������� ��� ������� �� ��� ���������� ������� stripslashes(),
     * � ����� ����� ���������� �������������. ������ �� "�������" ��������� ������, ��� ��� ����� ���������� ��������,
     * ����� � ������ ������� ���������������� �������� ���������� � ������ ��������� ������ ������
     * (��������� � ���������������� �� ������������, � ���� ����� ������� �� ��������). � ���� �������� ����� ���������� ���� ��������
     * ���������� (�������� �������������� �����), �� ������������� � ����� ������ ����������.  
     * 
     * �� ���������, ��� ������, ����������� ��������� ��������, "������", ��� �������� ���������� �� ������������.
     * 
     * @param bool $flag true - ��������� ��� ������������
     */
    function params_quoted($flag)
    {
        $this->_params_already_quoted = $flag;
    }

    /**
     * ����������� � �������.
     * 
     * @return bool true - ��� ������ �������, false - ������ 
     */
    function connect()
    {
        ///////////////////////////////////////////////////////////////////////////////
        // Logging
        ///////////////////////////////////////////////////////////////////////////////
        if ($this->_query_log && $this->_query_log_file) {
            $this->_log_connect($this->_db_host, $this->_db_user);
        }
        ///////////////////////////////////////////////////////////////////////////////

        if (!$this->_db_link) {
            $link = @mysql_connect($this->_db_host . ":" . $this->_db_port, $this->_db_user, $this->_db_password);
            if ($link) {
                $this->_db_link = $link;
            } else {
                $this->_error("Unable to connect to MySQL server: ".mysql_error(), E_USER_ERROR);
                return false;
            }
        }
        return true; 
    }

    /**
     * �������� ������� ��.
     * 
     * @param string $db_name ���������� ��� ��, �������� ����� � ������� {@link config()}
     * @return string ���������� ��� ���������� �� (���, ������� ���� ������� �� ������ ������)
     */
    function select_db($db_name)
    {
        $real_db_name = $this->_get_real_db_name($db_name);
        
        if ($real_db_name == $this->get_current_db()) {
            return $db_name;
        }

        ///////////////////////////////////////////////////////////////////////////////
        // Logging
        ///////////////////////////////////////////////////////////////////////////////
        if ($this->_query_log && $this->_query_log_file) {
            $this->_log_selecting_db($real_db_name);
        }
        ///////////////////////////////////////////////////////////////////////////////

        $res = @$this->__select_db($real_db_name, $this->_db_link);
        if (!$res) {
            $this->_error("Error on selecting database \"$real_db_name\": " . $this->__error($this->_db_link));
            return false;
        }
        $old_db_name = $this->_cur_db_name;
        $this->_cur_db_name = $real_db_name;
        return $old_db_name ? $old_db_name : true;
    }

    /**
     * �������� ���������� � ��������� ��.
     * @see connect()
     */
    function close()
    {
        $this->__close($this->_db_link);
        $this->_db_link = null;
    }

    /**
     * ���������� ������� � ��.
     * 
     * ��������� ������ � �� �� ������ ������ ������� � ����������� (placeholders) � ����� ����������.
     * (��. {@link params_quoted()}). �������� $params ����� ���� ��� ������������� ������� (�������� => ��������),
     * ��� � ������ ���������, � ������, ����� � ������ ��������� �������� ������ ���� ��������.
     * ����� ���������� (placeholders) � ������ ������� ����������� � ����� ������ ������ "%".
     * ������� �������������:
     * 
     * <code>
     *     ...
     *     $params = array(
     *         "foo1" => "'qwerty'",
     *         "foo2" => 3
     *     );
     *     $db->query("SELECT * FROM foo WHERE foo1 = %foo1% AND foo2 = %foo2%", $params);
     *     // �������������� ������:
     *     // SELECT * FROM foo WHERE foo1 = '\'qwerty\'' AND foo2 = '3'
     *     ...
     *     $foo_id = 12;
     *     $db->query("SELECT field FROM foo WHERE foo_id = %id%", $foo_id);
     *     // �������������� ������:
     *     // SELECT field FROM foo WHERE foo_id = '12'
     *     ...
     * </code>
     * 
     * � ������ ������� ��������� ����� �������� �������������� ������� � ��� ��� ���� ��.
     * ������� �����, ��� ��� �� �������� ��� ���������� ���, ������������� ����� � ������� {@link config()}
     * (�������� ����������� ����� �� ��� "���_��.���_�������" ����� �� �������, �� ������������ ����������
     * ��� �� �������������, ��� ��� ��� ����� ���� �������� � ���� ���� ������). ������� ������� �� ��� �������
     * ����� ����� � � ������� ��������� $db_name. ������� ������� ���� � ���, ��� � ��������� ������ �����
     * � ����� ���������� ������� ���������� ������� {@link select_db()} ��� ���������� ������� ��, � �����
     * ��� �������� � ��, ������� ���� ������� �� ������ ����� ������. ������ ������ �������� ���������, �� � ��������� �������
     * ������� (��������, ����� � ������� ��������� ��������� ������ �� ����� ��).
     * ������:
     * 
     * <code>
     *     ...
     *     $db->add_db_name("main", "db00645");
     *     $db->add_db_name("log", "db00645_log");
     *     $db->select_db("main");
     *     $db->query("SELECT * FROM #main#.foo");
     *     // �������������� ������:
     *     // SELECT * FROM db00645.foo
     *     ...
     *     $db->query("SELECT * FROM #log#.foo");
     *     // �������������� ������:
     *     // SELECT * FROM db00645_log.foo
     *     // ������������ ����� ������:
     *     // $db->query("SELECT * FROM foo", null, null, "log");
     *     ...
     * </code>
     * 
     * ������������ �������� ���������� ��� ������������� �������� ����� ������� ������������� �������� � ���������� �������
     * (��� ����� ����������� � ������ ������� ������ � ��):
     *  - �� ����� �������� �������, � ������� ����������� ��������� ��� � ������ ������� ��� � � ������� �������� ����������;
     *  - ����� ������ �������� ����������;
     *  - �������� ����� ��� ���������������� �� ����� ���� � �������� (�� ������������ ���������� ��� �������)
     *    �������������� ��������� ������ (��������, $_POST � $_GET), �������� ������� ����� ����� ���������
     *    � �������� ���������� ������� (��� ���������� ���� ������ � ������� ����������)
     * 
     * @param string $query   ������ � ����������� (placeholders)
     * @param mixed  $params  ��������� ������� (��. {@link params_quoted()})
     * @param string $db_name ��� �� ��� ������� (��. {@link config()}); ��-��������� - �������
     * 
     * @return resource ��������� �������
     */
    function &query($query, $params = null, $db_name = null)
    {
        ///////////////////////////////////////////////////////////////////////////////
        // Logging connection
        ///////////////////////////////////////////////////////////////////////////////
        if ($this->_query_log && $this->_query_log_file) {
            $this->_log_connect($this->_db_host, $this->_db_user);
        }
        ///////////////////////////////////////////////////////////////////////////////

        $query = $this->get_query($query, $params, $db_name);

        if ($db_name) {
            $this->select_db($db_name);
        }

        $this->_last_query = $query;

        // Mark start time
        if ($this->_query_log && $this->_query_log_verbose) {
            $start_run_time = explode(" ", microtime(1));
            $start_run_time = $start_run_time[0] + $start_run_time[1];
        }

        $result = $this->__query($query, $this->_db_link);

        // Mark end time
        if ($this->_query_log && $this->_query_log_verbose) {
            $end_run_time = explode(" ", microtime(1));
            $end_run_time = $end_run_time[0] + $end_run_time[1];
        }

        if ($result === false) {
            $error = trim($this->__error($this->_db_link));
            $this->_last_error = $error;
            $m = array();
            preg_match("/(?:\'|\")(.*)(?:\'|\")/U", $error, $m);
            if ($m[1]) {
                $error = preg_replace("/(".preg_quote($m[1]).")/U", "<font color='red'><b>\\1</b></font>", str_replace("\n", "", $error));
                $query = preg_replace("/(".preg_quote($m[1]).")/U", "<b>\\1</b>", $query);
            }
            $error_text = "SQL error: $error in query<br />\n";
            $error_text .= "<font color=\"red\">".nl2br($query)."</font>";
            $this->_error($error_text, E_USER_WARNING);
        } else {
            ////////////////////////////////////////////////////
            // Logging
            ////////////////////////////////////////////////////
            if ($this->_query_log && $this->_query_log_file) {
                $this->_log_query($query, "normal", $start_run_time, $end_run_time);
            }
            ////////////////////////////////////////////////////
        }
        
        if ($db_name) {
            $this->select_db($this->_cur_db_name);
        }
        return $result;
    }

    /**
     * ������� ����� ������ � �������.
     * ������:
     * 
     * <code>
     *     ...
     *     $data = array(
     *         "foo1" => "text",
     *         "foo2" => 5,
     *     );
     *     $db->insert("foo", $data);
     *     // �������������� ������:
     *     // INSERT INTO foo (foo1, foo2) VALUES('text', '5')
     *     ...
     * </code>
     * 
     * � ������� ����� ������ ������ ���������� � ������� ������, ��������� � �����, ���� ����� ����� ����� ���������
     * � ������� ����� �������:
     * 
     * <code>
     *     $db->insert("foo", $_POST);
     * </code>
     * 
     * @param string $table   ��� �������
     * @param array  $params  ������������� ������ ("����" => "��������") ������ ��� �������
     *                        (��������� � ���������� ��. {@link query()}, {@link params_quoted()}) 
     * @param string $db_name ��� �� ��� ������� (��. {@link config()}); ��-��������� - �������
     * 
     * @return bool false, ���� ������, true - ��� �������� ����������
     */
    function insert($table, $params, $db_name = null)
    {
        $insert_fields = "";
        $insert_params = "";
        $values = array();
        foreach($params as $key => $value) {
            $insert_fields .= $this->_field_quote . $key.$this->_field_quote . ", ";
            $insert_params .= $this->_param_char  . $key.$this->_param_char  . ", ";
            $values[$key] = $value;
        }
        $insert_fields = substr($insert_fields, 0, strlen($insert_fields) - 2);
        $insert_params = substr($insert_params, 0, strlen($insert_params) - 2);

        $res = $this->query("INSERT INTO $table (\n\t$insert_fields\n) VALUES (\n\t$insert_params\n)", $values, $db_name);
        if ($res) {
            return $this->affected_rows();
        } else {
            $this->_db_error = true;
            return false;
        }
    }

    /**
     * ���������� ����� � �������.
     * 
     * ����� ���������� ������ �� ���������� ��� ���������� � �������� ��� � �� �� ����������.
     * 
     * ������ 1.
     * <code>
     *     ...
     *     $data = array(
     *         "foo1" => "text",
     *         "foo2" => 5,
     *     );
     *     $where = array(
     *         "foo1_id" => 1,
     *         "foo2_id" => 5,
     *     );
     *     // $where - ������
     *     $db->update("foo", $data, $where);
     *     // �������������� ������:
     *     // UPDATE foo SET foo1 = 'text', foo2 = '5' WHERE foo1_id = '1' AND foo2_id => '5'
     *     ...
     * </code>
     *
     * ������ 2. 
     * <code>
     *     ...
     *     $data = array(
     *         "foo1" => "text",
     *         "foo2" => 5,
     *     );
     *     $where_params = array(
     *         "foo1" => "text2",
     *         "foo2" => 1
     *     );
     *     // $where - ������ � ����������� �����������
     *     $db->update("foo", $data, "foo1 = %foo1% OR foo2 = %foo2%", $where_params);
     *     // �������������� ������:
     *     // UPDATE foo SET foo1 = 'text', foo2 = '5' WHERE foo1 = 'text2' OR foo2 = '1' 
     *     ...
     * </code>
     *
     * ������ 3. 
     * <code>
     *     ...
     *     $data = array(
     *         "foo1" => "text",
     *         "foo2" => 5,
     *     );
     *     $where_param = 7;
     *     // $where, ������ � ����� ����������
     *     $db->update("foo", $data, "foo_id = %id%", $where_param);
     *     // �������������� ������:
     *     // UPDATE foo SET foo1 = 'text', foo2 = '5' WHERE foo_id = '7' 
     *     ...
     * </code>
     * 
     * @param string $table        ��� �������
     * @param array  $params       ������������� ������ ("����" => "��������") ������ ����������
     *                             (��������� � ���������� ��. {@link query()}, {@link params_quoted()}) 
     * @param mixed  $where        ������� WHERE ��� UPDATE
     *                             (����� ���� ��� ������������� �������� ��� ������� �� AND, ��� � ������� ������� � �����������,
     *                             �������� ������� ������� �� $where_params)  
     * @param mixed  $where_params ������������� ������ ���������� ��� ���� �������� ��������� ��������� $where;
     *                             ����� ����� ��������� ������, ����� $where - ������ � ����������� 
     * @param string $db_name      ��� �� ��� ������� (��. {@link config()}); ��-��������� - �������
     * 
     * @return bool|int false, ���� ������; ����� ���������� ����� ��� �������� ���������� (�� ������ 0 � false)
     */
    function update($table, $params, $where = null, $where_params = array(), $db_name = null)
    {
        $update_set = "";
        $update_values = array();
        foreach ($params as $key => $value) {
            $update_set .= $this->_field_quote . $key . $this->_field_quote .
                           " = " .
                           $this->_param_char . $key . $this->_param_char .
                           ", ";
            $update_values[$key] = $value;
        }
        $update_set = substr($update_set, 0, strlen($update_set) - 2);

        if (is_array($where)) {
            $where_str = "";
            foreach ($where as $key => $value) {
                $where_str .= $this->_field_quote . $key . $this->_field_quote . " " .
                              ($value ? "=" : "IS") . " " .
                              $this->_param_char . $key . $this->_param_char .
                              " AND ";
            }
            $where_str = substr($where_str, 0, strlen($where_str) - 5);
        } elseif (!is_array($where_params)) {
            $m = array();
            if (preg_match_all("/" . $this->_param_char . "(.+)" . $this->_param_char . "/U", $where_params, $m)) {
                $placeholders = array_unique($m[1]);
                $where_params = array($placeholders[0] => $where_params);
            }
        }

        $part1 = $this->get_query(
            ($where
                ? (is_array($where)
                       ? "\nWHERE \n\t$where_str"
                       : "\nWHERE \n\t$where"
                  )
                : ""
            ),
            is_array($where)
                ? $where
                : $where_params
         );
        $part2 = $this->get_query("UPDATE $table SET \n\t$update_set ", $update_values);
        $res = $this->query($part2 . $part1, null, $db_name);
        if ($res) {
            return $this->affected_rows();
        } else {
            $this->_db_error = true;
            return false;
        }
    }

    /**
     * �������� ����� �������.
     * 
     * @param string $table        ��� �������
     * @param mixed  $where        ������� WHERE ��� DELETE
     *                             (����� ���� ��� ������������� �������� ��� ������� �� AND, ��� � ������� ������� � �����������,
     *                             �������� ������� ������� �� $where_params)  
     * @param mixed  $where_params ������������� ������ ���������� ��� ���� �������� ��������� ��������� $where;
     *                             ����� ����� ��������� ������, ����� $where - ������ � ����������� 
     * @param string $db_name      ��� �� ��� ������� (��. {@link config()}); ��-��������� - �������
     * 
     * @return bool|int false, ���� ������; ����� ��������� ����� ��� �������� ���������� (�� ������ 0 � false)
     */
    function delete($table, $where = "", $where_params = array(), $db_name = null)
    {
        if (is_array($where)) {
            $where_str = "";
            foreach($where as $key => $value) {
                $where_str .= $this->_field_quote . $key . $this->_field_quote . " ".
                              ($value ? "=" : "IS") . " " .
                              $this->_param_char . $key . $this->_param_char .
                              " AND ";
            }
            $where_str = substr($where_str, 0, strlen($where_str) - 5);
        } elseif (!is_array($where_params)) {
            $m = array();
            if (preg_match_all("/" . $this->_param_char . "(.+)" . $this->_param_char . "/U", $where_params, $m)) {
                $placeholders = array_unique($m[1]);
                $where_params = array($placeholders[0] => $where_params);
            }
        }

        $res = $this->query(
            "DELETE FROM $table " .
            ($where
                ? (is_array($where)
                       ? "\nWHERE \n\t$where_str"
                       : "\nWHERE \n\t$where"
                  )
                : ""
            ),
            is_array($where)
                ? $where
                : $where_params
            , $db_name
        );
        if ($res) {
            return $this->affected_rows();
        } else {
            $this->_db_error = true;
            return false;
        }
    }

    /**
     * ���������� ������ ������ ������� ��� ������������� ������.
     * 
     * ������.
     * 
     * <code>
     *     // ������� foo:
     *     // +-------------+-----------+
     *     // | id |  nick  | full_name |
     *     // +-------------+-----------+
     *     // | 10 | john   | John Doe  |
     *     // | 11 | cat    | Katrina   |
     *     // +-------------+-----------+
     *     
     *     $data = $db->get_first("SELECT id, nick, full_name FROM foo WHERE id = 10");
     *     
     *     // $data = array(
     *     //     "id"        => 10,    
     *     //     "nick"      => "john",    
     *     //     "full_name" => "John Doe"
     *     // )
     *     ...
     * </code>
     *
     * @see query()
     * 
     * @param string $query   ������ � ����������� (placeholders)
     * @param array  $params  ��������� ������� (��. {@link query()}, {@link params_quoted()})
     * @param string $db_name ��� �� ��� ������� (��. {@link config()}); ��-��������� - �������
     * 
     * @return array ������ ������� � ���� �������������� ������� ("�������" => "��������")
     */
    function &get_first($query, $params = null, $db_name = null)
    {
        $res =& $this->query($query, $params, $db_name);
        if (!$res) {
            $this->_db_error = true;
        } else {
            $row = $this->__fetch_assoc($res);
            $this->__free_result($res);
        }
        return $row;
    }

    /**
     * ��������/��������� ������� ����� ������ ���������� ��������.
     * 
     * @param bool   $enable   ��������/��������� ������� ����� ������ (true/false)
     * @param string $log_file ��� ����� ���� (����� ��� ������ ������, ������ ��������� �� �����������) 
     */
    function enable_error_log($enable, $log_file = null)
    {
        $this->_error_log_file = $log_file;
        $this->_error_log = $enable;
    }
    
    /**
     * ���������� ���� ��� ������� ����� ��������.
     * 
     * @param string $log_file ��� ����� ���� 
     */
    function set_query_log_file($log_file)
    {
        $this->_query_log_file = $log_file;
    }
    
    /**
     * �������� ������� ����� ��������.
     * 
     * @param bool $only_updates true - ����� ��� ������ ��������� ��
     * @param bool $verbose      true - �������� � ��� �������������� ����������
     *                           (��� �������, �� �������� ��� ������ ������, ����� ������ � ����� ������� � �.�.)
     */
    function enable_query_log($only_updates = false, $verbose = false)
    {
        $this->_query_log = true;
        $this->_query_log_verbose = $verbose;
        $this->_query_log_only_updates = $only_updates;
    }
    
    /**
     * ��������� ������� ����� ��������.
     */
    function disable_query_log()
    {
        $this->_query_log = false;
        $this->_query_log_verbose = false;
    }
    
    /**
     * ������������� ������� ������ ������ ������.
     * 
     * ������ ������������ ��� ���������� ������ �������� ��� ������ ������ �� ����������.
     * ����� �� ��������� ������ ������ �� ���������� ����������, ����� ���������� � ������ ������
     * ������� ������ ������ ������, � ����� � ����� ������ ��������� ������� {@link error_occured()},
     * ��������� �� ������ ��� ���������� ������-������ �� �������� ������ ��� ���.
     * ������:
     * 
     * <code>
     *     $db->catch_error();
     * 
     *     $db->update("foo", $data);
     *     $db->insert("foo2", $data2);
     *     $db->select("SELECT * FROM foo");
     * 
     *     if ($db->error_occured()) {
     *         echo "������!";
     *     }
     * </code> 
     * 
     * @see error_occured()
     */
    function catch_error()
    {
        $this->_db_error = false;
    }

    /**
     * ���������� true, ���� ��������� ������ � ������ �������� (����� ������ {@link catch_error()}).
     * 
     * @see catch_error()
     */
    function error_occured()
    {
        return $this->_db_error;
    }
    
    /**
     * �� ��, ��� � {@link fetch_all() fetch_all($res DB_FETCH_ALL_VECTOR)}.
     * ������� � ���, ��� ������ ���������� ����� ��������� ��� ������ (��������� �������),
     * ��� � ������ ������ �������.
     * 
     * @param string|resource $query   ������ � ����������� (placeholders) ��� ��������� �������
     * @param array           $params  ��������� ������� (��. {@link query()}, {@link params_quoted()})
     * @param string          $db_name ��� �� ��� ������� (��. {@link config()}); ��-��������� - �������
     */
    function &get_vector($query, $params = null, $db_name = null)
    {
        if (is_resource($query)) {
            $res =& $query;
        } else {
            $res =& $this->query($query, $params, $db_name);
        }
        if (!$res) {
            $this->_db_error = true;
            return false;
        }
        $ret =& $this->fetch_all($res, DB_FETCH_ALL_VECTOR);
        if (!is_resource($query)) {
            $this->__free_result($res);
        }
        return $ret;
    }

    /**
     * �� ��, ��� � {@link fetch_all() fetch_all($res DB_FETCH_ALL_INDEX_KEYS)}.
     * ������� � ���, ��� ������ ���������� ����� ��������� ��� ������ (��������� �������),
     * ��� � ������ ������ �������.
     * 
     * @param string|resource $query   ������ � ����������� (placeholders) ��� ��������� �������
     * @param array           $params  ��������� ������� (��. {@link query()}, {@link params_quoted()})
     * @param string          $db_name ��� �� ��� ������� (��. {@link config()}); ��-��������� - �������
     */
    function &get_list($query, $params = null, $db_name = null)
    {
        if (is_resource($query)) {
            $res =& $query;
        } else {
            $res =& $this->query($query, $params, $db_name);
        }
        if (!$res) {
            $this->_db_error = true;
            return false;
        }
        $ret =& $this->fetch_all($res, DB_FETCH_ALL_INDEX_KEYS);
        if (!is_resource($query)) {
            $this->__free_result($res);
        }
        return $ret;
    }

    /**
     * �� ��, ��� � {@link fetch_all() fetch_all($res DB_FETCH_ALL_VALUE_KEYS)}.
     * ������� � ���, ��� ������ ���������� ����� ��������� ��� ������ (��������� �������),
     * ��� � ������ ������ �������.
     * 
     * @param string|resource $query     ������ � ����������� (placeholders) ��� ��������� �������
     * @param array           $params    ��������� ������� (��. {@link query()}, {@link params_quoted()})
     * @param string          $key_field ����, �������� �������� ��������� � ����������� ������ �������;
     *     ���� �� ������, �� ������� �������� ������� ����
     * @param string          $db_name   ��� �� ��� ������� (��. {@link config()}); ��-��������� - �������
     */
    function &get_key_list($query, $params = null, $key_field = null, $db_name = null)
    {
        if (is_resource($query)) {
            $res =& $query;
        } else {
            $res =& $this->query($query, $params, $db_name);
        }
        if (!$res) {
            $this->_db_error = true;
            return false;
        }
        $ret =& $this->fetch_all($res, DB_FETCH_ALL_VALUE_KEYS, $key_field);
        if (!is_resource($query)) {
            $this->__free_result($res);
        }
        return $ret;
    }

    /**
     * ����������� ��� ������ �� ���������� SELECT ������� � ������.
     * 
     * ���� ��������� ����� ���������� ����� � ������.
     * 
     * 1. ���������: ������, ������ ������ �������� - ������������� ������ ���� "����" => "��������".
     *
     * <code>
     *     // ������� foo:
     *     // +-------------+-----------+
     *     // | id |  nick  | full_name |
     *     // +-------------+-----------+
     *     // | 10 | john   | John Doe  |
     *     // | 11 | cat    | Katrina   |
     *     // +-------------+-----------+
     * 
     *     $res = $db->query("SELECT id, nick, full_name FROM foo");
     *     $data = $db->fetch_all($res, DB_FETCH_ALL_INDEX_KEYS);
     * 
     *     // $data = array(
     *     //     [0] => array(
     *     //         "id"        => 10,    
     *     //         "nick"      => "john",    
     *     //         "full_name" => "John Doe"
     *     //     ),
     *     //     [1] => array(
     *     //         "id"        => 11,    
     *     //         "nick"      => "cat",    
     *     //         "full_name" => "Katrina"
     *     //     )
     *     // )
     *     ...
     * </code>
     *  
     * 2. ���������: ������, ������ ������ �������� - ������������� ������ ���� "����" => "��������".
     *    �������� ���� ����������, ��� �������� ������� � $key_field, ��������� � ����������� ������ ������� �����
     *    (� ����� ������ �������� ��������� ���� ��� �� ������������). ���� $key_field �� ������, �
     *    �������� ������ ������� ��������� �������� ������� ������� ����������.
     *
     * <code>
     *     $res = $db->query("SELECT id, nick, full_name FROM foo");
     *     $data = $db->fetch_all($res, DB_FETCH_ALL_VALUE_KEYS);
     * 
     *     // $data = array(
     *     //     [10] => array(
     *     //         "nick"      => "john",    
     *     //         "full_name" => "John Doe"
     *     //     ),
     *     //     [11] => array(
     *     //         "nick"      => "cat",    
     *     //         "full_name" => "Katrina"
     *     //     )
     *     // ) 
     *     ...
     * </code>
     *  
     * 3. ���������: ���������� ������ ���� "�������� 1-�� ����" => "�������� 2-�� ����".
     *    ���� ��������� �������� ������ ���� �������, ����� �������� �������������.
     *
     * <code>
     *     $res = $db->query("SELECT id, nick FROM foo");
     *     $data = $db->fetch_all($res, DB_FETCH_ALL_VECTOR);
     *     // array(
     *     //     [10] => "john",
     *     //     [11] => "cat"
     *     // )
     * 
     *     // ����� ������ ���� ����
     *     $res = $db->query("SELECT nick FROM foo");
     *     $data = $db->fetch_all($res, DB_FETCH_ALL_VECTOR);
     *     // array(
     *     //     [0] => "john",
     *     //     [1] => "cat"
     *     // )
     *     ...
     * </code>
     * 
     * @param resource $res         ��������� ������� (������������ ������� {@link query()}).
     * @param int      $result_type ��� ����������� � ���������� �������<br />
     *     ��������� �������� $result_type:<br />
     *     DB_FETCH_ALL_INDEX_KEYS   - ��������� ������, ������ ������ �������� -
     *        ������������� ������ ���� "����" => "��������";<br />
     *     DB_FETCH_ALL_VALUE_KEYS - ����� ��, ��� DB_FETCH_ALL_INDEX_KEYS,
     *        �� �������� ���� ����������, ���������� � $column, ��������� � ����������� ������ ������� �����;<br />
     *     DB_FETCH_ALL_VECTOR     - ���������� ������ ���� "��������_�������_����" => "��������_�������_����".
     * @param string $key_field     ����, �������� �������� ��������� � ����������� ������, �����
     *     �������� $result_type ��������� �������� DB_FETCH_ALL_VALUE_KEYS; ���� �� ������, �� �������
     *     �������� ������� ������� ����������.
     * 
     * @return array ��������� � ���� �������
     */
    function &fetch_all(&$res, $result_type = DB_FETCH_ALL_INDEX_KEYS, $key_field = null)
    {
        if ($res) {
            // ���������� ��������� � ���������� �� ������
            $this->__data_seek($res, 0);    
            if (is_int($result_type)) {
                $data = array();
                $i = 0;
                while ($row = $this->__fetch_assoc($res)) {
                    if ($result_type == DB_FETCH_ALL_INDEX_KEYS) {
                        $data[$i] = $row;
                    }
                    if ($result_type == DB_FETCH_ALL_VALUE_KEYS) {
                        if (!$key_field) {
                            $keys = array_keys($row);
                            $key_field = $keys[0];
                        }
                        $data[$row[$key_field]] = $row;
                        unset($data[$row[$key_field]][$key_field]);
                    }
                    if ($result_type == DB_FETCH_ALL_VECTOR) {
                        $keys = array_keys($row);
                        if ($keys[1]) {
                            $data[$row[$keys[0]]] = $row[$keys[1]];
                        } else {
                            $data[] = $row[$keys[0]];
                        }
                    }
                    $i++;
                }
                return $data;
            } else {
                $this->_error("Unknown result type: ".$result_type);
                return false;
            }
        } else {
            $this->_error("First argument in fetch_all() is not a valid query result!");
            return false;
        }
    }
    
    /**
     * ���������� ��������������� ������ ��� ��� ����������.
     * 
     * @param string $query  ������ � ����������� (placeholders)
     * @param array  $params ��������� ������� (��. {@link query()}, {@link params_quoted()})
     * 
     * @return string �������������� ������
     */
    function get_query($query, $params)
    {

        $m = array();
        if (preg_match_all("/" . $this->_param_char . "(.+)" . $this->_param_char . "/U", $query, $m)) {
            $placeholders = array_unique($m[1]);
            if(!is_array($params)) {
                if(strpos($params, "%") === false) {
                    $params = array($placeholders[0] => $params);
                } else {
                    $params = array();
                }
            }
            foreach($placeholders as $value) {
                if(isset($params[$value])) {
                    $key = $value;
                    $value = $params[$key];
                    if ($this->_params_already_quoted) {
                        $value = stripslashes($value);
                    }
                    $value = $this->__escape_string($value."");
                    $value = str_replace("-", "\-", $value);
                    $value  = "'".$value."'";
                    $query = str_replace($this->_param_char . $key . $this->_param_char, $value, $query);
                }
            }
        }

        if (is_array($this->_db_names)) {
            foreach ($this->_db_names as $key => $value) {
                $query = str_replace($this->_db_char . $key . $this->_db_char, $value, $query);
            }
        }
        return $query;
    }
    
    /**
     * ������������ ������, ���������� ��� ��������� �������.
     * 
     * @param resource $res ������ �� ��������� �������
     */
    function free_result(&$res)
    {
        if ($res) {
            $this->__free_result($res);
        } else {
            $this->_error("First argument in free_result() is not a valid query result!");
            return false;
        }
    }
    
    /**
     * ���������� ��������� ������ ���������� ������� ��� ���������� ������.
     * 
     * @param resource $res ��������� �������.
     * @param int $fetch_mode ��� ����������� � ���������� �������;<br />
     *     DB_FETCH_ASSOC - ������������� ������,<br />
     *     DB_FETCH_NUM   - ������ � ��������� �������.
     */
    function &fetch_row(&$res, $fetch_mode = DB_FETCH_ASSOC)
    {
        if ($res) {
            switch ($fetch_mode) {
                case DB_FETCH_ASSOC:
                    $row = $this->__fetch_assoc($res);
                    break;
                case DB_FETCH_NUM:
                    $row = $this->__fetch_num($res);
                    break;
            }
            return $row;
        } else {
            $this->_error("First argument in fetch_row() is not a valid query result!");
            return false;
        }
    }
    
    /**
     * ���������� �������� ������� ������� ������ ������ �������.
     *
     * ������.
     * 
     * <code>
     *     // ������� foo:
     *     // +-------------+-----------+
     *     // | id |  nick  | full_name |
     *     // +-------------+-----------+
     *     // | 10 | john   | John Doe  |
     *     // | 11 | cat    | Katrina   |
     *     // +-------------+-----------+
     *
     *     $num = $db->get_one("SELECT COUNT(*) FROM foo");
     *     // $num = 2;
     *     ...
     * </code>
     *
     * @see query()
     * 
     * @param string|resource $query   ������ � ����������� (placeholders) ��� ��������� �������
     * @param array           $params  ��������� ������� (��. {@link query()}, {@link params_quoted()})
     * @param string          $db_name ��� �� ��� ������� (��. {@link config()}); ��-��������� - �������
     */
    function get_one($query, $params = null, $db_name = null)
    {
        if (is_resource($query)) {
            $res =& $query;
        } else {
            $res =& $this->query($query, $params, $db_name);
        }
        if (!$res) {
            $this->_db_error = true;
            return false;
        } else {
            $row = $this->fetch_row($res, DB_FETCH_NUM);
            if (!is_resource($query)) {
                $this->__free_result($res);
            }
            return $row[0];
        }
    }

    /**
     * ������������� ���������� ��������� ���������� ������� �� ������ ������.
     * 
     * @param resource $res ������ �� ��������� �������
     */
    function result_reset(&$res)
    {
        if ($res) {
            $this->__data_seek($res, 0);    
        } else {
            $this->_error("First argument in result_reset() is not a valid query result!");
            return false;
        }
    }
    
    /**
     * ���������� ���������� ����� ���������� �������.
     * 
     * @param resource $res �� ��������� �������
     * @return int ���������� ����� ����������
     */
    function num_rows(&$res)
    {
        return $this->__num_rows($res);
    }
    
    /**
     * ���������� ���������� ���������� ������� ���������� ������� UPDATE/DELETE.
     * 
     * @return int ���������� ����� ����������
     */
    function affected_rows()
    {
        return $this->__affected_rows();
    }

    /**
     * ���������� ���������� ����������.
     * 
     * ����� ���������� � ��� ������, ����� ����� �������� ���������� ��������������� � ������ ������� (�� �������� � �������������
     * ����������). $var ����� ���� ��������. � ���� ������ ������������ ��� ���������� �������.
     * ������:
     * 
     * <code>
     *     $var = "qwer'ty";
     *     escape_var($var);
     *     $db->query("SELECT * FROM foo WHERE foo = '$var'");
     *     // SELECT * FROM foo WHERE foo = 'qwer\'ty'; 
     *     ...
     * </code>
     * 
     * ������ �� ������������� ������������ ����� ������ � ���������� ��������.
     * ����������� ����������������� ������� (��. {@link query()}).
     * 
     * @see params_quoted()
     * 
     * @param  mixed $var ������ �� ����������, �������� ������� ����� ������������
     * @return mixed �������������� ����������
     */
    function &escape_var(&$var)
    {
        if (is_array($var)) {
            foreach ($var as $key => $value) {
                $this->escape_var($var[$key]);
            }
        } else {
            if ($var) {
                $var = str_replace("�", "�", $var);
                $var = str_replace("�", "�", $var);
            }
            $var = $this->__escape_string($var);
            $var = str_replace("-", "\-", $var);
        }
        return $var;
    }
    
    /**
     * ���������� ������ ����� �������.
     * 
     * @param string $table_name ��� �������
     * @param string $db_name    ��� �� ���, ��������� ������� (��. {@link config()})
     */
    function &table_fields($table_name, $db_name = null)
    {
        return $this->__table_fields($table_name, $db_name = null);
    }
    
    /**
     * ���������� ���������� ��� ������� ��.
     */
    function get_current_db()
    {
        return $this->_cur_db_name;
    }
    
    /**
     * ���������� ���������� � ����������.
     * 
     * ���������� ������ ����:
     *     array(
     *         "host"     => ���� ������� ��,
     *         "port"     => ���� ������� ��,
     *         "login"    => ����� ������������ ��,
     *         "passwd"   => ������ ������������ ��,
     *         "link"     => ���� ����������� � ������� 
     *         "db_names" => ������ ���� �� �����������
     *     )
     * 
     * @return array ���������� � ����������
     */
    function &get_connection()
    {
        $ret = array(
            "host"     => $this->_db_host,
            "port"     => $this->_db_port,
            "login"    => $this->_db_user,
            "passwd"   => $this->_db_password,
            "link"     => $this->_db_link,
            "db_names" => $this->_db_names,
            "charset"  => $this->_charset
        );
        return $ret; 
    }
    
    /**
     * ���������� �������� ����������������� ���� ������� ����� ��������� �������.
     * 
     * @return int ������������� ����������� ������
     */
    function insert_id()
    {
        //$last_id = $this->get_one("SELECT LAST_INSERT_ID()");
        $last_id = mysql_insert_id($this->_db_link); 
        return $last_id;
    }
/**#@-*/




    /**
     * 
     * ������ ����� � ������� ����
     * 
     */
    var $file;

    /*function sdGetSqlLine() {
        $res = "";
        $fl = true;
        while (!feof($this->file) && $fl) {
            $str = ltrim(fgets($this->file, 4096));
    		if (!empty($str) && !preg_match("/^(#|--)/", $str)) {
                //$fl = (strpos($str, ";") === false);
                $fl = preg_match("/;$/six", $str);
                $res .= str_replace("\r\n", " ", $str);
            }
        }
        return $res;
    }*/

    function sdImportFromFile($fname) {
        $this->file = @file($fname);
        if (!$this->file) {
            $this->_error("Can't read mysql dump: <b>".$fname."</b>");
        }
        $total = 0;
        $query = "";
        foreach ($this->file as $line) {
            /*$sql = $this->sdGetSqlLine();
            if ($sql) {
                $this->query($sql);
                $total++;
            }*/
			if (preg_match("/^\s?#/", $line) || !preg_match("/[^\s]/", $line))
				continue;
			else {
				$query .= $line;
				if (preg_match("/;\s?$/", $query)) {
					$this->query($query);
					$total++;
					$query = '';
				}
			}
        }
        //fclose($this->file);
        return $total;
    }

};
?>