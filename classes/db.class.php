<?php

class db
{

    var $con;

    function __construct()
    {
        $this->con = new mysqli(DB_SERVER, DB_USER, DB_PASSWD, DB_NAME);
        $this->con->query("SET NAMES utf8");
    }

    function films()
    {
        $results = $this->con->query("SELECT DISTINCT rolnr AS film FROM secties_totaal");
        return $this->ass_arr($results);
    }

    function get_table($table, $ids) {
        $results = $this->con->query("SELECT * FROM $table WHERE id_doorvaart IN ($ids)");
        return $this->ass_arr($results);
    }

    function years()
    {
        $results = $this->con->query("SELECT DISTINCT rolnr AS film FROM secties_totaal");
        return $this->ass_arr($results);
    }

    function get_small_regions() {
        $results = $this->con->query("SELECT DISTINCT `small_category` as region, `small_category_code` AS code FROM `places_standard` ORDER BY small_category");
        return $this->ass_arr($results);
    }

    function get_big_regions() {
        $results = $this->con->query("SELECT DISTINCT `big_category` as region, `big_category_code` AS code FROM `places_standard` ORDER BY big_category");
        return $this->ass_arr($results);
    }


    function currency() {
        $results = $this->ass_arr($this->con->query("SELECT Name AS name, short AS code, URL AS url FROM currency"));
        $results["data"] = array("itemList" => $results["data"]);
        return $results;
    }

    function shipmasters($letter, $page) {
        $offset = $page - 1;
        $offset = $offset * BROWSE_PAGE_LENGTH;
        //$results = $this->ass_arr($this->con->query("SELECT achternaam, volledige_naam FROM shipmasters WHERE letter= '$letter' ORDER BY achternaam, volledige_naam LIMIT $offset, " . BROWSE_PAGE_LENGTH));
        $results = $this->ass_arr($this->con->query("SELECT achternaam, volledige_naam FROM shipmasters WHERE letter= '$letter' ORDER BY achternaam, volledige_naam "));
        //$results = $this->ass_arr($this->con->query("SELECT achternaam, volledige_naam FROM shipmasters WHERE achternaam = 'Hall' ORDER BY achternaam LIMIT $offset, " . PAGE_LENGTH));
        $results["data"] = array("itemList" => $results["data"], "page" => $page, "number_of_pages" => $this->pagesShipmasters($letter));
        return $results;
    }

    function patronyms($letter) {
        $results = $this->ass_arr($this->con->query("SELECT patroniem FROM patroniemen WHERE letter= '$letter' ORDER BY patroniem "));
        $results["data"] = array("itemList" => $results["data"], "page" => 1, "number_of_pages" => 1);
        return $results;
    }

    function chr_names($letter) {
        $results = $this->ass_arr($this->con->query("SELECT voornaam FROM voornamen WHERE letter= '$letter' ORDER BY voornaam "));
        $results["data"] = array("itemList" => $results["data"], "page" => 1, "number_of_pages" => 1);
        return $results;
    }

    function pagesShipmasters($letter) {
        $results = $this->ass_arr($this->con->query("SELECT count(*) AS aantal FROM shipmasters WHERE letter = '$letter'"));
        return ceil($results["data"][0]["aantal"] / PAGE_LENGTH);
    }

    function places($letter, $port) {
        switch ($port) {
            case "plaats_standaard":
                $port_type = "home_port";
                break;
            case "van_standaard.plaats":
                $port_type = "from_port";
                break;
            case "naar_standaard.plaats":
                $port_type = "to_port";
                break;
            default:
                $port_type = "vnr";
        }
        //$results = $this->ass_arr($this->con->query("SELECT Modern_name AS name FROM places_standard WHERE letter= '$letter' ORDER BY Modern_name LIMIT $offset, " . BROWSE_PAGE_LENGTH));
        $results = $this->ass_arr($this->con->query("SELECT DISTINCT place_standard AS name, places_per_standard as list FROM places_source WHERE letter_standard= '$letter' and $port_type = 1 ORDER BY place_standard"));
        $results["data"] = array("itemList" => $results["data"], "page" => 1, "number_of_pages" => 1);
        return $results;
    }

