--
-- Table structure for table `ical_starchapter`
--

CREATE TABLE `ical_starchapter` (
  `host` varchar(64) NOT NULL,
  `event` smallint(6) NOT NULL,
  `hash` char(32) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ical_starchapter`
--
ALTER TABLE `ical_starchapter`
  ADD PRIMARY KEY (`host`,`event`);

