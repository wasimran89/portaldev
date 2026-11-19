ALTER TABLE qr_activity_log 
ADD COLUMN timestamp INT(10) UNSIGNED NOT NULL
AFTER uid;


