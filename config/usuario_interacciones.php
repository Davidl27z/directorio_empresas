<?php

function usuario_interacciones_ensure_schema(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $initialized = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS producto_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        producto_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_producto_likes_usuario_producto (usuario_id, producto_id),
        INDEX idx_producto_likes_producto (producto_id),
        INDEX idx_producto_likes_usuario_fecha (usuario_id, created_at),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS producto_guardados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        producto_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_producto_guardados_usuario_producto (usuario_id, producto_id),
        INDEX idx_producto_guardados_producto (producto_id),
        INDEX idx_producto_guardados_usuario_fecha (usuario_id, created_at),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS producto_resenas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pedido_item_id INT NOT NULL,
        pedido_id INT NOT NULL,
        producto_id INT NOT NULL,
        usuario_id INT NOT NULL,
        empresa_id INT NOT NULL,
        calificacion TINYINT NOT NULL,
        comentario TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_producto_resenas_pedido_item (pedido_item_id),
        INDEX idx_producto_resenas_producto (producto_id),
        INDEX idx_producto_resenas_usuario (usuario_id),
        INDEX idx_producto_resenas_pedido (pedido_id),
        FOREIGN KEY (pedido_item_id) REFERENCES pedido_items(id) ON DELETE CASCADE,
        FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function producto_counts_by_ids(PDO $pdo, array $productIds): array
{
    if (empty($productIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $sql = "
        SELECT p.id AS producto_id,
               COALESCE(l.like_count, 0) AS likes,
               COALESCE(g.saved_count, 0) AS guardados,
               COALESCE(r.review_count, 0) AS resenas,
               COALESCE(r.avg_rating, 0) AS promedio
        FROM productos p
        LEFT JOIN (
            SELECT producto_id, COUNT(*) AS like_count
            FROM producto_likes
            WHERE producto_id IN ($placeholders)
            GROUP BY producto_id
        ) l ON l.producto_id = p.id
        LEFT JOIN (
            SELECT producto_id, COUNT(*) AS saved_count
            FROM producto_guardados
            WHERE producto_id IN ($placeholders)
            GROUP BY producto_id
        ) g ON g.producto_id = p.id
        LEFT JOIN (
            SELECT producto_id, COUNT(*) AS review_count, AVG(calificacion) AS avg_rating
            FROM producto_resenas
            WHERE producto_id IN ($placeholders)
            GROUP BY producto_id
        ) r ON r.producto_id = p.id
        WHERE p.id IN ($placeholders)
    ";

    $params = array_merge($productIds, $productIds, $productIds, $productIds);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[(int) $row['producto_id']] = [
            'likes' => (int) $row['likes'],
            'guardados' => (int) $row['guardados'],
            'resenas' => (int) $row['resenas'],
            'promedio' => (float) $row['promedio'],
        ];
    }

    return $result;
}

function producto_user_flags_by_ids(PDO $pdo, int $usuarioId, array $productIds): array
{
    if ($usuarioId <= 0 || empty($productIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $likedStmt = $pdo->prepare("SELECT producto_id FROM producto_likes WHERE usuario_id = ? AND producto_id IN ($placeholders)");
    $likedStmt->execute(array_merge([$usuarioId], $productIds));
    $likedMap = array_fill_keys(array_map('intval', $likedStmt->fetchAll(PDO::FETCH_COLUMN)), true);

    $savedStmt = $pdo->prepare("SELECT producto_id FROM producto_guardados WHERE usuario_id = ? AND producto_id IN ($placeholders)");
    $savedStmt->execute(array_merge([$usuarioId], $productIds));
    $savedMap = array_fill_keys(array_map('intval', $savedStmt->fetchAll(PDO::FETCH_COLUMN)), true);

    $flags = [];
    foreach ($productIds as $productId) {
        $pid = (int) $productId;
        $flags[$pid] = [
            'liked' => isset($likedMap[$pid]),
            'saved' => isset($savedMap[$pid]),
        ];
    }

    return $flags;
}
