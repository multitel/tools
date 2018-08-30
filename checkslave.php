#!/usr/bin/env php

# Copyright MultiTEL LLC
# Author: Dan Caescu
# Feel free to use this code in however way you like. 
# No warranties of any kind are given. This may or may not work for you.

<?php
$debug = 0;
// database
$mysqluser = 'root';
$mysqlpass = 'PASSWORD';
$mysqlhost = 'locahost';

// email definitions
$to = "your@emailaddress";
$from = "yourfrom@emailaddress";

$headers = "From: ".$from." \r\n";
$headers.= "Reply-To: ".$to." \r\n";
$headers.= "X-Mailer: checkslave.php";


$pid = pcntl_fork();
if ($pid === -1) {
	die('Could not fork!');
} elseif ($pid) {
	exit;
	} else {
		if (1==1) { 
			chdir("/root/");

			if ($debug ==1 ) { 
				$STDIN = fopen('/dev/pts/0', 'r');
				$STDOUT = fopen('/dev/pts/0', 'wb');
				$STDERR = fopen('/dev/pts/0', 'wb');
			} else { 
				fclose(STDIN);
				fclose(STDOUT);
				fclose(STDERR);
				$STDIN  = fopen('/dev/null','r');
				$STDOUT = fopen('/dev/null', 'wb');
				$STDERR = fopen('/dev/null', 'wb');
			}

			// make it session leader
			posix_setsid();

			error_reporting(E_STRICT);

			while (1==1) { 

				$db = mysqli_connect($mysqlhost,$mysqluser,$mysqlpass);
				$q = "SHOW SLAVE STATUS";
				$r = mysqli_query($db,$q);
				while ($row=mysqli_fetch_assoc($r)) { 
					if ($row['Slave_SQL_Running']=='No') {
						$master_uuid = $row['Master_UUID'];
						$channel = $row['Channel_Name'];
						echo "Master UUID: ".$master_uuid."\n";
						$slave = $row['Slave_SQL_Running'];
            $hostname = `hostname`;
						$hostname = str_replace('\r\n','',$hostname);
						$hostname = str_replace('\n','',$hostname);
            $subject = "[REPLICATION] ".$hostname." is fixing mysql replication for channel ".$channel;
						$message = $hostname." is fixing mysql replication for channel ".$channel."\n";
						$message.= "Last SQL error: ".$row['Last_Error'];
						mail($to,$subject,$message,$headers);
						$start = time();
						while ($slave == 'No') {
							$q = "SHOW SLAVE STATUS FOR CHANNEL '".$channel."'";
							$x = mysqli_query($db,$q);
							$rowx = mysqli_fetch_assoc($x);
							$slave = $rowx['Slave_SQL_Running'];

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
									$q = "START SLAVE FOR CHANNEL '".$channel."'";
									mysqli_query($db,$q);
								}
							}
							sleep(1);
						}
						$end = time();
						$seconds = $end - $start;
            $subject = "[REPLICATION] ".$hostname." has fixed mysql replication for channel ".$channel;
            $message = $hostname." has fixed mysql replication for channel ".$channel."\n";
            $message.= "Last SQL error: ".$row['Last_Error']."\n";
						$message.= "Repair took: ".$seconds;
            mail($to,$subject,$message,$headers);
					}
				}
				sleep(5);
			}
		}
	}
?>
