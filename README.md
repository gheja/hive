hive (project)
==============

Hive is a distributed computing framework aiming for flexibility and simplicity.

It is consisting of two pieces: the Bee (the client or worker) and the Hive (the server itself with the queen).


The Bee
-------

The Bee is the client, the worker, he does the job.

He is implemented in simple shell scripts, making wget requests towards the Queen in the Hive.


The Queen
---------

The Queen is the server, she controls all her little Bees, tell them what needs to be done and collects the results.

She is implemented in PHP and backed with MySQL, waiting her Bees on HTTP or HTTPS by default.


Workflow of a Bee
-----------------

FIXME: this section needs expansion...

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


Structure of requests strings sent by the Bee
---------------------------------------------

The request string basically consists of two parts: request and parameters, they are divided by spaces. The request is exactly one word, the parameters can be arbitary long.

Example: "result 34 found it!", "hello 42 9434", "ready_to_work"

Every request will receive a response block in turn.


Structure of a response block sent by the Queen
-----------------------------------------------

The response block consists of... TODO


Requests of a Bee
-----------------

Always the Bee contacts the Queen. The following requests are implemented:
* "hello"
  * parameters: a number (integer, mandatory, always 42), benchmark result (integer, optional)
  * This is the first request that a Bee make, telling the Queen he exists
  * Possible responses: ERROR or OK
  * #1 example request: hello 42 1234
  * #1 example response: OK Hi there!

* "ready_to_work"
  * no parameters
  * When a Bee is ready to work, he sends this request.
  * The Queen sets the last unfinished slice to "invalid" in the Hive and looks for another slice of work to do.
  * Possible responses: ERROR or definition of work to do (job.script_name job.script_parameter slice.script_parameter)
  * #1 example request: ready_to_work
  * #1 example response: OK work01 param1 1122 3456
  * #2 example request: ready_to_work
  * #2 example response: OK sleep

* "result"
  * parameters: exit code (integer, mandatory), result string (string, mandatory)
  * parameters (alternative version): result string (string, mandatory)
  * When the Bee finishes a slice of jobs it reports the result to the Queen.
  * The Queen in turn updates the slice in the Hive.
  * Possible responses: ERROR or OK
  * example request: result 1 unlucky :(
  * example response: OK Thanks!


Security
--------

FIXME: this section needs expansion...

* all bees have their unique identifiers (UUID) consisting of:
  * cluster ID (manually set, 16 chars, [0-9a-z_]{16})
  * node/computer ID (calculated, 32 chars, [0-9a-f]{32})
  * instance/process ID (calculated, 32 chars, [0-9a-f]{32})
  * bee version string (manually set, 8 chars, [0-9a-z]{8})

the IDs should be shortened...

* every incoming and outgoing message is signed
  * using a shared secret key
  * having a random nonce
  * having a signature based on the transmitted data

this way the messages cannot be tampered from outside the cluster (the ones with secret key),
but can be replayed (ie. we should use a time based or a one-time nonce or token)

