<?php
	die("Disabled to prevent accidental run - comment out this line to enable, but please remove the comment afterwards!");
	require_once("config_maintenance.php");
	require_once("Db.class.php");
	
	$job_id = 1;
	
	for ($i=0; $i<120; $i++)
	{
		$sql = "INSERT INTO slice SET script_parameters = " . Db::Escape((string) $i) . ", job_id = " . Db::Escape($job_id) . ", status = 'new'";
		Db::Query($sql);
	}
?>