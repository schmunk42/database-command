<?php

/**
 * Class file.
 *
 * @author Tobias Munk <schmunk@usrbin.de>
 * @link http://www.phundament.com/
 * @copyright Copyright &copy; 2005-2011 diemeisterei GmbH
 * @license http://www.phundament.com/license/
 */

/**
 * Command to dump databases into PHP code for migration classes
 *
 * Creates a CDbMigration class in application.runtime
 *
 * Based upon http://www.yiiframework.com/doc/guide/1.1/en/database.migration#c2550 from Leric
 *
 * @author Tobias Munk <schmunk@usrbin.de>
 * @author Oleksii Strutsynskyi <cajoy1981@gmail.com>
 */

// TODO
    // fix col onupdate / timestamp

class EDatabaseCommand extends CConsoleCommand
{
    /**
     * @var string the directory that stores the migrations. This must be specified
     * in terms of a path alias, and the corresponding directory must exist.
     * Defaults to 'application.runtime' (meaning 'protected/runtime').
     * Copy the created migration into eg. application.migrations to activate it for your project.
     */
    public $migrationPath='application.runtime';

    /**
     * @var string database connection component
     */
    public $dbConnection = "db";

    /**
     * @var string wheter to dump a create table statement
     */
    public $createSchema = true;

    /**
     * @var string wheter to dump a insert data statements
     */
    public $insertData = true;

    /**
     * @var string wheter to add truncate table data statements
     */
    public $truncateTable = false;

    /**
     * @var string wheter to disable foreign key checks
     */
    public $foreignKeyChecks = true;

    /**
     * @var string dump only table with the given prefix
     */
    public $prefix = "";

    /**
     * @var string comma-separated list of tables to be excluded
     */
    public $excludeTables = "";

    /**
     * @var string wheter to ignore the migration table
     */
    public $ignoreMigrationTable = true;

    /**
     * @var bool whether to display the Foreign Keys warning
     */
    protected $_displayFkWarning = false;
    
    /**
     * @var string wheter to ignore autoincrement column values
     */
    public $insertAutoIncrementValues = true;

    public function beforeAction($action,$params)
    {
        $path=Yii::getPathOfAlias($this->migrationPath);
        if($path===false || !is_dir($path))
        {
            echo 'Error: The migration directory does not exist: '.$this->migrationPath."\n";
            exit(1);
        }
        $this->migrationPath=$path;

        return parent::beforeAction($action,$params);
    }

    public function getHelp()
    {
        echo <<<EOS
Usage: yiic {$this->name} <action>

Available actions:

dump [<name>] [--prefix=<table_prefix,...>] [--dbConnection=<db>]
    [--createSchema=<1|0>] [--insertData=<1|0>] [--foreignKeyChecks=<1|0>]
    [--ignoreMigrationTable=<1|0>] [--truncateTable=<0|1>]
    [--insertAutoIncrementValues=<1|0>] [--migrationPath=<application.runtime>]


EOS;
    }

