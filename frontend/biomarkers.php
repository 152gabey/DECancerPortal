<?php
$pageTitle = 'Biomarkers';
$activePage = 'biomarkers';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

$db = getDb();

function h($value): string            // makes text safe to print inside HTML
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}

// Shows a level with a sensible number of decimals: 1,234  /  56.7  /  0.42
function level($v): string
{
    $v = (float) $v;
    if ($v >= 100) { return number_format($v, 0); }
    if ($v >= 1)   { return number_format($v, 1); }
    return number_format($v, 2);
}

// 1. What was searched? This page's own box ("search") or the box in the top bar ("q")
$term = trim((string) ($_GET['search'] ?? $_GET['q'] ?? ''));

// 2. The columns: one per cancer type, cancer-free first
$types = $db->query('SELECT Cancer_ID, Cancer_Name, is_cancer_free FROM Cancer_Types ORDER BY is_cancer_free DESC, Cancer_Name')->fetchAll();

// 3. The rows: the biomarkers, in the model's order
$sql = 'SELECT Biomarker_ID, Name, Unit FROM Biomarkers';
$params = [];
if ($term !== '') {
    $sql .= ' WHERE Name LIKE ?';
    $params[] = '%' . $term . '%';
}
$stmt = $db->prepare($sql . ' ORDER BY Biomarker_ID');
$stmt->execute($params);
$biomarkers = $stmt->fetchAll();

// 4. The average of every biomarker in every cancer type, in one query
$averages = [];   // $averages[biomarker id][cancer id] = average level
$rows = $db->query('SELECT m.Biomarker_ID, d.Cancer_ID, AVG(m.Value) AS Avg_Value
    FROM Biomarker_Measurements m
    JOIN Samples s ON s.Sample_ID = m.Sample_ID
    JOIN Diagnosis d ON d.Patient_ID = s.Patient_ID
    GROUP BY m.Biomarker_ID, d.Cancer_ID')->fetchAll();
foreach ($rows as $r) {
    $averages[$r['Biomarker_ID']][$r['Cancer_ID']] = (float) $r['Avg_Value'];
}

// Which column is the cancer-free one?
$normalId = null;
foreach ($types as $t) {
    if ($t['is_cancer_free']) { $normalId = $t['Cancer_ID']; }
}

include __DIR__ . '/includes/header.php';
?>
      <section class="card prose">
        <h2>The 39 blood proteins</h2>
        <p>These are the proteins the model reads from each blood sample. The table shows the <strong>average level</strong> of each one in patients with each diagnosis. Cells shaded purple are more than 1.5&times; the cancer-free average, which suggests the protein runs high in that cancer. Click a protein to see it as a chart on the dashboard.</p>
      </section>

      <form class="card toolbar" action="biomarkers.php" method="get" role="search">
        <input type="search" name="search" autocomplete="off" value="<?= h($term) ?>" placeholder="Search for a protein, e.g. CA-125" aria-label="Search for a protein">
        <button class="button" type="submit">Search</button>
        <?php if ($term !== ''): ?><a class="text-link" href="biomarkers.php">Clear</a><?php endif; ?>
      </form>

      <section>
        <div class="section-head">
          <h2>Average level by diagnosis</h2>
          <span class="muted-text"><?= count($biomarkers) ?> of 39 shown</span>
        </div>
        <div class="table-wrap">
          <table class="heat">
            <thead>
              <tr>
                <th>Biomarker</th>
                <th>Unit</th>
                <?php foreach ($types as $t): ?><th><?= h($t['Cancer_Name']) ?></th><?php endforeach; ?>
                <th>Highest in</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$biomarkers): ?>
                <tr><td colspan="<?= count($types) + 3 ?>">No proteins match your search.</td></tr>
              <?php endif; ?>
              <?php foreach ($biomarkers as $b): ?>
                <?php
                  $id = $b['Biomarker_ID'];
                  $normal = $averages[$id][$normalId] ?? null;
                  $highest = null; $highestValue = -1;
                  foreach ($types as $t) {
                      $v = $averages[$id][$t['Cancer_ID']] ?? null;
                      if (!$t['is_cancer_free'] && $v !== null && $v > $highestValue) { $highestValue = $v; $highest = $t['Cancer_Name']; }
                  }
                ?>
                <tr>
                  <td><a href="index.php?biomarker=<?= (int) $id ?>"><strong><?= h($b['Name']) ?></strong></a></td>
                  <td><span class="unit"><?= h($b['Unit']) ?></span></td>
                  <?php foreach ($types as $t): ?>
                    <?php
                      $v = $averages[$id][$t['Cancer_ID']] ?? null;
                      $isHigh = !$t['is_cancer_free'] && $v !== null && $normal !== null && $normal > 0 && $v > 1.5 * $normal;
                    ?>
                    <td class="<?= $isHigh ? 'hi' : '' ?>"><?= $v === null ? '–' : level($v) ?></td>
                  <?php endforeach; ?>
                  <td><?= h($highest ?: '–') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

<?php include __DIR__ . '/includes/footer.php'; ?>
