#!/usr/bin/env php

# Copyright MultiTEL LLC
# Author: Dan Caescu
# Feel free to use this code in however way you like. 
# No warranties of any kind are given. This may or may not work for you.


# - chmod it +x before running
# - change the mysql password
# - run it with or without the channel argument : ./skip.php sfldmidi
<?php
if (!empty($argv[1])) { 
	echo "Channel: ".$argv[1]."\n";
	$channel = $argv[1];
}

$db = mysqli_connect('localhost','root','YOURPASSWORDHERE');

if (isset($channel)) { 
	$q = "SHOW SLAVE STATUS FOR CHANNEL '".$channel."'";
} else { 
	$q = "SHOW SLAVE STATUS";
}

$r = mysqli_query($db,$q);
$row = mysqli_fetch_assoc($r);
$master_uuid = $row['Master_UUID'];
echo "Master UUID: ".$master_uuid."\n";
$slave = $row['Slave_SQL_Running'];
while ($slave == 'No') {
	if (isset($channel)) {  
		$q = "SHOW SLAVE STATUS FOR CHANNEL '".$channel."'";
	} else { 
		$q = "SHOW SLAVE STATUS";
	}

	$r = mysqli_query($db,$q);
	$row = mysqli_fetch_assoc($r);
	$slave = $row['Slave_SQL_Running'];

  # or replace with \n instead of actual newline
	$executed_gtid_set = str_replace('
','',$row['Executed_Gtid_Set']);

	$egtids = explode(',',$executed_gtid_set);
	echo "Retrieved GTIDS:\n";
	print_r($egtids);
	foreach ($egtids as $egtid) { 
		if (strpos($egtid,$master_uuid) !== false) { 
			$pos = explode(":",$egtid);
			$pos = explode("-",$pos[1]);
			echo "Current Position: ".$pos[1]."\n";
			$next =  $pos[1] + 1;
			$q = "SET GTID_NEXT='".$master_uuid.":".$next."'";
			mysqli_query($db,$q);
			$q = "BEGIN; COMMIT; SET GTID_NEXT=AUTOMATIC;";
			mysqli_query($db,$q);
			if (isset($channel)) {
				$q = "START SLAVE FOR CHANNEL '".$channel."'";
			} else { 
				$q = "START SLAVE";
			}
			mysqli_query($db,$q);
		}
	}
  # give it some time to collect new errors
	sleep(1);
}
