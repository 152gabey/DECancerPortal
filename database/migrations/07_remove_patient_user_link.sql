-- Remove patient logins from the user schema after migration 06 has removed
-- the seeded patient account and there are no other Role='patient' rows.
-- Patient clinical records remain in Patients and retain their other links.
USE decancer_portal;

ALTER TABLE Users
  DROP FOREIGN KEY fk_users_patient,
  DROP INDEX uq_users_patient,
  DROP COLUMN Patient_ID,
  MODIFY COLUMN Role ENUM('oncologist','researcher','admin') NOT NULL;
