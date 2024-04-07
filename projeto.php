<?php
require_once 'class.staff.php';
require_once 'class.plugin.php';
require_once 'class.api.projeto.php';
require_once 'util/table.installer.php';
require_once 'config.php';

include 'api.config.php';

include INCLUDE_DIR . 'class.dispatcher.php';

include 'debugger.php';

class ProjetoPlugin extends Plugin
{
	var $config_class = 'ProjetoPluginConfig';

	function bootstrap()
	{
        $config = $this->getConfig();
		$username = $config->get('username');

		self::registerEndpoints();

		if ($this->firstRun()) {
			
			$this->createDBTables();
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

	function createDBTables()
	{
		$installer = new TableInstaller();
		return $installer->install();
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
						array(INCLUDE_DIR . 'plugins/api/api.projeto.php:TicketApiControllerProjeto', $route['function'])
					)
				);
			});
		}
	}
}