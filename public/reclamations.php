<?php
require dirname(__DIR__) . '/src/auth.php';

$user = exigerRole('conseiller', 'superviseur');
$superviseur = $user['role'] === 'superviseur';

if (($_GET['export'] ?? '') === 'csv') {
    exporterCsv($user, $_GET);
}

$cles = ['q', 'statut', 'priorite', 'categorie', 'conseiller', 'retard', 'ar_retard', 'cloturees', 'tri', 'dir', 'par', 'page'];
$query = [];
foreach ($cles as $cle) {
    if (isset($_GET[$cle]) && (string) $_GET[$cle] !== '') {
        $query[$cle] = (string) $_GET[$cle];
    }
}
$_SESSION['liste_qs'] = http_build_query($query);

$liste = preparerListe($user, $query);
$lignes = lignesListe($liste);
$notices = notifications($user);
$conseillers = $superviseur ? conseillersActifs() : [];
$nomsConseillers = [];
foreach ($conseillers as $conseiller) {
    $nomsConseillers[(string) $conseiller['id']] = nomPersonne($conseiller);
}

$filtresActifs = [];
if (trim((string) ($query['q'] ?? '')) !== '') {
    $filtresActifs['q'] = 'Recherche : ' . trim((string) $query['q']);
}
if (!empty($query['statut']) && isset(STATUTS[$query['statut']])) {
    $filtresActifs['statut'] = 'Statut : ' . STATUTS[$query['statut']];
}
if (!empty($query['priorite']) && isset(PRIORITES[$query['priorite']])) {
    $filtresActifs['priorite'] = 'Priorité : ' . PRIORITES[$query['priorite']];
}
if (!empty($query['categorie']) && isset(CATEGORIES[$query['categorie']])) {
    $filtresActifs['categorie'] = 'Catégorie : ' . $query['categorie'];
}
if ($superviseur && (($query['conseiller'] ?? '') !== '')) {
    $filtresActifs['conseiller'] = $query['conseiller'] === '0' ? 'Non attribuées' : ($nomsConseillers[$query['conseiller']] ?? 'Conseiller');
}
if (!empty($query['retard'])) {
    $filtresActifs['retard'] = 'En retard';
}
if (!empty($query['ar_retard'])) {
    $filtresActifs['ar_retard'] = 'AR en retard';
}
if (!empty($query['cloturees'])) {
    $filtresActifs['cloturees'] = 'Clôturées incluses';
}

function qsListe(array $base, array $remplacer = []): string
{
    $params = array_merge($base, $remplacer);
    foreach ($params as $cle => $valeur) {
        if ($valeur === null || $valeur === '') {
            unset($params[$cle]);
        }
    }
    $qs = http_build_query($params);

    return 'reclamations.php' . ($qs !== '' ? '?' . $qs : '');
}

function flecheTri(array $query, string $colonne, string $libelle, string $tri, string $dir): string
{
    $prochaine = ($tri === $colonne && $dir === 'asc') ? 'desc' : 'asc';
    $symbole = $tri === $colonne ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    $href = qsListe($query, ['tri' => $colonne, 'dir' => $prochaine, 'page' => null]);

    return '<a class="tri" href="' . e($href) . '">' . e($libelle . $symbole) . '</a>';
}

function pagesVisibles(int $page, int $pages): array
{
    if ($pages <= 5) {
        return range(1, $pages);
    }
    $liste = [1];
    $debut = max(2, $page - 1);
    $fin = min($pages - 1, $page + 1);
    if ($debut > 2) {
        $liste[] = '…';
    }
    for ($i = $debut; $i <= $fin; $i++) {
        $liste[] = $i;
    }
    if ($fin < $pages - 1) {
        $liste[] = '…';
    }
    $liste[] = $pages;

    return $liste;
}

