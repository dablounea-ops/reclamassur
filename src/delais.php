<?php

declare(strict_types=1);

const STATUTS = [
    'nouvelle' => 'Nouvelle',
    'ar_envoye' => 'AR envoyé',
    'en_cours' => 'En cours',
    'attente_client' => 'En attente du client',
    'resolue' => 'Résolue',
    'cloturee' => 'Clôturée',
];

const PRIORITES = [
    'basse' => 'Basse',
    'moyenne' => 'Moyenne',
    'haute' => 'Haute',
];

const CATEGORIES = [
    'Refus de prise en charge' => 'haute',
    "Retard d'indemnisation" => 'haute',
    'Contestation de cotisation / tarif' => 'moyenne',
    'Résiliation' => 'moyenne',
    'Erreur administrative' => 'moyenne',
    'Qualité de service / accueil' => 'basse',
    'Autre' => 'basse',
];

const TYPES_CONTRAT = [
    'auto' => 'Auto',
    'habitation' => 'Habitation',
    'sante' => 'Santé',
];

const CANAUX = [
    'web' => 'Web',
    'telephone' => 'Téléphone',
    'courrier' => 'Courrier',
];

const ACTIONS_HISTORIQUE = [
    'depot' => 'Dépôt',
    'attribution' => 'Attribution',
    'changement_statut' => 'Statut',
    'reponse_client' => 'Réponse au client',
    'note_interne' => 'Note interne',
    'telechargement' => 'Téléchargement',
];

function ajouterJoursOuvrables(DateTimeImmutable $date, int $jours): DateTimeImmutable
{
    $courante = $date->setTime(12, 0);
    while ($jours > 0) {
        $courante = $courante->modify('+1 day');
        if ((int) $courante->format('N') < 6) {
            $jours--;
        }
    }

    return $courante;
}

function ajouterMois(DateTimeImmutable $date, int $mois): DateTimeImmutable
{
    $jour = (int) $date->format('j');
    $base = $date->modify('first day of this month')->modify('+' . $mois . ' months');
    $dernier = (int) $base->format('t');

    return $base->setDate((int) $base->format('Y'), (int) $base->format('m'), min($jour, $dernier));
}

function prioriteDeDepart(string $categorie): string
{
    return CATEGORIES[$categorie] ?? 'basse';
}

function niveauSuperieur(string $priorite): string
{
    return match ($priorite) {
        'basse' => 'moyenne',
        'moyenne' => 'haute',
        default => 'haute',
    };
}

function etatDelai(array $reclamation, ?DateTimeImmutable $aujourdhui = null): array
{
    $aujourdhui = ($aujourdhui ?? new DateTimeImmutable('today'))->setTime(0, 0);
    if (in_array($reclamation['statut'], ['resolue', 'cloturee'], true)) {
        return ['code' => 'traitee', 'label' => 'Traitée', 'classe' => 'ok'];
    }

    $limite = new DateTimeImmutable($reclamation['date_limite_reponse']);
    $jours = (int) $aujourdhui->diff($limite)->format('%r%a');
    if ($jours < 0) {
        return ['code' => 'retard', 'label' => 'En retard', 'classe' => 'danger', 'jours' => $jours];
    }
    if ($jours <= 15) {
        return ['code' => 'proche', 'label' => 'J-' . $jours, 'classe' => 'warning', 'jours' => $jours];
    }

    return ['code' => 'ok', 'label' => $limite->format('d/m/Y'), 'classe' => 'ok', 'jours' => $jours];
}

function prioriteEffective(array $reclamation, ?DateTimeImmutable $aujourdhui = null): string
{
    $base = prioriteDeDepart($reclamation['categorie']);
    $etat = etatDelai($reclamation, $aujourdhui);
    if ($etat['code'] === 'retard' || $etat['code'] === 'proche') {
        return niveauSuperieur($base);
    }

    return $base;
}

function sqlPrioriteEffective(): string
{
    return <<<'SQL'
CASE
    WHEN r.statut NOT IN ('resolue', 'cloturee') AND DATEDIFF(r.date_limite_reponse, CURDATE()) <= 15 THEN
        CASE
            WHEN r.categorie IN ('Refus de prise en charge', 'Retard d''indemnisation') THEN 'haute'
            WHEN r.categorie IN ('Contestation de cotisation / tarif', 'Résiliation', 'Erreur administrative') THEN 'haute'
            ELSE 'moyenne'
        END
    WHEN r.categorie IN ('Refus de prise en charge', 'Retard d''indemnisation') THEN 'haute'
    WHEN r.categorie IN ('Contestation de cotisation / tarif', 'Résiliation', 'Erreur administrative') THEN 'moyenne'
    ELSE 'basse'
END
SQL;
}

function transitionsAutorisees(string $statut, bool $superviseur): array
{
    return match ($statut) {
        'nouvelle' => ['ar_envoye'],
        'ar_envoye' => ['en_cours'],
        'en_cours' => ['attente_client', 'resolue'],
        'attente_client' => ['en_cours'],
        'resolue' => ['cloturee', 'en_cours'],
        'cloturee' => $superviseur ? ['en_cours'] : [],
        default => [],
    };
}

