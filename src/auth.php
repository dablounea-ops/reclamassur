<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function url(string $chemin): string
{
    $base = rtrim(env('BASE_PATH', ''), '/');
    if ($chemin === '' || $chemin === '/') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base . $chemin;
}

function rediriger(string $chemin): never
{
    header('Location: ' . $chemin);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function prendreFlash(): ?array
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function jetonCsrf(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function champCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(jetonCsrf()) . '">';
}

function verifierCsrf(): void
{
    $jeton = $_POST['csrf'] ?? '';
    if (!is_string($jeton) || !hash_equals(jetonCsrf(), $jeton)) {
        afficherErreur('Formulaire expiré', 'Le formulaire a expiré. Rechargez la page puis recommencez.', 419);
    }
}

function cheminInterne(?string $chemin, string $defaut): string
{
    if (!is_string($chemin) || !str_starts_with($chemin, '/') || str_starts_with($chemin, '//') || str_contains($chemin, '://')) {
        return $defaut;
    }

    return $chemin;
}

function formatDate(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }
    $objet = new DateTimeImmutable($date);

    return $objet->format(strlen($date) > 10 ? 'd/m/Y H:i' : 'd/m/Y');
}

function formatOctets(int $octets): string
{
    if ($octets < 1024) {
        return $octets . ' o';
    }
    if ($octets < 1048576) {
        return (string) round($octets / 1024) . ' Ko';
    }

    return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
}

$seSouvenir = ($_COOKIE['reclamassur_remember'] ?? '') === '1';
$delaiInactivite = $seSouvenir ? 14 * 24 * 3600 : 30 * 60;

