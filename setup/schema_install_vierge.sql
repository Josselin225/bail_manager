-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: gestion_bail
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bailleurs`
--

DROP TABLE IF EXISTS `bailleurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bailleurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code_bailleur` varchar(14) DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `sexe` char(1) DEFAULT NULL,
  `numero_cni` varchar(50) DEFAULT NULL,
  `telephone1` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `telephone2` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adresse` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `solde_du_bailleur` decimal(10,2) DEFAULT '0.00',
  `total_commissions_entreprises` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `charges_locatives`
--

DROP TABLE IF EXISTS `charges_locatives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `charges_locatives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contrat_id` int NOT NULL,
  `type_charge` varchar(100) NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `date_charge` date NOT NULL,
  `description` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `contrat_id` (`contrat_id`),
  CONSTRAINT `charges_locatives_ibfk_1` FOREIGN KEY (`contrat_id`) REFERENCES `contrats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `compte_courant_bailleur`
--

DROP TABLE IF EXISTS `compte_courant_bailleur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compte_courant_bailleur` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bailleur_id` int DEFAULT NULL,
  `date_operation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `type_operation` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `montant` decimal(10,2) NOT NULL,
  `commentaire` text,
  PRIMARY KEY (`id`),
  KEY `bailleur_id` (`bailleur_id`),
  CONSTRAINT `compte_courant_bailleur_ibfk_1` FOREIGN KEY (`bailleur_id`) REFERENCES `bailleurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contrats`
--

