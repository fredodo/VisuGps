<?php
/*
Script: mvg_list.php
        Retrieve tracks from the and output a JSON array

License: GNU General Public License

This file is part of VisuGps

VisuGps is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

VisuGps is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with VisuGps; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

Copyright (c) 2008 Victor Berchet, <http://www.victorb.fr>

*/

header('Content-type: text/plain; charset=ISO-8859-1');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

require('mvg_db.inc.php');

$link = mysql_connect(dbHost, dbUser, dbPassword) or die ('Could not connect: ' . mysql_error());
mysql_select_db(dbName) or die ('Database does not exist');

$start = isset($_POST['start'])?intval($_POST['start']):0;
$end = isset($_POST['end'])?intval($_POST['end']):200;
if ($start < 0) $start = 0;
if ($end < $start) $end = $start;
$sort = isset($_POST['sort'])?format_mysql($_POST['sort']):'start';
$dir = isset($_POST['dir'])?(strtoupper($_POST['dir']) == 'ASC'?'ASC':'DESC'):'DESC';

// Set the end time for old flights which haven't received a stop
$query = "SELECT id, end " .
         "FROM flight " .
         "WHERE end IS NULL ";
$result = mysql_query($query) or die('Query error: ' . mysql_error());
if (mysql_num_rows($result)) {
    while ($flight = mysql_fetch_object($result)) {
        $query = "SELECT time, DATEDIFF(CURDATE(), time) AS delta " .
                 "FROM point WHERE flightId=$flight->id " .
                 "HAVING delta > 1 " .
                 "ORDER BY time DESC limit 0,1";
        $result2 = mysql_query($query) or die('Query error: ' . mysql_error());
        if (mysql_num_rows($result2) == 1) {
            $point = mysql_fetch_object($result2);
            $query = "UPDATE flight SET end='$point->time' WHERE id = $flight->id";
            mysql_query($query) or die('Query error: ' . mysql_error());
        }
    }
}

// List flights having more than 5 points
$query = "SELECT name, start, end, flightId, COUNT(latitude) as points " .
         "FROM pilot, flight, point " .
         "WHERE flightId = flight.id AND pilotId = pilot.id GROUP BY flightId " .
         "HAVING points > 5 " .
         "ORDER BY $sort $dir LIMIT $start," . ($end - $start + 1);
$result = mysql_query($query)  or die('Query error: ' . mysql_error());

$tracks['tracks'] = array();

if (mysql_num_rows($result)) {
    while ($row = mysql_fetch_object($result)) {
        $track['name'] = $row->name;
        $track['flightId'] = $row->flightId;
        $track['start']['time'] = $row->start;
        $track['end']['time'] = $row->end;

        $query = "SELECT latitude, longitude FROM point " .
                 "WHERE flightId = $row->flightId " .
                 "ORDER BY point.time ASC " .
                 "LIMIT 0,1";

        if ($result2 = mysql_query($query)) {
            if (mysql_num_rows($result2) == 1) {
                $takeoff = mysql_fetch_object($result2);
                $track['start']['lat'] = $takeoff->latitude;
                $track['start']['lon'] = $takeoff->longitude;
                $track['start']['location'] = getNearbyPlace($takeoff->latitude, $takeoff->longitude);
            }
        }

        $query = "SELECT latitude, longitude FROM point " .
                 "WHERE flightId = $row->flightId " .
                 "ORDER BY point.time DESC " .
                 "LIMIT 0,1";

        if ($result2 = mysql_query($query)) {
            if (mysql_num_rows($result2) == 1) {
                $landing = mysql_fetch_object($result2);
                $track['end']['lat'] = $landing->latitude;
                $track['end']['lon'] = $landing->longitude;
                $track['end']['location'] = getNearbyPlace($landing->latitude, $landing->longitude);
            }
        }

  $tracks['tracks'][] = $track;

  }
}

echo @json_encode($tracks);

function getNearbyPlace($lat, $lon) {
    $url = "http://ws.geonames.org/findNearbyPlaceNameJSON?lat=$lat&lng=$lon";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $data = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($data)->geonames[0];
    $location['place'] = $data->name;
    $location['country'] = strtolower($data->countryCode);
    return $location;
}

function format_mysql($text) {
    if(get_magic_quotes_gpc()) {
        if(ini_get('magic_quotes_sybase')) {
            $text = str_replace("''", "'", $text);
        } else {
            $text = stripslashes($text);
        }
    }
    return mysql_real_escape_string($text);
}

?>
