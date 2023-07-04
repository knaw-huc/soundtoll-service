<?php
$db = new db();

function films() {
    global $db;

    send_json($db->films());
}

function years() {
    global $db;

    send_json($db->years());
}

function map_info($code) {
    global $db;

    send_json($db->getMapInfo($code));
}

function download_table($name, $query) {
    global $db;

    $struc = json_decode(base64_decode($query), true);
    $page = 1;
    $struc["page"] = $page;
    $queryStr = base64_encode(json_encode($struc));
    header("Content-Type: text/csv charset=utf-8");
    header("Content-Disposition: attachment; filename=$name.csv");
    $results = search($queryStr, true);
    $ids = create_query_conditions($results["passages"]);
    $rows = $db->get_table($name, $ids);
    $set = $rows["data"];
    $keys = array_keys($set[0]);
    $fp = fopen('php://output', 'wb');
    fputcsv($fp,$keys);
    $goOn = true;

    while ($goOn) {
        foreach ($set as $line) {
            fputcsv($fp, $line);
        }

        $page++;
        $struc["page"] = $page;
        $queryStr = base64_encode(json_encode($struc));
        $results = search($queryStr, true);
        $ids = create_query_conditions($results["passages"]);
        if ($ids == '') {
            $goOn = false;
        } else {
            $rows = $db->get_table($name, $ids);
            $set = $rows["data"];
        }
    }

    fclose($fp);
}


function create_query_conditions($ids) {
    $retArray = array();
    foreach ($ids as $item) {
        $retArray[] = $item["id_doorvaart"];
    }
    return implode(",", $retArray);
}

function passage($id) {
    global $db;

    if ($db->isPassage($id)) {
        send_json($db->passage($id));
    } else {
        throw_error("Passage does not exist!");
    }
}

function shipmasters($letter, $page) {
    global $db;

    send_json($db->shipmasters($letter, $page));
}

function chr_names($letter) {
    global $db;

    send_json($db->chr_names($letter));
}

function patronyms($letter) {
    global $db;

    send_json($db->patronyms($letter));
}

function places($letter, $port) {
    global $db;

    send_json($db->places($letter, $port));
}

function big_regions($region, $port) {
    global $db;

    send_json($db->big_regions($region, $port));
}

function small_regions($region, $port) {
    global $db;

    send_json($db->small_regions($region, $port));
}

function map_places($codedStruc) {
    global $db;
    send_json($db->get_map_places($codedStruc));
}

function hist_places($letter, $port) {
    global $db;
    send_json($db->hist_places($letter, $port));
}

function commodities($letter) {
    global $db;
    send_json($db->commodities($letter));
}

function elastic($json_struc) {
    $options = array('Content-type: application/json', 'Content-Length: ' . strlen($json_struc));
    $ch = curl_init(ELASTIC_HOST);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_struc);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function get_regions($size = "big") {
    global $db;

    if ($size == "big") {
        $results = $db->get_big_regions();
    } else {
        $results = $db->get_small_regions() ;
    }

    send_json(array("regions" => $results["data"]));
}

function search($codedStruc, $download = false) {
    $json_struc = parse_codedStruc($codedStruc, $download);
    $send_back = array();
    $result = elastic($json_struc);
    $send_back["amount"] = $result["hits"]["total"]["value"];
    if ($download) {
        $send_back["pages"] = ceil($result["hits"]["total"]["value"] / DOWNLOAD_PAGE_LENGTH);
    } else {
        $send_back["pages"] = ceil($result["hits"]["total"]["value"] / PAGE_LENGTH);
    }

    $send_back["passages"] = array();
    foreach ($result["hits"]["hits"] as $passage) {
        $send_back["passages"][] = $passage["_source"];
    }
    if ($download) {
        return $send_back;
    } else {
        send_json($send_back);
    }
}

