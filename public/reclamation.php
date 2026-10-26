<?php

declare(strict_types=1);

session_start();
date_default_timezone_set('Europe/Paris');

function e(?string $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function envFichier(): array
{
    $config = [
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3306',
        'DB_NAME' => 'reclamassur',
        'DB_USER' => 'root',
        'DB_PASS' => '',
    ];
    $chemin = dirname(__DIR__) . '/.env';
    if (!is_file($chemin)) {
        return $config;
    }
    $lignes = file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || str_starts_with($ligne, '#') || !str_contains($ligne, '=')) {
            continue;
        }
        [$cle, $valeur] = explode('=', $ligne, 2);
        $config[trim($cle)] = trim($valeur, " \t\"'");
    }

    return $config;
}

function pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $config = envFichier();
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['DB_HOST'], $config['DB_PORT'], $config['DB_NAME']),
        $config['DB_USER'],
        $config['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    return $pdo;
}

function jeton(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function joursOuvrables(DateTimeImmutable $date, int $jours): DateTimeImmutable
{
    while ($jours > 0) {
        $date = $date->modify('+1 day');
        if ((int) $date->format('N') < 6) {
            $jours--;
        }
    }

    return $date;
}

function ajouterMois(DateTimeImmutable $date, int $mois): DateTimeImmutable
{
    $jour = (int) $date->format('j');
    $cible = $date->modify('first day of this month')->modify('+' . $mois . ' month');
    $dernier = (int) $cible->format('t');

    return $cible->setDate((int) $cible->format('Y'), (int) $cible->format('n'), min($jour, $dernier));
}

const CATEGORIES = [
    'Refus de prise en charge' => 'haute',
    "Retard d'indemnisation" => 'haute',
    'Contestation de cotisation / tarif' => 'moyenne',
    'Résiliation' => 'moyenne',
    'Erreur administrative' => 'moyenne',
    'Qualité de service / accueil' => 'basse',
    'Autre' => 'basse',
];

const TYPES = ['auto' => 'Auto', 'habitation' => 'Habitation', 'sante' => 'Santé'];

function typeDepuisNumero(string $numero): ?string
{
    return match (strtoupper(substr($numero, 0, 3))) {
        'AUT' => 'auto',
        'HAB' => 'habitation',
        'SAN' => 'sante',
        default => null,
    };
}

function valeur(array $valeurs, string $cle): string
{
    return (string) ($valeurs[$cle] ?? '');
}

function entete(string $titre): void
{
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre) ?> — ReclamAssur</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="app.css" rel="stylesheet">
</head>
<body>
<header class="entete">
    <a class="marque" href="reclamation.php"><i class="bi bi-shield-fill bouclier" aria-hidden="true"></i> Assurium</a>
    <nav aria-label="Navigation">
        <a class="lien" href="reclamation.php">Nos assurances</a>
        <a class="lien" href="reclamation.php">Sinistres</a>
        <a class="lien" href="reclamation.php">Contact</a>
        <a class="btn-collaborateur" href="connexion.php">Espace collaborateur</a>
    </nav>
</header>
<main class="page" id="contenu">
    <?php
}

function pied(): void
{
    ?>
</main>
<script src="app.js"></script>
</body>
</html>
    <?php
}

if (isset($_GET['politique'])) {
    entete('Politique de confidentialité');
    ?>
    <article class="prose">
        <h1>Politique de confidentialité</h1>
        <p>Assurium collecte le nom, le prénom, l'e-mail, le téléphone facultatif, le numéro de contrat, la catégorie, l'objet, la description et les pièces jointes. Aucun détail médical n'est demandé.</p>
        <p>Ces données servent uniquement à traiter la réclamation. Le dossier est conservé 5 ans après sa clôture, puis anonymisé.</p>
        <p><a href="reclamation.php">Retour au formulaire</a></p>
    </article>
    <?php
    pied();
    exit;
}

function afficherConfirmation(?array $confirmation): void
{
    entete('Réclamation enregistrée');
    ?>
    <section class="confirmation">
        <?php if (!$confirmation): ?>
            <div class="carte-confirmation">
                <h1>Aucune réclamation à afficher</h1>
                <p><a href="reclamation.php">Déposer une réclamation</a></p>
            </div>
        <?php else: ?>
            <div class="carte-confirmation">
                <p class="check" aria-hidden="true"><i class="bi bi-check-lg"></i></p>
                <h1>Réclamation enregistrée</h1>
                <p>Votre numéro de référence :</p>
                <p class="reference"><?= e($confirmation['reference']) ?></p>
                <p class="aide">Un e-mail de confirmation a été envoyé à <?= e($confirmation['email']) ?>.</p>
            </div>
        <?php endif; ?>
    </section>
    <?php
    pied();
}

