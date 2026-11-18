<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Страницы реестра.
 *
 * @var Complaints\Application\Shared\Page $page
 * @var string                             $path  например «complaint_supplier/index»
 * @var array<string, scalar|null>         $query фильтры реестра
 */
$pages = $page->pageCount();
if ($pages <= 1) {
    return;
}
?>
<ul class="pagination">
    <?php for ($number = 1; $number <= $pages; $number++) : ?>
        <li class="<?= $number === $page->page ? 'active' : '' ?>">
            <a href="<?= complaints_e(complaints_url($path . '/' . $number, $query ?? [])) ?>"><?= $number ?></a>
        </li>
    <?php endfor; ?>
</ul>
