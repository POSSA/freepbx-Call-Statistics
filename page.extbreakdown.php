<?php
// check to see if user has automatic updates enabled in FreePBX settings
$cm =& cronmanager::create($db);
$online_updates = $cm->updates_enabled() ? true : false;

// check dev site to see if new version of module is available
if ($online_updates && $foo = callstatistics_vercheck()) {
	echo "<br>A <b>new version of this module is available</b> from the <a target='_blank' href='http://pbxossa.org'>PBX Open Source Software Alliance</a><br>";
	}

?><iframe width=100% height=600px src="modules/callstatistics/extbreakdown/extbreakdown.php" />
</iframe>
<?php
$module_local = callstatistics_xml2array("modules/callstatistics/module.xml");
echo $module_local[module][name]." ver: ".$module_local[module][version];
?>