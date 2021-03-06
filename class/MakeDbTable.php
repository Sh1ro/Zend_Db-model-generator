<?php

/**
 * main class for files creation
 */
class MakeDbTable
{

    /**
     *  @var String $_tbname;
     */
    protected $_tbname;
    /**
     *
     *  @var String $_dbname;
     */
    protected $_dbname;
    /**
     *  @var PDO $_pdo;
     */
    protected $_pdo;
    /**
     *   @var Array $_columns;
     */
    protected $_columns;
    /**
     * @var String $_className;
     */
    protected $_className;
    /**
     *   @var String $_primaryKey;
     */
    protected $_primaryKey;
    /**
     *   @var String $_namespace;
     */
    protected $_namespace;
    /**
     *  @var Array $_config;
     */
    protected $_config;
    /**
     *   @var Boolean $_addRequire;
     */
    protected $_addRequire;
    /**
     *   @var String $_author;
     */
    protected $_author;
    /**
     *   @var String $_license;
     */
    protected $_license;
    /**
     *   @var String $_copyright;
     */
    protected $_copyright;
    /**
     *
     * @var String $_location;
     */
    protected $_location;
    /**
     *
     * @var Array $foreignKeysInfo
     */
    protected $_foreignKeysInfo;
        /**
     *  @var boolean $_useInitCaps;
     */
    protected $_useInitCaps = true;
    /**
     *
     * @var array override formatting rules with specific words
     */
    protected $_overrideWords = array();
    
    /**
     *
     *  the class constructor
     *
     * @param Array $config
     * @param String $dbname
     * @param String $namespace
     */
    function __construct($config, $dbname, $namespace)
    {

        $columns = array();
        $primaryKey = array();
        $this->_config = $config;

        $pdo = new PDO(
                        "{$this->_config['db.type']}:host={$this->_config['db.host']};dbname=$dbname",
                        $this->_config['db.user'],
                        $this->_config['db.password']
        );

        $this->_pdo = $pdo;
        //$this->_tbname=$tbname;
        $this->_namespace = $namespace;

        //docs section
        $this->_author = $this->_config['docs.author'];
        $this->_license = $this->_config['docs.license'];
        $this->_copyright = $this->_config['docs.copyright'];
        // other config
        $this->_addRequire = $config['include.addrequire'];
        $this->_useInitCaps = $config['formatting.use_initcap_vars'];
        $this->_overrideWords = $config['formatting.override_words'];
    }

    /**
     *
     * @param array $info
     */
    public function setForeignKeysInfo($info)
    {
        $this->_foreignKeysInfo = $info;
    }

    /**
     *
     * @return array
     */
    public function getForeignKeysInfo()
    {
        return $this->_foreignKeysInfo;
    }

    /**
     *
     * @param string $location
     */
    public function setLocation($location)
    {
        $this->_location = $location;
    }

    /**
     *
     * @return string
     */
    public function getLocation()
    {
        return $this->_location;
    }

    /**
     *
     * @param string $table
     */
    public function setTableName($table)
    {
        $this->_tbname = $table;
        $this->_className = $this->_getCapital($table, 'class');
    }

    /**
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->_tbname;
    }

    /**
     *
     *  removes underscores and capital the letter that was after the underscore
     *  example: 'ab_cd_ef' to 'AbCdEf'
     *
     *  variable names can be set to start lowercased with the config parameter
     *  formatting.use_init_caps = false, and these strings can be totally
     *  overridden with formatting.override_words
     *
     * @param String $str
     * @param string $type optional; when set to anything
     * besides 'var', will always ucfirst() the string if not in overrideWords
     * @return String
     */
    private function _getCapital($str, $type='var')
    {
        // short circuit overrideWords;
        // variables are returned as in override, functions are InitCapped
        if(array_key_exists($str, $this->_overrideWords)) {
            if($type == 'function' || $type == 'class') {
                return ucfirst($this->_overrideWords[$str]);
            } else {
                return $this->_overrideWords[$str];
            }
        }
        // TODO: match overrideWords elements and replace via regex; unify formatting rules
        
        $temp = '';
        $parts = explode('_', $str);

        foreach ($parts as $key => $part) {
            // if not using InitCap variablenames, don't cap the first part
            if ($type == 'var' && $key == 0 && !$this->_useInitCaps) {
                $temp.=$part;
            } else {
                $temp.=ucfirst($part);
            }
        }
        return $temp;
    }

    public function getTablesNamesFromDb()
    {

        $res = $this->_pdo->query('show tables')->fetchAll();
        $tables = array();
        foreach ($res as $table)
            $tables[] = $table[0];

        return $tables;
    }

    /**
     * converts MySQL data types to PHP data types
     *
     * @param string $str
     * @return string
     */
    private function _convertMysqlTypeToPhp($str)
    {
        preg_match('#^(?:tiny|small|medium|long|big|var|float)?(\w+)(?:\(\d+(?:,\d+)?\))?(?:\s\w+)*$#', $str, $matches);
        $res = str_ireplace(array('timestamp', 'blob', 'char'), 'string', $matches[1]);
        return $res;
    }

    public function parseTable()
    {
        $this->parseForeignKeys();
        $this->parseDescribeTable();
    }

