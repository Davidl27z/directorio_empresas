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

### 3.4 Roles de Usuario

El sistema cuenta con tres tipos de usuarios con diferentes permisos:

#### **CLIENTE** (`cliente`)
- Puede explorar empresas y productos
- Agregar productos al carrito (incluso sin login)
- Hacer pedidos (requiere registro/login)
- Ver historial de pedidos
- Gestionar su perfil

#### **EMPRESA** (`empresa`)  
- Todas las funciones de cliente
- Crear y gestionar empresas
- Administrar catálogo de productos
- Ver pedidos recibidos
- Gestionar estado de pedidos
- Contactar clientes

#### **ADMIN** (`admin`)
- Todas las funciones de empresa
- Gestionar categorías
- Aprobar/rechazar empresas
- Gestionar usuarios
- Acceso a estadísticas globales

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

### 5.5 Flujo de E-Commerce

```
Usuario anónimo
        ↓
Explora productos en empresa.php
        ↓
Agrega productos al carrito (sesión)
        ↓
Va a carrito.php
        ↓
Hace clic "Hacer Pedido"
        ↓
Redirige a login.php (si no logueado)
        ↓
Después de login/registro → hacer_pedido.php
        ↓
Elige método de contacto
        ↓
Confirma pedido
        ↓
BD: pedidos + pedido_items
        ↓
Empresa ve pedido en dashboard
        ↓
Empresa contacta cliente por método elegido
        ↓
Cliente recibe productos/paga según acuerdo
```

---

## 6. Funcionalidades Principales

### **Para Clientes:**
- ✅ Registrarse como cliente
- ✅ Explorar empresas y productos
- ✅ Agregar productos al carrito (anónimo)
- ✅ Hacer pedidos (requiere login)
- ✅ Elegir método de contacto para pedidos
- ✅ Ver historial de pedidos

### **Para Empresas:**
- ✅ Registrarse como empresa
- ✅ Todas las funciones de cliente
- ✅ Crear y gestionar empresas
- ✅ Administrar catálogo de productos
- ✅ Ver pedidos recibidos
- ✅ Gestionar estado de pedidos
- ✅ Contactar clientes por método elegido

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

---

## 12. E-Commerce Básico

### 12.1 Introducción
Se implementó un sistema de E-Commerce básico donde cada empresa puede tener su propio catálogo de productos. Los usuarios pueden agregar productos al carrito y hacer pedidos sin pago real, usando métodos de contacto para coordinación.

### 12.2 Modelo de Datos
**Nuevas tablas agregadas:**

#### **PRODUCTOS**
```sql
CREATE TABLE productos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL,
    imagen VARCHAR(255),
    empresa_id INT NOT NULL,
    disponible BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);
```

#### **PEDIDOS**
```sql
CREATE TABLE pedidos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    empresa_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    metodo_contacto ENUM('whatsapp', 'formulario', 'correo') NOT NULL,
    detalles_contacto TEXT,
    notas TEXT,
    estado ENUM('pendiente', 'procesando', 'completado', 'cancelado') DEFAULT 'pendiente',
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);
```

#### **PEDIDO_ITEMS**
```sql
CREATE TABLE pedido_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pedido_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);
```

### 12.3 Funcionalidades Implementadas

#### **Para Propietarios de Empresas:**
- ✅ Crear, editar, eliminar productos desde `user/productos.php`
- ✅ Gestionar catálogo de productos con imágenes
- ✅ Ver pedidos recibidos en `user/dashboard.php`
- ✅ Gestionar estado de pedidos en `user/ver_pedido.php`
- ✅ Contactar clientes por método elegido

#### **Para Usuarios Compradores:**
- ✅ Registrarse como cliente o empresa
- ✅ Ver productos en páginas de empresa
- ✅ Agregar productos al carrito (funciona anónimo)
- ✅ Gestionar carrito con cantidades
- ✅ Hacer pedidos separados por empresa (requiere login)
- ✅ Elegir método de contacto: WhatsApp, Teléfono+Email, o Email
- ✅ Agregar notas al pedido
- ✅ Ver confirmación de pedido

