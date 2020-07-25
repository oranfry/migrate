<?php
echo "Finding Collisions\n\n";

$lookup = [];
$collisions = [];
$from = @Config::get()->sequence_max ?? 1;
$newmax = 100000; // max mysql int 2147483647

foreach (array_keys(Config::get()->tables) as $table) {
    $lookup[$table] = [];
    $collisions[$table] = [];
}

for ($i = $from; $i < $newmax; $i++) {
    foreach (array_keys(Config::get()->tables) as $table) {
        $id = n2h($table, $i);

        if (isset($lookup[$table][$id])) {
            echo str_pad($table, 10, ' ') . ' ' . str_pad($i, 10, ' ', STR_PAD_LEFT) . ' <=> ' . str_pad($lookup[$id], 10);
            echo $id . ' <=> ' . n2h($table, $lookup[$table][$id]);
            echo "\n";
            $collisions[$table][] = $i;
        }

        $lookup[$table][$id] = $i;
    }
}

return [
    'max' => $i,
    'did' => $newmax - $from,
    'collisions' => $collisions,
];