    function big_regions($region, $port) {
        switch ($port) {
            case "plaats_standaard":
                $port_type = "home_port";
                break;
            case "van_standaard.plaats":
                $port_type = "from_port";
                break;
            case "naar_standaard.plaats":
                $port_type = "to_port";
                break;
            default:
                $port_type = "vnr";
        }
        //$results = $this->ass_arr($this->con->query("SELECT Modern_name AS name FROM places_standard WHERE letter= '$letter' ORDER BY Modern_name LIMIT $offset, " . BROWSE_PAGE_LENGTH));
        $results = $this->ass_arr($this->con->query("SELECT DISTINCT place_standard AS name, places_per_standard as list FROM places_source WHERE big_region = $region and $port_type = 1 ORDER BY place_standard"));
        $results["data"] = array("itemList" => $results["data"], "page" => 1, "number_of_pages" => 1);
        return $results;
    }

    function small_regions($region, $port) {
        switch ($port) {
            case "plaats_standaard":
                $port_type = "home_port";
                break;
            case "van_standaard.plaats":
                $port_type = "from_port";
                break;
            case "naar_standaard.plaats":
                $port_type = "to_port";
                break;
            default:
                $port_type = "vnr";
        }
        //$results = $this->ass_arr($this->con->query("SELECT Modern_name AS name FROM places_standard WHERE letter= '$letter' ORDER BY Modern_name LIMIT $offset, " . BROWSE_PAGE_LENGTH));
        $results = $this->ass_arr($this->con->query("SELECT DISTINCT place_standard AS name, places_per_standard as list FROM places_source WHERE small_region = $region and $port_type = 1 ORDER BY place_standard"));
        $results["data"] = array("itemList" => $results["data"], "page" => 1, "number_of_pages" => 1);
        return $results;
    }

    function get_map_places($codedStruc) {
        $struc = json_decode(base64_decode($codedStruc), true);
        $port = $struc["port"];
        $query = "SELECT distinct st.Stednavn as place_standard, st.decLatitude as lat, st.decLongitude as lon FROM `places_source` as so, places_standard as st WHERE so.place_standard = st.Stednavn and so.$port = 1 and st.Stednavn <> 'Unknown'";
        $region = $struc["region"];
        $commodity = $struc["commodity"];
        if ($region !== "0") {
            $query .= " AND st.big_category_code = $region";
        }
        $query .= $this->add_map_years($port, $struc["years"]);
        if (trim($commodity) !== "") {
            $tail = $this->addTail($commodity, $port);
            $query .= $tail;
        }

        return $this->ass_arr($this->con->query($query));
    }

    private function addTail($commodity, $port) {
        $sql = "";
        $condition = $this->commCondition($commodity);
        switch ($port) {
            case "home_port":
                return " AND so.place IN (SELECT distinct schipper_plaatsnaam FROM doorvaarten as d, ladingen as l where d.id_doorvaart = l.id_doorvaart AND l.soort $condition)";
                break;
            case "from_port":
                return " AND so.place IN (select DISTINCT van from ladingen where soort $condition)";
                break;
            case "to_port":
                return " AND so.place IN (select DISTINCT naar from ladingen where soort $condition)";
                break;
        }
        return $sql;
    }

    private function commCondition($commodity) {
        $retVal = str_replace("?", "_", $commodity);
        $retVal = str_replace("*", "%", $retVal);
        if (strpos($retVal, "_") || strpos($retVal, "%")) {
            return " LIKE '$retVal'";
        } else {
            return " = '$retVal'";
        }
    }

