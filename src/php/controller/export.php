<?php
$ids = [];
$output = "";
$timestamp = date('Y-m-d H:i:s');

foreach (Config::get()->export_linetypes as $econfig) {
    if (!preg_match('/^([a-z]+)(\*?)/', $econfig, $groups)) {
        error_response("Invalid export linetype export config {$econfig}");
    }

    $export_linetype = $groups[1];
    $include_children = $groups[2] == '*';

    $id = 1;
    $ids[$export_linetype] = [];

    $linetype = Linetype::load($export_linetype);
    $lines = $linetype->find_lines(null, null, null, false, $include_children, true);

    foreach ($lines as $line) {
        // remove inline children

        foreach ($line as $key => $value) {
            if (is_object($value)) {
                unset($line->{$key});
            }
        }

        // save map of old to new ids

        $ids[$export_linetype][$line->id] = $id++;

        $linetype->strip_r($line);

        // translate parent refs to new ids

        foreach ($linetype->find_incoming_links() as $incoming) {
            $tablelink = Tablelink::load($incoming->parent_link);
            $parentside = @$incoming->reverse ? 1 : 0;
            $childside = ($parentside + 1) % 2;

            if (@$line->{$incoming->parent_linetype}) {
                if (isset($ids[$incoming->parent_linetype])) {
                    if (!isset($ids[$incoming->parent_linetype][$line->{$incoming->parent_linetype}])) {
                        error_log("Not set: \$ids['" . $incoming->parent_linetype . "']['" . $line->{$incoming->parent_linetype} . "']");
                    }
                    $line->{$incoming->parent_linetype} = $ids[$incoming->parent_linetype][$line->{$incoming->parent_linetype}];
                } else {
                    unset($line->{$incoming->parent_linetype});
                }
            }
        }
    }

    if (count($lines)) {
        echo $timestamp . ' ' . $export_linetype . ' ' . json_encode($lines) . "\n";
    }
}

return [];
