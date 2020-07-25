<?php
echo "Finding Collisions\n\n";

$sequence = @Config::get()->sequence;

if (!$sequence) {
    error_log('Sequence not set up');
    die();
}

$lookup = [];
$collisions = [];
$from = $sequence->max ?? 1;
$process = 1000000; // max mysql int 2147483647

foreach (array_keys(Config::get()->tables) as $table) {
    $lookup[$table] = [];
}

for ($i = $from; $i < $from + $process; $i++) {
    foreach (array_keys(Config::get()->tables) as $table) {
        $id = n2h($table, $i);

        if (isset($lookup[$table][$id])) {
            echo str_pad($table, 10, ' ') . ' ' . str_pad($i, 10, ' ', STR_PAD_LEFT) . ' <=> ' . str_pad($lookup[$id], 10);
            echo $id . ' <=> ' . n2h($table, $lookup[$table][$id]);
            echo "\n";

            if (!isset($collisions[$table])) {
                $collisions[$table] = [];
            }

            $collisions[$table][] = $i;
        }

        $lookup[$table][$id] = $i;
    }
}

return [
    'max' => $i,
    'did' => $process,
    'collisions' => $collisions,
];