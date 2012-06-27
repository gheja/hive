#!/bin/sh

finish()
{
	echo $count
	exit 0
}

trap "finish" HUP INT USR1

count=0
while [ 1 ]; do
	count=$((count + 1))
	echo "x" | md5sum > /dev/null 2>&1
done
