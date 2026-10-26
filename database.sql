-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 12 oct. 2026 à 17:20
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `reclamassur`
--

-- --------------------------------------------------------

--
-- Structure de la table `client`
--

CREATE TABLE `client` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(180) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `client`
--

INSERT INTO `client` (`id`, `nom`, `prenom`, `email`, `telephone`) VALUES
(1, 'Durand', 'Amélie', 'amelie.durand@exemple.fr', '0611223344'),
(2, 'Leroy', 'Thomas', 'thomas.leroy@exemple.fr', '0622334455'),
(3, 'El Amrani', 'Nadia', 'nadia.elamrani@exemple.fr', '0633445566'),
(4, 'Petit', 'Julien', 'julien.petit@exemple.fr', '0644556677'),
(5, 'Bennani', 'Fatima', 'fatima.bennani@exemple.fr', '0655667788'),
(6, 'Martin', 'Lucas', 'lucas.martin@exemple.fr', '0666778899'),
(7, 'Bernard', 'Chloé', 'chloe.bernard@exemple.fr', '0677889900'),
(8, 'Alaoui', 'Mehdi', 'mehdi.alaoui@exemple.fr', '0688990011'),
(9, 'Rousseau', 'Emma', 'emma.rousseau@exemple.fr', '0699001122'),
(10, 'Lambert', 'Hugo', 'hugo.lambert@exemple.fr', '0600112233');

-- --------------------------------------------------------

--
-- Structure de la table `contrat`
--

CREATE TABLE `contrat` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `numero` varchar(12) NOT NULL,
  `type_contrat` enum('auto','habitation','sante') NOT NULL,
  `formule` varchar(80) NOT NULL,
  `date_souscription` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `contrat`
--

INSERT INTO `contrat` (`id`, `client_id`, `numero`, `type_contrat`, `formule`, `date_souscription`) VALUES
(1, 1, 'AUT-104582', 'auto', 'Tous risques', '2019-03-12'),
(2, 1, 'HAB-778412', 'habitation', 'Confort', '2021-06-01'),
(3, 2, 'SAN-220145', 'sante', 'Famille', '2020-01-15'),
(4, 3, 'AUT-330891', 'auto', 'Tiers étendu', '2022-09-08'),
(5, 4, 'HAB-441203', 'habitation', 'Essentielle', '2018-11-20'),
(6, 5, 'SAN-552014', 'sante', 'Solo', '2023-02-14'),
(7, 6, 'AUT-663120', 'auto', 'Tous risques', '2017-05-30'),
(8, 7, 'HAB-774531', 'habitation', 'Premium', '2024-04-02'),
(9, 8, 'SAN-885642', 'sante', 'Senior', '2016-08-19'),
(10, 9, 'AUT-196753', 'auto', 'Tiers', '2025-01-11'),
(11, 10, 'HAB-207864', 'habitation', 'Confort', '2021-12-03'),
(12, 3, 'SAN-318975', 'sante', 'Famille', '2019-07-22'),
(13, 5, 'AUT-429086', 'auto', 'Tiers étendu', '2022-03-18'),
(14, 8, 'HAB-530197', 'habitation', 'Essentielle', '2020-10-09'),
(15, 6, 'SAN-641208', 'sante', 'Solo', '2024-08-27');

-- --------------------------------------------------------

--
-- Structure de la table `historique`
--

