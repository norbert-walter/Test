<?php
//******************************************************************************
// Automation script for water sprinkler
//
// tested with PHP 5.1.2 Win32
//
// Author: Norbert Walter (C) 2016
// norbert-walter@web.de
// 
// Lizenz: GPL V2
//******************************************************************************

//******************************************************************************
// Function for POST requests with data take over
//******************************************************************************
//
// post_request($url, $data, $referer='')
//
// $url		URL for request (name of machine with paht to the page)
//          http://127.0.0.1/volkszaehler.org/htdocs/middleware/data/12345678-1234-1234-1234-123456789012.json
// $data	Data as 1D array with value paars
//			operation => add
//			value => 1
// $referer	Is the referer for the HTTP header
// 
// Return values:
//
// status => ok
// header => header
// content => content
// 
// The function send a HTTP POST request to specified URL and give back a respons
// array
//******************************************************************************

function post_request($url, $data, $referer='')
  {
    // Convert the data array into URL Parameters like a=b&foo=bar etc.
    $data = http_build_query($data);
 
    // parse the given URL
    $url = parse_url($url);
 
    if ($url['scheme'] != 'http') { 
        die('Error: Only HTTP request are supported !');
    }
 
    // extract host and path:
    $host = $url['host'];
    $path = $url['path'];
 
    // open a socket connection on port 80 - timeout: 30 sec
    $fp = fsockopen($host, 80, $errno, $errstr, 30);
 
    if ($fp){
 
        // send the request headers:
        fputs($fp, "POST $path HTTP/1.1\r\n");
        fputs($fp, "Host: $host\r\n");
 
        if ($referer != '')
            fputs($fp, "Referer: $referer\r\n");
 
        fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
        fputs($fp, "Content-length: ". strlen($data) ."\r\n");
        fputs($fp, "Connection: close\r\n\r\n");
        fputs($fp, $data);
 
        $result = ''; 
        while(!feof($fp)) {
            // receive the results of the request
            $result .= fgets($fp, 128);
        }
    }
    else { 
        return array(
            'status' => 'err', 
            'error' => "$errstr ($errno)"
        );
    }
 
    // close the socket connection:
    fclose($fp);
 
    // split the result header from the content
    $result = explode("\r\n\r\n", $result, 2);
 
    $header = isset($result[0]) ? $result[0] : '';
    $content = isset($result[1]) ? $result[1] : '';
 
    // return as structured array:
    return array(
        'status' => 'ok',
        'header' => $header,
        'content' => $content
    );
  }

//******************************************************************************
// Function for sending and saving values in Volkszaehler.org database
//******************************************************************************
//
// send_vzdatabase($url, $uuid, $operation, $value)
//
// $url		URL for request (name of database machine with paht to the middleware)
//          http://127.0.0.1/volkszaehler.org/htdocs/middleware
// $uuid	UUID for sensor
// $operation	Type of operation for database entry
//				Valid operations are: get, add, delete 
// $value	Value to save in database
// 
// Return values:
//
// status => ok
// header => header
// content => content
// 
// The function send a value to volkszehler.org database and saved the value under
// the specified UUID in the database
//******************************************************************************

function send_vzdatabase($url, $uuid, $operation, $value)
  {
   //Save the status for switch in database from volkszaehler.org

   // Submit those variables to the server
   $post_data = array('operation' => $operation,
				      'value' => $value
				     );
 
   // Send a request
   // http://127.0.0.1/volkszaehler.org/htdocs/middleware/data/12345678-1234-1234-1234-123456789012.json
   $send_url = $url.'/data/'.$uuid.'.json';
   echo("\nSend data to VZ-Database:\n\n$send_url\n\n");
   print_r($post_data);
   echo("\n");

   $result = post_request($send_url, $post_data);
 
   if ($result['status'] == 'ok')
     {
      // Print headers 
//    echo $result['header']; 
//    echo '<hr />';
      // print the result of the whole request:
      echo $result['content'];
     }
   else
     {
      echo 'A error occured: ' . $result['error']; 
     }
   return $result;	 
  }

  
//******************************************************************************
//Main program
//******************************************************************************

