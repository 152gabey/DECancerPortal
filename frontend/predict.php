<?php
$pageTitle = 'Predict';
$activePage = 'predict';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

date_default_timezone_set('Australia/Sydney');

// Escape dynamic values before inserting them into HTML.
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Validate a one-sample CSV and return its measurements keyed by Biomarker_ID.
function parseBiomarkerCsv(array $file, array $biomarkers): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The uploaded CSV could not be read. Please try again.');
    }
    if (($file['size'] ?? 0) > 1024 * 1024) {
        throw new RuntimeException('The CSV file must be no larger than 1 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('The uploaded CSV could not be read. Please try again.');
    }

    $handle = fopen($file['tmp_name'], 'rb');
    if ($handle === false) {
        throw new RuntimeException('The uploaded CSV could not be opened.');
    }

    try {
        $headers = fgetcsv($handle, null, ',', '"', '');
        if ($headers === false) {
            throw new RuntimeException('The CSV file is empty.');
        }
        $headers = array_map(static fn($header): string => trim((string) $header), $headers);
        if ($headers) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        // Accept both unit-labelled template headers and older plain-name headers.
        $headerAliases = [];
        foreach ($biomarkers as $biomarker) {
            $name = (string) $biomarker['Name'];
            $unit = trim((string) $biomarker['Unit']);
            $headerAliases[$name] = $name;
            if ($unit !== '') {
                $headerAliases[$name . ' (' . $unit . ')'] = $name;
            }
        }
        $headers = array_map(
            static fn($header): string => $headerAliases[$header] ?? $header,
            $headers
        );

        $expectedNames = array_column($biomarkers, 'Name');
        if (count($expectedNames) !== 39
            || array_map('intval', array_column($biomarkers, 'Biomarker_ID')) !== range(1, 39)) {
            throw new RuntimeException('The database biomarker list is incomplete or out of order.');
        }
        if (count($headers) !== 39
            || count(array_unique($headers)) !== 39
            || array_diff($expectedNames, $headers)
            || array_diff($headers, $expectedNames)) {
            throw new RuntimeException('The CSV header must contain each of the 39 biomarker names from the template exactly once.');
        }

        $headerIndexes = array_flip($headers);
        // Ignore blank rows, but allow only one completed sample row.
        $row = null;
        while (($nextRow = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if (!array_filter($nextRow, static fn($value): bool => trim((string) $value) !== '')) {
                continue;
            }
            if ($row !== null) {
                throw new RuntimeException('The CSV must contain exactly one non-empty sample row.');
            }
            $row = $nextRow;
        }
        if ($row === null || count($row) !== 39) {
            throw new RuntimeException('The CSV must contain one row with a value for every biomarker.');
        }

        $values = [];
        foreach ($biomarkers as $biomarker) {
            $rawValue = trim((string) ($row[$headerIndexes[$biomarker['Name']]] ?? ''));
            if ($rawValue === '' || !is_numeric($rawValue)) {
                throw new RuntimeException('Enter a numeric value for every biomarker in the CSV.');
            }
            $value = (float) $rawValue;
            if (!is_finite($value) || $value < 0) {
                throw new RuntimeException('Biomarker values must be finite numbers greater than or equal to zero.');
            }
            $values[(int) $biomarker['Biomarker_ID']] = $value;
        }

        return $values;
    } finally {
        fclose($handle);
    }
}

// Send a saved Sample_ID to the model API and return its decoded prediction.
function requestPrediction(int $sampleId): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required to contact the prediction service.');
    }

    $apiBase = rtrim(getenv('PREDICTION_API_URL') ?: 'http://127.0.0.1:8000', '/');
    $curl = curl_init($apiBase . '/api/predict/sample/' . $sampleId);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize a connection to the prediction service.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    if ($response === false) {
        throw new RuntimeException('Could not reach the prediction service: ' . $curlError);
    }

    $decoded = json_decode($response, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded)) {
        $detail = is_array($decoded) ? ($decoded['detail'] ?? 'Unexpected API response') : 'Invalid API response';
        throw new RuntimeException('Prediction failed: ' . (is_string($detail) ? $detail : 'The API rejected the request.'));
    }

    return $decoded;
}

