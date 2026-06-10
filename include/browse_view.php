<?php
if (!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}

$browseLetters = array_merge(
    range('A', 'Z'),
    ['А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ы', 'Э', 'Ю', 'Я']
);
$currentSort = (string)($_GET['sort'] ?? '4');
$currentDirection = strtolower((string)($_GET['type'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$sortOptions = [
    '4' => 'По дате добавления',
    '1' => 'По названию',
    '11' => 'По категории',
    '5' => 'По размеру',
    '2' => 'По количеству файлов',
    '3' => 'По комментариям',
    '7' => 'По количеству сидов',
    '8' => 'По количеству пиров',
    '12' => 'По просмотрам',
    '13' => 'По скачиваниям',
];
?>
<style type="text/css">
.browse-page{margin:0 auto 16px}
.browse-toolbar{overflow:hidden;border:1px solid #cbd5df;border-radius:5px;background:#f5f7f9}
.browse-title{display:flex;align-items:center;justify-content:space-between;padding:9px 13px;background:#607d8b;color:#fff;font-weight:bold}
.browse-title a{color:#fff;text-decoration:none}
.browse-count{font-weight:normal;opacity:.9}
.browse-alphabet{padding:8px 12px;border-bottom:1px solid #dbe2e8;background:#fff;text-align:center;line-height:24px}
.browse-alphabet a{display:inline-block;min-width:19px;margin:1px;padding:1px 3px;border-radius:3px;color:#455a64;text-decoration:none}
.browse-alphabet a:hover,.browse-alphabet a.selected{background:#607d8b;color:#fff}
.browse-form{padding:10px}
.browse-options{display:grid;grid-template-columns:minmax(300px,2fr) minmax(170px,1fr) minmax(210px,1fr);gap:9px}
.browse-fieldset{min-width:0;margin:0;padding:8px 10px;border:1px solid #ccd6de;border-radius:4px;background:#fff}
.browse-fieldset legend{padding:0 5px;color:#546e7a;font-weight:bold}
.browse-categories{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:4px 8px;max-height:154px;overflow:auto}
.browse-category{display:flex;align-items:center;gap:4px;white-space:nowrap;overflow:hidden}
.browse-category a{overflow:hidden;text-overflow:ellipsis;text-decoration:none}
.browse-fieldset select{box-sizing:border-box;width:100%;min-height:30px}
.browse-direction{display:flex;gap:12px;margin-top:9px}
.browse-search{display:flex;gap:8px;margin-top:10px;padding:8px 10px;border:1px solid #ccd6de;border-radius:4px;background:#fff}
.browse-search input[type=text]{box-sizing:border-box;flex:1;min-width:120px;padding:6px 8px}
.browse-search .btn{min-width:105px}
.browse-active{margin-top:8px;padding:7px 10px;border:1px solid #eadba5;border-radius:4px;background:#fff8df}
.browse-results{width:100%;margin-top:10px;border-collapse:collapse}
.browse-results>.index{padding:6px}
.browse-empty{padding:24px!important;text-align:center}
#suggcontainer{position:relative;z-index:20}
#suggestions{position:absolute;left:10px;right:10px;border:1px solid #777;background:#fff;text-align:left}
@media (max-width:850px){.browse-options{grid-template-columns:1fr 1fr}.browse-options .browse-fieldset:first-child{grid-column:1/-1}}
@media (max-width:580px){.browse-options{display:block}.browse-fieldset{margin-bottom:8px}.browse-categories{grid-template-columns:repeat(2,minmax(0,1fr))}.browse-search{flex-wrap:wrap}.browse-search .btn{width:100%}}
</style>

<div class="browse-page">
    <div class="browse-toolbar">
        <div class="browse-title">
            <a href="browse.php">Список раздач</a>
            <span class="browse-count">Найдено: <?= number_format((int)$num_torrents) ?></span>
        </div>

        <div class="browse-alphabet">
            <a href="browse.php"<?= $alpha === '' ? ' class="selected"' : '' ?>>Все</a>
            <?php foreach ($browseLetters as $letter): ?>
                <a href="browse.php?a=<?= rawurlencode($letter) ?>"<?= $alpha === $letter ? ' class="selected"' : '' ?>><?= $letter ?></a>
            <?php endforeach; ?>
        </div>

        <form class="browse-form" method="get" action="browse.php">
            <div class="browse-options">
                <fieldset class="browse-fieldset">
                    <legend>Активные категории</legend>
                    <div class="browse-categories">
                        <?php foreach ($cats as $cat): ?>
                            <?php $catId = (int)$cat['id']; ?>
                            <label class="browse-category">
                                <input name="c<?= $catId ?>" type="checkbox" value="1"<?= in_array($catId, $wherecatina) ? ' checked' : '' ?>>
                                <a href="browse.php?cat=<?= $catId ?>"><?= htmlspecialchars_uni($cat['name']) ?></a>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="browse-fieldset">
                    <legend>Показывать только</legend>
                    <select name="incldead" size="5">
                        <option value="0"<?= $incldead === 0 ? ' selected' : '' ?>><?= $tracker_lang['active'] ?></option>
                        <option value="1"<?= $incldead === 1 ? ' selected' : '' ?>><?= $tracker_lang['including_dead'] ?></option>
                        <option value="2"<?= $incldead === 2 ? ' selected' : '' ?>><?= $tracker_lang['only_dead'] ?></option>
                        <option value="3"<?= $incldead === 3 ? ' selected' : '' ?>><?= $tracker_lang['golden_torrents'] ?></option>
                        <option value="4"<?= $incldead === 4 ? ' selected' : '' ?>><?= $tracker_lang['no_seeds'] ?></option>
                    </select>
                </fieldset>

                <fieldset class="browse-fieldset">
                    <legend>Сортировка</legend>
                    <select name="sort">
                        <?php foreach ($sortOptions as $sortId => $sortLabel): ?>
                            <option value="<?= $sortId ?>"<?= $currentSort === $sortId ? ' selected' : '' ?>><?= $sortLabel ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="browse-direction">
                        <label><input type="radio" name="type" value="desc"<?= $currentDirection === 'desc' ? ' checked' : '' ?>> По убыванию</label>
                        <label><input type="radio" name="type" value="asc"<?= $currentDirection === 'asc' ? ' checked' : '' ?>> По возрастанию</label>
                    </div>
                </fieldset>
            </div>

            <div class="browse-search">
                <input type="text" id="searchinput" name="search" autocomplete="off"
                       placeholder="Введите название раздачи"
                       onkeyup="suggest(event.keyCode,this.value)"
                       onkeypress="return noenter(event.keyCode)"
                       value="<?= htmlspecialchars_uni($searchstr) ?>">
                <input class="btn" type="submit" value="Найти">
                <input class="btn" type="button" value="Сбросить" onclick="location.href='browse.php'">
            </div>

            <?php if (isset($cleansearchstr) || $alpha !== '' || $wherecatina || $incldead !== 0): ?>
                <div class="browse-active">
                    <b>Активный фильтр:</b>
                    <?php if (isset($cleansearchstr)): ?> запрос «<?= htmlspecialchars_uni($searchstr) ?>»<?php endif; ?>
                    <?php if ($alpha !== ''): ?> буква «<?= htmlspecialchars_uni($alpha) ?>»<?php endif; ?>
                    <?php if ($wherecatina): ?> категорий: <?= count($wherecatina) ?><?php endif; ?>
                    <?php if ($incldead !== 0): ?> режим показа №<?= $incldead ?><?php endif; ?>
                    &nbsp; <a href="browse.php">Сбросить фильтры</a>
                </div>
            <?php endif; ?>
        </form>

        <script src="js/suggest.js" type="text/javascript"></script>
        <div id="suggcontainer" style="display:none"><div id="suggestions"></div></div>
    </div>

    <table class="embedded browse-results" cellspacing="0" cellpadding="5">
        <?php if ($num_torrents): ?>
            <tr><td class="index" colspan="20"><?= $pagertop ?></td></tr>
            <?php torrenttable($res, 'index'); ?>
            <tr><td class="index" colspan="20"><?= $pagerbottom ?></td></tr>
        <?php else: ?>
            <tr><td class="index browse-empty"><?= $tracker_lang['nothing_found'] ?></td></tr>
        <?php endif; ?>
    </table>
</div>