//Define special debug level
$special_debug = 0; //0: using as data source the URL wetterkontor.de
					//1: using as data source the file wetterkontor.txt

//Define array
$weather = array("0" => array("tmin" => "", "tmax" => "", "sunrise" => "", "sunset" => "", "rain" => "", "wind" => "", "winddir" => "", "symbol" => "",),
		 "1" => array("tmin" => "", "tmax" => "", "sunrise" => "", "sunset" => "", "rain" => "", "wind" => "", "winddir" => "", "symbol" => "",),
		 "2" => array("tmin" => "", "tmax" => "", "sunrise" => "", "sunset" => "", "rain" => "", "wind" => "", "winddir" => "", "symbol" => "",), 
		 "3" => array("tmin" => "", "tmax" => "", "sunrise" => "", "sunset" => "", "rain" => "", "wind" => "", "winddir" => "", "symbol" => "",), 
		);
//print_r($weather);


//Check number of input values
if($argc <= 1)
	{
	 exit("
Syntax error: sprinkler.php <config file>\n
\n
config file: full path to config file sprinkler.conf\n
\n
Sample: php sprinkler.php /etc/sprinkler.conf\n	 
");
	}

//Take over input values
$config = $argv[1];

//Define includes
include $config; //Including config file

if($debug >= 1)
  {
   echo("Debug: $debug\n");
   echo("PLZ: $plz\n");
   echo("City: $city\n");
   echo("Rain: $threshold\n");
   echo("Temp: $temperature\n");
   echo("Day: $day\n");
   echo("On: $command_on\n");
   echo("Off: $command_off\n");
   echo("VZ-URL: $vz_url\n");
   echo("Switch UUID: $switch_uuid\n");
   echo("Rain UUID: $rain_uuid\n");
   echo("Temp UUID: $temp_uuid\n");

  }

//Create URL string

// http://dienste.wetterkontor.de/homepage/homepagewetter.asp?w=160&tc=000000&bc=F4F4F4&hc=33A8D6&htc=FFFFFF&id=de-39104-Magdeburg&fc=137AAB&sb=0&t=1
if($special_debug <> 1)
  {
   $url = 'http://dienste.wetterkontor.de/homepage/homepagewetter.asp?w=160&tc=000000&bc=F4F4F4&hc=33A8D6&htc=FFFFFF&id=de-'.$plz.'-'.$city.'&fc=137AAB&sb=0&t=1';
  }
else
  {
   $url = 'wetterkontor.txt';
  }
  
//Show URL  
if($debug >= 1)
  {
   echo("\nURL:\n\n$url\n\n");
  }

//Read answare as XML string and collect all informations from JavaScript "function SetWeather"
if ($content = file($url))
   {
    if($debug >= 2)
	  {
	   print_r($content);
	  }
	
	//**** Actual Day ****
	$base = 64; //Basis index in $content (startzeile)
	$index = 0; //Day index
	
	//Extract tmin from: document.getElementById('hpwtmin').innerHTML = "-1&deg\;C";
	$info = $content[$base];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '&deg\;C";');
	$diff = $position2 - $position1;
    $weather[$index]['tmin'] = substr($info, $position1, $diff);
	//Extract tmax from: document.getElementById('hpwtmax').innerHTML = "13&deg\;C";
	$info = $content[$base+1];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '&deg\;C";');
	$diff = $position2 - $position1;
    $weather[$index]['tmax'] = substr($info, $position1, $diff);
	//Extract sunrise from: document.getElementById('SA').innerHTML = "06:36";
	$info = $content[$base+2];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['sunrise'] = substr($info, $position1, $diff);
	//Extract sunset from: document.getElementById('SU').innerHTML = "19:57";
	$info = $content[$base+3];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['sunset'] = substr($info, $position1, $diff);
	//Extract rain from: document.getElementById('hpwrain').innerHTML = "15%";
	$info = $content[$base+4];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '%";');
	$diff = $position2 - $position1;
    $weather[$index]['rain'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('FF').innerHTML = "4";
	$info = $content[$base+5];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['wind'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('DD').src= "http://img.wetterkontor.de/symbole/wind/NW.png";
	$info = $content[$base+6];
    $position1 = strpos($info, 'wind/') + 5;
    $position2 = strpos($info, '.png";');
	$diff = $position2 - $position1;
    $weather[$index]['winddir'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('WR').src= "http://img.wetterkontor.de/symbole/106/wolkig.png";
	$info = $content[$base+8];
    $position1 = strpos($info, 'symbole/106/') + 12;
    $position2 = strpos($info, '.png";');
	$diff = $position2 - $position1;
    $weather[$index]['symbol'] = substr($info, $position1, $diff);
	
	//**** Day + 1 ****
	$base = 75; //Basis index in $content (startzeile)
	$index = 1; //Day index
	
	//Extract tmin from: document.getElementById('hpwtmin').innerHTML = "-1&deg\;C";
	$info = $content[$base];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '&deg\;C";');
	$diff = $position2 - $position1;
    $weather[$index]['tmin'] = substr($info, $position1, $diff);
	//Extract tmax from: document.getElementById('hpwtmax').innerHTML = "13&deg\;C";
	$info = $content[$base+1];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '&deg\;C";');
	$diff = $position2 - $position1;
    $weather[$index]['tmax'] = substr($info, $position1, $diff);
	//Extract sunrise from: document.getElementById('SA').innerHTML = "06:36";
	$info = $content[$base+2];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['sunrise'] = substr($info, $position1, $diff);
	//Extract sunset from: document.getElementById('SU').innerHTML = "19:57";
	$info = $content[$base+3];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['sunset'] = substr($info, $position1, $diff);
	//Extract rain from: document.getElementById('hpwrain').innerHTML = "15%";
	$info = $content[$base+4];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '%";');
	$diff = $position2 - $position1;
    $weather[$index]['rain'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('FF').innerHTML = "4";
	$info = $content[$base+5];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['wind'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('DD').src= "http://img.wetterkontor.de/symbole/wind/NW.png";
	$info = $content[$base+6];
    $position1 = strpos($info, 'wind/') + 5;
    $position2 = strpos($info, '.png";');
	$diff = $position2 - $position1;
    $weather[$index]['winddir'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('WR').src= "http://img.wetterkontor.de/symbole/106/wolkig.png";
	$info = $content[$base+8];
    $position1 = strpos($info, 'symbole/106/') + 12;
    $position2 = strpos($info, '.png";');
	$diff = $position2 - $position1;
    $weather[$index]['symbol'] = substr($info, $position1, $diff);	

	//**** Day + 2 ****
	$base = 86; //Basis index in $content (startzeile)
	$index = 2; //Day index
	
	//Extract tmin from: document.getElementById('hpwtmin').innerHTML = "-1&deg\;C";
	$info = $content[$base];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '&deg\;C";');
	$diff = $position2 - $position1;
    $weather[$index]['tmin'] = substr($info, $position1, $diff);
	//Extract tmax from: document.getElementById('hpwtmax').innerHTML = "13&deg\;C";
	$info = $content[$base+1];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '&deg\;C";');
	$diff = $position2 - $position1;
    $weather[$index]['tmax'] = substr($info, $position1, $diff);
	//Extract sunrise from: document.getElementById('SA').innerHTML = "06:36";
	$info = $content[$base+2];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['sunrise'] = substr($info, $position1, $diff);
	//Extract sunset from: document.getElementById('SU').innerHTML = "19:57";
	$info = $content[$base+3];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['sunset'] = substr($info, $position1, $diff);
	//Extract rain from: document.getElementById('hpwrain').innerHTML = "15%";
	$info = $content[$base+4];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '%";');
	$diff = $position2 - $position1;
    $weather[$index]['rain'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('FF').innerHTML = "4";
	$info = $content[$base+5];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['wind'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('DD').src= "http://img.wetterkontor.de/symbole/wind/NW.png";
	$info = $content[$base+6];
    $position1 = strpos($info, 'wind/') + 5;
    $position2 = strpos($info, '.png";');
	$diff = $position2 - $position1;
    $weather[$index]['winddir'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('WR').src= "http://img.wetterkontor.de/symbole/106/wolkig.png";
	$info = $content[$base+8];
    $position1 = strpos($info, 'symbole/106/') + 12;
    $position2 = strpos($info, '.png";');
	$diff = $position2 - $position1;
    $weather[$index]['symbol'] = substr($info, $position1, $diff);	
	
	//**** Day + 3 ****
	$base = 97; //Basis index in $content (startzeile)
	$index = 3; //Day index
	
	//Extract tmin from: document.getElementById('hpwtmin').innerHTML = "-1&deg\;C";
	$info = $content[$base];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '&deg\;C";');
	$diff = $position2 - $position1;
    $weather[$index]['tmin'] = substr($info, $position1, $diff);
	//Extract tmax from: document.getElementById('hpwtmax').innerHTML = "13&deg\;C";
	$info = $content[$base+1];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '&deg\;C";');
	$diff = $position2 - $position1;
    $weather[$index]['tmax'] = substr($info, $position1, $diff);
	//Extract sunrise from: document.getElementById('SA').innerHTML = "06:36";
	$info = $content[$base+2];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['sunrise'] = substr($info, $position1, $diff);
	//Extract sunset from: document.getElementById('SU').innerHTML = "19:57";
	$info = $content[$base+3];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['sunset'] = substr($info, $position1, $diff);
	//Extract rain from: document.getElementById('hpwrain').innerHTML = "15%";
	$info = $content[$base+4];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '%";');
	$diff = $position2 - $position1;
    $weather[$index]['rain'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('FF').innerHTML = "4";
	$info = $content[$base+5];
    $position1 = strpos($info, '= "') + 3;
    $position2 = strpos($info, '";');
	$diff = $position2 - $position1;
    $weather[$index]['wind'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('DD').src= "http://img.wetterkontor.de/symbole/wind/NW.png";
	$info = $content[$base+6];
    $position1 = strpos($info, 'wind/') + 5;
    $position2 = strpos($info, '.png";');
	$diff = $position2 - $position1;
    $weather[$index]['winddir'] = substr($info, $position1, $diff);
	//Extract wind from: document.getElementById('WR').src= "http://img.wetterkontor.de/symbole/106/wolkig.png";
	$info = $content[$base+8];
    $position1 = strpos($info, 'symbole/106/') + 12;
    $position2 = strpos($info, '.png";');
	$diff = $position2 - $position1;
    $weather[$index]['symbol'] = substr($info, $position1, $diff);	
	
	if($debug >= 1)
	  {
	   print_r($weather);
	  }   
   }
else
   {
    exit("Cold not open URL: $url \n");
   }

//Set $switch 1
$switch = 1;

//Calculation when switch is off over actual or more days
echo("\nLimit  Rain:<$threshold %  TMax:>$temperature C\n");
echo("************************************\n");
for($i = 0;$i < $day + 1;$i++)
  {
   if($weather[$i]['rain'] > $threshold)
     {$switch = 0;}
   if($weather[$i]['tmax'] < $temperature)
     {$switch = 0;}
   $r = $weather[$i]['rain'];
   $t = $weather[$i]['tmax'];
   echo("Day$i   Rain: $r %  TMax: $t C  SW: $switch\n");
  }
echo("\nResult: SW = $switch\n");

//Control the hardware depends on switch
if($switch == 1)
  {
   echo("\nCommand: $command_on\n\n");
   exec($command_on);
  }
else
  {
   echo("\nCommand: $command_off\n\n");
   exec($command_off);
  }

//When $switch_uuid was set then save the status for switch in database from volkszaehler.org
if($switch_uuid <> "")
  {
   echo("Saving switch value in VZ-Database");
   send_vzdatabase($vz_url, $switch_uuid, 'add', $switch);
  }
  
//When $rain_uuid was set then save the rain probability in database from volkszaehler.org
if($rain_uuid <> "")
  {
   echo("Saving rain value in VZ-Database");
   send_vzdatabase($vz_url, $rain_uuid, 'add', $weather[0]['rain']);
  }

//When $temp_uuid was set then save the temperature in database from volkszaehler.org
if($temp_uuid <> "")
  {
   echo("Saving temperature value in VZ-Database");
   send_vzdatabase($vz_url, $temp_uuid, 'add', $weather[0]['tmax']);
  }  

echo("\nFinish\n");
?>
