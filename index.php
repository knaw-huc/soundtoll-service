<?php
require(dirname(__FILE__) . '/config/config.php');
require(dirname(__FILE__) . '/config/db_config.php');
require(dirname(__FILE__) . '/classes/db.class.php');
require(dirname(__FILE__) . '/includes/functions.php');

$URI = $_SERVER["REQUEST_URI"];

$segments = explode('/', $URI);
if (isset($segments[2])) {
    $page = $segments[2];
} else {
    $page = "NULL";
}

switch ($page) {
    case "films":
        films();
        break;
    case "years":
        years();
        break;
    case "passage":
        if (isset($segments[3])) {
            passage($segments[3]);
        } else {
            throw_error();
        }
        break;
    case "places":
        if (isset($segments[3]) && isset($segments[4])) {
            if (!is_numeric($segments[4]) || $segments[4] <= 0) {
                throw_error("Invalid page parameter!");
            } else {
                places($segments[3], $segments[4]);
            }
        } else {
            throw_error("Too little parameters");
        }
        break;
    case "map":
        if (isset($segments[3])) {
            map_info($segments[3]);
        } else {
            throw_error();
        }
        break;
    case "commodities":
        if (isset($segments[3])) {
            commodities($segments[3]);
        } else {
            throw_error("Too little parameters");
        }
        break;
    case "chrnames":
        if (isset($segments[3])) {
            chr_names($segments[3]);
        } else {
            throw_error("Too little parameters");
        }
        break;
    case "patronyms":
        if (isset($segments[3])) {
            patronyms($segments[3]);
        } else {
            throw_error("Too little parameters");
        }
        break;
    case "hist_places":
        if (isset($segments[3]) && isset($segments[4])) {
            if (!is_numeric($segments[4]) || $segments[4] <= 0) {
                throw_error("Invalid page parameter!");
            } else {
                hist_places($segments[3], $segments[4]);
            }
        } else {
            throw_error("Too little parameters");
        }
        break;
    case "shipmasters":
        if (isset($segments[3]) && isset($segments[4])) {
            if (!is_numeric($segments[4]) || $segments[4] <= 0) {
                throw_error("Invalid page parameter!");
            } else {
                shipmasters($segments[3], $segments[4]);
            }
        } else {
            throw_error("What the fuck?");
        }
        break;
    case "currency":
        currency();
        break;
    case "small_regions":
        get_regions("small");
        break;
    case "big_regions":
        get_regions("big");
        break;
    case "elastic":
        if (isset($segments[3])) {
            switch($segments[3]) {
                case "initial_facet":
                    if (isset($segments[4]) && isset($segments[5])) {
                        get_initial_facets($segments[4], $segments[5]);
                    } else {
                        throw_error();
                    }
                    break;
                case "facet":
                    if (isset($segments[4]) && isset($segments[5]) && isset($segments[6])) {
                        get_facets($segments[4], $segments[6], $segments[5]);
                    } else {
                        throw_error();
                    }
                    break;
                case "nested_facet":
                    if (isset($segments[4]) && isset($segments[5])) {
                        if (isset($segments[6]))
                        {
                            get_nested_facets($segments[4], $segments[5], strtolower($segments[6]));
                        } else {
                            get_nested_facets($segments[4], $segments[5]);
                        }

                    } else {
                        throw_error();
                    }
                    break;
                case "search":
                    if (isset($segments[4])) {
                        search($segments[4]);
                    }
                    else {
                        throw_error();
                    }
                    break;
                default:
                    throw_error();
                    break;
            }

        } else {
            throw_error();
        }
        break;
    default:
        throw_error();
        break;
}