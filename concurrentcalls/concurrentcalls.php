<?php
	///////////////////////////////////////////////////////////////////////////////////////////////////
	//
	// This program is free software; you can redistribute it and/or
	// modify it under the terms of the GNU General Public License
	// as published by the Free Software Foundation; either version 2
	// of the License, or (at your option) any later version.
	//
	// This program is distributed in the hope that it will be useful,
	// but WITHOUT ANY WARRANTY; without even the implied warranty of
	// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	// GNU General Public License for more details.
	//
	// Module Name:		Concurrent Calls
	// Version:			2.1.1
	// Date:			2012-03-07
	// Homepage:		https://github.com/POSSA/freepbx-Call-Statistics
	//
	///////////////////////////////////////////////////////////////////////////////////////////////////

	// Read in default configuration
	if ( !isset($_POST["Update"]) ) {
		$concurrentcalls_settings = parse_ini_file("concurrentcalls.conf");
	}

	$dbConf["host"]		=	$amp_conf['AMPDBHOST'];
	$dbConf["user"]		=	$amp_conf['AMPDBUSER'];
	$dbConf["pass"]		=	$amp_conf['AMPDBPASS'];
	$dbConf["database"]	=	$amp_conf['AMPDBNAME'];
	$dbConf["engine"]	=	$amp_conf['AMPDBENGINE'];
	$dbConf["cdrdbase"]	=	"asteriskcdrdb";

	// If the database engine is anything other than MySQL, bail out.
	if ($dbConf["engine"] <> "mysql") {
		echo("Only MySQL is supported at this time.");
		exit;
	}

	// Process form variables
	// Determine if we are in debug mode
	if ( isset($_POST["debug"]) && (($_POST["debug"] == 0) || ($_POST["debug"] == 1)) ) {
		$debug = $_POST["debug"];
	} else {
		$debug = 0;
	}

	// Get the start date and time of the time period we're interested in.  If no start date and time are specified,
	// use today's date and start at midnight.
	if (isset($_POST["startDate"])) {
		$startDate = $_POST["startDate"];
	} else {
		$startDate = date('Y-m-d') . " 00:00:00";
	}

	// Get the end date and time of the time period we're interested in.  If no end date and time are specified,
	// use today's date and end at 23:59:59.
	if (isset($_POST["endDate"])) {
		$endDate = $_POST["endDate"];
	} else {
		$endDate = date('Y-m-d') . " 23:59:59";
	}

	// Get the minimum number of digits we'll use to filter on.  If no minimum number is specified, use 7.
	if ( isset($_POST["min_num_length"]) && is_numeric($_POST["min_num_length"]) ) {
		$concurrentcalls_settings["min_num_length"] = $_POST["min_num_length"];
	}

	// Get the source or destination number that we're filtering on.  If no number is specified, or an invalid number
	// is entered, unset the variable and do not filter on anything.  If we're filtering with wildcards, change the
	// standard * wildcard to SQL compatible %.
	if ( isset($_POST["number"]) && is_numeric($_POST["number"]) ) {
		$number = $_POST["number"];
	} elseif ( isset($_POST["number"]) && (strpos($_POST["number"], "*") !== FALSE) ) {
		$number = str_replace("*", "%", $_POST["number"]);
	} else {
		unset($number);
	}

	// Get the type of graph we're interested in.
	if ( isset($_POST["graph_type"]) ) {
		$concurrentcalls_settings["graph_type"] = $_POST["graph_type"];
	}

	// Set the width of the graph
	if ( isset($_POST["graph_width"]) && ($_POST["graph_width"] >= 750) ) {
		$concurrentcalls_settings["graph_width"] = $_POST["graph_width"];
	}

	// Get the trunk that we're filtering on.  If no trunk is defined, use a trunk value of '0' to not filter
	// on a trunk
	if ( isset($_POST["trunk"]) && is_numeric($_POST["trunk"]) ) {
		$trunk = $_POST["trunk"];
	} else {
		$trunk = 0;
	}

	// Define the hash to hold the changes in concurrent calls.  The primary key of this hash will be the timestamps
	// of when a call is started or ended.  The secondary key of the has will simply be an index number.  The value
	// of the hash will an array including an integer representing if the calls is beginning (+1) or ending (-1).
	$calls = array();

	// Connect to the MySQL database
	$link = mysql_connect($dbConf["host"], $dbConf["user"], $dbConf["pass"])
		or die("Failed to connect to MySQL server at: " . $dbConf["host"]);

	// Select the 'asterisk' database and obtain a list of trunks
	mysql_select_db($dbConf["database"], $link)
		or die("Failed to select database: " . $dbConf["database"]);

	// query to get all SIP trunks
	$SQLTrunks = "SELECT
					`name`,`channelid`
				FROM
					trunks
				WHERE
					disabled LIKE 'off' AND
					(
						tech LIKE 'sip'
					)
				ORDER BY
					trunkid";
	$SQLTrunksRS = mysql_query($SQLTrunks)
		or die("Failed to query the \"" . $dbConf["database"] . "\" database.");

	// Populate the $trunks array
	$trunks[0] = "";  // first entry is blank

	// Add SIP trunks to $trunks array
	while ($data = mysql_fetch_array($SQLTrunksRS)) {
		$trunks[] = array(
			'channelid' => "SIP/".$data["channelid"]."-",
			'name' => $data["name"]." (sip)",
		);
	}
	// query to get all IAX2 trunks
	$SQLTrunks = "SELECT
					`name`,`channelid`
				FROM
					trunks
				WHERE
					disabled LIKE 'off' AND
					(
						tech LIKE 'iax'
					)
				ORDER BY
					trunkid";
	$SQLTrunksRS = mysql_query($SQLTrunks)
		or die("Failed to query the \"" . $dbConf["database"] . "\" database.");

	// Add IAX2 trunks to $trunks array
	while ($data = mysql_fetch_array($SQLTrunksRS)) {
		$trunks[] = array(
			'channelid' => "IAX2/".$data["channelid"]."-",
			'name' => $data["name"]." (iax)",
		);
	}
	// query to get all DAHDI trunks 
	$SQLTrunks = "SELECT
					`name`,`channelid`
				FROM
					`trunks`
				WHERE
					`disabled` LIKE 'off' AND
					
					tech LIKE 'dahdi'
					
				ORDER BY
					trunkid";
	$SQLTrunksRS = mysql_query($SQLTrunks)
		or die("Failed to query the \"" . $dbConf["database"] . "\" database.");

	// Add DAHDI trunks to $trunks array
	while ($data = mysql_fetch_array($SQLTrunksRS)) {
		$trunks[] = array(
			'channelid' => $data["channelid"],   // treat this one differently until we find out if channel or group
			'name' => $data["name"]." (dahdi)",
			'dahdi' => true,
		);
	}
	
	// Add menu selections for trunk technologies
	$trunks[] = array(
			'channelid' => "IAX2/",
			'name' => "All IAX2 Trunks",
		);

	$trunks[] = array(
			'channelid' => "SIP/",
			'name' => "All SIP Trunks",
		);

	$trunks[] = array(
			'channelid' => "DAHDI/",
			'name' => "All DAHDI Trunks",
		);
	// support for ZAP removed 2014-01-25
	//	$trunks[] = "ZAP/";

	// can not query cdr for DAHDI groups directly, only DAHDI channels. If DAHDI $channelid is all numeric, it's a channel
	// if DAHDI $channelid has non-numeric chars, it is a group so query asterisk for all channels in the group and build 
	// regex that will match all channels
	if ($trunks[$trunk]['dahdi'] == true) {
		if (is_numeric($trunks[$trunk]['channelid'])) {
			$trunks[$trunk]['channelid'] = "DAHDI/".$trunks[$trunk]['channelid']."-";
		}
		else {
			// channel ID id has non-numeric chars, it is a DAHDI group, build regex to match all channels in group
			$chans = callstatistics_group2chan($trunks[$trunk]['channelid']);
			$trunks[$trunk]['channelid'] = "^DAHDI/[";
			foreach ($chans as $chan) {
				$trunks[$trunk]['channelid'] .= "$chan|";
			}
			$trunks[$trunk]['channelid'] .= "]-";
			$dahdi_group = true;
		}
	}

	// build channel search query, regexp for dahdi groups, all other channels get search by like
	if ($dahdi_group) {
		// note that $trunks is not escaped for MySQL regex, preg_quote seemed to break REGEXP search, not sure how to escape a mysql regexp
		$channel_search = "channel REGEXP '" . $trunks[$trunk]['channelid'] . "' OR dstchannel REGEXP '" . $trunks[$trunk]['channelid']."' ";
	} 
	else {
		$channel_search = "channel LIKE '" . $trunks[$trunk]['channelid'] . "%' OR dstchannel LIKE '" . $trunks[$trunk]['channelid'] . "%'";
	}


	// Write out the default configuration
	if ( isset($_POST["Update"]) ) {
		$fp = fopen("concurrentcalls.conf", "w");
		foreach ($concurrentcalls_settings as $key => $value) {
			fwrite($fp, $key . " = " . $value . "\n");
		}
		fclose($fp);
	}

	// Select the database
	mysql_select_db($dbConf["cdrdbase"], $link)
		or die("Failed to select database: " . $dbConf["cdrdbase"]);

	// Define the SQL Query
	$SQLCalls = "SELECT
					UNIX_TIMESTAMP(calldate) AS stime,
					UNIX_TIMESTAMP(calldate) + duration AS etime,
					calldate,
					src,
					dst,
					clid,
					dcontext,
					channel,
					dstchannel,
					duration,
					billsec,
					uniqueid
				FROM
					cdr
				WHERE
					calldate >= '" . $startDate . "' AND
					calldate <= '" . $endDate . "' AND
					(
						billsec > 0 OR
						duration > 0
					) AND
					(
						LENGTH(src) >= " . $concurrentcalls_settings["min_num_length"] . " OR
						LENGTH(dst) >= " . $concurrentcalls_settings["min_num_length"] . "
					) AND
					(
					   ".$channel_search."
					)";
					if (isset($number)) {
						$SQLCalls .= " AND
										(
											TRIM(src) LIKE '" . $number . "' OR
											TRIM(dst) LIKE '" . $number . "'
										)";
					}
	$SQLCalls .= " ORDER BY
	               	stime ASC";
	$SQLCallsRS = mysql_query($SQLCalls)
		or die("Failed to query the \"" . $dbConf["cdrdbase"] . "\" database.");

	// Populate the $calls array
	while ($data = mysql_fetch_array($SQLCallsRS)) {
		if ( (strlen($data["src"]) >= $concurrentcalls_settings["min_num_length"]) || (is_numeric($data["dst"])) ) {
			$calls[$data["stime"]][] = array(
											"load"			=>	1,
											"calldate"		=>	$data["calldate"],
											"src"			=>	$data["src"],
											"dst"			=>	$data["dst"],
											"clid"			=>	$data["clid"],
											"dcontext"		=>	$data["dcontext"],
											"channel"		=>	$data["channel"],
											"dstchannel"	=>	$data["dstchannel"],
											"duration"		=>	$data["duration"],
											"billsec"		=>	$data["billsec"],
											"uniqueid"		=>	$data["uniqueid"]
											);

			$calls[$data["etime"]][] = array(
											"load"			=>	-1,
											"calldate"		=>	$data["calldate"],
											"src"			=>	$data["src"],
											"dst"			=>	$data["dst"],
											"clid"			=>	$data["clid"],
											"dcontext"		=>	$data["dcontext"],
											"channel"		=>	$data["channel"],
											"dstchannel"	=>	$data["dstchannel"],
											"duration"		=>	$data["duration"],
											"billsec"		=>	$data["billsec"],
											"uniqueid"		=>	$data["uniqueid"]
											);
		}
	}

	// Close the link to MySQL
	mysql_close($link);

	// Sort the $calls array by its keys.  This is the same as sorting all calls by their epoch timestamps,
	// since the keys of the $calls array are the start and stop times of each call in epoch time.
	ksort($calls);

	// If debug is set, generate a CSV file with the appropriate data.
	if ($debug) {
		header("Content-type: application/vnd.ms-excel");
		header("Content-disposition: attachment; filename=concurrentcalls.csv");
		header("Pragma: public");
		header("Cache-control: must-revalidate");
		header("Expires: 0");

		// Generate the debug info
		$debug_load = 0;
		$debug_output = "Date,Number of Concurrent Calls,Maximum Number of Concurrent Calls,Source,Caller ID,Channel,Destination,Destination Context,Destination Channel,Duration,Billable Duration,Unique ID\n";
		$debug_maxLoad = 0;
		foreach ($calls as $timestamp => $index_array) {
			ksort($index_array);
			foreach ($index_array as $index => $calldetails) {
			$debug_load += $calldetails["load"];

			if ($debug_maxLoad < $debug_load) {
				$debug_maxLoad = $debug_load;
			}
				$debug_output .= "\"" . $calldetails["calldate"] . "\",\"" . $debug_load . "\",\"" . $debug_maxLoad . "\",\"" . $calldetails["src"] . "\",\"" . $calldetails["clid"] . "\",\"" . $calldetails["channel"] . "\",\"" . $calldetails["dst"] . "\",\"" . $calldetails["dcontext"] . "\",\"" . $calldetails["dstchannel"] . "\",\"" . $calldetails["duration"] . "\",\"" . $calldetails["billsec"] . "\",\"" . $calldetails["uniqueid"] . "\"\n";
			}
		}

		echo($debug_output);
		exit;
	}

	// Switch the % character back to *
	if (isset($number)) {
		$number = str_replace("%", "*", $_POST["number"]);
	}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">

