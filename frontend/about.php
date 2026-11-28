<?php
$pageTitle = 'About';
$activePage = 'about';
require __DIR__ . '/includes/auth.php';
include __DIR__ . '/includes/header.php';
?>
      <section class="card prose intro-card">
        <span class="badge">About this portal</span>
        <h2>A blood test that looks for signs of cancer</h2>
        <p>DECancer is a web portal for oncologists and researchers. It stores blood-test results for patients, shows how protein levels differ between cancer types, and runs a machine learning model that estimates how likely it is that a blood sample comes from a person with cancer, and which organ it points to.</p>
        <p class="notice"><strong>Demonstration project.</strong> This portal was built for a university course using de-identified research data. Patient names and countries are made up. It must not be used to make real medical decisions.</p>
      </section>

      <div class="about-grid">
        <section class="card prose">
          <h2>What is a liquid biopsy?</h2>
          <p>A traditional biopsy removes a piece of tissue. A liquid biopsy needs only a blood sample, so it is fast and less invasive. The blood plasma can carry tiny traces of a tumour, such as fragments of its DNA and proteins it releases. Measuring these traces may help find cancer earlier, and may help track a diagnosis or a relapse once cancer has been found.</p>
        </section>

        <section class="card prose">
          <h2>What the model looks at</h2>
          <p>For each blood sample the model uses:</p>
          <ul>
            <li><strong>39 blood proteins</strong> (the biomarkers), such as CA-125, CEA and IL-6, each measured in units like pg/ml or ng/ml.</li>
            <li><strong>The &Omega; score</strong>, a number derived from DNA changes found in the blood sample.</li>
            <li><strong>Age, sex and race</strong> of the patient.</li>
          </ul>
          <p>It was built from the DECancer research pipeline (Halner et al., <em>iScience</em>, 2023), which used blood-test data from the CancerSEEK study covering eight cancer types.</p>
        </section>
      </div>

      <section class="card prose">
        <h2>How to use the portal</h2>
        <ol class="steps">
          <li><strong>Find or add a patient.</strong> Open <a href="patients.php">Patients</a> to search the records, or add a new patient.</li>
          <li><strong>Look at their blood results.</strong> A patient's page shows each sample and compares every protein with the average of cancer-free patients.</li>
          <li><strong>Run a prediction.</strong> On <a href="predict.php">Predict</a>, enter the protein values, age, sex, race and &Omega; score, or upload a file, then press <em>Run prediction</em>.</li>
          <li><strong>Read the result,</strong> then tell the system whether you think it was right. Your feedback is saved and can help improve the model later.</li>
          <li><strong>Explore patterns.</strong> The <a href="index.php">Dashboard</a> shows how a chosen protein differs between cancer types.</li>
        </ol>
      </section>

      <section class="card prose">
        <h2>How to read a result</h2>
        <dl class="dl">
          <div><dt>Chance of cancer</dt><dd>A number from 0 to 1 for how likely the sample is to come from a person with any cancer. Closer to 1 means more likely.</dd></div>
          <div><dt>Result</dt><dd><span class="pill high">Cancer</span> or <span class="pill low">Cancer-free</span>, decided by comparing the chance of cancer with a cut-off set by the project team.</dd></div>
          <div><dt>Likelihood by organ</dt><dd>One number for each of the eight organs: breast, colorectum, esophagus, liver, lung, ovary, pancreas and stomach. The tallest bar is the organ the model thinks is most likely.</dd></div>
          <div><dt>Highest protein influence</dt><dd>The blood protein that pushed this prediction the most.</dd></div>
          <div><dt>Compared with cancer-free</dt><dd>On a patient's page, &ldquo;Higher&rdquo; means a protein is more than 1.5&times; the cancer-free average, and &ldquo;Lower&rdquo; means less than half of it. This is a reading aid, not a diagnosis.</dd></div>
        </dl>
        <p class="notice">A prediction is an estimate, not a diagnosis. The organ-level numbers are less certain than the cancer versus cancer-free result, especially for cancer types with few samples in the training data.</p>
      </section>

      <div class="about-grid">
        <section class="card prose">
          <h2>Who can see what</h2>
          <dl class="dl">
            <div><dt>Oncologist</dt><dd>Patients, samples, predictions, biomarkers and model performance.</dd></div>
            <div><dt>Researcher</dt><dd>Everything an oncologist can see, plus Trends and the &ldquo;Top influence&rdquo; summary.</dd></div>
          </dl>
          <p>Patients do not have accounts on this portal.</p>
        </section>

        <section class="card prose">
          <h2>Data sources</h2>
          <ul>
            <li><strong>Blood proteins, &Omega; score and cancer labels:</strong> the CancerSEEK dataset used by the DECancer paper.</li>
            <li><strong>Patient records:</strong> de-identified, with made-up names and countries.</li>
            <li><strong>Model:</strong> DECancer, Halner et al., <em>iScience</em> 26, 106610 (2023).</li>
          </ul>
        </section>
      </div>

<?php include __DIR__ . '/includes/footer.php'; ?>
