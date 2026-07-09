-- Site contact for plantation registration (landowner)
ALTER TABLE `plantations` ADD COLUMN `contact_person_name` varchar(150) DEFAULT NULL AFTER `mohon_points_json`;
ALTER TABLE `plantations` ADD COLUMN `contact_address` text DEFAULT NULL AFTER `contact_person_name`;
ALTER TABLE `plantations` ADD COLUMN `contact_phone` varchar(80) DEFAULT NULL AFTER `contact_address`;
