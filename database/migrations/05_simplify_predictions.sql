-- Remove undefined Missing_Cancer_Score and the misleading prediction User_ID.
-- Run once against a database that still has these columns. Back up first.
USE decancer_portal;

ALTER TABLE Predictions
  DROP FOREIGN KEY fk_pred_user,
  DROP COLUMN User_ID,
  DROP COLUMN Missing_Cancer_Score;
