SET SESSION sql_mode = '';

ALTER TABLE comments
  MODIFY added datetime NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY editedat datetime NULL DEFAULT NULL;
UPDATE comments SET added = NULL WHERE added = '0000-00-00 00:00:00';
UPDATE comments SET editedat = NULL WHERE editedat = '0000-00-00 00:00:00';

ALTER TABLE invites
  MODIFY time_invited datetime NULL DEFAULT CURRENT_TIMESTAMP;
UPDATE invites SET time_invited = NULL WHERE time_invited = '0000-00-00 00:00:00';

ALTER TABLE news
  MODIFY added datetime NULL DEFAULT CURRENT_TIMESTAMP;
UPDATE news SET added = NULL WHERE added = '0000-00-00 00:00:00';

ALTER TABLE peers
  MODIFY started datetime NULL DEFAULT NULL,
  MODIFY last_action datetime NULL DEFAULT NULL,
  MODIFY prev_action datetime NULL DEFAULT NULL;
UPDATE peers SET started = NULL WHERE started = '0000-00-00 00:00:00';
UPDATE peers SET last_action = NULL WHERE last_action = '0000-00-00 00:00:00';
UPDATE peers SET prev_action = NULL WHERE prev_action = '0000-00-00 00:00:00';

ALTER TABLE polls
  MODIFY added datetime NULL DEFAULT CURRENT_TIMESTAMP;
UPDATE polls SET added = NULL WHERE added = '0000-00-00 00:00:00';

ALTER TABLE ratings
  MODIFY added datetime NULL DEFAULT CURRENT_TIMESTAMP;
UPDATE ratings SET added = NULL WHERE added = '0000-00-00 00:00:00';

ALTER TABLE simpaty
  MODIFY respect_time datetime NULL DEFAULT CURRENT_TIMESTAMP;
UPDATE simpaty SET respect_time = NULL WHERE respect_time = '0000-00-00 00:00:00';

ALTER TABLE snatched
  MODIFY last_action datetime NULL DEFAULT NULL,
  MODIFY startdat datetime NULL DEFAULT NULL,
  MODIFY completedat datetime NULL DEFAULT NULL;
UPDATE snatched SET last_action = NULL WHERE last_action = '0000-00-00 00:00:00';
UPDATE snatched SET startdat = NULL WHERE startdat = '0000-00-00 00:00:00';
UPDATE snatched SET completedat = NULL WHERE completedat = '0000-00-00 00:00:00';

ALTER TABLE torrents
  MODIFY added datetime NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY last_action datetime NULL DEFAULT NULL,
  MODIFY last_mt_update datetime NULL DEFAULT NULL,
  MODIFY last_reseed datetime NULL DEFAULT NULL;
UPDATE torrents SET added = NULL WHERE added = '0000-00-00 00:00:00';
UPDATE torrents SET last_action = NULL WHERE last_action = '0000-00-00 00:00:00';
UPDATE torrents SET last_mt_update = NULL WHERE last_mt_update = '0000-00-00 00:00:00';
UPDATE torrents SET last_reseed = NULL WHERE last_reseed = '0000-00-00 00:00:00';

ALTER TABLE users
  MODIFY added datetime NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY last_login datetime NULL DEFAULT NULL,
  MODIFY last_access datetime NULL DEFAULT NULL,
  MODIFY warneduntil datetime NULL DEFAULT NULL,
  MODIFY birthday date NULL DEFAULT NULL;
UPDATE users SET added = NULL WHERE added = '0000-00-00 00:00:00';
UPDATE users SET last_login = NULL WHERE last_login = '0000-00-00 00:00:00';
UPDATE users SET last_access = NULL WHERE last_access = '0000-00-00 00:00:00';
UPDATE users SET warneduntil = NULL WHERE warneduntil = '0000-00-00 00:00:00';
UPDATE users SET birthday = NULL WHERE birthday = '0000-00-00';

ALTER TABLE users_ban
  MODIFY disuntil datetime NULL DEFAULT NULL;
UPDATE users_ban SET disuntil = NULL WHERE disuntil = '0000-00-00 00:00:00';

SET SESSION sql_mode = DEFAULT;
