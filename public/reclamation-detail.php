<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/auth.php';

$user = exigerRole('conseiller', 'superviseur');
$superviseur = estSuperviseur($user);

if (isset($_GET['piece'])) {
    $pieceId = (int) $_GET['piece'];
    $stmtPiece = pdo()->prepare(
        'SELECT p.*, r.conseiller_id
         FROM piece_jointe p
         JOIN reclamation r ON r.id = p.reclamation_id
         WHERE p.id = ?'
    );
    $stmtPiece->execute([$pieceId]);
    $pieceFichier = $stmtPiece->fetch();
    if (!$pieceFichier) {
        afficherErreur('Fichier introuvable', 'Cette pièce jointe n\'existe pas.', 404);
    }
    if (!peutVoirReclamation($user, $pieceFichier)) {
        afficherErreur('Accès refusé', 'Vous ne pouvez pas télécharger ce fichier.', 403);
    }
    $chemin = cheminPieceSure((string) $pieceFichier['nom_stockage']);
    if ($chemin === null) {
        afficherErreur('Fichier indisponible', 'Le fichier n\'est plus présent sur le serveur.', 404);
    }
    ajouterHistorique((int) $pieceFichier['reclamation_id'], (int) $user['id'], 'telechargement', 'Téléchargement de ' . $pieceFichier['nom_original'] . '.');
    $nomFichier = str_replace(['"', "\r", "\n"], '', (string) $pieceFichier['nom_original']);
    $mime = in_array($pieceFichier['type_mime'], ['application/pdf', 'image/jpeg', 'image/png'], true)
        ? $pieceFichier['type_mime']
        : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
    header('Content-Length: ' . (string) filesize($chemin));
    header('X-Content-Type-Options: nosniff');
    readfile($chemin);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$reclamation = $id > 0 ? chargerReclamation($id) : null;
if ($reclamation === null) {
    afficherErreur('Dossier introuvable', 'Cette réclamation n\'existe pas.', 404);
}
if (!peutVoirReclamation($user, $reclamation)) {
    afficherErreur('Accès refusé', 'Ce dossier ne vous est pas attribué.', 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();
    $resultat = traiterAction($user, $reclamation, $_POST);
    flash($resultat['ok'] ? 'success' : 'danger', $resultat['message']);
    rediriger('reclamation-detail.php?id=' . $id);
}

$pieces = pdo()->prepare('SELECT * FROM piece_jointe WHERE reclamation_id = ? ORDER BY date_ajout');
$pieces->execute([$id]);
$pieces = $pieces->fetchAll();

$historique = pdo()->prepare(
    'SELECT h.*, u.nom, u.prenom
     FROM historique h
     LEFT JOIN utilisateur u ON u.id = h.utilisateur_id
     WHERE h.reclamation_id = ?
     ORDER BY h.date_action ASC, h.id ASC'
);
$historique->execute([$id]);
$historique = $historique->fetchAll();

$transitions = transitionsAutorisees($reclamation['statut'], $superviseur);
$lecture = $reclamation['statut'] === 'cloturee';
$etat = etatDelai($reclamation);
$priorite = prioriteEffective($reclamation);
$retour = 'reclamations.php' . (!empty($_SESSION['liste_qs']) ? '?' . $_SESSION['liste_qs'] : '');
$conseillers = $superviseur ? conseillersActifs() : [];
$notices = notifications($user);
$initiales = mb_strtoupper(mb_substr($user['prenom'], 0, 1) . mb_substr($user['nom'], 0, 1));
$flash = prendreFlash();

$canauxDetail = [
    'web' => 'formulaire web',
    'telephone' => 'téléphone',
    'courrier' => 'courrier',
];
$canalLibelle = $canauxDetail[$reclamation['canal']] ?? $reclamation['canal'];
$anneeClient = (new DateTimeImmutable($reclamation['date_souscription']))->format('Y');
$limiteReponse = formatDate($reclamation['date_limite_reponse']);
if ($etat['code'] === 'traitee') {
    $echeanceLabel = 'Traitée';
    $echeanceClasse = 'traitee';
} elseif ($etat['code'] === 'retard') {
    $echeanceLabel = 'Échéance : En retard';
    $echeanceClasse = 'retard';
} elseif ($etat['code'] === 'proche') {
    $echeanceLabel = 'Échéance : ' . $limiteReponse . ' · ' . $etat['label'];
    $echeanceClasse = 'proche';
} else {
    $echeanceLabel = 'Échéance : ' . $limiteReponse;
    $echeanceClasse = 'ok';
}

$optionsStatut = [];
if ($transitions !== []) {
    $optionsStatut[$reclamation['statut']] = STATUTS[$reclamation['statut']] ?? $reclamation['statut'];
    foreach ($transitions as $statut) {
        $optionsStatut[$statut] = STATUTS[$statut] ?? $statut;
    }
}
$peutAttribuer = $superviseur && !$lecture;
$peutTraiter = $optionsStatut !== [] || $peutAttribuer;
$libelleBouton = 'Enregistrer';
if ($lecture) {
    $libelleBouton = 'Rouvrir le dossier';
}

function telephoneLisible(?string $telephone): string
{
    $chiffres = preg_replace('/\D+/', '', (string) $telephone) ?? '';
    if (strlen($chiffres) === 10) {
        return trim(chunk_split($chiffres, 2, ' '));
    }

    return trim((string) $telephone) !== '' ? trim((string) $telephone) : '';
}

function auteurCourt(?string $prenom, ?string $nom): string
{
    $prenom = trim((string) $prenom);
    $nom = trim((string) $nom);
    if ($prenom === '' && $nom === '') {
        return '';
    }
    $initiale = $nom !== '' ? ' ' . mb_strtoupper(mb_substr($nom, 0, 1)) . '.' : '';

    return trim($prenom . $initiale);
}

function presenterHistorique(array $ligne, string $canalLibelle): array
{
    $type = (string) $ligne['type_action'];
    $detail = trim((string) $ligne['detail']);
    $auteur = $ligne['utilisateur_id'] ? auteurCourt($ligne['prenom'] ?? '', $ligne['nom'] ?? '') : '';
    $par = $auteur !== '' ? 'par ' . $auteur : 'par le client';
    $point = 'bleu';
    $note = false;
    $etiquette = '';
    $titre = rtrim($detail, '.');
    $corps = '';

    if ($type === 'depot') {
        $titre = 'Réclamation déposée';
        if ($auteur === '') {
            $par = 'par le client (' . $canalLibelle . ')';
        }
    } elseif ($type === 'attribution' && preg_match('/attribué à (.+?)\.?$/u', $detail, $correspondance) === 1) {
        $titre = 'Attribuée à ' . $correspondance[1];
    } elseif ($type === 'changement_statut') {
        $point = 'orange';
        if (preg_match('/passé au statut (.+)$/u', $titre, $correspondance) === 1) {
            $titre = 'Statut : ' . $correspondance[1];
        }
    } elseif ($type === 'note_interne') {
        $point = 'orange';
        $note = true;
        $etiquette = 'Note interne';
        $titre = '« ' . $detail . ' »';
    } elseif ($type === 'reponse_client') {
        $titre = 'Réponse envoyée au client';
        $corps = $detail;
    }

    $date = new DateTimeImmutable((string) $ligne['date_action']);

    return [
        'heure' => $date->format('d/m · H:i'),
        'iso' => $date->format('c'),
        'point' => $point,
        'note' => $note,
        'etiquette' => $etiquette,
        'titre' => $titre,
        'corps' => $corps,
        'par' => $par,
    ];
}

function classePiece(string $nom): string
{
    $extension = strtolower(pathinfo($nom, PATHINFO_EXTENSION));

    return in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true) ? $extension : 'autre';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($reclamation['reference']) ?> — ReclamAssur</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="app.css" rel="stylesheet">
</head>
<body class="page-app">
<a class="skip" href="#contenu">Aller au contenu</a>
<div class="app">
    <aside class="menu-app">
        <p class="logo-app"><span class="badge-bouclier"><i class="bi bi-shield-fill" aria-hidden="true"></i></span><span>Assurium<br><small>ReclamAssur</small></span></p>
        <nav aria-label="Espace collaborateur">
            <a href="tableau-de-bord.php"><i class="bi bi-speedometer2" aria-hidden="true"></i> Tableau de bord</a>
            <a class="actif" href="reclamations.php" aria-current="page"><i class="bi bi-inboxes" aria-hidden="true"></i> Réclamations</a>
            <?php if ($superviseur): ?>
                <a href="tableau-de-bord.php#utilisateurs"><i class="bi bi-people" aria-hidden="true"></i> Utilisateurs</a>
            <?php endif; ?>
        </nav>
        <a class="sortir" href="deconnexion.php"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Déconnexion</a>
    </aside>
    <div class="corps-app">
        <header class="barre-app">
            <form class="recherche-app" method="get" action="reclamations.php" role="search">
                <label class="visually-hidden" for="q-rapide">Recherche par référence ou nom</label>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="q-rapide" name="q" type="search" placeholder="Rechercher une référence, un client...">
            </form>
            <div class="barre-droite">
                <details class="notif">
                    <summary aria-label="Notifications, <?= (int) $notices['total'] ?> élément(s)">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                        <?php if ($notices['total'] > 0): ?><span><?= (int) $notices['total'] ?></span><?php endif; ?>
                    </summary>
                    <div class="notif-liste">
                        <p><?= $superviseur ? 'Nouvelles non attribuées' : 'Dossiers à prendre en charge' ?></p>
                        <?php if ($notices['lignes'] === []): ?>
                            <p class="aide">Rien de nouveau.</p>
                        <?php else: ?>
                            <?php foreach ($notices['lignes'] as $notice): ?>
                                <a href="reclamation-detail.php?id=<?= (int) $notice['id'] ?>"><span class="mono"><?= e($notice['reference']) ?></span> <?= e($notice['objet']) ?></a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
                <div class="qui">
                    <span class="avatar" aria-hidden="true"><?= e($initiales) ?></span>
                    <span><strong><?= e($user['prenom']) ?> <?= e(mb_substr($user['nom'], 0, 1)) ?>.</strong><small><?= $superviseur ? 'Superviseur' : 'Conseiller' ?></small></span>
                </div>
            </div>
        </header>
        <main id="contenu" class="contenu-app">
            <?php if ($flash): ?>
                <div class="banniere-flash <?= $flash['type'] === 'success' ? 'ok' : 'ko' ?>" role="status">
                    <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>" aria-hidden="true"></i>
                    <span><?= e($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <nav class="fil" aria-label="Fil d'Ariane">
                <a href="<?= e($retour) ?>">Réclamations</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?= e($reclamation['reference']) ?></span>
            </nav>

            <header class="entete-dossier">
                <h1><span class="ref-titre"><?= e($reclamation['reference']) ?></span> · <?= e($reclamation['objet']) ?></h1>
                <p class="badges-dossier">
                    <?= badgeStatut($reclamation['statut']) ?>
                    <span class="badge-pil pr-<?= e($priorite) ?>">Priorité <?= e(mb_strtolower(PRIORITES[$priorite] ?? $priorite)) ?></span>
                    <span class="badge-echeance <?= e($echeanceClasse) ?>"><?= e($echeanceLabel) ?></span>
                </p>
            </header>

            <?php if ($lecture): ?>
                <p class="banniere-lecture">Ce dossier est clôturé et en lecture seule.<?= $superviseur ? ' Vous pouvez le rouvrir.' : '' ?></p>
            <?php endif; ?>

            <div class="detail-grille">
                <div>
                    <section class="carte-detail">
                        <h2>Client et contrat</h2>
                        <p class="nom-client"><?= e(nomPersonne($reclamation, 'client_prenom', 'client_nom')) ?></p>
                        <p class="coords">
                            <a href="mailto:<?= e($reclamation['client_email']) ?>"><?= e($reclamation['client_email']) ?></a>
                            <?php $telephone = telephoneLisible($reclamation['telephone']); ?>
                            <?php if ($telephone !== ''): ?> · <span><?= e($telephone) ?></span><?php endif; ?>
                        </p>
                        <hr class="separateur">
                        <p class="ligne-contrat">Contrat <span class="mono"><?= e($reclamation['numero']) ?></span> · <?= e(TYPES_CONTRAT[$reclamation['type_contrat']] ?? $reclamation['type_contrat']) ?> – Formule <?= e($reclamation['formule']) ?></p>
                        <p class="aide ligne-ancien">Client depuis <?= e($anneeClient) ?> · Canal : <?= e($canalLibelle) ?></p>
                    </section>

                    <section class="carte-detail">
                        <h2>Description <span class="deposee">Déposée le <?= e(formatDate($reclamation['date_depot'])) ?></span></h2>
                        <p class="description-texte"><?= e($reclamation['description']) ?></p>
                        <?php if ($reclamation['type_contrat'] === 'sante'): ?>
                            <p class="aide alerte-sante">Contrat santé : aucun détail médical ne doit être ajouté au dossier.</p>
                        <?php endif; ?>
                    </section>

                    <section class="carte-detail">
                        <h2>Pièces jointes (<?= count($pieces) ?>)</h2>
                        <?php if ($pieces === []): ?>
                            <p class="aide">Aucune pièce jointe.</p>
                        <?php else: ?>
                            <?php foreach ($pieces as $piece): ?>
                                <?php $extension = strtoupper(pathinfo((string) $piece['nom_original'], PATHINFO_EXTENSION)); ?>
                                <div class="piece-ligne">
                                    <span class="piece-ext <?= e(classePiece((string) $piece['nom_original'])) ?>"><?= e($extension !== '' ? $extension : 'FIC') ?></span>
                                    <p class="piece-meta"><strong><?= e($piece['nom_original']) ?></strong> <span>· <?= e(formatOctets((int) $piece['taille'])) ?></span></p>
                                    <a class="lien-telecharger" href="reclamation-detail.php?piece=<?= (int) $piece['id'] ?>">Télécharger</a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>

                    <section class="carte-detail">
                        <h2>Répondre</h2>
                        <?php if ($lecture): ?>
                            <p class="aide">Les notes et les réponses sont désactivées tant que le dossier est clôturé.</p>
                        <?php else: ?>
                            <form method="post" action="reclamation-detail.php?id=<?= (int) $id ?>">
                                <?= champCsrf() ?>
                                <label class="visually-hidden" for="message">Message</label>
                                <div class="zone-reponse">
                                    <textarea id="message" name="message" rows="4" maxlength="4000" required placeholder="Votre message..."></textarea>
                                </div>
                                <label class="case-mediateur">
                                    <input type="checkbox" name="defavorable" value="1">
                                    Décision défavorable : mentionner le Médiateur de l'assurance
                                </label>
                                <div class="ligne-reponse">
                                    <button class="btn-note" type="submit" name="action" value="note">Ajouter une note interne</button>
                                    <button class="btn-envoyer" type="submit" name="action" value="reponse">Envoyer au client</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </section>
                </div>

                <aside>
                    <section class="carte-detail">
                        <h2>Traitement</h2>
                        <?php if (!$peutTraiter): ?>
                            <p class="aide">Aucune transition de statut n'est possible.</p>
                        <?php else: ?>
                            <form method="post" action="reclamation-detail.php?id=<?= (int) $id ?>">
                                <?= champCsrf() ?>
                                <input type="hidden" name="action" value="enregistrer">
                                <?php if ($optionsStatut !== []): ?>
                                    <div class="champ-traitement">
                                        <label for="statut">Statut</label>
                                        <select id="statut" name="statut">
                                            <?php foreach ($optionsStatut as $cle => $libelle): ?>
                                                <option value="<?= e((string) $cle) ?>" <?= $cle === $reclamation['statut'] ? 'selected' : '' ?>><?= e($libelle) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (in_array('resolue', $transitions, true)): ?>
                                            <p class="aide">Le passage en Résolue exige au moins une réponse au client.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($peutAttribuer): ?>
                                    <div class="champ-traitement">
                                        <label for="conseiller_id">Conseiller assigné <small>(superviseur)</small></label>
                                        <select id="conseiller_id" name="conseiller_id">
                                            <option value="" <?= empty($reclamation['conseiller_id']) ? 'selected' : '' ?>>Non attribué</option>
                                            <?php foreach ($conseillers as $conseiller): ?>
                                                <option value="<?= (int) $conseiller['id'] ?>" <?= (int) $reclamation['conseiller_id'] === (int) $conseiller['id'] ? 'selected' : '' ?>><?= e(nomPersonne($conseiller)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                <button class="btn-enregistrer" type="submit"><?php echo $libelleBouton; ?></button> 
                            </form>
                        <?php endif; ?>
                    </section>

                    <section class="carte-detail">
                        <h2>Historique</h2>
                        <ol class="chrono">
                            <?php foreach ($historique as $ligne): ?>
                                <?php $evenement = presenterHistorique($ligne, $canalLibelle); ?>
                                <li>
                                    <span class="point <?= e($evenement['point']) ?>" aria-hidden="true"></span>
                                    <?php if ($evenement['note']): ?>
                                        <div class="bulle-note">
                                            <time datetime="<?= e($evenement['iso']) ?>"><?= e($evenement['heure']) ?> · <?= e($evenement['etiquette']) ?></time>
                                            <p><?= e($evenement['titre']) ?></p>
                                            <p class="par"><?= e($evenement['par']) ?></p>
                                        </div>
                                    <?php else: ?>
                                        <time datetime="<?= e($evenement['iso']) ?>"><?= e($evenement['heure']) ?></time>
                                        <p><?= e($evenement['titre']) ?></p>
                                        <?php if ($evenement['corps'] !== ''): ?>
                                            <p class="corps"><?= e($evenement['corps']) ?></p>
                                        <?php endif; ?>
                                        <p class="par"><?= e($evenement['par']) ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                </aside>
            </div>
        </main>
    </div>
</div>
</body>
</html>
