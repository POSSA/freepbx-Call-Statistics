<?php
// check to see if user has automatic updates enabled in FreePBX settings
if($quietmode && $_REQUEST['ccgraph']) {
	include_once(dirname(__FILE__).'/concurrentcalls/cc_graph.php');
} else {
	$cm =& cronmanager::create($db);
	$online_updates = $cm->updates_enabled() ? true : false;

	// check dev site to see if new version of module is available
	if ($online_updates && $foo = callstatistics_vercheck()) {
		echo "<br>A <b>new version of this module is available</b> from the <a target='_blank' href='http://pbxossa.org'>PBX Open Source Software Alliance</a><br>";
	}
	include_once(dirname(__FILE__).'/concurrentcalls/concurrentcalls.php');
	$module_local = callstatistics_xml2array("modules/callstatistics/module.xml");
	echo $module_local[module][name]." ver: ".$module_local[module][version];
}
?>
