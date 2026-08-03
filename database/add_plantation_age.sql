-- Age of plantation (years) for registration forms.
ALTER TABLE `plantations` ADD COLUMN `age_of_plantation` decimal(5,1) DEFAULT NULL AFTER `land_area`;
