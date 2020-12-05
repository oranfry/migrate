<?php
namespace migrate;

class Router extends \Router {
    protected static $routes = [
        'CLI collisions \S+ \S+' => ['PAGE', 'MAX', 'TABLE'],
        'CLI collisions \S+' => ['PAGE', 'MAX', 'TABLE' => null],
        'CLI export|import|expunge-tokens|reset-schema \S+ \S+' => ['PAGE', 'USERNAME', 'PASSWORD'],
        'CLI save \S+ \S+ \S+' => ['PAGE', 'USERNAME', 'PASSWORD', 'LINETYPE'],
        'CLI h2n \S+ \S+' => ['PAGE', 'TABLE', 'H'],
        'CLI n2h \S+ \S+' => ['PAGE', 'TABLE', 'N'],
    ];
}
