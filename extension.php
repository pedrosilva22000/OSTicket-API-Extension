<?php
include_once 'plugin.config.php';
require_once 'class.staff.php';
require_once 'class.plugin.php';
require_once 'api/class.api.extension.php';
require_once 'util/table.installer.php';
require_once 'config.php';

include INCLUDE_DIR . 'class.dispatcher.php';

class PluginExtension extends Plugin
{
	var $config_class = 'PluginConfigExtension';

	var $saveInfo = true; //valor defualt

	//É preciso usar init para o plugin ver as configs mesmo quando esta desativado
	function init(){
		$config = $this->getConfig();
		if(!($this->saveInfo = $config->get('save_info'))){
			$this->saveInfo = false;
		}
		if ($this->firstRun()) {
			$this->saveInfo = true; //mete a true por defualt a primeira vez porque o booleanfield esta bugado
		}
	}

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
		if($this->firstRun() && !$this->fileIsEmpty(SAVED_DATA_SQL)){
			$this->setDataBase();
			$this->populateSavedData();
		}
		return parent::enable();
	}

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

	//ESTA FUNCAO NAO CORRE QUANDO O PLUGIN É DESATIVADO MAS SIM QUANDO É VERIFICADO SE ESTA ATIVO OU NAO QUE É DEPOIS DE ESTAR DESATIVADO
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
			$this->storeData($tableNames);
		}

		//desisntala as tabelas e linhas novas da base de dados
		$installer = new TableInstaller();
		$installer->runScript(UNINSTALL_SCRIPT);
	}

	//Não esta a funcionar bem coloca todos os inserts com o mesmo nome de tabela 
	function storeData($tableNames){
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
		$file = fopen(SAVED_DATA_SQL, 'w');

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

	function firstRun()
	{
		$sql = 'SHOW TABLES LIKE \'' . API_NEW_TABLE . '\'';
		$res = db_query($sql);
		return (db_num_rows($res) == 0);
	}

	function setDataBase()
	{
		$installer = new TableInstaller();
		$installer->runScript(INSTALL_SCRIPT);
	}

	function populateSavedData(){
		$installer = new TableInstaller();
		$installer->runScript(SAVED_DATA_SQL);
	}

	//PARA TESTES APAGAR DEPOIS
	function debugToFile($erro)
    {
        $file = PRJ_PLUGIN_DIR . "debug.txt";
        $text =  $erro . "\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }

	//Só suporta uma instancia (porque usa sempre as mesmas tabelas)
	function isMultiInstance(){
		return false;
	}

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

	//OS PLUGINS DO OSTICKET NAO SUPORTAM ESTES METODOS NESTA VERSAO, PARA DESINSTALAR É NECESSÁRIO USAR O DISABLE
	function pre_uninstall(&$errors) {
		$this->debugToFile('PRE_UNINSTALL');
        return true;
    }

	function uninstall(&$errors) {
		$this->debugToFile('UNINSTALL');
		// $installer = new TableInstaller();
		// $installer->runScript(SQL_SCRIPTS_DIR.UNINSTALL_SCRIPT);
		parent::uninstall($errors);
	}

	function delete(){
		$this->debugToFile('DELETEEEE');
		// parent::delete();
	}
}
