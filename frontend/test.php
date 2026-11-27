<?php
declare(strict_types=1);
$error = null;
$result = null;
$sampleId = '';
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function request_prediction(int $id): array {
    $apiBase = rtrim(getenv('PREDICTION_API_URL') ?: 'http://127.0.0.1:8000', '/');
    $curl = curl_init($apiBase . '/api/predict/sample/' . $id);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    if ($response === false) {
        throw new RuntimeException('Could not reach the prediction API: ' . $curlError);
    }
    $decoded = json_decode($response, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded)) {
        $detail = is_array($decoded) ? ($decoded['detail'] ?? 'Unexpected API response') : 'Invalid API response';
        throw new RuntimeException('Prediction failed: ' . $detail);
    }
    return $decoded;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sampleId = trim((string)($_POST['sample_id'] ?? ''));
    $validatedId = filter_var($sampleId, FILTER_VALIDATE_INT);
    if ($validatedId === false || $validatedId < 1) {
        $error = 'Enter a valid positive database Sample_ID.';
    } else {
        try {
            $result = request_prediction($validatedId);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Predict a saved sample</title>
  <style>
    body { font: 16px/1.5 system-ui, sans-serif; margin: 2rem auto; max-width: 760px; padding: 0 1rem; color: #17202a; }
    label { display: grid; gap: .35rem; max-width: 24rem; }
    input, button { font: inherit; padding: .55rem; }
    button { margin-top: .8rem; cursor: pointer; }
    .card { background: #f3f6f8; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
    .error { color: #9b1c1c; }
    .bar { background: #dbe3ea; height: 1.2rem; border-radius: 8px; overflow: hidden; }
    .bar span { display: block; height: 100%; background: #3678b8; }
  </style>
</head>
<body>
  <h1>Predict a saved sample</h1>
  <p>Enter the numeric <code>Sample_ID</code> from the database. The API reads that sample and runs all cancer models.</p>
  <?php if ($error !== null): ?>
    <p class="card error" role="alert"><?= h($error) ?></p>
  <?php endif; ?>
  <?php if ($result !== null):
    $topPercent = round(((float)$result['highest_probability']) * 100, 1);
    $pancancerPercent = round(((float)$result['pancancer_probability']) * 100, 1);
  ?>
    <section class="card" aria-live="polite">
      <h2>Prediction <?= (int)$result['prediction_id'] ?> for sample <?= (int)$result['sample_id'] ?></h2>
      <p>Highest cancer-specific score: <strong><?= h((string)$result['highest_cancer_type']) ?> (<?= $topPercent ?>%)</strong></p>
      <p>Highest model's binary result: <?= h((string)$result['highest_result']) ?></p>
      <p>Pancancer score: <?= $pancancerPercent ?>%</p>
      <h3>Scores by cancer type</h3>
      <?php foreach ($result['cancer_scores'] as $cancerType => $score):
        $scorePercent = round(((float)$score) * 100, 1);
      ?>
        <p><?= h((string)$cancerType) ?>: <?= $scorePercent ?>%</p>
        <div class="bar" role="img" aria-label="<?= h((string)$cancerType) ?> score <?= $scorePercent ?> percent">
          <span style="width: <?= max(0, min(100, $scorePercent)) ?>%"></span>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
  <form method="post" class="card">
    <label for="sample_id">Database Sample_ID
      <input id="sample_id" type="number" name="sample_id" min="1" step="1" required value="<?= h($sampleId) ?>">
    </label>
    <button type="submit">Predict sample</button>
  </form>
  <p class="card"><strong>Research demo:</strong> model scores are not a diagnosis or a clinically validated result.</p>
</body>
</html>
