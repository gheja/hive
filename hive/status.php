<?php
	require_once("config.php");
	require_once("Db.class.php");
	
	$job_id = (int) $_GET['job_id'];
	$reference_bee_benchmark = 10800;
	$reference_bee_string = "One core of Intel Core i5-2500, 3.30GHz, x86_64/amd64 Debian Squeeze";
	
	function dhms($input, $long = false)
	{
		$d = floor($input / (3600 * 24));
		$h = floor($input % (3600 * 24) / 3600);
		$m = floor($input % 3600 / 60);
		$s = floor($input % 60);
		if (!$long)
		{
			return sprintf("%d days, %d:%02d:%02d", $d, $h, $m, $s);
		}
		else
		{
			return sprintf("%d days %d hours %d minutes", $d, $h, $m);
		}
	}
	
	$sql = "SELECT * FROM job WHERE job_id = " . Db::Escape($job_id);
	$job = array_pop(Db::Query($sql));
	if (!$job)
	{
		echo "Invalid job id.";
		die();
	}
	
	$sql = "SELECT COUNT(1) x FROM slice WHERE finish_time >= UNIX_TIMESTAMP() - 900 AND job_id = " . Db::Escape($job_id);
	$tmp = Db::Query($sql);
	$slices_in_last_15min = $tmp[0]['x'];
	
	$sql = "SELECT node_uuid, total_request_count, benchmark_points, last_seen FROM node WHERE last_seen >= UNIX_TIMESTAMP() - 3600 * 24";
	$nodes = Db::Query($sql);
	$benchmark_points = 0;
	foreach ($nodes as $node)
	{
		$benchmark_points += $node['benchmark_points'];
	}
	
	$sql = "SELECT MAX(finish_time) - MIN(start_time) x FROM slice WHERE start_time IS NOT NULL AND finish_time IS NOT NULL AND job_id = " . Db::Escape($job_id);
	$tmp = Db::Query($sql);
	$total_real_time_passed = dhms($tmp[0]['x']);
	
	$sql = "SELECT SUM(finish_time - start_time) x FROM slice WHERE start_time IS NOT NULL AND finish_time IS NOT NULL AND job_id = " . Db::Escape($job_id);
	$tmp = Db::Query($sql);
	$total_cpu_time_spent = dhms($tmp[0]['x']);
	
	$sql = "SELECT slice_id, status FROM slice WHERE job_id = " . Db::Escape($job['job_id']);
	$slices = Db::Query($sql, "slice_start");
	
	$slices_left = 0;
	foreach ($slices as $slice)
	{
		if ($slice['status'] != 'finished')
		{
			$slices_left++;
		}
	}
	
	if ($slices_in_last_15min == 0)
	{
		$time_left = "unknown";
		$eta = "unknown";
	}
	else
	{
		$tmp = $slices_left / ($slices_in_last_15min / 900);
		$time_left = dhms($tmp, true);
		$eta = date("Y-m-d H:i", time() + $tmp);
	}
?>
<html>
	<head>
		<title>Hive status</title>
		<meta http-equiv="refresh" content="5" />
	<head>
	<body>
		<pre>
<?php
	$j = 0;
	foreach ($slices as $slice)
	{
		if (++$j % 100 == 0)
		{
			echo "\n";
		}
		
		switch ($slice['status'])
		{
			case "new":
				echo ".";
			break;
			
			case "active":
				echo "A";
			break;
			
			case "finished":
				echo "!";
			break;
			
			case "invalid":
				echo "x";
			break;
			
			// unknown
			default:
				echo "?";
			break;
		}
	}
	
	echo "\n";
	echo "\n";
	echo ". new     A active     ! finished     x invalid     ? unknown\n";
	echo "\n";
	echo "Job parameters:\n";
	echo "  - script name: " . $job['script_name'] . "\n";
	echo "  - script parameters: " . $job['script_parameters'] . "\n";
	echo "  - weight: " . $job['weight'] . "\n";
	echo "  - status: " . $job['status'] . "\n";
	echo "  - slices left: " . $slices_left . "\n";
	echo "  - total real time passed: " . $total_real_time_passed . "\n";
	echo "  - total cpu time spent: " . $total_cpu_time_spent . "\n";
	echo "\n";
	echo "Some stats about the last 15 minutes of this job:\n";
	echo "  - slices processed: " . $slices_in_last_15min . "\n";
	echo "  - active nodes: " . count($nodes) . "\n";
	echo "  - total benchmark points: " . $benchmark_points . "\n";
	echo "  - average benchmark points: " . (count($nodes) == 0 ? 0 : round($benchmark_points / count($nodes))) . "\n";
	echo "\n";
	echo "Some estimations for this job:\n";
	echo "  - remaining time: " . $time_left ."\n";
	echo "  - finish: " . $eta . "\n";
	echo "\n";
	echo "Reference Bee for comparisons: " . $reference_bee_string . " (" . $reference_bee_benchmark . " benchmark points)\n";
	echo "\n";
	echo "Globally active nodes in last 24 hours:\n";
	echo htmlspecialchars("last seen  < cluster..... >-< host id..................... >-< instance id................. >-< ver. >  benchmark  power  requests") . "\n";
	foreach ($nodes as $node)
	{
		echo sprintf("%9s  %91s  %9s  %0.02fx  %8s\n", date("H:i:s", $node['last_seen']), $node['node_uuid'], $node['benchmark_points'], $node['benchmark_points'] / $reference_bee_benchmark, $node['total_request_count']);
	}
?>
		</pre>
	</body>
</html>
