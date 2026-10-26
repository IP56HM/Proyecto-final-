<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/config/conexion.php';
$usuario = $_SESSION['usuario'] ?? '';
$rol = $_SESSION['rol'] ?? '';
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['csrf_devoluciones'])) { $_SESSION['csrf_devoluciones'] = bin2hex(random_bytes(32)); }
$mensaje = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_devoluciones'], $_POST['csrf'] ?? '')) {
        $error = 'La sesión del formulario expiró. Actualiza la página.';
    } elseif (isset($_POST['devolver'])) {
        $id_detalle = filter_var($_POST['id_detalle'] ?? null, FILTER_VALIDATE_INT);
        $cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT);
        $motivo = trim($_POST['motivo'] ?? '');
        if (!$id_detalle || !$cantidad || $cantidad < 1 || $cantidad > 100000 || $motivo === '' || mb_strlen($motivo) > 255) {
            $error = 'Selecciona una venta, una cantidad válida y un motivo (máximo 255 caracteres).';
        } else {
            try {
                $conexion->begin_transaction();
                $stmt = $conexion->prepare('SELECT d.id_producto,d.cantidad,i.nombre,i.estado FROM detalle_ventas d JOIN inventario i ON i.id_producto=d.id_producto WHERE d.id_detalle=? FOR UPDATE');
                $stmt->bind_param('i', $id_detalle); $stmt->execute();
                $producto = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$producto || (int)$producto['estado'] !== 1) throw new Exception('Producto o venta no disponibles.');
                $stmt = $conexion->prepare('SELECT COALESCE(SUM(cantidad),0) AS cantidad FROM devoluciones WHERE id_detalle=?');
                $stmt->bind_param('i',$id_detalle);$stmt->execute();
                $yaDevuelto=(int)$stmt->get_result()->fetch_assoc()['cantidad'];$stmt->close();
                if ($cantidad > (int)$producto['cantidad']-$yaDevuelto) throw new Exception('Cantidad mayor que las unidades pendientes de devolver.');
                $id=(int)$producto['id_producto'];
                $stmt=$conexion->prepare('UPDATE inventario SET existencias=existencias+? WHERE id_producto=? AND existencias<=2147483647-?');
                $stmt->bind_param('iii',$cantidad,$id,$cantidad);$stmt->execute();
                if($stmt->affected_rows!==1) throw new Exception('No se pudo actualizar el stock.');$stmt->close();
                $uid=(int)$_SESSION['id_usuario'];
                $stmt=$conexion->prepare('INSERT INTO devoluciones (id_producto,id_usuario,id_detalle,cantidad,motivo,fecha) VALUES (?,?,?,?,?,NOW())');
                $stmt->bind_param('iiiis',$id,$uid,$id_detalle,$cantidad,$motivo);$stmt->execute();$stmt->close();
                $conexion->commit();header('Location: devoluciones.php?ok=1');exit;
            } catch(Throwable $ex) {
                $conexion->rollback();error_log('Devoluciones: '.$ex->getMessage());
                $error='No se pudo registrar la devolución. Revisa la venta, la cantidad y la actualización SQL.';
            }
        }
    }
}
if (isset($_GET['ok'])) $mensaje = 'Devolución registrada y existencias actualizadas.';
$productos = []; $lineas = []; $historial = []; $totalStock = 0; $totalDevoluciones = 0;
try {
    $r = $conexion->query('SELECT id_producto, nombre, existencias FROM inventario WHERE estado = 1 ORDER BY nombre');
    while ($fila = $r->fetch_assoc()) { $productos[] = $fila; $totalStock += (int)$fila['existencias']; }
    $r = $conexion->query('SELECT d.id_detalle,d.id_venta,i.nombre,d.cantidad,d.precio_unitario,COALESCE((SELECT SUM(r.cantidad) FROM devoluciones r WHERE r.id_detalle=d.id_detalle),0) AS devueltas FROM detalle_ventas d JOIN inventario i ON i.id_producto=d.id_producto WHERE i.estado=1 ORDER BY d.id_detalle DESC LIMIT 500'); while ($fila=$r->fetch_assoc()) { if ((int)$fila['cantidad']>(int)$fila['devueltas']) $lineas[]=$fila; }
    $r = $conexion->query('SELECT COUNT(*) AS n FROM devoluciones'); $totalDevoluciones = (int)$r->fetch_assoc()['n'];
    $r = $conexion->query('SELECT d.fecha, i.nombre AS producto, d.cantidad, d.motivo, a.usuario, d.id_detalle FROM devoluciones d JOIN inventario i ON i.id_producto = d.id_producto JOIN administracion a ON a.id_usuario = d.id_usuario ORDER BY d.id_devolucion DESC LIMIT 100');
    while ($fila = $r->fetch_assoc()) $historial[] = $fila;
} catch (Throwable $ex) { $error = 'No se pudieron consultar los datos. Primero ejecuta el archivo SQL incluido en phpMyAdmin.'; }
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Devoluciones | San Sebastián</title><link rel="stylesheet" href="css/dashboard.css"><link rel="stylesheet" href="css/devoluciones.css"></head><body>
<div class="dashboard">
<aside class="sidebar">

            <div class="brand">

                <div class="brand-logo">
                    📚
                </div>

                <div>
                    <h2>San Sebastián</h2>
                    <span>Sistema de Inventario</span>
                </div>

            </div>


            <nav class="menu">

                <a href="dashboard.php">
                    🏠
                    <span>Inicio</span>
                </a>

                <a href="inventario.php">
                    📦
                    <span>Inventario</span>
                </a>
