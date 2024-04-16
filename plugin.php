<?php

set_include_path(get_include_path().PATH_SEPARATOR.dirname(__file__).'/include');
return array(
    'id' =>             'proposta15:projeto',
    'version' =>        '0.1',
    'name' =>           'OSTicket API Extension',
    'author' =>         'Grupo Proposta 15',
    'description' =>    'Adds extra functionality to OSTicket API.',
    'plugin' =>         'projeto.php:ProjetoPlugin'
);