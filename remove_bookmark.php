<?php
require_once("include/bittorrent.php");
dbconn();
loggedinorreturn();

$id = isset($_GET["torrent"]) ? (int) $_GET["torrent"] : 0;

if (!is_valid_id($id)) {
    header("Location: bookmarks.php");
    exit;
}

sql_query("DELETE FROM bookmarks WHERE userid = " . $CURUSER['id'] . " AND torrentid = $id") or sqlerr(__FILE__, __LINE__);

header("Location: bookmarks.php?removed=1");
exit;
?>