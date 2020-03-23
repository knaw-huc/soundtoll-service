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

function places($letter, $page) {
    global $db;

    send_json($db->places($letter, $page));
}

function hist_places($letter, $page) {
    global $db;

    send_json($db->hist_places($letter, $page));
}

function elastic($json_struc) {

    //$json_struc = '{"size": 0,"aggs" : {"names" : {"terms" : { "field" : "schipper_achternaam.keyword",  "size" : 5 }}}}';
    $options = array('Content-type: application/json', 'Content-Length: ' . strlen($json_struc));

    $ch = curl_init(ELASTIC_HOST);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_struc);
    //curl_setopt($ch, CURLOPT_VERBOSE, 1);
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

    $result = elastic($json_struc);
    $send_back["amount"] = $result["hits"]["total"]["value"];
    $send_back["passages"] = array();
    foreach ($result["hits"]["hits"] as $passage) {
        $send_back["passages"][] = $passage["_source"];
    }
    send_json($send_back);
}

function parse_codedStruc($codedStruc) {
    $queryArray = json_decode(base64_decode($codedStruc), true);
    //error_log(print_r($queryArray, true));
    $from = ($queryArray["page"] - 1) * PAGE_LENGTH;
    $sortOrder = $queryArray["sortorder"];
    if ($queryArray["searchvalues"] == "none") {
        $json_struc = "{ \"query\": {\"match_all\": {}}, \"size\": 20, \"from\": $from, \"_source\": [\"id_doorvaart\", \"schipper_achternaam\", \"schipper_naam\", \"jaar\", \"schipper_plaatsnaam\"], \"sort\": [{ \"$sortOrder.keyword\": {\"order\":\"asc\"}}]}";
    } else {
        $json_struc = buildQuery($queryArray, $from);
    }
    error_log($json_struc);
    return $json_struc;
}

function buildQuery($queryArray, $from) {
    $terms = array();

    foreach($queryArray["searchvalues"] as $item) {
        $terms[] = matchTemplate($item["field"], makeItems($item["values"]));
    }

    return queryTemplate(implode(",", $terms), $from);
}

function matchTemplate($term, $value) {
    return "{\"terms\": {\"$term.keyword\": [$value]}}";
}

function queryTemplate($terms, $from) {
    return "{ \"query\": { \"bool\": { \"must\": [ $terms ] } }, \"size\": 20, \"from\": $from, \"_source\": [\"id_doorvaart\", \"schipper_achternaam\", \"schipper_naam\", \"jaar\", \"schipper_plaatsnaam\"], \"sort\": [ { \"schipper_achternaam.keyword\": {\"order\":\"asc\"}} ] }";
}

function makeItems($termArray) {
    $retArray = array();

    foreach($termArray as $term) {
        $retArray[] = "\"" . $term . "\"";
    }
    return implode(", ", $retArray);
}

function get_facets($field, $filter, $type) {
    if ($type == 'long') {
        $amount = 10;
    } else {
        $amount = 5;
    }
    if ($field == "schipper_naam")
    {
    $json_struc = "{\"query\": {\"regexp\": {\"schipper_achternaam.keyword\": {\"value\": \"$filter.*\"}}},\"aggs\": {\"names\" : {\"terms\" : { \"field\" : \"$field.keyword\",  \"size\" : $amount }}}}";
    } else {
        $json_struc = "{\"query\": {\"regexp\": {\"$field.keyword\": {\"value\": \"$filter.*\"}}},\"aggs\": {\"names\" : {\"terms\" : { \"field\" : \"$field.keyword\",  \"size\" : $amount }}}}";
    }

    $result = elastic($json_struc);
    send_json(array("buckets" => $result["aggregations"]["names"]["buckets"]));
}

function get_initial_facets($field, $type) {
    if ($type == 'long') {
        $amount = 10;
    } else {
        $amount = 5;
    }
    //error_log("{\"size\": 0,\"aggs\" : {\"names\" : {\"terms\" : { \"field\" : \"$field.keyword\",  \"size\" : $amount }}}}");
    $json_struc = "{\"size\": 0,\"aggs\" : {\"names\" : {\"terms\" : { \"field\" : \"$field.keyword\",  \"size\" : $amount }}}}";
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