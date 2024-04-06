<?php
require_once 'class.plugin.php';
require_once 'util/table.installer.php';
require_once 'config.php';

include 'api.config.php';
// include INCLUDE_DIR.'class.dispatcher.php';

class ProjetoPlugin extends Plugin {
	var $config_class = 'ProjetoPluginConfig';

	function bootstrap(){
        $this->debugToFile("bootstrap");
		$this->createDBTables();
        // self::registerEndpoint();
		if ($this->firstRun ()) {
			
			if (! $this->configureFirstRun ()) {
				return false;
			}
		}
		$this->getConfig();
	}

    function debugToFile($erro){
        $file = INCLUDE_DIR."plugins/api/debug.txt";
        $text =  $erro."\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }

    function firstRun() {
		$sql = 'SHOW TABLES LIKE \'' . TABLE_PREFIX.API_NEW_TABLE . '\'';
		$res = db_query ( $sql );
		return (db_num_rows ( $res ) == 0);
	}

    function configureFirstRun() {
		if (! $this->createDBTables ()) {
			echo "First run configuration error.  " . "Unable to create database tables!";
			return false;
		}
		return true;
	}

    function createDBTables() {
		$installer = new TableInstaller ();
		return $installer->install ();
	}


	// public function init() {
    //     // Register API Endpoint
    //     $this->debugToFile("INIT");
    //     self::registerEndpoint();
    // }

	
	// private static function registerEndpoint() {
    //     $dispatcher = patterns('',
        
    //         url_post("^/open/tickets\.(?P<format>xml|json|email)$", array(INCLUDE_DIR.'plugins/api/api.projeto.php:TicketApiControllerProjeto','create')),

    //         url_post("^/close/tickets\.(?P<format>xml|json|email)$", array(INCLUDE_DIR.'plugins/api/api.projeto.php:TicketApiControllerProjeto','close')),
            
    //         url_post("^/suspend/tickets\.(?P<format>xml|json|email)$", array(INCLUDE_DIR.'plugins/api/api.projeto.php:TicketApiControllerProjeto','suspend')),

    //         url_post("^/requestApiKey/tickets\.(?P<format>xml|json|email)$", array(INCLUDE_DIR.'plugins/api/api.projeto.php:TicketApiControllerProjeto','requestApiKey'))
    //     );
    //     $file = INCLUDE_DIR."plugins/api/debug.txt";
    //     $text =  "preolaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\n";
    //     file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    //     Signal::connect('api', $dispatcher);
    // }
    
}