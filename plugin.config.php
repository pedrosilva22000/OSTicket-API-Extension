<?php
include 'ost-config.php';

define('STATE_OPEN',1);
define('STATE_RESOLVE',2);
define('STATE_CLOSE',3);
define('STATE_ARCHIVED',4);
define('STATE_RESOLVED',5);
define('STATE_SUSPENDED',6);

//nome da tabela que guarda as novas apis keys
define('API_NEW_TABLE', TABLE_PREFIX.'api_key_nova');
define('SUSPEND_NEW_TABLE', TABLE_PREFIX.'suspended_ticket');

//directories to SQL files
define('PRJ_PLUGIN_DIR', INCLUDE_DIR.'plugins/api/');
define('SQL_SCRIPTS_DIR', PRJ_PLUGIN_DIR.'sql/');
define('PRJ_API_DIR', PRJ_PLUGIN_DIR.'api/');

//nome do ficheiro sql que guarda a informacao das tabelas para reinstalar o plugin
define('SAVED_DATA_SQL', SQL_SCRIPTS_DIR.'savedData.sql');
//nomes dos ficheiros sql para instalar e apagar as tabelas e linhas
define('UNINSTALL_SCRIPT', SQL_SCRIPTS_DIR.'uninstallScript.sql');
define('INSTALL_SCRIPT', SQL_SCRIPTS_DIR.'scripts.sql');