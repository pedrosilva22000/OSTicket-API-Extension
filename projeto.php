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
        self::registerEndpoints();

		if ($this->firstRun ()) {
			
			if (! $this->configureFirstRun ()) {
				return false;
			}
		}
		$this->getConfig();
	}

    static function debugToFile($erro){
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

        global $thisstaff;
        $data['idStaff'] = $thisstaff->getId();
        
		return true;
	}

    function createDBTables() {
		$installer = new TableInstaller ();
		return $installer->install ();
	}
	
	 private static function registerEndpoints() {

        $routes = array(
            array(
                'prefix' => "^/open/tickets",
                'function' => 'create'
            ),
            array(
                'prefix' => "^/close/tickets",
                'function' => 'close'
            ),
            array(
                'prefix' => "^/suspend/tickets",
                'function' => 'suspend'
            ),
            array(
                'prefix' => "^/requestApiKey/tickets",
                'function' => 'requestApiKey'
            ),
            array(
                'prefix' => "^/reopen/tickets",
                'function' => 'reopen'
            ),
            array(
                'prefix' => "^/edit/tickets",
                'function' => 'edit'
            )
        );

        foreach ($routes as $route) {
            Signal::connect('api', function ($dispatcher) use ($route) {
                $dispatcher->append(
                    url_post(
                        "{$route['prefix']}\.(?P<format>xml|json|email)$",
                        array(INCLUDE_DIR.'plugins/api/api.projeto.php:TicketApiControllerProjeto', $route['function'])
                    )
                );
            });
        }
    }
}