if (isset($_GET['envoyee'])) {
    afficherConfirmation($_SESSION['confirmation'] ?? null);
    exit;
}

$valeurs = [
    'nom' => '', 'prenom' => '', 'email' => '', 'telephone' => '',
    'numero' => '', 'type_contrat' => '', 'categorie' => '', 'objet' => '', 'description' => '',
];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jeton = (string) ($_POST['csrf'] ?? '');
    if ($jeton === '' || !hash_equals(jeton(), $jeton)) {
        $erreurs['_form'] = 'Le formulaire a expiré. Rechargez la page.';
    }
    foreach ($valeurs as $cle => $vide) {
        $valeurs[$cle] = trim((string) ($_POST[$cle] ?? ''));
    }
    $nom = $valeurs['nom'];
    $prenom = $valeurs['prenom'];
    $email = mb_strtolower($valeurs['email']);
    $telephone = $valeurs['telephone'];
    $numero = strtoupper($valeurs['numero']);
    $type = $valeurs['type_contrat'];
    $categorie = $valeurs['categorie'];
    $objet = $valeurs['objet'];
    $description = $valeurs['description'];
    $valeurs['email'] = $email;
    $valeurs['numero'] = $numero;

    if (trim((string) ($_POST['site_web'] ?? '')) !== '') {
        $erreurs['_form'] = 'Votre envoi n\'a pas pu être accepté.';
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
    $typeNumero = typeDepuisNumero($numero);
    if (!isset(TYPES[$type]) || ($typeNumero !== null && $typeNumero !== $type)) {
        $erreurs['type_contrat'] = 'Le type ne correspond pas au numéro de contrat.';
    }
    if (!isset(CATEGORIES[$categorie])) {
        $erreurs['categorie'] = 'Choisissez une catégorie.';
    }
    if ($objet === '' || mb_strlen($objet) > 150) {
        $erreurs['objet'] = 'L\'objet est obligatoire.';
    }
    if (mb_strlen($description) < 20 || mb_strlen($description) > 2000) {
        $erreurs['description'] = 'Décrivez votre réclamation (20 caractères minimum).';
    }
    if (empty($_POST['consentement'])) {
        $erreurs['consentement'] = 'Votre accord est nécessaire pour traiter la réclamation.';
    }

    $contrat = null;
    if (!isset($erreurs['numero']) && !isset($erreurs['_form'])) {
        try {
            $stmt = pdo()->prepare('SELECT id FROM contrat WHERE numero = ?');
            $stmt->execute([$numero]);
            $contrat = $stmt->fetch() ?: null;
            if ($contrat === null) {
                $erreurs['numero'] = 'Numéro de contrat introuvable. Vérifiez votre attestation.';
            }
        } catch (Throwable) {
            $erreurs['_form'] = 'La base de données ne répond pas. Vérifiez que MySQL est démarré.';
        }
    }

    $pieces = [];
    $fichiers = $_FILES['pieces'] ?? null;
    if (is_array($fichiers) && isset($fichiers['name']) && is_array($fichiers['name'])) {
        foreach ($fichiers['name'] as $index => $nomFichier) {
            $code = (int) ($fichiers['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($code === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $pieces[] = [
                'name' => (string) $nomFichier,
                'tmp_name' => (string) ($fichiers['tmp_name'][$index] ?? ''),
                'error' => $code,
                'size' => (int) ($fichiers['size'][$index] ?? 0),
            ];
        }
    }
    if (count($pieces) > 3) {
        $erreurs['pieces'] = '3 fichiers maximum.';
    }
    $valides = [];
    foreach ($pieces as $piece) {
        if ($piece['error'] !== UPLOAD_ERR_OK || $piece['size'] > 5 * 1024 * 1024) {
            $erreurs['pieces'] = $piece['size'] > 5 * 1024 * 1024 || in_array($piece['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Fichier trop volumineux (5 Mo max).'
                : 'Format non accepté.';
            break;
        }
        $extension = strtolower(pathinfo($piece['name'], PATHINFO_EXTENSION));
        $contenu = (string) file_get_contents($piece['tmp_name'], false, null, 0, 8);
        $mime = null;
        if (str_starts_with($contenu, '%PDF')) {
            $mime = 'application/pdf';
        } elseif (str_starts_with($contenu, "\xFF\xD8\xFF")) {
            $mime = 'image/jpeg';
        } elseif (str_starts_with($contenu, "\x89PNG\r\n\x1A\n")) {
            $mime = 'image/png';
        }
        $extensionAttendue = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };
        if ($extensionAttendue === null || ($extension === 'jpeg' ? 'jpg' : $extension) !== $extensionAttendue || !is_uploaded_file($piece['tmp_name'])) {
            $erreurs['pieces'] = 'Format non accepté.';
            break;
        }
        $valides[] = $piece + ['mime' => $mime, 'extension' => $extensionAttendue];
    }

    if ($erreurs === [] && $contrat !== null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'local';
        $journal = dirname(__DIR__) . '/uploads/.rate.json';
        $taux = is_file($journal) ? json_decode((string) file_get_contents($journal), true) : [];
        $taux = is_array($taux) ? $taux : [];
        $cle = 'depot:' . $ip;
        $maintenant = time();
        $hits = array_values(array_filter($taux[$cle] ?? [], static fn ($moment) => $moment > $maintenant - 3600));
        if (count($hits) >= 5) {
            $erreurs['_form'] = 'Vous avez atteint la limite de 5 dépôts par heure. Réessayez plus tard.';
        } else {
            $depot = new DateTimeImmutable('now');
            $limiteAr = joursOuvrables($depot, 10)->format('Y-m-d');
            $limiteReponse = ajouterMois($depot, 2)->format('Y-m-d');
            $db = pdo();
            $db->beginTransaction();
            $deplaces = [];
            try {
                $annee = $depot->format('Y');
                $verrou = $db->prepare('SELECT reference FROM reclamation WHERE reference LIKE ? ORDER BY reference DESC LIMIT 1 FOR UPDATE');
                $verrou->execute(['REC-' . $annee . '-%']);
                $dernier = $verrou->fetchColumn();
                $sequence = 1;
                if (is_string($dernier) && preg_match('/(\d+)$/', $dernier, $correspondance)) {
                    $sequence = (int) $correspondance[1] + 1;
                }
                $reference = sprintf('REC-%s-%05d', $annee, $sequence);
                $insert = $db->prepare(
                    'INSERT INTO reclamation
                    (reference, contrat_id, categorie, objet, description, statut, priorite, conseiller_id, canal, date_depot, date_limite_ar, date_limite_reponse, date_cloture)
                    VALUES (?, ?, ?, ?, ?, \'nouvelle\', ?, NULL, \'web\', ?, ?, ?, NULL)'
                );
                $insert->execute([
                    $reference,
                    (int) $contrat['id'],
                    $categorie,
                    $objet,
                    $description,
                    CATEGORIES[$categorie],
                    $depot->format('Y-m-d H:i:s'),
                    $limiteAr,
                    $limiteReponse,
                ]);
                $id = (int) $db->lastInsertId();
                $db->prepare('INSERT INTO historique (reclamation_id, utilisateur_id, type_action, detail, date_action) VALUES (?, NULL, \'depot\', ?, ?)')
                    ->execute([$id, 'Réclamation déposée par le client.', $depot->format('Y-m-d H:i:s')]);
                $dossier = dirname(__DIR__) . '/uploads';
                if (!is_dir($dossier)) {
                    mkdir($dossier, 0775, true);
                }
                $pieceStmt = $db->prepare('INSERT INTO piece_jointe (reclamation_id, nom_original, nom_stockage, type_mime, taille, date_ajout) VALUES (?, ?, ?, ?, ?, ?)');
                foreach ($valides as $piece) {
                    $stockage = bin2hex(random_bytes(16)) . '.' . $piece['extension'];
                    $destination = $dossier . '/' . $stockage;
                    if (!move_uploaded_file($piece['tmp_name'], $destination)) {
                        throw new RuntimeException('Fichier non déplacé.');
                    }
                    $deplaces[] = $destination;
                    $pieceStmt->execute([$id, mb_substr(basename($piece['name']), 0, 255), $stockage, $piece['mime'], $piece['size'], $depot->format('Y-m-d H:i:s')]);
                }
                $db->commit();
                $hits[] = $maintenant;
                $taux[$cle] = $hits;
                @file_put_contents($journal, json_encode($taux));
                $confirmation = ['reference' => $reference, 'email' => $email];
                $_SESSION['confirmation'] = $confirmation;
                afficherConfirmation($confirmation);
                exit;
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                foreach ($deplaces as $chemin) {
                    if (is_file($chemin)) {
                        unlink($chemin);
                    }
                }
                $erreurs['_form'] = 'L\'enregistrement a échoué. Réessayez.';
            }
        }
    }
}

entete('Déposer une réclamation');
?>
<section class="banniere">
    <h1>Déposer une réclamation</h1>
    <p>Vous n'êtes pas satisfait(e) de nos services ? Dites-le-nous : nous nous engageons à vous répondre.</p>
</section>
<div class="depot">
    <aside>
        <section class="carte">
            <h2>Comment ça marche ?</h2>
            <ol class="etapes">
                <li><span class="numero">1</span><span><strong>Vous décrivez votre réclamation</strong><br><span class="aide">en quelques minutes.</span></span></li>
                <li><span class="numero">2</span><span><strong>Accusé de réception</strong><br><span class="aide">sous 10 jours ouvrables.</span></span></li>
                <li><span class="numero">3</span><span><strong>Réponse définitive</strong><br><span class="aide">sous 2 mois maximum.</span></span></li>
            </ol>
        </section>
        <section class="carte carte-bleue">
            <h2>Besoin d'aide ?</h2>
            <p class="service">Service Réclamations</p>
            <p class="telephone">01 23 45 67 89</p>
            <p class="aide">du lundi au vendredi, 9 h – 18 h</p>
        </section>
        <section class="carte carte-bleue">
            <h2>Médiateur de l'assurance</h2>
           <p>Si notre réponse ne vous satisfait pas, vous pourrez saisir gratuitement le Médiateur de l'assurance.</p>
        </section>
    </aside>
    <section class="formulaire">
        <h2>Votre réclamation</h2>
        <p class="aide">Les champs marqués d'un * sont obligatoires.</p>
        <?php if (!empty($erreurs['_form'])): ?><p class="alerte-form" role="alert"><?= e($erreurs['_form']) ?></p><?php endif; ?>
        <?php if ($erreurs !== [] && empty($erreurs['_form'])): ?><p class="alerte-form" role="alert">Le formulaire contient des erreurs. Les champs déjà remplis sont conservés.</p><?php endif; ?>
        <form method="post" action="reclamation.php" enctype="multipart/form-data" id="form-reclamation" novalidate>
            <input type="hidden" name="csrf" value="<?= e(jeton()) ?>">
            <div class="pot" aria-hidden="true">
                <label for="site_web">Site web</label>
                <input id="site_web" name="site_web" tabindex="-1" autocomplete="off">
            </div>
            <div class="grille">
                <div class="champ">
                    <label for="nom">Nom <span class="requis">*</span></label>
                    <input id="nom" name="nom" value="<?= e(valeur($valeurs, 'nom')) ?>" maxlength="50" required autocomplete="family-name" class="<?= isset($erreurs['nom']) ? 'invalide' : '' ?>">
                    <?php if (isset($erreurs['nom'])): ?><p class="erreur" id="err-nom"><?= e($erreurs['nom']) ?></p><?php endif; ?>
                </div>
                <div class="champ">
                    <label for="prenom">Prénom <span class="requis">*</span></label>
                    <input id="prenom" name="prenom" value="<?= e(valeur($valeurs, 'prenom')) ?>" maxlength="50" required autocomplete="given-name" class="<?= isset($erreurs['prenom']) ? 'invalide' : '' ?>">
                    <?php if (isset($erreurs['prenom'])): ?><p class="erreur" id="err-prenom"><?= e($erreurs['prenom']) ?></p><?php endif; ?>
                </div>
                <div class="champ">
                    <label for="email">E-mail <span class="requis">*</span></label>
                    <input id="email" name="email" type="email" value="<?= e(valeur($valeurs, 'email')) ?>" required autocomplete="email" class="<?= isset($erreurs['email']) ? 'invalide' : '' ?>">
                    <?php if (isset($erreurs['email'])): ?><p class="erreur" id="err-email"><?= e($erreurs['email']) ?></p><?php endif; ?>
                </div>
                <div class="champ">
                    <label for="telephone">Téléphone</label>
                    <input id="telephone" name="telephone" value="<?= e(valeur($valeurs, 'telephone')) ?>" inputmode="numeric" autocomplete="tel" class="<?= isset($erreurs['telephone']) ? 'invalide' : '' ?>">
                    <?php if (isset($erreurs['telephone'])): ?><p class="erreur" id="err-telephone"><?= e($erreurs['telephone']) ?></p><?php endif; ?>
                </div>
                <div class="champ">
                    <label for="numero">N° de contrat <span class="requis">*</span></label>
                    <input id="numero" name="numero" value="<?= e(valeur($valeurs, 'numero')) ?>" placeholder="HAB-000000" maxlength="12" required class="<?= isset($erreurs['numero']) ? 'invalide' : '' ?>">
                    <?php if (isset($erreurs['numero'])): ?>
                        <p class="erreur" id="err-numero"><?= e($erreurs['numero']) ?></p>
                    <?php else: ?>
                        <p class="aide" id="aide-numero">Format attendu : HAB-000000 (6 chiffres)</p>
                    <?php endif; ?>
                </div>
                <div class="champ">
                    <label for="type_contrat">Type de contrat <span class="requis">*</span></label>
                    <select id="type_contrat" name="type_contrat" required class="<?= isset($erreurs['type_contrat']) ? 'invalide' : '' ?>">
                        <option value="">Choisir</option>
                        <?php foreach (TYPES as $cle => $libelle): ?>
                            <option value="<?= e($cle) ?>" <?= valeur($valeurs, 'type_contrat') === $cle ? 'selected' : '' ?>><?= e($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erreurs['type_contrat'])): ?><p class="erreur" id="err-type_contrat"><?= e($erreurs['type_contrat']) ?></p><?php endif; ?>
                    <p id="alerte-sante" class="alerte <?= valeur($valeurs, 'type_contrat') === 'sante' ? '' : 'cache' ?>">Ne transmettez aucun détail médical : pas de diagnostic, pas de compte rendu, pas de numéro de sécurité sociale.</p>
                </div>
                <div class="champ">
                    <label for="categorie">Catégorie <span class="requis">*</span></label>
                    <select id="categorie" name="categorie" required class="<?= isset($erreurs['categorie']) ? 'invalide' : '' ?>">
                        <option value="">Choisir</option>
                        <?php foreach (CATEGORIES as $libelle => $priorite): ?>
                            <option value="<?= e($libelle) ?>" <?= valeur($valeurs, 'categorie') === $libelle ? 'selected' : '' ?>><?= e($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erreurs['categorie'])): ?><p class="erreur" id="err-categorie"><?= e($erreurs['categorie']) ?></p><?php endif; ?>
                </div>
                <div class="champ">
                    <label for="objet">Objet <span class="requis">*</span></label>
                    <input id="objet" name="objet" value="<?= e(valeur($valeurs, 'objet')) ?>" maxlength="150" required class="<?= isset($erreurs['objet']) ? 'invalide' : '' ?>">
                    <?php if (isset($erreurs['objet'])): ?><p class="erreur" id="err-objet"><?= e($erreurs['objet']) ?></p><?php endif; ?>
                </div>
                <div class="champ champ-large">
                    <label for="description">Description <span class="requis">*</span></label>
                    <textarea id="description" name="description" maxlength="2000" required class="<?= isset($erreurs['description']) ? 'invalide' : '' ?>"><?= e(valeur($valeurs, 'description')) ?></textarea>
                    <p class="aide compteur"><span id="compteur"><?= (int) mb_strlen(valeur($valeurs, 'description')) ?></span> / 2000</p>
                    <?php if (isset($erreurs['description'])): ?><p class="erreur" id="err-description"><?= e($erreurs['description']) ?></p><?php endif; ?>
                </div>
                <div class="champ champ-large">
                    <div class="drop" id="dropzone">
                        <p><label for="pieces">Pièces jointes</label> <span class="aide">(PDF, JPG, PNG — 5 Mo max par fichier, 3 fichiers max)</span></p>
                        <p class="aide">Glissez vos fichiers ici ou <label for="pieces" class="parcourir">parcourir</label></p>
                        <input id="pieces" class="fichier-cache" name="pieces[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                        <ul id="liste-fichiers" class="fichiers"></ul>
                        <?php if (isset($erreurs['pieces'])): ?><p class="erreur" id="err-pieces"><?= e($erreurs['pieces']) ?></p><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="consentement">
                <input id="consentement" name="consentement" type="checkbox" value="1" <?= !empty($_POST['consentement']) ? 'checked' : '' ?> required>
                <label for="consentement">J'accepte que mes données soient utilisées pour traiter ma réclamation. <a href="reclamation.php?politique=1">En savoir plus</a></label>
            </div>
            <?php if (isset($erreurs['consentement'])): ?><p class="erreur" id="err-consentement"><?= e($erreurs['consentement']) ?></p><?php endif; ?>
            <button class="envoyer" type="submit" id="envoyer">Envoyer ma réclamation</button>
        </form>
    </section>
</div>
<?php
pied();