<a href="productos.php">➕ <span>Productos</span></a>

                <a href="ventas.php">
                    🛒
                    <span>Ventas</span>
                </a>

                <a href="compras.php">
                    📥
                    <span>Compras</span>
                </a>

                <a href="analisis.php">
                    📊
                    <span>Análisis</span>
                </a>

                <a href="devoluciones.php" class="active">
                    ↩️
                    <span>Devoluciones</span>
                </a>

                <a href="reportes.php">
                    📄
                    <span>Reportes</span>
                </a>

                <?php if ($rol === "administrador"): ?>

                    <a href="usuarios.php">
                        👥
                        <span>Usuarios</span>
                    </a>

                    <a href="configuracion.php">
                        ⚙️
                        <span>Configuración</span>
                    </a>

                <?php endif; ?>

            </nav>


            <div class="sidebar-bottom">

                <a href="logout.php" class="logout">
                    🚪
                    <span>Cerrar sesión</span>
                </a>

            </div>

        </aside>
<main class="main-content"><header class="topbar"><div><h1>Devoluciones</h1><p>Registra productos devueltos y actualiza las existencias del inventario.</p></div><div class="user-info"><div class="user-avatar"><?= e(strtoupper(substr($usuario,0,1))) ?></div><div><strong><?= e($usuario) ?></strong><span><?= e($rol) ?></span></div></div></header>
<?php if ($mensaje): ?><div class="dev-aviso dev-ok"><?= e($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="dev-aviso dev-error"><?= e($error) ?></div><?php endif; ?>
<section class="dev-stats"><div class="dev-stat"><span>Devoluciones registradas</span><strong><?= $totalDevoluciones ?></strong></div><div class="dev-stat"><span>Productos activos</span><strong><?= count($productos) ?></strong></div><div class="dev-stat"><span>Unidades en inventario</span><strong><?= $totalStock ?></strong></div></section>
<section class="dev-grid"><div class="dev-panel"><h2>↩️ Registrar devolución</h2><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_devoluciones']) ?>"><label for="producto">Producto vendido / venta</label><select name="id_detalle" id="producto" required><option value="">Selecciona una venta</option><?php foreach ($lineas as $p): ?><option value="<?= (int)$p['id_detalle'] ?>">Venta #<?= (int)$p['id_venta'] ?> — <?= e($p['nombre']) ?> (por devolver: <?= (int)$p['cantidad']-(int)$p['devueltas'] ?>)</option><?php endforeach; ?></select><label for="cantidad">Cantidad devuelta</label><input type="number" id="cantidad" name="cantidad" min="1" max="100000" required><label for="motivo">Motivo de devolución</label><textarea name="motivo" id="motivo" maxlength="255" required placeholder="Explica el motivo..."></textarea><button class="dev-button" type="submit" name="devolver" value="1">Registrar devolución</button></form></div><div class="dev-panel"><h2>Información</h2><p>Al registrar una devolución de cliente, las unidades se suman al stock del producto.</p><p>La operación y su historial se guardan juntos en MySQL.</p><p>Las ventas originales se conservan; el importe neto se descuenta en Ventas, Análisis, Reportes y Dashboard. Esto no procesa pagos ni reembolsos reales.</p><p>El historial se conserva para mantener trazabilidad.</p></div></section>
<section class="dev-panel dev-history"><h2>Historial de devoluciones</h2><div class="dev-table-wrap"><table><thead><tr><th>Fecha</th><th>Venta</th><th>Producto</th><th>Cantidad</th><th>Motivo</th><th>Usuario</th></tr></thead><tbody><?php foreach ($historial as $d): ?><tr><td><?= e($d['fecha']) ?></td><td><?= $d['id_detalle']===null ? "Anterior (sin vincular)" : "Detalle #".(int)$d['id_detalle'] ?></td><td><?= e($d['producto']) ?></td><td><?= (int)$d['cantidad'] ?></td><td><?= e($d['motivo']) ?></td><td><?= e($d['usuario']) ?></td></tr><?php endforeach; ?><?php if (!$historial): ?><tr><td colspan="6">Todavía no hay devoluciones registradas.</td></tr><?php endif; ?></tbody></table></div></section>
</main></div></body></html>
