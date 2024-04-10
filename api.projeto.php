<?php

include_once 'api.tickets.projeto.php';
include_once 'class.api.projeto.php';
include 'api.config.php';
include_once 'class.ticket.projeto.php';

// include 'debugger.php';

class TicketApiControllerProjeto extends TicketApiController
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
            $this->key = ApiProjeto::lookupByKeyPRJ($key);

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
        $id = ApiProjeto::add($data, $errors);
        //cria um objeto key com o id recebido
        $key = ApiProjeto::lookup($id);

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
        $ticket = $this->createTicket($this->getRequest($format));

        if ($ticket)
            $this->response(201, $ticket->getNumber());
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
            $this->response(201, $ticket->getNumber());
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
            $this->response(201, $ticket->getNumber());
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
            $this->response(201, $ticket->getNumber());
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
        $ticket = TicketProjeto::lookup(array('number' => $number));
        $comments = $data['comments'];

        global $thisstaff;
        $thisstaff = Staff::lookup($key->ht['id_staff']);
        $thisstaffuser = $thisstaff->getUserName();
        
        
        $msg = '';

        /* if (!$data['staff'] && $data['staff'] != null && $ticket->getStaffId() != 0) {
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
                $ticket->assignToStaff($staff, $comments, user: $thisstaffuser);
            }
        }

        if (!$data['team'] && $data['team'] != null && $ticket->getTeamId() != 0) {
            $ticket->setTeamId(0);
            $ticket->logEvent('assigned', array('team' => 'unassign'), user: $thisstaffuser);
        }
        if ($data['team']) {
            $team = Team::lookup($data['team']);
            if ($ticket->getTeamId() != $data['team']) {
                $ticket->assignToTeam($team, $comments, user: $thisstaffuser);
            }
        }

        if ($data['user']) {
            $user = User::lookup($data['user']);
            $ticket->changeOwner($user);
        } */

        $field = $ticket->getField("priority");
        
        $post = array("3","","<p>testeasdasd<p>");
        
      
        /* $field->setValue("High"); */

        $form = $field->getEditForm($post);
        /* Debugger::debugToFile($form->isValid()); */
        $ticket->updateField($form, $errors);





        /* //não guarda no historico
        if ($data['dept']) {
            if ($ticket->setDeptId($data['dept'])) {
                $ticket->logEvent('edited', array('dept' => $data['dept']), user: $thisstaffuser);
            }
        }

        if (!$data['sla'] && $data['sla'] != null && $ticket->getSLAId() != 0) {
            $ticket->setSLAId(0);
            $ticket->logEvent('edited', array('sla' => 'unassign'), user: $thisstaffuser);
        }
        if ($data['sla'] && $ticket->getSLAId() != $data['sla']) {
            $ticket->setSLAId($data['sla']);
            $ticket->logEvent('edited', array('sla' => $data['sla']), user: $thisstaffuser);
        }

        

        //AINDA NAO FUNCIONA

        if ($data['priority']) {
            if ($ticket->setPriorityId($data['priority'])) {
                $ticket->logEvent('edited', array('priority' => $data['priority']), user: $thisstaffuser);
                $this->debugToFile('priority');
            }
        }

        if ($data['topic']) {
            if ($ticket->setTopicId($data['topic'])) {
                $ticket->logEvent('edited', array('topic' => $data['topic']), user: $thisstaffuser);
                $this->debugToFile('topic');
            }
        }

        // if($data['duedate']){
        //     $duedate = $this->dateTimeMaker($data['duedate']);
        //     $ticket->duedate = $duedate;
        // } */

        return $ticket;
    }

    private function dateTimeMaker($dateString)
    {
        $pattern = "/^(0?[1-9]|1[0-2])\/(0?[1-9]|[12][0-9]|3[01])\/\d{2} \d{1,2}:\d{2} (AM|PM)$/";

        if (preg_match($pattern, $dateString)) {
            return false;
        }

        list($month, $day, $shortYear, $time) = sscanf($dateString, "%d/%d/%d %s");
        list($hour, $minute) = sscanf($time, "%d:%d");

        $fullYear = $shortYear < 50 ? $shortYear + 2000 : $shortYear + 1900;

        $dateTime = new DateTime();
        $dateTime->setDate($fullYear, $month, $day);
        $dateTime->setTime($hour, $minute);

        return $dateTime;
    }

    function suspend($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canSuspendTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $ticket = $this->suspendTicket($this->getRequest($format), $key);

        if ($ticket)
            $this->response(201, $ticket->getNumber());
        else
            $this->exerr(500, _S("unknown error"));
    }

    /* SuspendTicket(data,api) */
    function suspendTicket($data, $key, $source = 'API')
    {
        //TODO
    }
}
