<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/consultas_netas.php';
function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['csrf_operaciones'])) $_SESSION['csrf_operaciones'] = bin2hex(random_bytes(32));
$mensaje=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!hash_equals($_SESSION['csrf_operaciones'],$_POST['csrf']??'')) $error='Formulario inválido.';
 else {
  $id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);$cantidad=filter_var($_POST['cantidad']??'',FILTER_VALIDATE_INT);
  if (!$id || !$cantidad || $cantidad<1 || $cantidad>100000) $error='Producto o cantidad inválidos.';
  else {try {
   $conexion->begin_transaction();
   $stmt=$conexion->prepare('SELECT nombre,precio_venta,existencias FROM inventario WHERE id_producto=? AND estado=1 FOR UPDATE');$stmt->bind_param('i',$id);$stmt->execute();$p=$stmt->get_result()->fetch_assoc();$stmt->close();
   if (!$p || (int)$p['existencias']<$cantidad) throw new Exception('Existencias insuficientes.');
   $precio=(float)$p['precio_venta'];$total=round($precio*$cantidad,2);$uid=(int)$_SESSION['id_usuario'];
   $stmt=$conexion->prepare('INSERT INTO ventas (id_usuario,fecha,total) VALUES (?,NOW(),?)');$stmt->bind_param('id',$uid,$total);$stmt->execute();$venta_id=$conexion->insert_id;$stmt->close();
   $stmt=$conexion->prepare('INSERT INTO detalle_ventas (id_venta,id_producto,cantidad,precio_unitario,subtotal) VALUES (?,?,?,?,?)');$stmt->bind_param('iiidd',$venta_id,$id,$cantidad,$precio,$total);$stmt->execute();$stmt->close();
   $stmt=$conexion->prepare('UPDATE inventario SET existencias=existencias-? WHERE id_producto=?');$stmt->bind_param('ii',$cantidad,$id);$stmt->execute();$stmt->close();
   $conexion->commit();header('Location: ventas.php?ok=1');exit;
  } catch(Throwable $ex){$conexion->rollback();$error='No se pudo registrar la venta. Comprueba las existencias y la base de datos.';}}
 }
}
if(isset($_GET['ok']))$mensaje='Venta registrada y stock actualizado.';
$productos=[];$ventas=[];$totalDia=0;
try {$r=$conexion->query('SELECT id_producto,nombre,existencias,precio_venta FROM inventario WHERE estado=1 AND existencias>0 ORDER BY nombre');while($x=$r->fetch_assoc())$productos[]=$x;
$r=$conexion->query('SELECT COALESCE(SUM(total_neto),0) AS total FROM '.$ss_ventas_netas.' AS netas WHERE fecha>=CURDATE() AND fecha<CURDATE()+INTERVAL 1 DAY');$totalDia=(float)$r->fetch_assoc()['total'];
$r=$conexion->query('SELECT v.fecha,i.nombre,d.cantidad_neta AS cantidad,d.precio_unitario,d.subtotal_neto AS subtotal FROM '.$ss_detalle_neto.' d JOIN ventas v ON v.id_venta=d.id_venta JOIN inventario i ON i.id_producto=d.id_producto ORDER BY v.fecha DESC,d.id_detalle DESC LIMIT 100');while($x=$r->fetch_assoc())$ventas[]=$x;
} catch(Throwable $ex){$error='No se pudieron consultar las ventas.';}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ventas | San Sebastián</title><link rel="stylesheet" href="css/dashboard.css"><link rel="stylesheet" href="css/operaciones.css"></head><body><div class="dashboard"><aside class="sidebar">

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
                <a href="productos.php">
                    ➕
                    <span>Productos</span>
                </a>

                <a href="ventas.php" class="active">
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

                <a href="devoluciones.php" >
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

        </aside><main class="main-content"><header class="topbar"><div><h1>Ventas</h1></div><div class="user-info"><strong><?=esc($_SESSION['usuario']??'')?></strong></div></header><h1>🛒 Ventas</h1><p>Registra ventas y descuenta las existencias automáticamente.</p>
<?php if($error): ?><p class="alerta"><?=esc($error)?></p><?php endif; ?><?php if($mensaje): ?><p class="exito"><?=esc($mensaje)?></p><?php endif; ?>
<section class="mod-panel"><h2>Ventas de hoy: $<?=number_format($totalDia,2)?></h2></section>
<section class="mod-panel"><h2>Registrar venta</h2><form method="post" class="mod-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf_operaciones'])?>">
<label>Producto<select name="id" required><option value="">Seleccionar</option><?php foreach($productos as $p): ?><option value="<?= (int)$p['id_producto'] ?>"><?=esc($p['nombre'])?> (<?= (int)$p['existencias'] ?> uds. · $<?=number_format((float)$p['precio_venta'],2)?>)</option><?php endforeach; ?></select></label>
<label>Cantidad<input type="number" min="1" name="cantidad" required></label><button type="submit">Registrar venta</button></form></section>
<section class="mod-panel"><h2>Últimas ventas</h2><div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr></thead><tbody><?php foreach($ventas as $v): ?><tr><td><?=esc($v['fecha'])?></td><td><?=esc($v['nombre'])?></td><td><?= (int)$v['cantidad'] ?></td><td>$<?=number_format((float)$v['precio_unitario'],2)?></td><td>$<?=number_format((float)$v['subtotal'],2)?></td></tr><?php endforeach; ?></tbody></table></div></section></main></div></body></html>