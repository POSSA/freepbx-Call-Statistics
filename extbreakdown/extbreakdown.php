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
	// Module Name:		Extension Breakdown
	// Version:			1.0.0
	// Date:			2012-03-21
	// Homepage:		http://github.com/boolah/Call-Statistics/
	// Download:		http://github.com/boolah/Call-Statistics/
	//
	///////////////////////////////////////////////////////////////////////////////////////////////////

	// Initialize the session.  This is needed to build the graphs
	session_start();

	// Read in default configuration
	if ( !isset($_POST["Update"]) ) {
		$extbreakdown_settings = parse_ini_file("extbreakdown.conf");
	}

	// Function to convert seconds to time (hours and/or minutes and/or seconds)
	function sec2time($sec, $format = "m", $padding = true) {
		$hms = "";

		if ($format == "h") {
			// We want the number of hours in decimal format
			$hms = $sec / 3600;
		} elseif ($format == "m") {
			// We want the number of minutes in decimal format
			$hms = $sec / 60;
		} elseif ($format == "hm") {
			// We want hours and minutes in time format (hh:mm)
			$hours = intval($sec / 3600);
			$minutes = intval(($sec / 60) % 60);
			$hms = $hours . ':';
			$hms .= ($padding) ? str_pad($minutes, 2, "0", STR_PAD_LEFT) : $minutes;
		} elseif ($format == "ms") {
			// We want minutes and seconds in time format (mm:ss)
			$minutes = intval($sec / 60);
			$seconds = intval($sec % 60);
			$hms = $minutes . ':';
			$hms .= ($padding) ? str_pad($seconds, 2, "0", STR_PAD_LEFT) : $seconds;
		} else {
			// We want hours, minutes and seconds in time format (hh:mm:ss)
			$hours = intval($sec / 3600);
			$minutes = intval(($sec / 60) % 60);
			$seconds = intval($sec % 60);
			$hms = $hours . ':';
			$hms .= ($padding) ? str_pad($minutes, 2, "0", STR_PAD_LEFT) . ':' : $minutes;;
			$hms .= ($padding) ? str_pad($seconds, 2, "0", STR_PAD_LEFT) : $seconds;
		}

		// Return the formatted time
		return $hms;
	}

	// Retrieve variables from FreePBX in order to connect to the MySQL database
	if(file_exists("/etc/freepbx.conf")) {
        	//This is FreePBX 2.9+
		require("/etc/freepbx.conf");

	    $dbConf["host"]		=	$amp_conf['AMPDBHOST'];
		$dbConf["user"]		=	$amp_conf['AMPDBUSER'];
		$dbConf["pass"]		=	$amp_conf['AMPDBPASS'];
		$dbConf["database"]	=	$amp_conf['AMPDBNAME'];
		$dbConf["engine"]	=	$amp_conf['AMPDBENGINE'];
		$dbConf["cdrdbase"]	=	"asteriskcdrdb";
	} elseif(file_exists("/etc/asterisk/freepbx.conf")) {
		//This is FreePBX 2.9+
		require("/etc/asterisk/freepbx.conf");

	    $dbConf["host"]		=	$amp_conf['AMPDBHOST'];
		$dbConf["user"]		=	$amp_conf['AMPDBUSER'];
		$dbConf["pass"]		=	$amp_conf['AMPDBPASS'];
		$dbConf["database"]	=	$amp_conf['AMPDBNAME'];
		$dbConf["engine"]	=	$amp_conf['AMPDBENGINE'];
		$dbConf["cdrdbase"]	=	"asteriskcdrdb";
	} else {
		//This is FreePBX < 2.9
		require_once "DB.php";
		define("AMP_CONF", "/etc/amportal.conf");

		// Parse the amportal.conf configuration file
		function parse_amportal_conf($filename) {
			$file = file($filename);
			foreach ($file as $line) {
	   			if (preg_match("/^\s*([a-zA-Z0-9_]+)\s*=\s*(.*)\s*([;#].*)?/",$line,$matches)) {
	   	   			$conf[ $matches[1] ] = $matches[2];
	   			}
			}
			return $conf;
		}

	    $amp_conf = parse_amportal_conf(AMP_CONF);
		if (count($amp_conf) == 0) {
			fatal("FAILED");
		}

		// Database config
		$dbConf["host"]		=	(isset($amp_conf["AMPDBHOST"]) ? $amp_conf["AMPDBHOST"] : "localhost");
		$dbConf["user"]		=	(isset($amp_conf["AMPDBUSER"]) ? $amp_conf["AMPDBUSER"] : "asteriskuser");
		$dbConf["pass"]		=	(isset($amp_conf["AMPDBPASS"]) ? $amp_conf["AMPDBPASS"] : "amp109");
		$dbConf["database"]	=	(isset($amp_conf["AMPENGINE"]) ? $amp_conf["AMPENGINE"] : "asterisk");
		$dbConf["engine"]	=	(isset($amp_conf["AMPDBENGINE"]) ? $amp_conf["AMPDBENGINE"] : "");
		$dbConf["cdrdbase"]	=	"asteriskcdrdb";
	}

	// If the database engine is anything other than MySQL, bail out.
	if ($dbConf["engine"] <> "mysql") {
		echo("Only MySQL is supported at this time.");
		exit;
	}

	// Start Date
	if (isset($_POST["startDate"])) {
		$startDate = $_POST["startDate"];
	} else {
		$startDate = date('Y-m-d');
	}

	// End Date
	if (isset($_POST["endDate"])) {
		$endDate = $_POST["endDate"];
	} else {
		$endDate = date('Y-m-d');
	}

	// Set the width of the graph
	if ( isset($_POST["graph_width"]) && ($_POST["graph_width"] >= 750) ) {
		$extbreakdown_settings["graph_width"] = $_POST["graph_width"];
	}

	// Get the minimum number of digits we'll use to filter on.  If no minimum number is specified, use 7.
	if ( isset($_POST["min_num_length"]) && is_numeric($_POST["min_num_length"]) ) {
		$extbreakdown_settings["min_num_length"] = $_POST["min_num_length"];
	}

	// Extension
	if ( isset($_POST["ext"]) ) {
		$extbreakdown_settings["ext"] = $_POST["ext"];
	}

	// Write out the default configuration
	if ( isset($_POST["Update"]) ) {
		$fp = fopen("extbreakdown.conf", "w");
		foreach ($extbreakdown_settings as $key => $value) {
			fwrite($fp, $key . " = " . $value . "\n");
		}
		fclose($fp);
	}

	// Connect to the MySQL database
    $link = mysql_connect($dbConf["host"], $dbConf["user"], $dbConf["pass"])
		or die("Failed to connect to MySQL server at: " . $dbConf["host"]);

	// Select the 'asterisk' database and obtain a list of trunks
	mysql_select_db($dbConf["cdrdbase"], $link)
		or die("Failed to select database: " . $dbConf["database"]);

	$SQLCalls = "SELECT
						IF(LENGTH(dst) = " . strlen($extbreakdown_settings['ext']) . ", dst + 0, SUBSTRING(channel, " . (strlen($extbreakdown_settings['ext']) + 1) . ", " . strlen($extbreakdown_settings['ext']) . ") + 0) AS Extension,
						COUNT(calldate) AS Calls,
						SUM(duration) AS Seconds
					FROM
						cdr
					WHERE
						DATE(calldate) BETWEEN '" . $startDate . "' AND '" . $endDate . "' AND
						(
							billsec > 0 OR
							duration > 0
						) AND
						(
							(
								channel LIKE 'SIP/" . strtr($extbreakdown_settings['ext'], 'X', '_') . "-%' AND
								LENGTH(dst) >= " . $extbreakdown_settings["min_num_length"] . "
							) OR
							(
								LENGTH(src) >= " . $extbreakdown_settings["min_num_length"] . " AND
								LENGTH(dst) = " . strlen($extbreakdown_settings['ext']) . " AND
								dst LIKE '" . strtr($extbreakdown_settings['ext'], 'X', '_') . "'
							)
						)
					GROUP BY
						Extension
					ORDER BY
						Extension";
	$SQLCallsRS = mysql_query($SQLCalls)
		or die("Failed to query the \"" . $dbConf["cdrdbase"] . "\" database.");

	// Populate the $calls hash
	$calls = array();
	while ($data = mysql_fetch_array($SQLCallsRS)) {
			$calls[$data["Extension"]][] = array(
													"calls"		=>	$data["Calls"],
													"seconds"	=>	$data["Seconds"]
												);
	}

	// Close the link to MySQL
	mysql_close($link);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<title>Extension Breakdown</title>

	<link href="../main.css" rel="stylesheet" type="text/css" media="all" />

	<script type="text/javascript" src="calendarDateInput.js"></script>

	<script language="JavaScript" type="text/javascript">
		function formSubmit(myform) {
			// Remove any non-numeric values from the extension number, except for the wildcard character, 'X'
			var cleanedNumber = myform.ext.value.replace(/[^\d,X]/g,'');
			myform.ext.value = cleanedNumber;
		}

		function setDate(myform, period) {
			var curDate = new Date();

			// Set the start and end date depending on the time period we're interested in looking at
			if (period == "Today") {
				myform.startDate.value = '<?php echo(date("Y-m-d")); ?>';
				myform.endDate.value = '<?php echo(date("Y-m-d")); ?>';
			} else if (period == "Yesterday") {
				myform.startDate.value = '<?php echo(date("Y-m-d", strtotime("yesterday"))); ?>';
				myform.endDate.value = '<?php echo(date("Y-m-d", strtotime("yesterday"))); ?>';
			} else if (period == "ThisWeek") {
				if (curDate.getDay() == 1) {
					// If today is Monday
					myform.startDate.value = '<?php echo(date("Y-m-d")); ?>';
					myform.endDate.value = '<?php echo(date("Y-m-d")); ?>';
				} else {
					myform.startDate.value = '<?php echo(date("Y-m-d", strtotime("Last Monday"))); ?>';
					myform.endDate.value = '<?php echo(date("Y-m-d")); ?>';
				}
			} else if (period == "LastWeek") {
				if (curDate.getDay() == 1) {
					// If today is Monday
					myform.startDate.value = '<?php echo(date("Y-m-d", strtotime("Last Monday"))); ?>';
					myform.endDate.value = '<?php echo(date("Y-m-d", strtotime("Last Sunday"))); ?>';
				} else {
					myform.startDate.value = '<?php echo(date("Y-m-d", strtotime("-2 Mondays"))); ?>';
					myform.endDate.value = '<?php echo(date("Y-m-d", strtotime("Last Sunday"))); ?>';
				}
			} else if (period == "ThisMonth") {
 				myform.startDate.value = '<?php echo(date("Y-m-01")); ?>';
				myform.endDate.value = '<?php echo(date("Y-m-d")); ?>';
			} else if (period == "LastMonth") {
				myform.startDate.value = '<?php echo(date("Y-m-d", mktime(0, 0, 0, date("m")-1, 1))); ?>';
				myform.endDate.value = '<?php echo(date("Y-m-d", strtotime("-1 day", strtotime(date("Y-m-01"))))); ?>';
			}
			// Prepare some form variables for submission
			formSubmit(myform);

			// Submit the form and update the data
			myform.submit();
		}
	</script>
