<?php

function empresa_metricas_ensure_schema(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $initialized = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS empresa_visitas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        usuario_id INT NULL,
        session_key VARCHAR(128) NOT NULL,
        ip VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_empresa_visitas_empresa_fecha (empresa_id, created_at),
        INDEX idx_empresa_visitas_usuario (usuario_id),
        INDEX idx_empresa_visitas_session (session_key),
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function empresa_metricas_registrar_visita(PDO $pdo, int $empresaId): void
{
    if ($empresaId <= 0) {
        return;
    }

    empresa_metricas_ensure_schema($pdo);

    $usuarioId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $sessionKey = session_id() ?: bin2hex(random_bytes(16));
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $userAgent = $userAgent !== null ? substr($userAgent, 0, 255) : null;

    $stmt = $pdo->prepare(
        "INSERT INTO empresa_visitas (empresa_id, usuario_id, session_key, ip, user_agent) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$empresaId, $usuarioId ?: null, $sessionKey, $ip, $userAgent]);
}
