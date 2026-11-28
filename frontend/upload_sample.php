<?php
// Upload a tab-delimited text file of protein measurements for one patient.
// Each row of the file = one blood sample. Everything is saved, or nothing is.
$pageTitle  = 'Upload sample data';
$activePage = 'patients';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}

$db = getDb();

// The 39 protein columns the file must contain. Their names come from the
// database (Biomarkers.Model_Feature_Name), e.g. "AFP (pg/ml)".
$biomarkers = $db->query('SELECT Biomarker_ID, Model_Feature_Name FROM Biomarkers
    WHERE Model_Feature_Name IS NOT NULL ORDER BY Biomarker_ID')->fetchAll(PDO::FETCH_KEY_PAIR);

$fixedColumns    = ['Sample_Code', 'Age_At_Collection', 'Omega_Score'];   // required
$optionalColumns = ['Collection_Date', 'Sample_Type'];                    // may be left out

// ---- Download an example file (a header row plus one example row) ----
if (isset($_GET['template'])) {
    $header = array_merge($fixedColumns, $optionalColumns, array_values($biomarkers));
    $row    = array_merge(['SAMPLE-001', '60', '0.5', date('Y-m-d'), 'Plasma'], array_fill(0, count($biomarkers), '1.0'));
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="sample_upload_template.txt"');
    echo implode("\t", $header) . "\n" . implode("\t", $row) . "\n";
    exit;
}

// ---- Which patient is this for? ----
$patientId = (int) ($_GET['patient'] ?? $_POST['patient'] ?? 0);
$stmt = $db->prepare('SELECT Patient_ID, FirstName, LastName FROM Patients WHERE Patient_ID = ?');
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors  = [];
$savedIds = [];

if ($patient && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $maxRows = 200;

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Your session has expired. Please try again.';
    }

    $file = $_FILES['datafile'] ?? null;
    if (!$errors) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Please choose a file to upload (maximum 1 MB).';
        } elseif ($file['size'] > 1024 * 1024) {
            $errors[] = 'The file is too large. The maximum is 1 MB.';
        } elseif (!preg_match('/\.(txt|tsv)$/i', $file['name'])) {
            $errors[] = 'The file must be a tab-delimited text file (.txt or .tsv).';
        }
    }

    $rows = [];   // each: [line number, column name => value]
    if (!$errors) {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle, 0, "\t", '"', '');
        if (!$header) {
            $errors[] = 'The file is empty.';
        } else {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);   // remove invisible BOM mark
            $header    = array_map('trim', $header);

            $needed  = array_merge($fixedColumns, array_values($biomarkers));
            $missing = array_diff($needed, $header);
            $unknown = array_diff($header, $needed, $optionalColumns);
            if ($missing) {
                $errors[] = 'Missing columns: ' . implode(', ', array_slice($missing, 0, 8)) . (count($missing) > 8 ? ' ...' : '') . '. Download the template to see the exact names.';
            }
            if ($unknown) {
                $errors[] = 'Unrecognised columns: ' . implode(', ', array_slice($unknown, 0, 8)) . '.';
            }
            if (count($header) !== count(array_unique($header))) {
                $errors[] = 'Some column names appear more than once.';
            }
        }

        if (!$errors) {
            $line = 1;
            while (($cells = fgetcsv($handle, 0, "\t", '"', '')) !== false) {
                $line++;
                if (count($cells) === 1 && trim((string) $cells[0]) === '') {
                    continue;   // blank line
                }
                if (count($cells) !== count($header)) {
                    $errors[] = "Line $line: expected " . count($header) . ' columns but found ' . count($cells) . '.';
                    continue;
                }
                $rows[] = [$line, array_combine($header, array_map('trim', $cells))];
            }
            if (!$rows && !$errors) {
                $errors[] = 'The file has a header but no data rows.';
            }
            if (count($rows) > $maxRows) {
                $errors[] = "Too many rows ($maxRows is the maximum per upload).";
            }
        }
        fclose($handle);
    }

    // ---- Check every row before saving anything ----
    if (!$errors) {
        $seen   = [];
        $exists = $db->prepare('SELECT 1 FROM Samples WHERE Sample_Code = ?');
        foreach ($rows as [$line, $r]) {
            $code = $r['Sample_Code'];
            if ($code === '' || mb_strlen($code) > 30) {
                $errors[] = "Line $line: Sample_Code is required (up to 30 characters).";
            } elseif (isset($seen[$code])) {
                $errors[] = "Line $line: Sample_Code $code appears twice in the file.";
            } else {
                $seen[$code] = true;
                $exists->execute([$code]);
                if ($exists->fetch()) {
                    $errors[] = "Line $line: a sample with code $code already exists.";
                }
            }
            if (!ctype_digit($r['Age_At_Collection']) || (int) $r['Age_At_Collection'] > 120) {
                $errors[] = "Line $line: Age_At_Collection must be a whole number from 0 to 120.";
            }
            if (!is_numeric($r['Omega_Score'])) {
                $errors[] = "Line $line: Omega_Score must be a number.";
            }
            if (($r['Collection_Date'] ?? '') !== '') {
                $d = DateTime::createFromFormat('Y-m-d', $r['Collection_Date']);
                if (!$d || $d->format('Y-m-d') !== $r['Collection_Date']) {
                    $errors[] = "Line $line: Collection_Date must look like 2026-11-28.";
                }
            }
            if (mb_strlen($r['Sample_Type'] ?? '') > 50) {
                $errors[] = "Line $line: Sample_Type must be 50 characters or fewer.";
            }
            foreach ($biomarkers as $name) {
                if (!is_numeric($r[$name]) || (float) $r[$name] < 0) {
                    $errors[] = "Line $line: \"$name\" must be a number of 0 or more.";
                    break;   // one message per row is enough
                }
            }
        }
    }

    // ---- Save everything in one transaction ----
    if (!$errors) {
        try {
            $db->beginTransaction();
            $insSample = $db->prepare('INSERT INTO Samples (Sample_Code, Patient_ID, Uploaded_By, Collection_Date, Sample_Type, Age_At_Collection, Omega_Score)
                VALUES (?, ?, ?, ?, ?, ?, ?)');
            $insValue  = $db->prepare('INSERT INTO Biomarker_Measurements (Sample_ID, Biomarker_ID, Value) VALUES (?, ?, ?)');

            foreach ($rows as [$line, $r]) {
                $insSample->execute([
                    $r['Sample_Code'],
                    $patientId,
                    $_SESSION['user_id'],
                    ($r['Collection_Date'] ?? '') !== '' ? $r['Collection_Date'] : null,
                    ($r['Sample_Type'] ?? '') !== '' ? $r['Sample_Type'] : null,
                    (int) $r['Age_At_Collection'],
                    (float) $r['Omega_Score'],
                ]);
                $sampleId = (int) $db->lastInsertId();
                foreach ($biomarkers as $biomarkerId => $name) {
                    $insValue->execute([$sampleId, $biomarkerId, (float) $r[$name]]);
                }
                $savedIds[] = $sampleId;
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $savedIds = [];
            $errors[] = 'The database refused the data, so nothing was saved. (' . $e->getMessage() . ')';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<?php if (!$patient): ?>
      <section class="card empty"><h2>Patient not found</h2><p><a class="text-link" href="patients.php">&larr; Back to all patients</a></p></section>
<?php else: ?>
      <p><a class="text-link" href="patient.php?id=<?= (int) $patientId ?>">&larr; Back to <?= h($patient['FirstName'] . ' ' . $patient['LastName']) ?></a></p>

  <?php if ($savedIds): ?>
      <section class="card">
        <h2>Upload complete</h2>
        <p><?= count($savedIds) ?> sample<?= count($savedIds) === 1 ? '' : 's' ?> saved for <?= h($patient['FirstName'] . ' ' . $patient['LastName']) ?>, each with <?= count($biomarkers) ?> protein values.</p>
        <p><a class="button" href="patient.php?id=<?= (int) $patientId ?>&amp;sample=<?= (int) end($savedIds) ?>">View the new sample</a></p>
      </section>
  <?php endif; ?>

      <form class="card form" action="upload_sample.php" method="post" enctype="multipart/form-data" novalidate>
        <h2>Upload sample data</h2>
        <p class="muted-text">Upload a tab-delimited text file (.txt or .tsv) for <?= h($patient['FirstName'] . ' ' . $patient['LastName']) ?>. Each row is one blood sample. Nothing is saved unless every row passes the checks.</p>

        <?php if ($errors): ?>
          <div class="alert" role="alert">
            <?php foreach (array_slice($errors, 0, 10) as $error): ?><div><?= h($error) ?></div><?php endforeach; ?>
            <?php if (count($errors) > 10): ?><div>...and <?= count($errors) - 10 ?> more problems.</div><?php endif; ?>
          </div>
        <?php endif; ?>

        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <input type="hidden" name="patient" value="<?= (int) $patientId ?>">

        <div class="fields">
          <div class="field"><label for="datafile">Data file</label><input id="datafile" name="datafile" type="file" accept=".txt,.tsv" required></div>
        </div>

        <p class="muted-text">The first row must list these column names: <strong>Sample_Code, Age_At_Collection, Omega_Score</strong>, optionally <strong>Collection_Date</strong> (2026-11-28) and <strong>Sample_Type</strong>, then the <?= count($biomarkers) ?> protein columns named exactly as in the template (for example <em>AFP (pg/ml)</em>).</p>

        <div class="form-actions">
          <button class="button" type="submit">Upload file</button>
          <a class="button ghost" href="upload_sample.php?template=1">Download template</a>
        </div>
      </form>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
