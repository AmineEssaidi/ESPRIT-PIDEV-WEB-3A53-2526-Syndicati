-- Migration to change n_blocs from ENUM to SET for multiple selection
-- Execute this SQL in phpMyAdmin or MySQL client

USE horizon;

ALTER TABLE residence 
MODIFY COLUMN n_blocs SET('A', 'B', 'C', 'D', 'E');

-- Verify the change
DESCRIBE residence;
