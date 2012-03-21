<?php
	// Initialize the session.  This is needed to build the graphs
	session_start();

	// Retrieve variables passed from the main script
	$startDate = $_GET["startDate"];
	$endDate = $_GET["endDate"];
	$graph_width = $_GET["width"];

	// Include the main graphing module
	include_once("../jpgraph_lib/jpgraph.php");

	// Function to convert a timestamp (in epoch seconds) to universal time (yyyy-mm-dd hh:mm:ss)
	function date_conv($t_stamp) {
		return(date('Y-m-d H:i:s', $t_stamp));
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

		// We need to include the graphing modules for bar and line graphs
		include_once("../jpgraph_lib/jpgraph_bar.php");
		include_once("../jpgraph_lib/jpgraph_line.php");

		// Get the session variables (array) passed from the main script
		$time_distribution = $_SESSION["t_distribution"];
		$call_distribution = $_SESSION["c_distribution"];

		// If no calls are present in the time period we're looking at, initialize the two arrays so we graph nothing
		if (count($call_distribution) == 0) {
			$call_distribution[0] = 0;
		}
		if (count($time_distribution) == 0) {
			$time_distribution[strtotime($startDate)] = 0;
		}

		// Create the graph
	    $graph = new Graph($graph_width,450);
		$graph->SetMargin(60,60,45,90);
		$graph->SetMarginColor('white');
		$graph->SetScale("textint");
		$graph->SetY2Scale("lin");

		// X-Axis
		$graph->xaxis->SetTickLabels(array_keys($call_distribution));
		$graph->xaxis->title->Set("Extension");
		$graph->xaxis->title->SetFont(FF_FONT1,FS_BOLD);
		$graph->xaxis->title->SetMargin(20);
		$graph->xaxis->HideZeroLabel();
		$graph->xaxis->SetLabelAngle(45);

		// Y-Axis
		$graph->yaxis->scale->SetGrace(10);
		$graph->yaxis->title->Set("Number of Calls");
		$graph->yaxis->title->SetColor("red");
		$graph->yaxis->title->SetFont(FF_FONT1,FS_BOLD);
		$graph->yaxis->title->SetMargin(20);
		$graph->yaxis->SetColor('red');
		$graph->yaxis->HideZeroLabel();

		// Y2-Axis
		$graph->y2axis->title->Set("Time (minutes)");
		$graph->y2axis->title->SetColor('blue');
		$graph->y2axis->title->SetFont(FF_FONT1,FS_BOLD);
		$graph->y2axis->title->SetMargin(20);
		$graph->y2axis->SetColor('blue');
		$graph->SetY2OrderBack(false);

		// Hide the frame around the graph
		$graph->SetFrame(false);

		// Setup title
		$graph->title->Set("Breakdown of Calls by Extension");
		$graph->SetBackgroundGradient('#FFFFFF','#CDDEFF:0.8',GRAD_HOR,BGRAD_PLOT);
		$graph->tabtitle->Set("$startDate - $endDate             " . number_format(array_sum($call_distribution)) . " Total Calls              " . sec2time(array_sum($time_distribution), 'ms') . " Total Minutes");
		$graph->tabtitle->Align("center");
		$graph->tabtitle->SetWidth(TABTITLE_WIDTHFULL);

		// Enable X and Y Grid
		$graph->xgrid->Show();
		$graph->xgrid->SetColor('gray@0.5');
		$graph->ygrid->SetColor('gray@0.5');
		$graph->ygrid->SetFill(true,'#EFEFEF@0.5','#CDDEFF@0.5');

		// Create the Bar Plot
		$bplot = new BarPlot(array_values($call_distribution));
		$bplot->SetWeight(1);
		$bplot->SetFillColor('orange');
		$bplot->SetShadow('black',1,1);

		// Setup how the bar plot will look
		$bplot->value->Show();
		$bplot->value->SetFormat('%d');
		$bplot->value->SetColor('red');
		$bplot->value->SetFont(FF_FONT1,FS_BOLD);

		// Create the Line Plot
		$lplot = new LinePlot(array_map("sec2time", array_values($time_distribution)));
		$lplot->SetWeight(2);
		$lplot->SetColor('blue');
		$lplot->mark->SetType(MARK_FILLEDCIRCLE);
		$lplot->SetBarCenter();

		// Setup how the line plot will look
		$lplot->value->Show();
		$lplot->value->SetColor('blue');
		$lplot->value->SetFont(FF_FONT1,FS_BOLD);
		$lplot->value->SetMargin(20);

		// Add the bar plot, line plot and text box to the graph
		$graph->Add($bplot);
		$graph->AddY2($lplot);

		// Output the graph
		$graph->Stroke();
?>