CREATE TABLE `historique` (
  `id` int(11) NOT NULL,
  `reclamation_id` int(11) NOT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `type_action` varchar(50) NOT NULL,
  `detail` text NOT NULL,
  `date_action` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `historique`
--

INSERT INTO `historique` (`id`, `reclamation_id`, `utilisateur_id`, `type_action`, `detail`, `date_action`) VALUES
(1, 13, 1, 'depot', 'Réclamation saisie à partir d\'un courrier.', '2026-03-16 09:20:00'),
(2, 13, 1, 'attribution', 'Dossier attribué à Yanis Traoré.', '2026-03-17 09:00:00'),
(3, 13, 3, 'changement_statut', 'Dossier clôturé.', '2026-04-28 16:00:00'),
(4, 14, NULL, 'depot', 'Réclamation déposée par le client.', '2026-04-09 11:05:00'),
(5, 14, 1, 'attribution', 'Dossier attribué à Sophie Morel.', '2026-04-10 09:00:00'),
(6, 14, 2, 'changement_statut', 'Dossier clôturé.', '2026-05-21 15:30:00'),
(7, 27, NULL, 'depot', 'Réclamation déposée par le client.', '2026-09-02 09:05:00'),
(8, 27, 1, 'attribution', 'Dossier attribué à Sophie Morel.', '2026-09-03 08:40:00'),
(9, 27, 2, 'changement_statut', 'Accusé de réception envoyé.', '2026-09-04 10:15:00'),
(10, 27, 2, 'reponse_client', 'La résiliation est bien enregistrée. Une confirmation écrite est jointe à cette réponse.', '2026-10-01 11:20:00'),
(11, 27, 2, 'changement_statut', 'Dossier passé au statut Résolue.', '2026-10-01 11:21:00'),
(12, 31, 3, 'depot', 'Réclamation saisie suite à un appel téléphonique.', '2026-07-02 15:10:00'),
(13, 31, 1, 'attribution', 'Dossier attribué à Yanis Traoré.', '2026-07-03 09:10:00'),
(14, 31, 3, 'changement_statut', 'Accusé de réception envoyé.', '2026-07-06 14:00:00'),
(15, 31, 3, 'changement_statut', 'Dossier passé au statut En cours.', '2026-07-08 09:30:00'),
(16, 31, 3, 'note_interne', 'Le garage a transmis une facture incomplète. Relance prévue.', '2026-08-12 16:45:00'),
(17, 41, NULL, 'depot', 'Réclamation déposée par le client.', '2026-10-04 09:40:00'),
(18, 42, NULL, 'depot', 'Réclamation déposée par le client.', '2026-08-15 10:24:00'),
(19, 42, 1, 'attribution', 'Dossier attribué à Sophie Morel.', '2026-08-17 09:05:00'),
(20, 42, 2, 'changement_statut', 'Accusé de réception envoyé.', '2026-08-18 11:40:00'),
(21, 42, 2, 'reponse_client', 'Nous avons bien reçu votre réclamation et nous l\'étudions.', '2026-08-18 11:41:00'),
(22, 42, 2, 'changement_statut', 'Dossier passé au statut En cours.', '2026-08-20 14:12:00'),
(23, 42, 2, 'note_interne', 'Le rapport d\'expertise est demandé. Ne pas répondre au client avant réception.', '2026-09-03 16:40:00');

-- --------------------------------------------------------

--
-- Structure de la table `journal_connexion`
--

CREATE TABLE `journal_connexion` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `email_saisi` varchar(180) NOT NULL,
  `succes` tinyint(1) NOT NULL,
  `adresse_ip` varchar(45) DEFAULT NULL,
  `date_connexion` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `journal_connexion`
--

INSERT INTO `journal_connexion` (`id`, `utilisateur_id`, `email_saisi`, `succes`, `adresse_ip`, `date_connexion`) VALUES
(1, 1, 'karim.benali@assurium.fr', 1, '127.0.0.1', '2026-10-12 08:12:00'),
(2, 2, 'sophie.morel@assurium.fr', 1, '127.0.0.1', '2026-10-12 08:40:00'),
(3, 3, 'yanis.traore@assurium.fr', 1, '127.0.0.1', '2026-10-11 17:05:00'),
(4, NULL, 'sophie.morel@assurium.fr', 0, '127.0.0.1', '2026-10-10 18:22:00');

-- --------------------------------------------------------

--
-- Structure de la table `piece_jointe`
--

CREATE TABLE `piece_jointe` (
  `id` int(11) NOT NULL,
  `reclamation_id` int(11) NOT NULL,
  `nom_original` varchar(255) NOT NULL,
  `nom_stockage` varchar(255) NOT NULL,
  `type_mime` varchar(100) NOT NULL,
  `taille` int(11) NOT NULL,
  `date_ajout` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `piece_jointe`
--

INSERT INTO `piece_jointe` (`id`, `reclamation_id`, `nom_original`, `nom_stockage`, `type_mime`, `taille`, `date_ajout`) VALUES
(1, 42, 'constat-degat-eaux.pdf', '8f3a1c9e2b704d6a9c1e5f0a7b2d4e11.pdf', 'application/pdf', 245760, '2026-08-15 10:24:00'),
(2, 42, 'photo-mur.jpg', 'c4d91ab07e2541f68a0b3c9d2e7f1055.jpg', 'image/jpeg', 890112, '2026-08-15 10:24:00'),
(3, 31, 'facture-garage.pdf', '11aa90bc44de48f2a6c1e8b903d75f20.pdf', 'application/pdf', 512000, '2026-07-02 15:10:00'),
(4, 41, 'attestation.pdf', '77e0c1ab92df4a31b8c4e6f0a1d29384.pdf', 'application/pdf', 180224, '2026-10-04 09:40:00');

-- --------------------------------------------------------

--
-- Structure de la table `reclamation`
--

CREATE TABLE `reclamation` (
  `id` int(11) NOT NULL,
  `reference` varchar(20) NOT NULL,
  `contrat_id` int(11) NOT NULL,
  `categorie` varchar(50) NOT NULL,
  `objet` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `statut` enum('nouvelle','ar_envoye','en_cours','attente_client','resolue','cloturee') NOT NULL DEFAULT 'nouvelle',
  `priorite` enum('basse','moyenne','haute') NOT NULL,
  `conseiller_id` int(11) DEFAULT NULL,
  `canal` enum('web','telephone','courrier') NOT NULL DEFAULT 'web',
  `date_depot` datetime NOT NULL,
  `date_limite_ar` date NOT NULL,
  `date_limite_reponse` date NOT NULL,
  `date_cloture` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `reclamation`
--

INSERT INTO `reclamation` (`id`, `reference`, `contrat_id`, `categorie`, `objet`, `description`, `statut`, `priorite`, `conseiller_id`, `canal`, `date_depot`, `date_limite_ar`, `date_limite_reponse`, `date_cloture`) VALUES
(13, 'REC-2026-00013', 11, 'Qualité de service / accueil', 'Aucune réponse à mes appels', 'J\'ai appelé le service plusieurs fois en mars. Personne n\'a donné suite à ma demande.', 'cloturee', 'basse', 3, 'courrier', '2026-03-16 09:20:00', '2026-03-30', '2026-05-16', '2026-04-28'),
(14, 'REC-2026-00014', 9, 'Refus de prise en charge', 'Refus de soins dentaires', 'Des soins dentaires prévus au contrat ont été refusés sans explication claire.', 'cloturee', 'haute', 2, 'web', '2026-04-09 11:05:00', '2026-04-23', '2026-06-09', '2026-05-21'),
(15, 'REC-2026-00015', 14, 'Résiliation', 'Résiliation habitation ignorée', 'Ma demande de résiliation envoyée en mai n\'a pas été enregistrée.', 'cloturee', 'moyenne', 3, 'telephone', '2026-05-13 14:40:00', '2026-05-27', '2026-07-13', '2026-06-30'),
(16, 'REC-2026-00016', 12, 'Retard d\'indemnisation', 'Indemnité santé versée en retard', 'Le remboursement de mes soins a été versé plus de trois mois après la demande.', 'cloturee', 'haute', 2, 'web', '2026-06-02 10:15:00', '2026-06-16', '2026-08-02', '2026-07-20'),
(17, 'REC-2026-00017', 5, 'Contestation de cotisation / tarif', 'Hausse de cotisation habitation', 'Ma cotisation habitation a augmenté d\'environ 30 pour cent sans justification.', 'resolue', 'moyenne', 3, 'web', '2026-07-10 16:00:00', '2026-07-24', '2026-09-10', '2026-08-26'),
(18, 'REC-2026-00018', 7, 'Refus de prise en charge', 'Bris de glace refusé', 'Le remplacement du pare-brise a été refusé alors que la garantie est active.', 'resolue', 'haute', 2, 'telephone', '2026-08-03 09:50:00', '2026-08-17', '2026-10-03', '2026-09-18'),
(19, 'REC-2026-00019', 10, 'Erreur administrative', 'Erreur d\'adresse sur la carte verte', 'L\'adresse indiquée sur ma carte verte ne correspond pas à mon domicile.', 'resolue', 'moyenne', 3, 'web', '2026-09-15 13:25:00', '2026-09-29', '2026-11-15', '2026-10-08'),
(20, 'REC-2026-00020', 8, 'Autre', 'Duplicata d\'attestation jamais reçu', 'J\'ai demandé un duplicata d\'attestation en juin. Je ne l\'ai toujours pas reçu à la clôture.', 'resolue', 'basse', 2, 'web', '2026-06-22 08:45:00', '2026-07-06', '2026-08-22', '2026-08-04'),
(21, 'REC-2026-00021', 15, 'Erreur administrative', 'RIB erroné sur le contrat santé', 'Le RIB enregistré sur mon contrat santé est faux. Les virements ne peuvent pas partir.', 'attente_client', 'moyenne', 2, 'web', '2026-09-01 10:10:00', '2026-09-15', '2026-11-01', NULL),
(22, 'REC-2026-00022', 4, 'Retard d\'indemnisation', 'Indemnisation d\'un vol non versée', 'Le vol déclaré en juillet n\'a toujours pas été indemnisé. J\'attends le règlement.', 'attente_client', 'haute', 3, 'web', '2026-07-20 15:30:00', '2026-08-03', '2026-09-20', NULL),
(23, 'REC-2026-00023', 6, 'Qualité de service / accueil', 'Impossible de joindre le service', 'Depuis plusieurs semaines, personne ne décroche au numéro du service réclamations.', 'attente_client', 'moyenne', 2, 'courrier', '2026-08-18 11:00:00', '2026-09-01', '2026-10-18', NULL),
(24, 'REC-2026-00024', 8, 'Retard d\'indemnisation', 'Remboursement dégât des eaux en attente', 'Le dégât des eaux de l\'été n\'est toujours pas remboursé malgré un dossier complet.', 'en_cours', 'haute', 3, 'web', '2026-08-20 09:35:00', '2026-09-03', '2026-10-20', NULL),
(25, 'REC-2026-00025', 11, 'Erreur administrative', 'Surface du logement incorrecte', 'La surface déclarée sur le contrat habitation est fausse et change ma cotisation.', 'en_cours', 'moyenne', 2, 'courrier', '2026-08-28 14:20:00', '2026-09-11', '2026-10-28', NULL),
(26, 'REC-2026-00026', 9, 'Qualité de service / accueil', 'Le standard ne répond jamais', 'Chaque appel aboutit sur une messagerie. Aucun conseiller ne rappelle.', 'en_cours', 'moyenne', 2, 'telephone', '2026-06-15 17:10:00', '2026-06-29', '2026-08-15', NULL),
(27, 'REC-2026-00027', 3, 'Résiliation', 'Résiliation non enregistrée', 'Ma demande de résiliation du contrat santé n\'avait pas été prise en compte.', 'resolue', 'moyenne', 2, 'web', '2026-09-02 09:05:00', '2026-09-16', '2026-11-02', '2026-10-01'),
(28, 'REC-2026-00028', 12, 'Refus de prise en charge', 'Hospitalisation non prise en charge', 'Une hospitalisation prévue au contrat a été refusée. Je conteste cette décision.', 'en_cours', 'haute', 3, 'web', '2026-09-05 12:45:00', '2026-09-18', '2026-11-05', NULL),
(29, 'REC-2026-00029', 13, 'Contestation de cotisation / tarif', 'Cotisation auto en forte hausse', 'Ma cotisation auto a augmenté de 30 pour cent au renouvellement, sans sinistre.', 'en_cours', 'moyenne', 2, 'web', '2026-09-12 08:30:00', '2026-09-25', '2026-11-12', NULL),
(30, 'REC-2026-00030', 14, 'Résiliation', 'Résiliation auto non prise en compte', 'J\'ai demandé la résiliation de mon contrat auto. Elle n\'apparaît toujours pas.', 'en_cours', 'haute', 3, 'telephone', '2026-08-05 16:15:00', '2026-08-19', '2026-10-05', NULL),
(31, 'REC-2026-00031', 1, 'Retard d\'indemnisation', 'Remboursement auto en attente depuis mai', 'Mon garage a été réglé en partie seulement. Le solde est dû depuis mai.', 'en_cours', 'haute', 3, 'telephone', '2026-07-02 15:10:00', '2026-07-16', '2026-09-02', NULL),
(32, 'REC-2026-00032', 7, 'Autre', 'Changement de véhicule non enregistré', 'J\'ai déclaré mon nouveau véhicule. L\'ancien figure encore sur le contrat.', 'en_cours', 'basse', 2, 'web', '2026-09-18 10:55:00', '2026-10-02', '2026-11-18', NULL),
(33, 'REC-2026-00033', 7, 'Refus de prise en charge', 'Sinistre sur un parking refusé', 'Un accrochage sur un parking a été refusé. Je demande une nouvelle étude.', 'en_cours', 'haute', 3, 'web', '2026-10-01 13:40:00', '2026-10-15', '2026-12-01', NULL),
(34, 'REC-2026-00034', 3, 'Résiliation', 'Confirmation de résiliation absente', 'La résiliation a été demandée. Je n\'ai reçu aucune confirmation écrite.', 'ar_envoye', 'moyenne', 2, 'web', '2026-09-28 09:25:00', '2026-10-12', '2026-11-28', NULL),
(35, 'REC-2026-00035', 4, 'Refus de prise en charge', 'Responsabilité contestée après accident', 'L\'accident a été classé à tort à ma charge. Je conteste la responsabilité.', 'ar_envoye', 'haute', 3, 'telephone', '2026-09-25 18:05:00', '2026-10-09', '2026-11-25', NULL),
(36, 'REC-2026-00036', 10, 'Autre', 'Document de contrat illisible', 'Le document reçu par e-mail est illisible. Je ne peux pas vérifier mes garanties.', 'ar_envoye', 'basse', 2, 'web', '2026-09-22 11:35:00', '2026-10-06', '2026-11-22', NULL),
(37, 'REC-2026-00037', 15, 'Refus de prise en charge', 'Remboursement de lunettes refusé', 'Le remboursement de mes lunettes a été refusé alors que le forfait n\'est pas épuisé.', 'nouvelle', 'haute', NULL, 'web', '2026-10-10 09:15:00', '2026-10-23', '2026-12-10', NULL),
(38, 'REC-2026-00038', 13, 'Retard d\'indemnisation', 'Réparation auto non remboursée', 'La facture du garage a été envoyée début octobre. Aucun remboursement n\'est arrivé.', 'nouvelle', 'haute', NULL, 'web', '2026-10-09 14:50:00', '2026-10-23', '2026-12-09', NULL),
(39, 'REC-2026-00039', 5, 'Contestation de cotisation / tarif', 'Hausse de cotisation contestée', 'La cotisation habitation augmente fortement. Je demande le détail du calcul.', 'nouvelle', 'moyenne', NULL, 'telephone', '2026-10-08 10:05:00', '2026-10-22', '2026-12-08', NULL),
(40, 'REC-2026-00040', 6, 'Qualité de service / accueil', 'Courrier resté sans réponse', 'J\'ai écrit au service en septembre. L\'accusé de réception n\'est toujours pas arrivé.', 'nouvelle', 'basse', NULL, 'courrier', '2026-09-18 08:00:00', '2026-10-02', '2026-11-18', NULL),
(41, 'REC-2026-00041', 10, 'Erreur administrative', 'Attestation contenant une erreur', 'Mon attestation indique une mauvaise date de début de contrat.', 'nouvelle', 'moyenne', NULL, 'web', '2026-10-04 09:40:00', '2026-10-16', '2026-12-04', NULL),
(42, 'REC-2026-00042', 2, 'Refus de prise en charge', 'Sinistre dégât des eaux non indemnisé', 'Un dégât des eaux a été déclaré. La prise en charge a été refusée sans motif détaillé.', 'en_cours', 'haute', 2, 'web', '2026-08-15 10:24:00', '2026-08-28', '2026-10-15', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(180) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('conseiller','superviseur') NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `tentatives_echec` int(11) NOT NULL DEFAULT 0,
  `bloque_jusqua` datetime DEFAULT NULL,
  `derniere_connexion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `actif`, `tentatives_echec`, `bloque_jusqua`, `derniere_connexion`) VALUES
(1, 'Benali', 'Karim', 'karim.benali@assurium.fr', '$2y$10$aTW8II5476fW0clK1XuFKOisvLdNHJRfta9l8dg9toZX9fNGdxya2', 'superviseur', 1, 0, NULL, '2026-10-12 08:12:00'),
(2, 'Morel', 'Sophie', 'sophie.morel@assurium.fr', '$2y$10$G802MQvMMHBTzNQoXj/PU.GlGYmFZb8pjAtHyofbHk16LDSBB5EV2', 'conseiller', 1, 0, NULL, '2026-10-12 08:40:00'),
(3, 'Traoré', 'Yanis', 'yanis.traore@assurium.fr', '$2y$10$G802MQvMMHBTzNQoXj/PU.GlGYmFZb8pjAtHyofbHk16LDSBB5EV2', 'conseiller', 1, 0, NULL, '2026-10-11 17:05:00');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `contrat`
--
ALTER TABLE `contrat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `fk_contrat_client` (`client_id`);

--
-- Index pour la table `historique`
--
ALTER TABLE `historique`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_historique_reclamation` (`reclamation_id`),
  ADD KEY `fk_historique_utilisateur` (`utilisateur_id`);

--
-- Index pour la table `journal_connexion`
--
ALTER TABLE `journal_connexion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_journal_utilisateur` (`utilisateur_id`);

--
-- Index pour la table `piece_jointe`
--
ALTER TABLE `piece_jointe`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_piece_reclamation` (`reclamation_id`);

--
-- Index pour la table `reclamation`
--
ALTER TABLE `reclamation`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `fk_reclamation_contrat` (`contrat_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_conseiller` (`conseiller_id`),
  ADD KEY `idx_date_limite` (`date_limite_reponse`),
  ADD KEY `idx_date_depot` (`date_depot`);

--
-- Index pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `client`
--
ALTER TABLE `client`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `contrat`
--
ALTER TABLE `contrat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `historique`
--
ALTER TABLE `historique`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `journal_connexion`
--
ALTER TABLE `journal_connexion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `piece_jointe`
--
ALTER TABLE `piece_jointe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `reclamation`
--
ALTER TABLE `reclamation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `contrat`
--
ALTER TABLE `contrat`
  ADD CONSTRAINT `fk_contrat_client` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`);

--
-- Contraintes pour la table `historique`
--
ALTER TABLE `historique`
  ADD CONSTRAINT `fk_historique_reclamation` FOREIGN KEY (`reclamation_id`) REFERENCES `reclamation` (`id`),
  ADD CONSTRAINT `fk_historique_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `journal_connexion`
--
ALTER TABLE `journal_connexion`
  ADD CONSTRAINT `fk_journal_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `piece_jointe`
--
ALTER TABLE `piece_jointe`
  ADD CONSTRAINT `fk_piece_reclamation` FOREIGN KEY (`reclamation_id`) REFERENCES `reclamation` (`id`);

--
-- Contraintes pour la table `reclamation`
--
ALTER TABLE `reclamation`
  ADD CONSTRAINT `fk_reclamation_conseiller` FOREIGN KEY (`conseiller_id`) REFERENCES `utilisateur` (`id`),
  ADD CONSTRAINT `fk_reclamation_contrat` FOREIGN KEY (`contrat_id`) REFERENCES `contrat` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
