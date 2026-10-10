# Aplicativo de gestión de envíos (PHP + MySQL)

## Funciones
- Crear envíos con destinatario, dirección y descripción.
- Listar envíos registrados.
- Buscar por destinatario, dirección o descripción.
- Editar y eliminar envíos.
- Crear automáticamente la tabla `envios` si todavía no existe.
- Consultas preparadas, escape de HTML y token CSRF para los formularios.

## Requisitos
- PHP 8.0 o superior (extensiones PDO, pdo_mysql y mbstring).
- MySQL/MariaDB.
- La base de datos `andry_enviosgit` debe existir y el usuario debe tener permisos para crear tablas, consultar, insertar, actualizar y eliminar registros.

## Instalación
1. Sube `index.php` y `config.php` a una carpeta de tu hosting PHP.
2. Revisa las credenciales en `config.php`.
3. Abre `index.php` desde el navegador. La tabla se crea automáticamente al establecer la conexión.
4. Si aparece un error de conexión, confirma en el panel de Alwaysdata que la base de datos exista, que el host sea el correcto y que el usuario tenga permisos.

## Seguridad
Las credenciales se incluyeron según la solicitud. No publiques `config.php` en un repositorio público. Como la contraseña se compartió en el chat, considera cambiarla en el panel de la base de datos y actualizarla en `config.php`. Usa HTTPS en producción.
