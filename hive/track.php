<?php
	require_once("config.php");
	require_once("Db.class.php");
	
	function find_job()
	{	
		$sql = "SELECT job_id, weight FROM job WHERE status = 'active'";
		$jobs = Db::Query($sql);
		$total_weight = 0;
		foreach ($jobs as $job)
		{
			$total_weight += $job['weight'];
		}
		
		if ($total_weight == 0)
		{
			return null;
		}
		
		$a = rand(0, $total_weight);
		foreach ($jobs as $i=>$job)
		{
			$a -= $job['weight'];
			if ($a <= 0)
			{
				break;
			}
		}
		/* if there was no break (why would that happen?), this will default to the last one */
		
		$sql = "SELECT * FROM job WHERE job_id = " . Db::Escape($jobs[$i]['job_id']);
		$job = array_pop(Db::Query($sql));
		
		return $job;
	}
	
	function register_node($node_uuid)
	{
		$sql = "SELECT node_id FROM node WHERE node_uuid = " . Db::Escape($node_uuid);
		$result = Db::Query($sql);
		if (!is_array($result) || count($result) == 0)
		{
			$sql = "INSERT INTO node SET node_uuid = " . Db::Escape($node_uuid) . ", last_seen = UNIX_TIMESTAMP(), total_request_count = 1";
			$node_id = Db::Query($sql);
		}
		else
		{
			$node_id = $result[0]['node_id'];
			
			$sql = "UPDATE node SET  last_seen = UNIX_TIMESTAMP(), total_request_count = total_request_count + 1 WHERE node_uuid = " . Db::Escape($node_uuid);
			Db::Query($sql);
		}
		
		return $node_id;
	}
	
	function update_node_benchmark_points($node_id, $benchmark_points)
	{
		$sql = "UPDATE node SET benchmark_points = " . Db::Escape($benchmark_points) . " WHERE node_id = " . Db::Escape($node_id);
		Db::Query($sql);
	}
	
	function allocate_slice($job, $node_id)
	{
		/* invalidate current slice */
		$sql = "UPDATE slice SET status = 'invalid' WHERE node_id = " . Db::Escape($node_id) . " AND status = 'active'";
		Db::Query($sql);
		
		/* see if there is any slices left for this job */
		$sql = "SELECT COUNT(1) x FROM slice WHERE job_id = " . Db::Escape($job['job_id']) . " AND (status = 'new' OR status = 'invalid')";
		$tmp = Db::Query($sql);
		$slices_left = (int) $tmp[0]['x'];
		
		if ($slices_left == 0)
		{
			return null;
		}
		
		if ($job['slice_allocation_method'] == 'linear')
		{
			/* find the slice with the lowest index */
			$sql = "SELECT * FROM slice WHERE job_id = " . Db::Escape($job['job_id']) . " AND (status = 'new' OR status = 'invalid') ORDER BY slice_id ASC LIMIT 1";
		}
		elseif ($job['slice_allocation_method'] == 'random')
		{
			/* find the slice with a random index */
			$sql = "SELECT * FROM slice WHERE job_id = " . Db::Escape($job['job_id']) . " AND (status = 'new' OR status = 'invalid') ORDER BY RAND() LIMIT 1";
		}
		else
		{
			/* what?! */
			return null;
		}
		
		$tmp = Db::Query($sql);
		if (count($tmp) == 0)
		{
			return null;
		}
		
		$slice = $tmp[0];
		
		$sql = "UPDATE slice SET status = 'active', node_id = " . Db::Escape($node_id) . ", start_time = UNIX_TIMESTAMP() WHERE slice_id = " . Db::Escape($slice['slice_id']);
		Db::Query($sql);
		
		return $slice;
	}
	
	function report_result($node_id, $exit_code, $result_string)
	{
		$sql = "UPDATE slice SET exit_code = " . Db::Escape($exit_code) . ", result_string = " . Db::Escape($result_string)  .", status = 'finished', finish_time = UNIX_TIMESTAMP() WHERE node_id = " . Db::Escape($node_id) . " AND status = 'active' LIMIT 1";
		Db::Query($sql);
	}
	
	$request = $_GET['request'];
	$parameters = $_GET['parameters'];
	$uuid = $_GET['uuid'];
	$nonce = $_GET['nonce'];
	$signature = $_GET['signature'];
	
	$expected_signature = md5($request . "," . $parameters . "," . $uuid . "," . $nonce . "," . SHARED_SECRET);
	
	if ($expected_signature != $signature)
	{
		echo "Invalid signature or shared secret.\n";
		die();
	}
	
	if (!preg_match('/^[a-zA-Z0-9\-\_\+= ,:]*$/', $parameters))
	{
		echo "\"parameters\" is invalid.\n";
		die();
	}
	
	$node_id = register_node($uuid);
	
	switch ($request)
	{
		case "hello":
			list($magic, $benchmark_points) = explode(" ", $parameters);
			update_node_benchmark_points($node_id, (int) $benchmark_points);
			if ($magic != 42)
			{
				$status = "ERROR";
				$response = "And what about the fish?";
			}
			else
			{
				$status = "OK";
				$response = "Hi there!";
			}
		break;
		
		case "ready_to_work":
			$status = "ERROR";
			$response = "Unknown state in ready_to_work.";
			
			$job = find_job();
			if (!$job)
			{
				$status = "OK";
				$response = "sleep 1";
			}
			else
			{
				$slice = allocate_slice($job, $node_id);
				if (is_null($slice))
				{
					$status = "OK";
					$response = "sleep 2";
				}
				else
				{
					$status = "OK";
					$response = $job['script_name'] . " " . $job['script_parameters'] . " " . $slice['script_parameters'];
				}
			}
		break;
		
		case "result":
			if ($tmp = strpos($parameters, " "))
			{
				$exit_code = (int) substr($parameters, 0, $tmp);
				$result_string = substr($parameters, $tmp + 1);
			}
			else
			{
				$exit_code = 255;
				$result_string = $parameters;
			}
			report_result($node_id, $exit_code, $result_string);
			
			$status = "OK";
			$response = "Thanks!";
		break;
		
		default:
			$status = "ERROR";
			$response = "Invalid request.";
		break;
	}
	
	$server_nonce = rand(100000, 999999) . rand(100000, 999999);
	$server_signature = md5($status . "," . $response. "," . $server_nonce . "," . $uuid . "," . SHARED_SECRET);
	
	echo $status . "\n" . $response . "\n" . $server_nonce . "\n" . $server_signature . "\n";
?>
