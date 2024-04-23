<?php

include_once INCLUDE_DIR . 'class.ticket.php';

//classe que dá override a algumas funções da class api para adaptar a nova tabela api key
class TicketProjeto extends Ticket
{

    function debugToFile($erro)
    {
        $file = INCLUDE_DIR . "plugins/api/debug.txt";
        $text =  $erro . "\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }

    function getChanges($new, $old)
    {
        return ($old != $new) ? array($old, $new) : false;
    }

    function editFields($field, $newValue, $comments, $refer = false, $alert = true)
    {
        global $thisstaff, $cfg;

        switch ($field) {
            case 'dept':
                $oldValue = $this->getDept();
                $this->setDeptId($newValue);
                $dept = $this->getDept();
                if (
                    $this->isAssigned()
                    && ($staff = $this->getStaff())
                    && $dept->assignMembersOnly()
                    && !$dept->isMember($staff)
                ) {
                    $this->setStaffId(0);
                }
                break;
            case 'priority':
                $oldValue = $this->getPriorityId();
                $this->setPriorityId($newValue);
                $oldValue = array(Priority::lookup($oldValue)->getDesc(), $oldValue);
                $newValue = array(Priority::lookup($newValue)->getDesc(), $newValue);
                break;
                //ADICIONAR O RESTO DOS CASES
            default:
        }

        //verifica se o novo valor é igual ao antigo
        if ($newValue == $oldValue) {
            return false;
        }
        //guarda as mudancas para utilizar no logevent
        $changes = $this->getChanges($newValue, $oldValue);

        if ($field == 'priority') {
            $changes['fields'] = array(22 => $changes);
        }

        //se nao existir oldvalue, existirem erros e nao der save retorna falso
        if (!$oldValue || $errors || !$this->save(true)) {
            return false;
        }

        // Post internal note if any
        if ($field == 'dept') {
            // Reopen ticket if closed
            if ($this->isClosed())
                $this->reopen();

            // Set SLA of the new department
            if (!$this->getSLAId() || $this->getSLA()->isTransient())
                if (($slaId = $this->getDept()->getSLAId()))
                    $this->selectSLAId($slaId);

            //DELETES OLD REFERALS
            if (($referral = $this->hasReferral($dept, ObjectModel::OBJECT_TYPE_DEPT)))
                $referral->delete();

            // Log transfer event
            $this->logEvent('transferred', array('dept' => $dept->getName()));

            $note = null;
            if ($comments) {
                $title = sprintf(
                    __('%1$s transferred from %2$s to %3$s'),
                    __('Ticket'),
                    $oldValue->getName(),
                    $dept->getName()
                );

                $_errors = array();
                $note = $this->postNote(
                    array('note' => $comments, 'title' => $title),
                    $_errors,
                    $thisstaff,
                    false
                );
            }

            //REFERS OLD DEPT IF REFER IS TRUE
            if ($refer && $oldValue)
                $this->getThread()->refer($oldValue);

            if (!$alert || !$cfg->alertONTransfer() || !$dept->getNumMembersForAlerts())
                return true;
            else {
                $this->alerts($dept, $note);
            }
        } else {
            $this->logEvent('edited', $changes);

            if ($comments) {
                $title = sprintf(__('%s updated'), __($field)); //CHECKAR ESTES COMMENTS
                $_errors = array();
                $this->postNote(
                    array('note' => $comments, 'title' => $title),
                    $_errors,
                    $thisstaff,
                    false
                );
            }
        }

        $this->lastupdate = SqlFunction::NOW();

        if ($field != 'dept')
            Signal::send('model.updated', $this);

        return true;
    }

    function alerts($dept, $note)
    {
        global $thisstaff, $cfg;

        if (
            ($email = $dept->getAlertEmail())
            && ($tpl = $dept->getTemplate())
            && ($msg = $tpl->getTransferAlertMsgTemplate())
        ) {
            $msg = $this->replaceVars(
                $msg->asArray(),
                array('comments' => $note, 'staff' => $thisstaff)
            );
            // Recipients
            $recipients = array();
            // Assigned staff or team... if any
            if ($this->isAssigned() && $cfg->alertAssignedONTransfer()) {
                if ($this->getStaffId())
                    $recipients[] = $this->getStaff();
                elseif (
                    $this->getTeamId()
                    && ($team = $this->getTeam())
                    && ($members = $team->getMembersForAlerts())
                ) {
                    $recipients = array_merge($recipients, $members);
                }
            } elseif ($cfg->alertDeptMembersONTransfer() && !$this->isAssigned()) {
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
                $alert = $this->replaceVars($msg, array('recipient' => $staff));
                $email->sendAlert($staff, $alert['subj'], $alert['body'], null, $options);
                $sentlist[] = $staff->getEmail();
            }
        }
    }

    //POR TESTAR
    function setPriorityId($priorityId)
    {
        if ($priorityId == $this->getPriorityId())
            return true;

        if ($priorityId && !($priority = Priority::lookup($priorityId)))
            return false;

        $this->getAnswer('priority')->setValue($priority);
        $this->getAnswer('priority')->save(true);

        $this->setFormEntryValueId($priorityId);

        return $this->save(true);
    }

    function setFormEntryValueId($newValue)
    {
        $sql = "UPDATE " . FORM_ANSWER_TABLE . " SET value_id = " . $newValue . " WHERE entry_id=(SELECT id FROM " . FORM_ENTRY_TABLE . " WHERE object_id = " . $this->getId() . ")";
        db_query($sql);
    }

    function setSuspend($comments = '', &$errors = array())
    {
        global $cfg, $thisstaff;

        if ($thisstaff && !($role = $this->getRole($thisstaff)))
            return false;

        $hadStatus = $this->getStatusId();
        $ecb = $refer = null;



        //tirar o suspend
        if ($this->getStatus() == 'Suspended') {



            $query_update = 'UPDATE ' . SUSPEND_NEW_TABLE . ' SET date_end_suspension=NOW()'
                . 'WHERE number_ticket=' . $this->getNumber()
                . ' AND id=(SELECT MAX(id) FROM ' . SUSPEND_NEW_TABLE . ' WHERE number_ticket =' . $this->getNumber() . ')';

            if (!db_query($query_update))
                return false;


            $query_time = 'SELECT TIMEDIFF(date_end_suspension,date_of_suspension) AS suspension_duration FROM ost_suspended_ticket WHERE number_ticket =' . $this->getNumber()
                . '  AND id=(SELECT MAX(id) FROM ' . SUSPEND_NEW_TABLE . ' WHERE number_ticket =' . $this->getNumber() . ')';

            $res = db_query($query_time);
            if (!$res)
                return false;




            //fazer a diferença e dps meter os valores

            $initialDatetime = new DateTime($this->getSLADueDate(true));

            $durationToSubtract = db_result($res);

            list($hours, $minutes, $seconds) = explode(':', $durationToSubtract);
            $interval = new DateInterval("PT{$hours}H{$minutes}M{$seconds}S");
            $finalDatetime = $initialDatetime->add($interval);

            $finalDatetimeFormatted = $finalDatetime->format('Y-m-d H:i:s');

            $this->est_duedate = $finalDatetimeFormatted;
            // $this->duedate = $finalDatetimeFormatted;

            //limpar o overdue se exitir
            if ($this->isOverdue())
                $this->clearOverdue(false);


            $this->status = TicketStatus::lookup(STATE_OPEN);
            $status =  STATE_OPEN;

            //TIREI POR ENQUANTO PARA FICAR IGUAL AO DELES
            // $ecb = function ($t) {
            //     $t->logEvent('reopened', array('status' => STATE_OPEN));
            //     // Set new sla duedate if any
            //     $t->updateEstDueDate();
            // };
        }


        //meter o suspend
        elseif ($this->getStatus() == 'Open') {

            if ($errors) return false;

            $query = 'INSERT INTO ' . SUSPEND_NEW_TABLE . ' SET '
                . 'number_ticket=' . $this->getNumber()
                . ', date_of_suspension=NOW()';
            $query .= ', date_end_suspension=NULL';

            //definir o due date como null
            $this->est_duedate = Null;
            $this->duedate = Null;

            if (!db_query($query))
                return false;

            $this->status = TicketStatus::lookup(STATE_SUSPENDED);
            $status =  STATE_SUSPENDED;

            //TIREI POR ENQUANTO PARA FICAR IGUAL AO DELES
            // $ecb = function ($t) {
            //     $t->logEvent('suspended', array('status' => STATE_SUSPENDED));
            //     // Set new sla duedate if any
            //     $t->updateEstDueDate();
            // };
        }

        if (!$this->save(true))
            return false;

        // Refer thread to previously assigned or closing agent
        if ($refer && $cfg->autoReferTicketsOnClose())
            $this->getThread()->refer($refer);

        // Log status change b4 reload — if currently has a status. (On new
        // ticket, the ticket is opened and thereafter the status is set to
        // the requested status).
        if ($hadStatus) {
            $alert = false;
            if ($comments = ThreadEntryBody::clean($comments)) {
                // Send out alerts if comments are included
                $alert = true;
                $this->logNote(__('Status Changed'), $comments, $thisstaff, $alert);
            }
        }
        // Log events via callback
        if ($ecb)
            $ecb($this);
        elseif ($hadStatus)
            // Don't log the initial status change
            $this->logEvent('edited', array('status' => $status));

        return true;
    }

    static function getSources()
    {
        return Ticket::$sources;
    }
}