DROP TABLE IF EXISTS `contrats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contrats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `maison_id` int DEFAULT NULL,
  `locataire_id` int DEFAULT NULL,
  `loyer_mensuel` decimal(10,2) NOT NULL,
  `commission_pourcentage` decimal(5,2) DEFAULT '10.00',
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `statut_contrat` enum('actif','termine') DEFAULT 'actif',
  `date_contrat` date DEFAULT NULL COMMENT 'date de signature du contrat',
  `depot_garantie` decimal(10,2) DEFAULT NULL COMMENT 'caution',
  `solde_actuel` decimal(10,2) DEFAULT NULL,
  `depot_garantie_actuel` decimal(10,2) DEFAULT NULL,
  `date_prochain_loyer` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `maison_id` (`maison_id`),
  KEY `locataire_id` (`locataire_id`),
  CONSTRAINT `contrats_ibfk_1` FOREIGN KEY (`maison_id`) REFERENCES `maisons` (`id`),
  CONSTRAINT `contrats_ibfk_2` FOREIGN KEY (`locataire_id`) REFERENCES `locataires` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `encaissements`
--

DROP TABLE IF EXISTS `encaissements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `encaissements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contrat_id` int DEFAULT NULL,
  `montant_recu` decimal(10,2) NOT NULL,
  `date_encaissement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `mode_paiement` enum('especes','virement','mobile_money') DEFAULT 'especes',
  `reference_recu` varchar(50) DEFAULT NULL,
  `periode_concernee` varchar(50) DEFAULT NULL,
  `reliquat_apres_paiement` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `contrat_id` (`contrat_id`),
  CONSTRAINT `encaissements_ibfk_1` FOREIGN KEY (`contrat_id`) REFERENCES `contrats` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `locataire_acces`
--

DROP TABLE IF EXISTS `locataire_acces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `locataire_acces` (
  `id` int NOT NULL AUTO_INCREMENT,
  `locataire_id` int NOT NULL,
  `code_acces` varchar(20) NOT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locataire_id` (`locataire_id`),
  CONSTRAINT `locataire_acces_ibfk_1` FOREIGN KEY (`locataire_id`) REFERENCES `locataires` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `locataires`
--

DROP TABLE IF EXISTS `locataires`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `locataires` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `telephone1` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `telephone2` varchar(14) DEFAULT NULL,
  `piece_identite` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `photo_locataire` varchar(255) DEFAULT NULL,
  `photo_cni_recto` varchar(255) DEFAULT NULL,
  `photo_cni_verso` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text,
  `date_action` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_adresse` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=164 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `loyers_generes`
--

DROP TABLE IF EXISTS `loyers_generes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyers_generes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contrat_id` int DEFAULT NULL,
  `montant_du` decimal(10,2) NOT NULL,
  `mois_concerne` int DEFAULT NULL,
  `annee_concernee` int DEFAULT NULL,
  `est_paye` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `contrat_id` (`contrat_id`),
  CONSTRAINT `loyers_generes_ibfk_1` FOREIGN KEY (`contrat_id`) REFERENCES `contrats` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maisons`
--

DROP TABLE IF EXISTS `maisons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maisons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bailleur_id` int DEFAULT NULL,
  `designation` varchar(255) NOT NULL,
  `type_maison` varchar(100) DEFAULT NULL,
  `adresse` text,
  `description` text,
  `statut` enum('disponible','occupe') DEFAULT 'disponible',
  `condition` varchar(10) DEFAULT NULL COMMENT 'nombre de loyer àpayer avant d''entrer dans la maison',
  `loyer` decimal(10,2) DEFAULT NULL,
  `image1` varchar(255) DEFAULT NULL,
  `image2` varchar(255) DEFAULT NULL,
  `image3` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bailleur_id` (`bailleur_id`),
  CONSTRAINT `maisons_ibfk_1` FOREIGN KEY (`bailleur_id`) REFERENCES `bailleurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom_visiteur` varchar(255) NOT NULL,
  `email_visiteur` varchar(255) NOT NULL,
  `sujet` varchar(255) DEFAULT NULL,
  `contenu` text NOT NULL,
  `statut` enum('non_lu','lu') DEFAULT 'non_lu',
  `date_envoi` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mouvements_caisse_entreprise`
--

DROP TABLE IF EXISTS `mouvements_caisse_entreprise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mouvements_caisse_entreprise` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bailleur_id` int DEFAULT NULL,
  `type_mouvement` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `montant` decimal(10,2) NOT NULL,
  `date_operation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reference_paiement` varchar(100) DEFAULT NULL,
  `commentaire` text,
  `effectue_par` varchar(100) DEFAULT 'Administrateur',
  PRIMARY KEY (`id`),
  KEY `bailleur_id` (`bailleur_id`),
  CONSTRAINT `mouvements_caisse_entreprise_ibfk_1` FOREIGN KEY (`bailleur_id`) REFERENCES `bailleurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mouvements_caution`
--

DROP TABLE IF EXISTS `mouvements_caution`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mouvements_caution` (
  `id` int NOT NULL AUTO_INCREMENT,
  `locataire_id` int NOT NULL,
  `type_mouvement` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `montant` decimal(10,2) NOT NULL,
  `date_operation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `commentaire` text,
  `effectue_par` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `locataire_id` (`locataire_id`),
  CONSTRAINT `mouvements_caution_ibfk_1` FOREIGN KEY (`locataire_id`) REFERENCES `locataires` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `paiements`
--

DROP TABLE IF EXISTS `paiements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paiements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contrat_id` int DEFAULT NULL,
  `montant_total` decimal(10,2) NOT NULL,
  `commission_entreprise` decimal(10,2) DEFAULT NULL,
  `net_bailleur` decimal(10,2) DEFAULT NULL,
  `date_paiement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `periode_loyer` varchar(50) DEFAULT NULL,
  `statut_versement_bailleur` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `contrat_id` (`contrat_id`),
  CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`contrat_id`) REFERENCES `contrats` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `maison_id` int NOT NULL,
  `nom_visiteur` varchar(255) NOT NULL,
  `tel_visiteur` varchar(50) NOT NULL,
  `date_visite` date NOT NULL,
  `statut` enum('en_attente','confirme','annule') DEFAULT 'en_attente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `maison_id` (`maison_id`),
  CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`maison_id`) REFERENCES `maisons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `revisions_loyer`
--

DROP TABLE IF EXISTS `revisions_loyer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `revisions_loyer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contrat_id` int NOT NULL,
  `ancien_loyer` decimal(12,2) NOT NULL,
  `nouveau_loyer` decimal(12,2) NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `effectue_par` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `contrat_id` (`contrat_id`),
  CONSTRAINT `revisions_loyer_ibfk_1` FOREIGN KEY (`contrat_id`) REFERENCES `contrats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` int DEFAULT NULL,
  `last_activity` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom_entreprise` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_telephone` varchar(50) DEFAULT NULL,
  `taux_commission` decimal(5,2) DEFAULT '10.00',
  `adresse_siege` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logo_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom_complet` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('admin','agent') DEFAULT 'agent',
  `dernier_acces` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expire` datetime DEFAULT NULL,
  `trial_ends_at` datetime DEFAULT NULL,
  `is_subscribed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `versements_bailleurs`
--

DROP TABLE IF EXISTS `versements_bailleurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `versements_bailleurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bailleur_id` int DEFAULT NULL,
  `montant_verse` decimal(10,2) NOT NULL,
  `commission_retenue` decimal(10,2) NOT NULL,
  `date_versement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bailleur_id` (`bailleur_id`),
  CONSTRAINT `versements_bailleurs_ibfk_1` FOREIGN KEY (`bailleur_id`) REFERENCES `bailleurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-28 14:30:36

-- ═══════════════════════════════════════════════════════════════
-- Données initiales pour une installation vierge
-- ═══════════════════════════════════════════════════════════════

-- Compte administrateur initial (mot de passe temporaire : ChangeMoi123!)
-- IMPORTANT : connectez-vous puis changez ce mot de passe immédiatement
-- via le menu utilisateur > "Changer le mot de passe".
INSERT INTO `users` (`nom_complet`, `email`, `mot_de_passe`, `role`) VALUES
('Administrateur', 'admin@votre-agence.com', '$2y$10$UTUMOOt260CBX9Ci4q3GX.HqiaRXSk3JrjGKvDsmG8vqQmTKITLia', 'admin');

-- Ligne de paramètres par défaut (obligatoire : la page Paramètres cible id=1)
INSERT INTO `settings` (`id`, `nom_entreprise`, `taux_commission`) VALUES
(1, 'BailManager', 10.00);
