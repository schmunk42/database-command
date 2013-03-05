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
class EDatabaseCommand extends CConsoleCommand
{
    /**
     * @var string the directory that stores the migrations. This must be specified
     * in terms of a path alias, and the corresponding directory must exist.
     * Defaults to 'application.migrations' (meaning 'protected/migrations').
     */
    public $migrationPath='application.migrations';

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
     * @var bool whether to display the Foreign Keys warning
     */
    protected $_displayFkWarning = false;

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
    [--truncateTable=<0|1>]


EOS;
    }

    public function actionDump($args)
    {

        $schema = Yii::app()->{$this->dbConnection}->schema;
        $tables = Yii::app()->{$this->dbConnection}->schema->tables;

        $code = '';
        $code .= $this->indent(2) . "if (Yii::app()->db->schema instanceof CMysqlSchema) {\n";
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

        $codeTruncate = $codeSchema = $codeForeignKeys = $codeInserts = '';
        foreach ($tables as $table) {

            $found = false;
            foreach ($prefixes AS $prefix) {
                if (substr($table->name, 0, strlen($prefix)) == $prefix) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                continue;
            }

            if ($this->truncateTable == true) {
                $codeTruncate .= $this->generateTruncate($table, $schema);
            }

            if ($this->createSchema == true) {
                $codeSchema .= $this->generateSchema($table, $schema);
                $codeForeignKeys .= $this->generateForeignKeys($table, $schema);
            }

            if ($this->insertData == true) {
                $codeInserts .= $this->generateInserts($table, $schema);
            }
        }

        $code .= $codeTruncate."\n".$codeSchema."\n".$codeForeignKeys."\n".$codeForeignKeys."\n".$codeInserts;

        if ($this->foreignKeyChecks == false) {
            $code .= $this->indent(2) . "if (Yii::app()->db->schema instanceof CMysqlSchema)\n";
            $code .= $this->indent(2) . "   \$this->execute('SET FOREIGN_KEY_CHECKS = 1;');\n";
        }

        $migrationClassCode = $this->renderFile(
            dirname(__FILE__) . '/views/migration.php', array('migrationClassName' => $migrationClassName,
                                                              'functionUp' => $code), true);

        file_put_contents($filename, $migrationClassCode);

        echo "Your data has been dumped to '$filename'\n";

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

    private function generateForeignKeys($table, $schema)
    {
        if (count($table->foreignKeys) == 0) {
            return "";
        }
        $code = "\n\n\n" . $this->indent(2) . "// Foreign Keys for table '" . $table->name . "'\n";
        $code .= $this->indent(2) . "if ((Yii::app()->db->schema instanceof CSqliteSchema) == false):\n";
        foreach ($table->foreignKeys as $name => $foreignKey) {
            $code .= $this->indent(3) . "\$this->addForeignKey('fk_{$table->name}_{$foreignKey[0]}_{$name}', '{$table->name}', '{$name}', '{$foreignKey[0]}', '{$foreignKey[1]}', null, null); // FIX RELATIONS \n";
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

        if (count($data) == 0) {
            return "";
        }
        $code = "\n\n\n" . $this->indent(2) . "// Data for table '" . $table->name . "'\n";
        foreach ($data AS $row) {
            $code .= $this->indent(2) . '$this->insert("' . $table->name . '", array(' . "\n";
            foreach ($row AS $column => $value) {
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
        if ($col->defaultValue != null) {
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
