<?php

include_once INCLUDE_DIR.'api.tickets.php';
include_once 'class.api.projeto.php';
include 'api.config.php';

class TicketApiControllerProjeto extends TicketApiController{

    //overrride da função já existente mas sem as verificações de ip
    function requireApiKey() {
        // Require a valid API key sent as X-API-Key HTTP header
        // see getApiKey method.
        if (!($key=$this->getKey()))
            return $this->exerr(401, __('Valid API key required'));
        elseif (!$key->isActive())
            return $this->exerr(401, __('API key not found/active'));

        return $key;
    }

    //overrride da função já existente mas sem as verificações de ip
    function getKey(){
        if (!$this->key
                && ($key=$this->getApiKey()))
            $this->key = ApiProjeto::lookupByKeyPRJ($key);

        return $this->key;
    }

    //TEMP
    //função para criar a tabela nova, é uma função de teste, depois iremos ter um ficheiro sql com toda a nova informação sobre as tabelas
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

    //TEMP
    //endpoint para adicionar novas tabelas e linhas enquanto não é criado um ficheiro sql para isso
    function createTables(){
        $this->createKeyTable();
    }

    //TEMP
    //funcao para fazer debug
    function debugToFile($erro){
        $file = INCLUDE_DIR."plugins/api/debug.txt";
        $text =  $erro."\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }
    
    //função chamada no endpoint/url requestApiKey
    function requestApiKey($format){
        //trata a informação do json
        $data = $this->getRequest($format);

        //validacao do admin (password corresponde ao username e se é admin) e se existe o staff que vai receber a nova api key
        $staff = Staff::lookup($data['staffUsername']);
        $admin = Staff::lookup($data['adminUsername']);
        if(!$staff || !$admin->check_passwd($data['adminPassword']) || !$admin->isAdmin()){
            return false;
        }
        
        //id do staff é passado para a variavel $data (no json é passado o nome do user, não o id)
        $data['idStaff'] = $staff->getId();

        //adiciona uma api key nova ao staff definido e retorna o id da api key se funcionar corretamente
        $id = ApiProjeto::add($data, $errors);
        //cria um objeto key com o id recebido
        $key = ApiProjeto::lookup($id);

        //se não existir o objeto key é porque algum erro aconteceu e não foi possivel criar a key nova, se existir respoinde com a api key nova
        $this->debugToFile($key->getKey());
        if ($key)
            $this->response(201, $key->getKey());
        else
            $this->exerr(500, _S("unknown error"));
    }

    //overrride da função já existente mas verifica a api key com a nova tabela
    function create($format) {
        $this->debugToFile('create');
        if (!($key=$this->requireApiKey()) || !$key->canCreateTickets())
            return $this->exerr(401, __('API key not authorized'));
        $this->debugToFile('key aceita');
        $ticket = null;
        $ticket = $this->createTicket($this->getRequest($format));
        $this->debugToFile('criou ticket');
        if ($ticket)
            $this->response(201, $ticket->getNumber());
        else
            $this->exerr(500, _S("unknown error"));

    }


    //função chamada no endpoint/url close, fecha um ticket, igual ao open mas verifica se pode fechar
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
        //variavel global que indica o staff que esta a fazer o pedido da api
        global $thisstaff;

        //cria objetos baseados na informação passada no json
        $number = $data['ticketNumber'];
        $ticket = Ticket::lookup(array('number'=>$number));      
        $staff = Staff::lookup($key->ht['id_staff']);
        
        $comments = $data['comments'];

        //vai buscar o id 3 ->fechar atraves do STATE_CLOSE, ver api.config.php
        $status = TicketStatus::lookup(STATE_CLOSE);
        
        $thisstaff = $staff;
        //altera o status do ticket
        if($ticket->setStatus(status:$status,comments:$comments)){
            return $ticket;
        }
        //adicionar tabela para saber a source que fechou talvez

    }

    //TODO
    function reopen($format){

    }

    //TODO
    function reopenTicket($data, $source = 'API') {
        
    }

    //TODO
    function suspend($format) {
        

    }
    //TODO
    /* SuspendTicket(data,api) */
    function suspendTicket($data, $source = 'API') {

    }
}

?>