# Documentación: Directorio de Empresas 📱

**Autor:** Estudiante de Desarrollo Web  
**Fecha:** Marzo 2026  
**Asignatura:** Desarrollo de Aplicaciones Web II  

---

## 1. Introducción

Este proyecto es un **directorio web de empresas** que permite a los usuarios navegar, buscar y visualizar información de diferentes negocios organizados por categorías. Es un proyecto académico desarrollado en **PHP con MySQL**, utilizando patrones modernos de desarrollo como **PDO** para la conexión a bases de datos y **sesiones seguras** para la autenticación.

El objetivo principal es crear una plataforma donde:
- **Usuarios normales** pueden explorar y buscar empresas
- **Administradores** pueden gestionar el contenido del sitio
- Las empresas se almacenan organizadamente por categorías

Es básicamente un "Páginas Amarillas" digital pero en versión pequeña para la universidad.

---

## 2. Arquitectura General del Proyecto

El proyecto sigue una estructura **MVC-simplificado** con capas bien definidas:

```
directorio_empresas/
├── config/              # Configuración de la app
│   ├── db.php          # Conexión a BD y sesiones
│   ├── csrf.php        # Protección CSRF
│   └── upload.php      # Gestión de archivos
├── auth/               # Autenticación
│   ├── login.php       # Login de usuarios
│   ├── logout.php      # Cierre de sesión
│   └── register.php    # Registro
├── admin/              # Panel administrativo
│   ├── dashboard.php   # Estadísticas
│   ├── empresas.php    # CRUD de empresas
│   ├── categorias.php  # CRUD de categorías
│   ├── usuarios.php    # Gestión de usuarios
│   └── sidebar.php     # Menú del admin
├── user/               # Panel del usuario (empresario)
│   ├── dashboard.php   # Ver mis empresas
│   ├── crear_empresa.php
│   ├── editar_empresa.php
│   └── eliminar_empresa.php
├── templates/          # Template reutilizables
│   ├── header.php      # Cabecera HTML
│   └── footer.php      # Pie de página
├── uploads/            # Archivos subidos
│   ├── logos/          # Logos de empresas
│   └── categorias/     # Imágenes de categorías
├── css/                # Estilos (Bootstrap)
├── js/                 # Código JavaScript
├── index.php           # Página principal
├── buscar.php          # Búsqueda
├── categoria.php       # Ver categoría
└── empresa.php         # Ver detalle empresa
```

### **Tecnologías Utilizadas**

| Capa | Tecnología |
|------|-----------|
| **Frontend** | HTML5, CSS3 (Bootstrap 5), JavaScript |
| **Backend** | PHP 7.4+ |
| **Base de Datos** | MySQL 5.7+ |
| **Servidor** | Apache (XAMPP) |
| **Seguridad** | PDO, password_hash(), Sesiones seguras |

---

## 3. Modelo Entidad-Relación (MER)

### 3.1 Diagrama E-R

```
┌─────────────────┐         ┌──────────────────┐
│   USUARIOS      │         │   CATEGORIAS     │
├─────────────────┤         ├──────────────────┤
│ id (PK)         │         │ id (PK)          │
│ nombre          │         │ nombre           │
│ email (UNIQUE)  │         │ descripcion      │
│ password (hash) │         │ imagen           │
│ rol             │◄────┐   │ fecha_creacion   │
│ fecha_registro  │     │   │                  │
│ activo          │     │   └──────────────────┘
└─────────────────┘     │         △
      △                 │         │
      │                 │    ┌────┴──────────────┐
      │                 │    │                   │
      │            ┌────┴────────────┐      ┌────────────────┐
      │            │   EMPRESAS      │      │  categorias_id │
      │            ├─────────────────┤      │ (FK a CATEGORIAS)
      │            │ id (PK)         │      └────────────────┘
      │            │ nombre          │
      │            │ descripcion     │
      │            │ direccion       │
      │            │ telefono        │
      │            │ email           │
      │            │ website         │
      │            │ logo            │
      │     ┌──────┤ usuario_id (FK) │
      │     │      │ categoria_id    │
      │     │      │ aprobada        │
      │     │      │ fecha_creacion  │
      │     │      │ fecha_modificacion
      │     │      └─────────────────┘
      │     │
      └─────┴────(propietario de empresa)
```

