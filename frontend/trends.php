<?php
$pageTitle = 'Trends';
$activePage = 'trends';
require __DIR__ . '/includes/auth.php';
requireRole('researcher', 'admin');
include __DIR__ . '/includes/header.php';
?>
      <section class="card empty">
        <h2>Trends</h2>
        <p>Researcher view: how biomarker levels and predictions change over time and between cancer types.</p>
      </section>
<?php include __DIR__ . '/includes/footer.php'; ?>