// Map stable database biomarker IDs to the corresponding HTML form field names.
$biomarkerFields = [
    1 => 'afp',
    2 => 'angiopoietin_2',
    3 => 'axl',
    4 => 'ca_125',
    5 => 'ca_15_3',
    6 => 'ca19_9',
    7 => 'cd44',
    8 => 'cea',
    9 => 'cyfra_21_1',
    10 => 'dkk1',
    11 => 'endoglin',
    12 => 'fgf2',
    13 => 'follistatin',
    14 => 'galectin_3',
    15 => 'g_csf',
    16 => 'gdf15',
    17 => 'he4',
    18 => 'hgf',
    19 => 'il_6',
    20 => 'il_8',
    21 => 'kallikrein_6',
    22 => 'leptin',
    23 => 'mesothelin',
    24 => 'midkine',
    25 => 'myeloperoxidase',
    26 => 'nse',
    27 => 'opg',
    28 => 'opn',
    29 => 'par',
    30 => 'prolactin',
    31 => 'segfr',
    32 => 'sfas',
    33 => 'shbg',
    34 => 'sher2_segfr2_serbb2',
    35 => 'specam_1',
    36 => 'tgfa',
    37 => 'thrombospondin_2',
    38 => 'timp_1',
    39 => 'timp_2',
];
$biomarkerGroups = [
    'Cancer antigens' => [1, 4, 5, 6, 8, 9, 17, 21, 23, 26, 30],
    'Cytokines and growth factors' => [2, 10, 12, 13, 15, 16, 18, 19, 20, 22, 24, 36],
    'Receptors, adhesion and matrix' => [3, 7, 11, 14, 27, 28, 31, 32, 34, 35, 37, 38, 39],
    'Other proteins' => [25, 29, 33],
];

// Load database-backed names and units for the form, CSV template, and feedback choices.
$db = getDb();
$biomarkers = $db->query('SELECT Biomarker_ID, Name, Unit FROM Biomarkers ORDER BY Biomarker_ID')->fetchAll();
$cancerOptions = $db->query(
    'SELECT Cancer_ID, Cancer_Name FROM Cancer_Types ORDER BY Cancer_ID'
)->fetchAll();
$biomarkersById = [];
foreach ($biomarkers as $biomarker) {
    $biomarkersById[(int) $biomarker['Biomarker_ID']] = $biomarker;
}

// Build the downloadable template from the same biomarker list used by CSV validation.
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="biomarker_sample_template.csv"');
    $output = fopen('php://output', 'wb');
    $headers = array_map(
        static function ($biomarker): string {
            $name = (string) $biomarker['Name'];
            $unit = trim((string) $biomarker['Unit']);
            return $unit === '' ? $name : $name . ' (' . $unit . ')';
        },
        $biomarkers
    );
    fputcsv($output, $headers, ',', '"', '');
    fputcsv($output, array_fill(0, count($biomarkers), ''), ',', '"', '');
    fclose($output);
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$old = [
    'patient_id' => '',
    'sample_code' => '',
    'collection_date' => '',
    'sample_type' => 'Plasma',
    'age_at_collection' => '',
    'omega_score' => '',
    'saved_sample_id' => (string) ($_GET['sample_id'] ?? ''),
    'feedback_type' => '',
    'suggested_cancer_id' => '',
    'feedback_comment' => '',
];
foreach ($biomarkerFields as $field) {
    $old[$field] = '';
}

