<?php
namespace migrate;

class Router extends \Router {
    protected static $routes = [
        'CLI collisions \S+' => ['PAGE', 'MAX'],
        'CLI n2h \S+ \S+' => ['PAGE', 'TABLE', 'N'],
        'CLI export|import|import_translate_ids \S+ \S+' => ['PAGE', 'USERNAME', 'PASSWORD'],
    ];
}
