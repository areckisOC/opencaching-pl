<?php

require_once __DIR__ . '/lib/ClassPathDictionary.php';
spl_autoload_register(function ($className) {
    include_once ClassPathDictionary::getClassPath($className);
});
session_start();
//ajaxRetreiveRegionByCoordinates.php
if (!isset($_SESSION['user_id'])) {
    print 'no hacking please!';
    exit;
}
require_once __DIR__ . '/GetRegions.php';

$latitude = $_REQUEST['lat'];
$longitude = $_REQUEST['lon'];

$region = new GetRegions();
$regiony = $region->GetRegion($latitude, $longitude);
echo json_encode($regiony);
//print '<pre>';
//var_dump($regiony);

