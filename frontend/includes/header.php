<?php
// Shared top of every page. Each page sets $pageTitle and $activePage before including this file.
date_default_timezone_set('Australia/Sydney');

// key => [label, icon, roles that can see it (empty list = everyone)]
$navItems = [
    'index'      => ['Dashboard',         'dashboard', []],
    'patients'   => ['Patients',          'users',     []],
    'biomarkers' => ['Biomarkers',        'samples',   []],
    'predict'    => ['Predict',           'predict',   []],
    'trends'     => ['Trends',            'trends',    ['researcher', 'admin']],
    'model'      => ['Model performance', 'model',     []],
    'about'      => ['About',             'about',     []],
];

// Who is signed in (set by login.php)
$userName = $_SESSION['name'] ?? '';
$userRole = ucfirst($_SESSION['role'] ?? '');
$initials = '';
foreach (explode(' ', trim($userName)) as $word) {
    $initials .= strtoupper(substr($word, 0, 1));
}
$initials = substr($initials, 0, 2);

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

// icon('name') prints a small line icon. Every icon is just SVG shapes.
function icon(string $name): string
{
    $shapes = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'predict' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'explore' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'trends' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'samples' => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>',
        'model' => '<path d="M5 20V10M12 20V4M19 20v-7"/>',
        'about' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14a5 5 0 0 1 4.5 5"/>',
        'dna' => '<path d="M7 3c0 5 10 5 10 9s-10 4-10 9"/><path d="M17 3c0 5-10 5-10 9s10 4 10 9"/><path d="M8.5 6.5h7M8.5 17.5h7"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">' . ($shapes[$name] ?? '') . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> | DECancer Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="shell">
  <!-- Hidden checkbox: the Menu button (a label) ticks it, and the CSS slides the sidebar in on phones. -->
  <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Show or hide the menu">

  <aside class="sidebar">
    <a class="brand" href="index.php"><span class="brand-mark"><?= icon('dna') ?></span>DECancer</a>

    <nav aria-label="Main">
      <?php foreach ($navItems as $key => [$label, $iconName, $roles]): ?>
        <?php if ($roles && !hasRole(...$roles)) { continue; } ?>
        <a href="<?= $key ?>.php" class="nav-link<?= $key === $activePage ? ' active' : '' ?>"<?= $key === $activePage ? ' aria-current="page"' : '' ?>>
          <?= icon($iconName) ?><span><?= $label ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="help">
      <p>Reading a result can be tricky.</p>
      <div class="help-row">
        <span>See how likelihood and risk are scored</span>
        <a class="round-btn dark" href="about.php" aria-label="How results are scored"><?= icon('arrow') ?></a>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <label for="nav-toggle" class="menu-button">Menu</label>
      <div class="titles">
        <p><?= $greeting ?>, <?= htmlspecialchars($userName) ?></p>
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
      </div>

      <form class="search" action="biomarkers.php" method="get" role="search">
        <input type="search" name="q" placeholder="Search biomarkers or patients" aria-label="Search biomarkers or patients">
        <button class="round-btn dark" type="submit" aria-label="Search"><?= icon('explore') ?></button>
      </form>

      <div class="user">
        <span class="avatar"><?= htmlspecialchars($initials) ?></span>
        <span class="user-text"><?= htmlspecialchars($userName) ?><small><?= htmlspecialchars($userRole) ?></small></span>
      </div>
      <a class="text-link signout" href="logout.php">Sign out</a>
    </header>

    <main>
