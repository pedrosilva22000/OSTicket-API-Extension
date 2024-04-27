<?php

include_once 'plugin.config.php';
include_once 'class.api.extension.php';

//WIP
include_once PRJ_PLUGIN_DIR.'class.ticket.extension.php';
// include 'debugger.php';

class TicketApiControllerExtension extends TicketApiController
{

    //overrride da função já existente mas sem as verificações de ip
    function requireApiKey()
    {
        // Require a valid API key sent as X-API-Key HTTP header
        // see getApiKey method.
        if (!($key = $this->getKey()))
            return $this->exerr(401, __('Valid API key required'));
        elseif (!$key->isActive())
            return $this->exerr(401, __('API key not found/active'));

        return $key;
    }

    //overrride da função já existente mas sem as verificações de ip
    function getKey()
    {
        if (
            !$this->key
            && ($key = $this->getApiKey())
        )
            $this->key = ApiExtension::lookupByKeyPRJ($key);

        return $this->key;
    }

    //função chamada no endpoint/url requestApiKey
    function requestApiKey($format)
    {
        //trata a informação do json
        $data = $this->getRequest($format);

        //validacao do admin (password corresponde ao username e se é admin) e se existe o staff que vai receber a nova api key
        $staff = Staff::lookup($data['staff']);
        $admin = Staff::lookup($data['admin']);
        if (!$staff || !$admin->check_passwd($data['adminPassword']) || !$admin->isAdmin()) {
            return false;
        }

        //id do staff é passado para a variavel $data (no json é passado o nome do user, não o id)
        $data['idStaff'] = $staff->getId();

        //adiciona uma api key nova ao staff definido e retorna o id da api key se funcionar corretamente
        $id = ApiExtension::add($data, $errors);
        //cria um objeto key com o id recebido
        $key = ApiExtension::lookup($id);

        //se não existir o objeto key é porque algum erro aconteceu e não foi possivel criar a key nova, se existir respoinde com a api key nova
        if ($key)
            $this->response(201, $key->getKey());
        else
            $this->exerr(500, _S("unknown error"));
    }

