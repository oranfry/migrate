<?php

function build_table_definitions($token)
{
    $schemata = [];
    $definitions = [
        'sequence_pointer' => "create table `sequence_pointer` (`table` varchar(255) not null, `pointer` int default '1', primary key (`table`)) engine=innodb default charset=latin1",
        'master_record_lock' => "create table `master_record_lock` (`counter` int default null) engine=innodb default charset=latin1",
    ];

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

    $tablelinks = [];

    foreach (array_keys(BlendsConfig::get($token)->linetypes) as $linetypeName) {
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

    foreach ($schemata as $table => $fields) {
        $definitions[$table] = "create table `{$table}` (" . implode(", ", array_map(function($def, $fieldName){
            return "`{$fieldName}` {$def->def}";
        }, $fields, array_keys($fields))) . ", primary key (`id`)) engine=InnoDB default charset=latin1";
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

        $definitions[$tablelink->middle_table] = preg_replace('/\s+/', ' ', "create table `{$tablelink->middle_table}` (
                `{$tablelink->ids[0]}_id` char(10) not null,
                `{$tablelink->ids[1]}_id` char(10) not null,
                {$unique}
                key `fk_{$tablelinkname}_1` (`{$tablelink->ids[0]}_id`),
                key `fk_{$tablelinkname}_2` (`{$tablelink->ids[1]}_id`),
                constraint `fk_{$tablelinkname}_1` foreign key (`{$tablelink->ids[0]}_id`) references `{$dbtables[0]}` (`id`) on delete cascade on update restrict,
                constraint `fk_{$tablelinkname}_2` foreign key (`{$tablelink->ids[1]}_id`) references `{$dbtables[1]}` (`id`) on delete cascade on update restrict
            ) engine=innodb default charset=latin1");
    }

    return $definitions;
}