<head>
	<title>Concurrent Calls</title>

	<link href="../main.css" rel="stylesheet" type="text/css" media="all" />

	<script language="JavaScript" type="text/javascript">

		function formSubmit(cc_form) {
			// Debug checkbox
			if (cc_form.debugChkBox.checked) {
				cc_form.debug.value = '1';
			} else {
				cc_form.debug.value = '0';
			}

			// Remove any non-numeric values from the source/destination number filter field,
			// except the wildcard (*) character.
			var cleanedNumber = cc_form.number.value.replace(/[^\d]\*/g,'');
			cc_form.number.value = cleanedNumber;
		}

		function setDate(cc_form, period) {
			var curDate = new Date();

			// Set the start and end date depending on the time period we're interested in looking at
			if (period == "Today") {
				cc_form.startDate.value = '<?php echo(date("Y-m-d 00:00:00")); ?>';
				cc_form.endDate.value = '<?php echo(date("Y-m-d 23:59:59")); ?>';
			} else if (period == "Yesterday") {
				cc_form.startDate.value = '<?php echo(date("Y-m-d 00:00:00", strtotime("yesterday"))); ?>';
				cc_form.endDate.value = '<?php echo(date("Y-m-d 23:59:59", strtotime("yesterday"))); ?>';
			} else if (period == "ThisWeek") {
				if (curDate.getDay() == 1) {
					// If today is Monday
					cc_form.startDate.value = '<?php echo(date("Y-m-d 00:00:00")); ?>';
					cc_form.endDate.value = '<?php echo(date("Y-m-d 23:59:59")); ?>';
				} else {
					cc_form.startDate.value = '<?php echo(date("Y-m-d 00:00:00", strtotime("Last Monday"))); ?>';
					cc_form.endDate.value = '<?php echo(date("Y-m-d 23:59:59")); ?>';
				}
			} else if (period == "LastWeek") {
				if (curDate.getDay() == 1) {
					// If today is Monday
					cc_form.startDate.value = '<?php echo(date("Y-m-d 00:00:00", strtotime("Last Monday"))); ?>';
					cc_form.endDate.value = '<?php echo(date("Y-m-d 23:59:59", strtotime("Last Sunday"))); ?>';
				} else {
					cc_form.startDate.value = '<?php echo(date("Y-m-d 00:00:00", strtotime("-2 Mondays"))); ?>';
					cc_form.endDate.value = '<?php echo(date("Y-m-d 23:59:59", strtotime("Last Sunday"))); ?>';
				}
			} else if (period == "ThisMonth") {
 				cc_form.startDate.value = '<?php echo(date("Y-m-01 00:00:00")); ?>';
				cc_form.endDate.value = '<?php echo(date("Y-m-d 23:59:59")); ?>';
			} else if (period == "LastMonth") {
				cc_form.startDate.value = '<?php echo(date("Y-m-d 00:00:00", mktime(0, 0, 0, date("m")-1, 1))); ?>';
				cc_form.endDate.value = '<?php echo(date("Y-m-d 23:59:59", strtotime("-1 day", strtotime(date("Y-m-01"))))); ?>';
			}
			// Prepare some form variables for submission
			formSubmit(cc_form);

			// Submit the form and update the data
			cc_form.submit();
		}

		//alert("Iframe Width = " + window.innerWidth?window.innerWidth:document.body.clientWidth);
	</script>