session_name('RECLAMASSUR');
session_set_cookie_params([
    'lifetime' => $seSouvenir ? 14 * 24 * 3600 : 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $delaiInactivite) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
if (!empty($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function utilisateurCourant(): ?array
{
    static $charge = false;
    static $utilisateur = null;
    if ($charge) {
        return $utilisateur;
    }
    $charge = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = pdo()->prepare('SELECT id, nom, prenom, email, role, actif FROM utilisateur WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $ligne = $stmt->fetch();
    if (!$ligne || !(int) $ligne['actif']) {
        $_SESSION = [];
        return null;
    }
    $utilisateur = $ligne;

    return $utilisateur;
}

function estConnecte(): bool
{
    return utilisateurCourant() !== null;
}

function estSuperviseur(?array $utilisateur = null): bool
{
    $utilisateur ??= utilisateurCourant();

    return $utilisateur !== null && $utilisateur['role'] === 'superviseur';
}

function exigerConnexion(): array
{
    $utilisateur = utilisateurCourant();
    if ($utilisateur === null) {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (str_ends_with($script, '.php')) {
            rediriger('connexion.php');
        }
        $suivant = $_SERVER['REQUEST_URI'] ?? url('/tableau-de-bord');
        rediriger(url('/connexion?next=' . urlencode($suivant)));
    }
    header('Cache-Control: no-store');

    return $utilisateur;
}

function exigerRole(string ...$roles): array
{
    $utilisateur = exigerConnexion();
    if ($roles !== [] && !in_array($utilisateur['role'], $roles, true)) {
        afficherErreur('Accès refusé', "Cette page n'est pas accessible avec votre rôle.", 403);
    }

    return $utilisateur;
}

function peutVoirReclamation(array $utilisateur, array $reclamation): bool
{
    if ($utilisateur['role'] === 'superviseur') {
        return true;
    }

    return (int) ($reclamation['conseiller_id'] ?? 0) === (int) $utilisateur['id'];
}

function journaliser(?int $utilisateurId, string $email, bool $succes, ?string $ip): void
{
    $stmt = pdo()->prepare(
        'INSERT INTO journal_connexion (utilisateur_id, email_saisi, succes, adresse_ip, date_connexion)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $utilisateurId,
        mb_substr($email, 0, 180),
        $succes ? 1 : 0,
        $ip,
        date('Y-m-d H:i:s'),
    ]);
}

function tenterConnexion(string $email, string $motDePasse, bool $seSouvenir): array
{
    $email = mb_strtolower(trim($email));
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $generique = 'Identifiants incorrects.';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@assurium.fr')) {
        journaliser(null, $email, false, $ip);

        return ['ok' => false, 'message' => $generique];
    }

    $stmt = pdo()->prepare('SELECT * FROM utilisateur WHERE email = ?');
    $stmt->execute([$email]);
    $compte = $stmt->fetch();
    if (!$compte || !(int) $compte['actif']) {
        journaliser($compte['id'] ?? null, $email, false, $ip);

        return ['ok' => false, 'message' => $generique];
    }

    if (!empty($compte['bloque_jusqua']) && strtotime((string) $compte['bloque_jusqua']) > time()) {
        $minutes = (int) ceil((strtotime((string) $compte['bloque_jusqua']) - time()) / 60);

        return [
            'ok' => false,
            'message' => 'Compte temporairement bloqué. Réessayez dans ' . max(1, $minutes) . ' minute(s).',
        ];
    }

    if (!empty($compte['bloque_jusqua']) && strtotime((string) $compte['bloque_jusqua']) <= time()) {
        pdo()->prepare('UPDATE utilisateur SET tentatives_echec = 0, bloque_jusqua = NULL WHERE id = ?')
            ->execute([(int) $compte['id']]);
        $compte['tentatives_echec'] = 0;
    }

    if (!password_verify($motDePasse, (string) $compte['mot_de_passe'])) {
        $tentatives = (int) $compte['tentatives_echec'] + 1;
        if ($tentatives >= 5) {
            pdo()->prepare('UPDATE utilisateur SET tentatives_echec = ?, bloque_jusqua = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?')
                ->execute([$tentatives, (int) $compte['id']]);
            journaliser((int) $compte['id'], $email, false, $ip);

            return ['ok' => false, 'message' => 'Compte bloqué pendant 15 minutes après 5 tentatives.'];
        }
        pdo()->prepare('UPDATE utilisateur SET tentatives_echec = ? WHERE id = ?')
            ->execute([$tentatives, (int) $compte['id']]);
        journaliser((int) $compte['id'], $email, false, $ip);
        $reste = 5 - $tentatives;
        $mot = $reste > 1 ? 'tentatives' : 'tentative';

        return ['ok' => false, 'message' => $generique . ' Il vous reste ' . $reste . ' ' . $mot . '.'];
    }

    pdo()->prepare('UPDATE utilisateur SET tentatives_echec = 0, bloque_jusqua = NULL, derniere_connexion = NOW() WHERE id = ?')
        ->execute([(int) $compte['id']]);
    journaliser((int) $compte['id'], $email, true, $ip);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $compte['id'];
    $_SESSION['last_activity'] = time();

    if ($seSouvenir) {
        setcookie('reclamassur_remember', '1', [
            'expires' => time() + 14 * 24 * 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie('reclamassur_remember', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    return ['ok' => true];
}

function deconnecter(): void
{
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'] ?: '/', $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    setcookie('reclamassur_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_destroy();
}

function conseillersActifs(): array
{
    return pdo()->query(
        "SELECT id, nom, prenom FROM utilisateur WHERE role = 'conseiller' AND actif = 1 ORDER BY nom, prenom"
    )->fetchAll();
}

function nomPersonne(array $ligne, string $prenom = 'prenom', string $nom = 'nom'): string
{
    return trim((string) ($ligne[$prenom] ?? '') . ' ' . (string) ($ligne[$nom] ?? ''));
}

function limiteAtteinte(string $cle, int $maximum, int $fenetreSecondes = 3600): bool
{
    $dossier = RACINE . '/uploads';
    if (!is_dir($dossier)) {
        mkdir($dossier, 0775, true);
    }
    $chemin = $dossier . '/.rate.json';
    $donnees = [];
    if (is_file($chemin)) {
        $donnees = json_decode((string) file_get_contents($chemin), true);
        if (!is_array($donnees)) {
            $donnees = [];
        }
    }

    $maintenant = time();
    $horaires = array_values(array_filter(
        $donnees[$cle] ?? [],
        static fn ($moment) => is_int($moment) && $moment > $maintenant - $fenetreSecondes
    ));

    if (count($horaires) >= $maximum) {
        $donnees[$cle] = $horaires;
        file_put_contents($chemin, json_encode($donnees), LOCK_EX);

        return true;
    }

    $horaires[] = $maintenant;
    $donnees[$cle] = $horaires;
    file_put_contents($chemin, json_encode($donnees), LOCK_EX);

    return false;
}

require_once __DIR__ . '/delais.php';