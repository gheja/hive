#!/bin/sh

job=$1

### a simple md5 hash guesser
if [ "x$job" = "xtest1" ]; then
	correct=$2
	size=$3
	num=$4
	
	i=$((num * size))
	max=$(((num + 1) * size))
	
	while [ $i -lt $max ]; do
		md5=`echo -n "$i" | md5sum | awk '{ print $1; }'`
		if [ "$md5" = "$correct" ]; then
			echo "$i"
			exit 0
		fi
		i=$((i + 1))
	done
	echo "unlucky"
	exit 1

### an example job
elif [ "x$job" = "xjob01" ]; then

	arch=`uname -m`
	if [ ! -e ./jobs/job01_${arch} ]; then
		arch="i386"
	fi
	
	./jobs/job01_${arch} $2 $3 $4
	result=$?
	exit $result

### the sleep job, when there is nothing else to do
elif [ "x$job" = "xsleep" ]; then

	sleep $((RANDOM % 50 + 10))
	exit 0

else

	echo "Invalid job."
	exit 1

fi