    function add_map_years($port, $years) {
        if ($years == "0") {
            return "";
        } else {
            switch ($port) {
                case "home_port":
                    $table = "jaren_home";
                    break;
                case "from_port":
                    $table = "jaren_van";
                    break;
                case "to_port":
                    $table = "jaren_naar";
                    break;
                default:
                    return "zeeman";
            }

            switch($years) {
                case "1":
                    $condition = " < 1634";
                    break;
                case "2":
                    $condition = " BETWEEN 1634 AND 1857";
                    break;
                case "3":
                    $condition = " BETWEEN 1634 AND 1700";
                    break;
                case "4":
                    $condition = " BETWEEN 1700 AND 1750";
                    break;
                case "5":
                    $condition = " BETWEEN 1750 AND 1800";
                    break;
                case "6":
                    $condition = " BETWEEN 1800 AND 1857";
                    break;
                default:
                    return "bonzo";
            }
            return " AND st.Stednavn IN (SELECT plaats FROM $table WHERE jaar $condition)";
        }
    }

    function commodities($letter) {
        $results = $this->ass_arr($this->con->query("SELECT commodity AS name FROM commodities WHERE letter= '$letter' ORDER BY commodity"));
        $results["data"] = array("itemList" => $results["data"], "page" => 1, "number_of_pages" => 1);
        return $results;
    }

    function pagesPlaces($letter) {
        $results = $this->ass_arr($this->con->query("SELECT count(*) AS aantal FROM places_standard WHERE letter = '$letter'"));
        return ceil($results["data"][0]["aantal"] / PAGE_LENGTH);
    }

    function hist_places($letter, $port) {
        switch ($port) {
            case "schipper_plaatsnaam":
                $port_type = "home_port";
                break;
            case "van.plaats":
                $port_type = "from_port";
                break;
            case "naar.plaats":
                $port_type = "to_port";
                break;
            default:
                $port_type = "vnr";
        }
        //$results = $this->ass_arr($this->con->query("SELECT place AS name FROM places_source WHERE letter= '$letter' ORDER BY place LIMIT $offset, " . BROWSE_PAGE_LENGTH));
        $results = $this->ass_arr($this->con->query("SELECT place AS name, places_per_standard as list FROM places_source WHERE letter= '$letter' AND $port_type = 1 ORDER BY place"));
        $results["data"] = array("itemList" => $results["data"], "page" => 1, "number_of_pages" => $this->hist_pagesPlaces($letter));
        return $results;
    }

    function hist_pagesPlaces($letter) {
        $results = $this->ass_arr($this->con->query("SELECT count(*) AS aantal FROM places_source WHERE letter = '$letter'"));
        return ceil($results["data"][0]["aantal"] / PAGE_LENGTH);
    }

    function isPassage($id)
    {
        $results = $this->ass_arr($this->con->query("SELECT COUNT(*) as count FROM doorvaarten WHERE id_doorvaart = $id"));
        return $results["data"][0]["count"];
    }

    function passage($id)
    {
        $retArr = $this->ass_arr($this->con->query("SELECT `id_doorvaart`, `volgnummer`, `schipper_voornamen`, `schipper_patroniem`, `schipper_tussenvoegsel`, `schipper_achternaam`, `schipper_plaatsnaam`, `tmp` as schipper_naam, CONCAT(dag, '-', maand, '-', jaar) AS datum,`soort_korting`, `korting_muntsoort1`, `korting_bedrag1`, `korting_muntsoort2`, `korting_bedrag2`, `korting_muntsoort3`, `korting_bedrag3`, `subtotaal1_muntsoort1`, `subtotaal1_bedrag1`, `subtotaal1_muntsoort2`, `subtotaal1_bedrag2`, `subtotaal1_muntsoort3`, `subtotaal1_bedrag3`, `subtotaal2_muntsoort1`, `subtotaal2_bedrag1`, `subtotaal2_muntsoort2`, `subtotaal2_bedrag2`, `subtotaal2_muntsoort3`, `subtotaal2_bedrag3`, `totaal_muntsoort1`, `totaal_bedrag1`, `totaal_muntsoort2`, `totaal_bedrag2`, `totaal_muntsoort3`, `totaal_bedrag3`, `totaal_muntsoort4`, `totaal_bedrag4`, `totaal_muntsoort5`, `totaal_bedrag5`, jaar, tonnage, opmerking_bron FROM doorvaarten WHERE id_doorvaart = $id"));
        $retArr["data"][0]["cargo"] = $this->getCargos($id);
        $retArr["data"][0]["tax"] = $this->getTaxes($id);
        $retArr["data"][0]["scans"] = $this->getScans($id);
        $retArr["data"][0]["section"] = $this->getSection($retArr["data"][0]["scans"][0]["bestandsnaam"]);
        $retArr["data"][0]["register"] = $this->getRegister($retArr["data"][0]["scans"][0]["bestandsnaam"]);
        $retArr["data"][0]["locations"] = $this->getPlaces($id);
        $retArr["data"][0]["valuta"] = $this->getValuta($id);
        $retArr["data"][0]["units"] = $this->getUnits($id);
        $retArr["data"][0]["vide_list"] = $this->get_vide_list($id);
        $retArr["data"] = $retArr["data"][0];
        return $retArr;
    }

