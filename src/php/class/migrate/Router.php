<?php
namespace migrate;

class Router extends \Router {
    protected static $routes = [
        'CLI collisions' => ['PAGE'],
        'CLI export|import|import_translate_ids \S+ \S+' => ['PAGE', 'USERNAME', 'PASSWORD'],
    ];
}
