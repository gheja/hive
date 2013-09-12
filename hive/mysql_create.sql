CREATE TABLE `job` (
  `job_id` int(10) unsigned NOT NULL auto_increment,
  `script_name` char(32) NOT NULL,
  `script_parameters` char(64) NOT NULL,
  `slice_allocation_method` enum('linear','random') NOT NULL default 'random',
  `weight` int(10) unsigned NOT NULL,
  `status` enum('new','active','finished','disabled') NOT NULL default 'new',
  PRIMARY KEY  (`job_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

CREATE TABLE `node` (
  `node_id` int(10) unsigned NOT NULL auto_increment,
  `node_uuid` char(92) NOT NULL,
  `last_seen` int(10) unsigned NOT NULL,
  `total_request_count` int(10) unsigned NOT NULL default '0',
  `benchmark_points` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`node_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

CREATE TABLE `slice` (
  `slice_id` int(10) unsigned NOT NULL auto_increment,
  `job_id` int(10) unsigned NOT NULL COMMENT '->job.job_id',
  `node_id` int(10) unsigned default NULL COMMENT '->node.node_id',
  `script_parameters` char(64) NOT NULL,
  `status` enum('new','active','invalid','finished') NOT NULL default 'new',
  `start_time` int(10) unsigned default NULL,
  `finish_time` int(10) unsigned default NULL,
  `exit_code` int(11) default NULL,
  `result_string` char(32) default NULL,
  PRIMARY KEY  (`slice_id`),
  KEY `status` (`status`),
  KEY `node_id` (`node_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;