    //overrride da função já existente mas verifica a api key com a nova tabela
    function create($format)
    {

        if (!($key = $this->requireApiKey()) || !$key->canCreateTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        $data = $this->getRequest($format);
        $data['email'] = Staff::lookup($key->getStaffId())->getEmail();
        $ticket = $this->createTicket($data);

        if ($ticket)
            $this->response(201, "Ticket ".$ticket->getNumber()." Created");
        else
            $this->exerr(500, _S("unknown error"));
    }


    //função chamada no endpoint/url close, fecha um ticket, igual ao open mas verifica se pode fechar
    function close($format)
    {

        if (!($key = $this->requireApiKey()) || !$key->canCloseTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        $ticket = $this->closeTicket($this->getRequest($format), $key);

        if ($ticket)
            $this->response(201, "Ticket ".$ticket->getNumber()." Closed");
        else
            $this->exerr(500, _S("unknown error"));
    }

    function closeTicket($data, $key, $source = 'API')
    {
        //variavel global que indica o staff que esta a fazer o pedido da api
        global $thisstaff;

        //cria objetos baseados na informação passada no json
        $number = $data['ticketNumber'];
        $ticket = Ticket::lookup(array('number' => $number));
        $staff = Staff::lookup($key->ht['id_staff']);

        $comments = $data['comments'];

        //vai buscar o id 3 ->fechar atraves do STATE_CLOSE, ver api.config.php
        $status = TicketStatus::lookup(STATE_CLOSE);

        $thisstaff = $staff;
        //altera o status do ticket
        if ($ticket->setStatus(status: $status, comments: $comments)) {
            return $ticket;
        }
        //adicionar tabela para saber a source que fechou talvez

    }

    function reopen($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canReopenTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        $ticket = $this->reopenTicket($this->getRequest($format), $key);

        if ($ticket)
            $this->response(201, "Ticket ".$ticket->getNumber()." Reopened");
        else
            $this->exerr(500, _S("unknown error"));
    }

    function reopenTicket($data, $key, $source = 'API')
    {
        //variavel global que indica o staff que esta a fazer o pedido da api
        global $thisstaff;

        //cria objetos baseados na informação passada no json
        $number = $data['ticketNumber'];
        $ticket = Ticket::lookup(array('number' => $number));
        $staff = Staff::lookup($key->ht['id_staff']);

        $comments = $data['comments'];

        //vai buscar o id 3 ->fechar atraves do STATE_CLOSE, ver api.config.php
        $status = TicketStatus::lookup(STATE_OPEN);

        $thisstaff = $staff;
        //altera o status do ticket
        if ($ticket->setStatus(status: $status, comments: $comments)) {
            return $ticket;
        }
        //adicionar tabela para saber a source que fechou talvez
    }

    function edit($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $ticket = $this->editTicket($this->getRequest($format), $key);

        if ($ticket)
            $this->response(201, "Ticket ".$ticket->getNumber()." Updated");
        else
            $this->exerr(500, _S("unknown error"));
    }

    function debugToFile($erro)
    {
        $file = INCLUDE_DIR . "plugins/api/debug.txt";
        $text =  $erro . "\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }

    //WIP
    function editTicket($data, $key, $source = 'API')
    {
        $number = $data['ticketNumber'];
        $ticket = TicketExtension::lookup(array('number' => $number));

        $comments = $data['comments'];

        global $thisstaff, $cfg;
        $thisstaff = Staff::lookup($key->ht['id_staff']);
        $thisstaffuser = $thisstaff->getUserName();

        $msg = ''; //erros e assim

        //fields alterados
        $fields = array();
        //assignees
        $staffAssignee = null;
        $teamAssignee = null;

        if (!$data['staff'] && $data['staff'] != null && $ticket->getStaffId() != 0) {
            if ($ticket->setStaffId(0)) {
                $msg = $msg . 'Staff unassign successfully \n';
                $ticket->logEvent('assigned', array('staff' => 'unassign'), user: $thisstaffuser);
            } else {
                $msg = $msg . 'Unable to unassign staff \n';
            }
        }
        if ($data['staff']) {
            $staff = Staff::lookup($data['staff']);
            if ($ticket->getStaffId() != $staff->getId()) {
                $ticket->assignToStaff($staff, '', user: $thisstaffuser);
                $staffAssignee = $staff;
            }
        }

        if (!$data['team'] && $data['team'] != null && $ticket->getTeamId() != 0) {
            $ticket->setTeamId(0); //usar release() maybe
            $ticket->logEvent('assigned', array('team' => 'unassign'), user: $thisstaffuser);
        }
        if ($data['team']) {
            $team = Team::lookup($data['team']);
            if ($ticket->getTeamId() != $data['team']) {
                $ticket->assignToTeam($team, '', user: $thisstaffuser);
                $teamAssignee = $team;
            }
        }

        if ($data['user'] && $data['user'] != $ticket->getUserId()) {
            $user = User::lookup($data['user']);
            $ticket->changeOwner($user);
        }

        // //source
        // //VERIFICA SE A SOURCE INSERIDA NO JSON É POSSIVEL enum('Web', 'Email', 'Phone', 'API', 'Other')
        if ($data['source'] && in_array($data['source'], $ticket->getSources()) && $data['source'] != $ticket->getSource()){
            $this->simulatePost($ticket, 'source', $data);
            $fields[] = 'source';
        }
        // //source

        // //topic
        if ($data['topic'] && $data['topic'] != $ticket->getTopicId()){
            $this->simulatePost($ticket, 'topic', $data);
            $fields[] = 'topic';
        }
        // //topic

        // //sla
        if ($data['sla'] && $data['sla'] != $ticket->getSLAId()){
            $this->simulatePost($ticket, 'sla', $data);
            $fields[] = 'sla';
        }
        // //sla

        // //dept
        if($data['dept'] && $data['dept'] != $ticket->getDeptId()){
            $ticket->editFields('dept', $data['dept'], '', $data['refer']);
            $fields[] = 'dept';
        }
        // //dept

        // //priority
        if ($data['priority'] && $data['priority'] != $ticket->getPriorityId()){
            $ticket->editFields('priority', $data['priority'], '');
            $fields[] = 'priority';
        }
        // //priority

        // //duedate
        if($data['duedate'] && $this->isValidDateTimeFormat($data['duedate']) && $this->compareStringToDate($data['duedate'], $ticket->getDueDate())){
            $this->simulatePost($ticket, 'duedate', $data);
            $fields[] = 'duedate';
        }
        // //duedate

        //Adiciona SÓ UM comentario para todas as alteracoes
        //para se ter comentarios separados tem de se alterar os valores um de cada vez
        $notes = $ticket->addComments($comments, $fields, $staffAssignee, $teamAssignee);

        //alerta do departamento (se for alterado), tem de estar no fim porque usa os comentarios (notes)
        $alert = $data['alert'];
        if(in_array('dept', $fields) && !$alert || !$cfg->alertONTransfer() || !$ticket->getDept()->getNumMembersForAlerts()){
            $ticket->alerts($notes);
        }

        return $ticket;
    }

    function simulatePost($ticket, $fieldString, $data){
        $field = $ticket->getField($fieldString);
        $post = array("","","");
        $field->setValue($data[$fieldString]);
        $form = $field->getEditForm($post);
        if($form->isValid()){
            $ticket->updateField($form, $errors);
        }
    }

    //valida se a data inserida no json pelo utilizador está no formato correto
    function isValidDateTimeFormat($dateTimeString, $format = 'Y-m-d H:i:s') {
        $dateTimeObj = DateTime::createFromFormat($format, $dateTimeString);
        return $dateTimeObj && $dateTimeObj->format($format) === $dateTimeString;
    }

    //compara uma data em string no formato correto com uma data
    function compareStringToDate($stringDate, $date){
        $dateTimeString = DateTime::createFromFormat('Y-m-d H:i:s', $stringDate);
        $formattedDateTimeString = $dateTimeString->format('Y-m-d H:i:s');

        return ($formattedDateTimeString === $date);
    }

    function suspend($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canSuspendTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $ticket = $this->suspendTicket($this->getRequest($format), $key);

        if ($ticket){
            $response = ($ticket->getStatus() == "Open") ? " Unsuspended" : " Suspended";
            $this->response(201, "Ticket ".$ticket->getNumber().$response);
        } 
        else
            $this->exerr(500, _S("unknown error"));
    }

    /* SuspendTicket(data,api) */
    function suspendTicket($data, $key, $source = 'API')
    {
        $number = $data['ticketNumber'];
        $ticket = TicketExtension::lookup(array('number' => $number));
        $staff = Staff::lookup($key->getStaffId());

        $comments = $data['comments'];
        
        global $thisstaff;
        $thisstaff = $staff;
        //altera o status do ticket
        if ($ticket->setSuspend(comments: $comments)) {
            return $ticket;
        }
    }

    function showDeps(){

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));
        
        $res = ApiExtension::getDeps();

        $this->response(201, $res);

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
        
    }

    function showSLAs(){

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));
        
        $res = ApiExtension::getSLAS();

        $this->response(201, $res);

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));

    }

    function showTeams(){

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));
        
        $res = ApiExtension::getTeams();

        $this->response(201, $res);

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));

    }

    function showStaff(){

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));
        
        $res = ApiExtension::getStaff();

        $this->response(201, $res);

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));

    }

    function showPriority(){

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));
        
        $res = ApiExtension::getPiority();

        $this->response(201, $res);

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));

    }

    function showTopic(){

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));
        
        $res = ApiExtension::getTopic();

        $this->response(201, $res);

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));

    }

    function showSources(){

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));
        
        $res = ApiExtension::getSources();

        $this->response(201, $res);

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));

    }
}
