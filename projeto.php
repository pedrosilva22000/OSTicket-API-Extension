<?php
require_once 'class.plugin.php';
require_once 'util/table.installer.php';
require_once 'config.php';

include 'api.config.php';

include INCLUDE_DIR.'class.dispatcher.php';

/* include INCLUDE_DIR.'class.signal.php'; */
/* require 'api.inc.php'; */

class ProjetoPlugin extends Plugin {
	var $config_class = 'ProjetoPluginConfig';

	function bootstrap(){
        
		$this->createDBTables();

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


	 public function init() {
        self::registerEndpointOpen();
		self::registerEndpointClose();
		self::registerEndpointSuspend();
		self::registerEndpointRequest();

     }
	
	 private static function registerEndpointOpen() {
		Signal::connect('api', function ($dispatcher){
            $dispatcher->append(
                url_post("^/open/tickets\.(?P<format>xml|json|email)$", array(INCLUDE_DIR.'plugins/api/api.projeto.php:TicketApiControllerProjeto','create')),
            );
        });
    }

	private static function registerEndpointClose() {
		Signal::connect('api', function ($dispatcher){
            $dispatcher->append(
				url_post("^/close/tickets\.(?P<format>xml|json|email)$", array(INCLUDE_DIR.'plugins/api/api.projeto.php:TicketApiControllerProjeto','close')),
            );
        });
    }

	private static function registerEndpointSuspend() {
		Signal::connect('api', function ($dispatcher){
            $dispatcher->append(
                url_post("^/suspend/tickets\.(?P<format>xml|json|email)$", array(INCLUDE_DIR.'plugins/api/api.projeto.php:TicketApiControllerProjeto','suspend')),
            );
        });
    }

	private static function registerEndpointRequest() {
		Signal::connect('api', function ($dispatcher){
            $dispatcher->append(
                url_post("^/requestApiKey/tickets\.(?P<format>xml|json|email)$", array(INCLUDE_DIR.'plugins/api/api.projeto.php:TicketApiControllerProjeto','requestApiKey'))
            );
        });
    }
    
}