    public function parseForeignKeys()
    {
        $result = '';
        $tbname = $this->getTableName();
        $this->_pdo->query("SET NAMES UTF8");
        $qry = $this->_pdo->query("show create table $tbname");

        if (!$qry)
            throw new Exception("`show create table $tbname` returned false!.");

        $res = $qry->fetchAll();
        if (!isset($res[0]['Create Table']))
            throw new Exception("`show create table $tbname` did not provide known output");

        $query = $res[0]['Create Table'];
        $lines = explode("\n", $query);
        $tblinfo = array();
        $keys = array();
        foreach ($lines as $line) {
            preg_match('/^\s*CONSTRAINT `(\w+)` FOREIGN KEY \(`(\w+)`\) REFERENCES `(\w+)` \(`(\w+)`\)/', $line, $tblinfo);
            if (sizeof($tblinfo) > 0) {
                $keys[] = array(
                    'key_name' => $tblinfo[1],
                    'column_name' => $tblinfo[2],
                    'foreign_tbl_name' => $tblinfo[3],
                    'foreign_tbl_column_name' => $tblinfo[4]
                );
            }
        }


        $this->setForeignKeysInfo($keys);
    }

    public function parseDescribeTable()
    {

        $tbname = $this->getTableName();
        $this->_pdo->query("SET NAMES UTF8");

        $qry = $this->_pdo->query("describe $tbname");

        if (!$qry)
            throw new Exception("`describe $tbname` returned false!.");

        $res = $qry->fetchAll();
        $primaryKey = array();

        foreach ($res as $row) {
            $rowArray = array(
                'field' => $row['Field'],
                'type' => $row['Type'],
                'phptype' => $this->_convertMysqlTypeToPhp($row['Type']),
                'capital' => $this->_getCapital($row['Field']),
                'variableName' => $this->_getCapital($row['Field']),
                'functionName' => $this->_getCapital($row['Field'], 'function')
                );
            
            if($row['Key'] == 'PRI') {
                $primaryKey[] = $rowArray;  
            }
            $columns[] = $rowArray;

        }

        if (sizeof($primaryKey) == 0) {
            throw new Exception("didn't find primary keys in table $tbname.");
        } else if (sizeof($primaryKey) > 1) {
            throw new Exception("found more then one primary key! probably a bug: " . join(", ", $primaryKey));
        }
        $this->_primaryKey = $primaryKey[0];
        $this->_columns = $columns;
    }

    /**
     *
     * parse a tpl file and return the result
     *
     * @param String $tplFile
     * @return String
     */
    public function getParsedTplContents($tplFile, $referenceMap='')
    {
        ob_start();
        require('templates' . DIRECTORY_SEPARATOR . $tplFile);
        $data = ob_get_contents();
        ob_end_clean();
        return $data;
    }

    /**
     * creats the DbTable class file
     */
    function makeDbTableFile()
    {
        $referenceMap = '';
        $dbTableFile = $this->getLocation() . DIRECTORY_SEPARATOR . 'DbTable' . DIRECTORY_SEPARATOR . $this->_className . '.php';

        $foreignKeysInfo = $this->getForeignKeysInfo();
        $references = array();
        foreach ($foreignKeysInfo as $info) {
            $refTableClass = $this->_getCapital($info['foreign_tbl_name']);
            $key = $this->_getCapital($info['key_name']);
            $references[] = "
                   '$key' => array(
                       'columns' => '{$info['column_name']}',
                       'refTableClass' => '{$refTableClass}',
                       'refColumns' =>  '{$info['foreign_tbl_column_name']}'
                           )";
            if (sizeof($references) > 0) {
                $referenceMap = "protected \$_referenceMap    = array(\n" .
                        join(',', $references) . "          \n                );";
            }
        }

        $dbTableData = $this->getParsedTplContents('dbtable.tpl', $referenceMap);

        if (!file_put_contents($dbTableFile, $dbTableData))
            die("Error: could not write db table file $dbTableFile.");
    }

    /**
     * creates the Mapper class file
     */
    function makeMapperFile()
    {

        $mapperFile = $this->getLocation() . DIRECTORY_SEPARATOR . $this->_className . 'Mapper.php';

        $mapperData = $this->getParsedTplContents('mapper.tpl');

        if (!file_put_contents($mapperFile, $mapperData))
            die("Error: could not write mapper file $mapperFile.");
    }

    /**
     * creates the model class file
     */
    function makeModelFile()
    {

        $modelFile = $this->getLocation() . DIRECTORY_SEPARATOR . $this->_className . '.php';

        $modelData = $this->getParsedTplContents('model.tpl');

        if (!file_put_contents($modelFile, $modelData))
            die("Error: could not write model file $modelFile.");
    }

    /**
     *
     * creates all class files
     *
     * @return Boolean
     */
    function doItAll()
    {

        $this->makeDbTableFile();
        $this->makeMapperFile();
        $this->makeModelFile();

        $templatesDir = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'templates') . DIRECTORY_SEPARATOR;

        if (!copy($templatesDir . 'model_class.tpl', $this->getLocation() . DIRECTORY_SEPARATOR . 'MainModel.php'))
            die("could not copy model_class.tpl as MainModel.php");
        if (!copy($templatesDir . 'dbtable_class.tpl', $this->getLocation() . DIRECTORY_SEPARATOR . 'DbTable' . DIRECTORY_SEPARATOR . 'MainDbTable.php'))
            die("could not copy dbtable_class.php as MainDbTable.php");

        return true;
    }

}
