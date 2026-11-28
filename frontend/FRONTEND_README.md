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
3. **Configure database access** in the repository-root `.env` file. If you have not created it yet:
   ```sh
   cp .env.example .env
   ```
   Set `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER` and `MYSQL_PASSWORD` there. The PHP frontend and Python model/backend use these same settings, so configure them once.
4. **Test accounts are ready** after importing `01_data_import_v1.sql`. Passwords are only test credentials:

   | Username | Role | Password |
   |---|---|---|
   | `researcher1` | Researcher | `TestPassword123` |
   | `oncologist1` | Oncologist | `TestPassword123` |

   Do not use these credentials or expose this database on a public network in production.
5. **Start the website**, from the top folder of the repo:
   ```sh
   php -S localhost:8000 -t frontend
   ```
6. Open <http://localhost:8000/login.php>.

## 2. Accounts to try

| Username | Role | Password | What to expect |
|---|---|---|---|
| `researcher1` | Researcher | `TestPassword123` | Sees everything, including **Trends** and the **Top influence** card on the dashboard |
| `oncologist1` | Oncologist | `TestPassword123` | No **Trends** link and no **Top influence** card |

These are throwaway passwords for local test copies of the database. Don't reuse a real password anywhere in this project.

## 3. What to look at, page by page

| Page | What it does | Real data or placeholder |
|---|---|---|
| **Login** (`login.php`) | Checks the username and password against the `Users` table. Wrong details and disallowed roles get the same error. | Real |
| **Dashboard** (`index.php`) | Four count tiles, a bar chart of one protein's average level per cancer type (pick a protein and press *Show*), recent predictions, prediction results, recent patients, and (researchers only) top influence. | Real. Prediction tables will say "No predictions have been run yet" until predictions exist. |
| **Patients** (`patients.php`) | Search by name or ID, filter by cancer type, stage and sex, 15 per page. | Real |
| **Patient detail** (`patient.php?id=...`) | Profile, latest diagnosis, latest prediction, all samples (click a sample ID to switch), and all 39 biomarkers compared with the cancer-free average. | Real |
| **Add a patient** (`add_patient.php`) | Creates a new patient record, then opens their page. Try leaving fields empty to see the checks. | Real. It writes to your local database. |
| **Predict** (`predict.php`) | Enter or import one sample's 39 biomarker measurements for an existing patient and save it, optionally providing an external sample code. The generated `Sample_ID` is used to run the model. Results and prediction feedback appear beside the saved-sample form. | Real. Requires the FastAPI service and configured model files. |
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

- CSV import requires exactly one sample row and one column per biomarker, using the downloadable template's headers. Each header includes the protein's unit; enter numeric values in those units. The same measurements can instead be entered in the form.
- The Biomarkers, Trends and Model performance pages are placeholders.
- There is no JavaScript or AJAX yet. The dashboard dropdown reloads the page.
- There is no way to add a diagnosis from the website yet.
