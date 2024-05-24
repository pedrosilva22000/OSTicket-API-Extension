<?php

/**
 * @file
 * TicketAPIControler class extension for the OSTicket API Extension plugin.
 */

include_once 'plugin.config.php';
include_once 'class.api.extension.php';
include_once PRJ_PLUGIN_DIR . 'class.ticket.extension.php';

/**
 * Class ApiExtension.
 *
 * This class extends the TicketApiController class to use our own endpoints.
 */
class TicketApiControllerExtension extends TicketApiController
{
    //FUNCAO TEMPORARIA DE DEBUG APAGAR DEPOIS
    function debugToFile($erro)
    {
        $file = PRJ_PLUGIN_DIR . "debug.txt";
        $text =  $erro . "\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }

    /**
     * Verifies if the API key sent as X-API-Key in the HTTP header is valid, exists and is active.
     * Overrides the function with the same name in TicketApiController but removes all IP verifications.
     * 
     * @return object ApiExtension.
     */
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

    /**
     * Gets the objecto of the API key sent as X-API-Key in the HTTP header is valid, exists and is active.
     * Overrides the function with the same name in TicketApiController but removes all IP verifications.
     * 
     * @return object ApiExtension.
     */
    function getKey()
    {
        if (
            !$this->key
            && ($key = $this->getApiKey())
        )
            $this->key = ApiExtension::lookupByKeyPRJ($key);

        return $this->key;
    }

    /**
     * Function executed when the endpoint/url requestApiKey/tickets is called.
     * 
     * Verifies if the staff specified exists, if the person making the request is an admin,
     * and if the password inserted matches the password of the person making the request.
     * 
     * Adds a new API key and deactivates the old one to the specified staff, 
     * if no staff is specified, the API key is added to the admin making the request.
     * 
     * Makes a response with the new API key.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function requestApiKey($format)
    {
        //trata a informação do json
        $data = $this->getRequest($format);

        //se nao existir $data['staff'] entao o staff é igual ao admin
        $data['staff'] ?
            ($staff = Staff::lookup($data['staff'])) && ($admin = Staff::lookup($data['admin'])) : ($staff = $admin = Staff::lookup($data['admin']));

        //validacao do admin (password corresponde ao username e se é admin) e se existe o staff que vai receber a nova api key
        if (!$staff || !$admin->isAdmin() || !$admin->check_passwd($data['adminPassword'])) {
            return false;
        }

        //id do staff é passado para a variavel $data (no json pode ser passado o nome ou no email do user, não o id)
        $data['idStaff'] = $staff->getId();

        //adiciona uma api key nova ao staff definido e retorna o id da api key se funcionar corretamente
        $id = ApiExtension::add($data, $errors);
        //cria um objeto key com o id recebido
        $key = ApiExtension::lookup($id);

        //se não existir o objeto key é porque algum erro aconteceu e não foi possivel criar a key nova, se existir respoinde com a api key nova
        if ($key)
            $this->response(201, _S('New key ') . $key->getKey() . _S(' added to ') . $staff->getName());
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url getApiKey/tickets is called.
     * 
     * Gets the API key of the user sent in the json.
     * 
     * Authenticates the user to make sure it is really him making the request
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function getUserApiKey($format)
    {
        $errors = '';

        //trata a informação do json
        $data = $this->getRequest($format);

        $staff = Staff::lookup($data['staff']);
        //validacao do staff
        if (!$staff || !$staff->check_passwd($data['password'])) {
            $errors = _S("Staff does not exist or password is incorrect");
        } else {
            //id do staff é passado para a variavel $data (no json pode ser passado o nome ou no email do user, não o id)
            $staffId = $staff->getId();

            //adiciona uma api key nova ao staff definido e retorna o id da api key se funcionar corretamente
            if (!($id = ApiExtension::getKeyIdForUser($staffId))) {
                $errors = $staff->getName() . _S(" has no key");
            }

            $key = ApiExtension::lookup($id);
        }

        //se não existir o objeto key é porque algum erro aconteceu e não foi possivel criar a key nova, se existir respoinde com a api key nova
        if ($key)
            $this->response(201, $staff->getName() . _S('\'s key is: ') . $key->getKey());
        else
            $this->exerr(500, $errors);
    }

    /**
     * Function executed when the endpoint/url create/tickets is called.
     * Overrides the function with the same name in TicketApiController to use our own API keys.
     * 
     * Verifies if the key is valid and has permission to create tickets.
     * 
     * Creates a new ticket with the values inserted.
     * 
     * Makes a response with the created ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function create($format)
    {

        if (!($key = $this->requireApiKey()) || !$key->canCreateTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $data = $this->getRequest($format);
        $data['name'] = Staff::lookup($key->getStaffId())->username;
        $data['email'] = Staff::lookup($key->getStaffId())->getEmail();
        $ticket = $this->createTicket($data);

        if ($ticket)
            $this->response(201, _S("Ticket ") . $ticket->getNumber() . _S(" Created"));
        else
            $this->exerr(500, _S("unknown error"));
    }


    /**
     * Function executed when the endpoint/url close/tickets is called.
     * 
     * Verifies if the key is valid and has permission to close tickets.
     * 
     * Closes the specified ticket.
     * 
     * Makes a response with the closed ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function close($format)
    {

        if (!($key = $this->requireApiKey()) || !$key->canCloseTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        $ticket = $this->closeTicket($this->getRequest($format), $key);

        if ($ticket)
            $this->response(201, "Ticket " . $ticket->getNumber() . " Closed");
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Changes the status of a ticket to closed, with the specified comments.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $key ApiExtension, the key used to call this endpoint.
     * @param string $source = 'API'.
     * 
     * @return mixed (Ticket or boolean) the ticket that was closed, if ticket was not closed returns false.
     */
    function closeTicket($data, $key, $source = 'API') //source nao esta a fazer nada ja nao me lembro porque
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

        return false;
    }

