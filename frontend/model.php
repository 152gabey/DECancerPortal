<?php
$pageTitle = 'Model performance';
$activePage = 'model';
require __DIR__ . '/includes/auth.php';

$modelResults = [
    'pancancer' => [
        'name' => 'Pan-cancer',
        'positive_label' => 'Cancer',
        'auc' => 0.995619021,
        'tn' => 136,
        'fp' => 2,
        'fn' => 6,
        'tp' => 147,
        'roc' => 'roc-Pancancer.jpg',
        'note' => 'Six of 153 cancer cases were missed, and two of 138 healthy controls were flagged.',
    ],
    'Breast' => [
        'name' => 'Breast',
        'positive_label' => 'Breast cancer',
        'auc' => 0.998380754,
        'tn' => 129,
        'fp' => 2,
        'fn' => 2,
        'tp' => 31,
        'roc' => 'roc-Breast.jpg',
        'note' => 'Two of 33 cancer cases were missed, and two healthy controls were flagged.',
    ],
    'Colorectum' => [
        'name' => 'Colorectum',
        'positive_label' => 'Colorectum cancer',
        'auc' => 0.995941853,
        'tn' => 121,
        'fp' => 6,
        'fn' => 0,
        'tp' => 63,
        'roc' => 'roc-Colorectum.jpg',
        'note' => 'All 63 cancer cases were detected, while six healthy controls were flagged.',
    ],
    'Esophagus' => [
        'name' => 'Esophagus',
        'positive_label' => 'Esophagus cancer',
        'auc' => 0.975,
        'tn' => 132,
        'fp' => 0,
        'fn' => 2,
        'tp' => 3,
        'roc' => 'roc-Esophagus.jpg',
        'note' => 'No healthy controls were flagged, but two of five cancer cases were missed. The 100% precision is based on only three cancer predictions.',
    ],
    'Liver' => [
        'name' => 'Liver',
        'positive_label' => 'Liver cancer',
        'auc' => 1,
        'tn' => 127,
        'fp' => 0,
        'fn' => 1,
        'tp' => 9,
        'roc' => 'roc-Liver.jpg',
        'note' => 'No healthy controls were flagged; one of ten cancer cases was missed.',
    ],
    'Lung' => [
        'name' => 'Lung',
        'positive_label' => 'Lung cancer',
        'auc' => 0.99638009,
        'tn' => 129,
        'fp' => 1,
        'fn' => 3,
        'tp' => 14,
        'roc' => 'roc-Lung.jpg',
        'note' => 'Three of 17 cancer cases were missed, and one healthy control was flagged.',
    ],
    'Ovary' => [
        'name' => 'Ovary',
        'positive_label' => 'Ovary cancer',
        'auc' => 0.999145299,
        'tn' => 130,
        'fp' => 0,
        'fn' => 0,
        'tp' => 6,
        'roc' => 'roc-Ovary.jpg',
        'note' => 'All six cancer cases and all 130 healthy controls were classified correctly; the cancer group is small.',
    ],
    'Pancreas' => [
        'name' => 'Pancreas',
        'positive_label' => 'Pancreas cancer',
        'auc' => 0.993076923,
        'tn' => 129,
        'fp' => 1,
        'fn' => 3,
        'tp' => 12,
        'roc' => 'roc-Pancreas.jpg',
        'note' => 'Three of 15 cancer cases were missed, and one healthy control was flagged.',
    ],
    'Stomach' => [
        'name' => 'Stomach',
        'positive_label' => 'Stomach cancer',
        'auc' => 0.977,
        'tn' => 125,
        'fp' => 0,
        'fn' => 4,
        'tp' => 12,
        'roc' => 'roc-Stomach.jpg',
        'note' => 'No healthy controls were flagged, but four of 16 cancer cases were missed.',
    ],
];

function modelHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function modelPercentage(int $numerator, int $denominator): string
{
    return $denominator > 0
        ? number_format(($numerator / $denominator) * 100, 1) . '%'
        : '—';
}

function modelMetrics(array $result): array
{
    $trueNegatives = $result['tn'];
    $falsePositives = $result['fp'];
    $falseNegatives = $result['fn'];
    $truePositives = $result['tp'];
    $total = $trueNegatives + $falsePositives + $falseNegatives + $truePositives;

    return [
        'precision' => modelPercentage($truePositives, $truePositives + $falsePositives),
        'recall' => modelPercentage($truePositives, $truePositives + $falseNegatives),
        'accuracy' => modelPercentage($truePositives + $trueNegatives, $total),
        'f1' => modelPercentage(
            2 * $truePositives,
            (2 * $truePositives) + $falsePositives + $falseNegatives
        ),
    ];
}

$requestedModel = $_GET['model'] ?? 'pancancer';
$selectionError = !is_string($requestedModel) || !array_key_exists($requestedModel, $modelResults);
if ($selectionError && array_key_exists('model', $_GET)) {
    http_response_code(400);
}
$selectedKey = $selectionError ? 'pancancer' : $requestedModel;
$selected = $modelResults[$selectedKey];
$selectedMetrics = modelMetrics($selected);

