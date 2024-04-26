<?php
require_once(INCLUDE_DIR.'/class.forms.php');

class PluginConfigExtension extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-passthru');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'title' => new SectionBreakField(array(
                'label' => $__('OSTicket API Extension Configuration Option'),
            )),
            'username' => new TextboxField(array(
                'label' => __('Username'),
                'required' => true,
                'configuration' => array('size'=>40),
                'hint' => __('Admin username to add the first API key.'),
            )),
            'warning' => new SectionBreakField(array(
                'label' => $__('IF YOU DON\'T WANT TO SAVE THE TABLES, PLEASE UPDATE THE INSTANCE AFTER CREATING IT!!!
                This is due to an error in OSTicket BooleanFields.'),
            )),
            'save_info' => new BooleanField(array(
                'label' => __('Save New Tables Info'),
                'default' => true, //NAO SERVE DE NADA PORQUE O CONFIG NAO GUARDA A CHECKBOXES A PRIMEIRA VEZ SO 
                //DEPOIS DE ATUALIZAR A INSTANCIA, ISTO É UM ERRO DO OSTICKET EM SI, por isso esta sempre false por defualt
                'configuration' => array(
                    'desc' => __('Saves all values inside the tables added by this plugin after deactivating it, 
                    so when the plugin is activated again it has all the same data as before.')
                )
            )),
        );
    }


    function pre_save(&$config, &$errors) {
        global $msg;

        list($__, $_N) = self::translate();
        if (!$errors)
            $msg = $__('Configuration updated successfully');

        return true;
    }

}