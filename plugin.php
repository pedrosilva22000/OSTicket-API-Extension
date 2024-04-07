<?php

set_include_path(get_include_path().PATH_SEPARATOR.dirname(__file__).'/include');
return array(
    'id' =>             'proposta15:projeto',
    'version' =>        '0.1',
    'name' =>           'Proposta 15',
    'author' =>         'Os três mosqueteiros',
    'description' =>    'Adds extra functionality to OSTicket API.',
    'plugin' =>         'projeto.php:ProjetoPlugin'
);

?>