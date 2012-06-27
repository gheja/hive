<?php
	require_once("config_maintenance.php");
	require_once("Db.class.php");
	
	/* mark long abandoned slices as inactive */
	$sql = "UPDATE slice SET status = 'invalid' WHERE status = 'active' and start_time < UNIX_TIMESTAMP() - " . Db::Escape(SLICE_TIMEOUT);
	Db::Query($sql);
	
	/* TODO: close finished jobs */
?>
<html>
	<head>
		<title>Hive maintenance</title>
		<meta http-equiv="refresh" content="60" />
	<head>
	<body>
		Maintenance done on <?php echo date("Y-m-d H:i:s"); ?>.
	</body>
</html>