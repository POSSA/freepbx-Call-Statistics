<?php

if (!(extension_loaded('gd') && function_exists('gd_info'))) {
    echo "Required dependency is missing.  GD extensions for PHP must be installed for this module to operate.";
	die;
}
	
?>
