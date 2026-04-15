DROP DATABASE IF EXISTS `TeamBeijingDBTest`;
CREATE DATABASE `TeamBeijingDBTest`;
USE `TeamBeijingDBTest`;

DROP TABLE IF EXISTS `Users`;
CREATE TABLE `Users` (
    `Email` VARCHAR(255) NOT NULL,
    `Username` VARCHAR(16) NOT NULL UNIQUE,
    `Passwd` VARCHAR(64) NOT NULL,
    `Notification` TINYINT(1) NOT NULL,
    PRIMARY KEY (`Email`)
) ENGINE=InnoDB;

DELIMITER //
CREATE TRIGGER secure_passwords_before__insert
BEFORE INSERT ON `Users`
FOR EACH ROW
BEGIN
    SET NEW.Passwd = SHA2(NEW.Passwd, 256);
END //

CREATE TRIGGER secure_passwords_before__update
BEFORE UPDATE ON `Users`
FOR EACH ROW
BEGIN
    IF NEW.Passwd <> OLD.Passwd THEN
        SET NEW.Passwd = SHA2(NEW.Passwd, 256);
    END IF;
END //
DELIMITER ;

DROP TABLE IF EXISTS `Picture`;
CREATE TABLE `Picture` (
    `FileName` VARCHAR(255) NOT NULL,
    `FilePath` VARCHAR(512) NOT NULL,
    `HydrationStatus` VARCHAR(45) NOT NULL,
    `ConfidenceScore` VARCHAR(10) NOT NULL,
    `FlowerSpecies` VARCHAR(45) DEFAULT "unknown",
    PRIMARY KEY (`FileName`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `ImageAnalysis`;
CREATE TABLE `ImageAnalysis` (
    `FileName` VARCHAR(255) NOT NULL,
    `Analysis` LONGTEXT NOT NULL,
    `Recommendation` MEDIUMTEXT NOT NULL,
    PRIMARY KEY (`FileName`),
    CONSTRAINT `FileName` FOREIGN KEY (`FileName`) REFERENCES Picture (`FileName`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `Notification`;
CREATE TABLE `Notification` (
    `Timestamp` VARCHAR(255) NOT NULL,
    `Email` VARCHAR(255) NOT NULL,
    `Type` VARCHAR(16) NOT NULL,
    PRIMARY KEY (`Timestamp`, `Type`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `DailySummary`;
CREATE TABLE `DailySummary` (
    `Timestamp` VARCHAR(255) NOT NULL,
    `Summary` LONGTEXT NOT NULL,
    PRIMARY KEY (`Timestamp`)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `WeeklySummary`;
CREATE TABLE `WeeklySummary` (
    `Timestamp` VARCHAR(255) NOT NULL,
    `Summary` LONGTEXT NOT NULL,
    PRIMARY KEY (`Timestamp`)
) ENGINE=InnoDB;
