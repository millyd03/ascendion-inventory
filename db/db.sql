CREATE TABLE `inventory`.`items` (
    `Item` VARCHAR(255) NOT NULL,
    `Id` INT NOT NULL AUTO_INCREMENT,
    `item_type` INT NOT NULL,
    PRIMARY KEY (`Id`),
    UNIQUE `item_name` (`Item`)
) ENGINE = InnoDB;

INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Pen', 1, 1);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Printer', 2, 2);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Marker', 3, 1);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Scanner', 4, 2);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Clear Tape', 5, 1);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Standing Table', 6, 2);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Shredder', 7, 2);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Thumbtack', 8, 1);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Paper Clip', 10, 1);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('A4 Sheet', 11, 1);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Notebook', 12, 1);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Chair',	13, 3);
INSERT INTO `items`(`Item`, `Id`, `item_type`) VALUES ('Stool',	14, 3);

CREATE TABLE `inventory`.`requests` (
    `req_id` INT NOT NULL AUTO_INCREMENT,
    `requested_by` VARCHAR(255) NOT NULL,
    `requested_on` DATE NOT NULL,
    `items` TEXT NOT NULL,
    PRIMARY KEY (`req_id`)
) ENGINE = InnoDB; 

CREATE TABLE `inventory`.`summary` (
    `req_id` INT NOT NULL AUTO_INCREMENT,
    `requested_by` VARCHAR(255) NOT NULL,
    `items` TEXT NOT NULL,
    PRIMARY KEY (`req_id`)
    UNIQUE `user_name` (`requested_by`)
) ENGINE = InnoDB; 