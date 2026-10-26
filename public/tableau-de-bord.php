<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/auth.php';

$user = exigerRole('conseiller', 'superviseur');
$periode = (string) ($_GET['periode'] ?? 'mois');
$stats = statistiques($user, $periode);
$notices = notifications($user);
$superviseur = $user['role'] === 'superviseur';

$couleurs = [
    'nouvelle' => '#1D4ED8',
    'ar_envoye' => '#8B5CF6',
    'en_cours' => '#F97316',
    'attente_client' => '#A855F7',
    'resolue' => '#059669',
    'cloturee' => '#64748B',
];
$ordreStatuts = ['nouvelle', 'ar_envoye', 'en_cours', 'attente_client', 'resolue'];
$compteStatut = [];
foreach ($stats['statuts'] as $ligne) {
    $compteStatut[$ligne['statut']] = (int) $ligne['total'];
}
$statutsAnneau = [];
foreach ($ordreStatuts as $statut) {
    $statutsAnneau[] = ['statut' => $statut, 'total' => $compteStatut[$statut] ?? 0];
}
$totalOuverts = array_sum(array_column($statutsAnneau, 'total'));
$maxCategorie = 0;
foreach ($stats['categories'] as $ligne) {
    $maxCategorie = max($maxCategorie, (int) $ligne['total']);
}
$nuances = ['#1D4ED8', '#2563EB', '#3B82F6', '#60A5FA', '#93C5FD', '#BFDBFE', '#DBEAFE'];
$libellesCourts = [
    'Contestation de cotisation / tarif' => 'Contestation de tarif',
    'Qualité de service / accueil' => 'Qualité de service',
];
$stops = [];
$depart = 0.0;
foreach ($statutsAnneau as $ligne) {
    if ($totalOuverts === 0 || $ligne['total'] === 0) {
        continue;
    }
    $part = ($ligne['total'] / $totalOuverts) * 100;
    $fin = $depart + $part;
    $stops[] = $couleurs[$ligne['statut']] . ' ' . $depart . '% ' . $fin . '%';
    $depart = $fin;
}
$conic = $stops === [] ? '#E2E8F0 0% 100%' : implode(', ', $stops);
$delta = $stats['delai_delta'];
if ($delta === null) {
    $legendeDelai = 'dossiers résolus sur la période';
} else {
    $signe = $delta > 0 ? '+' : ($delta < 0 ? '−' : '');
    $legendeDelai = $signe . abs((int) $delta) . ' j par rapport à ' . $stats['delai_compare'];
}

$jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$aujourdhui = new DateTimeImmutable('today');
$situation = $jours[(int) $aujourdhui->format('w')] . ' ' . $aujourdhui->format('j') . ' ' . $mois[(int) $aujourdhui->format('n') - 1] . ' ' . $aujourdhui->format('Y');
$initiales = mb_strtoupper(mb_substr($user['prenom'], 0, 1) . mb_substr($user['nom'], 0, 1));
$periodes = ['semaine' => 'Cette semaine', 'mois' => 'Ce mois-ci', 'annee' => 'Cette année'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tableau de bord — ReclamAssur</title>
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
            <a class="actif" href="tableau-de-bord.php" aria-current="page"><i class="bi bi-speedometer2" aria-hidden="true"></i> Tableau de bord</a>
            <a href="reclamations.php"><i class="bi bi-inboxes" aria-hidden="true"></i> Réclamations</a>
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
            <div class="entete-board">
                <div>
                    <h1>Tableau de bord</h1>
                    <p class="aide">Situation au <?= e($situation) ?></p>
                </div>
                <form method="get" action="tableau-de-bord.php">
                    <label class="visually-hidden" for="periode">Période</label>
                    <select id="periode" name="periode" onchange="this.form.submit()">
                        <?php foreach ($periodes as $cle => $libelle): ?>
                            <option value="<?= e($cle) ?>" <?= $stats['periode']['cle'] === $cle ? 'selected' : '' ?>><?= e($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="kpi-grille">
                <a class="kpi kpi-bleu" href="reclamations.php?statut=nouvelle">
                    <span>Nouvelles</span>
                    <strong class="bleu"><?= (int) $stats['nouvelles'] ?></strong>
                    <?php if ($superviseur): ?><small><?= (int) $stats['non_attribuees'] ?> non attribuées</small><?php endif; ?>
                </a>
                <a class="kpi kpi-orange" href="reclamations.php?statut=en_cours">
                    <span>En cours</span>
                    <strong class="orange"><?= (int) $stats['en_cours'] ?></strong>
                    <small>dont <?= (int) $stats['en_attente'] ?> en attente client</small>
                </a>
                <a class="kpi kpi-rouge" href="reclamations.php?retard=1">
                    <span>En retard</span>
                    <strong class="rouge"><?= (int) $stats['en_retard'] ?></strong>
                    <small>délai de 2 mois dépassé</small>
                </a>
                <a class="kpi kpi-vert" href="reclamations.php?statut=resolue">
                    <span>Délai moyen</span>
                    <strong class="vert"><?= $stats['delai_moyen'] === null ? '—' : (int) $stats['delai_moyen'] . ' j' ?></strong>
                    <small><?= e($legendeDelai) ?></small>
                </a>
            </div>

            <div class="grille-board">
                <section class="carte-board">
                    <h2>Réclamations par catégorie</h2>
                    <?php if ($stats['categories'] === []): ?>
                        <p class="aide">Aucune réclamation sur cette période.</p>
                    <?php else: ?>
                        <div class="barres" role="img" aria-label="Nombre de réclamations par catégorie sur la période">
                            <?php foreach ($stats['categories'] as $index => $ligne): ?>
                                <?php $largeur = $maxCategorie > 0 ? max(8, (int) round(((int) $ligne['total'] / $maxCategorie) * 100)) : 0; ?>
                                <div class="barre-ligne">
                                    <span><?= e($libellesCourts[$ligne['categorie']] ?? $ligne['categorie']) ?></span>
                                    <div class="barre-piste"><div class="barre-remplie" style="width: <?= $largeur ?>%; background: <?= e($nuances[$index] ?? '#DBEAFE') ?>"></div></div>
                                    <strong><?= (int) $ligne['total'] ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <section class="carte-board">
                    <h2>Par statut</h2>
                    <?php if ($totalOuverts === 0): ?>
                        <p class="aide">Aucun dossier ouvert sur cette période.</p>
                    <?php else: ?>
                        <div class="anneau-bloc">
                            <div class="anneau" style="background: conic-gradient(<?= e($conic) ?>)" role="img" aria-label="Répartition des dossiers ouverts par statut">
                                <span><strong><?= (int) $totalOuverts ?></strong><small>dossiers</small></span>
                            </div>
                            <ul class="legende">
                                <?php foreach ($statutsAnneau as $ligne): ?>
                                    <li><i style="background: <?= e($couleurs[$ligne['statut']]) ?>"></i><?= e(STATUTS[$ligne['statut']] ?? $ligne['statut']) ?> (<?= (int) $ligne['total'] ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="carte-board">
                <h2>Dossiers urgents <span class="aide">échéance dans moins de 15 jours ou dépassée</span></h2>
                <?php if ($stats['urgents'] === []): ?>
                    <p class="aide">Aucun dossier urgent. Les échéances sont sous contrôle.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Référence</th>
                                    <th scope="col">Client</th>
                                    <th scope="col">Catégorie</th>
                                    <th scope="col">Échéance</th>
                                    <th scope="col">Conseiller</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($stats['urgents'] as $ligne): ?>
                                <?php $etat = etatDelai($ligne); ?>
                                <tr>
                                    <td><a class="mono" href="reclamation-detail.php?id=<?= (int) $ligne['id'] ?>"><?= e($ligne['reference']) ?></a></td>
                                    <td><?= e(mb_substr((string) $ligne['client_prenom'], 0, 1) . '. ' . $ligne['client_nom']) ?></td>
                                    <td><?= e($ligne['categorie']) ?></td>
                                    <td><span class="pill dl-<?= e($etat['classe']) ?>"><?= e($etat['label']) ?></span></td>
                                    <td><?= $ligne['conseiller_nom'] ? e($ligne['conseiller_prenom'] . ' ' . mb_substr((string) $ligne['conseiller_nom'], 0, 1) . '.') : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
</body>
</html>