    public function actionDump($args)
    {
        echo "Connecting to '".Yii::app()->{$this->dbConnection}->connectionString."'\n";

        $schema = Yii::app()->{$this->dbConnection}->schema;
        $tables = Yii::app()->{$this->dbConnection}->schema->tables;

        $sql=" SELECT * FROM information_schema.referential_constraints WHERE constraint_schema = :databaseName";
        //get DataBase Name
        $curdb  = explode('=', Yii::app()->db->connectionString);
        $totalForeignKey=Yii::app()->{$this->dbConnection}->createCommand($sql)->bindValue(':databaseName',$curdb[2])->queryAll();

        $code = '';
        $code .= $this->indent(2) . "if (\$this->dbConnection->schema instanceof CMysqlSchema) {\n";
        if ($this->foreignKeyChecks == false) {
            $code .= $this->indent(2) . "   \$this->execute('SET FOREIGN_KEY_CHECKS = 0;');\n";
        }
        $code .= $this->indent(2) . "   \$options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8';\n";
        $code .= $this->indent(2) . "} else {\n";
        $code .= $this->indent(2) . "   \$options = '';\n";
        $code .= $this->indent(2) . "}\n";

        $migrationName = (isset($args[0])) ? $args[0] : 'dump';
        if (preg_match('/^[a-z_]\w+$/i', $migrationName) === 0) {
            exit("Invalid class name '$migrationName'\n");
        }

        $migrationClassName = 'm' . date('ymd_His') . "_" . $migrationName;
        $filename = $this->migrationPath . DIRECTORY_SEPARATOR . $migrationClassName . ".php";
        $prefixes = explode(",", $this->prefix);

        $codeTruncate = $codeSchema = $codeForeignKeys = $codeIndexs = $codeInserts = '';

        echo "Querying tables \n";

        foreach ($tables as $table) {

            $found = false;

            if ($this->ignoreMigrationTable && $table->name == "migration") {
                continue;
            }
            if (in_array($table->name, explode(",",$this->excludeTables))) {
                continue;
            }

            foreach ($prefixes AS $prefix) {
                if (substr($table->name, 0, strlen($prefix)) == $prefix) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                continue;
            }

            echo " -> ".$table->name."\n";

            if ($this->truncateTable == true) {
                $codeTruncate .= $this->generateTruncate($table, $schema);
            }

            if ($this->createSchema == true) {
                $codeSchema .= $this->generateSchema($table, $schema);
                $codeIndexs .= $this->getIndexs($table);
                $codeForeignKeys .= $this->generateForeignKeys($table, $schema,$totalForeignKey);
            }

            if ($this->insertData == true) {
                $codeInserts .= $this->generateInserts($table, $schema);
            }
        }

        $code .= $codeTruncate."\n";
        $code .= $codeSchema."\n";
        $code .= $codeIndexs."\n";
        $code .= $codeForeignKeys."\n";
        $code .= $codeInserts."\n";

        if ($this->foreignKeyChecks == false) {
            $code .= $this->indent(2) . "if (\$this->dbConnection->schema instanceof CMysqlSchema)\n";
            $code .= $this->indent(2) . "   \$this->execute('SET FOREIGN_KEY_CHECKS = 1;');\n";
        }

        $migrationClassCode = $this->renderFile(
            dirname(__FILE__) . '/views/migration.php', array('migrationClassName' => $migrationClassName,
                                                              'functionUp' => $code), true);

        file_put_contents($filename, $migrationClassCode);

        echo "\n\nMigration class successfully created at \n$filename\n\n";

        if ($this->_displayFkWarning) {
            echo <<<EOS
WARNING
Your database include Foreign Keys definitions. Sadly Yii methods don't allow to know the details of the relation, precisely
ON DELETE and ON UPDATE conditions.
Please open the generated file, look for lines with "FIX RELATIONS" comment and adjust them according to your database.
For details about the addForeignKey definition please see here:
    http://www.yiiframework.com/doc/api/1.1/CDbMigration#addForeignKey-detail


EOS;
        }
    }

    private function indent($level = 0)
    {
        return str_repeat("    ", $level);
    }

    private function generateSchema($table, $schema)
    {
        $options = "ENGINE=InnoDB DEFAULT CHARSET=utf8";
        $code = "\n\n\n" . $this->indent(2) . "// Schema for table '" . $table->name . "'\n";
        $code .= $this->indent(2) . '$this->createTable("' . $table->name . '", ';
        $code .= "\n";
        $code .= $this->indent(3) . 'array(' . "\n";
        foreach ($table->columns as $col) {
            $code .= $this->indent(3) . '"' . $col->name . '"=>"' . $this->resolveColumnType($col) . '",' . "\n";
        }

        // special case for non-auto-increment PKs
        $code .= $this->generatePrimaryKeys($table->columns);
        $code .= $this->indent(3) . '), ' . "\n";
        $code .= $this->indent(2) . '$options);';
        return $code;
    }

    private function generatePrimaryKeys($columns)
    {
        foreach ($columns as $col) {
            if ($col->isPrimaryKey && !$col->autoIncrement) {
                return $this->indent(3) . '"PRIMARY KEY (' . $col->name . ')"' . "\n";
            }
        }
    }
    private function getForeignKeyConstraint($table_name,$refernced_table_name,$arrayForeignKey)
    {
        foreach($arrayForeignKey as $index=>$recode)
        {
            if($recode['TABLE_NAME']==$table_name && $recode['REFERENCED_TABLE_NAME']==$refernced_table_name)
            {
               return $index ;
            }
        }
         return null ;
    }

