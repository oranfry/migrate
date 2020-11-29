<?php
$token = Blends::login(USERNAME, PASSWORD, true);
$definitions = build_table_definitions($token);

foreach ($definitions as $table => $definition) {
    Db::succeed("drop table if exists `{$table}`");
    Db::succeed($definition);
}

return [
    'definitions' => $definitions,
];