// Restore the latest result and one-time notices after redirects such as prediction or feedback.
$errors = [];
$predictionResult = $_SESSION['prediction_flash'] ?? null;
$sampleSavedNotice = $_SESSION['sample_saved_flash'] ?? null;
if ($predictionResult !== null) {
    $_SESSION['current_prediction'] = $predictionResult;
} else {
    $currentPrediction = $_SESSION['current_prediction'] ?? null;
    $requestedPredictionId = filter_var(
        $_GET['prediction_id'] ?? $_POST['prediction_id'] ?? false,
        FILTER_VALIDATE_INT
    );
    if (is_array($currentPrediction)
        && $requestedPredictionId !== false
        && (int) ($currentPrediction['prediction_id'] ?? 0) === $requestedPredictionId) {
        $predictionResult = $currentPrediction;
    }
}
$feedbackNotice = $_SESSION['feedback_flash'] ?? null;
unset($_SESSION['prediction_flash'], $_SESSION['sample_saved_flash'], $_SESSION['feedback_flash']);

// Dispatch each submitted action only after checking the session CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $unused) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $old[$key] = trim($_POST[$key]);
        }
    }

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Your session has expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'run_saved') {
        $sampleId = filter_var($old['saved_sample_id'], FILTER_VALIDATE_INT);
        if ($sampleId === false || $sampleId < 1) {
            $errors[] = 'Enter a valid positive Sample_ID.';
        } else {
            try {
                // Request a prediction from the model API and redirect to the same page with its Prediction ID in the query string.
                $predictionResult = requestPrediction($sampleId);
                $predictionId = filter_var($predictionResult['prediction_id'] ?? false, FILTER_VALIDATE_INT);
                if ($predictionId === false || $predictionId < 1) {
                    throw new RuntimeException('The prediction service returned no valid Prediction_ID.');
                }
                $_SESSION['prediction_flash'] = $predictionResult;
                header('Location: predict.php?sample_id=' . $sampleId . '&prediction_id=' . $predictionId);
                exit;
            } catch (RuntimeException $error) {
                $errors[] = $error->getMessage();
            }
        }
    } elseif (($_POST['action'] ?? '') === 'submit_feedback') {
        $predictionId = filter_var($_POST['prediction_id'] ?? false, FILTER_VALIDATE_INT);
        $feedbackType = $old['feedback_type'];
        $suggestedCancerId = $old['suggested_cancer_id'] === ''
            ? null
            : filter_var($old['suggested_cancer_id'], FILTER_VALIDATE_INT);
        $comment = $old['feedback_comment'];

        if ($predictionId === false || $predictionId < 1
            || $predictionResult === null
            || (int) ($predictionResult['prediction_id'] ?? 0) !== $predictionId) {
            $errors[] = 'This prediction is no longer available. Run it again before submitting feedback.';
        }
        if (!in_array($feedbackType, ['correct', 'incorrect', 'unsure'], true)) {
            $errors[] = 'Choose a feedback option.';
        }
        if ($suggestedCancerId === false) {
            $errors[] = 'Choose a valid suggested cancer or leave that field empty.';
        } elseif ($suggestedCancerId !== null) {
            $validCancerIds = array_map('intval', array_column($cancerOptions, 'Cancer_ID'));
            if (!in_array($suggestedCancerId, $validCancerIds, true)) {
                $errors[] = 'Choose a valid suggested cancer or leave that field empty.';
            }
        }
        if (mb_strlen($comment) > 2000) {
            $errors[] = 'Feedback comments must be 2000 characters or fewer.';
        }

        if (!$errors) {
            $existingFeedback = $db->prepare(
                'SELECT Feedback_ID FROM Prediction_Feedback WHERE Prediction_ID = ? AND User_ID = ?'
            );
            $existingFeedback->execute([$predictionId, (int) $_SESSION['user_id']]);
            if ($existingFeedback->fetchColumn()) {
                $errors[] = 'You have already submitted feedback for this prediction.';
            } else {
                try {
                    $insertFeedback = $db->prepare(
                        'INSERT INTO Prediction_Feedback
                            (Prediction_ID, User_ID, Feedback_Type, Suggested_Cancer_ID, Feedback_Comment)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $insertFeedback->execute([
                        $predictionId,
                        (int) $_SESSION['user_id'],
                        $feedbackType,
                        $suggestedCancerId,
                        $comment !== '' ? $comment : null,
                    ]);
                    $_SESSION['feedback_flash'] = 'Thank you. Your feedback has been saved.';
                    header('Location: predict.php?sample_id=' . (int) $predictionResult['sample_id']
                        . '&prediction_id=' . $predictionId);
                    exit;
                } catch (PDOException $error) {
                    if ($error->getCode() === '23000') {
                        $errors[] = 'You have already submitted feedback for this prediction.';
                    } else {
                        error_log('Prediction feedback failed: ' . $error->getMessage());
                        $errors[] = 'Your feedback could not be saved. Check the PHP error log for details.';
                    }
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'save_sample') {
        // Validate sample metadata and all biomarker values before writing anything.
        $patientId = filter_var($old['patient_id'], FILTER_VALIDATE_INT);
        if ($patientId === false || $patientId < 1) {
            $errors[] = 'Enter a valid existing Patient_ID.';
        }
        if (mb_strlen($old['sample_code']) > 30) {
            $errors[] = 'Sample code must be 30 characters or fewer.';
        }
        if (mb_strlen($old['sample_type']) > 50) {
            $errors[] = 'Sample type must be 50 characters or fewer.';
        }

        $age = filter_var($old['age_at_collection'], FILTER_VALIDATE_INT);
        if ($age === false || $age < 0 || $age > 120) {
            $errors[] = 'Age at collection must be a whole number from 0 to 120.';
        }
        if ($old['omega_score'] === '' || !is_numeric($old['omega_score']) || !is_finite((float) $old['omega_score'])) {
            $errors[] = 'Enter a valid numeric omega score.';
        }

        $collectionDate = null;
        if ($old['collection_date'] !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $old['collection_date']);
            if (!$date || $date->format('Y-m-d') !== $old['collection_date']) {
                $errors[] = 'Enter a valid collection date.';
            } elseif ($date > new DateTimeImmutable('today')) {
                $errors[] = 'Collection date cannot be in the future.';
            } else {
                $collectionDate = $old['collection_date'];
            }
        }

        $measurements = null;
        $uploadError = $_FILES['biomarker_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!$errors && $uploadError !== UPLOAD_ERR_NO_FILE) {
            try {
                $measurements = parseBiomarkerCsv($_FILES['biomarker_file'], $biomarkers);
            } catch (RuntimeException $error) {
                $errors[] = $error->getMessage();
            }
        } elseif (!$errors) {
            $measurements = [];
            foreach ($biomarkers as $biomarker) {
                $biomarkerId = (int) $biomarker['Biomarker_ID'];
                $field = $biomarkerFields[$biomarkerId] ?? null;
                if ($field === null || !isset($_POST[$field]) || !is_string($_POST[$field]) || trim($_POST[$field]) === '') {
                    $errors[] = 'Enter all 39 biomarker values or upload a CSV file.';
                    break;
                }
                $rawValue = trim($_POST[$field]);
                if (!is_numeric($rawValue)) {
                    $errors[] = 'Biomarker values must be numeric.';
                    break;
                }
                $value = (float) $rawValue;
                if (!is_finite($value) || $value < 0) {
                    $errors[] = 'Biomarker values must be finite numbers greater than or equal to zero.';
                    break;
                }
                $measurements[$biomarkerId] = $value;
            }
        }

        if (!$errors) {
            $patient = $db->prepare(
                'SELECT st.Sex_Name, rt.Race_Name
                 FROM Patients p
                 JOIN Sex_Types st ON st.Sex_Type_ID = p.Sex_Type_ID
                 JOIN Race_Types rt ON rt.Race_Type_ID = p.Race_Type_ID
                 WHERE p.Patient_ID = ?'
            );
            $patient->execute([$patientId]);
            $patientDetails = $patient->fetch();
            if (!$patientDetails) {
                $errors[] = 'That Patient_ID was not found. Find an existing patient in the Patients section.';
            } elseif (!in_array($patientDetails['Sex_Name'], ['Female', 'Male'], true)
                || !in_array($patientDetails['Race_Name'], [
                    'Asian', 'Black', 'Black/Hispanic', 'Caucasian',
                    'Caucasian/Hispanic', 'Hispanic', 'Other', 'Unknown',
                ], true)) {
                $errors[] = 'This patient has demographic values that are not supported by the prediction model.';
            }
        }

        if (!$errors) {
            try {
                // Save the sample and its 39 measurements atomically.
                $db->beginTransaction();
                $insertSample = $db->prepare(
                    'INSERT INTO Samples (Sample_Code, Patient_ID, Uploaded_By, Collection_Date, Sample_Type, Age_At_Collection, Omega_Score)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $insertSample->execute([
                    $old['sample_code'] !== '' ? $old['sample_code'] : null,
                    $patientId,
                    (int) $_SESSION['user_id'],
                    $collectionDate,
                    $old['sample_type'] !== '' ? $old['sample_type'] : null,
                    $age,
                    (float) $old['omega_score'],
                ]);
                $sampleId = (int) $db->lastInsertId();

                $insertMeasurement = $db->prepare(
                    'INSERT INTO Biomarker_Measurements (Sample_ID, Biomarker_ID, Value) VALUES (?, ?, ?)'
                );
                foreach ($measurements as $biomarkerId => $value) {
                    $insertMeasurement->execute([$sampleId, $biomarkerId, $value]);
                }
                $db->commit();
            } catch (PDOException $error) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Sample upload failed: ' . $error->getMessage());
                $errors[] = $error->getCode() === '23000'
                    ? 'That sample code is already in use or the submitted records violate a database constraint.'
                    : 'The sample could not be saved. Check the PHP error log for details.';
            }

            if (!$errors) {
                $_SESSION['sample_saved_flash'] = 'Sample saved successfully as Sample_ID ' . $sampleId
                    . '. Its ID has been entered in the prediction form.';
                header('Location: predict.php?sample_id=' . $sampleId);
                exit;
            }
        }
    } else {
        $errors[] = 'Choose a valid sample action and try again.';
    }
}

