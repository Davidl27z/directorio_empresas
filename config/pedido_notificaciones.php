<?php

function pedido_notification_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function pedido_notification_ensure_schema(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $initialized = true;

    if (!pedido_notification_column_exists($pdo, 'pedidos', 'visto_empresa')) {
        $pdo->exec("ALTER TABLE pedidos ADD COLUMN visto_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER estado");
    }
}