    function get_vide_list($id) {
        $retArray = array();
        $result = $this->ass_arr($this->con->query("SELECT id1 as id FROM `vide` WHERE id2 = $id UNION SELECT id2 as id from vide where id1 = $id"));
        foreach ($result["data"] as $item) {
            $retArray[] = $item["id"];
        }
        return $retArray;
    }

    function is_passage($id) {
        $result = $this->ass_arr($this->con->query("SELECT COUNT(*) as aantal FROM doorvaarten WHERE id_doorvaart = $id"));
        return ($result["data"][0]["aantal"]);

    }

    function getmapInfo($code) {
        $retArr = $this->ass_arr($this->con->query("SELECT Modern_name AS name, region, decLatitude as lat, decLongitude AS `long`, zoom FROM places_standard WHERE Kode = '$code'"));
        return $retArr["data"][0];
    }

    private function getScans($id)
    {
        $results = $this->ass_arr($this->con->query("SELECT bestandsnaam FROM images WHERE id_doorvaart = $id"));
        $buffer = array();
        foreach ($results["data"] as $item) {
            $tempArray = $item;
            $tempArray["url"] = $this->getScanURL($tempArray["bestandsnaam"]);
            $tempArray["iiif"] = $this->getIIIF($tempArray["bestandsnaam"]);
            $buffer[] = $tempArray;
        }
        return $buffer;
    }

    private function getScanURL($scan) {
        $parts = explode("_", $scan);
        return SCAN_HOST . $parts[0] . "/" . $scan;
    }

    private function getIIIF($scan) {
        $parts = explode("_", $scan);
        $fn = $parts[0] . "/" . $scan;
        $result = $this->ass_arr($this->con->query("SELECT uri FROM iiif WHERE filename = '$fn'"));
        return $result["data"][0]["uri"];
    }

    private function getSection($name)
    {
        $results = $this->ass_arr($this->con->query("SELECT s.snaam FROM `scans_totaal` as st, secties_totaal as s WHERE st.bnaam = '$name' AND st.sectienr = s.sectienr"));
        if ($results["number_of_records"] > 0) {
            return $results["data"][0]["snaam"];
        } else {
            return "";
        }

    }

    private function getRegister($name)
    {
        $results = $this->ass_arr($this->con->query("SELECT r.titel FROM `scans_totaal` as s, registers_totaal as r WHERE s.bnaam = '$name' AND s.registernr = r.registernr"));
        if (isset($results["data"][0]["titel"])) {
            return $results["data"][0]["titel"];
        } else {
            return "";
        }

    }

    private function getTaxes($id)
    {
        $results = $this->ass_arr($this->con->query("SELECT * FROM belastingen WHERE id_doorvaart = $id"));
        return $results["data"];
    }

    private function getCargos($id)
    {
        $results = $this->ass_arr($this->con->query("SELECT * FROM ladingen WHERE id_doorvaart = $id"));
        return $results["data"];
    }

    private function getPlaces($id)
    {
        $retArray = array();
        $retArray = $this->getValuesFromPassage($id, "doorvaarten", "schipper_plaatsnaam", $retArray);
        $retArray = $this->getValuesFromPassage($id, "ladingen", "van", $retArray);
        $retArray = $this->getValuesFromPassage($id, "ladingen", "naar", $retArray);
        return $this->getPlacesInfo($retArray);
    }

