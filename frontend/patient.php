<?php
$pageTitle = 'Patient';
$activePage = 'patients';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

$db = getDb();

function h($value): string            // makes text safe to print inside HTML
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}

// 1. Which patient? The number after "?id=" in the address
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT p.Patient_ID, p.FirstName, p.LastName, p.Date_of_Birth, st.Sex_Name AS Sex, rt.Race_Name AS Race, p.Country,
        TIMESTAMPDIFF(YEAR, p.Date_of_Birth, CURDATE()) AS Age
    FROM Patients p
    LEFT JOIN Sex_Types st ON st.Sex_Type_ID = p.Sex_Type_ID
    LEFT JOIN Race_Types rt ON rt.Race_Type_ID = p.Race_Type_ID
    WHERE p.Patient_ID = ?');
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    http_response_code(404);
    $pageTitle = 'Patient not found';
    include __DIR__ . '/includes/header.php';
    echo '<section class="card empty"><h2>Patient not found</h2><p><a class="text-link" href="patients.php">&larr; Back to all patients</a></p></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}
$name = $patient['FirstName'] . ' ' . $patient['LastName'];
$pageTitle = $name;

// 2. Their most recent diagnosis
$stmt = $db->prepare('SELECT d.Cancer_Stage, d.Diagnosis_Date, d.Histopathology, d.Treatment, d.Diagnosis_Status, ct.Cancer_Name
    FROM Diagnosis d JOIN Cancer_Types ct ON ct.Cancer_ID = d.Cancer_ID
    WHERE d.Patient_ID = ?
    ORDER BY d.Diagnosis_Date DESC, d.Diagnosis_ID DESC LIMIT 1');
$stmt->execute([$id]);
$diagnosis = $stmt->fetch();

// 3. Their samples, newest first
$stmt = $db->prepare('SELECT Sample_ID, Sample_Code, Collection_Date, Sample_Type, Age_At_Collection, Omega_Score
    FROM Samples WHERE Patient_ID = ?
    ORDER BY Collection_Date DESC, Sample_ID DESC');
$stmt->execute([$id]);
$samples = $stmt->fetchAll();

// Which sample is shown below? "?sample=" picks one, otherwise the newest
$current = $samples[0] ?? null;
foreach ($samples as $s) {
    if (isset($_GET['sample']) && (int) $_GET['sample'] === (int) $s['Sample_ID']) {
        $current = $s;
    }
}

// 4. Latest prediction on any of their samples
$stmt = $db->prepare('SELECT pr.Prediction_ID, pr.Date, pr.Pancancer_Probability, pr.Prediction_Result, pr.Model_Version,
        s.Sample_ID, s.Sample_Code
    FROM Predictions pr
    JOIN Samples s ON s.Sample_ID = pr.Sample_ID
    WHERE s.Patient_ID = ?
    ORDER BY pr.Date DESC, pr.Prediction_ID DESC LIMIT 1');
$stmt->execute([$id]);
$prediction = $stmt->fetch();

$topOrgan = null;
if ($prediction) {
    $stmt = $db->prepare('SELECT ct.Cancer_Name, ps.Probability FROM Prediction_Scores ps
        JOIN Cancer_Types ct ON ct.Cancer_ID = ps.Cancer_ID
        WHERE ps.Prediction_ID = ? ORDER BY ps.Probability DESC LIMIT 1');
    $stmt->execute([$prediction['Prediction_ID']]);
    $topOrgan = $stmt->fetch();
}

// 5. Biomarker values for the chosen sample, next to the average of cancer-free patients
$biomarkers = [];
if ($current) {
    $stmt = $db->prepare('SELECT b.Name, b.Unit, m.Value, n.Avg_Normal
        FROM Biomarkers b
        LEFT JOIN Biomarker_Measurements m ON m.Biomarker_ID = b.Biomarker_ID AND m.Sample_ID = ?
        LEFT JOIN (
            SELECT bm.Biomarker_ID, AVG(bm.Value) AS Avg_Normal
            FROM Biomarker_Measurements bm
            JOIN Samples s2 ON s2.Sample_ID = bm.Sample_ID
            JOIN Diagnosis d2 ON d2.Patient_ID = s2.Patient_ID
            JOIN Cancer_Types c2 ON c2.Cancer_ID = d2.Cancer_ID AND c2.is_cancer_free = 1
            GROUP BY bm.Biomarker_ID
        ) n ON n.Biomarker_ID = b.Biomarker_ID
        ORDER BY b.Biomarker_ID');
    $stmt->execute([$current['Sample_ID']]);
    $biomarkers = $stmt->fetchAll();
}

$statusClass = ['confirmed' => 'low', 'suspected' => 'medium', 'ruled_out' => 'neutral'];
$initials = strtoupper(substr($patient['FirstName'], 0, 1) . substr($patient['LastName'], 0, 1));

include __DIR__ . '/includes/header.php';
?>
      <p><a class="text-link" href="patients.php">&larr; All patients</a></p>
      <div class="patient">
        <section class="card profile">
          <span class="avatar xl"><?= h($initials) ?></span>
          <h2><?= h($name) ?></h2>
          <p class="muted-text">Patient ID <?= (int) $patient['Patient_ID'] ?></p>
          <dl class="dl">
            <div><dt>Date of birth</dt><dd><?= $patient['Date_of_Birth'] ? h(date('j M Y', strtotime($patient['Date_of_Birth']))) : '–' ?></dd></div>
            <div><dt>Sex</dt><dd><?= h($patient['Sex']) ?></dd></div>
            <div><dt>Age</dt><dd><?= h($patient['Age']) ?></dd></div>
            <div><dt>Race</dt><dd><?= h($patient['Race'] ?: '–') ?></dd></div>
            <div><dt>Country</dt><dd><?= h($patient['Country'] ?: '–') ?></dd></div>
          </dl>
          <div class="profile-actions">
            <a class="button" href="predict.php">Run a new prediction</a>
          </div>
        </section>

        <div class="col">
          <section class="card">
            <div class="card-head">
              <h2>Diagnosis</h2>
              <?php if ($diagnosis): ?>
                <span class="pill <?= $statusClass[$diagnosis['Diagnosis_Status']] ?? 'neutral' ?>"><?= h(ucfirst(str_replace('_', ' ', $diagnosis['Diagnosis_Status']))) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($diagnosis): ?>
              <dl class="dl">
                <div><dt>Cancer type</dt><dd><?= h($diagnosis['Cancer_Name']) ?></dd></div>
                <div><dt>AJCC stage</dt><dd><?= h($diagnosis['Cancer_Stage'] ?: '–') ?></dd></div>
                <div><dt>Diagnosed</dt><dd><?= $diagnosis['Diagnosis_Date'] ? h(date('j M Y', strtotime($diagnosis['Diagnosis_Date']))) : '–' ?></dd></div>
                <div><dt>Histopathology</dt><dd><?= h($diagnosis['Histopathology'] ?: '–') ?></dd></div>
                <div><dt>Treatment</dt><dd><?= h($diagnosis['Treatment'] ?: '–') ?></dd></div>
              </dl>
            <?php else: ?>
              <p class="muted-text">No diagnosis recorded for this patient.</p>
            <?php endif; ?>
          </section>

          <section class="card">
            <div class="card-head"><h2>Latest prediction</h2><a class="text-link" href="predict.php">Run a new one</a></div>
            <?php if ($prediction): ?>
              <div class="result-top">
                <div>
                  <small>Pancancer probability</small>
                  <strong class="big"><?= number_format((float) $prediction['Pancancer_Probability'], 2) ?></strong>
                  <span class="pill <?= $prediction['Prediction_Result'] === 'cancer' ? 'high' : 'low' ?>"><?= $prediction['Prediction_Result'] === 'cancer' ? 'Cancer' : 'Cancer-free' ?></span>
                </div>
                <div>
                  <small>Highest cancer-specific score</small>
                  <strong><?= h($topOrgan['Cancer_Name'] ?? '–') ?></strong>
                  <?php if ($topOrgan): ?><span><?= number_format((float) $topOrgan['Probability'] * 100, 1) ?>%</span><?php endif; ?>
                </div>
              </div>
              <dl class="dl">
                <div><dt>Sample ID</dt><dd><?= (int) $prediction['Sample_ID'] ?></dd></div>
                <div><dt>Sample code</dt><dd><?= h($prediction['Sample_Code'] ?? '–') ?></dd></div>
                <div><dt>Predicted</dt><dd><?= h(date('j M Y, H:i', strtotime($prediction['Date']))) ?></dd></div>
                <div><dt>Model version</dt><dd><?= h($prediction['Model_Version']) ?></dd></div>
              </dl>
            <?php else: ?>
              <p class="muted-text">No prediction has been run for this patient yet.</p>
            <?php endif; ?>
          </section>

          <section>
            <div class="section-head"><h2>Samples</h2><span class="muted-text"><?= count($samples) ?> in total &middot; <a class="text-link" href="upload_sample.php?patient=<?= $id ?>">Upload sample file</a></span></div>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Sample ID</th><th>Sample code</th><th>Collected</th><th>Type</th><th>Age</th><th>&Omega; score</th></tr></thead>
                <tbody>
                  <?php if (!$samples): ?><tr><td colspan="6">No samples for this patient.</td></tr><?php endif; ?>
                  <?php foreach ($samples as $s): ?>
                    <tr>
                      <td>
                        <a class="sample-id-link" href="patient.php?id=<?= $id ?>&amp;sample=<?= (int) $s['Sample_ID'] ?>"><strong><?= (int) $s['Sample_ID'] ?></strong></a><br>
                        <?= $current && $s['Sample_ID'] == $current['Sample_ID'] ? '<span class="badge">Shown below</span>' : '' ?>
                      </td>
                      <td><?= h($s['Sample_Code'] ?? '–') ?></td>
                      <td><?= $s['Collection_Date'] ? h(date('j M Y', strtotime($s['Collection_Date']))) : '–' ?></td>
                      <td><?= h($s['Sample_Type'] ?: '–') ?></td>
                      <td><?= h($s['Age_At_Collection']) ?></td>
                      <td><?= h(number_format((float) $s['Omega_Score'], 2)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>

          <?php if ($current): ?>
          <section class="card">
            <div class="card-head sample-card-head">
              <h2>Biomarker values</h2>
              <div class="sample-identifiers">
                <span class="badge">Sample ID: <?= (int) $current['Sample_ID'] ?></span>
                <span class="badge">Sample code: <?= h($current['Sample_Code'] ?? '–') ?></span>
              </div>
            </div>
            <p class="muted-text small">All <?= count($biomarkers) ?> proteins, compared with the average of cancer-free patients. &ldquo;Higher&rdquo; means more than 1.5&times; the cancer-free average, &ldquo;Lower&rdquo; less than half.</p>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Biomarker</th><th>Value</th><th>Cancer-free average</th><th>Compared</th></tr></thead>
                <tbody>
                  <?php foreach ($biomarkers as $b): ?>
                    <?php
                      $v = $b['Value'];
                      $avg = $b['Avg_Normal'];
                      if ($v === null || $avg === null || $avg <= 0) { $label = '–'; $cls = 'neutral'; }
                      elseif ($v > 1.5 * $avg) { $label = 'Higher'; $cls = 'high'; }
                      elseif ($v < 0.5 * $avg) { $label = 'Lower'; $cls = 'medium'; }
                      else { $label = 'Similar'; $cls = 'low'; }
                    ?>
                    <tr>
                      <td><strong><?= h($b['Name']) ?></strong></td>
                      <td><?= $v === null ? '–' : h(number_format((float) $v, 2)) ?> <span class="unit"><?= h($b['Unit']) ?></span></td>
                      <td><?= $avg === null ? '–' : h(number_format((float) $avg, 2)) ?></td>
                      <td><span class="pill <?= $cls ?>"><?= $label ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
          <?php endif; ?>
        </div>
      </div>

<?php include __DIR__ . '/includes/footer.php'; ?>
