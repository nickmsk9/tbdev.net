<?php
define('IN_TRACKER', true);

$mysql_link = mysqli_connect('localhost', 'root', '', 'tbdev');

function sql_query(string $query)
{
    global $mysql_link;
    return mysqli_query($mysql_link, $query);
}

function sqlesc(string $value): string
{
    global $mysql_link;
    return "'" . mysqli_real_escape_string($mysql_link, $value) . "'";
}

function sqlerr(string $file = '', int $line = 0): void
{
    global $mysql_link;
    throw new RuntimeException(mysqli_error($mysql_link) . " at $file:$line");
}

function get_date_time(int $timestamp = 0): string
{
    return date('Y-m-d H:i:s', $timestamp ?: time());
}

require __DIR__ . '/include/multitracker.php';
var_export(multitracker_refresh(1));