#### **Sistema de Carrito:**
- ✅ Almacenamiento en sesiones PHP
- ✅ Agrupación de productos por empresa
- ✅ Cálculo automático de totales
- ✅ Actualización de cantidades en tiempo real

### 12.4 Flujo de Compra

```
Usuario ve productos en empresa.php
        ↓
Hace clic "Agregar al Carrito"
        ↓
Productos se almacenan en $_SESSION['carrito']
        ↓
Usuario va a carrito.php
        ↓
Ve productos agrupados por empresa
        ↓
Hace clic "Hacer Pedido" para cada empresa
        ↓
hacer_pedido.php: Elige método de contacto
        ↓
Se crea registro en BD (pedidos + pedido_items)
        ↓
pedido_confirmado.php: Muestra resumen
        ↓
Empresa contacta al usuario por método elegido
```

### 12.5 Métodos de Contacto

1. **WhatsApp:** Usuario proporciona número, empresa inicia chat
2. **Teléfono + Email:** Datos completos para contacto múltiple
3. **Email:** Contacto por correo electrónico

### 12.6 Archivos Creados/Modificados

#### **Nuevos Archivos:**
- `temp_ecommerce_setup.php` (script de instalación de BD)
- `temp_update_roles.php` (actualización de roles de usuario)
- `carrito.php` (gestión del carrito de compras)
- `hacer_pedido.php` (formulario de pedido)
- `pedido_confirmado.php` (confirmación de pedido)
- `user/productos.php` (CRUD de productos)
- `user/api_productos.php` (API para gestión de productos)
- `user/ver_pedido.php` (detalles de pedidos para empresas)

#### **Archivos Creados:**
- `agregar_carrito.php` (endpoint AJAX para agregar productos al carrito)

#### **Archivos Modificados:**
- `auth/register.php` (corrección de error de sintaxis - código duplicado)
- `js/main.js` (función `agregarAlCarrito()` con manejo de usuarios no autenticados)
- `DOCUMENTACION.md` (esta documentación)

#### **Carpetas Creadas:**
- `uploads/productos/` (imágenes de productos)

### 12.7 Consideraciones de Seguridad
- ✅ Validación de propiedad de productos (solo dueño puede editar)
- ✅ Sanitización de inputs
- ✅ Protección CSRF en formularios
- ✅ Validación de archivos de imagen
- ✅ Sesiones seguras para carrito

### 12.8 Limitaciones Actuales
- ❌ No hay sistema de pagos integrado (por diseño)
- ❌ No hay inventario/stock tracking
- ❌ No hay envío automático de emails
- ❌ No hay panel de administración de pedidos para empresas

### 12.9 Expansión Futura
Posibles mejoras:
- Sistema de reseñas de productos
- Cupones de descuento
- Seguimiento de pedidos
- Notificaciones por email
- Integración con pasarelas de pago
- API para apps móviles

---

## 13. Mejoras Recientes de UI/UX (Marzo 2026)

### 13.1 Introducción
Se implementaron mejoras significativas en la interfaz de usuario y experiencia de navegación para optimizar el flujo de compra y la usabilidad general del sitio.

### 13.2 Icono de Carrito con Contador Dinámico
**Archivo modificado:** `templates/header.php`

Se agregó un icono de carrito persistente en la barra de navegación con las siguientes características:

- **Contador dinámico:** Muestra el número total de productos en el carrito
- **Posicionamiento absoluto:** Badge rojo con Bootstrap que se superpone al icono
- **Actualización automática:** El contador se actualiza en todas las páginas
- **Enlace directo:** Click lleva directamente a `carrito.php`

**Implementación técnica:**
```php
// Contar productos en carrito
$carrito_count = 0;
if (isset($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $empresa_id => $productos) {
        $carrito_count += count($productos);
    }
}

// HTML del icono
<a href="carrito.php" class="btn btn-outline-primary position-relative">
    <i class="fas fa-shopping-cart"></i>
    <?php if ($carrito_count > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $carrito_count ?>
        </span>
    <?php endif; ?>
</a>
```

