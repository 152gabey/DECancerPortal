# DECancer Portal: website (frontend) review guide

This folder holds the website for the DECancer portal. It is written in PHP, HTML and CSS and reads from Anna's MySQL database (`decancer_portal`). This guide explains how to run it, how to sign in, and what to look at on each page.

## 1. Run it on your computer

You need PHP 8 and MySQL installed (PHP's MySQL driver, `pdo_mysql`, comes with the usual installs).

1. **Get the branch**
   ```sh
   git fetch
   git checkout website
   ```
2. **Set up the database** from the `database` branch, if you haven't already: run `00_init_database_v1.sql`, then `01_data_import_v1.sql`.
3. **Make your private settings file**, from the top folder of the repo:
   ```sh
   cp frontend/includes/config.example.php frontend/includes/config.php
   ```
   Open `frontend/includes/config.php` and put your own MySQL password in. This file is ignored by git, so your password is never uploaded.
4. **Set test passwords** (the database stores only scrambled hashes, so we set our own on each local copy):
   ```sh
   php frontend/tools/set_password.php researcher1 'TestPassword123'
   php frontend/tools/set_password.php oncologist1 'TestPassword123'
   ```
   Re-running Anna's `01` import script resets these, so run the commands again afterwards.
5. **Start the website**, from the top folder of the repo:
   ```sh
   php -S localhost:8000 -t frontend
   ```
6. Open <http://localhost:8000/login.php>.

## 2. Accounts to try

| Username | Role | Password | What to expect |
|---|---|---|---|
| `researcher1` | Researcher | `TestPassword123` (after step 4) | Sees everything, including **Trends** and the **Top influence** card on the dashboard |
| `oncologist1` | Oncologist | `TestPassword123` (after step 4) | No **Trends** link and no **Top influence** card |
| `admin` | Admin | set it the same way if you want to use it | Same view as a researcher |
| `patient1` | Patient | (any) | **Refused.** Patients can't sign in, so you should see "Incorrect username or password" |

These are throwaway passwords for local test copies of the database. Don't reuse a real password anywhere in this project.

## 3. What to look at, page by page

| Page | What it does | Real data or placeholder |
|---|---|---|
| **Login** (`login.php`) | Checks the username and password against the `Users` table. Wrong details, switched-off accounts and patients all get the same error. | Real |
| **Dashboard** (`index.php`) | Four count tiles, a bar chart of one protein's average level per cancer type (pick a protein and press *Show*), recent predictions, prediction results, recent patients, and (researchers only) top influence. | Real. Prediction tables will say "No predictions have been run yet" until predictions exist. |
| **Patients** (`patients.php`) | Search by name or ID, filter by cancer type, stage and sex, 15 per page. | Real |
| **Patient detail** (`patient.php?id=...`) | Profile, latest diagnosis, latest prediction, all samples (click a sample code to switch), and all 39 biomarkers compared with the cancer-free average. | Real |
| **Add a patient** (`add_patient.php`) | Creates a new patient record, then opens their page. Try leaving fields empty to see the checks. | Real. It writes to your local database. |
| **Predict** (`predict.php`) | The 39 biomarker inputs, patient details, a file upload, a results panel and feedback buttons. | **Design only.** The buttons don't do anything yet and the results panel shows example numbers. |
| **Biomarkers** | Placeholder card. | Placeholder |
| **Trends** (researchers only) | Placeholder card. Oncologists get "You do not have access to this page." | Placeholder |
| **Model performance** | Placeholder card, waiting for evaluation charts. | Placeholder |
| **About** (`about.php`) | What a liquid biopsy is, what the model uses, how to use the portal and how to read a result. | Text |

## 4. Quick checks worth doing

- Open `http://localhost:8000/patients.php` while signed out. It should send you to the login page.
- Sign in as `oncologist1` and type `http://localhost:8000/trends.php` into the address bar. It should be blocked.
- Add a patient, then find them in the Patients list.
- Shrink the browser window. The sidebar should turn into a Menu button.


## 6. Known limitations

- Predict is not connected to the model yet.
- The Biomarkers, Trends and Model performance pages are placeholders.
- There is no JavaScript or AJAX yet. The dashboard dropdown reloads the page.
- There's no way to add a diagnosis or a sample from the website yet.