$feedbackSubmitted = false;
$predictionPatientId = null;
if ($predictionResult !== null) {
    // Resolve the patient for the exact-sample details link and check for existing feedback.
    $samplePatient = $db->prepare('SELECT Patient_ID FROM Samples WHERE Sample_ID = ?');
    $samplePatient->execute([(int) $predictionResult['sample_id']]);
    $patientId = $samplePatient->fetchColumn();
    if ($patientId !== false) {
        $predictionPatientId = (int) $patientId;
    }

    $feedbackCheck = $db->prepare(
        'SELECT Feedback_ID FROM Prediction_Feedback WHERE Prediction_ID = ? AND User_ID = ?'
    );
    $feedbackCheck->execute([
        (int) $predictionResult['prediction_id'],
        (int) $_SESSION['user_id'],
    ]);
    $feedbackSubmitted = (bool) $feedbackCheck->fetchColumn();
}

include __DIR__ . '/includes/header.php';
?>
<!-- Sample entry stays on the left; prediction, result, and feedback stay on the right. -->
<div class="predict">
  <section class="card form">
    <h2>Save a sample</h2>
    <p class="muted-text">Enter biomarker values manually or upload a CSV, then save the sample. Saving does not run a prediction.</p>

    <?php if ($errors && !in_array($_POST['action'] ?? '', ['run_saved', 'submit_feedback'], true)): ?>
      <div class="alert" role="alert">
        <?php foreach ($errors as $error): ?><div><?= h($error) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($sampleSavedNotice !== null): ?>
      <p class="note" role="status"><?= h($sampleSavedNotice) ?> Select <strong>Run prediction</strong> on the right when ready.</p>
    <?php endif; ?>

    <form action="predict.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="save_sample">
      <input type="hidden" name="MAX_FILE_SIZE" value="1048576">
      <fieldset class="group-box">
        <legend>Patient and sample details</legend>
        <div class="fields">
          <div class="field">
            <label for="patient_id">Existing Patient_ID</label>
            <input id="patient_id" name="patient_id" type="number" min="1" step="1" value="<?= h($old['patient_id']) ?>" required>
            <small>Look up the patient in <a href="patients.php">Patients</a>. Sex and race are read from their record.</small>
          </div>
          <div class="field"><label for="sample_code">Sample code (optional)</label><input id="sample_code" name="sample_code" type="text" maxlength="30" value="<?= h($old['sample_code']) ?>"><small>Leave blank if there is no external code. The generated Sample_ID is used for prediction.</small></div>
          <div class="field"><label for="collection_date">Collection date (optional)</label><input id="collection_date" name="collection_date" type="date" max="<?= date('Y-m-d') ?>" value="<?= h($old['collection_date']) ?>"></div>
          <div class="field"><label for="sample_type">Sample type</label><input id="sample_type" name="sample_type" type="text" maxlength="50" value="<?= h($old['sample_type']) ?>" placeholder="Plasma"></div>
          <div class="field"><label for="age_at_collection">Age at collection</label><input id="age_at_collection" name="age_at_collection" type="number" min="0" max="120" step="1" value="<?= h($old['age_at_collection']) ?>" required></div>
          <div class="field"><label for="omega_score">&Omega; score</label><input id="omega_score" name="omega_score" type="number" step="any" value="<?= h($old['omega_score']) ?>" required></div>
        </div>
      </fieldset>

      <div class="upload">
        <div>
          <strong>Optional CSV import</strong>
          <small>Upload one row with a header for each of the 39 biomarkers; units are shown in the headers.</small>
          <a class="text-link" href="predict.php?download_template=1">Download CSV template</a>
        </div>
        <input type="file" name="biomarker_file" accept=".csv,text/csv" aria-label="Upload biomarker CSV">
      </div>

      <h2 class="form-title">Blood protein biomarkers</h2>
      <?php foreach ($biomarkerGroups as $groupName => $biomarkerIds): ?>
        <details class="group" open>
          <summary><?= h($groupName) ?><small><?= count($biomarkerIds) ?> proteins</small></summary>
          <div class="fields">
            <?php foreach ($biomarkerIds as $biomarkerId):
                $biomarker = $biomarkersById[$biomarkerId];
                $field = $biomarkerFields[$biomarkerId];
            ?>
              <div class="field">
                <label for="<?= h($field) ?>"><?= h($biomarker['Name']) ?></label>
                <div class="input-unit">
                  <input id="<?= h($field) ?>" name="<?= h($field) ?>" type="number" step="any" min="0" inputmode="decimal" value="<?= h($old[$field]) ?>">
                  <span><?= h($biomarker['Unit']) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>

      <div class="form-actions">
        <button class="button" type="submit">Save sample</button>
        <button class="button ghost" type="reset">Clear form</button>
      </div>
    </form>
  </section>

  <div class="predict-side">
    <section class="card form">
      <h2>Predict an already saved sample</h2>
      <p class="muted-text">Enter a database Sample_ID, then run the saved sample through the model.</p>
      <?php if ($errors && ($_POST['action'] ?? '') === 'run_saved'): ?>
        <div class="alert" role="alert">
          <?php foreach ($errors as $error): ?><div><?= h($error) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <form action="predict.php" method="post">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="run_saved">
        <div class="fields">
          <div class="field"><label for="saved_sample_id">Database Sample_ID</label><input id="saved_sample_id" name="saved_sample_id" type="number" min="1" step="1" value="<?= h($old['saved_sample_id']) ?>" required></div>
        </div>
        <div class="form-actions"><button class="button" type="submit">Run prediction</button></div>
      </form>
    </section>

    <?php if ($predictionResult !== null): ?>
      <section class="card results" aria-live="polite">
        <div class="card-head"><h2>Prediction result</h2><span class="badge">Sample <?= (int) $predictionResult['sample_id'] ?></span></div>
        <?php if ($predictionPatientId !== null): ?>
          <p class="meta"><a class="text-link" href="patient.php?id=<?= $predictionPatientId ?>&amp;sample=<?= (int) $predictionResult['sample_id'] ?>">View patient and input details</a></p>
        <?php endif; ?>
        <div class="result-top">
          <div>
            <small>Pancancer probability</small>
            <strong class="big"><?= number_format((float) $predictionResult['pancancer_probability'], 3) ?></strong>
            <span class="pill <?= $predictionResult['highest_result'] === 'cancer' ? 'high' : 'low' ?>"><?= h(ucfirst((string) $predictionResult['highest_result'])) ?></span>
          </div>
          <div>
            <small>Highest cancer-specific score</small>
            <strong><?= h((string) $predictionResult['highest_cancer_type']) ?></strong>
            <span><?= number_format((float) $predictionResult['highest_probability'] * 100, 1) ?>%</span>
          </div>
        </div>
        <h3>Likelihood by cancer type</h3>
        <?php foreach ($predictionResult['cancer_scores'] as $cancerType => $score):
            $scorePercent = max(0, min(100, (float) $score * 100));
        ?>
          <div class="bar-row wide">
            <span class="bar-label"><?= h((string) $cancerType) ?></span>
            <div class="bar-track"><div class="bar-fill<?= $cancerType === $predictionResult['highest_cancer_type'] ? '' : ' muted' ?>" style="width: <?= $scorePercent ?>%"></div></div>
            <span class="bar-value"><?= number_format((float) $score, 3) ?></span>
          </div>
        <?php endforeach; ?>
        <p class="muted-text">Prediction ID <?= (int) $predictionResult['prediction_id'] ?>. Research demo only; this output is not a diagnosis.</p>

      <div class="feedback-panel">
        <h2>Was this prediction right?</h2>
        <?php if ($feedbackNotice !== null): ?>
          <p class="note" role="status"><?= h($feedbackNotice) ?></p>
        <?php endif; ?>
        <?php if ($errors && ($_POST['action'] ?? '') === 'submit_feedback'): ?>
          <div class="alert" role="alert">
            <?php foreach ($errors as $error): ?><div><?= h($error) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($feedbackSubmitted): ?>
          <p class="muted-text">You have already submitted feedback for this prediction.</p>
        <?php else: ?>
          <form class="feedback" action="predict.php" method="post">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="submit_feedback">
            <input type="hidden" name="prediction_id" value="<?= (int) $predictionResult['prediction_id'] ?>">
            <div class="choice" role="radiogroup" aria-label="Feedback on this prediction">
              <?php foreach (['correct' => 'Correct', 'incorrect' => 'Incorrect', 'unsure' => 'Unsure'] as $value => $label): ?>
                <label><input type="radio" name="feedback_type" value="<?= h($value) ?>"<?= $old['feedback_type'] === $value ? ' checked' : '' ?> required><span><?= h($label) ?></span></label>
              <?php endforeach; ?>
            </div>
            <label for="suggested_cancer_id">If incorrect, what do you think it is?</label>
            <select id="suggested_cancer_id" name="suggested_cancer_id">
              <option value="">Select (optional)</option>
              <?php foreach ($cancerOptions as $cancer): ?>
                <option value="<?= (int) $cancer['Cancer_ID'] ?>"<?= (string) $cancer['Cancer_ID'] === $old['suggested_cancer_id'] ? ' selected' : '' ?>><?= h($cancer['Cancer_Name']) ?></option>
              <?php endforeach; ?>
            </select>
            <label for="feedback_comment">Comment (optional)</label>
            <textarea id="feedback_comment" name="feedback_comment" rows="3" maxlength="2000"><?= h($old['feedback_comment']) ?></textarea>
            <button class="button" type="submit">Send feedback</button>
          </form>
        <?php endif; ?>
      </div>
      </section>
    <?php endif; ?>
  </div>

  <p class="note">Model scores are for research demonstration only and are not a clinical diagnosis.</p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
