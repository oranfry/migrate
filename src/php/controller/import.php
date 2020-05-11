<?php
$output = '';

while ($f = fgets(STDIN)) {
    list($linetype_name, $rawdata) = explode(' ', $f, 2);
    $linetype = Linetype::load($linetype_name);

    $data = $linetype->save(json_decode($rawdata));
    $output .= "Imported " . count($data) . " x {$linetype_name}\n";
}
return [
    'output' => $output,
];
