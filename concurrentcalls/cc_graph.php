<?php
	// Retrieve variables passed from the main script
	$graph_type = $_GET["graph"];
	$graph_width = $_GET["width"];
	$startDate = $_GET["startDate"];
	$endDate = $_GET["endDate"];
	$maxLoad = (is_numeric($_GET["maxLoad"]) ? $_GET["maxLoad"] : 0);

	// Include the main graphing module
	include_once(dirname(dirname(__FILE__))."/jpgraph_lib/jpgraph.php");

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

	if ($graph_type == "concurrentcalls") {
		// We need to include the graphing modules for bar and line graphs
		include_once(dirname(dirname(__FILE__))."/jpgraph_lib/jpgraph_bar.php");
		include_once(dirname(dirname(__FILE__))."/jpgraph_lib/jpgraph_line.php");

		// Get the session variables (array) passed from the main script
		$load_distribution = $_SESSION["l_distribution"];
		$call_duration = $_SESSION["c_duration"];

		// If no calls are present in the time period we're looking at, initialize the two arrays so we graph nothing
		if (count($load_distribution) == 0) {
			$load_distribution[0] = 0;
		} else {
			unset($load_distribution[0]);
		}
		if (count($call_duration) == 0) {
			$call_duration[strtotime($startDate)] = 0;
		}

		// Create the graph
	    $graph = new Graph($graph_width,450);
		$graph->SetMargin(60,60,45,90);
		$graph->SetMarginColor('white');
		$graph->SetScale("textint");
		$graph->SetY2Scale("lin");

		// X-Axis
		$graph->xaxis->SetTickLabels(array_keys($load_distribution));
		$graph->xaxis->title->Set("Number of Concurrent Calls");
		$graph->xaxis->title->SetFont(FF_FONT1,FS_BOLD);
		$graph->xaxis->title->SetMargin(20);
		$graph->xaxis->HideZeroLabel();

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
		$graph->title->Set("Concurrent Calls");
		$graph->SetBackgroundGradient('#FFFFFF','#CDDEFF:0.8',GRAD_HOR,BGRAD_PLOT);
		$graph->tabtitle->Set("$startDate - $endDate                 Maximum Concurrent Calls = $maxLoad");
		$graph->tabtitle->Align("center");
		$graph->tabtitle->SetWidth(TABTITLE_WIDTHFULL);

		// Enable X and Y Grid
		$graph->xgrid->Show();
		$graph->xgrid->SetColor('gray@0.5');
		$graph->ygrid->SetColor('gray@0.5');
		$graph->ygrid->SetFill(true,'#EFEFEF@0.5','#CDDEFF@0.5');

		// Create the Bar Plot
		$bplot = new BarPlot(array_values($load_distribution));
		$bplot->SetWeight(1);
		$bplot->SetFillColor('orange');
		$bplot->SetShadow('black',1,1);

		// Setup how the bar plot will look
		$bplot->value->Show();
		$bplot->value->SetFormat('%d');
		$bplot->value->SetColor('red');
		$bplot->value->SetFont(FF_FONT1,FS_BOLD);

		// Create the Line Plot
		$lplot = new LinePlot(array_map("sec2time", array_values($call_duration)));
		$lplot->SetWeight(2);
		$lplot->SetColor('blue');
		$lplot->mark->SetType(MARK_FILLEDCIRCLE);
		$lplot->SetBarCenter();

		// Setup how the line plot will look
		$lplot->value->Show();
		$lplot->value->SetColor('blue');
		$lplot->value->SetFont(FF_FONT1,FS_BOLD);
		$lplot->value->SetMargin(20);

		// Create the text box to display summary information.
		$txt=new Text(number_format(array_sum($load_distribution)) . " Total Calls\n-----------------------\n" . sec2time(array_sum($call_duration), 'ms') . " Total Minutes");
		$txt->SetPos($graph_width - 245,65);

		$txt->SetFont(FF_FONT1,FS_BOLD);
		$txt->ParagraphAlign('right');
		$txt->SetBox('#FFFFCC','black');
		$txt->SetColor("firebrick");

		// Add the bar plot, line plot and text box to the graph
		$graph->Add($bplot);
		$graph->AddY2($lplot);
		$graph->AddText($txt);

		// Output the graph
		$graph->Stroke();
	} elseif ($graph_type == "cc_over_time") {
		// We need to include the graphing modules for line graphs
		include_once(dirname(dirname(__FILE__))."/jpgraph_lib/jpgraph_line.php");

		// Get the session variables (array) passed from the main script
		$time_distribution = $_SESSION["t_distribution"];

		// If no calls are present in the time period we're looking at, initialize the array so we graph nothing
		if (count($time_distribution) == 0) {
			$time_distribution[strtotime($startDate)] = 0;
			$time_distribution[strtotime($endDate)] = 0;
		}

		// Create the graph
	    $graph = new Graph($graph_width,450);
		$graph->SetMargin(60,10,45,120);
		$graph->SetMarginColor('white');
		$graph->SetScale("textint");

		// Determine the appropriate number of x-axis labels
		if (count($time_distribution) <= 20) {
			$labelInterval = 1;
		} else {
			$labelInterval = count($time_distribution) / 20;
			$graph->xaxis->HideTicks();
		}

		// X-Axis
		$graph->xaxis->SetTickLabels(array_map("date_conv", array_keys($time_distribution)));
		$graph->xaxis->SetLabelAngle(65);
		$graph->xaxis->SetTextLabelInterval($labelInterval);
		$graph->xaxis->title->SetMargin(20);
		$graph->xaxis->HideZeroLabel();

		// Y-Axis
		$graph->yaxis->scale->SetGrace(10);
		$graph->yaxis->title->Set("Number of Concurrent Calls");
		$graph->yaxis->title->SetFont(FF_FONT1,FS_BOLD);
		$graph->yaxis->title->SetMargin(20);
		$graph->yaxis->HideZeroLabel();

		// Hide the frame around the graph
		$graph->SetFrame(false);

		// Setup title
		$graph->title->Set("Concurrent Calls Over Time");
		$graph->SetBackgroundGradient('#FFFFFF','#CDDEFF:0.8',GRAD_HOR,BGRAD_PLOT);
		$graph->tabtitle->Set("$startDate - $endDate                        Maximum Concurrent Calls = $maxLoad");
		$graph->tabtitle->Align("center");
		$graph->tabtitle->SetWidth(TABTITLE_WIDTHFULL);

		// Enable X and Y Grid
		$graph->xgrid->Show(false);
		$graph->ygrid->SetColor('gray@0.5');
		$graph->ygrid->SetFill(true,'#EFEFEF@0.5','#CDDEFF@0.5');

		// Create the line plot
		$lplot = new LinePlot(array_values($time_distribution));

		// Setup how the line plot should look
		$lplot->SetWeight(1);
		$lplot->SetColor('blue');

		// Add the line plot to the graph
		$graph->Add($lplot);

		// Output the graph
		$graph->Stroke();
	} elseif ($graph_type == "cc_breakdown") {
		// We need to include the graphing modules for bar and line graphs
		include_once(dirname(dirname(__FILE__))."/jpgraph_lib/jpgraph_pie.php");

		// Get the session variables (array) passed from the main script
		$load_distribution = $_SESSION["l_distribution"];
		$call_duration = $_SESSION["c_duration"];

		// If no calls are present in the time period we're looking at, initialize the two arrays so we graph nothing
		if (count($load_distribution) == 0) {
			$load_distribution[0] = 1;
		} else {
			unset($load_distribution[0]);
		}
		if (count($call_duration) == 0) {
			$call_duration[strtotime($startDate)] = 1;
		}

		// Define the size of each pie plot within the graph
		$plot_size = 0.30;

		// Create the graph
	    $graph = new PieGraph($graph_width,450,"auto");
		$graph->SetMargin(60,60,45,90);
		$graph->SetMarginColor('white');

		// Setup the title and subtitle for the entire plot
		$graph->title->Set("Breakdown of Concurrent Calls");
		$graph->title->SetFont(FF_FONT2,FS_BOLD);
		$graph->subtitle->Set($startDate . " - " . $endDate);

		// Setup the legend
		$graph->legend->SetLayout(LEGEND_HOR);
		$graph->legend->Pos(0.50,0.99,"center","bottom");

		// Hide the frame around the graph
		$graph->SetFrame(false);

		// Create the pie plot of the breakdown of number of channels used
		$p_channels = new PiePlot(array_values($load_distribution));
		$p_channels->SetLegends(array_keys($load_distribution));
		$p_channels->SetSize($plot_size);
		$p_channels->SetCenter(0.25, 0.50);
		$p_channels->value->Show();
		$p_channels->value->SetFont(FF_FONT1);
		$p_channels->value->SetColor("firebrick");
		$p_channels->title->Set("Number of Calls");
		$p_channels->title->SetColor("firebrick");
		$p_channels->title->SetMargin(10,0,0,0);

		// Create the pie plot of the breakdown of the time spent using a given number of channels
		$p_time = new PiePlot($call_duration);
		$p_time->SetSize($plot_size);
		$p_time->SetCenter(0.75, 0.50);
		$p_time->value->Show();
		$p_time->value->SetFont(FF_FONT1);
		$p_time->value->SetColor("blue");
		$p_time->title->Set("Time Utilization");
		$p_time->title->SetColor("blue");
		$p_time->title->SetMargin(10,0,0,0);

		// Add the pie plots to the graph
		$graph->Add($p_channels);
		$graph->Add($p_time);

		// Output the graph
		$graph->Stroke();
	}
