<?php
require __DIR__ . '/config.php';

start_board_session();        // must run before any output
$voter = voter_id();
session_write_close();        // release the session file lock; $_SESSION stays readable

$errors = [];
$old    = ['title' => '', 'description' => '', 'category' => 'Feature'];

// ---------- Handle form submissions (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input_str($_POST, 'action');

    if ($action === 'add') {
        $old['title']       = input_str($_POST, 'title');
        $old['description'] = input_str($_POST, 'description');
        $old['category']    = input_str($_POST, 'new_category', 'Other');

        if ($old['title'] === '' || strlen($old['title']) > 80) {
            $errors[] = 'Title is required and must be 80 characters or fewer.';
        }
        if (strlen($old['description']) > 500) {
            $errors[] = 'Description must be 500 characters or fewer.';
        }
        if (!in_array($old['category'], CATEGORIES, true)) {
            $errors[] = 'Pick a valid category.';
        }

        if (!$errors) {
            $entry = $old;
            $ok = update_requests(function ($items) use ($entry) {
                $maxId = 0;
                foreach ($items as $r) {
                    $maxId = max($maxId, (int)$r['id']);
                }
                $items[] = [
                    'id'          => $maxId + 1,
                    'title'       => $entry['title'],
                    'description' => $entry['description'],
                    'category'    => $entry['category'],
                    'votes'       => 0,
                    'voters'      => [],
                    'created'     => gmdate('c'),
                    'instance'    => instance_name(),
                ];
                return $items;
            });
            if ($ok) {
                header('Location: ' . board_url(['msg' => 'added']));
                exit;
            }
            $errors[] = 'Could not save: the data folder is not writable.';
        }
    } elseif ($action === 'upvote') {
        $id = (int)input_str($_POST, 'id', '0');
        if ($id > 0 && $voter !== '') {
            // The "already voted?" check happens inside the file lock, on the server
            update_requests(function ($items) use ($id, $voter) {
                foreach ($items as &$r) {
                    if ((int)$r['id'] === $id) {
                        $voters = $r['voters'] ?? [];
                        if (!in_array($voter, $voters, true)) {
                            $voters[]    = $voter;
                            $r['voters'] = $voters;
                            $r['votes']  = (int)$r['votes'] + 1;
                        }
                        break;
                    }
                }
                unset($r);
                return $items;
            });
        }
        header('Location: ' . board_url([
            'q'        => input_str($_POST, 'q'),
            'category' => input_str($_POST, 'category'),
            'sort'     => input_str($_POST, 'sort'),
        ]));
        exit;
    }
}

// ---------- Read filters (GET) and load the list ----------
$q        = input_str($_GET, 'q');
$category = input_str($_GET, 'category');
$sort     = input_str($_GET, 'sort') === 'newest' ? 'newest' : 'top';

$all   = load_requests();
$items = filter_requests($all, $q, $category, $sort);

page_header('Board');
?>
<h1>Feedback &amp; requests board</h1>

<?php if (input_str($_GET, 'msg') === 'added'): ?>
  <p class="notice ok">Request added.</p>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
  <p class="notice err"><?= h($e) ?></p>
<?php endforeach; ?>

<section class="card">
  <h2>Submit a request</h2>
  <form method="post" action="index.php">
    <input type="hidden" name="action" value="add">
    <label>Title
      <input type="text" name="title" maxlength="80" required value="<?= h($old['title']) ?>">
    </label>
    <label>Description (optional)
      <textarea name="description" rows="3" maxlength="500"><?= h($old['description']) ?></textarea>
    </label>
    <label>Category
      <select name="new_category">
        <?php foreach (CATEGORIES as $c): ?>
          <option value="<?= h($c) ?>" <?= $old['category'] === $c ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit">Submit request</button>
  </form>
</section>

<section class="card">
  <h2>Search &amp; filter</h2>
  <form method="get" action="index.php" class="row">
    <input type="search" name="q" placeholder="Search title or description" value="<?= h($q) ?>">
    <select name="category">
      <option value="">All categories</option>
      <?php foreach (CATEGORIES as $c): ?>
        <option value="<?= h($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sort">
      <option value="top" <?= $sort === 'top' ? 'selected' : '' ?>>Most votes</option>
      <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
    </select>
    <button type="submit">Apply</button>
    <a href="index.php">Reset</a>
  </form>
</section>

<h2>Requests (showing <?= count($items) ?> of <?= count($all) ?>)</h2>

<?php if (!$items): ?>
  <p class="muted">No requests to show.</p>
<?php endif; ?>

<?php foreach ($items as $r): $already = in_array($voter, $r['voters'] ?? [], true); ?>
  <article class="card item">
    <form method="post" action="index.php" class="vote">
      <input type="hidden" name="action" value="upvote">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="q" value="<?= h($q) ?>">
      <input type="hidden" name="category" value="<?= h($category) ?>">
      <input type="hidden" name="sort" value="<?= h($sort) ?>">
      <button type="submit" <?= $already ? 'disabled title="You already voted"' : '' ?>>&#9650; <?= (int)$r['votes'] ?></button>
    </form>
    <div>
      <h3><?= h($r['title']) ?> <span class="badge"><?= h($r['category']) ?></span></h3>
      <?php if ($r['description'] !== ''): ?>
        <p><?= nl2br(h($r['description'])) ?></p>
      <?php endif; ?>
      <p class="muted">#<?= (int)$r['id'] ?> &middot; added <?= h($r['created']) ?> &middot; stored by instance <?= h($r['instance']) ?></p>
    </div>
  </article>
<?php endforeach; ?>

<?php page_footer(); ?>