function libelleChangement(string $statut): string
{
    return match ($statut) {
        'ar_envoye' => 'Accusé de réception envoyé.',
        'en_cours' => 'Dossier passé au statut En cours.',
        'attente_client' => 'Dossier passé au statut En attente du client.',
        'resolue' => 'Dossier passé au statut Résolue.',
        'cloturee' => 'Dossier clôturé.',
        default => 'Statut mis à jour.',
    };
}

function typeDepuisNumero(string $numero): ?string
{
    return match (strtoupper(substr($numero, 0, 3))) {
        'AUT' => 'auto',
        'HAB' => 'habitation',
        'SAN' => 'sante',
        default => null,
    };
}

function anciennete(string $date): string
{
    $diff = (new DateTimeImmutable($date))->diff(new DateTimeImmutable('today'));
    if ($diff->y >= 1) {
        return $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
    }
    if ($diff->m >= 1) {
        return $diff->m . ' mois';
    }

    return $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
}

function badgeStatut(string $statut): string
{
    $classe = 'st-' . preg_replace('/[^a-z_]/', '', $statut);

    return '<span class="badge-pil ' . $classe . '">' . e(STATUTS[$statut] ?? $statut) . '</span>';
}

function badgePriorite(string $priorite): string
{
    $classe = 'pr-' . preg_replace('/[^a-z]/', '', $priorite);

    return '<span class="badge-pil ' . $classe . '">' . e(PRIORITES[$priorite] ?? $priorite) . '</span>';
}

function badgeDelai(array $etat): string
{
    return '<span class="badge-pil dl-' . e($etat['classe']) . '">' . e($etat['label']) . '</span>';
}

function plierTexte(string $texte): string
{
    $texte = mb_strtolower(trim($texte));
    $texte = strtr($texte, [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ï' => 'i', 'î' => 'i', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        'œ' => 'oe', 'æ' => 'ae',
    ]);

    return preg_replace('/[^a-z]/', '', $texte) ?? '';
}

function dossierPieces(): string
{
    $dossier = RACINE . '/uploads';
    if (!is_dir($dossier)) {
        mkdir($dossier, 0775, true);
    }

    return $dossier;
}

