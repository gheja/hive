hive (project)
==============

Hive is a distributed computing framework aiming for flexibility and simplicity.

It is consisting of two pieces: the Bee (the client or worker) and the Hive (the server itself with the queen).


The Bee
====

The Bee is the client, the worker, he does the job.

He is implemented in simple shell scripts, making wget requests towards the Queen in the Hive.


The Queen
=========

The Queen is the server, she controls all her little Bees, tell them what needs to be done and collects the results.

She is implemented in PHP and backed with MySQL. From the nature she's made waits for her Bees on HTTP or HTTPS.


Workflow of a Bee (FIXME)
================

* startup
* validation of settings
* calculating the IDs
* running a benchmark
* greeting the Queen ("hello" request with the benchmark result)
* receiving acknowledgement
* starting the endless loop of work-work-work-work-work...
  * asking the Queen if there is something to do ("ready_to_work" request)
  * processing the work she told us (or sleep)
  * sending the results to her ("result" request)


Security (FIXME)
================

* all bees have their unique identifiers (UUID) consisting of:
  * cluster ID (manually set, 16 chars, [0-9a-z_]{16})
  * node/computer ID (calculated, 32 chars, [0-9a-f]{32})
  * instance/process ID (calculated, 32 chars, [0-9a-f]{32})
  * bee version string (manually set, 8 chars, [0-9a-z]{8})

* every incoming and outgoing message is signed
  * using a shared secret key
  * having a random nonce
  * having a signature based on the transmitted data

the IDs should be shortened...
