<?php
$pageTitle = 'Add a patient';
$activePage = 'patients';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/db.php';

date_default_timezone_set('Australia/Sydney');

function h($value): string            // makes text safe to print inside HTML
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}

$db = getDb();

// The only values the dropdowns may send, read from the lookup tables as [id => name]
$sexOptions  = $db->query('SELECT Sex_Type_ID, Sex_Name FROM Sex_Types ORDER BY Sex_Type_ID')->fetchAll(PDO::FETCH_KEY_PAIR);
$raceOptions = $db->query('SELECT Race_Type_ID, Race_Name FROM Race_Types ORDER BY Race_Type_ID')->fetchAll(PDO::FETCH_KEY_PAIR);

// A secret code stored in the session and sent back with the form.
// It proves the form came from this site and not from somewhere else.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'date_of_birth' => '', 'sex' => '', 'race' => '', 'country' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Read what was typed
    foreach ($old as $key => $unused) {
        $value = $_POST[$key] ?? '';
        $old[$key] = is_string($value) ? trim($value) : '';
    }

    // 2. Check it
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Your session has expired. Please try again.';
    }
    if ($old['first_name'] === '' || mb_strlen($old['first_name']) > 50) {
        $errors[] = 'First name is required (up to 50 characters).';
    }
    if ($old['last_name'] === '' || mb_strlen($old['last_name']) > 50) {
        $errors[] = 'Last name is required (up to 50 characters).';
    }

    $dob = DateTime::createFromFormat('Y-m-d', $old['date_of_birth']);
    if (!$dob || $dob->format('Y-m-d') !== $old['date_of_birth']) {
        $errors[] = 'Enter a valid date of birth.';
    } elseif ($dob > new DateTime('today') || $dob < new DateTime('1900-01-01')) {
        $errors[] = 'Date of birth must be between 1900 and today.';
    }

    if (!in_array($old['sex'], $sexOptions, true)) {
        $errors[] = 'Choose a sex from the list.';
    }
    if (!in_array($old['race'], $raceOptions, true)) {
        $errors[] = 'Choose a race from the list.';
    }
    if (mb_strlen($old['country']) > 60) {
        $errors[] = 'Country must be 60 characters or fewer.';
    }

    // 3. If everything is fine, save the patient and open their page
    if (!$errors) {
        // The table stores the ID of the chosen sex and race, so look those up from the names
        $sexId  = array_search($old['sex'], $sexOptions, true);
        $raceId = array_search($old['race'], $raceOptions, true);

        $stmt = $db->prepare('INSERT INTO Patients (FirstName, LastName, Date_of_Birth, Sex_Type_ID, Race_Type_ID, Country, Created_Date)
            VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $old['first_name'],
            $old['last_name'],
            $old['date_of_birth'],
            $sexId,
            $raceId,
            $old['country'] !== '' ? $old['country'] : null,
        ]);

        header('Location: patient.php?id=' . (int) $db->lastInsertId());
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>
      <p><a class="text-link" href="patients.php">&larr; All patients</a></p>

      <form class="card form" action="add_patient.php" method="post" novalidate>
        <h2>New patient</h2>
        <p class="muted-text">Create a record for a new patient. You can run a prediction for them afterwards.</p>

        <?php if ($errors): ?>
          <div class="alert" role="alert">
            <?php foreach ($errors as $error): ?><div><?= h($error) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

        <div class="fields">
          <div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" type="text" maxlength="50" value="<?= h($old['first_name']) ?>" required></div>
          <div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" type="text" maxlength="50" value="<?= h($old['last_name']) ?>" required></div>
          <div class="field"><label for="date_of_birth">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= h($old['date_of_birth']) ?>" required></div>
          <div class="field">
            <label for="sex">Sex</label>
            <select id="sex" name="sex" required>
              <option value="">Select</option>
              <?php foreach ($sexOptions as $opt): ?><option<?= $old['sex'] === $opt ? ' selected' : '' ?>><?= h($opt) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="race">Race</label>
            <select id="race" name="race" required>
              <option value="">Select</option>
              <?php foreach ($raceOptions as $opt): ?><option<?= $old['race'] === $opt ? ' selected' : '' ?>><?= h($opt) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label for="country">Country (optional)</label><input id="country" name="country" type="text" maxlength="60" value="<?= h($old['country']) ?>"></div>
        </div>

        <div class="form-actions">
          <button class="button" type="submit">Save patient</button>
          <a class="button ghost" href="patients.php">Cancel</a>
        </div>
      </form>

<?php include __DIR__ . '/includes/footer.php'; ?>
