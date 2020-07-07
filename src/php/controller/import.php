<?php
echo "Importing\n\n";

while ($f = fgets(STDIN)) {
    list($date, $time, $linetype_name, $rawdata) = explode(' ', $f, 4);
    $linetype = Linetype::load($linetype_name);
    $timestamp = "{$date} {$time}";

    $print = "{$linetype_name} ";
    echo str_pad($print, 30, '.');
    $data = $linetype->save(AUTH_TOKEN, json_decode($rawdata), 0, $timestamp);

    echo str_pad(' ' . count($data), 10, '.', STR_PAD_LEFT) . "\n";
}
echo "\n";

return [];
