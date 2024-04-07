<?php

include_once INCLUDE_DIR.'api.tickets.php';

class TicketProjeto extends Ticket{
    function setTopicId($topicId){
        if ($topicId == $this->getTopicId())
            return false;

        $topic = null;
        if ($topicId && !($topic = Topic::lookup($topicId)))
            return false;

        $this->topic = $topic;
        return $this->save();
    }

    function setDueDate($duedate){
        if ($duedate == $this->getDueDate())
            return false;

        //verificar se o formato da data esta correto

        $this->duedate = $duedate;
        return $this->save();
    }

    function setPriorityId($priorityId){

        if ($priorityId == $this->getPriorityId())
            return false;

        $priority = null;
        if ($priorityId && !($priority = Priority::lookup($priorityId)))
            return false;

        $this->priority = $priority;
        return $this->save();
    }

    function debugToFile($erro){
        $file = INCLUDE_DIR."plugins/api/debug.txt";
        $text =  $erro."\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }
}