### 3.2 Descripción de Tablas

#### **USUARIOS**
```sql
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,  -- hash bcrypt
    rol ENUM('user', 'admin') DEFAULT 'user',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE
);
```

**Explicación:**
- `id`: Identificador único de cada usuario
- `email`: Único, no puede repetirse (UNIQUE)
- `password`: Se almacena con **hash bcrypt** (nunca en texto plano)
- `rol`: Define si es usuario normal ('user') o administrador ('admin')
- `activo`: Para dar de baja usuarios sin eliminarlos (soft delete)

#### **CATEGORIAS**
```sql
CREATE TABLE categorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    imagen VARCHAR(255),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Explicación:**
- Almacena los tipos de negocio (Restaurantes, Talleres, Tiendas, etc.)
- `imagen`: Path al archivo de imagen que se muestra en el carrusel
- Los datos son relativamente estáticos

#### **EMPRESAS**
```sql
CREATE TABLE empresas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    direccion VARCHAR(200),
    telefono VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(255),
    logo VARCHAR(255),
    usuario_id INT NOT NULL,
    categoria_id INT NOT NULL,
    aprobada BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
        ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);
```

**Explicación:**
- Es la tabla central del proyecto
- `usuario_id`: Referencia quién es dueño de la empresa
- `categoria_id`: A qué categoría pertenece
- `aprobada`: El admin debe aprobar las empresas antes de que sean visibles
- `fecha_modificacion`: Se actualiza automáticamente cada vez que se edita

### 3.3 Relaciones

1. **Usuario → Empresa (1 a N)**
   - Un usuario puede tener varias empresas
   - Una empresa pertenece a exactamente un usuario

2. **Categoría → Empresa (1 a N)**
   - Una categoría contiene muchas empresas
   - Una empresa pertenece a una categoría

---

## 4. Conceptos Importantes de Desarrollo

### 4.1 Seguridad

#### **a) Inyección SQL - Prevención con PDO**

**MAL (vulnerable):**
```php
$email = $_POST['email'];
$sql = "SELECT * FROM usuarios WHERE email = '$email'";
// Un atacante puede escribir: ' OR '1'='1
```

**BIEN (seguro con PDO):**
```php
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();
```

El `prepare()` y `execute()` separan el código SQL de los datos. SQL es compilado primero, luego se inyectan los datos.

#### **b) Hash de Contraseñas**

**MAL:**
```php
password_verify($password, $user['password']);
// Si alguien accede la BD, ve las contraseñas en texto plano
```

**BIEN:**
```php
// Al registrar:
$hash = password_hash($_POST['password'], PASSWORD_BCRYPT);

// Al verificar:
if (password_verify($_POST['password'], $user['password_hash'])) {
    // Login exitoso
}
```

Se usa **bcrypt** que es unidireccional. Aunque alguien robe la BD, no puede obtener las contraseñas.

#### **c) Session Fixation**

```php
// Después de login exitoso:
session_regenerate_id(true);
```

Esto evita que un atacante fije un ID de sesión y luego robe el acceso del usuario.

#### **d) Protección CSRF**

Aunque en el código veo el archivo `csrf.php`, es importante generar tokens únicos para cada formulario:

```php
// Generar token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Enviar en formulario
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

// Verificar
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("CSRF detectado");
}
```

### 4.2 Autenticación y Autorización

**Autenticación:** ¿Quién eres? (login)  
**Autorización:** ¿Qué puedes hacer? (rol)

```php
// En cada página protegida:
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit;
}

// Si es admin:
if ($_SESSION['user_role'] != 'admin') {
    die("Acceso denegado");
}
```

### 4.3 Manejo de Archivos (Upload)

Los archivos subidos se guardan en `/uploads/`:
- Logos → `/uploads/logos/`
- Imágenes categorías → `/uploads/categorias/`

**Seguridad en uploads:**
```php
// Validar tipo de archivo
$allowed = ['jpg', 'jpeg', 'png', 'gif'];
$ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    die("Tipo de archivo no permitido");
}