function parse_codedStruc($codedStruc, $download) {
    $queryArray = json_decode(base64_decode($codedStruc), true);
    if ($download) {
        $from = ($queryArray["page"] - 1) * DOWNLOAD_PAGE_LENGTH;
    } else {
        $from = ($queryArray["page"] - 1) * PAGE_LENGTH;
    }

    $sortOrder = $queryArray["sortorder"];
    $sortElements = explode(';', $sortOrder);
    $sortField = $sortElements[0];
    $sortAscDesc = $sortElements[1];
    if ($queryArray["searchvalues"] == "none") {
        //if ($sortField == "jaar") {
        //    $json_struc = "{ \"query\": {\"match_all\": {}}, \"size\": 100, \"from\": $from, \"_source\": [\"id_doorvaart\", \"type\", \"schipper_achternaam\", \"schipper_naam\", \"schipper_patroniem\" , \"dag\", \"maand\", \"jaar\", \"schipper_plaatsnaam\", \"van_eerste\", \"naar_eerste\"], \"sort\": [{ \"jaar\": {\"order\":\"$sortAscDesc\"}}, { \"maand\": {\"order\":\"$sortAscDesc\"}}, { \"dag\": {\"order\":\"$sortAscDesc\"}}]}";
        //} else {
            $json_struc = "{ \"query\": {\"match_all\": {}}, \"size\": 100, \"from\": $from, \"_source\": [\"id_doorvaart\", \"type\", \"schipper_achternaam\", \"schipper_naam\", \"schipper_voornamen\",  \"schipper_tussenvoegsel\", \"schipper_patroniem\" , \"dag\", \"maand\", \"jaar\", \"schipper_plaatsnaam\", \"van_eerste\", \"naar_eerste\"], \"sort\": [{ \"$sortField\": {\"order\":\"$sortAscDesc\"}}]}";
        //}

    } else {
        $json_struc = buildQuery($queryArray, $from, $sortOrder, $download);
    }
    return $json_struc;
}

function buildQuery($queryArray, $from, $sortOrder, $download) {
    $terms = array();

    foreach($queryArray["searchvalues"] as $item) {

        if (strpos($item["field"], '.')) {
            if (strpos($item["field"], 'FREE_TEXT:') !== false) {
                get_nested_free_texts($item["field"], makeItems($item["values"]), $terms);
            } else {
                $fieldArray = explode(".", $item["field"]);
                $terms[] = nestedTemplate($fieldArray, $item["values"]);
            }
        } else {
            $terms[] = matchTemplate($item["field"], makeItems($item["values"]));
        }

    }
    return queryTemplate(implode(",", $terms), $from, $sortOrder, $download);
}

function get_nested_free_texts($entry, $values, &$terms) {
    $components = explode(":", $entry);
    $field = $components[1];
    $path = explode(".", $field);
    $path = $path[0];
        $terms[] = "{\"nested\": {\"path\": \"$path\", \"query\": {\"wildcard\": {\"$field\": {\"value\": \"$values\"}}}}}";
}

function matchTemplate($term, $value) {
    $components = explode(":", $term);
    if ($components[0] == "FREE_TEXT") {
        return get_ft_matches($value, $components[1]);
    }
    switch ($term) {
        //case "FREE_TEXT":
            //return "{\"multi_match\": {\"query\": \"$value\"}}";
            //return "{\"wildcard\": {\"fulltext\": {\"value\": \"$value\"}}}";
            //return get_ft_matches($value);
        case "PERIOD":
            return yearValues($value);
        case "jaar":
            return "{\"terms\": {\"jaar\": [$value]}}";
        default:
            return "{\"match\": {\"$term.raw\": \"$value\"}}";
    }
}

function get_ft_matches($values, $field) {
    $valArr = explode(",", $values);
    $lengte = count($valArr);
    if ($field == "fulltext") {
        $sField = $field;
    } else {
        $sField = $field .".raw";
    }
    switch ($lengte) {
        case 0:
            return "";
        case 1:
            $val = str_replace("-", "\\\\-", trim($valArr[0]));
            return "{\"wildcard\": {\"$sField\": {\"value\": \"$val\", \"case_insensitive\": true}}}";
        default:
            $retArr = array();
            foreach ($valArr as $value) {
                $val = str_replace("-", "\\\\-", trim($value));
                $retArr[] = "{\"wildcard\": {\"$sField\": {\"value\": \"$val\", \"case_insensitive\": true}}}";
            }
            return implode(",", $retArr);
    }
}

function yearValues($range)
{
    $vals = explode("-", $range);
    $from = $vals[0];
    $to = $vals[1];
    return "{\"range\": {\"jaar\": {\"from\": $from, \"to\": $to}}}";
}

function nestedTemplate($fieldArray, $value) {
    $path = $fieldArray[0];
    $field = implode(".", $fieldArray);
    return "{\"nested\": {\"path\": \"$path\",\"query\": {\"bool\": {\"must\": [{\"terms\": {\"$field.raw\": [\"{$value[0]}\"]}}]}}}}";
}