    private function getUnits($id)
    {
        $retArray = array();
        $retArray = $this->getValuesFromPassage($id, "ladingen", "maat", $retArray);
        $retArray = $this->getValuesFromPassage($id, "ladingen", "maat_alt", $retArray);
        return $this->getStandardUnits($retArray);
    }

    private function getValuta($id)
    {
        $retArray = array();
        $retArray = $this->getValuesFromPassage($id, "ladingen", "muntsoort1", $retArray);
        $retArray = $this->getValuesFromPassage($id, "ladingen", "muntsoort2", $retArray);
        $retArray = $this->getValuesFromPassage($id, "ladingen", "muntsoort3", $retArray);
        $retArray = $this->getValuesFromPassage($id, "belastingen", "muntsoort1", $retArray);
        $retArray = $this->getValuesFromPassage($id, "belastingen", "muntsoort2", $retArray);
        $retArray = $this->getValuesFromPassage($id, "belastingen", "muntsoort3", $retArray);
        return $this->getValutaCodes($retArray);
    }

    private function getPlacesInfo($array)
    {
        $retArray = array();
        foreach ($array as $item) {
            $retArray[] = $this->getPlaceInfo($item);
        }
        return $retArray;
    }

    private function getPlaceInfo($place)
    {
        $escPlace = mysqli_real_escape_string($this->con,  $place);
        $info = $this->ass_arr($this->con->query("SELECT m. Kode, m.Modern_name as mname, m.decLongitude as longitude, m.decLatitude as latitude, m.Zoom as zoom FROM `places_source` as s, places_standard as m WHERE s.place = '$escPlace' AND  s.soundcoding = m.Kode"));
        if ($info["number_of_records"] == 0) {
            return array("code" => "", "name" => $place, "mname" => "", "longitude" => "", "latitude" => "", "zoom" => "");
        } else {
            return array("code" => $info["data"][0]["Kode"], "name" => $place, "mname" => $info["data"][0]["mname"], "longitude" => $info["data"][0]["longitude"], "latitude" => $info["data"][0]["latitude"], "zoom" => $info["data"][0]["zoom"]);
        }
    }

    private function getValutaCodes($array)
    {
        $retArray = array();
        foreach ($array as $item) {
            $retArray[] = $this->getValutaCode($item);
        }
        return $retArray;
    }

    private function getStandardUnits($array)
    {
        $retArray = array();
        foreach ($array as $item) {
            $retArray[] = $this->getStandardUnit($item);
        }
        return $retArray;
    }

    private function getValutaCode($valuta)
    {
        $code = $this->ass_arr($this->con->query("SELECT short, URL FROM currency WHERE Name = '$valuta'"));
        if ($code["number_of_records"] == 0) {
            return array("name" => $valuta, "code" => "", "url" => "");
        } else {
            return array("name" => $valuta, "code" => $code["data"][0]["short"], "url" => $code["data"][0]["URL"]);
        }
    }

    private function getStandardUnit($unit)
    {
        $code = $this->ass_arr($this->con->query("SELECT maat, st_maat FROM maten WHERE maat = '$unit'"));
        if ($code["number_of_records"] == 0) {
            return array("unit" => $unit, "standard_unit" => "");
        } else {
            return array("unit" => $unit, "standard_unit" => $code["data"][0]["st_maat"]);
        }
    }

    private function getValuesFromPassage($id, $table, $field, $valueArray)
    {
        $retArray = $valueArray;
        $sql = "SELECT DISTINCT $field AS value FROM $table WHERE id_doorvaart = $id";
        $result = $this->ass_arr($this->con->query($sql));
        if (count($result["data"])) {
            foreach ($result["data"] as $item) {
                if (!in_array($item["value"], $retArray) && trim($item["value"] != "")) {
                    $retArray[] = $item["value"];
                }
            }
        }
        return $retArray;
    }

    private function ass_arr($results)
    {
        $data = array();
        $retArray = array();

        while ($row = $results->fetch_assoc()) {
            $data[] = $row;
        }
        $retArray["number_of_records"] = count($data);
        $retArray["data"] = $data;
        return $retArray;
    }

}