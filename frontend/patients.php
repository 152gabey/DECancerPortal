<?php
$pageTitle = 'Patients';
$activePage = 'patients';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

$db = getDb();

// Small helpers
function h($value): string            // makes text safe to print inside HTML
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}
function getParam(string $key): string // reads one value from the address bar, e.g. ?q=smith
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}
function pageUrl(int $n): string      // link to page $n that keeps the current search and filters
{
    return 'patients.php?' . http_build_query(array_merge($_GET, ['page' => $n]));
}

// 1. Read what the user typed into the search form
$q       = getParam('search');
$cancer  = getParam('cancer');
$stage   = getParam('stage');
$sex     = getParam('sex');
$page    = max(1, (int) getParam('page'));
$perPage = 15;

// 2. Build the WHERE part of the query, one filter at a time
$where  = [];
$params = [];

if ($q !== '') {
    $digits = preg_replace('/\D/', '', $q);   // "P-1024" or "1024" both search by ID
    $where[] = "(p.FirstName LIKE :q1 OR p.LastName LIKE :q2 OR CONCAT(p.FirstName, ' ', p.LastName) LIKE :q3 OR p.Patient_ID = :qid)";
    $params[':q1'] = $params[':q2'] = $params[':q3'] = '%' . $q . '%';
    $params[':qid'] = $digits !== '' ? $digits : 0;
}
if ($cancer !== '') { $where[] = 'ct.Cancer_Name = :cancer';  $params[':cancer'] = $cancer; }
if ($stage !== '')  { $where[] = 'd.Cancer_Stage = :stage';   $params[':stage']  = $stage; }
if ($sex !== '')    { $where[] = 'p.Sex = :sex';              $params[':sex']    = $sex; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Each patient is joined to their most recent diagnosis, and that to the cancer type name
$from = "FROM Patients p
    LEFT JOIN Diagnosis d ON d.Diagnosis_ID = (
        SELECT d2.Diagnosis_ID FROM Diagnosis d2
        WHERE d2.Patient_ID = p.Patient_ID
        ORDER BY d2.Diagnosis_Date DESC, d2.Diagnosis_ID DESC
        LIMIT 1)
    LEFT JOIN Cancer_Types ct ON ct.Cancer_ID = d.Cancer_ID";

// 3. Count the matches, then fetch just this page of them
$stmt = $db->prepare("SELECT COUNT(*) $from $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pages  = max(1, (int) ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT p.Patient_ID, p.FirstName, p.LastName, p.Sex, p.Country,
        TIMESTAMPDIFF(YEAR, p.Date_of_Birth, CURDATE()) AS Age,
        ct.Cancer_Name, d.Cancer_Stage, d.Diagnosis_Status
    $from $whereSql
    ORDER BY p.Patient_ID
    LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Options for the filter dropdowns come from the database too
$cancerOptions = $db->query('SELECT Cancer_Name FROM Cancer_Types ORDER BY Cancer_Name')->fetchAll(PDO::FETCH_COLUMN);
$stageOptions  = $db->query('SELECT DISTINCT Cancer_Stage FROM Diagnosis WHERE Cancer_Stage IS NOT NULL ORDER BY Cancer_Stage')->fetchAll(PDO::FETCH_COLUMN);

$statusClass = ['confirmed' => 'low', 'suspected' => 'medium', 'ruled_out' => 'neutral'];
$first = $total ? $offset + 1 : 0;
$last  = min($offset + $perPage, $total);
$filtered = ($q !== '' || $cancer !== '' || $stage !== '' || $sex !== '');

include __DIR__ . '/includes/header.php';
?>
      <form class="card toolbar" action="patients.php" method="get" role="search">
        <input type="search" name="search" autocomplete="off" value="<?= h($q) ?>" placeholder="Search by name or patient ID" aria-label="Search by name or patient ID">
        <select name="cancer" aria-label="Cancer type">
          <option value="">All cancer types</option>
          <?php foreach ($cancerOptions as $opt): ?>
            <option<?= $opt === $cancer ? ' selected' : '' ?>><?= h($opt) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="stage" aria-label="Stage">
          <option value="">All stages</option>
          <?php foreach ($stageOptions as $opt): ?>
            <option<?= $opt === $stage ? ' selected' : '' ?>><?= h($opt) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="sex" aria-label="Sex">
          <option value="">All sexes</option>
          <option<?= $sex === 'Female' ? ' selected' : '' ?>>Female</option>
          <option<?= $sex === 'Male' ? ' selected' : '' ?>>Male</option>
        </select>
        <button class="button" type="submit">Search</button>
        <?php if ($filtered): ?><a class="text-link" href="patients.php">Clear</a><?php endif; ?>
      </form>

      <p><a class="button" href="add_patient.php">Add a new patient</a></p>

      <section>
        <div class="section-head">
          <h2>All patients</h2>
          <span class="muted-text">Showing <?= number_format($first) ?>&ndash;<?= number_format($last) ?> of <?= number_format($total) ?></span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Patient</th><th>Age &middot; Sex</th><th>Country</th><th>Cancer type</th><th>Stage</th><th>Diagnosis</th><th><span class="sr-only">Open</span></th></tr>
            </thead>
            <tbody>
              <?php if (!$patients): ?>
                <tr><td colspan="7">No patients match your search.</td></tr>
              <?php endif; ?>
              <?php foreach ($patients as $row): ?>
                <?php $name = $row['FirstName'] . ' ' . $row['LastName']; ?>
                <tr>
                  <td>
                    <a class="person-cell" href="patient.php?id=<?= (int) $row['Patient_ID'] ?>">
                      <span class="avatar sm"><?= h(strtoupper(substr($row['FirstName'], 0, 1) . substr($row['LastName'], 0, 1))) ?></span>
                      <span><strong><?= h($name) ?></strong><small>ID <?= (int) $row['Patient_ID'] ?></small></span>
                    </a>
                  </td>
                  <td><?= h($row['Age']) ?> &middot; <?= h($row['Sex']) ?></td>
                  <td><?= h($row['Country'] ?: '–') ?></td>
                  <td><?= h($row['Cancer_Name'] ?: 'No diagnosis') ?></td>
                  <td><?= h($row['Cancer_Stage'] ?: '–') ?></td>
                  <td>
                    <?php if ($row['Diagnosis_Status']): ?>
                      <span class="pill <?= $statusClass[$row['Diagnosis_Status']] ?? 'neutral' ?>"><?= h(ucfirst(str_replace('_', ' ', $row['Diagnosis_Status']))) ?></span>
                    <?php else: ?>–<?php endif; ?>
                  </td>
                  <td><a class="round-btn" href="patient.php?id=<?= (int) $row['Patient_ID'] ?>" aria-label="Open <?= h($name) ?>"><?= icon('arrow') ?></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <nav class="pager" aria-label="Pagination">
          <?php if ($page > 1): ?><a href="<?= h(pageUrl($page - 1)) ?>">Previous</a><?php else: ?><span class="disabled">Previous</span><?php endif; ?>
          <?php for ($n = max(1, $page - 2); $n <= min($pages, $page + 2); $n++): ?>
            <a href="<?= h(pageUrl($n)) ?>"<?= $n === $page ? ' aria-current="page"' : '' ?>><?= $n ?></a>
          <?php endfor; ?>
          <?php if ($page < $pages): ?><a href="<?= h(pageUrl($page + 1)) ?>">Next</a><?php else: ?><span class="disabled">Next</span><?php endif; ?>
        </nav>
      </section>

<?php include __DIR__ . '/includes/footer.php'; ?>
