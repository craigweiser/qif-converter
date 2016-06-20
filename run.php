<?php

function generateRegister($filename, $bank) {
    $fileLines = openCsv($filename, $bank);

    if ($bank == "bcee" || $bank == "abnamro") {
        array_shift($fileLines); // get rid of the first row
    }

    if(count($fileLines)<1) {
        echo "No lines founds in file";
        exit;
    }
    
    $registerQif = "!Type:Bank\n\n";
    foreach ($fileLines as $lineIndex => $lineCsv) {
        if ($bank == "spardabank" && $lineIndex < 11) {
            continue;
        }
        if(count($lineCsv)< 1) {
            echo "No fields found!";
            exit;
        }
        if (isset($lineCsv[0]) && (isset($lineCsv[1]) || $bank == "abnamro")) {
            $registerQif .= generateTransaction($lineCsv, $bank);
        }
    }
    return $registerQif;
}

function generateTransaction($lineCsv, $bank) {
    if ($bank == "spardabank") {
        $fields = spardabank($lineCsv);
    }
    if ($bank == "bceeOld") {
        $fields = bceeOld($lineCsv);
    }
    if ($bank == "bcee") {
        $fields = bcee($lineCsv);
    }
    if ($bank == "ing") {
        $fields = ing($lineCsv);
    }
    if ($bank == "rabobank") {
        $fields = rabobank($lineCsv);
    }
    if ($bank == "abnamro") {
        $fields = abnamro($lineCsv);
    }

    if(empty($fields)) {
        echo "No fields returned for line (".  implode("~", $lineCsv).") from generate transaction!";
        exit;
    }
    
    $transaction = createTransaction($fields);

    $transaction .= "^\n\n";
    return $transaction;
}

function spardabank($row) {
    $fields['date'] = substr($row[1], 3, 2) . "/" . substr($row[1], 0, 2) . "/" . substr($row[1], 6, 4);
    $fields['amount'] = $row[3];
    $fields['payee'] = $row[2];
    $fields['payee'] = trim(preg_replace('/\s\s+/', ' ', $fields['payee']));
    $fields['category'] = mapPayeeToCategories($fields['payee']);

    return $fields;
}

function bceeOld($row) {
    $fields['date'] = substr($row[0], 5, 2) . "/" . substr($row[0], 8, 2) . "/" . substr($row[0], 0, 4);
    $fields['amount'] = $row[2];
    $fields['payee'] = $row[1];
    $fields['payee'] = trim(preg_replace('/\s\s+/', ' ', $fields['payee']));
    $fields['category'] = "";

    return $fields;
}

function bcee($row) {
    $row[0] = str_replace("\0", "", $row[0]);
    $row[1] = str_replace("\0", "", $row[1]);
    $row[2] = str_replace("\0", "", $row[2]);
    $row[3] = str_replace("\0", "", $row[3]);

    $fields['date'] = $row[0];
    $fields['amount'] = str_replace(",", ".", str_replace(" ", "", str_replace("\xA0", "", $row[2]))); //this is not a space but some strange character

    $fields['payee'] = $row[1];
    $fields['payee'] = trim(preg_replace('/\s\s+/', ' ', $fields['payee']));
    $fields['category'] = "";

    return $fields;
}

function ing($row) {
    $fields['date'] = substr($row[0], 4, 2) . "/" . substr($row[0], 6, 2) . "/" . substr($row[0], 0, 4);
    $fields['amount'] = str_replace(",", ".", $row[5] == "Bij" ? $row[6] : "-" . $row[6]);
    $fields['payee'] = $row[1] . " " . $row[8] . " " . $row[3];
    $fields['payee'] = trim(preg_replace('/\s\s+/', ' ', $fields['payee']));
    $fields['category'] = $row[7];

    return $fields;
}

function rabobank($row) {

    // Rabobank CSV strucure based on:
    // https://bankieren.rabobank.nl/klanten/bedrijven/help/specificaties_elektronische_betaaldiensten/rabo_internetbankieren_professional/export/ --> 'CSV (kommagescheiden nieuw)'

    $fields['date'] = substr($row[2], 4, 2) . "/" . substr($row[2], 6, 2) . "/" . substr($row[2], 0, 4);
    $fields['amount'] = str_replace(",", ".", $row[3] == "C" ? $row[4] : "-" . $row[4]);
    $fields['payee'] = $row[5] . " " . $row[6];
    $fields['payee'] = trim(preg_replace('/\s\s+/', ' ', $fields['payee']));
    $fields['category'] = "";

    return $fields;
}

function abnamro($row) {
    $fields['date'] = substr($row[2], 4, 2) . "/" . substr($row[2], 6, 2) . "/" . substr($row[2], 0, 4);
    $fields['amount'] = $row[6];
    $fields['payee'] = $row[7];
    $fields['category'] = "";
    return $fields;
}

function openCsv($fileName, $bank) {

    $delimiter = getDelimiter($bank);

    $lines = array();
    $row = 1;
    if (($handle = fopen($fileName, "r")) !== FALSE) {
        while (($fields = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            $lines[] = $fields;
            $row++;
        }
        fclose($handle);
    }
    return $lines;
}

function getDelimiter($bank) {
    if ($bank == "spardabank") {
        $delimiter = ";";
    }
    if ($bank == "ing") {
        $delimiter = ",";
    }
    if ($bank == "bcee") {
        $delimiter = ";";
    }
    if ($bank == "rabobank") {
        $delimiter = ",";
    }
    if ($bank == "abnamro") {
        $delimiter = ",";
    }
    return $delimiter;
}

function mapPayeeToCategories($payee)
{
    $category = '';
    $ini_array = parse_ini_file("account_mapping.ini", true);
    $mappings = $ini_array['mappings'];
    $accounts = $ini_array['accounts'];
    foreach ($mappings as $key => $mapping) {
        $pattern = '/'.$mapping.'/i';
        //echo 'testing pattern: ' . $pattern . ' on payee: ' . $payee;
        if(preg_match($pattern, $payee)) {
            $category = $accounts[$key];
        }
    }
    return $category;
}


function createTransaction($fields)
{
    $transaction = "";
    $transaction .= "D{$fields['date']}\n";
    $transaction .= "T{$fields['amount']}\n";
    $transaction .= "P{$fields['payee']}\n";
    if ($fields['category']) {
        $transaction .= "L{$fields['category']}\n";
    }
    return $transaction;
}