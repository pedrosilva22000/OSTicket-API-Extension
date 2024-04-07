<?php

include_once INCLUDE_DIR.'class.api.php';

//classe que dá override a algumas funções da class api para adaptar a nova tabela api key
class ApiProjeto extends API{

    function __construct($id) {
        $this->id = 0;
        $this->load($id);
    }

    function load($id=0) {

        if(!$id && !($id=$this->getId()))
            return false;

        $sql='SELECT * FROM '.TABLE_PREFIX.API_NEW_TABLE.' WHERE id='.db_input($id);
        if(!($res=db_query($sql)) || !db_num_rows($res))
            return false;

        $this->ht = db_fetch_array($res);
        $this->id = $this->ht['id'];

        return true;
    }

     /** Static functions **/
   

    static function lookupByKeyPRJ($key) {
        return self::lookup(self::getIdByKeyPRJ($key));
    }

    static function getIdByKeyPRJ($key) {

        $sql='SELECT id FROM '.TABLE_PREFIX.API_NEW_TABLE.' WHERE apikey='.db_input($key);

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
            $sql='UPDATE '.TABLE_PREFIX.API_NEW_TABLE.' SET '.$sql.' WHERE id='.db_input($id);
            if(db_query($sql))
                return true;

            $errors['err']=sprintf(__('Unable to update %s.'), __('this API key'))
               .' '.__('Internal error occurred');

        } else {
            //query que desativa api keys antigas do staff que está a receber uma key nova, se existirem
            $updateSql='UPDATE '.TABLE_PREFIX.API_NEW_TABLE.' SET isactive = 0 WHERE id_staff='.db_input($vars['idStaff']);
            if(!db_query($updateSql))
                return false;

            $sql='INSERT INTO '.TABLE_PREFIX.API_NEW_TABLE.' SET '.$sql
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