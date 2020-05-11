<?php
namespace migrate;

class Router extends \Router {
    protected static $routes = [
        'CLI (export|import)' => ['PAGE'],
    ];
}
