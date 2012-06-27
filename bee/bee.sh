#!/bin/sh

### default settings
cluster_id="test_cluster0001" # 16 chars, [a-z0-9_]{16}
shared_secret="1234567890abcdef1234567890abcdef" # 32 chars, [a-z0-9_]{32}
server_url="https://127.0.0.1/hive/track.php"
### end of default settings

### place your configuration in bee.conf (in the above format)
[ -e bee.conf ] && source ./bee.conf

bee_version="bee00001"
tmpfile=`tempfile`
job_request_id=0
pid=$$

validate_settings()
{
	echo "$cluster_id" | grep -Eq '^[a-z0-9_]{16}$'
	if [ $? != 0 ]; then
		echo "ERROR: Invalid Cluster ID - must be exactly 16 characters long, consisting only lowercase letters, numbers and underscore."
		exit 1
	fi
}

calculate_node_id()
{
	kernel_md5=`cat /proc/version 2>/dev/null | md5sum | awk '{ print $1; }'`
	mac_md5=`cat /sys/devices/*/*/*/net/*/address 2>/dev/null | md5sum | awk '{ print $1; }'`
	cpu_md5=`cat /proc/cpuinfo | md5sum | awk '{ print $1; }'`
	mem_total_md5=`cat /proc/meminfo | grep MemTotal | md5sum | awk '{ print $1; }'`
	
	node_id=`echo "$mac_md5,$cpu_md5,$kernel_md5,$mem_total_md5" | md5sum | awk '{ print $1; }'`
}

calculate_instance_id()
{
	pid=$$
	now=`date +%s.%N`
	
	instance_id=`echo "$pid,$now" | md5sum | awk '{ print $1; }'`
}

calculate_uuid()
{
	uuid="$cluster_id-$node_id-$instance_id-$bee_version"
}

calculate_ids()
{
	calculate_node_id
	calculate_instance_id
	calculate_uuid
}

do_benchmark()
{
	./bee_benchmark.sh > $tmpfile 2>&1 &
	tmp_pid=$!
	sleep 5
	kill -USR1 $tmp_pid
	sleep 1
	[ -e /proc/$tmp_pid ] && kill $tmp_pid
	sleep 1
	[ -e /proc/$tmp_pid ] && kill -9 $tmp_pid
	benchmark_points=`cat $tmpfile | tail -n 1`
}

make_request()
{
	request=$1
	parameters=$2
	# "urlencode"
	parameters_url=`echo "$parameters" | sed -e 's/ /%20/g'`

	nonce="$((RANDOM % 899999 + 10000))$((RANDOM % 899999 + 10000))"
	signature=`echo -n "$request,$parameters,$uuid,$nonce,$shared_secret" | md5sum | awk '{ print $1; }'`
	url="$server_url?request=$request&parameters=$parameters_url&uuid=$uuid&nonce=$nonce&signature=$signature"
	
	# echo "Making request: $request, parameters: $parameters, nonce: $nonce, signature: $signature"
	# echo "URL: $url"
	echo "> $request $parameters"
	
	wget --no-check-certificate -O $tmpfile -q "$url"
	cat $tmpfile | wc -l | grep -Eq '^4$'
	if [ $? != 0 ]; then
		echo "ERROR: Incorrectly formatted response from server (an error code message?)"
		cat $tmpfile
		exit 2
	fi
	
	status=`cat $tmpfile | head -n 1`
	response=`cat $tmpfile | tail -n +2 | head -n 1`
	server_nonce=`cat $tmpfile | tail -n +3 | head -n 1`
	server_signature=`cat $tmpfile | tail -n +4 | head -n 1`
	expected_server_signature=`echo -n "$status,$response,$server_nonce,$uuid,$shared_secret" | md5sum | awk '{ print $1; }'`
	
	if [ "x$expected_server_signature" != "x$server_signature" ]; then
		echo "ERROR: Invalid response from server - invalid signature or shared secret."
		echo "  status: $status"
		echo "  response: $response"
		echo "  server_nonce: $server_nonce"
		echo "  server_signature: $server_signature (expected: $expected_server_signature)"
		exit 3
	else
		if [ "x$status" != "xOK" ]; then
			echo "Server reported an error: $status: $response"
			exit 4
		fi
		echo "< $response"
	fi
}

hello()
{
	make_request "hello" "42 $benchmark_points"
}

work()
{
	job_request_id=$((job_request_id + 1))
	make_request "ready_to_work" "$job_request_id"
	echo "$response" | grep -Eq '^[a-zA-Z0-9:_\-\+= ]+$'
	if [ $? != 0 ]; then
		echo "ERROR: Server responded with a suspicious job. The response must be at least one characters long, must contain only alphanumeric letters, colon, minus, plus, equation mark and space."
		exit 5
	fi
	
	./bee_job.sh $response 2>&1 > $tmpfile
	exit_code=$?
	
# 	if [ $exit_code != 0 ]; then
# 		echo "ERROR: bee_job.sh exit code: $exit_code"
# 		cat $tmpfile
# 		exit $exit_code
# 	fi
	
	result=`cat $tmpfile | tail -n 1`
	make_request "result" "$exit_code $result"
	
	return 1
}

validate_settings
calculate_ids

echo "Cluster ID:  $cluster_id"
echo "Node ID:     $node_id"
echo "Instance ID: $instance_id"
echo "PID:         $pid"
echo "Bee version: $bee_version"
echo "UUID:        $uuid"
echo ""
echo "Running benchmark..."
do_benchmark
echo "  $benchmark_points points"
echo ""

hello

while [ 1 ]; do
	work
	if [ $? = 0 ]; then
		break
	fi
done

rm $tmpfile
