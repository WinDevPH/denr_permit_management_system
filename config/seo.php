<?php
/**
 * Shared SEO helpers for public DENR pages.
 */
function denr_public_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/denr/index.php');
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '' || $dir === '.') {
        return $scheme . '://' . $host . '/';
    }

    return $scheme . '://' . $host . $dir . '/';
}

function denr_render_public_seo(array $options = []): void
{
    $siteName = 'DENR Region IX Digital System';
    $title = (string) ($options['title'] ?? 'DENR Region IX | Private Tree Plantation Registration & Permit Management');
    $description = (string) ($options['description'] ?? 'Official DENR Region IX digital portal for private tree plantation registration, mapping, and permit management across the Zamboanga Peninsula.');
    $keywords = (string) ($options['keywords'] ?? 'DENR Region IX, tree plantation registration, cutting permit, private plantation, Zamboanga Peninsula, DENR digital system');
    $baseUrl = denr_public_base_url();
    $canonical = (string) ($options['canonical'] ?? $baseUrl);
    $image = (string) ($options['image'] ?? $baseUrl . 'assets/img/denrlogo.png');

    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>\n";
    echo '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta name="keywords" content="' . htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta name="author" content="Department of Environment and Natural Resources - Region IX">' . "\n";
    echo '<meta name="robots" content="index, follow">' . "\n";
    echo '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:url" content="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta property="og:site_name" content="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta property="og:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta name="twitter:card" content="summary">' . "\n";
    echo '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta name="twitter:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta name="twitter:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}

function denr_get_admin_contacts(PDO $db): array
{
    $stmt = $db->query(
        "SELECT full_name, email, contact_number
         FROM users
         WHERE role = 'admin' AND (status IS NULL OR status = 'active')
         ORDER BY user_id ASC"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function denr_format_contact_number(?string $raw): string
{
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if ($digits === '') {
        return '—';
    }
    if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
        return '0' . $digits;
    }

    return $digits;
}