**Beneficios:**
- ✅ Visibilidad constante del estado del carrito
- ✅ Mejor experiencia de usuario (no hay que recordar si agregaron productos)
- ✅ Diseño responsive con Bootstrap
- ✅ Acceso rápido al carrito desde cualquier página

### 13.3 Corrección de Redirección de Login
**Archivo modificado:** `auth/login.php`

Se implementó un sistema de redirección inteligente después del login que prioriza:

1. **URL guardada:** Si el usuario fue redirigido al login desde una página protegida
2. **Dashboard apropiado:** Según el rol del usuario
   - **Admin:** `admin/dashboard.php`
   - **Cliente/Empresa:** `user/dashboard.php`

**Lógica implementada:**
```php
// Después de login exitoso
if (isset($_SESSION['redirect_after_login'])) {
    $redirect = $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']);
    header("Location: $redirect");
    exit;
} elseif ($_SESSION['user_role'] == 'admin') {
    header("Location: admin/dashboard.php");
} else {
    header("Location: user/dashboard.php");
}
```

**Beneficios:**
- ✅ Eliminación de redirecciones incorrectas
- ✅ Mejor flujo de usuario (regresa donde estaba)
- ✅ Separación clara entre roles de usuario
- ✅ Experiencia más intuitiva

### 13.4 Archivos Modificados
- **Modificados:**
  - `templates/header.php` (icono de carrito con contador)
  - `auth/login.php` (lógica de redirección inteligente)
  - `DOCUMENTACION.md` (esta documentación)

### 13.5 Impacto en la Experiencia de Usuario
- **Antes:** Carrito invisible, redirecciones confusas después del login
- **Después:** Carrito siempre visible con contador, redirecciones apropiadas según rol

### 13.6 Próximas Mejoras Planeadas
- Implementación de notificaciones por email para pedidos
- Sistema de reseñas y calificaciones
- Panel de administración de pedidos para empresas
- Optimización móvil adicional

---

## 14. Correcciones de Bugs (Marzo 2026)

### 14.1 Introducción
Se corrigieron errores críticos que impedían el funcionamiento básico del sistema de registro y carrito de compras.

### 14.2 Error de Sintaxis en Registro
**Archivo modificado:** `auth/register.php`

**Problema:** Error de parse "Unclosed '{' on line 8" causado por código duplicado y mal estructurado en el bloque try-catch.

**Solución:** Eliminación del código duplicado y reestructuración correcta del manejo de excepciones.

**Código corregido:**
```php
try {
    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nombre, $email, $password, $rol]);
    $success = "¡Registro exitoso! <a href='login.php'>Inicia sesión</a>";
    if ($rol === 'admin') {
        $success .= " (Primer usuario creado como administrador)";
    }
} catch (PDOException $e) {
    $error = "El correo ya está registrado.";
}
```

### 14.3 Funcionalidad del Carrito para Usuarios Anónimos
**Archivos creados/modificados:**
- `agregar_carrito.php` (nuevo endpoint AJAX)
- `js/main.js` (función `agregarAlCarrito()`)

**Problema:** El botón "Agregar al Carrito" no funcionaba porque la función JavaScript no existía.

**Solución:** 
- Creación de endpoint `agregar_carrito.php` que maneja la lógica de agregar productos
- Implementación de función JavaScript que hace petición AJAX
- Verificación de autenticación: usuarios no logueados son redirigidos al login

**Flujo implementado:**
```
Usuario hace clic "Agregar al Carrito"
        ↓
JavaScript envía petición AJAX a agregar_carrito.php
        ↓
PHP verifica si usuario está autenticado
        ↓
NO → Respuesta JSON con redirect a login.php
        ↓
SÍ → Agrega producto a $_SESSION['carrito']
        ↓
JavaScript muestra toast de éxito y actualiza contador
```