// Generar nombre único (evita colisiones)
$nuevo_nombre = uniqid() . '.' . $ext;

// Guardar
move_uploaded_file($_FILES['logo']['tmp_name'], "uploads/logos/$nuevo_nombre");
```

### 4.4 Validación de Entrada

```php
// Siempre sanitizar lo que viene del usuario:
$nombre = htmlspecialchars($_POST['nombre'], ENT_QUOTES, 'UTF-8');

// Esto convierte:
// <script>alert('XSS')</script>
// En:
// &lt;script&gt;alert('XSS')&lt;/script&gt;
```

### 4.5 Base de Datos - Relaciones

Hay relaciones **1 a N**:

```php
// Obtener empresa con su categoría (JOIN):
$stmt = $pdo->prepare("
    SELECT e.*, c.nombre as categoria_nombre 
    FROM empresas e 
    LEFT JOIN categorias c ON e.categoria_id = c.id 
    WHERE e.id = ?
");
$stmt->execute([$id]);
```

**LEFT JOIN:** Si una empresa no tiene categoría, igual aparece. Si fuera INNER JOIN, NO aparecería.

### 4.6 Sesiones Seguras

En `config/db.php`:
```php
session_set_cookie_params([
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,          // JS no puede acceder (XSS)
    'samesite' => 'Lax'          // CSRF
]);
```

---

## 5. Flujos Principales

### 5.1 Flujo de Registro

```
Usuario llena formulario
        ↓
Validar email no exista
        ↓
Hash de contraseña (bcrypt)
        ↓
INSERT en tabla usuarios
        ↓
Mensaje de éxito, redirigir a login
```

### 5.2 Flujo de Login

```
Usuario introduce email/contraseña
        ↓
SELECT usuario por email
        ↓
password_verify() con hash
        ↓
NO → Mostrar error
        ↓
SÍ → Guardar datos en $_SESSION
        ↓
session_regenerate_id() (evitar session fixation)
        ↓
Redirigir a dashboard
```

### 5.3 Flujo de Crear Empresa

```
Usuario va a crear_empresa.php
        ↓
Valida que esté logueado
        ↓
Usuario llena formulario + sube logo
        ↓
Valida datos
        ↓
Guarda logo en /uploads/logos/
        ↓
INSERT empresa con usuario_id actual
        ↓
Empresa queda con aprobada = FALSE
        ↓
Admin debe aprobar desde dashboard
        ↓
Una vez aprobada, aparece a todos los usuarios
```

### 5.4 Flujo de Búsqueda

```
Usuario escribe "Restaurant" y presiona Buscar
        ↓
buscar.php recibe parámetro GET: q=Restaurant
        ↓
LIKE SQL busca en nombre y descripción
        ↓
Muestra solo empresas con aprobada = TRUE
        ↓
Resultados con paginación
```

---

## 6. Funcionalidades Principales

### **Para Usuarios Normales:**
- ✅ Registrarse y crear cuenta
- ✅ Buscar empresas por palabra clave
- ✅ Explorar por categorías
- ✅ Ver detalles de empresa (contacto, info)
- ✅ Crear sus propias empresas
- ✅ Panel personal para editar/eliminar sus empresas

### **Para Administradores:**
- ✅ Dashboard con estadísticas (total empresas, usuarios, etc.)
- ✅ Aprobar/rechazar empresas nuevas
- ✅ CRUD completo de empresas
- ✅ CRUD de categorías
- ✅ Gestión de usuarios

---

## 7. Patrones de Código Usados

### 7.1 PDO (PHP Data Objects)

Permite acceso a múltiples BD con interfaz uniforme.

```php
// Preparar (compila el SQL)
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");

// Ejecutar (inyecta datos)
$stmt->execute([$email]);

// Obtener resultado
$user = $stmt->fetch();  // Una fila
$usuarios = $stmt->fetchAll();  // Todas
```

### 7.2 Sesiones para Mantener Estado

```php
session_start();
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_role'] = $user['rol'];

// En otra página:
if (isset($_SESSION['user_id'])) {
    echo "Hola " . $_SESSION['user_name'];
}
```

### 7.3 Templates (Reutilización)

En lugar de repetir HTML:

```php
// header.php tiene la barra de navegación
// footer.php tiene el pie
// Se incluyen en cada página:

<?php include 'templates/header.php'; ?>
<!-- contenido específico -->
<?php include 'templates/footer.php'; ?>
```

### 7.4 Validación y Santización

```php
// Validar que no sea vacío
if (empty($_POST['nombre'])) {
    $errors[] = "El nombre es requerido";
}

// Santizar (escapar caracteres especiales)
$nombre = htmlspecialchars($_POST['nombre'], ENT_QUOTES, 'UTF-8');
```

---

## 8. Configuración y Ejecución

### 8.1 Requisitos
- PHP 7.4+
- MySQL 5.7+
- Apache
- XAMPP (ya incluye todo)

### 8.2 Pasos para Correr Localmente

1. **Coloca los archivos en `/xampp/htdocs/directorio_empresas/`**

2. **Crea la base de datos:**
   ```sql
   CREATE DATABASE directorio_db;
   ```

3. **Crea las tablas** (ejecuta en phpMyAdmin):
   ```sql
   -- Tabla de usuarios
   CREATE TABLE usuarios (
       id INT PRIMARY KEY AUTO_INCREMENT,
       nombre VARCHAR(100) NOT NULL,
       email VARCHAR(100) UNIQUE NOT NULL,
       password VARCHAR(255) NOT NULL,
       rol ENUM('user', 'admin') DEFAULT 'user',
       fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       activo BOOLEAN DEFAULT TRUE
   );

   -- Tabla de categorías
   CREATE TABLE categorias (
       id INT PRIMARY KEY AUTO_INCREMENT,
       nombre VARCHAR(100) NOT NULL,
       descripcion TEXT,
       imagen VARCHAR(255),
       fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );

   -- Tabla de empresas
   CREATE TABLE empresas (
       id INT PRIMARY KEY AUTO_INCREMENT,
       nombre VARCHAR(150) NOT NULL,
       descripcion TEXT,
       direccion VARCHAR(200),
       telefono VARCHAR(20),
       email VARCHAR(100),
       website VARCHAR(255),
       logo VARCHAR(255),
       usuario_id INT NOT NULL,
       categoria_id INT NOT NULL,
       aprobada BOOLEAN DEFAULT FALSE,
       fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
       FOREIGN KEY (categoria_id) REFERENCES categorias(id)
   );
   ```

4. **Inicia Apache y MySQL** desde XAMPP Control Panel

5. **Accede a:** `http://localhost/directorio_empresas`

### 8.3 Crear Usuario Admin (para pruebas)

En phpMyAdmin, ejecuta:
```sql
INSERT INTO usuarios (nombre, email, password, rol) 
VALUES (
    'Admin', 
    'admin@ejemplo.com', 
    '$2y$10$...',  -- hash de password_hash('123456', PASSWORD_BCRYPT)
    'admin'
);
```

---

## 9. Desafíos Encontrados

### **1. Rutas dinámicas**
Los archivos están en carpetas diferentes (admin/, auth/, user/). Para que los includes funcionen, usamos `$base_url`:
```php
$base_url = '/directorio_empresas';
// En links:
<a href="<?= $base_url ?>/admin/empresas.php">Empresas</a>
```

### **2. Diferencia entre borrado lógico y físico**
- **Físico:** `DELETE FROM usuarios WHERE id = ?` (se pierde todo)
- **Lógico:** `UPDATE usuarios SET activo = FALSE` (no pierde datos, se puede restaurar)

Elegimos lógico con la columna `activo` para no perder datos históricos.

### **3. Aprobación de empresas**
Las empresas nuevas quedan con `aprobada = FALSE` hasta que el admin las revise. Esto evita spam.

---

## 10. Conclusiones y Aprendizajes

### **Lo más importante:**

1. **Seguridad primero:** PDO, hash, CSRF, XSS
2. **Estructura clara:** Separar autenticación, admin, usuario normal
3. **Bases de datos:** Las relaciones (FK) mantienen integridad
4. **Reutilización:** Templates y funciones reutilizables
5. **Sesiones:** Mantienen estado entre páginas HTTP

### **Lo que aprendí:**

Este proyecto me mostró que una aplicación web "simple" requiere muchas consideraciones de seguridad. El 80% del código es validación y protección, solo el 20% es funcionalidad visible.

También entendí por qué existen frameworks como Laravel: automatizar toda esta infraestructura de seguridad, BD, y rutas ahorra muchísimo tiempo y errores.

---

## 11. Referencias y Recursos

- **Documentación oficial PHP:** https://www.php.net/docs.php
- **MySQL Joins:** https://dev.mysql.com/doc/
- **OWASP (Seguridad web):** https://owasp.org/
- **Bootstrap 5:** https://getbootstrap.com/

---

**Nota final:** Este proyecto era un trabajo académico para aprender los conceptos fundamentales de desarrollo web. En un proyecto real, se usaría un framework como Laravel o Symfony para mejor mantenibilidad.

---

## 11. Optimización SEO

### 11.1 Introducción
Se implementó optimización SEO básica para mejorar la visibilidad del sitio en motores de búsqueda como Google. Esto incluye meta tags dinámicas, sitemap XML y configuración para indexación.

### 11.2 MetaTags Dinámicas
**Archivo modificado:** `templates/header.php`

Se agregaron meta tags dinámicas que cambian según la página:
- **Title:** Dinámico basado en contenido (ej: "Nombre Empresa - Directorio de Empresas")
- **Description:** Resumen del contenido (máx 160 caracteres)
- **Keywords:** Palabras clave relevantes
- **Open Graph:** Para compartir en redes sociales (Facebook, Twitter)
- **Canonical URL:** Evita contenido duplicado

**Variables PHP usadas:**
```php
$page_title = 'Título de la página';
$page_description = 'Descripción SEO';
$page_keywords = 'palabra1, palabra2';
$og_image = 'url/de/imagen'; // Para compartir
```

**Ejemplos de implementación:**
- **index.php:** Title general del sitio
- **empresa.php:** Title con nombre de empresa, description de su descripción
- **categoria.php:** Title con nombre de categoría

### 11.3 Sitemap XML
**Archivo creado:** `sitemap.php`

Genera un sitemap XML dinámico que incluye:
- Página principal (prioridad 1.0, frecuencia diaria)
- Todas las categorías (prioridad 0.8, frecuencia semanal)
- Todas las empresas (prioridad 0.6, frecuencia mensual)

**Estructura del XML:**
```xml
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>http://localhost/directorio_empresas/index.php</loc>
        <lastmod>2026-03-27</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <!-- Más URLs -->
</urlset>
```

**Acceso:** `http://localhost/directorio_empresas/sitemap.php`

### 11.4 Robots.txt
**Archivo creado:** `robots.txt`

Configuración básica que permite indexación completa:
```
User-agent: *
Allow: /

Sitemap: http://localhost/directorio_empresas/sitemap.php
```

### 11.5 Indexación en Google
Para indexar el sitio en Google Search Console:

1. **Crear cuenta** en Google Search Console
2. **Agregar propiedad:** URL del sitio
3. **Verificar propiedad** (archivo HTML o DNS)
4. **Enviar sitemap:** `sitemap.php`
5. **Monitorear indexación** y errores

**Notas importantes:**
- **Localhost:** Google no indexa localhost. Para pruebas usar dominio público.
- **Producción:** Cambiar `localhost` por dominio real en `sitemap.php` y `robots.txt`.
- **URLs absolutas:** Asegurar que todas las URLs sean absolutas para SEO.

### 11.6 Beneficios Implementados
- ✅ Mejora en rankings de búsqueda
- ✅ Mejor experiencia en redes sociales
- ✅ Indexación automática de nuevas páginas
- ✅ Evita contenido duplicado con canonical URLs
- ✅ Compatibilidad con herramientas de SEO

### 11.7 Archivos Modificados/Creados
- **Modificados:**
  - `templates/header.php` (meta tags dinámicas)
  - `index.php` (variables SEO)
  - `empresa.php` (variables SEO)
  - `categoria.php` (variables SEO)

- **Creados:**
  - `sitemap.php` (sitemap XML dinámico)
  - `robots.txt` (configuración de bots)

