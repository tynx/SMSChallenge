CREATE DATABASE `smschallenge` DEFAULT CHARACTER SET UTF8;

CREATE TABLE `smschallenge`.`user`(
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `dn` VARCHAR(2048) DEFAULT NULL,
  `employeeID` BIGINT DEFAULT NULL,
  `defaultLanguage` VARCHAR(2) DEFAULT NULL,
  `employeeType` VARCHAR(24) NULL,
  `title` VARCHAR(24) NULL,
  `givenName` VARCHAR(1024) NOT NULL,
  `surName` VARCHAR(1024) NOT NULL,
  `username` VARCHAR(1024) NOT NULL,
  `email` VARCHAR(1024) NULL,
  `telephoneNumber` VARCHAR(1024) NULL,
  `mobileNumber` VARCHAR(1024) NULL,
  `department` VARCHAR(1024) NULL,
  `manager` VARCHAR(2048) NULL,
  `street` VARCHAR(2048) NULL,
  `office` VARCHAR(1024) NULL,
  `room` VARCHAR(1024) NULL,
  `postalcode` INT NULL,
  `city` VARCHAR(1024) NULL,
  `web` VARCHAR(2048) NULL,
  `synced` TINYINT(1) DEFAULT 0 NOT NULL,
  `lastSynced` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastLogin` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lastChange` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
  `permissionStatus` INT NOT NULL DEFAULT 0,
  `password` VARCHAR(40) DEFAULT NULL,
  `code` VARCHAR(1024) DEFAULT NULL,
  `Notes` varchar(2048) DEFAULT NULL,
  `salt`  varchar(6) DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARACTER SET UTF8
  COLLATE utf8_general_ci;

CREATE TABLE `smschallenge`.`log` (
  `id` INT(20) PRIMARY KEY AUTO_INCREMENT, 
  `id_user` INT NULL,
  `host` VARCHAR(30) DEFAULT NULL,
  `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `priority` ENUM('debug', 'info','warning','error','critical') DEFAULT 'info',
  `message` varchar(1024) DEFAULT NULL
) ENGINE = InnoDB
  DEFAULT CHARACTER SET UTF8
  COLLATE utf8_general_ci;