function normaliserFichiers(?array $fichiers): array
{
    if ($fichiers === null || !isset($fichiers['name']) || !is_array($fichiers['name'])) {
        return [];
    }
    $liste = [];
    foreach ($fichiers['name'] as $index => $nom) {
        $erreur = (int) ($fichiers['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($erreur === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $liste[] = [
            'name' => (string) $nom,
            'tmp_name' => (string) ($fichiers['tmp_name'][$index] ?? ''),
            'error' => $erreur,
            'size' => (int) ($fichiers['size'][$index] ?? 0),
        ];
    }

    return $liste;
}

function typeReelFichier(string $chemin): ?string
{
    $contenu = file_get_contents($chemin, false, null, 0, 8);
    if ($contenu === false) {
        return null;
    }
    if (str_starts_with($contenu, '%PDF')) {
        return 'application/pdf';
    }
    if (str_starts_with($contenu, "\xFF\xD8\xFF")) {
        return 'image/jpeg';
    }
    if (str_starts_with($contenu, "\x89PNG\r\n\x1A\n")) {
        return 'image/png';
    }

    return null;
}

function traiterDepot(array $saisie, ?array $fichiers, ?array $agent): array
{
    $erreurs = [];
    $interne = $agent !== null;
    $nom = trim((string) ($saisie['nom'] ?? ''));
    $prenom = trim((string) ($saisie['prenom'] ?? ''));
    $email = mb_strtolower(trim((string) ($saisie['email'] ?? '')));
    $telephone = trim((string) ($saisie['telephone'] ?? ''));
    $numero = strtoupper(trim((string) ($saisie['numero'] ?? '')));
    $type = (string) ($saisie['type_contrat'] ?? '');
    $categorie = (string) ($saisie['categorie'] ?? '');
    $objet = trim((string) ($saisie['objet'] ?? ''));
    $description = trim((string) ($saisie['description'] ?? ''));
    $canal = $interne ? (string) ($saisie['canal'] ?? '') : 'web';

    if (trim((string) ($saisie['site_web'] ?? '')) !== '') {
        return ['ok' => false, 'erreurs' => ['_form' => 'Votre envoi n\'a pas pu être accepté.']];
    }

    if (!preg_match("/^[\\p{L}][\\p{L} '\\-]{1,49}$/u", $nom)) {
        $erreurs['nom'] = 'Veuillez saisir un nom valide.';
    }
    if (!preg_match("/^[\\p{L}][\\p{L} '\\-]{1,49}$/u", $prenom)) {
        $erreurs['prenom'] = 'Veuillez saisir un nom valide.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = 'L\'adresse e-mail n\'est pas valide.';
    }
    $chiffres = preg_replace('/\s+/', '', $telephone) ?? '';
    if ($telephone !== '' && !preg_match('/^\d{10}$/', $chiffres)) {
        $erreurs['telephone'] = 'Le numéro doit contenir 10 chiffres.';
    }
    if (!preg_match('/^(AUT|HAB|SAN)-\d{6}$/', $numero)) {
        $erreurs['numero'] = 'Numéro de contrat introuvable. Vérifiez votre attestation.';
    }
    if (!isset(TYPES_CONTRAT[$type])) {
        $erreurs['type_contrat'] = 'Le type ne correspond pas au numéro de contrat.';
    } elseif ($numero !== '' && typeDepuisNumero($numero) !== null && typeDepuisNumero($numero) !== $type) {
        $erreurs['type_contrat'] = 'Le type ne correspond pas au numéro de contrat.';
    }
    if (!isset(CATEGORIES[$categorie])) {
        $erreurs['categorie'] = 'Choisissez une catégorie.';
    }
    if (mb_strlen($objet) < 5 || mb_strlen($objet) > 150) {
        $erreurs['objet'] = 'L\'objet doit contenir entre 5 et 150 caractères.';
    }
    if (mb_strlen($description) < 20 || mb_strlen($description) > 2000) {
        $erreurs['description'] = 'Décrivez votre réclamation (20 caractères minimum).';
    }
    if ($interne && !in_array($canal, ['telephone', 'courrier'], true)) {
        $erreurs['canal'] = 'Indiquez si la réclamation arrive par téléphone ou par courrier.';
    }
    if (empty($saisie['consentement'])) {
        $erreurs['consentement'] = 'Votre accord est nécessaire pour traiter la réclamation.';
    }

    $contrat = null;
    if (!isset($erreurs['numero'])) {
        $stmt = pdo()->prepare(
            'SELECT c.id, c.numero, c.type_contrat, cl.nom, cl.prenom, cl.email
             FROM contrat c
             JOIN client cl ON cl.id = c.client_id
             WHERE c.numero = ?'
        );
        $stmt->execute([$numero]);
        $contrat = $stmt->fetch() ?: null;
        if ($contrat === null) {
            $erreurs['numero'] = 'Numéro de contrat introuvable. Vérifiez votre attestation.';
        } elseif (plierTexte($contrat['nom']) !== plierTexte($nom) || plierTexte($contrat['prenom']) !== plierTexte($prenom)) {
            $erreurs['nom'] = 'Le nom ne correspond pas au titulaire du contrat.';
        } elseif (mb_strtolower((string) $contrat['email']) !== $email) {
            $erreurs['email'] = 'L\'adresse e-mail ne correspond pas au contrat.';
        }
    }

    $pieces = normaliserFichiers($fichiers);
    if (count($pieces) > 3) {
        $erreurs['pieces'] = '3 fichiers maximum.';
    }
    $valides = [];
    foreach ($pieces as $piece) {
        if ($piece['error'] !== UPLOAD_ERR_OK) {
            $erreurs['pieces'] = $piece['error'] === UPLOAD_ERR_INI_SIZE || $piece['error'] === UPLOAD_ERR_FORM_SIZE
                ? 'Fichier trop volumineux (5 Mo max).'
                : 'Format non accepté.';
            break;
        }
        if ($piece['size'] > 5 * 1024 * 1024) {
            $erreurs['pieces'] = 'Fichier trop volumineux (5 Mo max).';
            break;
        }
        $extension = strtolower(pathinfo($piece['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true) || !is_uploaded_file($piece['tmp_name'])) {
            $erreurs['pieces'] = 'Format non accepté.';
            break;
        }
        $mime = typeReelFichier($piece['tmp_name']);
        $extensionAttendue = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };
        if ($extensionAttendue === null || ($extension === 'jpeg' ? 'jpg' : $extension) !== $extensionAttendue) {
            $erreurs['pieces'] = 'Format non accepté.';
            break;
        }
        $valides[] = $piece + ['mime' => $mime, 'extension' => $extensionAttendue];
    }

    if ($erreurs !== []) {
        return ['ok' => false, 'erreurs' => $erreurs];
    }

    if (!$interne && limiteAtteinte('depot:' . ($_SERVER['REMOTE_ADDR'] ?? 'local'), 5)) {
        return ['ok' => false, 'erreurs' => ['_form' => 'Vous avez atteint la limite de 5 dépôts par heure. Réessayez plus tard.']];
    }

    $depot = new DateTimeImmutable('now');
    $limiteAr = ajouterJoursOuvrables($depot, 10)->format('Y-m-d');
    $limiteReponse = ajouterMois($depot, 2)->format('Y-m-d');
    $deplaces = [];

    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $annee = $depot->format('Y');
        $verrou = $pdo->prepare("SELECT reference FROM reclamation WHERE reference LIKE ? ORDER BY reference DESC LIMIT 1 FOR UPDATE");
        $verrou->execute(['REC-' . $annee . '-%']);
        $dernier = $verrou->fetchColumn();
        $sequence = 1;
        if (is_string($dernier) && preg_match('/(\d+)$/', $dernier, $correspondance)) {
            $sequence = (int) $correspondance[1] + 1;
        }
        $reference = sprintf('REC-%s-%05d', $annee, $sequence);

        $insert = $pdo->prepare(
            'INSERT INTO reclamation
            (reference, contrat_id, categorie, objet, description, statut, priorite, conseiller_id, canal, date_depot, date_limite_ar, date_limite_reponse, date_cloture)
            VALUES (?, ?, ?, ?, ?, \'nouvelle\', ?, NULL, ?, ?, ?, ?, NULL)'
        );
        $insert->execute([
            $reference,
            (int) $contrat['id'],
            $categorie,
            $objet,
            $description,
            prioriteDeDepart($categorie),
            $canal,
            $depot->format('Y-m-d H:i:s'),
            $limiteAr,
            $limiteReponse,
        ]);
        $id = (int) $pdo->lastInsertId();

        $detailDepot = match ($canal) {
            'telephone' => 'Réclamation saisie suite à un appel téléphonique.',
            'courrier' => 'Réclamation saisie à partir d\'un courrier.',
            default => 'Réclamation déposée par le client.',
        };
        $pdo->prepare(
            'INSERT INTO historique (reclamation_id, utilisateur_id, type_action, detail, date_action) VALUES (?, ?, \'depot\', ?, ?)'
        )->execute([$id, $agent['id'] ?? null, $detailDepot, $depot->format('Y-m-d H:i:s')]);

        $pieceStmt = $pdo->prepare(
            'INSERT INTO piece_jointe (reclamation_id, nom_original, nom_stockage, type_mime, taille, date_ajout) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($valides as $piece) {
            $stockage = bin2hex(random_bytes(16)) . '.' . $piece['extension'];
            $destination = dossierPieces() . '/' . $stockage;
            if (!move_uploaded_file($piece['tmp_name'], $destination)) {
                throw new RuntimeException('Déplacement du fichier impossible.');
            }
            $deplaces[] = $destination;
            $pieceStmt->execute([
                $id,
                mb_substr(basename($piece['name']), 0, 255),
                $stockage,
                $piece['mime'],
                $piece['size'],
                $depot->format('Y-m-d H:i:s'),
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($deplaces as $chemin) {
            if (is_file($chemin)) {
                unlink($chemin);
            }
        }
        error_log($exception->getMessage());

        return ['ok' => false, 'erreurs' => ['_form' => 'L\'enregistrement a échoué. Réessayez.']];
    }

    return [
        'ok' => true,
        'erreurs' => [],
        'id' => $id,
        'confirmation' => [
            'reference' => $reference,
            'email' => $email,
            'date_limite_ar' => $limiteAr,
            'date_limite_reponse' => $limiteReponse,
        ],
    ];
}


function echapperLike(string $valeur): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $valeur);
}

function preparerListe(array $utilisateur, array $query): array
{
    $where = ['1 = 1'];
    $params = [];

    if ($utilisateur['role'] !== 'superviseur') {
        $where[] = 'r.conseiller_id = :me';
        $params['me'] = (int) $utilisateur['id'];
    } elseif (($query['conseiller'] ?? '') !== '') {
        if ((string) $query['conseiller'] === '0') {
            $where[] = 'r.conseiller_id IS NULL';
        } elseif (ctype_digit((string) $query['conseiller'])) {
            $where[] = 'r.conseiller_id = :conseiller';
            $params['conseiller'] = (int) $query['conseiller'];
        }
    }

    if (!empty($query['statut']) && isset(STATUTS[$query['statut']])) {
        $where[] = 'r.statut = :statut';
        $params['statut'] = $query['statut'];
    } elseif (empty($query['cloturees'])) {
        $where[] = "r.statut <> 'cloturee'";
    }

    if (!empty($query['retard'])) {
        $where[] = "r.date_limite_reponse < CURDATE() AND r.statut NOT IN ('resolue', 'cloturee')";
    }
    if (!empty($query['ar_retard'])) {
        $where[] = "r.statut = 'nouvelle' AND r.date_limite_ar < CURDATE()";
    }
    if (!empty($query['categorie']) && isset(CATEGORIES[$query['categorie']])) {
        $where[] = 'r.categorie = :categorie';
        $params['categorie'] = $query['categorie'];
    }
    if (!empty($query['priorite']) && isset(PRIORITES[$query['priorite']])) {
        $where[] = sqlPrioriteEffective() . ' = :priorite';
        $params['priorite'] = $query['priorite'];
    }
    $recherche = trim((string) ($query['q'] ?? ''));
    if ($recherche !== '') {
        $where[] = "(r.reference LIKE :q1 ESCAPE '!'
            OR cl.nom LIKE :q2 ESCAPE '!'
            OR cl.prenom LIKE :q3 ESCAPE '!'
            OR CONCAT(cl.prenom, ' ', cl.nom) LIKE :q4 ESCAPE '!'
            OR CONCAT(cl.nom, ' ', cl.prenom) LIKE :q5 ESCAPE '!'
            OR c.numero LIKE :q6 ESCAPE '!')";
        $like = '%' . echapperLike($recherche) . '%';
        foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6'] as $cle) {
            $params[$cle] = $like;
        }
    }

    $triAutorise = [
        'reference' => 'r.reference',
        'client' => 'cl.nom',
        'contrat' => 'c.numero',
        'categorie' => 'r.categorie',
        'statut' => 'r.statut',
        'priorite' => sqlPrioriteEffective(),
        'echeance' => 'r.date_limite_reponse',
        'conseiller' => 'u.nom',
        'depot' => 'r.date_depot',
    ];
    $tri = isset($triAutorise[$query['tri'] ?? '']) ? (string) $query['tri'] : 'echeance';
    $direction = strtolower((string) ($query['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $ordre = $tri === 'echeance' && !isset($query['tri'])
        ? "CASE WHEN r.statut IN ('resolue', 'cloturee') THEN 1 ELSE 0 END, r.date_limite_reponse ASC, r.id ASC"
        : $triAutorise[$tri] . ' ' . $direction . ', r.id ASC';

    $demande = (int) ($query['par'] ?? 10);
    $parPage = in_array($demande, [10, 25, 50], true) ? $demande : 10;
    $page = max(1, (int) ($query['page'] ?? 1));

    $from = 'FROM reclamation r
        JOIN contrat c ON c.id = r.contrat_id
        JOIN client cl ON cl.id = c.client_id
        LEFT JOIN utilisateur u ON u.id = r.conseiller_id
        WHERE ' . implode(' AND ', $where);

    $compte = pdo()->prepare('SELECT COUNT(*) ' . $from);
    foreach ($params as $cle => $valeur) {
        $compte->bindValue(':' . $cle, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $compte->execute();
    $total = (int) $compte->fetchColumn();
    $pages = max(1, (int) ceil($total / $parPage));
    if ($page > $pages) {
        $page = $pages;
    }

    return [
        'from' => $from,
        'params' => $params,
        'ordre' => $ordre,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'par' => $parPage,
        'tri' => $tri,
        'dir' => strtolower($direction),
    ];
}

function lignesListe(array $liste, bool $tout = false): array
{
    $sql = 'SELECT r.*, c.numero, c.type_contrat, c.formule, c.date_souscription,
            cl.nom AS client_nom, cl.prenom AS client_prenom, cl.email AS client_email, cl.telephone,
            u.nom AS cons_nom, u.prenom AS cons_prenom
        ' . $liste['from'] . ' ORDER BY ' . $liste['ordre'];
    if (!$tout) {
        $sql .= ' LIMIT :limite OFFSET :decalage';
    }
    $stmt = pdo()->prepare($sql);
    foreach ($liste['params'] as $cle => $valeur) {
        $stmt->bindValue(':' . $cle, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if (!$tout) {
        $stmt->bindValue(':limite', $liste['par'], PDO::PARAM_INT);
        $stmt->bindValue(':decalage', ($liste['page'] - 1) * $liste['par'], PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function exporterCsv(array $utilisateur, array $query): never
{
    $liste = preparerListe($utilisateur, $query);
    $lignes = lignesListe($liste, true);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reclamations.csv"');
    echo "\xEF\xBB\xBF";
    $sortie = fopen('php://output', 'w');
    fputcsv($sortie, ['Référence', 'Dépôt', 'Client', 'Contrat', 'Catégorie', 'Objet', 'Statut', 'Priorité', 'Échéance', 'Conseiller', 'Canal'], ';');
    foreach ($lignes as $ligne) {
        $cellules = [
            $ligne['reference'],
            formatDate($ligne['date_depot']),
            nomPersonne($ligne, 'client_prenom', 'client_nom'),
            $ligne['numero'],
            $ligne['categorie'],
            $ligne['objet'],
            STATUTS[$ligne['statut']] ?? $ligne['statut'],
            PRIORITES[prioriteEffective($ligne)] ?? $ligne['priorite'],
            etatDelai($ligne)['label'],
            $ligne['cons_nom'] ? nomPersonne($ligne, 'cons_prenom', 'cons_nom') : 'Non attribué',
            CANAUX[$ligne['canal']] ?? $ligne['canal'],
        ];
        fputcsv($sortie, array_map(static function (string $valeur): string {
            return preg_match('/^[=+\-@]/', $valeur) === 1 ? "'" . $valeur : $valeur;
        }, $cellules), ';');
    }
    fclose($sortie);
    exit;
}

function lienListe(array $query, array $remplacer = [], bool $export = false): string
{
    $params = array_merge($query, $remplacer);
    foreach ($params as $cle => $valeur) {
        if ($valeur === null || $valeur === '' || $cle === 'export') {
            unset($params[$cle]);
        }
    }
    if ($export) {
        $params['export'] = 'csv';
    }
    $qs = http_build_query($params);

    return url('/reclamations') . ($qs !== '' ? '?' . $qs : '');
}


function bornesPeriode(string $periode): array
{
    $aujourd = new DateTimeImmutable('today');
    if ($periode === 'semaine') {
        $debut = $aujourd->modify('monday this week');
        $fin = $debut->modify('+6 days');
        $periode = 'semaine';
    } elseif ($periode === 'annee') {
        $debut = $aujourd->setDate((int) $aujourd->format('Y'), 1, 1);
        $fin = $aujourd->setDate((int) $aujourd->format('Y'), 12, 31);
        $periode = 'annee';
    } else {
        $debut = $aujourd->modify('first day of this month');
        $fin = $aujourd->modify('last day of this month');
        $periode = 'mois';
    }

    return [
        'cle' => $periode,
        'debut' => $debut->format('Y-m-d') . ' 00:00:00',
        'fin' => $fin->format('Y-m-d') . ' 23:59:59',
        'jour_debut' => $debut->format('Y-m-d'),
        'jour_fin' => $fin->format('Y-m-d'),
    ];
}

function periodePrecedente(array $bornes): array
{
    $debut = new DateTimeImmutable($bornes['jour_debut']);
    $fin = new DateTimeImmutable($bornes['jour_fin']);
    if ($bornes['cle'] === 'semaine') {
        $debut = $debut->modify('-7 days');
        $fin = $fin->modify('-7 days');
        $libelle = 'la sem. préc.';
    } elseif ($bornes['cle'] === 'annee') {
        $debut = $debut->modify('-1 year');
        $fin = $fin->modify('-1 year');
        $libelle = $debut->format('Y');
    } else {
        $debut = $debut->modify('first day of previous month');
        $fin = $debut->modify('last day of this month');
        $mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $libelle = $mois[(int) $debut->format('n') - 1];
    }

    return [
        'jour_debut' => $debut->format('Y-m-d'),
        'jour_fin' => $fin->format('Y-m-d'),
        'libelle' => $libelle,
    ];
}

function clausePerimetre(array $utilisateur): array
{
    if ($utilisateur['role'] === 'superviseur') {
        return ['sql' => '1 = 1', 'params' => []];
    }

    return ['sql' => 'r.conseiller_id = :me', 'params' => ['me' => (int) $utilisateur['id']]];
}

function indicateur(string $sql, array $params): int
{
    $stmt = pdo()->prepare($sql);
    foreach ($params as $cle => $valeur) {
        $stmt->bindValue(':' . $cle, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function statistiques(array $utilisateur, string $periode): array
{
    $bornes = bornesPeriode($periode);
    $perimetre = clausePerimetre($utilisateur);
    $params = $perimetre['params'] + [
        'debut' => $bornes['debut'],
        'fin' => $bornes['fin'],
    ];
    $base = 'FROM reclamation r WHERE ' . $perimetre['sql'] . ' AND r.date_depot >= :debut AND r.date_depot <= :fin';

    $nouvelles = indicateur('SELECT COUNT(*) ' . $base . " AND r.statut = 'nouvelle'", $params);
    $enCours = indicateur('SELECT COUNT(*) ' . $base . " AND r.statut = 'en_cours'", $params);
    $enAttente = indicateur('SELECT COUNT(*) ' . $base . " AND r.statut = 'attente_client'", $params);
    $enRetard = indicateur(
        'SELECT COUNT(*) ' . $base . " AND r.date_limite_reponse < CURDATE() AND r.statut NOT IN ('resolue', 'cloturee')",
        $params
    );
    $stmt = pdo()->prepare(
        "SELECT AVG(DATEDIFF(r.date_cloture, r.date_depot))
         FROM reclamation r
         WHERE {$perimetre['sql']}
           AND r.statut IN ('resolue', 'cloturee')
           AND r.date_cloture IS NOT NULL
           AND r.date_cloture >= :jour_debut AND r.date_cloture <= :jour_fin"
    );
    foreach (['jour_debut' => $bornes['jour_debut'], 'jour_fin' => $bornes['jour_fin']] + $perimetre['params'] as $cle => $valeur) {
        $stmt->bindValue(':' . $cle, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $moyenne = $stmt->fetchColumn();

    $prec = periodePrecedente($bornes);
    $stmtPrec = pdo()->prepare(
        "SELECT AVG(DATEDIFF(r.date_cloture, r.date_depot))
         FROM reclamation r
         WHERE {$perimetre['sql']}
           AND r.statut IN ('resolue', 'cloturee')
           AND r.date_cloture IS NOT NULL
           AND r.date_cloture >= :jour_debut AND r.date_cloture <= :jour_fin"
    );
    foreach (['jour_debut' => $prec['jour_debut'], 'jour_fin' => $prec['jour_fin']] + $perimetre['params'] as $cle => $valeur) {
        $stmtPrec->bindValue(':' . $cle, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmtPrec->execute();
    $moyennePrec = $stmtPrec->fetchColumn();
    $delta = null;
    if ($moyenne !== null && $moyenne !== false && $moyennePrec !== null && $moyennePrec !== false) {
        $delta = (int) round((float) $moyenne) - (int) round((float) $moyennePrec);
    }

    $categoriesStmt = pdo()->prepare('SELECT r.categorie, COUNT(*) AS total ' . $base . ' GROUP BY r.categorie ORDER BY total DESC');
    foreach ($params as $cle => $valeur) {
        $categoriesStmt->bindValue(':' . $cle, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $categoriesStmt->execute();

    $statutsStmt = pdo()->prepare(
        "SELECT r.statut, COUNT(*) AS total {$base} AND r.statut <> 'cloturee' GROUP BY r.statut"
    );
    foreach ($params as $cle => $valeur) {
        $statutsStmt->bindValue(':' . $cle, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statutsStmt->execute();

    $urgentsSql = "SELECT r.id, r.reference, r.objet, r.statut, r.categorie, r.date_limite_reponse, r.date_depot,
            cl.prenom AS client_prenom, cl.nom AS client_nom,
            u.prenom AS conseiller_prenom, u.nom AS conseiller_nom
         FROM reclamation r
         JOIN contrat c ON c.id = r.contrat_id
         JOIN client cl ON cl.id = c.client_id
         LEFT JOIN utilisateur u ON u.id = r.conseiller_id
         WHERE {$perimetre['sql']}
           AND r.statut NOT IN ('resolue', 'cloturee')
           AND DATEDIFF(r.date_limite_reponse, CURDATE()) <= 15
         ORDER BY r.date_limite_reponse ASC, r.id ASC
         LIMIT 8";
    $urgentsStmt = pdo()->prepare($urgentsSql);
    foreach ($perimetre['params'] as $cle => $valeur) {
        $urgentsStmt->bindValue(':' . $cle, $valeur, PDO::PARAM_INT);
    }
    $urgentsStmt->execute();

    $arRetard = indicateur(
        "SELECT COUNT(*) FROM reclamation r WHERE {$perimetre['sql']} AND r.statut = 'nouvelle' AND r.date_limite_ar < CURDATE()",
        $perimetre['params']
    );
    $nonAttribuees = 0;
    if ($utilisateur['role'] === 'superviseur') {
        $nonAttribuees = indicateur(
            "SELECT COUNT(*) FROM reclamation r WHERE r.conseiller_id IS NULL AND r.statut = 'nouvelle' AND r.date_depot >= :debut AND r.date_depot <= :fin",
            ['debut' => $bornes['debut'], 'fin' => $bornes['fin']]
        );
    }

    return [
        'periode' => $bornes,
        'nouvelles' => $nouvelles,
        'en_cours' => $enCours,
        'en_attente' => $enAttente,
        'en_retard' => $enRetard,
        'delai_moyen' => $moyenne !== null && $moyenne !== false ? (int) round((float) $moyenne) : null,
        'delai_delta' => $delta,
        'delai_compare' => $prec['libelle'],
        'categories' => $categoriesStmt->fetchAll(),
        'statuts' => $statutsStmt->fetchAll(),
        'urgents' => $urgentsStmt->fetchAll(),
        'ar_retard' => $arRetard,
        'non_attribuees' => $nonAttribuees,
    ];
}

function notifications(array $utilisateur): array
{
    if ($utilisateur['role'] === 'superviseur') {
        $compte = (int) pdo()->query("SELECT COUNT(*) FROM reclamation WHERE conseiller_id IS NULL AND statut = 'nouvelle'")->fetchColumn();
        $stmt = pdo()->query("SELECT id, reference, objet FROM reclamation WHERE conseiller_id IS NULL AND statut = 'nouvelle' ORDER BY date_depot DESC LIMIT 5");
    } else {
        $compteStmt = pdo()->prepare("SELECT COUNT(*) FROM reclamation WHERE conseiller_id = ? AND statut IN ('nouvelle', 'ar_envoye')");
        $compteStmt->execute([(int) $utilisateur['id']]);
        $compte = (int) $compteStmt->fetchColumn();
        $stmt = pdo()->prepare("SELECT id, reference, objet FROM reclamation WHERE conseiller_id = ? AND statut IN ('nouvelle', 'ar_envoye') ORDER BY date_depot DESC LIMIT 5");
        $stmt->execute([(int) $utilisateur['id']]);
    }

    return ['total' => $compte, 'lignes' => $stmt->fetchAll()];
}


function ajouterHistorique(int $reclamationId, ?int $utilisateurId, string $type, string $detail): void
{
    pdo()->prepare(
        'INSERT INTO historique (reclamation_id, utilisateur_id, type_action, detail, date_action) VALUES (?, ?, ?, ?, ?)'
    )->execute([$reclamationId, $utilisateurId, $type, $detail, date('Y-m-d H:i:s')]);
}

function traiterAction(array $utilisateur, array $reclamation, array $saisie): array
{
    $action = (string) ($saisie['action'] ?? '');
    $id = (int) $reclamation['id'];
    $superviseur = $utilisateur['role'] === 'superviseur';

    if ($reclamation['statut'] === 'cloturee' && !($superviseur && in_array($action, ['statut', 'enregistrer'], true))) {
        return ['ok' => false, 'message' => 'Ce dossier est clôturé. Seul un superviseur peut le rouvrir.'];
    }

    if ($action === 'enregistrer') {
        $messages = [];
        $nouveau = (string) ($saisie['statut'] ?? '');
        $statutChange = $nouveau !== '' && $nouveau !== (string) $reclamation['statut'];
        $conseillerId = (int) ($saisie['conseiller_id'] ?? 0);
        $attribChange = $superviseur && $conseillerId > 0 && $conseillerId !== (int) ($reclamation['conseiller_id'] ?? 0);

        if (!$statutChange && !$attribChange) {
            return ['ok' => false, 'message' => 'Aucune modification à enregistrer.'];
        }
        if ($statutChange) {
            $resultat = traiterAction($utilisateur, $reclamation, ['action' => 'statut', 'statut' => $nouveau]);
            if (!$resultat['ok']) {
                return $resultat;
            }
            $reclamation['statut'] = $nouveau;
            $messages[] = 'Statut mis à jour.';
        }
        if ($attribChange) {
            $resultat = traiterAction($utilisateur, $reclamation, ['action' => 'attribuer', 'conseiller_id' => (string) $conseillerId]);
            if (!$resultat['ok']) {
                $prefixe = $messages === [] ? '' : implode(' ', $messages) . ' ';

                return ['ok' => false, 'message' => $prefixe . $resultat['message']];
            }
            $messages[] = $resultat['message'];
        }

        return ['ok' => true, 'message' => implode(' ', $messages)];
    }

    if ($action === 'note') {
        $note = trim((string) ($saisie['note'] ?? $saisie['message'] ?? ''));
        if (mb_strlen($note) < 2 || mb_strlen($note) > 2000) {
            return ['ok' => false, 'message' => 'La note interne doit contenir entre 2 et 2 000 caractères.'];
        }
        ajouterHistorique($id, (int) $utilisateur['id'], 'note_interne', $note);

        return ['ok' => true, 'message' => 'Note interne ajoutée.'];
    }

    if ($action === 'reponse') {
        $texte = trim((string) ($saisie['reponse'] ?? $saisie['message'] ?? ''));
        if (mb_strlen($texte) < 10 || mb_strlen($texte) > 4000) {
            return ['ok' => false, 'message' => 'La réponse au client doit contenir entre 10 et 4 000 caractères.'];
        }
        if (!empty($saisie['defavorable'])) {
            $texte .= "\n\nSi cette décision ne vous satisfait pas, vous pouvez saisir gratuitement le Médiateur de l'assurance : https://www.mediation-assurance.org";
        }
        ajouterHistorique($id, (int) $utilisateur['id'], 'reponse_client', $texte);

        return ['ok' => true, 'message' => 'Réponse enregistrée. L\'envoi d\'e-mail est simulé dans cette version.'];
    }

    if ($action === 'attribuer') {
        if (!$superviseur) {
            return ['ok' => false, 'message' => 'Seul un superviseur peut attribuer un dossier.'];
        }
        $conseillerId = (int) ($saisie['conseiller_id'] ?? 0);
        $stmt = pdo()->prepare("SELECT id, nom, prenom FROM utilisateur WHERE id = ? AND role = 'conseiller' AND actif = 1");
        $stmt->execute([$conseillerId]);
        $conseiller = $stmt->fetch();
        if (!$conseiller) {
            return ['ok' => false, 'message' => 'Choisissez un conseiller actif.'];
        }
        if ((int) $reclamation['conseiller_id'] === $conseillerId) {
            return ['ok' => false, 'message' => 'Ce dossier est déjà attribué à ce conseiller.'];
        }
        pdo()->prepare('UPDATE reclamation SET conseiller_id = ? WHERE id = ?')->execute([$conseillerId, $id]);
        $nom = nomPersonne($conseiller);
        ajouterHistorique($id, (int) $utilisateur['id'], 'attribution', 'Dossier attribué à ' . $nom . '.');

        return ['ok' => true, 'message' => 'Dossier attribué à ' . $nom . '.'];
    }

    if ($action === 'statut') {
        $nouveau = (string) ($saisie['statut'] ?? '');
        $autorises = transitionsAutorisees((string) $reclamation['statut'], $superviseur);
        if (!in_array($nouveau, $autorises, true)) {
            return ['ok' => false, 'message' => 'Cette transition de statut n\'est pas autorisée.'];
        }
        if ($nouveau === 'resolue') {
            $compte = pdo()->prepare("SELECT COUNT(*) FROM historique WHERE reclamation_id = ? AND type_action = 'reponse_client'");
            $compte->execute([$id]);
            if ((int) $compte->fetchColumn() === 0) {
                return ['ok' => false, 'message' => 'Envoyez d\'abord une réponse au client avant de passer le dossier en Résolue.'];
            }
        }
        $dateCloture = $reclamation['date_cloture'] ?: null;
        if (in_array($nouveau, ['resolue', 'cloturee'], true)) {
            $dateCloture = $dateCloture ?: date('Y-m-d');
        }
        if ($nouveau === 'en_cours' && in_array($reclamation['statut'], ['resolue', 'cloturee'], true)) {
            $dateCloture = null;
        }
        $maj = pdo()->prepare('UPDATE reclamation SET statut = ?, date_cloture = ? WHERE id = ?');
        $maj->bindValue(1, $nouveau);
        $maj->bindValue(2, $dateCloture, $dateCloture === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $maj->bindValue(3, $id, PDO::PARAM_INT);
        $maj->execute();
        $detail = $nouveau === 'en_cours' && in_array($reclamation['statut'], ['resolue', 'cloturee'], true)
            ? 'Dossier rouvert.'
            : libelleChangement($nouveau);
        ajouterHistorique($id, (int) $utilisateur['id'], 'changement_statut', $detail);

        return ['ok' => true, 'message' => 'Statut mis à jour.'];
    }

    return ['ok' => false, 'message' => 'Action inconnue.'];
}

function chargerReclamation(int $id): ?array
{
    $stmt = pdo()->prepare(
        'SELECT r.*, c.numero, c.type_contrat, c.formule, c.date_souscription,
                cl.nom AS client_nom, cl.prenom AS client_prenom, cl.email AS client_email, cl.telephone,
                u.nom AS cons_nom, u.prenom AS cons_prenom
         FROM reclamation r
         JOIN contrat c ON c.id = r.contrat_id
         JOIN client cl ON cl.id = c.client_id
         LEFT JOIN utilisateur u ON u.id = r.conseiller_id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    $ligne = $stmt->fetch();

    return $ligne ?: null;
}

function cheminPieceSure(string $nomStockage): ?string
{
    if (preg_match('/^[a-f0-9]{32}\.(pdf|jpe?g|png)$/i', $nomStockage) !== 1) {
        return null;
    }
    $dossier = realpath(dossierPieces());
    $fichier = realpath(dossierPieces() . '/' . $nomStockage);
    if ($dossier === false || $fichier === false || !str_starts_with($fichier, $dossier . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $fichier;
}