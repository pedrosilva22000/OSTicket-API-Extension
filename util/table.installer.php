<?php

include_once 'plugin.config.php';
require_once 'class.setup.php';

class TableInstaller extends \SetupWizard {
    
    function runScript($dir) {
		
		$schemaFile = $dir ;
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