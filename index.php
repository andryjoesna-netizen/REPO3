<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$pdo = null;
$error = '';
$notice = '';
$edit = null;

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string)($_POST['csrf'] ?? '');
        if (!hash_equals($_SESSION['csrf'], $token)) {
            throw new RuntimeException('La sesión expiró. Recarga la página e inténtalo de nuevo.');
        }

        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
            $destinatario = trim((string)($_POST['destinatario'] ?? ''));
            $direccion = trim((string)($_POST['direccion'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));

            if ($destinatario === '' || $direccion === '' || $descripcion === '') {
                throw new RuntimeException('Completa destinatario, dirección y descripción.');
            }
            if (mb_strlen($destinatario) > 150 || mb_strlen($direccion) > 255) {
                throw new RuntimeException('El destinatario o la dirección superan la longitud permitida.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE envios SET destinatario=?, direccion=?, descripcion=? WHERE id=?');
                $stmt->execute([$destinatario, $direccion, $descripcion, $id]);
                $_SESSION['flash'] = 'Envío actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO envios (destinatario, direccion, descripcion) VALUES (?, ?, ?)');
                $stmt->execute([$destinatario, $direccion, $descripcion]);
                $_SESSION['flash'] = 'Envío registrado correctamente.';
            }
            header('Location: index.php');
            exit;
        }

        if ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new RuntimeException('Identificador de envío no válido.');
            $stmt = $pdo->prepare('DELETE FROM envios WHERE id=?');
            $stmt->execute([$id]);
            $_SESSION['flash'] = 'Envío eliminado.';
            header('Location: index.php');
            exit;
        }
    }

    if (isset($_GET['edit'])) {
        $id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM envios WHERE id=?');
            $stmt->execute([$id]);
            $edit = $stmt->fetch() ?: null;
        }
    }

    if (!empty($_SESSION['flash'])) {
        $notice = (string)$_SESSION['flash'];
        unset($_SESSION['flash']);
    }

    $search = trim((string)($_GET['q'] ?? ''));
    if ($search !== '') {
        $stmt = $pdo->prepare('SELECT * FROM envios WHERE destinatario LIKE ? OR direccion LIKE ? OR descripcion LIKE ? ORDER BY creado_en DESC, id DESC');
        $like = '%' . $search . '%';
        $stmt->execute([$like, $like, $like]);
        $envios = $stmt->fetchAll();
    } else {
        $envios = $pdo->query('SELECT * FROM envios ORDER BY creado_en DESC, id DESC')->fetchAll();
    }
} catch (Throwable $ex) {
    $error = $ex instanceof PDOException
        ? 'No fue posible conectar o consultar la base de datos. Verifica el host, las credenciales y que la base de datos exista en el servidor.'
        : $ex->getMessage();
    $envios = $envios ?? [];
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de envíos</title>
    <style>
        :root{--ink:#172033;--muted:#687386;--brand:#2457d6;--bg:#f3f6fb;--line:#e1e7f0;--white:#fff;--danger:#c0392b}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--ink)}
        header{background:linear-gradient(120deg,#183a8a,#3979f6);color:white;padding:30px 22px}
        .head,.wrap{max-width:1120px;margin:auto}.head h1{margin:0 0 7px;font-size:clamp(25px,4vw,34px)}.head p{margin:0;color:#e0eaff}
        .wrap{padding:24px 18px 50px;display:grid;grid-template-columns:350px 1fr;gap:22px;align-items:start}
        .card{background:var(--white);border:1px solid var(--line);border-radius:16px;padding:22px;box-shadow:0 8px 24px #1720330a}
        h2{font-size:19px;margin:0 0 18px}.field{margin-bottom:15px}label{display:block;font-size:13px;font-weight:700;margin-bottom:7px}
        input,textarea{width:100%;padding:11px 12px;border:1px solid #ccd5e3;border-radius:9px;font:inherit;color:var(--ink);background:white}
        textarea{min-height:105px;resize:vertical}input:focus,textarea:focus{outline:2px solid #c8d7ff;border-color:var(--brand)}
        .btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:9px;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer;font-size:14px}
        .primary{background:var(--brand);color:white}.secondary{background:#eef2f8;color:#28364d}.danger{background:#fff0ee;color:var(--danger)}
        .actions{display:flex;gap:8px;flex-wrap:wrap}.message{padding:12px 14px;border-radius:10px;margin-bottom:16px;font-size:14px}
        .success{background:#e8f8ef;color:#17683b}.error{background:#fff0ee;color:#9f2d23}
        .toolbar{display:flex;gap:10px;margin-bottom:16px}.toolbar input{flex:1;min-width:0}
        .count{color:var(--muted);font-size:13px;margin:-8px 0 15px}
        .table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:13px 11px;border-bottom:1px solid var(--line);vertical-align:top}
        th{color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.06em}td strong{display:block;margin-bottom:4px}
        .desc{white-space:pre-wrap;overflow-wrap:anywhere;max-width:260px;color:#4d586b}.date{color:var(--muted);font-size:12px;white-space:nowrap}
        .empty{text-align:center;padding:42px 16px;color:var(--muted)}.empty b{display:block;color:var(--ink);margin-bottom:7px}
        form.inline{display:inline}.mobile-label{display:none}
        @media(max-width:850px){.wrap{grid-template-columns:1fr}.toolbar{flex-wrap:wrap}.toolbar input{flex-basis:100%}}
        @media(max-width:560px){header{padding:24px 18px}.card{padding:16px}table,tbody,tr,td{display:block}thead{display:none}tr{padding:10px 0;border-bottom:1px solid var(--line)}td{border:0;padding:7px 3px}.mobile-label{display:block;color:var(--muted);font-size:10px;text-transform:uppercase;font-weight:700;margin-bottom:3px}.desc{max-width:none}.date{white-space:normal}}
    </style>
</head>
<body>
<header><div class="head"><h1>Gestión de envíos</h1><p>Registra, consulta, edita y elimina envíos desde un solo lugar.</p></div></header>
<main class="wrap">
    <section class="card">
        <h2><?= $edit ? 'Editar envío' : 'Registrar envío' ?></h2>
        <?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($notice): ?><div class="message success"><?= e($notice) ?></div><?php endif; ?>
        <form method="post" action="index.php">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">
            <div class="field"><label for="destinatario">Destinatario</label><input id="destinatario" name="destinatario" maxlength="150" required value="<?= e($edit['destinatario'] ?? '') ?>" placeholder="Nombre de quien recibe"></div>
            <div class="field"><label for="direccion">Dirección</label><input id="direccion" name="direccion" maxlength="255" required value="<?= e($edit['direccion'] ?? '') ?>" placeholder="Dirección de entrega"></div>
            <div class="field"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" required placeholder="Contenido o detalles del envío"><?= e($edit['descripcion'] ?? '') ?></textarea></div>
            <div class="actions">
                <button class="btn primary" type="submit"><?= $edit ? 'Guardar cambios' : 'Registrar envío' ?></button>
                <?php if ($edit): ?><a class="btn secondary" href="index.php">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </section>
    <section class="card">
        <h2>Envíos registrados</h2>
        <form class="toolbar" method="get" action="index.php">
            <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Buscar por destinatario, dirección o descripción">
            <button class="btn primary" type="submit">Buscar</button>
            <?php if (!empty($_GET['q'])): ?><a class="btn secondary" href="index.php">Limpiar</a><?php endif; ?>
        </form>
        <p class="count"><?= count($envios) ?> resultado(s)</p>
        <?php if (!$envios): ?>
            <div class="empty"><b>No hay envíos para mostrar</b>Registra un envío usando el formulario.</div>
        <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Destinatario</th><th>Dirección</th><th>Descripción</th><th>Registro</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($envios as $envio): ?><tr>
            <td><span class="mobile-label">Destinatario</span><strong><?= e($envio['destinatario']) ?></strong></td>
            <td><span class="mobile-label">Dirección</span><?= e($envio['direccion']) ?></td>
            <td><span class="mobile-label">Descripción</span><div class="desc"><?= e($envio['descripcion']) ?></div></td>
            <td><span class="mobile-label">Registro</span><span class="date"><?= e($envio['creado_en']) ?></span></td>
            <td><span class="mobile-label">Acciones</span><div class="actions">
                <a class="btn secondary" href="index.php?edit=<?= (int)$envio['id'] ?>">Editar</a>
                <form class="inline" method="post" action="index.php" onsubmit="return confirm('¿Seguro que deseas eliminar este envío?');">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$envio['id'] ?>">
                    <button class="btn danger" type="submit">Eliminar</button>
                </form>
            </div></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
