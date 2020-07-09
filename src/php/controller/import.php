<?php
echo "Importing\n\n";

$token = Blends::login(USERNAME, PASSWORD);

while ($f = fgets(STDIN)) {
    list($date, $time, $linetype_name, $rawdata) = explode(' ', $f, 4);
    $linetype = Linetype::load($linetype_name);
    $timestamp = "{$date} {$time}";

    $print = "{$date} {$time} {$linetype_name} ";
    echo str_pad($print, 49, '.');
    $data = $linetype->save($token, json_decode($rawdata), 0, $timestamp);

    echo str_pad(' ' . count($data), 10, '.', STR_PAD_LEFT) . "\n";
}
echo "\n";

$token = Blends::logout($token);

return [];
