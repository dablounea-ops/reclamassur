<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

define('RACINE', dirname(__DIR__));

function chargerEnv(string $chemin): void
{
    if (!is_file($chemin)) {
        return;
    }
    $lignes = file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lignes === false) {
        return;
    }
    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || str_starts_with($ligne, '#')) {
            continue;
        }
        $parties = explode('=', $ligne, 2);
        if (count($parties) !== 2) {
            continue;
        }
        $cle = trim($parties[0]);
        $valeur = trim($parties[1], " \t\"'");
        $_ENV[$cle] = $valeur;
        putenv($cle . '=' . $valeur);
    }
}

function env(string $cle, string $defaut = ''): string
{
    $valeur = $_ENV[$cle] ?? getenv($cle);
    if ($valeur === false || $valeur === null || $valeur === '') {
        return $defaut;
    }

    return (string) $valeur;
}

chargerEnv(RACINE . '/.env');

function e(?string $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function afficherErreur(string $title, string $message, int $code = 500): never
{
    http_response_code($code);
    $titre = e($title);
    $texte = e($message);
    echo <<<HTML
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>{$titre}</title></head>
<body style="font-family:sans-serif;background:#F1F5F9;color:#0F172A;padding:48px">
<h1>{$titre}</h1><p>{$texte}</p>
<p><a href="/reclamation">Retour à l'accueil</a></p>
</body></html>
HTML;
    exit;
}

function pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_NAME', 'reclamassur')
    );

    try {
        $pdo = new PDO($dsn, env('DB_USER', 'reclamassur'), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        error_log('Connexion MySQL impossible : ' . $exception->getMessage());
        afficherErreur(
            'Service indisponible',
            'La base de données ne répond pas. Vérifiez que MySQL est démarré, que database.sql est importé, et que le fichier .env est correct.'
        );
    }

    return $pdo;
}
