ALTER TABLE users
  ADD COLUMN hidecomments enum('yes','no') NOT NULL DEFAULT 'no' AFTER postsperpage,
  ADD COLUMN torrent_columns varchar(255) NOT NULL
    DEFAULT 'category,size,comments,seeders,leechers,uploader,added'
    AFTER hidecomments;
