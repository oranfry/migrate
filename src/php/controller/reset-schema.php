<?php
$token = Blends::login(USERNAME, PASSWORD, true);

Db::succeed("drop table if exists `sequence_pointer`");
Db::succeed("create table `sequence_pointer` (`table` varchar(255) not null, `pointer` int default '1', primary key (`table`)) engine=innodb default charset=latin1");

Db::succeed("drop table if exists `master_record_lock`");
Db::succeed("create table `master_record_lock` (`counter` int default null) engine=innodb default charset=latin1");

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

$linetypes = [];
$tablelinks = [];

foreach (array_values(array_unique(array_merge(['user', 'token'], BlendsConfig::get($token)->export_linetypes))) as $export) {
    $includeChildren = preg_match('/(.*)' . preg_quote('*') . '$/', $export, $groups);

    if ($includeChildren) {
        $linetypeName = $groups[1];
    } else {
        $linetypeName = $export;
    }

    $linetypes[] = $linetypeName;

    if ($includeChildren) {
        $linetype = Linetype::load($token, $linetypeName);

        foreach ($linetype->children as $child) {
            $linetypes[] = $child->linetype;
        }
    }
}

foreach ($linetypes as $linetypeName) {
    $linetype = Linetype::load($token, $linetypeName);
    $table = $linetype->table;
    $db_table = BlendsConfig::get($token)->tables[$table];

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

    foreach (@$linetype->children as $child) {
        $tablelinks[$child->parent_link] = true;
    }

    foreach (@$linetype->inlinelinks ?: [] as $child) {
        $tablelinks[$child->tablelink] = true;
    }
}

foreach ($schemata as $table => $schemata[$db_table]) {
    $tabledef = "create table `{$table}` (\n  ";

    $tabledef .= implode(",\n  ", array_map(function($def, $fieldName){
        return "`{$fieldName}` {$def->def}";
    }, $schemata[$db_table], array_keys($schemata[$db_table])));

    $tabledef .= ",\n  primary key (`id`)\n) engine=InnoDB default charset=latin1;";

    Db::succeed("drop table if exists `{$table}`");
    Db::succeed($tabledef);
}

foreach (array_keys($tablelinks) as $tablelinkName) {
    $tablelink = Tablelink::load($tablelinkName);
    $dbtables = [
        BlendsConfig::get($token)->tables[$tablelink->tables[0]],
        BlendsConfig::get($token)->tables[$tablelink->tables[1]],
    ];

    $unique = implode(",\n        ", array_filter([
        in_array($tablelink->type, ['oneone', 'manyone']) ? "UNIQUE KEY `{$tablelink->ids[0]}_id` (`{$tablelink->ids[0]}_id`)" : '',
        in_array($tablelink->type, ['oneone', 'onemany']) ? "UNIQUE KEY `{$tablelink->ids[1]}_id` (`{$tablelink->ids[1]}_id`)" : '',
    ]));

    $unique .= ($unique ? ',' : '');

    Db::succeed("drop table if exists `{$tablelink->middle_table}`");
    Db::succeed("CREATE TABLE `{$tablelink->middle_table}` (
        `{$tablelink->ids[0]}_id` char(10) NOT NULL,
        `{$tablelink->ids[1]}_id` char(10) NOT NULL,
        {$unique}
        KEY `fk_{$tablelinkName}_1` (`{$tablelink->ids[0]}_id`),
        KEY `fk_{$tablelinkName}_2` (`{$tablelink->ids[1]}_id`),
        CONSTRAINT `fk_{$tablelinkName}_1` FOREIGN KEY (`{$tablelink->ids[0]}_id`) REFERENCES `{$dbtables[0]}` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
        CONSTRAINT `fk_{$tablelinkName}_2` FOREIGN KEY (`{$tablelink->ids[1]}_id`) REFERENCES `{$dbtables[1]}` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
}

return [
    'schemata' => $schemata,
];
