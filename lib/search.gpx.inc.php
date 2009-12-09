<?php
	/***************************************************************************
															./lib/search.gpx.inc.php
																-------------------
			begin                : November 1 2005 
			copyright            : (C) 2005 The OpenCaching Group
			forum contact at     : http://www.opencaching.com/phpBB2

		***************************************************************************/

	/***************************************************************************
		*                                         				                                
		*   This program is free software; you can redistribute it and/or modify  	
		*   it under the terms of the GNU General Public License as published by  
		*   the Free Software Foundation; either version 2 of the License, or	    	
		*   (at your option) any later version.
		*
		***************************************************************************/

	/****************************************************************************
		    
		Unicode Reminder ??
                                     				                                
		GPX search output
		
	****************************************************************************/

	global $content, $bUseZip, $sqldebug, $usr, $hide_coords;

	function getPictures($cacheid, $picturescount)
	{
		global $dblink;
		global $thumb_max_width;
		global $thumb_max_height;

		$sql = 'SELECT uuid, title, url, spoiler FROM pictures WHERE object_id=\'' . sql_escape($cacheid) . '\' AND object_type=2 AND display=1 ORDER BY date_created';
		

		$rs = sql($sql);
		while ($r = sql_fetch_array($rs))
		{
			$retval .= '&lt;img src="'.$r['url'].'"&gt;&lt;br&gt;'.cleanup_text($r['title']).'&lt;br&gt;';
		}

		mysql_free_result($rs);
		return $retval;
	}

	$gpxHead = 
'<?xml version="1.0" encoding="utf-8"?>
<gpx xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.topografix.com/GPX/1/0 http://www.topografix.com/GPX/1/0/gpx.xsd http://geocaching.com.au/geocache/1 http://geocaching.com.au/geocache/1/geocache.xsd" xmlns="http://www.topografix.com/GPX/1/0" version="1.0" creator="www.opencaching.pl">
  <desc>Geocache</desc>
  <author>Geocaching</author>
  <url>http://www.opencaching.pl</url>
  <urlname>www.opencaching.pl</urlname>
  <time>{{time}}</time>
';
	
	$gpxLine = 
'
	<wpt lat="{lat}" lon="{lon}">
	<time>{{time}}</time>
	<name>{{waypoint}}</name>
	<desc>{cachename} by {owner}, {type_text} ({difficulty}/{terrain})</desc>
	<src>www.opencaching.pl</src>
	<url>http://www.opencaching.pl/viewcache.php?cacheid={cacheid}</url>
	<urlname>{cachename}</urlname>
	<sym>Geocache</sym>
	<type>Geocache|{geocache_type}</type>
	<geocache status="{status}" xmlns="http://geocaching.com.au/geocache/1">
			<name>{cachename}</name>
			<owner>{owner}</owner>
			<locale></locale>
			<state>{state}</state>
			<country>{country}</country>
			<type>{type}</type>
			<container>{container}</container>
			<difficulty>{difficulty}</difficulty>
			<terrain>{terrain}</terrain>
			<summary html="false">{shortdesc}</summary>
			<description html="true">{desc}{rr_comment}&lt;br&gt;{{images}}</description>
			{hints}
			<licence></licence>
			<logs>
				{logs}
			</logs>
		</geocache>
	</wpt>
';

	$gpxLog = '
<log id="{id}">
	<time>{date}</time>
	<geocacher>{username}</geocacher>
	<type>{type}</type>
	<text>{{text}}</text>
