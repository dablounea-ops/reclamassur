<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/auth.php';

function lienLocal(string $fichier, string $route): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    return str_ends_with($script, '.php') ? $fichier : url($route);
}

if (isset($_GET['oubli'])) {
    $envoye = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifierCsrf();
        $envoye = true;
    }
    $title = 'Mot de passe oublié';
    rendreConnexion($title, $envoye, null, '');
    exit;
}

if (estConnecte()) {
    rediriger(lienLocal('tableau-de-bord.php', '/tableau-de-bord'));
}

$erreur = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();
    $email = (string) ($_POST['email'] ?? '');
    $resultat = tenterConnexion($email, (string) ($_POST['mot_de_passe'] ?? ''), !empty($_POST['souvenir']));
    if ($resultat['ok']) {
        rediriger(lienLocal('tableau-de-bord.php', '/tableau-de-bord'));
    }
    $erreur = $resultat['message'];
}

rendreConnexion('Connexion', false, $erreur, $email);

function rendreConnexion(string $titre, bool $envoye, ?string $erreur, string $email): void
{
    $oubli = isset($_GET['oubli']);
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre) ?> — ReclamAssur</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="app.css" rel="stylesheet">
</head>
<body class="page-connexion">
<a class="skip" href="#connexion">Aller au contenu</a>
<div class="ecran-connexion">
    <section class="panneau-identite" aria-label="Assurium">
        <div class="rond" aria-hidden="true"></div>
        <div>
            <p class="logo-connexion"><span class="badge-bouclier"><i class="bi bi-shield-fill" aria-hidden="true"></i></span> Assurium</p>
            <h1>Espace collaborateur</h1>
            <p class="sous-titre-service">Service Réclamations – ReclamAssur</p>
            <ul class="avantages">
                <li><span class="puce-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span> Suivi centralisé des réclamations</li>
                <li><span class="puce-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span> Respect des délais réglementaires</li>
                <li><span class="puce-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span> Historique complet de chaque dossier</li>
            </ul>
        </div>
        <p class="reserve">Accès réservé au personnel d'Assurium</p>
    </section>
    <section class="panneau-saisie">
        <div class="carte-connexion" id="connexion">
            <?php if ($oubli): ?>
                <h1>Mot de passe oublié</h1>
                <p class="aide">Saisissez votre adresse professionnelle. Aucun e-mail n'est envoyé dans cette version.</p>
                <?php if ($envoye): ?>
                    <p class="alerte-ok" role="status">Si un compte Assurium correspond à cette adresse, un message de réinitialisation a été préparé.</p>
                <?php endif; ?>
                <form method="post" action="connexion.php?oubli=1" id="form-connexion">
                    <?= champCsrf() ?>
                    <label for="email">Adresse e-mail professionnelle</label>
                    <input id="email" name="email" type="email" required autocomplete="username" placeholder="sophie.morel@assurium.fr">
                    <button class="envoyer" type="submit" id="btn-connexion" style="margin-top:16px">Demander un lien</button>
                </form>
                <a class="retour" href="connexion.php">Retour à la connexion</a>
            <?php else: ?>
                <h1>Connexion</h1>
                <p class="aide">Utilisez votre adresse e-mail professionnelle.</p>
                <?php if ($erreur): ?>
                    <p class="alerte-connexion" role="alert"><span class="icone-erreur" aria-hidden="true">!</span><span><?= e($erreur) ?></span></p>
                <?php endif; ?>
                <form method="post" action="connexion.php" id="form-connexion" novalidate>
                    <?= champCsrf() ?>
                    <label for="email">Adresse e-mail professionnelle</label>
                    <input id="email" name="email" type="email" value="<?= e($email) ?>" required autocomplete="username" inputmode="email" placeholder="sophie.morel@assurium.fr">
                    <label for="mot_de_passe">Mot de passe</label>
                    <div class="champ-motdepasse">
                        <input id="mot_de_passe" name="mot_de_passe" type="password" required autocomplete="current-password">
                        <button class="oeil" type="button" id="voir-motdepasse" aria-controls="mot_de_passe" aria-pressed="false" aria-label="Afficher le mot de passe"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                    <div class="ligne-connexion">
                        <label class="souvenir" for="souvenir"><input id="souvenir" name="souvenir" type="checkbox" value="1"> Se souvenir de moi</label>
                        <a href="connexion.php?oubli=1">Mot de passe oublié ?</a>
                    </div>
                    <button class="envoyer" type="submit" id="btn-connexion">Se connecter</button>
                    <p class="journal">Toute connexion est enregistrée (date, heure, adresse IP).</p>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
<script src="app.js"></script>
</body>
</html>
    <?php
}
