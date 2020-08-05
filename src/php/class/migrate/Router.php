<?php
namespace migrate;

class Router extends \Router {
    protected static $routes = [
        'CLI collisions \S+' => ['PAGE', 'MAX', 'TABLE' => null],
        'CLI collisions \S+ \S+' => ['PAGE', 'MAX', 'TABLE'],
        'CLI n2h \S+ \S+' => ['PAGE', 'TABLE', 'N'],
        'CLI h2n \S+ \S+' => ['PAGE', 'TABLE', 'H'],
        'CLI export|import|import_translate_ids \S+ \S+' => ['PAGE', 'USERNAME', 'PASSWORD'],
    ];
}
