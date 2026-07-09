-- Create tree species table
CREATE TABLE IF NOT EXISTS `tree_species` (
  `species_id` int(11) NOT NULL AUTO_INCREMENT,
  `species_name` varchar(100) NOT NULL,
  `scientific_name` varchar(150) DEFAULT NULL,
  `common_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`species_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert common Philippine tree species
INSERT INTO `tree_species` (`species_name`, `scientific_name`, `common_name`) VALUES
('Narra', 'Pterocarpus indicus', 'Philippine Mahogany'),
('Molave', 'Vitex parviflora', 'Molave'),
('Acacia', 'Acacia mangium', 'Acacia'),
('Mahogany', 'Swietenia macrophylla', 'Mahogany'),
('Ipil-ipil', 'Leucaena leucocephala', 'Ipil-ipil'),
('Gmelina', 'Gmelina arborea', 'Gmelina'),
('Bamboo', 'Bambusa vulgaris', 'Common Bamboo'),
('Yakal', 'Shorea astylosa', 'Yakal'),
('Kamagong', 'Diospyros blancoi', 'Velvet Apple'),
('Apitong', 'Dipterocarpus grandiflorus', 'Apitong'),
('Lauan', 'Shorea contorta', 'White Lauan'),
('Teak', 'Tectona grandis', 'Teak'),
('Mangium', 'Acacia mangium', 'Black Wattle'),
('Rubber Tree', 'Hevea brasiliensis', 'Rubber Tree'),
('Falcata', 'Paraserianthes falcataria', 'Falcata'),
('Agoho', 'Casuarina equisetifolia', 'Beach She-oak'),
('Mango', 'Mangifera indica', 'Mango Tree'),
('Coconut', 'Cocos nucifera', 'Coconut Palm'),
('Durian', 'Durio zibethinus', 'Durian'),
('Rambutan', 'Nephelium lappaceum', 'Rambutan');

-- Add verification_document column to plantations table if it doesn't exist
ALTER TABLE `plantations` 
ADD COLUMN IF NOT EXISTS `verification_document` varchar(255) DEFAULT NULL AFTER `longitude`;
