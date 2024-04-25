<?php

/**
 * @file
 * Configuration file for the OSTicket API Extension plugin.
 */

/**
 * Plugin configuration array for the OSTicket API Extension.
 *
 * This array defines the settings and metadata for the OSTicket API Extension plugin.
 *
 * @return array An array containing plugin configuration details.
 */
return array(
    /**
     * Include path setup.
     *
     * This statement sets up the include path to include the 'include' directory
     * within the plugin directory.
     *
     * @var string
     */
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__file__) . '/include'),

    /**
     * Unique identifier for the plugin.
     *
     * This ID is used to identify the plugin internally.
     *
     * @var string
     */
    'id' => 'proposta15:projeto',

    /**
     * Version number of the plugin.
     *
     * The version number follows the format [major].[minor].
     *
     * @var string
     */
    'version' => '0.1',

    /**
     * Name of the plugin.
     *
     * The display name of the OSTicket API Extension plugin.
     *
     * @var string
     */
    'name' => 'OSTicket API Extension',

    /**
     * Author(s) of the plugin.
     *
     * The name(s) of the individual(s) or group responsible for developing the plugin.
     *
     * @var string
     */
    'author' => 'Grupo Proposta 15',

    /**
     * Description of the plugin.
     *
     * Provides an overview of the functionality added by the OSTicket API Extension.
     *
     * @var string
     */
    'description' => 'Adds extra functionality to OSTicket API.',

    /**
     * Plugin entry point.
     *
     * The file and class name that serves as the main entry point for the plugin.
     *
     * @var string
     */
    'plugin' => 'projeto.php:ProjetoPlugin'
);

?>
