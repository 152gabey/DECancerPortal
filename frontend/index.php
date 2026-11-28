<?php
$pageTitle = 'Dashboard';
$activePage = 'index';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

$db = getDb();

function h($value): string            // makes text safe to print inside HTML
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}

// Rounds a number up to a "nice" chart ceiling such as 10, 20, 50, 100, 200
function niceCeiling(float $v): float
{
    if ($v <= 0) { return 1; }
    $pow = pow(10, floor(log10($v)));
    foreach ([1, 2, 5, 10] as $step) {
        if ($v <= $step * $pow) { return $step * $pow; }
    }
    return 10 * $pow;
}

// Prints rows of "label ---- bar ---- number". $rows is [label => number].
function barRows(array $rows, string $fillClass = ''): string
{
    $max = $rows ? max($rows) : 0;
    $out = '';
    foreach ($rows as $label => $n) {
        $width = $max > 0 ? round($n / $max * 100) : 0;
        $out .= '<div class="bar-row"><span class="bar-label">' . h($label) . '</span>'
              . '<div class="bar-track"><div class="bar-fill ' . $fillClass . '" style="width: ' . $width . '%"></div></div>'
              . '<span class="bar-value">' . number_format($n) . '</span></div>';
    }
    return $out;
}

// ---- 1. The four tiles ----
$counts = [
    'patients'    => (int) $db->query('SELECT COUNT(*) FROM Patients')->fetchColumn(),
    'samples'     => (int) $db->query('SELECT COUNT(*) FROM Samples')->fetchColumn(),
    'biomarkers'  => (int) $db->query('SELECT COUNT(*) FROM Biomarkers')->fetchColumn(),
    'predictions' => (int) $db->query('SELECT COUNT(*) FROM Predictions')->fetchColumn(),
];

// ---- 2. The biomarker chart ----
$biomarkerList = $db->query('SELECT Biomarker_ID, Name, Unit FROM Biomarkers ORDER BY Name')->fetchAll();

// Which biomarker? "?biomarker=" in the address, otherwise CA-125, otherwise the first one
$selected = null;
foreach ($biomarkerList as $b) {
    if (isset($_GET['biomarker']) && (int) $_GET['biomarker'] === (int) $b['Biomarker_ID']) { $selected = $b; }
}
if (!$selected) {
    foreach ($biomarkerList as $b) {
        if ($b['Name'] === 'CA-125') { $selected = $b; }
    }
}
$selected = $selected ?: ($biomarkerList[0] ?? null);