</head>

<body>
<form name="frm_concurrentcalls" method="post" >

		<fieldset>
			<!--[if !IE]>-->
				<legend>Parameters</legend>
			<!--<![endif]-->

			<input type="hidden" name="debug" />
			<input type="hidden" name="graph_width" />

			<table>
				<tr>
					<td colspan="6" id="center">
						<a href="javascript:setDate(document.frm_concurrentcalls, 'Today');">Today</a> |
						<a href="javascript:setDate(document.frm_concurrentcalls, 'Yesterday');">Yesterday</a> |
						<a href="javascript:setDate(document.frm_concurrentcalls, 'ThisWeek');">This Week</a> |
						<a href="javascript:setDate(document.frm_concurrentcalls, 'LastWeek');">Last Week</a> |
						<a href="javascript:setDate(document.frm_concurrentcalls, 'ThisMonth');">This Month</a> |
						<a href="javascript:setDate(document.frm_concurrentcalls, 'LastMonth');">Last Month</a>
					</td>
				</tr>
				<tr>
					<td><label>Start Date</label></td>
			  		<td><input type="text" name="startDate" size="20" maxlength="19" value="<?php echo($startDate); ?>" /></td>
					<td><label>Trunk</label></td>
					<td>
						<select name="trunk">
						<?php
							for ($i = 0; $i < sizeof($trunks); $i++) {
								if ($trunk == $i) {
									echo("<option value='" . $i . "' selected>" . $trunks[$i]['name'] . "</option>");
								} else {
									echo("<option value='" . $i . "'>" . $trunks[$i]['name'] . "</option>");
								}
							}
						?>
						</select>
					</td>
					<td><label>Debug</label></td>
					<td>
			        <?php
						if ($debug) {
							echo("<input type='checkbox' value='" . $debug . "' name='debugChkBox' checked />");
						} else {
							echo("<input type='checkbox' value='" . $debug . "' name='debugChkBox' />");
						}
					?>
					</td>
				</tr>
				<tr>
					<td><label>End Date</label></td>
			  		<td><input type="text" name="endDate" size="20" maxlength="19" value="<?php echo($endDate); ?>" /></td>
					<td><label>Number</label></td>
					<td><input type="text" name="number" size="25" maxlength="25" value="<?php if (isset($number)) { echo($number); } ?>" /></td>
					<td><label>Width</label></td>
					<td>
						<select name="graph_width">
							<?php
								for ($i = 750; $i <= 3000; $i += 250) {
									if ($concurrentcalls_settings["graph_width"] == $i) {
										echo("<option value='" . $i . "' selected>" . $i . "</option>");
									} else {
										echo("<option value='" . $i . "'>" . $i . "</option>");
									}
								}
							?>
						</select>
					</td>
				</tr>
					<td><label>Graph Type</label></td>
					<td>
                        <select name="graph_type">
						<?php
							if ($concurrentcalls_settings["graph_type"] == "concurrentcalls") {
								echo("<option value='concurrentcalls' selected>Concurrent Calls</option>");
								echo("<option value='cc_over_time'>Concurrent Calls Over Time</option>");
								echo("<option value='cc_breakdown'>Breakdown of Concurrent Calls</option>");
							} elseif ($concurrentcalls_settings["graph_type"] == "cc_over_time") {
								echo("<option value='concurrentcalls'>Concurrent Calls</option>");
								echo("<option value='cc_over_time' selected>Concurrent Calls Over Time</option>");
								echo("<option value='cc_breakdown'>Breakdown of Concurrent Calls</option>");
							} elseif ($concurrentcalls_settings["graph_type"] == "cc_breakdown") {
								echo("<option value='concurrentcalls'>Concurrent Calls</option>");
								echo("<option value='cc_over_time'>Concurrent Calls Over Time</option>");
								echo("<option value='cc_breakdown' selected>Breakdown of Concurrent Calls</option>");
							}
						?>
			 			</select>
					</td>
					<td><label>Min # Length</label></td>
					<td>
						<select name="min_num_length">
							<?php
								for ($i = 3; $i <= 12; $i++) {
									if ($i == $concurrentcalls_settings["min_num_length"]) {
										echo ("<option value='" . $i . "' selected>" . $i . "</option>");
									} else {
										echo ("<option value='" . $i . "'>" . $i . "</option>");
									}
								}
							?>
						</select>
					</td>
					<td colspan="2" id="center"><input id="submitBtn" name="Update" type="submit" value="Update" onClick="formSubmit(this.form);" /></td>
				<tr>

				</tr>
			</table>
		</fieldset>
	</form>

	<?php
		// Initialize some variables which will be used in processing the concurrent calls
		$load				= 0;			// Number of concurrent calls at any given time
		$maxLoad			= 0;			// Maximum number of concurrent calls up to the given time
		$p_tstamp			= 0;			// Timestamp in epoch time for the preceding key of the $calls array
		$p_load				= 0;			// Number of concurrent calls for the preceding value of the $calls array
		$load_distribution	= array();		// Array, indexed by the number of concurrent calls, valued with the number
											// of calls at the given number of concurrent calls
		$call_duration		= array();		// Array, indexed by the number of concurrent calls, valued with the duration,
											// in seconds, timestamp of the previous call to present
		$time_distribution	= array();		// Array, indexed by call timestamps, valued with the load at the indexed timestamp

		// Traverse the $calls array examining each key/value pair.  We can then calculate the number of concurrent
		// calls at any given time and obtain the maximum number of concurrent calls throughout the time period we're
		// looking at.
		ksort($calls);
		foreach ($calls as $timestamp => $index_array) {
			ksort($index_array);
			foreach ($index_array as $index => $calldetails) {
				// The current load will be what the previous load was plus the change in load
				$load += $calldetails["load"];

				// Build the time_distribution array
				$time_distribution[$timestamp] = $load;

				// If the key for the $load_distribution array has not been set, create it and set the value to one.  Otherwise,
				// increment the number of calls at the current load.
				if (isset($load_distribution[$load])) {
					if ($calldetails["load"] > 0) {
						$load_distribution[$load]++;
					}
				} else {
					$load_distribution[$load] = 1;
				}

				// If the key for the $call_duration array has not been set and the current load is not zero, create it
				// and set the value to zero.
				if ( !isset($call_duration[$load]) && ($load <> 0) ) {
					$call_duration[$load] = 0;
				}

				// If the number of concurrent calls for the preceding call was not zero (e.g. the previous call ended, leaving
				// no calls in the system), calculate the duration of time spent from the preceding call to this call.  The
				if ($p_load <> 0) {
					$call_duration[$p_load] += ($timestamp - $p_tstamp) * $p_load;
				}

				// If the current number of concurrent calls is greater than the number of concurrent calls we've seen
				// up to now, set $maxLoad to the current number of concurrent calls.
				if ($maxLoad < $load) {
					$maxLoad = $load;
				}

				// Set the number of concurrent calls and timestamp for the preceding call to the current call
				$p_load = $load;
				$p_tstamp = $timestamp;
			}
		}

		// Sort the $load_distribution, $time_distrubution and $call_duration arrays by their respective keys
		ksort($load_distribution);
		ksort($time_distribution);
		ksort($call_duration);

		// Create session variables out of the $load_distribution, $call_duration and $time_distribution arrays.  This is
		// necessary to pass the arrays to the graphing routines.
		$_SESSION["l_distribution"] 	= $load_distribution;
		$_SESSION["c_duration"] 		= $call_duration;
		$_SESSION["t_distribution"] 	= $time_distribution;

		// Create the requested graph
		echo("<p id='center'>");
			echo("<img src='?display=concurrentcalls&quietmode=1&ccgraph=1&graph=" . $concurrentcalls_settings["graph_type"] . "&startDate=" . $startDate . "&endDate=" . $endDate . "&maxLoad=" . $maxLoad . "&width=" . $concurrentcalls_settings["graph_width"] . "' />");
		echo("</p>");
	?>
	<p id="footer">
		<a href="https://github.com/POSSA/freepbx-Call-Statistics">Call Statistics</a> <?php echo ($version_name); ?>
	</p>
</body>

</html>
