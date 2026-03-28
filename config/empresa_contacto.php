<?php

function empresa_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function empresa_ensure_contact_schema(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $initialized = true;

    $requiredColumns = [
        'instagram' => "ALTER TABLE empresas ADD COLUMN instagram VARCHAR(255) NULL AFTER website",
        'tiktok' => "ALTER TABLE empresas ADD COLUMN tiktok VARCHAR(255) NULL AFTER instagram",
        'facebook' => "ALTER TABLE empresas ADD COLUMN facebook VARCHAR(255) NULL AFTER tiktok",
        'whatsapp' => "ALTER TABLE empresas ADD COLUMN whatsapp VARCHAR(30) NULL AFTER facebook",
        'medios_contacto_pedido' => "ALTER TABLE empresas ADD COLUMN medios_contacto_pedido VARCHAR(100) NOT NULL DEFAULT 'whatsapp,formulario,correo' AFTER whatsapp",
    ];

    foreach ($requiredColumns as $column => $sql) {
        if (!empresa_column_exists($pdo, 'empresas', $column)) {
            $pdo->exec($sql);
        }
    }
}

function empresa_contact_method_labels(): array
{
    return [
        'whatsapp' => 'WhatsApp',
        'formulario' => 'Telefono + Email',
        'correo' => 'Email',
    ];
}

function empresa_normalize_website(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . $value;
    }

    return $value;
}

function empresa_normalize_contact_methods($methods): array
{
    if (!is_array($methods)) {
        return [];
    }

    $allowedMethods = array_keys(empresa_contact_method_labels());
    $normalized = [];

    foreach ($methods as $method) {
        $method = trim((string) $method);
        if (in_array($method, $allowedMethods, true) && !in_array($method, $normalized, true)) {
            $normalized[] = $method;
        }
    }

    return $normalized;
}

function empresa_serialize_contact_methods($methods): string
{
    return implode(',', empresa_normalize_contact_methods($methods));
}

function empresa_deserialize_contact_methods(?string $storedMethods): array
{
    $methods = empresa_normalize_contact_methods(explode(',', (string) $storedMethods));

    if (!empty($methods)) {
        return $methods;
    }

    return array_keys(empresa_contact_method_labels());
}

function empresa_normalize_instagram(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $value = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $value);
    $value = trim($value, "@/ ");

    return $value;
}

function empresa_instagram_url(?string $value): ?string
{
    $handle = empresa_normalize_instagram($value);

    if ($handle === '') {
        return null;
    }

    return 'https://www.instagram.com/' . rawurlencode($handle);
}

function empresa_normalize_tiktok(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $value = preg_replace('#^https?://(www\.)?tiktok\.com/@#i', '', $value);
    $value = trim($value, "@/ ");

    return $value;
}

function empresa_tiktok_url(?string $value): ?string
{
    $handle = empresa_normalize_tiktok($value);

    if ($handle === '') {
        return null;
    }

    return 'https://www.tiktok.com/@' . rawurlencode($handle);
}

function empresa_normalize_facebook(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $value = preg_replace('#^https?://(www\.)?facebook\.com/#i', '', $value);
    $value = trim($value, "/ ");

    return $value;
}

function empresa_facebook_url(?string $value): ?string
{
    $handle = empresa_normalize_facebook($value);

    if ($handle === '') {
        return null;
    }

    return 'https://www.facebook.com/' . rawurlencode($handle);
}

function empresa_normalize_whatsapp(?string $value): string
{
    return trim((string) $value);
}

function empresa_whatsapp_digits(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value);
}

function empresa_whatsapp_url(?string $value, string $message = ''): ?string
{
    $digits = empresa_whatsapp_digits($value);

    if ($digits === '') {
        return null;
    }

    $url = 'https://wa.me/' . $digits;
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }

    return $url;
}
