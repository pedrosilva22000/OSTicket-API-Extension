<?php

include 'ost-config.php';

// Estados possíveis dos tickets para cada base de dados,
// verificar valores na tabela ost_ticket_status

define('STATE_OPEN',1);
define('STATE_RESOLVE',2);
define('STATE_CLOSE',3);
define('STATE_ARCHIVED',4);
define('STATE_RESOLVED',5);

//variavel que gurada o nome da nova tabela que guarda api keys associadas a users
define('API_NEW_TABLE', 'api_key_nova');

?>