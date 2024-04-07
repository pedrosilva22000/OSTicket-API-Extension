<?php
require_once(INCLUDE_DIR.'/class.forms.php');

class ProjetoPluginConfig extends PluginConfig {

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
                'label' => $__('Topics to fulfill for future use of the API'),
            )),
            'username' => new TextboxField(array(
                'label' => __('Username'),
                'required' => true,
                'configuration' => array('size'=>40),
                'hint' => __('Admin username to add the first API key.'),
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