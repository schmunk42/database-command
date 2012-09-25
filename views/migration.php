<?php echo "
/**
 * Created with https://github.com/schmunk42/database-command
 */

<?php

class {$migrationClassName} extends CDbMigration {

	public function safeUp() {
{$functionUp}
	}

	public function safeDown() {
		echo 'Migration down not supported.';
	}

}

?>
";
?>