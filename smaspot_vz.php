<?php
//******************************************************************************
// This script save data from SMA solar inverter in volkszaehler.org database
// Interprete the data from the CSV output file
//
// tested with PHP 5.1.2 Win32
//
// Author: Norbert Walter (C) 2015
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
//          	http://127.0.0.1/volkszaehler.org/htdocs/middleware/data/12345678-1234-1234-1234-123456789012.json
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
//          	http://127.0.0.1/volkszaehler.org/htdocs/middleware
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

function send_vzdatabase($url, $uuid, $operation, $timestamp, $value)
  {
   // Save the status for switch in database from volkszaehler.org

   // Submit those variables to the server
   $post_data = array('operation' => $operation,
					  'ts' => $timestamp,
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
// Function for converting a SMAspot time string in a unix timestamp
//******************************************************************************
//
// unix_timestamp($timestring)
//
// $timestring	SMAspot timestring (04/10/2013 17:04:10)
// 
// Return values: Time array
//
//		time[month]		Month 2 characters
//		time[day]		Day 2 characters
//		time[year]		Year 4 characters
//		time[hour]		Hour 2 characters (24 format)
//		time[minute]	Minute 2 characters
//		time[second]	Second 2 characters
//		time[stamp]		Timestamp in MySQL format [ms] 13 characters 
//
// The function convert a SMAspot time string in a unix timestamp
//******************************************************************************

function unix_timestamp($timestring)
  {
	//Define array
	$time = array("month" => "01",
				  "day" => "01",
				  "year" => "1970",
		          "hour" => "00",
				  "minute" => "00",
				  "second" => "00",
				 );
	
	// Sepatarte time values
	$time['month'] = substr($timestring, 0, 2);
	$time['day'] = substr($timestring, 3, 2);
	$time['year'] = substr($timestring, 6, 4);
	$time['hour'] = substr($timestring, 11, 2);
	$time['minute'] = substr($timestring, 14, 2);
	$time['second'] = substr($timestring, 17, 2);
	
	// Generate timestamp in ms
	$time['stamp'] = mktime($time[hour], $time[minute], $time[second], $time[month], $time[day], $time[year]) * 1000;
	
	// Debug
	if(debug >= 1)
	  {
	   echo("Month: $time[month]\n");
	   echo("Day: $time[day]\n");
	   echo("Year: $time[year]\n");
	   echo("Hour: $time[hour]\n");
	   echo("Min: $time[minute]\n");
	   echo("Sec: $time[second]\n");
	   echo("Timestamp: $time[stamp]\n");
	  }
	// Return time
	return $time;
  }
//******************************************************************************
// Main program
//******************************************************************************

// Check number of input values
if($argc <= 1)
	{
	 exit("
Syntax error: smaspot_vz.php <config file>\n
\n
config file: full path to config file smaspot_vz.conf\n
\n
Sample: php smaspot_vz.php /etc/smaspot_vz.conf\n	 
");
	}

//Take over input values
$config = $argv[1];	//Path to config file

// Define includes
include $config; //Including config file

// Show the config data
if($debug >= 1)
  {
   echo("Data Directory: $data_directory\n");
   echo("File Type: $file_type\n");
   echo("Delemiter: $delemiter\n");
   echo("Solar Inverter: $model_solar_inverter\n");
   echo("Head Pump: $model_heater_pump\n");
   echo("Voltage: $voltage_heater_pump\n");
   echo("Power Heat Pump: $power_heat_pump\n");
   echo("Power E-Heater: $power_e_heater\n");
   echo("COP: $cop\n");
   echo("On: $command_on\n");
   echo("Off: $command_off\n");
   echo("VZ-URL: $vz_url\n");
   echo("Switch UUID: $switch_uuid\n");
  }

// Get actual date
$time_string = date('m/d/Y H:i:s');
echo("\nActual Date: $time_string\n");  
 
//$time_string ="10/20/2015 23:01:10";
$time_array = unix_timestamp($time_string);
echo("Timestamp [ms]: $time_array[stamp]\n");
  
// Convert all data lines of data file in a array
// ex. path to datafile /opt/SMAspot/data/Test Solar System-Spot-20131004.csv
$filename = $filename = $data_directory.'\Test Solar System-Spot-test.'.$file_type;	// testfile
//$filename = $data_directory.$system_name.'-Spot-'.$actual_date.'.'.$file_type;
echo ("\nPath: $filename\n");
$row = 1;
if (($handle = fopen($filename, "r")) !== FALSE)
  {
    while (($line_data = fgetcsv($handle, 1000, $delemiter)) !== FALSE)
	  {
       // Sepatate name line
	   if($row == 7)
	     {
		  $names = $line_data;
		 }
	   // Separate unit line
	   if($row == 8)
	     {
		  $uname = $line_data;
		  $data_row = 0;
		 } 
	   $num = count($line_data);
	   if($debug >= 2)
	     {
          echo "$num Felder in Zeile $row\n";
		 }
       $row++;
       // Separate all data lines
	   if($row >= 11)
	     {
		  for ($c=0; $c < $num; $c++)
		    {
		     $name = $names[$c];
		     $unit = $uname[$c];
			 // Read only the last data line
			 if($data_handling == 0)
			   {
			    $values[0][$name] = str_replace($decimal_point,'.',$line_data[$c]);
			   }
			 // Read all data lines
			 else
			   {
			    $values[$data_row][$name] = str_replace($decimal_point,'.',$line_data[$c]);
			   }	
			 if($debug >= 2)
	           {
		        echo("$line_data[$c]\n");
			   }
            }
	      $data_row++;
		 }
      }
    fclose($handle);
  }
else
  {
   exit("Cold not open file: $filename \n");
  }
  
if($debug >= 1)
  {
   print_r($values);
  }

// Get array group elements
$array_groupelements = count($values);
echo("Array Group Elemets:  $array_groupelements\n");

// Read and save all group elements
for($i = 0; $i < $array_groupelements; $i++)
  {
	// Sum of all photo voltaic energy phases
	$pvoltaic_power = $values[$i]['GridMs.W.phsA'] + $values[$i]['GridMs.W.phsB'] + $values[$i]['GridMs.W.phsC'];	 

	// Sum of all power consuption from water heater
	$heater_power = $power_heat_pump + $power_e_heater;	
		
	echo("\nSwitch for electrical energy overrange\n");
	echo("**************************************\n");
	echo("Power Head Pump:  $power_heat_pump\n");
	echo("Power E- Heater:  $power_e_heater\n");
	echo("Sum Heater Power: $heater_power\n");
	echo("Power P-Voltaic:  $pvoltaic_power\n");
	echo("\n");

	// Set switch "electrical energy overrange" for water heater boost function
	if($pvoltaic_power > $heater_power)
	  {
	   echo("Power P-Voltaic > Sum Power\n");
	   $switch = 1;
	  }
	else
	  {
	   echo("Power P-Voltaic < Sum Power\n");
	   $switch = 0;
	  }  
	echo("\nSwitch = $switch\n");

	//Control the hardware depends on switch (only when read last data line)
	if($data_handling == 0 && $hardware_control == 1)
	  {
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
	  }

	//When $switch_uuid was set then save the status for switch in database from volkszaehler.org
	if($switch_uuid <> "" && $save_vz == 1)
	  {
	   //TimeStamp ="10/20/2015 23:01:10";
	   $time = unix_timestamp($values[$i]['TimeStamp']); //user time from CSV file
	   echo("\nSaving switch value in VZ-Database");	   
	   send_vzdatabase($vz_url, $switch_uuid, 'add', $time[stamp], $switch);
	  }
  }
echo("\nFinish\n");
?>