    /**
     * Function executed when the endpoint/url reopen/tickets is called.
     * 
     * Verifies if the key is valid and has permission to reopen tickets.
     * 
     * Reopens the specified ticket.
     * 
     * Makes a response with the reopened ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function reopen($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canReopenTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        $ticket = $this->reopenTicket($this->getRequest($format), $key);

        if ($ticket)
            $this->response(201, "Ticket " . $ticket->getNumber() . " Reopened");
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Changes the status of a ticket to open, with the specified comments.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $key ApiExtension, the key used to call this endpoint.
     * @param string $source = 'API'.
     * 
     * @return mixed (Ticket or boolean) the ticket that was reopened, if ticket was not reopened returns false.
     */
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

        return false;
    }

    /**
     * Function executed when the endpoint/url edit/tickets is called.
     * 
     * Verifies if the key is valid and has permission to edit tickets.
     * 
     * Edits the specified ticket.
     * 
     * Makes a response with the edited ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function edit($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $msg = '';
        $ticket = $this->editTicket($this->getRequest($format), $key, $msg);

        if ($ticket)
            $this->response(201, $msg);
        else
            $this->exerr(500, _S($msg));
    }

    /**
     * Edits the specified fields of a ticket, with the specified comments.
     * Adds ONLY ONE comment per API call.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $key ApiExtension, the key used to call this endpoint.
     * @param string $source = 'API'.
     * 
     * @return mixed (Ticket or boolean) the ticket that was edited, if ticket was not edited returns false.
     */
    function editTicket($data, $key, &$msg, $source = 'API')
    {
        $number = $data['ticketNumber'];
        if (!($ticket = TicketExtension::lookup(array('number' => $number)))) {
            $msg = 'Ticket ' . $number . ' does not exist \n';
            return false;
        }

        $comments = $data['comments'];

        global $thisstaff, $cfg;
        $thisstaff = Staff::lookup($key->ht['id_staff']);
        $thisstaffuser = $thisstaff->getUserName();

        //fields alterados
        $fields = array();
        //assignees
        $staffAssignee = null;
        $teamAssignee = null;

        //staff
        // if (!$data['staff'] && $data['staff'] != null && $ticket->getStaffId() != 0) {
        //     if ($ticket->setStaffId(0)) {
        //         $msg = $msg . 'Staff unassign successfully \n';
        //         $ticket->logEvent('assigned', array('staff' => 'unassign'), user: $thisstaffuser);
        //     } else {
        //         $msg = $msg . 'Unable to unassign staff \n';
        //     }
        // }
        if ($data['staff']) {
            if ($staff = Staff::lookup($data['staff'])) {
                if ($ticket->getStaffId() != $staff->getId()) {
                    $oldStaff = $ticket->getStaff();

                    $ticket->assignToStaff($staff, '', user: $thisstaffuser);
                    $staffAssignee = $staff;
                    $fields[] = 'staff';

                    $msg .= 'Staff Updated. ' . $oldStaff->getName() . ' changed to ' . $staff->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update Staff. ' . $staff->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Staff. ' . $data['staff'] . " does not exist\n";
            }
        }
        //staff

        //team
        // if (!$data['team'] && $data['team'] != null && $ticket->getTeamId() != 0) {
        //     $ticket->setTeamId(0); //usar release() maybe
        //     $ticket->logEvent('assigned', array('team' => 'unassign'), user: $thisstaffuser);
        // }
        if ($data['team']) {
            if ($team = Team::lookup($data['team'])) {
                if ($ticket->getTeamId() != $data['team']) {
                    $oldTeam = $ticket->getTeam();

                    $ticket->assignToTeam($team, '', user: $thisstaffuser);
                    $teamAssignee = $team;
                    $fields[] = 'team';

                    $msg .= 'Team Updated. ' . $oldTeam->getName() . ' changed to ' . $team->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update Team. ' . $team->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Team. ' . $data['team'] . " does not exist\n";
            }
        }

        //team

        //user
        if ($data['user']) {
            if ($user = User::lookup($data['user'])) {
                if ($data['user'] != $ticket->getUserId()) {
                    $oldUser = $ticket->getUser();
                    $ticket->changeOwner($user);

                    $msg .= 'User Updated. ' . $oldUser->getName() . ' changed to ' . $user->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update User. ' . $user->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update User. ' . $data['user'] . " does not exist\n";
            }
        }
        //user

        // //source
        // //VERIFICA SE A SOURCE INSERIDA NO JSON É POSSIVEL enum('Web', 'Email', 'Phone', 'API', 'Other')
        if ($data['source']) {
            if (in_array($data['source'], $ticket->getSources())) {
                if ($data['source'] != $ticket->getSource()) {
                    $oldSource = $ticket->getSource();
                    $this->simulatePost($ticket, 'source', $data);
                    $fields[] = 'source';
                    $msg .= 'Source Updated. ' . $oldSource . ' changed to ' . $data['source'] . "\n";
                } else {
                    $msg .= 'Failed to Update Source. ' . $data['source'] . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Source. ' . $data['source'] . " is not a valid source\n";
            }
        }
        // //source

        // //topic
        if ($data['topic']) {
            if ($topic = Topic::lookup($data['topic'])) {
                if ($data['topic'] != $ticket->getTopicId()) {
                    $oldTopic = $ticket->getTopic();
                    $this->simulatePost($ticket, 'topic', $data);
                    $fields[] = 'topic';
                    $msg .= 'Topic Updated. ' . $oldTopic->getName() . ' changed to ' . $topic->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update Topic. ' . $topic->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Topic. ' . $data['topic'] . " does not exist\n";
            }
        }
        // //topic

        // //sla
        if ($data['sla']) {
            if ($sla = SLA::lookup($data['sla'])) {
                if ($data['sla'] != $ticket->getSLAId()) {
                    $oldSLA = $ticket->getSLA();
                    $this->simulatePost($ticket, 'sla', $data);
                    $fields[] = 'sla';
                    $msg .= 'SLA Updated. ' . $oldSLA->getName() . ' changed to ' . $sla->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update SLA. ' . $sla->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update SLA. ' . $data['sla'] . " does not exist\n";
            }
        }
        // //sla

        // //dept
        if ($data['dept']) {
            if ($dept = Dept::lookup($data['dept'])) {
                if ($data['dept'] != $ticket->getDeptId()) {
                    $oldDept = $ticket->getDept();
                    $ticket->editFields('dept', $data['dept'], $data['refer']);
                    $fields[] = 'dept';
                    $msg .= 'Dept Updated. ' . $oldDept->getName() . ' changed to ' . $dept->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update Dept. ' . $dept->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Dept. ' . $data['dept'] . " does not exist\n";
            }
        }
        // //dept

        // //priority
        if ($data['priority']) {
            if ($priority = Priority::lookup($data['priority'])) {
                if ($data['priority'] != $ticket->getPriorityId()) {
                    $oldPriority = $ticket->getPriority();
                    $ticket->editFields('priority', $data['priority'], '');
                    $fields[] = 'priority';
                    $msg .= 'Priority Updated. ' . $oldPriority->getDesc() . ' changed to ' . $priority->getDesc() . "\n";
                } else {
                    $msg .= 'Failed to Update Priority. ' . $priority->getDesc() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Priority. ' . $data['priority'] . " does not exist\n";
            }
        }
        // //priority

        // //duedate
        if ($data['duedate']) {
            if ($this->isValidDateTimeFormat($data['duedate'])) {
                if ($data['duedate'] != $ticket->getDueDate()) {
                    $this->debugToFile($ticket->getDueDate());
                    if (!($oldDueDate = $ticket->getDueDate())) {
                        $oldDueDate = 'Empty Due Date';
                    }
                    $this->simulatePost($ticket, 'duedate', $data);
                    $fields[] = 'duedate';
                    $msg .= 'Due Date Updated. ' . $oldDueDate . ' changed to ' . $data['duedate'] . "\n";
                } else {
                    $msg .= 'Failed to Update Due Date. ' . $data['duedate'] . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Due Date. ' . $data['duedate'] . " is not a valid date, date should have format 'Y-m-d H:i:s'\n";
            }
        }
        // //duedate

        //Adiciona SÓ UM comentario para todas as alteracoes
        //para se ter comentarios separados tem de se alterar os valores um de cada vez
        if (!$comments || !empty($fields))
            $notes = $ticket->addComments($comments, $fields, $staffAssignee, $teamAssignee);

        //alerta do departamento (se for alterado), tem de estar no fim porque usa os comentarios (notes)
        $alert = $data['alert'];
        if (in_array('dept', $fields) && !$alert || !$cfg->alertONTransfer() || !$ticket->getDept()->getNumMembersForAlerts()) {
            $ticket->alerts($notes);
        }

        $msg .= empty($fields) ? "Ticket " . $number . " Failed to Update\n" : "Ticket " . $number . " Updated\n";

        //se nenhum valor foi alterado return false
        return !empty($fields) ? $ticket : false;
    }

    /**
     * Simulates the POST sent in the OSTicket interface when editing a field.
     * 
     * @param object $ticket Ticket, ticket of the field being edited.
     * @param string $fieldString A string with the name of the field being edited.
     * @param array $data The new values of the fields sent in the body of the HTTP.
     */
    function simulatePost($ticket, $fieldString, $data)
    {
        $field = $ticket->getField($fieldString);
        $post = array("", "", "");
        $field->setValue($data[$fieldString]);
        $form = $field->getEditForm($post);
        if ($form->isValid()) {
            $ticket->updateField($form, $errors);
        }
    }

    /**
     * Validates if the date sent in the HTTP body is valid.
     * 
     * @param string $dateTimeString Date sent in the HTTP body.
     * @param string $format Correct format the date sent should have.
     * 
     * @return boolean true if the date sent is in the correct format, false if not
     */
    function isValidDateTimeFormat($dateTimeString, $format = 'Y-m-d H:i:s')
    {
        $pattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        if (!preg_match($pattern, $dateTimeString)) {
            return false;
        }
        $dateTimeObj = DateTime::createFromFormat($format, $dateTimeString);
        return $dateTimeObj && $dateTimeObj->format($format) == $dateTimeString;
    }

    /**
     * Function executed when the endpoint/url suspend/tickets is called.
     * 
     * Verifies if the key is valid and has permission to suspend tickets.
     * 
     * Suspends the specified ticket.
     * 
     * Makes a response with the suspended ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function suspend($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canSuspendTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $ticket = $this->suspendTicket($this->getRequest($format), $key, 'Open');

        if ($ticket)
            $this->response(201, "Ticket " . $ticket->getNumber() . " Suspendeded");
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url unSuspend/tickets is called.
     * 
     * Verifies if the key is valid and has permission to suspend tickets.
     * 
     * Unsuspends the specified ticket.
     * 
     * Makes a response with the unsuspended ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function unSuspend($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canSuspendTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $ticket = $this->suspendTicket($this->getRequest($format), $key, 'Suspended');

        if ($ticket)
            $this->response(201, "Ticket " . $ticket->getNumber() . " Unsuspended");
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Changes the status of a ticket to suspend or open, depending on the previous status, with the specified comments.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $key ApiExtension, the key used to call this endpoint.
     * @param string $source = 'API'.
     * 
     * @return mixed (Ticket or boolean) the ticket that was suspended/unsuspended, if ticket was not suspended/unsuspended returns false.
     */
    function suspendTicket($data, $key, $status, $source = 'API')
    {
        $number = $data['ticketNumber'];
        $ticket = TicketExtension::lookup(array('number' => $number));
        $staff = Staff::lookup($key->getStaffId());

        $comments = $data['comments'];

        global $thisstaff;
        $thisstaff = $staff;
        //altera o status do ticket
        if ($ticket->setSuspend($status, comments: $comments)) {
            return $ticket;
        }
        return false;
    }

    /**
     * Function executed when the endpoint/url departments is called.
     * 
     * Gets all departments.
     * 
     * Makes a response with all departments.
     * If there are errors response has code 500 and specified error.
     */
    function showDeps()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getDeps();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url slas is called.
     * 
     * Gets all slas.
     * 
     * Makes a response with all slas.
     * If there are errors response has code 500 and specified error.
     */
    function showSLAs()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getSLAS();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url teams is called.
     * 
     * Gets all teams.
     * 
     * Makes a response with all teams.
     * If there are errors response has code 500 and specified error.
     */
    function showTeams()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getTeams();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url staff is called.
     * 
     * Gets all staff.
     * 
     * Makes a response with all staff.
     * If there are errors response has code 500 and specified error.
     */
    function showStaff()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getStaff();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url users is called.
     * 
     * Gets all users.
     * 
     * Makes a response with all users.
     * If there are errors response has code 500 and specified error.
     */
    function showUsers()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getUsers();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url priorities is called.
     * 
     * Gets all priorities.
     * 
     * Makes a response with all priorities.
     * If there are errors response has code 500 and specified error.
     */
    function showPriority()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getPiority();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url topics is called.
     * 
     * Gets all topics.
     * 
     * Makes a response with all topics.
     * If there are errors response has code 500 and specified error.
     */
    function showTopic()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getTopic();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url sources is called.
     * 
     * Gets all sources.
     * 
     * Makes a response with all sources.
     * If there are errors response has code 500 and specified error.
     */
    function showSources()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getSources();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }
}
