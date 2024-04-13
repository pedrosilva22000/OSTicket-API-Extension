<?php
include_once 'plugin.config.php';
require_once 'class.staff.php';
require_once 'class.plugin.php';
require_once 'api/class.api.projeto.php';
require_once 'util/table.installer.php';
require_once 'config.php';

include INCLUDE_DIR . 'class.dispatcher.php';

include 'debugger.php';

class ProjetoPlugin extends Plugin
{
	var $config_class = 'ProjetoPluginConfig';

	function isActive()
	{
		if (!parent::isActive()) {
			$this->disable();
		} else {
			$this->enable();
		}
		return parent::isActive();
	}

	function enable()
	{
		if (parent::isActive()) {
		}
		return parent::enable();
	}

	function disable()
	{
		//exporta a informação para um ficheiro
		$sql_query = 'SELECT * FROM `' . TABLE_PREFIX . API_NEW_TABLE . '`';

		$res = db_query($sql_query);
		/* db_query($sql_query); */

		//vai buscar as linhas todas -> informação
		$array = db_assoc_array($res, 2);

		foreach ($array as $arr) {

			foreach ($arr as $a) {
				Debugger::debugToFile($a);
			}
		}



		/* Debugger::debugToFile(db_query($sql_query)); */


		$file = fopen(SQL_SCRIPTS_DIR . 'data.sql', 'w');

		// Iterate over the result set
		/* while ($row = db_fetch_row($res)) {
            
            fwrite($file, $row);
        } */



		/* fwrite($file,$res); */

		fclose($file);
	}

	function bootstrap()
	{
		$config = $this->getConfig();
		$username = $config->get('username');

		self::registerEndpoints();

		if ($this->firstRun()) {

			$this->setDataBase();
			$this->populateFirst($username);
		}
	}

	function populateFirst($username)
	{
		$staff = Staff::lookup($username);

		$data = array(
			'idStaff' => "{$staff->getId()}",
			'isActive' => "1",
			'canCreateTickets' => "1",
			'canCloseTickets' => "1",
			'canReopenTickets' => "1",
			'canEditTickets' => "1",
			'canSuspendTickets' => "1",
			'notes' => "An API key automatically generated upon the plugin's first run."
		);

		ApiProjeto::add($data, $erros);
	}

	function firstRun()
	{
		$sql = 'SHOW TABLES LIKE \'' . TABLE_PREFIX . API_NEW_TABLE . '\'';
		$res = db_query($sql);
		return (db_num_rows($res) == 0);
	}

	function setDataBase()
	{
		$installer = new TableInstaller();
		$installer->install(SQL_SCRIPTS_DIR . "scripts.sql");
	}


	private static function registerEndpoints()
	{

		$routes = array(
			array(
				'prefix' => "open/tickets",
				'function' => 'create'
			),
			array(
				'prefix' => "close/tickets",
				'function' => 'close'
			),
			array(
				'prefix' => "suspend/tickets",
				'function' => 'suspend'
			),
			array(
				'prefix' => "requestApiKey/tickets",
				'function' => 'requestApiKey'
			),
			array(
				'prefix' => "reopen/tickets",
				'function' => 'reopen'
			),
			array(
				'prefix' => "edit/tickets",
				'function' => 'edit'
			)
		);

		foreach ($routes as $route) {
			Signal::connect('api', function ($dispatcher) use ($route) {
				$dispatcher->append(
					url_post(
						"^/{$route['prefix']}\.(?P<format>xml|json|email)$",
						array(PRJ_API_DIR . 'api.projeto.php:TicketApiControllerProjeto', $route['function'])
					)
				);
			});
		}
	}
}
