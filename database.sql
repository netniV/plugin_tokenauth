--
-- Database: `cacti`
--

-- --------------------------------------------------------

--
-- Table structure for table `plugin_debug`
--

CREATE TABLE IF NOT EXISTS `plugin_tokenauth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL DEFAULT '0',
  `enabled` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `token` varchar(3000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `salt` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `user` (`user`),
  KEY `user_enabled` (`user`,`enabled`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Token Authentication'