**Características implementadas:**
- ✅ Validación de producto existente
- ✅ Verificación de autenticación
- ✅ Manejo de errores con mensajes toast
- ✅ Actualización visual del contador del carrito
- ✅ Redirección automática para usuarios no autenticados

### 14.4 Archivos Modificados
- **Creados:**
  - `agregar_carrito.php` (endpoint AJAX para carrito)
- **Modificados:**
  - `auth/register.php` (corrección de sintaxis)
  - `js/main.js` (función agregarAlCarrito y utilidades, ruta absoluta)
  - `user/dashboard.php` (interfaces separadas para clientes y empresas)
  - `DOCUMENTACION.md` (documentación de correcciones)

### 14.5 Validaciones Realizadas
- ✅ Sintaxis PHP correcta en todos los archivos
- ✅ Página de registro funcionando (HTTP 200)
- ✅ Endpoint de carrito creado y funcional
- ✅ Función JavaScript implementada con manejo de errores
- ✅ Dashboard muestra interfaces diferentes según rol

---

## 15. Interfaces Diferenciadas por Rol de Usuario (Marzo 2026)

### 15.1 Introducción
Se implementaron interfaces completamente diferentes para clientes y empresas en el dashboard de usuario, eliminando opciones irrelevantes y agregando funcionalidades específicas para cada rol.

### 15.2 Dashboard para Clientes
**Interfaz enfocada en compras:**

- **Estadísticas principales:**
  - Total de pedidos realizados
  - Pedidos pendientes
  - Acceso rápido al carrito

- **Lista de pedidos:**
  - Historial completo de compras
  - Estado de cada pedido con iconos visuales
  - Información de contacto de las empresas
  - Método de contacto elegido
  - Botón para ver detalles de cada pedido

- **Funcionalidades:**
  - ✅ Ver historial de pedidos
  - ✅ Estado de pedidos en tiempo real
  - ✅ Información de contacto de empresas
  - ✅ Enlace directo a explorar más empresas
  - ❌ No muestra opciones de crear empresas

### 15.3 Dashboard para Empresas
**Interfaz enfocada en gestión:**

- **Gestión de empresas:**
  - Lista de empresas propias
  - Estado de aprobación
  - Acciones de editar, eliminar, gestionar productos

- **Creación de empresas:**
  - Formulario completo para agregar nuevas empresas
  - Categorías disponibles
  - Upload de logos

- **Pedidos recibidos:**
  - Lista de pedidos de clientes
  - Información del comprador
  - Estado y método de contacto
  - Acceso a detalles de pedidos

### 15.4 Diferencias Clave

| Característica | Cliente | Empresa |
|---|---|---|
| **Objetivo principal** | Comprar productos | Vender productos |
| **Pedidos** | Historial de compras | Pedidos recibidos |
| **Empresas** | ❌ No gestiona | ✅ Crea y administra |
| **Productos** | ❌ No gestiona | ✅ Administra catálogo |
| **Estadísticas** | Pedidos realizados | Empresas y productos |
| **Navegación** | Explorar empresas | Gestionar negocio |

### 15.5 Implementación Técnica
**Lógica de separación:**
```php
if ($user_role === 'cliente') {
    // Mostrar dashboard de cliente
    // - Estadísticas de pedidos
    // - Historial de compras
} else {
    // Mostrar dashboard de empresa
    // - Gestión de empresas
    // - Pedidos recibidos
}
```

### 15.6 Beneficios Implementados
- ✅ **UX mejorada:** Cada usuario ve solo lo relevante
- ✅ **Navegación clara:** Sin opciones confusas
- ✅ **Funcionalidad enfocada:** Interfaces específicas por objetivo
- ✅ **Seguridad:** No muestra opciones de otros roles

### 15.7 Corrección del Carrito
**Problema identificado:** Ruta relativa incorrecta en JavaScript

**Solución:** Cambiar de ruta relativa a absoluta
```javascript
// Antes (relativa)
fetch('agregar_carrito.php', {

// Después (absoluta)  
fetch('/directorio_empresas/agregar_carrito.php', {
```