    private function getIndexs($table)
    {
        // TODO check if only CMysqlSchema
        $sql = "SHOW INDEX FROM {$table->name}";
        $indexs=Yii::app()->{$this->dbConnection}->createCommand($sql)->queryAll();
        if (count($indexs) == 0)
        {
            return "";
        }
        $code = "\n\n" . $this->indent(2) . "// Indexs Keys for table '" . $table->name . "'\n";
        foreach ($indexs as $index)
        {
            // i think primary already set !!
            if ($index['Key_name'] == 'PRIMARY')
            {
                continue;
            }
            $indexUnique = 'true';
            if ($index['Non_unique'] == 1)
            {
                $indexUnique = 'false';
            }
            $code .= $this->indent(2) . "\$this->createIndex('{$index['Key_name']}','{$index['Table']}','{$index['Column_name']}',{$indexUnique}); \n";
        }
        return $code;
    }

    private function generateForeignKeys($table, $schema,$totalForeignKey)
    {
        if (count($table->foreignKeys) == 0) {
            return "";
        }
        $code = "\n\n" . $this->indent(2) . "// Foreign Keys for table '" . $table->name . "'\n";
        $code .= $this->indent(2) . "if ((\$this->dbConnection->schema instanceof CSqliteSchema) == false):\n";
         
        foreach ($table->foreignKeys as $name => $foreignKey) {
            $index=$this->getForeignKeyConstraint($table->name,$foreignKey[0],$totalForeignKey);
            if($index!=null)
            {
               $DELETE_RULE= "'".$totalForeignKey[$index]['DELETE_RULE']."'";
               $UPDATE_RULE= "'".$totalForeignKey[$index]['UPDATE_RULE']."'"; 

            }
            else
            {
                 $DELETE_RULE='Null';
                 $UPDATE_RULE='Null';
            }
            $code .= $this->indent(3) . "\$this->addForeignKey('fk_{$table->name}_{$foreignKey[0]}_{$name}', '{$table->name}', '{$name}', '{$foreignKey[0]}', '{$foreignKey[1]}', {$DELETE_RULE},{$UPDATE_RULE}); \n";
 
        }
        $code .= $this->indent(2) . "endif;\n";
        $this->_displayFkWarning = TRUE;
        return $code;
    }

    private function generateInserts($table, $schema)
    {
        $data = Yii::app()->{$this->dbConnection}->createCommand()
            ->from($table->name)
            ->query();

        $code = "\n\n\n" . $this->indent(2) . "// Data for table '" . $table->name . "'\n";
        foreach ($data AS $row) {
            $code .= $this->indent(2) . '$this->insert("' . $table->name . '", array(' . "\n";
            foreach ($row AS $column => $value) {
                if($this->insertAutoIncrementValues == false && $table->columns[$column]->autoIncrement === true) {
                  $value = null;
                }
                $code .= $this->indent(3) . '"' . $column . '"=>' . (($value === null) ? 'null' :
                    '"' . addcslashes($value, '"\\$') . '"') . ',' . "\n";
            }
            $code .= $this->indent(2) . ') );' . "\n\n";
        }
        return $code;
    }

    private function generateTruncate($table)
    {
        $code = "";
        $code .= $this->indent(2) . '$this->truncateTable("' . $table->name . '");' . "\n";
        return $code;
    }

    private function resolveColumnType($col)
    {
        $result = $col->dbType;
        if (!$col->allowNull) {
            $result .= ' NOT NULL';
        }
        if ($col->defaultValue !== NULL)
        {
            $result .= " DEFAULT '{$col->defaultValue}'";
        }
        if ($col->isPrimaryKey) {
            $result .= " PRIMARY KEY";
        }
        if ($col->autoIncrement) {
            $result .= " AUTO_INCREMENT";
        }
        return $result;
    }


}

?>
