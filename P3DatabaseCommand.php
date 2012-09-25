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
 * @package p3extensions.commands
 * @since 3.0.1
 */

class P3DatabaseCommand extends CConsoleCommand {
    
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
	 * @var string dump only table with the given prefix
	 */    
    public $prefix = "";
    
	public function getHelp() {
        echo <<<EOS
Usage: yiic {$this->name} <action>

Available actions: dump [--prefix=<table_prefix>] [--createSchema=<0|1>] [--insertData=<0|1>] [--dbConnection=<db>]


EOS;
    }
	
	public function actionDump($args) {

        $schema = Yii::app()->{$this->dbConnection}->schema;		
		$tables = Yii::app()->{$this->dbConnection}->schema->tables;
		
		$code = '';
		$code .= $this->indent(2)."if (Yii::app()->db->schema instanceof CMysqlSchema)\n";
		$code .= $this->indent(2)."	\$options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8';\n";
		$code .= $this->indent(2)."else\n";
		$code .= $this->indent(2)."	\$options = '';\n";
		
		foreach ($tables as $table) {
			if (substr($table->name, 0, strlen($this->prefix)) != $this->prefix)
				continue;
            
            if ($this->createSchema == true) {
			    $code .= "\n\n\n".$this->indent(2)."// Schema for table '" . $table->name . "'\n\n";
    			$code .= $this->generateSchema($table, $schema);

    			$code .= "\n\n\n".$this->indent(2)."// Foreign Keys for table '" . $table->name . "'\n\n";
	    		$code .= $this->indent(2)."if ((Yii::app()->db->schema instanceof CSqliteSchema) == false):\n";
		    	$code .= $this->generateForeignKeys($table, $schema);
			    $code .= "\n".$this->indent(2)."endif;\n";
			}

            if ($this->insertData == true) {
    			$code .= "\n\n\n".$this->indent(2)."// Data for table '" . $table->name . "'\n\n";
	    		$code .= $this->generateInserts($table, $schema);
	    	}
		}
		
		$migrationClassName = 'm'.date('ymd_His')."_dump";
		$filename = Yii::app()->basePath.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.$migrationClassName.".php";
		$migrationClassCode = $this->renderFile(
			dirname(__FILE__).'/views/migration.php', 
			array('migrationClassName' => $migrationClassName, 'functionUp'=>$code), 
			true);
		
		file_put_contents($filename, $migrationClassCode);
		
		echo "Your data has been dumped to '$filename'\n";
	}
    
    private function indent($level = 0) {
        return str_repeat("    ", $level);
    }
    
    
	private function generateSchema($table, $schema) {
		$options = "ENGINE=InnoDB DEFAULT CHARSET=utf8";
		$code = '';
		$code .= $this->indent(2).'$this->createTable("' . $table->name . '", ';
		$code .= "\n";
		$code .= $this->indent(3).'array(' . "\n";
		foreach ($table->columns as $col) {
			$code .= $this->indent(3). '"' . $col->name . '"=>"' . $this->resolveColumnType($col) . '",' . "\n";
		}
		
		// special case for non-auto-increment PKs
		$code .= $this->generatePrimaryKeys($table->columns);
		$code .= "\n";
		$code .= $this->indent(3).'), ';
		$code .= "\n";
		$code .= $this->indent(2).'$options);';
		return $code;
	}


	private function generatePrimaryKeys($columns) {
		foreach ($columns as $col) {			
			if ($col->isPrimaryKey && !$col->autoIncrement) {
				return $this->indent(3).'"PRIMARY KEY ('.$col->name.')"';
			}
		}
	}

	private function generateForeignKeys($table, $schema) {
		$code = "";
		foreach ($table->foreignKeys as $name => $foreignKey) {
			#echo "FK" . $name . var_dump($foreignKey);
			$code .= "\n".$this->indent(3)."\$this->addForeignKey('fk_{$foreignKey[0]}_{$name}', '{$table->name}', '{$name}', '{$foreignKey[0]}', '{$foreignKey[1]}', null, null); // update 'null' for ON DELTE and ON UPDATE\n";
		}
		return $code;
	}

	private function generateInserts($table, $schema) {
		$code = '';
		$data = Yii::app()->db->createCommand()
			->from($table->name)
			->query();

		foreach ($data AS $row) {
			$code .= $this->indent(2).'$this->insert("' . $table->name . '", array(' . "\n";
			foreach ($row AS $column => $value) {
				$code .= $this->indent(3).'"' . $column . '"=>' . (($value === null) ? 'null' : '"' . str_replace('"','\"',$value) . '"') . ',' . "\n";
			}
			$code .= $this->indent(2).') );' . "\n\n";
		}
		return $code;
	}

	private function resolveColumnType($col) {
		if ($col->isPrimaryKey && $col->autoIncrement) {
			return "pk";
		}

		$result = $col->dbType;

		if (!$col->allowNull) {
			$result .= ' NOT NULL';
		}
		if ($col->defaultValue != null) {
			$result .= " DEFAULT '{$col->defaultValue}'";
		}
		return $result;
	}

}

?>