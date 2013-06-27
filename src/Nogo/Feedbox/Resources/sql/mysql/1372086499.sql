CREATE TABLE IF NOT EXISTS version (
  key VARCHAR(255) PRIMARY KEY
);

INSERT INTO version VALUES ('1368823260');
INSERT INTO version VALUES ('1372086499');

CREATE TABLE IF NOT EXISTS setting (
  id INTEGER PRIMARY KEY,
  key VARCHAR(255) NOT NULL,
  value TEXT,
  created_at DATETIME,
  updated_at DATETIME
);

CREATE TABLE IF NOT EXISTS tags (
  id INTEGER PRIMARY KEY,
  name varchar(255) NOT NULL UNIQUE,
  color VARCHAR(20) NOT NULL DEFAULT '#000000',
  unread INTEGER DEFAULT 0,
  created_at DATETIME,
  updated_at DATETIME
);

CREATE TABLE IF NOT EXISTS source_tags (
  source_id INTEGER NOT NULL,
  tag_id INTEGER NOT NULL
);