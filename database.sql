--
-- Database: `cacti`
--

-- --------------------------------------------------------

--
-- Table structure for table `plugin_debug`
--

CREATE TABLE IF NOT EXISTS `plugin_tokenauth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL default '0',
  `enabled` char(2) NOT NULL DEFAULT '',
  `salt` varchar(80) NOT NULL DEFAULT '',
  `token` varchar(300) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `user` (`user`),
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
