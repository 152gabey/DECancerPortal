<?php
session_start();
require __DIR__ . '/includes/db.php';

// Roles that are allowed to sign in. Patients are not on this list.
$allowedRoles = ['oncologist', 'researcher', 'admin'];

$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // 1. Look the username up in the Users table
    $db   = getDb();
    $stmt = $db->prepare('SELECT User_ID, Username, FirstName, LastName, Role, Password_Hash FROM Users WHERE Username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // 2. Check: user exists, role is allowed, and password matches
    if ($user
        && in_array($user['Role'], $allowedRoles, true)
        && password_verify($password, $user['Password_Hash'])) {

        // 3. Remember who is signed in
        session_regenerate_id(true);
        $_SESSION['user_id']  = $user['User_ID'];
        $_SESSION['username'] = $user['Username'];
        $_SESSION['name']     = $user['FirstName'] . ' ' . $user['LastName'];
        $_SESSION['role']     = $user['Role'];

        header('Location: whoami.php');
        exit;
    }

    // Wrong username, wrong password, disallowed role, and patients all get the same message
    $error = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | DECancer Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">

    <section class="login-art">
      <a class="brand" href="login.php"><span class="brand-mark"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3c0 5 10 5 10 9s-10 4-10 9"/><path d="M17 3c0 5-10 5-10 9s10 4 10 9"/><path d="M8.5 6.5h7M8.5 17.5h7"/></svg></span>DECancer</a>
      <svg class="helix" viewBox="0 0 300 520" role="img" aria-label="Illustration of a DNA double helix"><g transform="rotate(-16 150 260)"><g class="rungs"><line x1="150.0" y1="30.0" x2="150.0" y2="30.0"/><line x1="200.2" y1="53.0" x2="99.8" y2="53.0"/><line x1="209.0" y1="76.0" x2="91.0" y2="76.0"/><line x1="169.2" y1="99.0" x2="130.8" y2="99.0"/><line x1="113.6" y1="122.0" x2="186.4" y2="122.0"/><line x1="88.0" y1="145.0" x2="212.0" y2="145.0"/><line x1="113.6" y1="168.0" x2="186.4" y2="168.0"/><line x1="169.2" y1="191.0" x2="130.8" y2="191.0"/><line x1="209.0" y1="214.0" x2="91.0" y2="214.0"/><line x1="200.2" y1="237.0" x2="99.8" y2="237.0"/><line x1="150.0" y1="260.0" x2="150.0" y2="260.0"/><line x1="99.8" y1="283.0" x2="200.2" y2="283.0"/><line x1="91.0" y1="306.0" x2="209.0" y2="306.0"/><line x1="130.8" y1="329.0" x2="169.2" y2="329.0"/><line x1="186.4" y1="352.0" x2="113.6" y2="352.0"/><line x1="212.0" y1="375.0" x2="88.0" y2="375.0"/><line x1="186.4" y1="398.0" x2="113.6" y2="398.0"/><line x1="130.8" y1="421.0" x2="169.2" y2="421.0"/><line x1="91.0" y1="444.0" x2="209.0" y2="444.0"/><line x1="99.8" y1="467.0" x2="200.2" y2="467.0"/><line x1="150.0" y1="490.0" x2="150.0" y2="490.0"/></g><path class="strand-b" d="M150.0 30.0 L140.3 33.8 L130.8 37.7 L121.9 41.5 L113.6 45.3 L106.2 49.2 L99.8 53.0 L94.8 56.8 L91.0 60.7 L88.8 64.5 L88.0 68.3 L88.8 72.2 L91.0 76.0 L94.8 79.8 L99.8 83.7 L106.2 87.5 L113.6 91.3 L121.9 95.2 L130.8 99.0 L140.3 102.8 L150.0 106.7 L159.7 110.5 L169.2 114.3 L178.1 118.2 L186.4 122.0 L193.8 125.8 L200.2 129.7 L205.2 133.5 L209.0 137.3 L211.2 141.2 L212.0 145.0 L211.2 148.8 L209.0 152.7 L205.2 156.5 L200.2 160.3 L193.8 164.2 L186.4 168.0 L178.1 171.8 L169.2 175.7 L159.7 179.5 L150.0 183.3 L140.3 187.2 L130.8 191.0 L121.9 194.8 L113.6 198.7 L106.2 202.5 L99.8 206.3 L94.8 210.2 L91.0 214.0 L88.8 217.8 L88.0 221.7 L88.8 225.5 L91.0 229.3 L94.8 233.2 L99.8 237.0 L106.2 240.8 L113.6 244.7 L121.9 248.5 L130.8 252.3 L140.3 256.2 L150.0 260.0 L159.7 263.8 L169.2 267.7 L178.1 271.5 L186.4 275.3 L193.8 279.2 L200.2 283.0 L205.2 286.8 L209.0 290.7 L211.2 294.5 L212.0 298.3 L211.2 302.2 L209.0 306.0 L205.2 309.8 L200.2 313.7 L193.8 317.5 L186.4 321.3 L178.1 325.2 L169.2 329.0 L159.7 332.8 L150.0 336.7 L140.3 340.5 L130.8 344.3 L121.9 348.2 L113.6 352.0 L106.2 355.8 L99.8 359.7 L94.8 363.5 L91.0 367.3 L88.8 371.2 L88.0 375.0 L88.8 378.8 L91.0 382.7 L94.8 386.5 L99.8 390.3 L106.2 394.2 L113.6 398.0 L121.9 401.8 L130.8 405.7 L140.3 409.5 L150.0 413.3 L159.7 417.2 L169.2 421.0 L178.1 424.8 L186.4 428.7 L193.8 432.5 L200.2 436.3 L205.2 440.2 L209.0 444.0 L211.2 447.8 L212.0 451.7 L211.2 455.5 L209.0 459.3 L205.2 463.2 L200.2 467.0 L193.8 470.8 L186.4 474.7 L178.1 478.5 L169.2 482.3 L159.7 486.2 L150.0 490.0 "/><path class="strand-a" d="M150.0 30.0 L159.7 33.8 L169.2 37.7 L178.1 41.5 L186.4 45.3 L193.8 49.2 L200.2 53.0 L205.2 56.8 L209.0 60.7 L211.2 64.5 L212.0 68.3 L211.2 72.2 L209.0 76.0 L205.2 79.8 L200.2 83.7 L193.8 87.5 L186.4 91.3 L178.1 95.2 L169.2 99.0 L159.7 102.8 L150.0 106.7 L140.3 110.5 L130.8 114.3 L121.9 118.2 L113.6 122.0 L106.2 125.8 L99.8 129.7 L94.8 133.5 L91.0 137.3 L88.8 141.2 L88.0 145.0 L88.8 148.8 L91.0 152.7 L94.8 156.5 L99.8 160.3 L106.2 164.2 L113.6 168.0 L121.9 171.8 L130.8 175.7 L140.3 179.5 L150.0 183.3 L159.7 187.2 L169.2 191.0 L178.1 194.8 L186.4 198.7 L193.8 202.5 L200.2 206.3 L205.2 210.2 L209.0 214.0 L211.2 217.8 L212.0 221.7 L211.2 225.5 L209.0 229.3 L205.2 233.2 L200.2 237.0 L193.8 240.8 L186.4 244.7 L178.1 248.5 L169.2 252.3 L159.7 256.2 L150.0 260.0 L140.3 263.8 L130.8 267.7 L121.9 271.5 L113.6 275.3 L106.2 279.2 L99.8 283.0 L94.8 286.8 L91.0 290.7 L88.8 294.5 L88.0 298.3 L88.8 302.2 L91.0 306.0 L94.8 309.8 L99.8 313.7 L106.2 317.5 L113.6 321.3 L121.9 325.2 L130.8 329.0 L140.3 332.8 L150.0 336.7 L159.7 340.5 L169.2 344.3 L178.1 348.2 L186.4 352.0 L193.8 355.8 L200.2 359.7 L205.2 363.5 L209.0 367.3 L211.2 371.2 L212.0 375.0 L211.2 378.8 L209.0 382.7 L205.2 386.5 L200.2 390.3 L193.8 394.2 L186.4 398.0 L178.1 401.8 L169.2 405.7 L159.7 409.5 L150.0 413.3 L140.3 417.2 L130.8 421.0 L121.9 424.8 L113.6 428.7 L106.2 432.5 L99.8 436.3 L94.8 440.2 L91.0 444.0 L88.8 447.8 L88.0 451.7 L88.8 455.5 L91.0 459.3 L94.8 463.2 L99.8 467.0 L106.2 470.8 L113.6 474.7 L121.9 478.5 L130.8 482.3 L140.3 486.2 L150.0 490.0 "/><circle class="node-b" cx="150.0" cy="30.0" r="4.0"/><circle class="node-a" cx="150.0" cy="30.0" r="10.0"/><circle class="node-b" cx="99.8" cy="53.0" r="5.2"/><circle class="node-a" cx="200.2" cy="53.0" r="8.8"/><circle class="node-b" cx="91.0" cy="76.0" r="7.9"/><circle class="node-a" cx="209.0" cy="76.0" r="6.1"/><circle class="node-b" cx="130.8" cy="99.0" r="9.9"/><circle class="node-a" cx="169.2" cy="99.0" r="4.1"/><circle class="node-b" cx="186.4" cy="122.0" r="9.4"/><circle class="node-a" cx="113.6" cy="122.0" r="4.6"/><circle class="node-b" cx="212.0" cy="145.0" r="7.0"/><circle class="node-a" cx="88.0" cy="145.0" r="7.0"/><circle class="node-b" cx="186.4" cy="168.0" r="4.6"/><circle class="node-a" cx="113.6" cy="168.0" r="9.4"/><circle class="node-b" cx="130.8" cy="191.0" r="4.1"/><circle class="node-a" cx="169.2" cy="191.0" r="9.9"/><circle class="node-b" cx="91.0" cy="214.0" r="6.1"/><circle class="node-a" cx="209.0" cy="214.0" r="7.9"/><circle class="node-b" cx="99.8" cy="237.0" r="8.8"/><circle class="node-a" cx="200.2" cy="237.0" r="5.2"/><circle class="node-b" cx="150.0" cy="260.0" r="10.0"/><circle class="node-a" cx="150.0" cy="260.0" r="4.0"/><circle class="node-b" cx="200.2" cy="283.0" r="8.8"/><circle class="node-a" cx="99.8" cy="283.0" r="5.2"/><circle class="node-b" cx="209.0" cy="306.0" r="6.1"/><circle class="node-a" cx="91.0" cy="306.0" r="7.9"/><circle class="node-b" cx="169.2" cy="329.0" r="4.1"/><circle class="node-a" cx="130.8" cy="329.0" r="9.9"/><circle class="node-b" cx="113.6" cy="352.0" r="4.6"/><circle class="node-a" cx="186.4" cy="352.0" r="9.4"/><circle class="node-b" cx="88.0" cy="375.0" r="7.0"/><circle class="node-a" cx="212.0" cy="375.0" r="7.0"/><circle class="node-b" cx="113.6" cy="398.0" r="9.4"/><circle class="node-a" cx="186.4" cy="398.0" r="4.6"/><circle class="node-b" cx="169.2" cy="421.0" r="9.9"/><circle class="node-a" cx="130.8" cy="421.0" r="4.1"/><circle class="node-b" cx="209.0" cy="444.0" r="7.9"/><circle class="node-a" cx="91.0" cy="444.0" r="6.1"/><circle class="node-b" cx="200.2" cy="467.0" r="5.2"/><circle class="node-a" cx="99.8" cy="467.0" r="8.8"/><circle class="node-b" cx="150.0" cy="490.0" r="4.0"/><circle class="node-a" cx="150.0" cy="490.0" r="10.0"/></g></svg>
      <div class="login-art-text">
        <h2>Blood-test insights for clinicians and researchers</h2>
        <p>Explore biomarker data and run cancer predictions.</p>
      </div>
    </section>

    <main class="login-form">
      <h1>Sign in</h1>
      <p class="muted-text">Access is limited to oncologists and researchers.</p>

      <p class="alert" role="alert"<?= $error ? '' : ' hidden' ?>>Incorrect username or password.</p>

      <form action="login.php" method="post">
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="button wide" type="submit">Sign in</button>
      </form>

      <p class="muted-text small">Forgotten your password? Ask your administrator to reset it.</p>
    </main>

  </div>
</div>
</body>
</html>
