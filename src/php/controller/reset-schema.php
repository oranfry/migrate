<?php
$token = Blends::login(USERNAME, PASSWORD, true);
$schemata = [];
$defs = [
    'text' => "varchar(255) null",
    'numberdp' => 'decimal (32, 16) null',
    'number' => 'integer null',
    'multiline' => "text",
    'date' => "date",
    'timestamp' => "timestamp",
];
$examples = [
    'text' => "'hello'",
    'number' => '1.23',
    'multiline' => "'multiline\ntext'",
    'date' => "'2020-01-01'",
    'timestamp' => "'2020-01-01 01:02:03'",
];

foreach (BlendsConfig::get()->linetypes as $linetypeName => $linetype_config) {
    $linetype = Linetype::load($token, $linetypeName);
    $table = $linetype->table;
    $db_table = BlendsConfig::get()->tables[$table];

    if (!isset($schemata[$db_table])) {
        $schemata[$db_table] = [
            'id' => (object) ['def' => 'char(10) not null', 'primary' => true],
            'user' => (object) ['def' => 'varchar(255) default null'],
            'created' => (object) ['def' => 'timestamp not null default current_timestamp'],
        ];
    }

    $table_expression = 'select ' . implode(', ', array_map(function($uf){
        $field_full = str_replace('{t}.', '', $uf);
        return "null {$field_full}";
    }, array_keys($linetype->unfuse_fields)));

    foreach ($linetype->fields as $field) {
        $key = $field->type . (@$field->dp ? 'dp' : '');

        if (
            !preg_match('/^{t}\.([a-z]+)$/', @$field->fuse, $groups)
            ||
            isset($schemata[$db_table][$groups[1]])
            ||
            !isset($defs[$key])
        ) {
            continue;
        }

        $schemata[$db_table][$groups[1]] = (object) ['def' => $defs[$key]];
    }

    foreach ($linetype->unfuse_fields as $field => $expression) {
        $field_full = str_replace('{t}.', '', $field);

        if (isset($schemata[$db_table][$field_full])) {
            continue;
        }

        $expression_full = str_replace('{t}', 't', $expression);
        preg_match_all('/:([a-z_]+)/', $expression_full, $matches);

        for ($i = 0; $i < count($matches[1]); $i++) {
            $var = preg_replace('/^t_/', '', $matches[1][$i]);
            foreach ($linetype->fields as $field) {
                if ($field->name == $var) {
                    $expression_full = str_replace(':' . $matches[1][$i], $examples[$field->type], $expression_full);

                    break;
                }
            }
        }

        $result = Db::succeed("select {$expression_full} field from ({$table_expression}) t");
        $value = $result->fetch(PDO::FETCH_ASSOC)['field'];

        $def = 'varchar(255) null';

        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $value)) {
            $def = 'timestamp null';
        } elseif (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value)) {
            $def = 'date null';
        } elseif (is_numeric($value)) {
            $def = 'decimal(32, 16) null';
        }

        $schemata[$db_table][$field_full] = (object) ['def' => $def];
    }
}

foreach ($schemata as $table => $schemata[$db_table]) {
    $tabledef = "create table `{$table}` (\n  ";

    $tabledef .= implode(",\n  ", array_map(function($def, $fieldName){
        return "`{$fieldName}` {$def->def}";
    }, $schemata[$db_table], array_keys($schemata[$db_table])));

    $tabledef .= ",\n  primary key (`id`)\n) engine=InnoDB default charset=latin1;";

    $result = Db::succeed("drop table if exists `{$table}`");
    $result = Db::succeed($tabledef);

    // $row = $result->fetch(PDO::FETCH_ASSOC);
    // echo "\n";
    // echo $row["Create Table"] . "\n";
    // echo "\n\n\n\n--------------------\n\n\n\n";
}

Db::succeed("drop table if exists `sequence_pointer`");
Db::succeed("create table `sequence_pointer` (`table` varchar(255) not null, `pointer` int default '1', primary key (`table`)) engine=innodb default charset=latin1");

return [
    'schemata' => $schemata,
];
