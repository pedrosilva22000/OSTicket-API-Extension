<?php

/**
 * @file
 * Plugin class extension for the OSTicket API Extension plugin.
 */

include_once 'plugin.config.php';
require_once 'class.staff.php';
require_once 'class.plugin.php';
require_once 'api/class.api.extension.php';
require_once 'util/table.installer.php';
require_once 'config.php';

include INCLUDE_DIR . 'class.dispatcher.php';

/**
 * Class PluginExtension.
 *
 * This class is the main class of the plugin, this is where the plugin is initialized and
 * the class that uninstalls the plugin when disabled.
 */
class PluginExtension extends Plugin
{
	//PARA TESTES APAGAR DEPOIS
	function debugToFile($erro)
    {
        $file = PRJ_PLUGIN_DIR . "debug.txt";
        $text =  $erro . "\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }

	//referencia para a classe das configuracoes do plugin
	var $config_class = 'PluginConfigExtension';

	//valor default de guardar a opcao de guardar os valores nas tabelas depois de desativar o plugin
	//porque a checkbox nao funciona corretamente
	var $saveInfo = true;

	/**
     * Function called when the plugin is initialized.
	 * Always runs, even if disabled as long as the plugin is installed.
	 * 
     * Used to store the configuration values even when disabled, 
	 * this is needed for the saveInfo config to install the tables back after enabling the plugin.
	 * 
	 * Also check if is the first run of the plugin to set the saveInfo config as true by default, because pf the Booleanfield bug.
     */
	function init(){
		$config = $this->getConfig();
		if(!($this->saveInfo = $config->get('save_info'))){
			$this->saveInfo = false;
		}
		if ($this->firstRun()) {
			$this->saveInfo = true; //mete a true por default a primeira vez porque o booleanfield esta bugado
		}
	}

	/**
     * Function called when the plugin is initialized.
	 * The plugin Needs to be installed and active for this function to run.
	 * 
	 * Gets all config values not stored in init() function.
	 * Registers all endpoints for the API to use.
	 * If its the first run of the plugin, installs all tables and rows necessary, 
	 * and adds a new API key to the staff specified in the config.
     */
	function bootstrap()
	{
		$config = $this->getConfig();
		$username = $config->get('username');

		self::registerEndpoints();
		if ($this->firstRun()) {
			$this->setDataBase();
			$this->addApiKeyRow($username);
		}
	}

	/**
     * Function that overrides the function of the same name in the Plugin Class.
	 * 
	 * This is used to run custom code when the plugin is enabled or disabled.
	 * 
	 * @return boolean true if the plugin is active, false if not.
     */
	function isActive()
	{
		if (!parent::isActive()) {
			$this->disable();
		} else {
			$this->enable();
		}
		return parent::isActive();
	}

	/**
     * Function that overrides the function of the same name in the Plugin Class.
	 * 
	 * This is function is called everytime there is a verification to see if the plugin is active and it is active.
	 * 
	 * If its the first run of the plugin installs all tables and rows necessary.
	 * If SAVED_DATA_SQL (the file where old table values are stored) is not empty, tries to add all those old values too.
	 * 
	 * @return boolean always true, value of parent::enable().
     */
	function enable()
	{
		if($this->firstRun())
			$this->setDataBase();

		if(!$this->fileIsEmpty(SAVED_DATA_SQL))
			$this->populateSavedData();

		return parent::enable();
	}

	/**
	 * Function that verifies if a file is empty or not.
	 * Verifies if the file actually exists and if any bytes are store there.
	 * 
	 * This is used to see if there are any old values in the tables stored.
	 * 
	 * @param string file name and path that need to be checked.
	 * 
	 * @return boolean true if file is empty, false if not.
     */
	//verifica se existe um ficheir com os valores da tabvela guardados
	function fileIsEmpty($filename){

		if (file_exists($filename)) {

			$handle = fopen($filename, "r");
			$filesize = filesize($filename);
			fclose($handle);
	
			//verifica se o ficheiro tem alguma informacao la dentro baseado no seu tamanho
			if ($filesize > 0) {
				return false; //nao esta vazio, logo vai correr o que esta la dentro quando o plugin é ativado
			} else {
				return true; //esta vazio, o plugin corre por defualt com uma api key nova vinda do config
			}
		} else {
			return true; //o ficheiro nao existe
		}
	}

	/**
	 * This is function is called everytime there is a verification to see if the plugin is active and it is NOT active.
	 * 
	 * Verifies if it is the plugin first run (verifies if theere are any tables installed already),
	 * if not there is no point to try and do anything.
	 * 
	 * Verifies if the saveInfo configuration option is true,
	 * if it is, stores all values in the tables installed by the plugin in a file (SAVED_DATA_SQL),
	 * that is used to add back all those values if the plugin is enabled again.
	 * 
	 * Uninstalls all tables and rows added by the plugin.
     */
	function disable()
	{
		if($this->firstRun()){
			return;
		}

		//ve se o utilizador quer guardar a informacao das tabelas ou nao, true por defualt para alterar é necessario dar uodate a instancia
		if($this->saveInfo){
			//guarda a informacao das novas tabelas num ficheiro sql
			//suporta varias tabelas, se criarmos novas é so adicionar o nome a array
			$tableNames = array(
				API_NEW_TABLE,
				SUSPEND_NEW_TABLE
				//ADICIONAR NOVAS TABELAS AQUI
			);
			$this->storeData($tableNames, SAVED_DATA_SQL);
		}

		//desisntala as tabelas e linhas novas da base de dados
		$installer = new TableInstaller();
		$installer->runJob(UNINSTALL_SCRIPT);
	}

	/**
	 * Stores all data in the tables specified in the specified file.
	 * 
	 * Selects all data from the specified tables.
	 * For every table creates an INSERT query and stores all data in the query as a string in the file,
	 * to be runned again when the plugin is enabled.
	 * 
	 * @param array array with all of the tables names added by the plugin.
	 * @param string name and path of the file you want all of the data to be stored in.
     */
	function storeData($tableNames, $sqlSaveData){
		//guarda os resultados de cada tabela numa array
		$tablesArray = array();

		foreach($tableNames as $tableName){
			//exporta a informação para um ficheiro
			$sql_query = 'SELECT * FROM `' . $tableName . '`';

			$res = db_query($sql_query);
			/* db_query($sql_query); */

			//vai buscar as linhas todas -> informação
			$array = db_assoc_array($res);

			//guarda o resultado desta tabela na array de tabelas
			$tablesArray[$tableName] = $array;
		}

		//guarda os inserts todos no ficheiro sql
		$file = fopen($sqlSaveData, 'w');

		foreach($tablesArray as $tableName => $array){

			$columns = array_keys($array[0]);

			$valuesArrays = array_fill(0, count($columns), array());

			foreach ($array as $arr) {
				foreach ($columns as $index => $column) {
					$valuesArrays[$index][] = "'" . addslashes($arr[$column]) . "'";
				}
			}

			$insertSQL = "INSERT INTO `$tableName` (" . implode(", ", array_map(function($column) {
                return "`$column`";
            }, $columns)) . ")\n VALUES ";

			foreach ($valuesArrays[0] as $rowIndex => $value) {
				$insertSQL .= "(" . implode(", ", array_column($valuesArrays, $rowIndex)) . "),\n";
			}

			$insertSQL = rtrim($insertSQL, ",\n") . ";\n\n";

			fwrite($file, $insertSQL);
		}

		fclose($file);
	}

	/**
	 * Adds a new API key to the specified staff.
	 * 
	 * @param string username, email or id of the staff.
     */
	function addApiKeyRow($username)
	{
		$staff = Staff::lookup($username);
        // $staff = StaffAuthenticationBackend::getUser(); tentatica de meter o staff com login com api key nova sem usar o config

		$data = array(
			'idStaff' => "{$staff->getId()}",
			'isActive' => "1",
			'canCreateTickets' => "1",
			'canCloseTickets' => "1",
			'canReopenTickets' => "1",
			'canEditTickets' => "1",
			'canSuspendTickets' => "1",
			'notes' => "An API key automatically generated upon the plugin first run."
		);

		ApiExtension::add($data, $erros);
	}

	/**
	 * Verifies if it is the plugins firstrun.
	 * Does that by checking if the new tables are already installed in the OSTicket schema.
	 * 
	 * @param boolean true if the new tables are already installed, flase if not.
     */
	function firstRun()
	{
		$sql = 'SHOW TABLES LIKE \'' . API_NEW_TABLE . '\'';
		$res = db_query($sql);
		return (db_num_rows($res) == 0);
	}

	/**
	 * Installs all new tables and rows needed for the plugin to work.
     */
	function setDataBase()
	{
		$installer = new TableInstaller();
		$installer->runJob(INSTALL_SCRIPT);
	}

	/**
	 * Inserts all the stored data from previous plugin uses in the new tables.
     */
	function populateSavedData(){
		$installer = new TableInstaller();
		$installer->runJob(SAVED_DATA_SQL);
	}

	/**
	 * Overrides function with the same name in the paren class.
	 * 
	 * Makes the plugin only support one instance per installation.
	 * This is because even if more instances were allowed thay would all use the same tables,
	 * which would mean they would all be the same.
     */
	function isMultiInstance(){
		return false;
	}

	/**
	 * Registers all endpoints and their respective functions to be used by the API.
     */
	private static function registerEndpoints()
	{

		$routesPOST = array(
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

		$routesGET = array(
			array(
				'prefix' => "departments",
				'function' => 'showDeps'
			),
			array(
				'prefix' => "slas",
				'function' => 'showSLAs'
			),
			array(
				'prefix' => "teams",
				'function' => 'showTeams'
			),
			array(
				'prefix' => "staff",
				'function' => 'showStaff'
			),
			array(
				'prefix' => "users",
				'function' => 'showUsers'
			),
			array(
				'prefix' => "priorities",
				'function' => 'showPriority'
			),
			array(
				'prefix' => "topics",
				'function' => 'showTopic'
			),
			array(
				'prefix' => "sources",
				'function' => 'showSources'
			)

		);

		foreach ($routesPOST as $route) {
			Signal::connect('api', function ($dispatcher) use ($route) {
				$dispatcher->append(
					url_post(
						"^/{$route['prefix']}\.(?P<format>xml|json|email)$",
						array(PRJ_API_DIR.'api.extension.php:TicketApiControllerExtension', $route['function'])
					)
				);
			});
		}

		foreach ($routesGET as $route) {
			Signal::connect('api', function ($dispatcher) use ($route) {
				$dispatcher->append(
					url_get(
						"^/{$route['prefix']}\.(?P<format>xml|json|email)$",
						array(PRJ_API_DIR.'api.extension.php:TicketApiControllerExtension', $route['function'])
					)
				);
			});
		}
	}

	/**
	 * DOES NOT WORK, NOT SUPPORTED BY OSTICKET YET
	 * 
	 * Overrides function with the same name in the parent class.
	 * Enables the injection of custom code when pre uninstalling the plugin.
	 * @return boolean true.
     */
	function pre_uninstall(&$errors) {
		// $this->debugToFile('PRE_UNINSTALL');
        return parent::pre_uninstall($errors);
    }

	/**
	 * DOES NOT WORK, NOT SUPPORTED BY OSTICKET YET
	 * 
	 * Overrides function with the same name in the parent class.
	 * Enables the injection of custom code when uninstalling the plugin.
	 * @return boolean true or false.
     */
	function uninstall(&$errors) {
		// $this->debugToFile('UNINSTALL');
		// $installer = new TableInstaller();
		// $installer->runScript(SQL_SCRIPTS_DIR.UNINSTALL_SCRIPT);
		return parent::uninstall($errors);
	}

	/**
	 * DOES NOT WORK, NOT SUPPORTED BY OSTICKET YET
	 * 
	 * Overrides function with the same name in the parent class.
	 * Enables the injection of custom code when deleting the plugin.
     */
	function delete(){
		// $this->debugToFile('DELETEEEE');
		parent::delete();
	}
}