</head>

<body>

<form name="frm_calls" method="POST" action="<?php echo($_SERVER["PHP_SELF"]); ?>">
	<fieldset>
		<!--[if !IE]>-->
			<legend>Breakdown of Calls by Extension</legend>
		<!--<![endif]-->

		<table>
			<tr>
				<td colspan="7" id="center">
					<a href="javascript:setDate(document.frm_calls, 'Today');">Today</a> |
					<a href="javascript:setDate(document.frm_calls, 'Yesterday');">Yesterday</a> |
					<a href="javascript:setDate(document.frm_calls, 'ThisWeek');">This Week</a> |
					<a href="javascript:setDate(document.frm_calls, 'LastWeek');">Last Week</a> |
					<a href="javascript:setDate(document.frm_calls, 'ThisMonth');">This Month</a> |
					<a href="javascript:setDate(document.frm_calls, 'LastMonth');">Last Month</a>
				</td>
			</tr>
			<tr>
				<td>
					<label>Start Date:</label>
				</td>
				<td>
					<script>DateInput('startDate', true, 'YYYY-MM-DD', '<?php echo($startDate); ?>')</script>
				</td>
				<td>
					<label>External # Length:</label>
				</td>
				<td>
					<select name="min_num_length">
						<?php
							for ($i = 3; $i <= 12; $i++) {
								if ($i == $extbreakdown_settings["min_num_length"]) {
									echo ("<option value='" . $i . "' selected>" . $i . "</option>");
								} else {
									echo ("<option value='" . $i . "'>" . $i . "</option>");
								}
							}
						?>
					</select>
				</td>
				<td>
					<label>Width</label>
				</td>
				<td>
					<select name="graph_width">
						<?php
							for ($i = 750; $i <= 3000; $i += 250) {
								if ($extbreakdown_settings["graph_width"] == $i) {
									echo("<option value='" . $i . "' selected>" . $i . "</option>");
								} else {
									echo("<option value='" . $i . "'>" . $i . "</option>");
								}
							}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<label>End Date:</label>
				</td>
				<td>
					<script>DateInput('endDate', true, 'YYYY-MM-DD', '<?php echo($endDate); ?>')</script>
				</td>
				<td>
					<label>Extension Format:</label>
				</td>
				<td>
					<input type="text" name="ext" size="6" maxlength="6" value="<?php echo($extbreakdown_settings["ext"]); ?>" />
				</td>
				<td>&nbsp;</td>
				<td>
					<input id="submitBtn" type="submit" name="Update" value="Update" onClick="formSubmit(this.form);" />
				</td>
			</tr>
		</table>
	</fieldset>
</form>

<?php
	$time_distribution = array();
	$call_distribution = array();

	ksort($calls);
	foreach ($calls as $ext => $index_array) {
		foreach ($index_array as $index => $ext_array) {
			$time_distribution[$ext] = $ext_array["seconds"];
			$call_distribution[$ext] = $ext_array["calls"];
		}
	}

	$_SESSION["t_distribution"] = $time_distribution;
	$_SESSION["c_distribution"] = $call_distribution;

	// Create the requested graph
	echo("<p id='center'>");
		echo("<img src='ext_graph.php?startDate=" . $startDate . "&endDate=" . $endDate . "&width=" . $extbreakdown_settings["graph_width"] . "' />");
	echo("</p>");
?>

	<p id="footer">
		<a href="http://github.com/boolah/Call-Statistics/">Call Statistics</a> v0.0.1 BETA RELEASE
	</p>
</body>

</html>