function queryTemplate($terms, $from, $sortOrder, $download) {
    $sortElements = explode(";", $sortOrder);
    $sortField = $sortElements[0];
    $sortAscDesc = $sortElements[1];
    //if ($sortField == "jaar") {
    //    return "{ \"query\": { \"bool\": { \"must\": [ $terms ] } }, \"size\": 100, \"from\": $from, \"_source\": [\"id_doorvaart\", \"type\", \"schipper_achternaam\", \"schipper_naam\", \"schipper_patroniem\" ,\"dag\",\"maand\",\"jaar\", \"schipper_plaatsnaam\", \"van_eerste\", \"naar_eerste\"], \"sort\": [ { \"jaar\": {\"order\":\"$sortAscDesc\"}}, { \"maand\": {\"order\":\"$sortAscDesc\"}}, { \"dag\": {\"order\":\"$sortAscDesc\"}} ] }";
    //} else {
    if ($download) {
        return "{ \"query\": { \"bool\": { \"must\": [ $terms ] } }, \"size\": 500, \"from\": $from, \"_source\": [\"id_doorvaart\"] }";
    } else {
        return "{ \"query\": { \"bool\": { \"must\": [ $terms ] } }, \"size\": 100, \"from\": $from, \"_source\": [\"id_doorvaart\", \"type\", \"schipper_achternaam\", \"schipper_naam\", \"schipper_voornamen\", \"schipper_tussenvoegsel\", \"schipper_patroniem\" ,\"dag\",\"maand\",\"jaar\", \"schipper_plaatsnaam\", \"van_eerste\", \"naar_eerste\"], \"sort\": [ { \"$sortField\": {\"order\":\"$sortAscDesc\"}} ] }";
    }

    //}

}

function makeItems($termArray) {
    $retArray = array();

    foreach($termArray as $term) {
        //$retArray[] = strtolower($term);
        $retArray[] = $term;
    }
    return implode(", ", $retArray);
}

function get_facets($field, $filter, $type) {
    if ($filter != "") {
        $filter = regShit($filter);
    }
    if ($type == 'long') {
        $amount = 10;
    } else {
        $amount = 5;
    }
    if ($field == "schipper_naam")
    {
    $json_struc = "{\"query\": {\"regexp\": {\"schipper_achternaam.raw\": {\"value\": \"$filter.*\"}}},\"aggs\": {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    } else {
        $json_struc = "{\"query\": {\"regexp\": {\"$field.raw\": {\"value\": \"$filter.*\"}}},\"aggs\": {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    }

    $result = elastic($json_struc);
    send_json(array("buckets" => $result["aggregations"]["names"]["buckets"]));
}

function get_nested_facets($field, $type, $filter = "") {
    if ($type == 'long') {
        $amount = 10;
    } else {
        $amount = 5;
    }
    if ($filter != "") {
        $filter = regShit($filter);
    }
    $field_elements = explode(".", $field);
    $path = $field_elements[0];
    $json_struc = "{\"size\": 0,\"aggs\": {\"nested_terms\": {\"nested\": {\"path\": \"$path\"},\"aggs\": {\"filter\": {\"filter\": {\"regexp\": {\"$field.raw\": \"$filter.*\"}},\"aggs\": {\"names\": {\"terms\": {\"field\": \"$field.raw\",\"size\": $amount}}}}}}}}";
    $result = elastic($json_struc);
    send_json(array("buckets" => $result["aggregations"]["nested_terms"]["filter"]["names"]["buckets"]));
}

function regShit($filter) {
    if (strlen($filter) == 1) {
        return "[" . strtoupper(substr($filter, 0, 1)) . strtolower(substr($filter, 0, 1)) . "]";
    } else {
        return "[" . strtoupper(substr($filter, 0, 1)) . strtolower(substr($filter, 0, 1)) . "]" . substr($filter, 1);
    }

}

function get_initial_facets($field, $type) {
    if ($type == 'long') {
        $amount = 10;
    } else {
        $amount = 5;
    }
    $json_struc = "{\"size\": 0,\"aggs\" : {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    $result = elastic($json_struc);
    echo send_json(array("buckets" => $result["aggregations"]["names"]["buckets"]));
}

function currency() {
    global $db;

    send_json($db->currency());
}

function throw_error($error = "Bad request") {
    $response = array("error" => $error);
    send_json($response);
}

function send_json($message_array) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($message_array);
}
function send_elastic($json) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    echo $json;
}

function check_id($id) {
    global $db;

    if (is_numeric($id)) {
        if (strpos($id, '.') || strpos($id, ',')) {
            send_json(array("amount" => 0));
        } else {
            $recs = $db->is_passage($id);
            send_json(array("amount" => $recs));
        }
    } else {
        send_json(array("amount" => 0));
    }
}