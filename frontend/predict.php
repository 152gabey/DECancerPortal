<?php
$pageTitle = 'Predict';
$activePage = 'predict';
require __DIR__ . '/includes/auth.php';
include __DIR__ . '/includes/header.php';
?>
<div class="predict">
  <form class="card form" action="#" method="post">
    <fieldset class="group-box">
      <legend>Patient and sample</legend>
      <div class="fields">
        <div class="field"><label for="patient_id">Patient ID</label><input id="patient_id" name="patient_id" type="text" placeholder="P-1024"></div>
        <div class="field"><label for="sample_id">Sample ID</label><input id="sample_id" name="sample_id" type="text" placeholder="S-0412"></div>
        <div class="field"><label for="age">Age</label><input id="age" name="age" type="number" min="0" max="120"></div>
        <div class="field"><label for="sex">Sex</label><select id="sex" name="sex"><option value="">Select</option><option>Female</option><option>Male</option></select></div>
        <div class="field"><label for="race">Race</label><select id="race" name="race"><option value="">Select</option><option>Asian</option><option>Black</option><option>Black/Hispanic</option><option>Caucasian</option><option>Caucasian/Hispanic</option><option>Hispanic</option><option>Other</option><option>Unknown</option></select></div>
        <div class="field"><label for="omega">&Omega; score</label><input id="omega" name="omega" type="number" step="any"></div>
      </div>
    </fieldset>

    <div class="upload">
      <div><strong>Have the values in a file?</strong><small>Upload a CSV with one column per biomarker.</small></div>
      <input type="file" name="biomarker_file" accept=".csv" aria-label="Upload biomarker CSV">
    </div>

    <h2 class="form-title">Blood protein biomarkers</h2>
        <details class="group" open>
          <summary>Cancer antigens<small>11 proteins</small></summary>
          <div class="fields"><div class="field"><label for="afp">AFP</label><div class="input-unit"><input id="afp" name="afp" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="ca_125">CA-125</label><div class="input-unit"><input id="ca_125" name="ca_125" type="number" step="any" min="0" inputmode="decimal"><span>U/ml</span></div></div><div class="field"><label for="ca_15_3">CA 15-3</label><div class="input-unit"><input id="ca_15_3" name="ca_15_3" type="number" step="any" min="0" inputmode="decimal"><span>U/ml</span></div></div><div class="field"><label for="ca19_9">CA19-9</label><div class="input-unit"><input id="ca19_9" name="ca19_9" type="number" step="any" min="0" inputmode="decimal"><span>U/ml</span></div></div><div class="field"><label for="cea">CEA</label><div class="input-unit"><input id="cea" name="cea" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="cyfra_21_1">CYFRA 21-1</label><div class="input-unit"><input id="cyfra_21_1" name="cyfra_21_1" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="he4">HE4</label><div class="input-unit"><input id="he4" name="he4" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="mesothelin">Mesothelin</label><div class="input-unit"><input id="mesothelin" name="mesothelin" type="number" step="any" min="0" inputmode="decimal"><span>ng/ml</span></div></div><div class="field"><label for="nse">NSE</label><div class="input-unit"><input id="nse" name="nse" type="number" step="any" min="0" inputmode="decimal"><span>ng/ml</span></div></div><div class="field"><label for="kallikrein_6">Kallikrein-6</label><div class="input-unit"><input id="kallikrein_6" name="kallikrein_6" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="prolactin">Prolactin</label><div class="input-unit"><input id="prolactin" name="prolactin" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div></div>
        </details>
        <details class="group" open>
          <summary>Cytokines and growth factors<small>12 proteins</small></summary>
          <div class="fields"><div class="field"><label for="angiopoietin_2">Angiopoietin-2</label><div class="input-unit"><input id="angiopoietin_2" name="angiopoietin_2" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="fgf2">FGF2</label><div class="input-unit"><input id="fgf2" name="fgf2" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="g_csf">G-CSF</label><div class="input-unit"><input id="g_csf" name="g_csf" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="hgf">HGF</label><div class="input-unit"><input id="hgf" name="hgf" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="il_6">IL-6</label><div class="input-unit"><input id="il_6" name="il_6" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="il_8">IL-8</label><div class="input-unit"><input id="il_8" name="il_8" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="midkine">Midkine</label><div class="input-unit"><input id="midkine" name="midkine" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="tgfa">TGFa</label><div class="input-unit"><input id="tgfa" name="tgfa" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="leptin">Leptin</label><div class="input-unit"><input id="leptin" name="leptin" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="gdf15">GDF15</label><div class="input-unit"><input id="gdf15" name="gdf15" type="number" step="any" min="0" inputmode="decimal"><span>ng/ml</span></div></div><div class="field"><label for="follistatin">Follistatin</label><div class="input-unit"><input id="follistatin" name="follistatin" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="dkk1">DKK1</label><div class="input-unit"><input id="dkk1" name="dkk1" type="number" step="any" min="0" inputmode="decimal"><span>ng/ml</span></div></div></div>
        </details>
        <details class="group" open>
          <summary>Receptors, adhesion and matrix<small>13 proteins</small></summary>
          <div class="fields"><div class="field"><label for="axl">AXL</label><div class="input-unit"><input id="axl" name="axl" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="cd44">CD44</label><div class="input-unit"><input id="cd44" name="cd44" type="number" step="any" min="0" inputmode="decimal"><span>ng/ml</span></div></div><div class="field"><label for="endoglin">Endoglin</label><div class="input-unit"><input id="endoglin" name="endoglin" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="segfr">sEGFR</label><div class="input-unit"><input id="segfr" name="segfr" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="sfas">sFas</label><div class="input-unit"><input id="sfas" name="sfas" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="sher2_segfr2_serbb2">sHER2/sEGFR2/sErbB2</label><div class="input-unit"><input id="sher2_segfr2_serbb2" name="sher2_segfr2_serbb2" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="specam_1">sPECAM-1</label><div class="input-unit"><input id="specam_1" name="specam_1" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="galectin_3">Galectin-3</label><div class="input-unit"><input id="galectin_3" name="galectin_3" type="number" step="any" min="0" inputmode="decimal"><span>ng/ml</span></div></div><div class="field"><label for="opg">OPG</label><div class="input-unit"><input id="opg" name="opg" type="number" step="any" min="0" inputmode="decimal"><span>ng/ml</span></div></div><div class="field"><label for="opn">OPN</label><div class="input-unit"><input id="opn" name="opn" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="thrombospondin_2">Thrombospondin-2</label><div class="input-unit"><input id="thrombospondin_2" name="thrombospondin_2" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="timp_1">TIMP-1</label><div class="input-unit"><input id="timp_1" name="timp_1" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="timp_2">TIMP-2</label><div class="input-unit"><input id="timp_2" name="timp_2" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div></div>
        </details>
        <details class="group" open>
          <summary>Other proteins<small>3 proteins</small></summary>
          <div class="fields"><div class="field"><label for="myeloperoxidase">Myeloperoxidase</label><div class="input-unit"><input id="myeloperoxidase" name="myeloperoxidase" type="number" step="any" min="0" inputmode="decimal"><span>ng/ml</span></div></div><div class="field"><label for="par">PAR</label><div class="input-unit"><input id="par" name="par" type="number" step="any" min="0" inputmode="decimal"><span>pg/ml</span></div></div><div class="field"><label for="shbg">SHBG</label><div class="input-unit"><input id="shbg" name="shbg" type="number" step="any" min="0" inputmode="decimal"><span>nM</span></div></div></div>
        </details>

    <div class="form-actions">
      <button class="button" type="submit">Run prediction</button>
      <button class="button ghost" type="reset">Clear form</button>
    </div>
  </form>

  <aside class="card results">
    <div class="card-head"><h2>Results</h2><span class="badge">Example output</span></div>
    <p class="muted-text">This is what you will see after running a prediction. The numbers below are placeholders.</p>

    <div class="result-top">
      <div><small>Chance of cancer</small><strong class="big">0.88</strong><span class="pill high">Cancer</span></div>
      <div><small>Highest protein influence</small><strong>CA-125</strong></div>
    </div>

    <h3>Likelihood by organ</h3>
        <div class="bar-row wide">
          <span class="bar-label">Breast</span>
          <div class="bar-track"><div class="bar-fill muted" style="width: 4%"></div></div>
          <span class="bar-value">0.04</span>
        </div>
        <div class="bar-row wide">
          <span class="bar-label">Colorectum</span>
          <div class="bar-track"><div class="bar-fill muted" style="width: 6%"></div></div>
          <span class="bar-value">0.06</span>
        </div>
        <div class="bar-row wide">
          <span class="bar-label">Esophagus</span>
          <div class="bar-track"><div class="bar-fill muted" style="width: 3%"></div></div>
          <span class="bar-value">0.03</span>
        </div>
        <div class="bar-row wide">
          <span class="bar-label">Liver</span>
          <div class="bar-track"><div class="bar-fill muted" style="width: 2%"></div></div>
          <span class="bar-value">0.02</span>
        </div>
        <div class="bar-row wide">
          <span class="bar-label">Lung</span>
          <div class="bar-track"><div class="bar-fill muted" style="width: 5%"></div></div>
          <span class="bar-value">0.05</span>
        </div>
        <div class="bar-row wide top">
          <span class="bar-label">Ovary</span>
          <div class="bar-track"><div class="bar-fill" style="width: 82%"></div></div>
          <span class="bar-value">0.82</span>
        </div>
        <div class="bar-row wide">
          <span class="bar-label">Pancreas</span>
          <div class="bar-track"><div class="bar-fill muted" style="width: 7%"></div></div>
          <span class="bar-value">0.07</span>
        </div>
        <div class="bar-row wide">
          <span class="bar-label">Stomach</span>
          <div class="bar-track"><div class="bar-fill muted" style="width: 3%"></div></div>
          <span class="bar-value">0.03</span>
        </div>
    <p class="note">Missing-cancer score: 0.03</p>

    <h3>Most influential biomarkers</h3>
        <div class="bar-row wide">
          <span class="bar-label">CA-125</span>
          <div class="bar-track"><div class="bar-fill ink" style="width: 100%"></div></div>
          <span class="bar-value">0.31</span>
        </div>
        <div class="bar-row wide">
          <span class="bar-label">HE4</span>
          <div class="bar-track"><div class="bar-fill ink" style="width: 71%"></div></div>
          <span class="bar-value">0.22</span>
        </div>
        <div class="bar-row wide">
          <span class="bar-label">CEA</span>
          <div class="bar-track"><div class="bar-fill ink" style="width: 29%"></div></div>
          <span class="bar-value">0.09</span>
        </div>
        <div class="bar-row wide">
          <span class="bar-label">IL-6</span>
          <div class="bar-track"><div class="bar-fill ink" style="width: 23%"></div></div>
          <span class="bar-value">0.07</span>
        </div>
        <div class="bar-row wide">
          <span class="bar-label">Mesothelin</span>
          <div class="bar-track"><div class="bar-fill ink" style="width: 16%"></div></div>
          <span class="bar-value">0.05</span>
        </div>

    <p class="meta">Run by Dr. Placeholder &middot; 26 Nov 2026, 09:14 &middot; DeCancer v1.0</p>

    <form class="feedback" action="#" method="post">
      <h3>Was this prediction right?</h3>
      <div class="choice" role="radiogroup" aria-label="Feedback on this prediction">
        <label><input type="radio" name="feedback_type" value="correct"><span>Correct</span></label>
        <label><input type="radio" name="feedback_type" value="incorrect"><span>Incorrect</span></label>
        <label><input type="radio" name="feedback_type" value="unsure"><span>Unsure</span></label>
      </div>
      <label for="suggested">If incorrect, what do you think it is?</label>
      <select id="suggested" name="suggested_cancer"><option value="">Select (optional)</option><option>Breast</option><option>Colorectum</option><option>Esophagus</option><option>Liver</option><option>Lung</option><option>Ovary</option><option>Pancreas</option><option>Stomach</option><option>Normal (cancer-free)</option></select>
      <label for="comment">Comment (optional)</label>
      <textarea id="comment" name="comment" rows="3"></textarea>
      <button class="button" type="submit">Send feedback</button>
    </form>
  </aside>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
