SET NAMES utf8mb4;

ALTER TABLE `categories`
  MODIFY `name` varchar(100) NOT NULL DEFAULT '',
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

DELETE FROM `categories`;

INSERT INTO `categories` (`id`, `sort`, `name`, `image`) VALUES
  (1,10,'Образы CD/DVD/HD','cat_iso.gif'),
  (4,120,'Разное','cat_other.gif'),
  (5,50,'Игры PC','cat_games.gif'),
  (6,60,'Мультимедиа','cat_mult.gif'),
  (8,80,'PSP / PS2 / PS3 / Xbox','cat_psp.gif'),
  (10,100,'Музыка и аудиокниги','cat_music.gif'),
  (11,110,'Сериалы','cat_serial.gif'),
  (12,120,'Аниме','cat_anime.gif'),
  (13,130,'Фильмы / AVI','cat_avi.gif'),
  (14,140,'Фильмы / HDTV / HD / Blu-Ray','cat_hd-film.gif'),
  (15,150,'DVD / Фильмы','cat_dvd.gif'),
  (16,160,'Книги / PDF / DjVu','cat_book.gif'),
  (18,170,'Клипы / Музыкальное видео','cat_clips.gif'),
  (22,190,'TV / Документальное','cat_tv.gif'),
  (24,30,'Софт Unix / Linux','cat_linux.gif'),
  (25,90,'Картинки / Обои','cat_image.gif'),
  (26,40,'Софт Windows','cat_windows.gif'),
  (27,200,'PDA / Phone / Android / Palm','cat_pda.gif');

ALTER TABLE `categories` AUTO_INCREMENT = 28;
