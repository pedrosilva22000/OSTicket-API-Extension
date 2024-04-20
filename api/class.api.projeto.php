<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'plugin.config.php';
include_once PRJ_PLUGIN_DIR.'class.ticket.projeto.php';

//classe que dá override a algumas funções da class api para adaptar a nova tabela api key
class ApiProjeto extends API{

    function __construct($id) {
        $this->id = 0;
        $this->load($id);
    }

    function load($id=0) {

        if(!$id && !($id=$this->getId()))
            return false;

        $sql='SELECT * FROM '.API_NEW_TABLE.' WHERE id='.db_input($id);
        if(!($res=db_query($sql)) || !db_num_rows($res))
            return false;

        $this->ht = db_fetch_array($res);
        $this->id = $this->ht['id'];

        return true;
    }

     /** Static functions **/
   static function getDeps(){

        $sql='SELECT id, signature AS NomeDepartamento FROM ' . DEPT_TABLE;

        if (($res=db_query($sql))) 
        {
            while ($row = $res -> fetch_row()) {
              printf ("%s - %s\n", $row[0], $row[1]);
            }

            $res -> free_result();
          }
   }

    static function getSLAS(){

        $sql='SELECT id, name AS SLA FROM ' . SLA_TABLE;

        if (($res=db_query($sql))) 
        {
            while ($row = $res -> fetch_row()) {
            printf ("%s - %s\n", $row[0], $row[1]);
            }

            $res -> free_result();
        }
    }

    static function getTeams(){

        $sql='SELECT team_id, name, notes FROM ' . TEAM_TABLE;

        if (($res=db_query($sql))) 
        {
            while ($row = $res -> fetch_row()) {
            printf ("%s - %s (%s)\n", $row[0], $row[1], $row[2]);
            }

            $res -> free_result();
        }
    }

    static function getStaff(){

        $sql='SELECT staff_id, firstname, lastname, email FROM ' . STAFF_TABLE;

        if (($res=db_query($sql))) 
        {
            while ($row = $res -> fetch_row()) {
            printf ("%s - %s %s (%s)\n", $row[0], $row[1], $row[2], $row[3]);
            }

            $res -> free_result();
        }
    }
    
    static function getPiority(){
        $sql='SELECT priority_id, priority_desc FROM ' . TICKET_PRIORITY_TABLE;

        if (($res=db_query($sql))) 
        {
            while ($row = $res -> fetch_row()) {
            printf ("%s - %s\n", $row[0], $row[1]);
            }

            $res -> free_result();
        }
    }

    static function getTopic(){
        $sql='SELECT topic_id, topic, notes FROM ' . TOPIC_TABLE;

        if (($res=db_query($sql))) 
        {
            while ($row = $res -> fetch_row()) {
            printf ("%s - %s (%s)\n", $row[0], $row[1], $row[2]);
            }

            $res -> free_result();
        }
    }

    static function getSources(){
        //$sources = SOURCES;
        $sources = TicketProjeto::getSources();

        foreach ($sources as $item) {
            printf("%s\n",$item);
        }
    }

    static function lookupByKeyPRJ($key) {
        return self::lookup(self::getIdByKeyPRJ($key));
    }

    static function getIdByKeyPRJ($key) {

        $sql='SELECT id FROM '.API_NEW_TABLE.' WHERE apikey='.db_input($key);

        if(($res=db_query($sql)) && db_num_rows($res))
            list($id) = db_fetch_row($res);

        return $id;
    }

    static function lookup($id) {
        return ($id && is_numeric($id) && ($k= new ApiProjeto($id)) && $k->getId()==$id)?$k:null;
    }

    static function add($vars, &$errors) {
        return ApiProjeto::save(0, $vars, $errors);
    }

    static function save($id, $vars, &$errors) {
        if($errors) return false;

        $sql=' updated=NOW() '
            .',id_staff='.db_input($vars['idStaff'])
            .',isactive='.db_input($vars['isActive'])
            .',can_create_tickets='.db_input($vars['canCreateTickets'])
            .',can_close_tickets='.db_input($vars['canCloseTickets'])
            .',can_reopen_tickets='.db_input($vars['canReopenTickets'])
            .',can_edit_tickets='.db_input($vars['canEditTickets'])
            .',can_suspend_tickets='.db_input($vars['canSuspendTickets'])
            .',notes='.db_input(Format::sanitize($vars['notes']));

        if($id) {
            $sql='UPDATE '.API_NEW_TABLE.' SET '.$sql.' WHERE id='.db_input($id);
            if(db_query($sql))
                return true;

            $errors['err']=sprintf(__('Unable to update %s.'), __('this API key'))
               .' '.__('Internal error occurred');

        } else {
            //query que desativa api keys antigas do staff que está a receber uma key nova, se existirem
            $updateSql='UPDATE '.API_NEW_TABLE.' SET isactive = 0 WHERE id_staff='.db_input($vars['idStaff']);
            if(!db_query($updateSql))
                return false;

            $sql='INSERT INTO '.API_NEW_TABLE.' SET '.$sql
                .',created=NOW() '
                .',apikey='.db_input(strtoupper(md5(time().md5(Misc::randCode(16)))));

            
            if(db_query($sql) && ($id=db_insert_id()))
                return $id;

            $errors['err']=sprintf('%s %s',
                sprintf(__('Unable to add %s.'), __('this API key')),
                __('Correct any errors below and try again.'));
        }

        return false;
    }

    function canCloseTickets(){
        return ($this->ht['can_close_tickets']);
    }

    function canReopenTickets(){
        return ($this->ht['can_reopen_tickets']);
    }

    function canEditTickets(){
        return ($this->ht['can_edit_tickets']);
    }

    function canSuspendTickets(){
        return ($this->ht['can_suspend_tickets']);
    }   
}