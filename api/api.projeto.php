<?php

include_once 'plugin.config.php';
include_once 'class.api.projeto.php';

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
        $ticket = Ticket::lookup(array('number' => $number));
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
            $ticket->setTeamId(0); //usar release() maybe
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

        //source
        //VERIFICAR SE A SOURCE INSERIDA NO JSON É POSSIVEL enum('Web', 'Email', 'Phone', 'API', 'Other')
        // if ($data['source']){
        //     $field = $ticket->getField("source");
        //     $parsedComments = "<p>".$comments."<p>";
        //     $post = array("","",$parsedComments); 
        //     $field->setValue($data['source']);
        //     $form = $field->getEditForm($post);
        //     if($form->isValid()){
        //         $ticket->updateField($form, $errors);
        //     }
        // }
        //source

        //topic
        // if ($data['topic'] && $data['topic'] != $ticket->getTopicId()){
        //     $field = $ticket->getField("topic");
        //     $parsedComments = "<p>".$comments."<p>";
        //     $post = array("","",$parsedComments);
        //     $field->setValue($data['topic']);
        //     $form = $field->getEditForm($post);
        //     if($form->isValid()){
        //         $ticket->updateField($form, $errors);
        //     }
        // }
        //topic

        //sla
        // if ($data['sla']){
        //     $field = $ticket->getField("sla");
        //     $parsedComments = "<p>".$comments."<p>";
        //     $post = array("","",$parsedComments);
        //     $field->setValue($data['sla']);
        //     $form = $field->getEditForm($post);
        //     if($form->isValid()){
        //         $ticket->updateField($form, $errors);
        //     }
        // }
        //sla

        if($data['dept'] && $data['dept'] != $ticket->getDeptId()){
            $this->transfer($data['dept'],$comments, $data['refer'], $ticket, $thisstaff);
        }

        //NAO FUNCIONA AINDA
        // // //priority
        // if ($data['priority']){
        //     $field = $ticket->getField("priority");
        //     $parsedComments = "<p>".$comments."<p>";
        //     $post = array("","",$parsedComments);
        //     $field->setValue(1);
        //     $form = $field->getEditForm($post);
        //     if($form->isValid()){
        //         $ticket->updateField($form, $errors);
        //     }
        // }
        // // //priority

        // if($data['duedate']){
        //     $duedate = $this->dateTimeMaker($data['duedate']);
        //     $ticket->duedate = $duedate;
        // } */

        return $ticket;
    }

    //talvez meter numa classe ticket extendida
    function transfer($deptId, $deptComments, $refer, $ticket, $alert = true){
        global $thisstaff, $cfg;

        $cdept = $ticket->getDept();
        $ticket->setDeptId($deptId);
        $dept = $ticket->getDept();

        // Make sure the new department allows assignment to the
        // currently assigned agent (if any)
        if (
            $ticket->isAssigned()
            && ($staff = $ticket->getStaff())
            && $dept->assignMembersOnly()
            && !$dept->isMember($staff)
        ) {
            $ticket->setStaffId(0);
        }

        if ($errors || !$ticket->save(true))
            return false;

        // Reopen ticket if closed
        if ($ticket->isClosed())
            $ticket->reopen();

        // Set SLA of the new department
        if (!$ticket->getSLAId() || $ticket->getSLA()->isTransient())
            if (($slaId = $ticket->getDept()->getSLAId()))
                $ticket->selectSLAId($slaId);

        // Log transfer event
        $ticket->logEvent('transferred', array('dept' => $dept->getName()));

        if (($referral = $ticket->hasReferral($dept, ObjectModel::OBJECT_TYPE_DEPT)))
            $referral->delete();

        // Post internal note if any
        $note = null;
        $comments = $deptComments;
        if ($comments) {
            $title = sprintf(
                __('%1$s transferred from %2$s to %3$s'),
                __('Ticket'),
                $cdept->getName(),
                $dept->getName()
            );

            $_errors = array();
            $note = $ticket->postNote(
                array('note' => $comments, 'title' => $title),
                $_errors,
                $thisstaff,
                false
            );
        }

        if ($refer && $cdept)
            $ticket->getThread()->refer($cdept);

        //Send out alerts if enabled AND requested
        if (!$alert || !$cfg->alertONTransfer() || !$dept->getNumMembersForAlerts())
            return true; //no alerts!!

        if (
            ($email = $dept->getAlertEmail())
            && ($tpl = $dept->getTemplate())
            && ($msg = $tpl->getTransferAlertMsgTemplate())
        ) {
            $msg = $ticket->replaceVars(
                $msg->asArray(),
                array('comments' => $note, 'staff' => $thisstaff)
            );
            // Recipients
            $recipients = array();
            // Assigned staff or team... if any
            if ($ticket->isAssigned() && $cfg->alertAssignedONTransfer()) {
                if ($ticket->getStaffId())
                    $recipients[] = $ticket->getStaff();
                elseif (
                    $ticket->getTeamId()
                    && ($team = $ticket->getTeam())
                    && ($members = $team->getMembersForAlerts())
                ) {
                    $recipients = array_merge($recipients, $members);
                }
            } elseif ($cfg->alertDeptMembersONTransfer() && !$ticket->isAssigned()) {
                // Only alerts dept members if the ticket is NOT assigned.
                foreach ($dept->getMembersForAlerts() as $M)
                    $recipients[] = $M;
            }

            // Always alert dept manager??
            if (
                $cfg->alertDeptManagerONTransfer()
                && $dept
                && ($manager = $dept->getManager())
            ) {
                $recipients[] = $manager;
            }
            $sentlist = $options = array();
            if ($note) {
                $options += array('thread' => $note);
            }
            foreach ($recipients as $k => $staff) {
                if (
                    !is_object($staff)
                    || !$staff->isAvailable()
                    || in_array($staff->getEmail(), $sentlist)
                ) {
                    continue;
                }
                $alert = $ticket->replaceVars($msg, array('recipient' => $staff));
                $email->sendAlert($staff, $alert['subj'], $alert['body'], null, $options);
                $sentlist[] = $staff->getEmail();
            }
        }

        return true;
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
