<?php
// config/pyme_recursos.php
// Helper para gestionar la tabla de recursos PYMES

function pyme_recursos_ensure_schema(PDO $pdo) {
    try {
        // Crear tabla de recursos si no existe
        $sql = "CREATE TABLE IF NOT EXISTS pyme_recursos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(255) NOT NULL,
            descripcion TEXT,
            beneficios TEXT,
            dirigido_a VARCHAR(255),
            enlace_url VARCHAR(500),
            estado TINYINT(1) DEFAULT 1,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
    } catch (Exception $e) {
        error_log("Error en pyme_recursos_ensure_schema: " . $e->getMessage());
    }
}

function pyme_recursos_obtener_todos(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM pyme_recursos WHERE estado = 1 ORDER BY fecha_creacion DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error en pyme_recursos_obtener_todos: " . $e->getMessage());
        return [];
    }
}

function pyme_recursos_obtener_admin(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM pyme_recursos ORDER BY fecha_creacion DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error en pyme_recursos_obtener_admin: " . $e->getMessage());
        return [];
    }
}

function pyme_recursos_obtener_por_id(PDO $pdo, int $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pyme_recursos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error en pyme_recursos_obtener_por_id: " . $e->getMessage());
        return null;
    }
}

function pyme_recursos_crear(PDO $pdo, string $titulo, string $descripcion, string $beneficios, string $dirigido_a, string $enlace_url) {
    try {
        $stmt = $pdo->prepare("INSERT INTO pyme_recursos (titulo, descripcion, beneficios, dirigido_a, enlace_url, estado) VALUES (?, ?, ?, ?, ?, 1)");
        return $stmt->execute([$titulo, $descripcion, $beneficios, $dirigido_a, $enlace_url]);
    } catch (Exception $e) {
        error_log("Error en pyme_recursos_crear: " . $e->getMessage());
        return false;
    }
}

function pyme_recursos_actualizar(PDO $pdo, int $id, string $titulo, string $descripcion, string $beneficios, string $dirigido_a, string $enlace_url) {
    try {
        $stmt = $pdo->prepare("UPDATE pyme_recursos SET titulo = ?, descripcion = ?, beneficios = ?, dirigido_a = ?, enlace_url = ?, fecha_actualizacion = NOW() WHERE id = ?");
        return $stmt->execute([$titulo, $descripcion, $beneficios, $dirigido_a, $enlace_url, $id]);
    } catch (Exception $e) {
        error_log("Error en pyme_recursos_actualizar: " . $e->getMessage());
        return false;
    }
}

function pyme_recursos_eliminar(PDO $pdo, int $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM pyme_recursos WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (Exception $e) {
        error_log("Error en pyme_recursos_eliminar: " . $e->getMessage());
        return false;
    }
}

function pyme_recursos_cambiar_estado(PDO $pdo, int $id, int $estado) {
    try {
        $stmt = $pdo->prepare("UPDATE pyme_recursos SET estado = ? WHERE id = ?");
        return $stmt->execute([$estado, $id]);
    } catch (Exception $e) {
        error_log("Error en pyme_recursos_cambiar_estado: " . $e->getMessage());
        return false;
    }
}
?>
