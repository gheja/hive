#!/bin/sh

my_pid=$$

kill_bees()
{
	echo "Oh, signal caught, my little bees..."
	kill $pids
	exit 0
}

trap "kill_bees; exit 0" INT HUP

cpu_count=`cat /proc/cpuinfo | grep -Ec '^processor'`
screen_lines=`tput lines 2>/dev/null`
[ "x$screen_lines" = "x" ] && screen_lines=25

pids=""
i=0
while [ $i -lt $cpu_count ]; do
	./bee.sh 2>&1 > bee_${my_pid}_${i}.log &
	pid=$!
	pids="$pids $pid"
	echo "Spawned bee with pid $pid."
	i=$((i + 1))
done

sleep 1

lines=$(((screen_lines - 2 * cpu_count) / cpu_count))
[ $lines -lt 0 ] && lines=1

while [ 1 ]; do
	tail -n $lines bee_${my_pid}_*.log
	sleep 5
	clear
done
