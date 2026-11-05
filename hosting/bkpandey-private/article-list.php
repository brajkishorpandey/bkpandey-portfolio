<?php
declare(strict_types=1);

$search = is_string($_GET['q'] ?? null) ? trim(substr($_GET['q'], 0, 160)) : '';
$search = preg_replace('/[\x00-\x1F\x7F]/u', '', $search) ?? '';
$status = $_GET['status'] ?? '';
$status = in_array($status, ['draft', 'published', 'archived'], true) ? $status : '';
$category = filter_var($_GET['category'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$page = max(1, filter_var($_GET['page'] ?? '', FILTER_VALIDATE_INT) ?: 1);
$conditions = [];
$values = [];
if ($search !== '') {
    $term = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    $conditions[] = "(p.title LIKE ? ESCAPE '!' OR p.slug LIKE ? ESCAPE '!')";
    $values = [$term, $term];
}
if ($status !== '') {
    $conditions[] = 'p.status = ?';
    $values[] = $status;
}
if ($category > 0) {
    $conditions[] = 'p.category_id = ?';
    $values[] = $category;
}
$where = $conditions ? ' WHERE '.implode(' AND ', $conditions) : '';
$count = $db->prepare('SELECT COUNT(*) FROM bp_blog_posts p'.$where);
$count->execute($values);
$total = (int) $count->fetchColumn();
$size = 20;
$pages = max(1, (int) ceil($total / $size));
$page = min($page, $pages);
$offset = ($page - 1) * $size;
$list = $db->prepare('SELECT p.id,p.title,p.status,p.updated_at,c.name AS category
    FROM bp_blog_posts p JOIN bp_blog_categories c ON c.id=p.category_id'.$where.' ORDER BY p.updated_at DESC,p.id DESC LIMIT ? OFFSET ?');
foreach ($values as $index => $value) {
    $list->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$list->bindValue(count($values) + 1, $size, PDO::PARAM_INT);
$list->bindValue(count($values) + 2, $offset, PDO::PARAM_INT);
$list->execute();
$rows = $list->fetchAll();
$filters = array_filter(['q' => $search, 'status' => $status, 'category' => $category], static fn ($value) => $value !== '' && $value !== 0);
$listUrl = static fn (int $number): string => '/admin?'.http_build_query($filters + ['page' => $number], '', '&', PHP_QUERY_RFC3986);
?>
<section class="admin-section traffic-panel" aria-label="Article list">
    <form action="/admin" method="get" class="traffic-filters" aria-label="Filter articles">
        <div class="field">
            <label for="article-search">Title or slug</label>
            <input id="article-search" name="q" type="search" maxlength="160" value="<?= bp_h($search) ?>">
        </div>
        <div class="field">
            <label for="article-status">Status</label>
            <select id="article-status" name="status">
                <option value="">All statuses</option>
                <?php foreach (['draft', 'published', 'archived'] as $value): ?>
                <option value="<?= $value ?>"<?= $value === $status ? ' selected' : '' ?>><?= ucfirst($value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="article-category">Category</label>
            <select id="article-category" name="category">
                <option value="">All categories</option>
                <?php foreach (bp_categories($db) as $topic): ?>
                <option value="<?= (int) $topic['id'] ?>"<?= (int) $topic['id'] === $category ? ' selected' : '' ?>><?= bp_h($topic['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="traffic-filter-actions">
            <button type="submit" class="btn btn-primary">Apply filters</button>
            <a class="btn" href="/admin">Reset</a>
        </div>
    </form>
    <?php if (!$rows): ?>
        <p>No articles match these filters.</p>
    <?php else: ?>
        <p class="traffic-results muted">Showing <?= number_format($offset + 1) ?>–<?= number_format($offset + count($rows)) ?> of <?= number_format($total) ?> articles.</p>
        <div class="admin-list-wrap traffic-table-wrap">
            <table class="traffic-table" role="table" aria-label="Articles">
                <thead role="rowgroup"><tr role="row">
                    <th scope="col" role="columnheader">Article</th>
                    <th scope="col" role="columnheader">Category</th>
                    <th scope="col" role="columnheader">Status</th>
                    <th scope="col" role="columnheader">Updated (UTC)</th>
                    <th scope="col" role="columnheader">Edit</th>
                </tr></thead>
                <tbody role="rowgroup">
                <?php foreach ($rows as $row): ?>
                    <tr role="row">
                        <th scope="row" role="rowheader"><?= bp_h($row['title']) ?></th>
                        <td role="cell"><span class="traffic-mobile-label" aria-hidden="true">Category</span><?= bp_h($row['category']) ?></td>
                        <td role="cell"><span class="traffic-mobile-label" aria-hidden="true">Status</span><?= bp_h(ucfirst($row['status'])) ?></td>
                        <td role="cell"><span class="traffic-mobile-label" aria-hidden="true">Updated (UTC)</span><?= bp_h($row['updated_at']) ?></td>
                        <td role="cell"><a class="traffic-open" href="/admin?edit=<?= (int) $row['id'] ?>" aria-label="Edit <?= bp_h($row['title']) ?>">Edit ↗</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <nav class="traffic-pager" aria-label="Article pages">
            <?php if ($page > 1): ?><a class="btn" rel="prev" href="<?= bp_h($listUrl($page - 1)) ?>">← Previous</a><?php endif; ?>
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn" rel="next" href="<?= bp_h($listUrl($page + 1)) ?>">Next →</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
