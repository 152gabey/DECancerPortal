-- Remove unused account activity fields from Users.
USE decancer_portal;

ALTER TABLE Users
  DROP CHECK chk_users_active,
  DROP COLUMN Is_Active,
  DROP COLUMN Last_Login;
