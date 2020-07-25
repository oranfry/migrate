<?php
namespace migrate;

class Router extends \Router {
    protected static $routes = [
        'CLI export|import|import_translate_ids|collisions \S+ \S+' => ['PAGE', 'USERNAME', 'PASSWORD'],
    ];
}