</log>
';

	$gpxFoot = '</gpx>';

	$gpxTimeFormat = 'Y-m-d\TH:i:s\Z';

	$gpxStatus[0] = 'Unavailable'; // andere
	$gpxStatus[1] = 'Available';
	$gpxStatus[2] = 'Unavailable';
	$gpxStatus[3] = 'Archived';
	
	$gpxContainer[0] = 'Other';
	$gpxContainer[2] = 'Micro';
	$gpxContainer[3] = 'Small';
	$gpxContainer[4] = 'Regular';
	$gpxContainer[5] = 'Large';
	$gpxContainer[6] = 'Large';
	$gpxContainer[7] = 'Virtual';

	// known by gpx
	$gpxType[1] = 'Other';
	$gpxType[2] = 'Traditional';
	$gpxType[3] = 'Multi';
	$gpxType[4] = 'Virtual';
	$gpxType[5] = 'Webcam';
	$gpxType[6] = 'Event';
	// unknown ... converted
	$gpxType[7] = 'Multi';
	$gpxType[8] = 'Multi';
	$gpxType[9] = 'Traditional';
	$gpxType[10] = 'Traditional';

	// nazwy skrzynek zgodne z Geocaching / Garmin
	$gpxGeocacheType[1] = 'Unknown Cache';
	$gpxGeocacheType[2] = 'Traditional Cache';
	$gpxGeocacheType[3] = 'Multi-Cache';
	$gpxGeocacheType[4] = 'Virtual Cache';
	$gpxGeocacheType[5] = 'Webcam Cache';
	$gpxGeocacheType[6] = 'Unknown Cache';
	$gpxGeocacheType[7] = 'Multi-cache';
	$gpxGeocacheType[8] = 'Multi-cache';
	$gpxGeocacheType[10] = 'Traditional Cache';

	// nazwy skrzynek do description
	$gpxGeocacheTypeText[1] = 'Unknown Cache';
	$gpxGeocacheTypeText[2] = 'Traditional Cache';
	$gpxGeocacheTypeText[3] = 'Multi-Cache';
	$gpxGeocacheTypeText[4] = 'Virtual Cache';
	$gpxGeocacheTypeText[5] = 'Webcam Cache';
	$gpxGeocacheTypeText[6] = 'Event Cache';
	$gpxGeocacheTypeText[7] = 'Quiz';
	$gpxGeocacheTypeText[8] = 'Moving Cache';
	$gpxGeocacheTypeText[10] = 'Unknown Cache';
	
	$gpxLogType[0] = 'Other';
	$gpxLogType[1] = 'Found';
	$gpxLogType[2] = 'Not Found';
	$gpxLogType[3] = 'Note';

	if( $usr || !$hide_coords )
		{
		//prepare the output
		$caches_per_page = 20;
		
		$sql = 'SELECT '; 
		
		if (isset($lat_rad) && isset($lon_rad))
		{
			$sql .= getSqlDistanceFormula($lon_rad * 180 / 3.14159, $lat_rad * 180 / 3.14159, 0, $multiplier[$distance_unit]) . ' `distance`, ';
		}
		else
		{
			if ($usr === false)
			{
				$sql .= '0 distance, ';
			}
			else
			{
				//get the users home coords
				$rs_coords = sql("SELECT `latitude`, `longitude` FROM `user` WHERE `user_id`='&1'", $usr['userid']);
				$record_coords = sql_fetch_array($rs_coords);
				
				if ((($record_coords['latitude'] == NULL) || ($record_coords['longitude'] == NULL)) || (($record_coords['latitude'] == 0) || ($record_coords['longitude'] == 0)))
				{
					$sql .= '0 distance, ';
				}
				else
				{
					//TODO: load from the users-profile
					$distance_unit = 'km';

					$lon_rad = $record_coords['longitude'] * 3.14159 / 180;   
					$lat_rad = $record_coords['latitude'] * 3.14159 / 180; 

					$sql .= getSqlDistanceFormula($record_coords['longitude'], $record_coords['latitude'], 0, $multiplier[$distance_unit]) . ' `distance`, ';
				}
				mysql_free_result($rs_coords);
			}
		}
		$sql .= '`caches`.`cache_id` `cache_id`, `caches`.`status` `status`, `caches`.`type` `type`, `caches`.`size` `size`, `caches`.`longitude` `longitude`, `caches`.`latitude` `latitude`, `caches`.`user_id` `user_id`
					FROM `caches`
					WHERE `caches`.`cache_id` IN (' . $sqlFilter . ')';
		
		$sortby = $options['sort'];
		if (isset($lat_rad) && isset($lon_rad) && ($sortby == 'bydistance'))
		{
			$sql .= ' ORDER BY distance ASC';
		}
		else if ($sortby == 'bycreated')
		{
			$sql .= ' ORDER BY date_created DESC';
		}
		else // by name
		{
			$sql .= ' ORDER BY name ASC';
		}

		//startat?
		$startat = isset($_REQUEST['startat']) ? $_REQUEST['startat'] : 0;
		if (!is_numeric($startat)) $startat = 0;
		
		if (isset($_REQUEST['count']))
			$count = $_REQUEST['count'];
		else
			$count = $caches_per_page;
		
		$maxlimit = 1000000000;
		
		if ($count == 'max') $count = $maxlimit;
		if (!is_numeric($count)) $count = 0;
		if ($count < 1) $count = 1;
		if ($count > $maxlimit) $count = $maxlimit;

		$sqlLimit = ' LIMIT ' . $startat . ', ' . $count;

		// temporäre tabelle erstellen
		sql('CREATE TEMPORARY TABLE `gpxcontent` ' . $sql . $sqlLimit);

		$rsCount = sql('SELECT COUNT(*) `count` FROM `gpxcontent`');
		$rCount = sql_fetch_array($rsCount);
		mysql_free_result($rsCount);
		
		if ($rCount['count'] == 1)
		{
			$rsName = sql('SELECT `caches`.`wp_oc` `wp_oc`, `caches`.`name` `name` FROM `gpxcontent`, `caches` WHERE `gpxcontent`.`cache_id`=`caches`.`cache_id` LIMIT 1');
			$rName = sql_fetch_array($rsName);
			mysql_free_result($rsName);
			if( $_GET['realname'] == 1 )
				$sFilebasename = str_replace(" ", "", PLConvert('UTF-8','POLSKAWY',$rName['name']));
			else
				$sFilebasename = $rName['wp_oc'];
			
		}
		else {
			if ($options['searchtype'] == 'bywatched') {
				$sFilebasename = 'watched_caches';
			} elseif ($options['searchtype'] == 'bylist') {
				$sFilebasename = 'cache_list';
			} else {
				$rsName = sql('SELECT `queries`.`name` `name` FROM `queries` WHERE `queries`.`id`= &1 LIMIT 1', $options['queryid']);
				$rName = sql_fetch_array($rsName);
				mysql_free_result($rsName);
				if (isset($rName['name']) && ($rName['name'] != '')) {
					$sFilebasename = trim($rName['name']);
					$sFilebasename = str_replace(" ", "_", $sFilebasename);
				} else {
					$sFilebasename = 'ocpl' . $options['queryid'];
				}
			}
		}
			
		$bUseZip = ($rCount['count'] > 50);
		$bUseZip = $bUseZip || (isset($_REQUEST['zip']) && ($_REQUEST['zip'] == '1'));
		$bUseZip = false; // workaround for timeouts with big files
		if ($bUseZip == true)
		{
			$content = '';
			require_once($rootpath . 'lib/phpzip/ss_zip.class.php');
			$phpzip = new ss_zip('',6);
		}

		// ok, ausgabe starten
		
		if ($sqldebug == false)
		{
			if ($bUseZip == true)
			{
				header("content-type: application/zip");
				header('Content-Disposition: attachment; filename=' . $sFilebasename . '.zip');
			}
			else
			{
				header("Content-type: application/gpx");
				header("Content-Disposition: attachment; filename=" . $sFilebasename . ".gpx");
			}
		}
		
		$gpxHead = str_replace('{{time}}', date($gpxTimeFormat, time()), $gpxHead);
		append_output($gpxHead);

		// ok, ausgabe ...
		$rs = sql('SELECT `gpxcontent`.`cache_id` `cacheid`, `gpxcontent`.`longitude` `longitude`, `gpxcontent`.`latitude` `latitude`, `caches`.`wp_oc` `waypoint`, `caches`.`date_hidden` `date_hidden`, `caches`.`picturescount` `picturescount`, `caches`.`name` `name`, `caches`.`country` `country`, `caches`.`terrain` `terrain`, `caches`.`difficulty` `difficulty`, `caches`.`desc_languages` `desc_languages`, `caches`.`size` `size`, `caches`.`type` `type`, `caches`.`status` `status`, `user`.`username` `username`, `cache_desc`.`desc` `desc`, `cache_desc`.`short_desc` `short_desc`, `cache_desc`.`hint` `hint`, `cache_desc`.`rr_comment`, `caches`.`logpw` FROM `gpxcontent`, `caches`, `user`, `cache_desc` WHERE `gpxcontent`.`cache_id`=`caches`.`cache_id` AND `caches`.`cache_id`=`cache_desc`.`cache_id` AND `caches`.`default_desclang`=`cache_desc`.`language` AND `gpxcontent`.`user_id`=`user`.`user_id`');
		while($r = sql_fetch_array($rs))
		{
			$thisline = $gpxLine;
			$lat = sprintf('%01.5f', $r['latitude']);
			$thisline = str_replace('{lat}', $lat, $thisline);
			
			$lon = sprintf('%01.5f', $r['longitude']);
			$thisline = str_replace('{lon}', $lon, $thisline);

			$time = date($gpxTimeFormat, strtotime($r['date_hidden']));
			$thisline = str_replace('{{time}}', $time, $thisline);
			$thisline = str_replace('{{waypoint}}', $r['waypoint'], $thisline);
			$thisline = str_replace('{cacheid}', $r['cacheid'], $thisline);
			$thisline = str_replace('{cachename}', cleanup_text($r['name']), $thisline);
			$thisline = str_replace('{country}', $r['country'], $thisline);
			$thisline = str_replace('{state}', '', $thisline);
			
			if ($r['hint'] == '')
				$thisline = str_replace('{hints}', '', $thisline);
			else
				$thisline = str_replace('{hints}', '<hints>' . cleanup_text($r['hint']) . '</hints>', $thisline);
			
			$logpw = ($r['logpw']==""?"":"UWAGA! W skrzynce znajduje się hasło - pamiętaj o jego zapisaniu!<br>");			
			$thisline = str_replace('{shortdesc}', cleanup_text($r['short_desc']), $thisline);
			$thisline = str_replace('{desc}', cleanup_text($logpw.$r['desc']), $thisline);
			if( $r['rr_comment'] == '' )
				$thisline = str_replace('{rr_comment}', '', $thisline);
			else
				$thisline = str_replace('{rr_comment}', cleanup_text("<br><br>--------<br>".$r['rr_comment']."<br>"), $thisline);
			
			$thisline = str_replace('{{images}}', getPictures($r['cacheid'], false, $r['picturescount']), $thisline);

			if (isset($gpxType[$r['type']]))
				$thisline = str_replace('{type}', $gpxType[$r['type']], $thisline);
			else
				$thisline = str_replace('{type}', $gpxType[1], $thisline);
			
			if (isset($gpxGeocacheType[$r['type']]))
				$thisline = str_replace('{geocache_type}', $gpxGeocacheType[$r['type']], $thisline);
			else
				$thisline = str_replace('{geocache_type}', $gpxGeocacheType[1], $thisline);

			if (isset($gpxGeocacheTypeText[$r['type']]))
				$thisline = str_replace('{type_text}', $gpxGeocacheTypeText[$r['type']], $thisline);
			else
				$thisline = str_replace('{type_text}', $gpxGeocacheTypeText[1], $thisline);

			if (isset($gpxContainer[$r['size']]))
				$thisline = str_replace('{container}', $gpxContainer[$r['size']], $thisline);
			else
				$thisline = str_replace('{container}', $gpxContainer[0], $thisline);
			
			if (isset($gpxStatus[$r['status']]))
				$thisline = str_replace('{status}', $gpxStatus[$r['status']], $thisline);
			else
				$thisline = str_replace('{status}', $gpxStatus[0], $thisline);
			
			$difficulty = sprintf('%01.1f', $r['difficulty'] / 2);
			$thisline = str_replace('{difficulty}', $difficulty, $thisline);

			$terrain = sprintf('%01.1f', $r['terrain'] / 2);
			$thisline = str_replace('{terrain}', $terrain, $thisline);

			$thisline = str_replace('{owner}', xmlentities($r['username']), $thisline);

			// logs ermitteln
			$logentries = '';
			$rsLogs = sql("SELECT `cache_logs`.`id`, `cache_logs`.`type`, `cache_logs`.`date`, `cache_logs`.`text`, `user`.`username` FROM `cache_logs`, `user` WHERE `cache_logs`.`deleted`=0 AND `cache_logs`.`user_id`=`user`.`user_id` AND `cache_logs`.`cache_id`=&1 ORDER BY `cache_logs`.`date` DESC, `cache_logs`.`id` DESC", $r['cacheid']); // adam: removed LIMIT 20
			while ($rLog = sql_fetch_array($rsLogs))
			{
				$thislog = $gpxLog;
				
				$thislog = str_replace('{id}', $rLog['id'], $thislog);
				$thislog = str_replace('{date}', date($gpxTimeFormat, strtotime($rLog['date'])), $thislog);
				$thislog = str_replace('{username}', xmlentities($rLog['username']), $thislog);
				
				if (isset($gpxLogType[$rLog['type']]))
					$logtype = $gpxLogType[$rLog['type']];
				else
					$logtype = $gpxLogType[0];
					
				$thislog = str_replace('{type}', $logtype, $thislog);
													$thislog = str_replace('{{text}}', cleanup_text($rLog['text']), $thislog);
				$logentries .= $thislog . "\n";
				
			}
			$thisline = str_replace('{logs}', $logentries, $thisline);

			append_output($thisline);
		}
		mysql_free_result($rs);
		
		append_output($gpxFoot);

		if ($sqldebug == true) sqldbg_end();
		
		// phpzip versenden
		if ($bUseZip == true)
		{
			$phpzip->add_data($sFilebasename . '.gpx', $content);
			echo $phpzip->save($sFilebasename . '.zip', 'b');
		}
	}
	
	exit;
	
	function xmlentities($str)
	{
		$from[0] = '&'; $to[0] = '&amp;';
		$from[1] = '<'; $to[1] = '&lt;';
		$from[2] = '>'; $to[2] = '&gt;';
		$from[3] = '"'; $to[3] = '&quot;';
		$from[4] = '\''; $to[4] = '&apos;';
		$from[5] = ']]>'; $to[5] = ']] >';

		for ($i = 0; $i <= 4; $i++)
			$str = str_replace($from[$i], $to[$i], $str);

		return filterevilchars($str);
	}

        function cleanup_text($str)
        {
          $str = PLConvert('UTF-8','POLSKAWY',$str);
          $str = strip_tags($str, "<p><br><li>");
          // <p> -> nic
          // </p>, <br> -> nowa linia
          $from[] = '<p>'; $to[] = '';
          $from[] = '</p>'; $to[] = "\n";
          $from[] = '<br>'; $to[] = "\n";
          $from[] = '<br />'; $to[] = "\n";
					$from[] = '<br/>'; $to[] = "\n";
            
          $from[] = '<li>'; $to[] = " - ";
          $from[] = '</li>'; $to[] = "\n";
          
          $from[] = '&oacute;'; $to[] = 'o';
          $from[] = '&quot;'; $to[] = '"';
          $from[] = '&[^;]*;'; $to[] = '';
          
          $from[] = '&'; $to[] = '&amp;';
          $from[] = '<'; $to[] = '&lt;';
          $from[] = '>'; $to[] = '&gt;';
          $from[] = ']]>'; $to[] = ']] >';
					$from[] = ''; $to[] = '';
              
          for ($i = 0; $i < count($from); $i++)
            $str = str_replace($from[$i], $to[$i], $str);
                                 
          return filterevilchars($str);
        }
        
	
        function filterevilchars($str)
	{
		return str_replace('[\\x00-\\x09|\\x0B-\\x0C|\\x0E-\\x1F]', '', $str);
	}
	
	function append_output($str)
	{
		global $content, $bUseZip, $sqldebug;
		if ($sqldebug == true) return;
		
		if ($bUseZip == true)
			$content .= $str;
		else
			echo $str;
			}
	/*
Funkcja do konwersji polskich znakow miedzy roznymi systemami kodowania.
Zwraca skonwertowany tekst.

Argumenty:
$source - string - źródłowe kodowanie
$dest - string - źródłowe kodowanie
$tekst - string - tekst do konwersji

Obsługiwane formaty kodowania to:
POLSKAWY (powoduje zamianę polskich liter na ich łacińskie odpowiedniki)
ISO-8859-2
WINDOWS-1250
UTF-8
ENTITIES (zamiana polskich znaków na encje html)

Przyklad:
echo(PlConvert('UTF-8','ISO-8859-2','Zażółć gęślą jaźń.'));
*/
function PlConvert($source,$dest,$tekst)
{
    $source=strtoupper($source);
    $dest=strtoupper($dest);
    if($source==$dest) return $tekst;

    $chars['POLSKAWY']    =array('a','c','e','l','n','o','s','z','z','A','C','E','L','N','O','S','Z','Z');
    $chars['ISO-8859-2']  =array("\xB1","\xE6","\xEA","\xB3","\xF1","\xF3","\xB6","\xBC","\xBF","\xA1","\xC6","\xCA","\xA3","\xD1","\xD3","\xA6","\xAC","\xAF");
    $chars['WINDOWS-1250']=array("\xB9","\xE6","\xEA","\xB3","\xF1","\xF3","\x9C","\x9F","\xBF","\xA5","\xC6","\xCA","\xA3","\xD1","\xD3","\x8C","\x8F","\xAF");
    $chars['UTF-8']       =array('ą','ć','ę','ł','ń','ó','ś','ź','ż','Ą','Ć','Ę','Ł','Ń','Ó','Ś','Ź','Ż');
    $chars['ENTITIES']    =array('ą','ć','ę','ł','ń','ó','ś','ź','ż','Ą','Ć','Ę','Ł','Ń','Ó','Ś','Ź','Ż');

    if(!isset($chars[$source])) return false;
    if(!isset($chars[$dest])) return false;
    
	$tekst = str_replace('a', 'a', $tekst);
	$tekst = str_replace('é', 'e', $tekst);

    return str_replace($chars[$source],$chars[$dest],$tekst);
}
			
			?>