**Resultado:** Botón "Agregar al Carrito" ahora funciona correctamente tanto para usuarios autenticados como anónimos.

### 15.8 Archivos Modificados
- **Modificados:**
  - `user/dashboard.php` (interfaces separadas por rol)
  - `js/main.js` (ruta absoluta para endpoint del carrito, incluir credenciales)
  - `admin/usuarios.php` (agregar roles cliente y empresa, mostrar nombres amigables)
  - `DOCUMENTACION.md` (documentación de mejoras)

### 15.9 Próximas Mejoras
- Implementar notificaciones push para nuevos pedidos
- Sistema de reseñas y calificaciones
- Dashboard móvil optimizado
- Exportación de datos de pedidos

---

## 16. Correcciones de Bugs y Mejoras (Marzo 2026)

### 16.1 Introducción
Se corrigieron problemas críticos en el sistema de carrito de compras y se mejoró la gestión de usuarios en el panel de administración.

### 16.2 Corrección del Botón "Agregar al Carrito"
**Problema identificado:** El botón no funcionaba porque las peticiones AJAX no incluían las cookies de sesión.

**Solución implementada:**
```javascript
fetch('/directorio_empresas/agregar_carrito.php', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'  // ✅ Incluir cookies de sesión
})
```

**Resultado:** El botón "Agregar al Carrito" ahora funciona correctamente para usuarios autenticados y muestra el mensaje de login para usuarios anónimos.

### 16.3 Gestión Completa de Roles de Usuario
**Problema:** El panel de administración solo mostraba roles "Usuario" y "Administrador", faltando "Cliente" y "Empresa".

**Correcciones implementadas:**

#### **Formulario de Edición:**
```html
<select name="rol" class="form-select">
    <option value="cliente">Cliente</option>
    <option value="empresa">Empresa</option>
    <option value="admin">Administrador</option>
</select>
```

#### **Validación de Roles:**
```php
$rol = in_array($_POST['rol'] ?? '', ['admin', 'cliente', 'empresa']) ? $_POST['rol'] : 'cliente';
```

#### **Visualización Amigable:**
```php
$rol_nombres = [
    'cliente' => 'Cliente',
    'empresa' => 'Empresa', 
    'admin' => 'Administrador'
];
```

### 16.4 Funcionalidades Implementadas
- ✅ **Carrito funcional:** Botón "Agregar al Carrito" opera correctamente
- ✅ **Gestión de roles completa:** Todos los roles (cliente, empresa, admin) disponibles
- ✅ **Interfaz amigable:** Nombres de roles en español en la tabla
- ✅ **Validación robusta:** Lógica de roles corregida y simplificada

### 16.5 Archivos Modificados
- **Modificados:**
  - `js/main.js` (credenciales en petición AJAX)
  - `admin/usuarios.php` (roles completos y visualización amigable)
  - `carrito.php` (reestructuración completa para carrito agrupado por empresa)
  - `DOCUMENTACION.md` (documentación de correcciones)

### 16.6 Validaciones Realizadas
- ✅ Sintaxis PHP correcta en archivos modificados
- ✅ Páginas responden correctamente (HTTP 200)
- ✅ Endpoint del carrito incluye credenciales de sesión
- ✅ Panel de administración muestra todos los roles
- ✅ Carrito maneja correctamente la estructura agrupada por empresa

---

## 17. Corrección de Estructura del Carrito (Marzo 2026)

### 17.1 Introducción
Se corrigió un problema crítico en la estructura del carrito de compras que causaba múltiples errores de "Undefined array key".

### 17.2 Problema Identificado
**Errores reportados:**
- `Warning: Undefined array key "empresa_id"`
- `Warning: Undefined array key "empresa_nombre"`
- `Warning: Undefined array key "precio"`
- `Warning: Undefined array key "cantidad"`
- `Warning: Undefined array key "nombre"`

**Causa raíz:** Inconsistencia en la estructura del carrito entre archivos:
- `agregar_carrito.php`: Estructura agrupada por empresa
- `carrito.php`: Esperaba estructura plana por producto

