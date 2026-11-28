-- Remove the seeded admin and patient login accounts.
-- Review the reference counts before running DELETE. If either account is
-- referenced by Samples.Uploaded_By or Prediction_Feedback.User_ID, preserve
-- the account or reassign those records before proceeding.
USE decancer_portal;

SELECT u.User_ID, u.Username,
       (SELECT COUNT(*) FROM Samples s WHERE s.Uploaded_By = u.User_ID) AS uploaded_samples,
       (SELECT COUNT(*) FROM Prediction_Feedback f WHERE f.User_ID = u.User_ID) AS submitted_feedback
FROM Users u
WHERE u.Username IN ('admin', 'patient1');

-- This should return no rows before migration 07 changes the Role enum.
SELECT User_ID, Username
FROM Users
WHERE Role = 'patient'
  AND Username <> 'patient1';

DELETE FROM Users
WHERE Username IN ('admin', 'patient1');
