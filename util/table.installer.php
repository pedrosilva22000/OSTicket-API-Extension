<?php

require_once 'class.setup.php';
include 'api.config.php';

class TableInstaller extends \SetupWizard {
    
    function install($dir) {
		
		$schemaFile = __DIR__ . $dir ;
		$this->runJob ( $schemaFile );
	}

    private function runJob($schemaFile, $show_sql_errors = true) {
		// Last minute checks.
		if (! file_exists ( $schemaFile )) {
			return;
		} elseif (! $this->load_sql_file ( $schemaFile, TABLE_PREFIX, true, true )) {
			if ($show_sql_errors) {
				return;
			}
		}
		
	}

	

}



?>