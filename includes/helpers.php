function get_position_name($code) {
    $positions = array(
        1 => 'GK',
        2 => 'CB',
        3 => 'SB',
        4 => 'WB',
        5 => 'DMF',
        6 => 'CMF',
        7 => 'AMF',
        8 => 'WF',
        9 => 'SS',
        10 => 'CF'
    );

    return isset($positions[$code]) ? $positions[$code] : '???';
}

function render_positions($csv_string) {
    $codes = explode(',', $csv_string);
    $names = array();
    foreach ($codes as $code) {
        $names[] = get_position_name((int)trim($code));
    }
    return implode(', ', $names);
}
