<?php

require_once 'class.setup.php';
include 'api.config.php';

class TableInstaller extends \SetupWizard {
    
    function install() {
		
		$schemaFile = __DIR__ . '/../sql/api_key_table.sql';
		return $this->runJob ( $schemaFile );
	}

    private function runJob($schemaFile, $show_sql_errors = true) {
		// Last minute checks.
		if (! file_exists ( $schemaFile )) {
			echo '<br />';
			var_dump ( $schemaFile );
			echo '<br />';
			echo 'File Access Error - please make sure your download is the latest (#1)';
			echo '<br />';
			return false;
		} elseif (! $this->load_sql_file ( $schemaFile, TABLE_PREFIX, true, true )) {
			if ($show_sql_errors) {
				echo '<br />';
				echo 'Error parsing SQL schema! Get help from developers (#4)';
				echo '<br />';
				return false;
			}
			return true;
		}
		
		return true;
	}

	

}



?>