### 17.3 Estructura del Carrito Corregida

#### **Estructura Implementada:**
```php
$_SESSION['carrito'][$empresa_id][] = [
    'id' => $producto_id,
    'nombre' => $producto['nombre'],
    'precio' => $producto['precio'],
    'imagen' => $producto['imagen'],
    'cantidad' => $cantidad
];
```

#### **Lógica de Procesamiento:**
```php
// Calcular totales por empresa
foreach ($_SESSION['carrito'] as $empresa_id => $productos) {
    // Obtener nombre de empresa desde BD
    $stmt = $pdo->prepare("SELECT nombre FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();
    
    // Procesar productos de cada empresa
    $total = 0;
    foreach ($productos as $item) {
        $total += $item['precio'] * $item['cantidad'];
    }
}
```

### 17.4 Funcionalidades Implementadas
- ✅ **Carrito agrupado por empresa:** Productos organizados por empresa vendedora
- ✅ **Actualización de cantidades:** Funciona correctamente con nueva estructura
- ✅ **Eliminación de productos:** Maneja índices correctos del array
- ✅ **Cálculo de totales:** Por empresa y global
- ✅ **Interfaz visual:** Muestra productos agrupados por empresa

### 17.5 Operaciones del Carrito

#### **Actualizar Cantidad:**
```php
foreach ($_SESSION['carrito'] as $empresa_id => &$productos) {
    foreach ($productos as &$item) {
        if ($item['id'] == $producto_id) {
            $item['cantidad'] = $cantidad;
            break 2;
        }
    }
}
```

#### **Eliminar Producto:**
```php
foreach ($_SESSION['carrito'] as $empresa_id => &$productos) {
    foreach ($productos as $key => $item) {
        if ($item['id'] == $producto_id) {
            unset($productos[$key]);
            if (empty($productos)) {
                unset($_SESSION['carrito'][$empresa_id]);
            }
            break 2;
        }
    }
}
```

### 17.6 Archivos Modificados
- **Modificados:**
  - `carrito.php` (reestructuración completa de lógica y vista)
  - `DOCUMENTACION.md` (documentación de correcciones)

### 17.7 Beneficios Implementados
- ✅ **Sin errores de array:** Todas las claves están correctamente definidas
- ✅ **Estructura consistente:** Mismo formato en agregar y mostrar carrito
- ✅ **Funcionalidad completa:** Actualizar, eliminar, calcular totales
- ✅ **Interfaz clara:** Productos agrupados por empresa vendedora

---

## 18. Ajuste Empresa: Carrito → Ventas y Chat (Marzo 2026)

### 18.1 Ajuste en roles
- Las empresas ya no ven sección de carrito en `carrito.php` (redireccionan a `user/dashboard.php`).
- Sección de cliente sigue funcionando con carrito normal.

### 18.2 Botón "Ventas" para empresa
- En `user/dashboard.php`, empresa ahora muestra un botón `📈 Ventas` que lleva a sección de pedidos recibidos.

### 18.3 Chat por pedido
- En `user/ver_pedido.php`, se agregó chat por pedido entre cliente y empresa:
  - Tabla `pedido_mensajes` creada si no existe.
  - Vista de mensajes con autor, rol y timestamp.
  - Formulario para enviar nuevos mensajes.
- Chat está disponible tanto para empresa (pedido propio) como para cliente (pedido propio).

### 18.4 UI en pedido
- Se muestran datos completos para cerrar venta:
  - cliente, email, fecha, estado, método de contacto, detalles de contacto
  - lista de productos con subtotales
  - botones de acción (WhatsApp/Email/Llamar)

### 18.5 Notificaciones internas
- Al crear pedido, se conserva `detalles_contacto` en `pedidos`, y se puede llevar conversación dentro del pedido via chat.

---

**Última actualización:** Marzo 2026  
**Versión:** 1.8 - Company Sales + Chat


---

**Última actualización:** Marzo 2026  
**Versión:** 1.4 - Bug Fixes

