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
    //error_log($json_struc);
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

function search($codedStruc) {
    $json_struc = parse_codedStruc($codedStruc);
    $send_back = array();
    error_log($json_struc);
    $result = elastic($json_struc);
    $send_back["amount"] = $result["hits"]["total"]["value"];
    $send_back["pages"] = ceil($result["hits"]["total"]["value"] / PAGE_LENGTH);
    $send_back["passages"] = array();
    foreach ($result["hits"]["hits"] as $passage) {
        $send_back["passages"][] = $passage["_source"];
    }
    send_json($send_back);
}

function parse_codedStruc($codedStruc) {
    $queryArray = json_decode(base64_decode($codedStruc), true);
    $from = ($queryArray["page"] - 1) * PAGE_LENGTH;
    $sortOrder = $queryArray["sortorder"];
    $sortElements = explode(';', $sortOrder);
    $sortField = $sortElements[0];
    $sortAscDesc = $sortElements[1];
    if ($queryArray["searchvalues"] == "none") {
        $json_struc = "{ \"query\": {\"match_all\": {}}, \"size\": 100, \"from\": $from, \"_source\": [\"id_doorvaart\", \"type\", \"schipper_achternaam\", \"schipper_naam\", \"schipper_patroniem\" , \"dag\", \"maand\", \"jaar\", \"schipper_plaatsnaam\", \"van_eerste\", \"naar_eerste\"], \"sort\": [{ \"$sortField\": {\"order\":\"$sortAscDesc\"}}]}";
    } else {
        $json_struc = buildQuery($queryArray, $from, $sortOrder);
    }
    return $json_struc;
}

function buildQuery($queryArray, $from, $sortOrder) {
    $terms = array();

    foreach($queryArray["searchvalues"] as $item) {
        if (strpos($item["field"], '.')) {
            $fieldArray = explode(".", $item["field"]);
            $terms[] = nestedTemplate($fieldArray, makeItems($item["values"]));
        } else {
            $terms[] = matchTemplate($item["field"], makeItems($item["values"]));
        }

    }

    return queryTemplate(implode(",", $terms), $from, $sortOrder);
}

function matchTemplate($term, $value) {
    switch ($term) {
        case "FREE_TEXT":
            return "{\"multi_match\": {\"query\": \"$value\"}}";
        case "PERIOD":
            return yearValues($value);
        case "jaar":
            return "{\"terms\": {\"jaar\": [$value]}}";
        default:
            return "{\"terms\": {\"$term.raw\": [\"$value\"]}}";
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
    return "{\"nested\": {\"path\": \"$path\",\"query\": {\"bool\": {\"must\": [{\"terms\": {\"$field.raw\": [\"$value\"]}}]}}}}";
}

function queryTemplate($terms, $from, $sortOrder) {
    $sortElements = explode(";", $sortOrder);
    $sortField = $sortElements[0];
    $sortAscDesc = $sortElements[1];
    return "{ \"query\": { \"bool\": { \"must\": [ $terms ] } }, \"size\": 100, \"from\": $from, \"_source\": [\"id_doorvaart\", \"type\", \"schipper_achternaam\", \"schipper_naam\", \"schipper_patroniem\" ,\"dag\",\"maand\",\"jaar\", \"schipper_plaatsnaam\", \"van_eerste\", \"naar_eerste\"], \"sort\": [ { \"$sortField\": {\"order\":\"$sortAscDesc\"}} ] }";
}

function makeItems($termArray) {
    $retArray = array();

    foreach($termArray as $term) {
        //$retArray[] = "\"" . $term . "\"";
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