// Average level of that protein for each cancer type (cancer-free first)
$levels = [];
if ($selected) {
    $stmt = $db->prepare('SELECT ct.Cancer_Name, ct.is_cancer_free, AVG(m.Value) AS Avg_Value
        FROM Biomarker_Measurements m
        JOIN Samples s ON s.Sample_ID = m.Sample_ID
        JOIN Diagnosis d ON d.Patient_ID = s.Patient_ID
        JOIN Cancer_Types ct ON ct.Cancer_ID = d.Cancer_ID
        WHERE m.Biomarker_ID = ?
        GROUP BY ct.Cancer_ID, ct.Cancer_Name, ct.is_cancer_free
        ORDER BY ct.is_cancer_free DESC, ct.Cancer_Name');
    $stmt->execute([$selected['Biomarker_ID']]);
    $levels = $stmt->fetchAll();
}

$normalAvg = null;
$topName = null;
$topValue = -1;
foreach ($levels as $row) {
    if ($row['is_cancer_free']) { $normalAvg = (float) $row['Avg_Value']; }
    elseif ((float) $row['Avg_Value'] > $topValue) { $topValue = (float) $row['Avg_Value']; $topName = $row['Cancer_Name']; }
}

// Draws the bar chart as an SVG picture
function biomarkerChart(array $levels, ?float $normalAvg, string $unit): string
{
    $X0 = 56; $X1 = 728; $BASE = 250; $TOP = 40;
    $max = 0;
    foreach ($levels as $r) { $max = max($max, (float) $r['Avg_Value']); }
    $ymax = niceCeiling($max);
    $slot = ($X1 - $X0) / max(1, count($levels));
    $bw = min(46, $slot * 0.6);
    $ys = fn(float $v) => $BASE - $v / $ymax * ($BASE - $TOP);

    $s = '<svg class="plot" viewBox="0 0 760 320" role="img" aria-label="Bar chart of the average level of one biomarker in each cancer type compared with cancer-free patients">';
    for ($i = 0; $i <= 4; $i++) {
        $g = $ymax * $i / 4;
        $y = $ys($g);
        $s .= sprintf('<line class="grid" x1="%d" y1="%.0f" x2="%d" y2="%.0f"/><text class="tick" x="%d" y="%.0f" text-anchor="end">%s</text>',
            $X0, $y, $X1, $y, $X0 - 10, $y + 4, h(rtrim(rtrim(number_format($g, 1), '0'), '.')));
    }
    $s .= '<text class="tick" x="' . ($X0 - 10) . '" y="24" text-anchor="end">' . h($unit) . '</text>';
    foreach ($levels as $i => $r) {
        $v = (float) $r['Avg_Value'];
        $cx = $X0 + $slot * ($i + 0.5);
        $y = $ys($v);
        if ($r['is_cancer_free']) { $kind = 'normal'; }
        elseif ($normalAvg !== null && $v > $normalAvg * 1.5) { $kind = 'high'; }
        else { $kind = 'low'; }
        $label = number_format($v, $v >= 100 ? 0 : 1);
        $s .= sprintf('<rect class="bar-b-%s" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="8"><title>%s: %s %s</title></rect>',
            $kind, $cx - $bw / 2, $y, $bw, $BASE - $y, h($r['Cancer_Name']), $label, h($unit));
        $s .= sprintf('<text class="val-label" x="%.1f" y="%.1f" text-anchor="middle">%s</text>', $cx, $y - 8, $label);
        $s .= sprintf('<text class="cat-label" x="%.1f" y="%d" text-anchor="middle">%s</text>', $cx, $BASE + 22, h($r['Cancer_Name']));
    }
    if ($normalAvg !== null) {
        $yb = $ys($normalAvg);
        $s .= sprintf('<line class="baseline" x1="%d" y1="%.1f" x2="%d" y2="%.1f"/>', $X0, $yb, $X1, $yb);
    }
    return $s . '</svg>';
}

// ---- 3. Recent predictions ----
$recentPredictions = $db->query('SELECT pr.Prediction_ID, pr.Date, pr.Pancancer_Probability, pr.Prediction_Result,
        s.Sample_Code, s.Patient_ID,
        (SELECT c.Cancer_Name FROM Prediction_Scores ps JOIN Cancer_Types c ON c.Cancer_ID = ps.Cancer_ID
         WHERE ps.Prediction_ID = pr.Prediction_ID ORDER BY ps.Probability DESC LIMIT 1) AS Top_Organ
    FROM Predictions pr
    JOIN Samples s ON s.Sample_ID = pr.Sample_ID
    ORDER BY pr.Date DESC, pr.Prediction_ID DESC LIMIT 5')->fetchAll();

// ---- 4. Right-hand cards ----
$resultCounts = $db->query('SELECT Prediction_Result, COUNT(*) FROM Predictions GROUP BY Prediction_Result')->fetchAll(PDO::FETCH_KEY_PAIR);
$resultRows = [];
if ($resultCounts) {
    $resultRows = ['Cancer' => (int) ($resultCounts['cancer'] ?? 0), 'Cancer-free' => (int) ($resultCounts['cancer-free'] ?? 0)];
}

$recentPatients = $db->query('SELECT p.Patient_ID, p.FirstName, p.LastName, ct.Cancer_Name, d.Cancer_Stage
    FROM Patients p
    LEFT JOIN Diagnosis d ON d.Diagnosis_ID = (
        SELECT d2.Diagnosis_ID FROM Diagnosis d2 WHERE d2.Patient_ID = p.Patient_ID
        ORDER BY d2.Diagnosis_Date DESC, d2.Diagnosis_ID DESC LIMIT 1)
    LEFT JOIN Cancer_Types ct ON ct.Cancer_ID = d.Cancer_ID
    ORDER BY p.Created_Date DESC, p.Patient_ID DESC LIMIT 4')->fetchAll();

$influence = $db->query('SELECT b.Name, COUNT(*) AS n FROM Predictions pr
    JOIN Biomarkers b ON b.Biomarker_ID = pr.Highest_Biomarker_ID
    GROUP BY b.Biomarker_ID, b.Name ORDER BY n DESC, b.Name LIMIT 5')->fetchAll(PDO::FETCH_KEY_PAIR);

include __DIR__ . '/includes/header.php';
?>
<div class="dash">
  <div class="col">

    <section class="hero2">
      <div class="hero-head">
        <div>
          <h2>Biomarker levels by cancer type</h2>
          <p>Average level of <?= h($selected['Name'] ?? 'one protein') ?> in each group</p>
        </div>
        <form class="select-wrap" action="index.php" method="get">
          <label for="biomarker">Biomarker</label>
          <select id="biomarker" name="biomarker">
            <?php foreach ($biomarkerList as $b): ?>
              <option value="<?= (int) $b['Biomarker_ID'] ?>"<?= $selected && $b['Biomarker_ID'] == $selected['Biomarker_ID'] ? ' selected' : '' ?>><?= h($b['Name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="button" type="submit">Show</button>
        </form>
      </div>

      <?php if ($levels): ?>
        <?= biomarkerChart($levels, $normalAvg, $selected['Unit']) ?>
      <?php else: ?>
        <p class="muted-text">No measurements found for this biomarker.</p>
      <?php endif; ?>

      <div class="plot-foot">
        <div class="legend"><span><i class="dot n"></i>Cancer-free</span><span><i class="dot h"></i>More than 1.5&times; higher</span><span><i class="dot l"></i>Similar</span><span class="muted">Dashed line = cancer-free level</span></div>
        <?php if ($topName): ?><div class="facts"><span><strong><?= h($topName) ?></strong> highest average</span></div><?php endif; ?>
        <a class="button" href="predict.php">Run a prediction</a>
      </div>
    </section>

    <section class="tiles">
      <div class="tile lavender"><span class="tile-icon"><?= icon('users') ?></span><span class="tile-value"><?= number_format($counts['patients']) ?></span><span class="tile-label">Patients</span></div>
      <div class="tile peach"><span class="tile-icon"><?= icon('samples') ?></span><span class="tile-value"><?= number_format($counts['samples']) ?></span><span class="tile-label">Samples</span></div>
      <div class="tile blue"><span class="tile-icon"><?= icon('model') ?></span><span class="tile-value"><?= number_format($counts['biomarkers']) ?></span><span class="tile-label">Biomarkers tracked</span></div>
      <div class="tile sand"><span class="tile-icon"><?= icon('predict') ?></span><span class="tile-value"><?= number_format($counts['predictions']) ?></span><span class="tile-label">Predictions run</span></div>
    </section>

    <section>
      <div class="section-head">
        <h2>Recent predictions</h2>
        <a class="text-link" href="predict.php">New prediction</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Sample</th><th>Cancer probability</th><th>Result</th><th>Top organ</th><th>When</th><th><span class="sr-only">Open</span></th></tr>
          </thead>
          <tbody>
            <?php if (!$recentPredictions): ?>
              <tr><td colspan="6">No predictions have been run yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentPredictions as $p): ?>
              <?php $isCancer = $p['Prediction_Result'] === 'cancer'; ?>
              <tr>
                <td><strong><?= h($p['Sample_Code']) ?></strong></td>
                <td><span class="meter"><i style="width: <?= round($p['Pancancer_Probability'] * 100) ?>%"></i></span><?= number_format((float) $p['Pancancer_Probability'], 2) ?></td>
                <td><span class="pill <?= $isCancer ? 'high' : 'low' ?>"><?= $isCancer ? 'Cancer' : 'Cancer-free' ?></span></td>
                <td><?= h($p['Top_Organ'] ?: '–') ?></td>
                <td><?= h(date('j M, H:i', strtotime($p['Date']))) ?></td>
                <td><a class="round-btn" href="patient.php?id=<?= (int) $p['Patient_ID'] ?>" aria-label="Open patient for sample <?= h($p['Sample_Code']) ?>"><?= icon('arrow') ?></a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

  </div>

  <div class="col">
    <section class="card">
      <h2>Prediction results</h2>
      <?= $resultRows ? barRows($resultRows) : '<p class="muted-text">No predictions have been run yet.</p>' ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Recent patients</h2><a class="text-link" href="patients.php">View all</a></div>
      <ul class="people">
        <?php foreach ($recentPatients as $p): ?>
          <?php $full = $p['FirstName'] . ' ' . $p['LastName']; ?>
          <li>
            <span class="avatar sm"><?= h(strtoupper(substr($p['FirstName'], 0, 1) . substr($p['LastName'], 0, 1))) ?></span>
            <span><strong><?= h($full) ?></strong><small><?= h($p['Cancer_Name'] ? $p['Cancer_Name'] . ($p['Cancer_Stage'] ? ' · Stage ' . $p['Cancer_Stage'] : '') : 'No diagnosis') ?></small></span>
            <a class="round-btn" href="patient.php?id=<?= (int) $p['Patient_ID'] ?>" aria-label="Open <?= h($full) ?>"><?= icon('arrow') ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <?php if (hasRole('researcher', 'admin')): ?>
    <section class="card">
      <div class="card-head"><h2>Top influence</h2><span class="badge">Researcher view</span></div>
      <p class="muted-text small">Biomarker most often flagged as the biggest influence on a prediction</p>
      <?= $influence ? barRows($influence, 'ink') : '<p class="muted-text">No predictions have been run yet.</p>' ?>
    </section>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