$debutAffiche = $liste['total'] === 0 ? 0 : (($liste['page'] - 1) * $liste['par']) + 1;
$finAffiche = min($liste['total'], $liste['page'] * $liste['par']);
$initiales = mb_strtoupper(mb_substr($user['prenom'], 0, 1) . mb_substr($user['nom'], 0, 1));
$pages = pagesVisibles($liste['page'], $liste['pages']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Réclamations — ReclamAssur</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
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
                <input id="q-rapide" name="q" type="search" value="<?= e($query['q'] ?? '') ?>" placeholder="Rechercher une référence, un client...">
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
            <div class="entete-liste">
                <h1>Réclamations <span><?= (int) $liste['total'] ?> résultat<?= $liste['total'] > 1 ? 's' : '' ?></span></h1>
                <a class="btn-saisir" href="reclamation.php?interne=1"><i class="bi bi-plus-lg" aria-hidden="true"></i> Saisir une réclamation</a>
            </div>

            <form class="barre-filtres" method="get" action="reclamations.php">
                <label class="visually-hidden" for="q">Recherche</label>
                <span class="champ-recherche">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="q" name="q" type="search" value="<?= e($query['q'] ?? '') ?>" placeholder="Référence, client...">
                </span>
                <label class="visually-hidden" for="statut">Statut</label>
                <select id="statut" name="statut" class="<?= !empty($query['statut']) ? 'actif' : '' ?>" onchange="this.form.submit()">
                    <option value="">Statut</option>
                    <?php foreach (STATUTS as $cle => $libelle): ?>
                        <option value="<?= e($cle) ?>" <?= ($query['statut'] ?? '') === $cle ? 'selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="visually-hidden" for="priorite">Priorité</label>
                <select id="priorite" name="priorite" class="<?= !empty($query['priorite']) ? 'actif' : '' ?>" onchange="this.form.submit()">
                    <option value="">Priorité</option>
                    <?php foreach (PRIORITES as $cle => $libelle): ?>
                        <option value="<?= e($cle) ?>" <?= ($query['priorite'] ?? '') === $cle ? 'selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="visually-hidden" for="categorie">Catégorie</label>
                <select id="categorie" name="categorie" class="<?= !empty($query['categorie']) ? 'actif' : '' ?>" onchange="this.form.submit()">
                    <option value="">Catégorie</option>
                    <?php foreach (CATEGORIES as $libelle => $priorite): ?>
                        <option value="<?= e($libelle) ?>" <?= ($query['categorie'] ?? '') === $libelle ? 'selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($superviseur): ?>
                    <label class="visually-hidden" for="conseiller">Conseiller</label>
                    <select id="conseiller" name="conseiller" class="<?= ($query['conseiller'] ?? '') !== '' ? 'actif' : '' ?>" onchange="this.form.submit()">
                        <option value="">Conseiller</option>
                        <option value="0" <?= ($query['conseiller'] ?? '') === '0' ? 'selected' : '' ?>>Non attribuées</option>
                        <?php foreach ($conseillers as $conseiller): ?>
                            <option value="<?= (int) $conseiller['id'] ?>" <?= ($query['conseiller'] ?? '') === (string) $conseiller['id'] ? 'selected' : '' ?>><?= e(nomPersonne($conseiller)) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <label class="case-cloturees"><input type="checkbox" name="cloturees" value="1" <?= !empty($query['cloturees']) ? 'checked' : '' ?> onchange="this.form.submit()"> Afficher les clôturées</label>
                <?php foreach (['retard', 'ar_retard', 'tri', 'dir', 'par'] as $cle): ?>
                    <?php if (!empty($query[$cle])): ?><input type="hidden" name="<?= e($cle) ?>" value="<?= e($query[$cle]) ?>"><?php endif; ?>
                <?php endforeach; ?>
                <a class="reinit" href="reclamations.php">Réinitialiser</a>
            </form>

            <?php if ($filtresActifs !== []): ?>
                <div class="pastilles" aria-label="Filtres actifs">
                    <?php foreach ($filtresActifs as $cle => $libelle): ?>
                        <a class="pastille-filtre" href="<?= e(qsListe($query, [$cle => null, 'page' => null])) ?>"><?= e($libelle) ?> <span aria-hidden="true">×</span><span class="visually-hidden">Retirer</span></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($lignes === []): ?>
                <p class="vide-liste">Aucune réclamation ne correspond à ces critères.</p>
            <?php else: ?>
                <div class="cartes-liste">
                    <?php foreach ($lignes as $ligne): ?>
                        <?php $etat = etatDelai($ligne); ?>
                        <a class="carte-dossier" href="reclamation-detail.php?id=<?= (int) $ligne['id'] ?>">
                            <span class="mono"><?= e($ligne['reference']) ?></span>
                            <strong><?= e($ligne['objet']) ?></strong>
                            <span><?= e(mb_substr((string) $ligne['client_prenom'], 0, 1) . '. ' . $ligne['client_nom']) ?> · <?= e($ligne['categorie']) ?></span>
                            <span><?= badgePriorite(prioriteEffective($ligne)) ?> <?= badgeStatut($ligne['statut']) ?> <?= badgeDelai($etat) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="table-wrap table-bureau">
                    <table class="table-liste">
                        <thead>
                            <tr>
                                <?php
                                $colonnes = [
                                    'reference' => 'Référence',
                                    'client' => 'Client',
                                    'categorie' => 'Catégorie',
                                    'priorite' => 'Priorité',
                                    'statut' => 'Statut',
                                    'echeance' => 'Échéance',
                                ];
                                if ($superviseur) {
                                    $colonnes['conseiller'] = 'Conseiller';
                                }
                                ?>
                                <?php foreach ($colonnes as $cle => $libelle): ?>
                                    <th scope="col"><?= flecheTri($query, $cle, $libelle, $liste['tri'], $liste['dir']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lignes as $ligne): ?>
                            <?php
                            $etat = etatDelai($ligne);
                            if ($etat['code'] === 'ok' && preg_match('#^(\d{2}/\d{2})/#', $etat['label'], $correspondance) === 1) {
                                $etat['label'] = $correspondance[1];
                            }
                            $href = 'reclamation-detail.php?id=' . (int) $ligne['id'];
                            ?>
                            <tr data-href="<?= e($href) ?>">
                                <td><a class="mono" href="<?= e($href) ?>"><?= e($ligne['reference']) ?></a></td>
                                <td><?= e(mb_substr((string) $ligne['client_prenom'], 0, 1) . '. ' . $ligne['client_nom']) ?></td>
                                <td class="cat-court" title="<?= e($ligne['categorie']) ?>"><?= e($ligne['categorie']) ?></td>
                                <td><?= badgePriorite(prioriteEffective($ligne)) ?></td>
                                <td><?= badgeStatut($ligne['statut']) ?></td>
                                <td><?= badgeDelai($etat) ?></td>
                                <?php if ($superviseur): ?>
                                    <td><?= $ligne['cons_nom'] ? e($ligne['cons_prenom'] . ' ' . mb_substr((string) $ligne['cons_nom'], 0, 1) . '.') : '—' ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pied-liste">
                    <p>Affichage <?= (int) $debutAffiche ?> – <?= (int) $finAffiche ?> sur <?= (int) $liste['total'] ?></p>
                    <form method="get" action="reclamations.php">
                        <?php foreach ($query as $cle => $valeur): ?>
                            <?php if ($cle !== 'par' && $cle !== 'page'): ?><input type="hidden" name="<?= e($cle) ?>" value="<?= e($valeur) ?>"><?php endif; ?>
                        <?php endforeach; ?>
                        <label> <select name="par" aria-label="Lignes par page" onchange="this.form.submit()">
                            <?php foreach ([10, 25, 50] as $nombre): ?>
                                <option value="<?= $nombre ?>" <?= $liste['par'] === $nombre ? 'selected' : '' ?>><?= $nombre ?> par page</option>
                            <?php endforeach; ?>
                        </select></label>
                    </form>
                    <nav aria-label="Pagination">
                        <?php if ($liste['page'] > 1): ?>
                            <a class="page-num" href="<?= e(qsListe($query, ['page' => $liste['page'] - 1])) ?>" aria-label="Page précédente">‹</a>
                        <?php endif; ?>
                        <?php foreach ($pages as $numero): ?>
                            <?php if ($numero === '…'): ?>
                                <span class="page-points">…</span>
                            <?php else: ?>
                                <a class="page-num<?= $numero === $liste['page'] ? ' actif' : '' ?>" href="<?= e(qsListe($query, ['page' => $numero])) ?>" <?= $numero === $liste['page'] ? 'aria-current="page"' : '' ?>><?= (int) $numero ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($liste['page'] < $liste['pages']): ?>
                            <a class="page-num" href="<?= e(qsListe($query, ['page' => $liste['page'] + 1])) ?>" aria-label="Page suivante">›</a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script>
document.querySelectorAll('tr[data-href]').forEach(function (ligne) {
    ligne.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            return;
        }
        window.location.href = ligne.dataset.href;
    });
});
</script>
</body>
</html>