include __DIR__ . '/includes/header.php';
?>
      <section class="card model-intro">
        <div>
          <span class="badge">Held-out test set · 20%</span>
          <h2>Random Forest model evaluation</h2>
        </div>
        <p>These results describe the cancer-versus-healthy test split. They are test-set results only; training-set performance is not displayed on this page.</p>
        <p class="muted-text">Inputs: 39 protein biomarkers, age, sex, race and the &Omega; score. This is exploratory research output, not a clinical diagnostic tool.</p>
      </section>

      <?php if ($selectionError): ?>
        <div class="alert" role="alert">That model selection is not available. Showing the pan-cancer test results instead.</div>
      <?php endif; ?>

      <section class="card model-selector">
        <div>
          <h2>Results by cancer type</h2>
          <p class="muted-text">Select a model to view its ROC curve and confusion matrix.</p>
        </div>
        <form action="model.php" method="get" class="model-selector-form">
          <label for="model-select">Cancer type</label>
          <select id="model-select" name="model">
            <?php foreach ($modelResults as $key => $result): ?>
              <option value="<?= modelHtml($key) ?>"<?= $key === $selectedKey ? ' selected' : '' ?>><?= modelHtml($result['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="button" type="submit">Show results</button>
        </form>
      </section>

      <section class="model-metrics" aria-label="<?= modelHtml($selected['name']) ?> test metrics">
        <div class="model-metric">
          <span>ROC AUC</span>
          <strong><?= number_format($selected['auc'], 4) ?></strong>
          <small>How well the model separates cancer from cancer-free across thresholds.</small>
        </div>
        <div class="model-metric">
          <span>Accuracy</span>
          <strong><?= $selectedMetrics['accuracy'] ?></strong>
          <small>Share of all predictions that were correct.</small>
        </div>
        <div class="model-metric">
          <span>Precision</span>
          <strong><?= $selectedMetrics['precision'] ?></strong>
          <small>Of cancer predictions, this share was correct.</small>
        </div>
        <div class="model-metric">
          <span>Recall</span>
          <strong><?= $selectedMetrics['recall'] ?></strong>
          <small>Of cancer cases, this share was detected.</small>
        </div>
        <div class="model-metric">
          <span>F1 score</span>
          <strong><?= $selectedMetrics['f1'] ?></strong>
          <small>Balance between precision and recall.</small>
        </div>
      </section>

      <section class="model-visuals" aria-label="<?= modelHtml($selected['name']) ?> test visualizations">
        <figure class="card model-figure">
          <div class="model-figure-heading">
            <div><span class="model-kicker">01 / DISCRIMINATION</span><h2>Receiver operating characteristic</h2></div>
            <span class="badge">AUC <?= number_format($selected['auc'], 4) ?></span>
          </div>
          <img src="model_results/<?= modelHtml($selected['roc']) ?>" alt="<?= modelHtml($selected['name']) ?> test-set ROC curve">
          <figcaption class="muted-text">The ROC curve shows sensitivity against 1 − specificity as the classification threshold changes.</figcaption>
        </figure>

        <figure class="card model-figure">
          <div class="model-figure-heading">
            <div><span class="model-kicker">02 / CLASSIFICATION</span><h2>Confusion matrix</h2></div>
            <span class="badge">TEST SET</span>
          </div>
          <p class="model-matrix-note">Rows are actual classes; columns are model predictions. Cancer is the positive class.</p>
          <div class="table-wrap">
            <table class="model-matrix">
              <caption class="sr-only"><?= modelHtml($selected['name']) ?> test-set confusion matrix</caption>
              <thead>
                <tr>
                  <th scope="col">Actual / predicted</th>
                  <th scope="col">Cancer-free</th>
                  <th scope="col"><?= modelHtml($selected['positive_label']) ?></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <th scope="row">Cancer-free</th>
                  <td class="matrix-tn"><strong><?= $selected['tn'] ?></strong><small>True negative</small></td>
                  <td class="matrix-fp"><strong><?= $selected['fp'] ?></strong><small>False positive</small></td>
                </tr>
                <tr>
                  <th scope="row"><?= modelHtml($selected['positive_label']) ?></th>
                  <td class="matrix-fn"><strong><?= $selected['fn'] ?></strong><small>False negative</small></td>
                  <td class="matrix-tp"><strong><?= $selected['tp'] ?></strong><small>True positive</small></td>
                </tr>
              </tbody>
            </table>
          </div>
          <figcaption class="muted-text"><?= modelHtml($selected['note']) ?></figcaption>
        </figure>
      </section>

      <section class="card model-comparison">
        <div class="section-head">
          <div>
            <h2>Test metrics across all models</h2>
            <p class="muted-text">Choose a model name to inspect its plots and confusion matrix.</p>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th scope="col">Model</th>
                <th scope="col">AUC</th>
                <th scope="col">TN</th>
                <th scope="col">FP</th>
                <th scope="col">FN</th>
                <th scope="col">TP</th>
                <th scope="col">Precision</th>
                <th scope="col">Recall</th>
                <th scope="col">Accuracy</th>
                <th scope="col">F1 score</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($modelResults as $key => $result): ?>
                <?php $metrics = modelMetrics($result); ?>
                <tr<?= $key === $selectedKey ? ' class="selected-model"' : '' ?>>
                  <th scope="row"><a href="model.php?model=<?= rawurlencode($key) ?>"<?= $key === $selectedKey ? ' aria-current="page"' : '' ?>><?= modelHtml($result['name']) ?></a></th>
                  <td><?= number_format($result['auc'], 4) ?></td>
                  <td><?= $result['tn'] ?></td>
                  <td><?= $result['fp'] ?></td>
                  <td><?= $result['fn'] ?></td>
                  <td><?= $result['tp'] ?></td>
                  <td><?= $metrics['precision'] ?></td>
                  <td><?= $metrics['recall'] ?></td>
                  <td><?= $metrics['accuracy'] ?></td>
                  <td><?= $metrics['f1'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="model-footnote">TN = healthy correctly classified; FP = healthy classified as cancer; FN = cancer missed; TP = cancer correctly detected. Precision = TP ÷ (TP + FP); recall = TP ÷ (TP + FN); accuracy = (TP + TN) ÷ total; F1 is the harmonic mean of precision and recall. Several cancer test groups are small, so interpret percentages alongside the counts.</p>
      </section>

<?php include __DIR__ . '/includes/footer.php'; ?>
