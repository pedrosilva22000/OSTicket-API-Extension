<?php

include_once INCLUDE_DIR.'api.tickets.php';
include_once 'class.api.projeto.php';
include 'api.config.php';

/* Adicionado */
class TicketApiControllerProjeto extends TicketApiController{

    function requireApiKey() {
        // Require a valid API key sent as X-API-Key HTTP header
        // see getApiKey method.
        if (!($key=$this->getKey()))
            return $this->exerr(401, __('Valid API key required'));
        elseif (!$key->isActive())
            return $this->exerr(401, __('API key not found/active'));

        return $key;
    }

    function getKey(){
        if (!$this->key
                && ($key=$this->getApiKey()))
            $this->key = ApiProjeto::lookupByKeyPRJ($key);

        return $this->key;
    }

    function createKeyTable() {
        $sql = "select count(*) from information_schema.tables where
            table_schema='information_schema' and table_name =
            'INNODB_FT_CONFIG'";
        $mysql56 = db_result(db_query($sql));

        $sql = "show status like 'wsrep_local_state'";
        $galera = db_result(db_query($sql));

        if ($galera && !$mysql56)
            throw new Exception('Galera cannot be used with MyISAM tables. Upgrade to MariaDB 10 / MySQL 5.6 is required');
        $engine = $galera ? 'InnodB' : ($mysql56 ? '' : 'MyISAM');
        if ($engine)
            $engine = 'ENGINE='.$engine;
            
        $sql = "CREATE TABLE IF NOT EXISTS ". TABLE_PREFIX.API_NEW_TABLE." (
            id INT AUTO_INCREMENT PRIMARY KEY,
            isactive TINYINT(1) NOT NULL,
            id_staff VARCHAR(255) NOT NULL,
            apikey VARCHAR(255) NOT NULL,
            can_create_tickets TINYINT(1) NOT NULL,
            can_close_tickets TINYINT(1) NOT NULL,
            can_suspend_tickets TINYINT(1) NOT NULL,
            notes TEXT,
            updated DATETIME,
            created DATETIME)
            $engine CHARSET=utf8";

        if (!db_query($sql))
            return false;

        $config = new MySqlSearchConfig();
        $config->set('reindex', 1);
        return true;
    }

    //endpoint para adicionar tabelas para testar
    function createTables(){
        $this->createKeyTable();
    }

    //funcao para fazer debug
    function debugToFile($erro){
        $file = "debug.txt";
        $text =  $erro."\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }
    
    function requestApiKey($format){
        $data = $this->getRequest($format);

        //validacao do staff
        $staff = Staff::lookup($data['staffUsername']);
        $admin = Staff::lookup($data['adminUsername']);
        if(!$staff || !$admin->check_passwd($data['adminPassword']) || !$staff->isAdmin()){
            return false;
        }
        
        $data['idStaff'] = $staff->getId();

        $id = ApiProjeto::add($data, $errors);
        $key = ApiProjeto::lookup($id);

        $this->debugToFile($key->getKey());
        if ($key)
            $this->response(201, $key->getKey());
        else
            $this->exerr(500, _S("unknown error"));
    }

    function create($format) {

        if (!($key=$this->requireApiKey()) || !$key->canCreateTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        $ticket = $this->createTicket($this->getRequest($format));
        
        if ($ticket)
            $this->response(201, $ticket->getNumber());
        else
            $this->exerr(500, _S("unknown error"));

    }



    function close($format) {
        
        if (!($key=$this->requireApiKey()) || !$key->canCloseTickets() )
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
            
        $ticket = $this->closeTicket($this->getRequest($format),$key);

        if ($ticket)
            $this->response(201, $ticket->getNumber());
        else
            $this->exerr(500, _S("unknown error"));
    }

    function closeTicket($data, $key, $source='API'){
        
        global $thisstaff;

        $number = $data['ticketNumber'];
        $ticket = Ticket::lookup(array('number'=>$number));      
        
        $staff = Staff::lookup($key->ht['id_staff']);
        
        // como fazer o comentário
        $comments = $data['comments'];

        //vai buscar o id 3 ->fechar
        $status = TicketStatus::lookup(STATE_CLOSE);
        
        $thisstaff = $staff;
        if($ticket->setStatus(status:$status,comments:$comments)){
            return $ticket;
        }
        //adicionar tabela para saber a source que fechou

    }

    function reopen($format){

    }

    function reopenTicket($data, $source = 'API') {
        
    }


    function suspend($format) {
        

    }

    /* SuspendTicket(data,api) */
    function suspendTicket($data, $source = 'API') {

    }

    /* Fim de Adicionamento */
}

?>