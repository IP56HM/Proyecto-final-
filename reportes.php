<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { header('Location: login.php'); exit; }
require_once 'config/conexion.php';
require_once __DIR__ . '/consultas_netas.php';
$rol = $_SESSION['rol'] ?? 'consultador';
$usuario = $_SESSION['usuario'] ?? '';
$admin = $rol === 'administrador';
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function token() { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(24)); return $_SESSION['csrf_token']; }
function check_token() { if (!hash_equals(token(), (string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); exit('Solicitud no válida.'); } }
function go($where){ header('Location: '.$where); exit; }
$page='reportes';
$tipo=$_GET['tipo']??'ventas';
if (!in_array($tipo,['ventas','compras','inventario','perdidas'],true)) $tipo='ventas';
$desde=$_GET['desde']??date('Y-m-01'); $hasta=$_GET['hasta']??date('Y-m-d');
foreach ([$desde,$hasta] as $fecha) { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha); if(!$d || $d->format('Y-m-d')!==$fecha) { http_response_code(400); exit('Fecha inválida'); } }
if ($desde>$hasta) [$desde,$hasta]=[$hasta,$desde];
$inicio=$desde.' 00:00:00';$fin=$hasta.' 23:59:59';
$esInventario=$tipo==='inventario';
$sqls=[
 'ventas'=>"SELECT v.id_venta AS ID, v.fecha AS Fecha, a.usuario AS Responsable, v.total_original AS Bruto, (v.total_original-v.total_neto) AS Devuelto, v.total_neto AS Total FROM {$ss_ventas_netas} v LEFT JOIN administracion a ON a.id_usuario=v.id_usuario WHERE v.fecha BETWEEN ? AND ? ORDER BY v.fecha DESC LIMIT 5000",
 'compras'=>"SELECT c.id_compra AS ID, c.fecha AS Fecha, a.usuario AS Responsable, c.total AS Total FROM compras c LEFT JOIN administracion a ON a.id_usuario=c.id_usuario WHERE c.fecha BETWEEN ? AND ? ORDER BY c.fecha DESC LIMIT 5000",
 'perdidas'=>"SELECT p.id_perdida AS ID, p.fecha AS Fecha, i.nombre AS Producto, p.cantidad AS Cantidad, p.motivo AS Motivo, p.valor_perdida AS Valor FROM perdidas p LEFT JOIN inventario i ON i.id_producto=p.id_producto WHERE p.fecha BETWEEN ? AND ? ORDER BY p.fecha DESC LIMIT 5000",
 'inventario'=>"SELECT i.id_producto AS ID, i.nombre AS Producto, c.nombre AS Categoria, i.existencias AS Existencias, i.stock_minimo AS Minimo, i.precio_venta AS Precio, IF(i.estado=1,'Activo','Inactivo') AS Estado FROM inventario i LEFT JOIN categorias c ON c.id_categoria=i.id_categoria ORDER BY i.nombre LIMIT 5000"
];
$filas=[];$error='';
try { $stmt=$conexion->prepare($sqls[$tipo]); if(!$esInventario)$stmt->bind_param('ss',$inicio,$fin);$stmt->execute();$filas=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close(); }
catch(Throwable $e){error_log('Reportes: '.$e->getMessage());$error='No se pudieron obtener los datos. Revisa la conexión y las tablas.';}
if (isset($_GET['csv']) && !$error) {
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="reporte_'.$tipo.'_'.date('Ymd').'.csv"');
 $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
 $columnas=$filas?array_keys($filas[0]):(['ID','Fecha','Responsable','Total']);fputcsv($out,$columnas);
 foreach($filas as $f)fputcsv($out,array_values($f));fclose($out);exit;
}
$columnas=$filas?array_keys($filas[0]):[];
$total=0;foreach($filas as $f){if(isset($f['Total']))$total+=(float)$f['Total'];elseif(isset($f['Valor']))$total+=(float)$f['Valor'];}
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reportes | San Sebastián</title><link rel="stylesheet" href="css/dashboard.css"><link rel="stylesheet" href="css/gestion.css"></head><body><div class="dashboard"><aside class="sidebar"><div class="brand"><div class="brand-logo">📚</div><div><h2>San Sebastián</h2><span>Sistema de Inventario</span></div></div>
<nav class="menu">
<a href="dashboard.php">🏠 <span>Inicio</span></a>
<a href="inventario.php">📦 <span>Inventario</span></a>
<a href="productos.php">➕ <span>Productos</span></a>
<a href="ventas.php">🛒 <span>Ventas</span></a>
<a href="compras.php">📥 <span>Compras</span></a>
<a href="analisis.php">📊 <span>Análisis</span></a>
<a href="devoluciones.php">↩️ <span>Devoluciones</span></a>
<a href="reportes.php" class="<?= $page==='reportes'?'active':'' ?>">📄 <span>Reportes</span></a>
<?php if ($admin): ?>
<a href="usuarios.php" class="<?= $page==='usuarios'?'active':'' ?>">👥 <span>Usuarios</span></a>
<a href="configuracion.php" class="<?= $page==='configuracion'?'active':'' ?>">⚙️ <span>Configuración</span></a>
<?php endif; ?>
</nav><div class="sidebar-bottom"><a class="logout" href="logout.php">🚪 <span>Cerrar sesión</span></a></div></aside><main class="main-content"><header class="topbar"><div><h1>Reportes</h1><p>Consulta y exporta movimientos reales de tu librería.</p></div><div class="user-info"><div class="user-avatar"><?= e(mb_substr($usuario,0,1)) ?></div><div><strong><?= e($usuario) ?></strong><span><?= e($rol) ?></span></div></div></header><section class="gestion-panel"><form class="gestion-filtros" method="get"><label>Reporte<select name="tipo"><?php foreach(['ventas'=>'Ventas','compras'=>'Compras','inventario'=>'Inventario','perdidas'=>'Pérdidas'] as $k=>$v): ?><option value="<?= e($k) ?>" <?= $tipo===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></label><label>Desde<input type="date" name="desde" value="<?= e($desde) ?>"></label><label>Hasta<input type="date" name="hasta" value="<?= e($hasta) ?>"></label><button class="gestion-btn">Consultar</button><a class="gestion-btn secundario" href="?<?= e(http_build_query(['tipo'=>$tipo,'desde'=>$desde,'hasta'=>$hasta,'csv'=>1])) ?>">Descargar CSV</a></form><p class="gestion-muted">Máximo 5,000 registros por consulta. El reporte de inventario refleja las existencias actuales y no usa filtro de fechas.</p></section>
<div class="gestion-stats"><div class="gestion-panel"><span>Registros encontrados</span><strong><?= count($filas) ?></strong></div><div class="gestion-panel"><span><?= $tipo==='inventario'?'Tipo de reporte':'Total del periodo' ?></span><strong><?= $tipo==='inventario'?'Inventario':'$'.number_format($total,2) ?></strong></div></div>
<section class="gestion-panel"><h2>Detalle del reporte</h2><?php if($error): ?><p class="gestion-alerta"><?= e($error) ?></p><?php elseif (!$filas): ?><p>No hay datos para mostrar en este periodo.</p><?php else: ?><div class="gestion-scroll"><table class="gestion-tabla"><thead><tr><?php foreach($columnas as $col): ?><th><?= e($col) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($filas as $fila): ?><tr><?php foreach($fila as $val): ?><td><?= e($val) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></main></div></body></html>