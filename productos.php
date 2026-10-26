<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/config/conexion.php';
function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['csrf_operaciones'])) $_SESSION['csrf_operaciones'] = bin2hex(random_bytes(32));
$mensaje=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!hash_equals($_SESSION['csrf_operaciones'], $_POST['csrf']??'')) $error='Formulario inválido.';
 elseif (($_SESSION['rol']??'')!=='administrador') $error='Solo el administrador puede agregar productos.';
 else {
  $nombre=trim($_POST['nombre']??''); $descripcion=trim($_POST['descripcion']??'');
  $cat=filter_var($_POST['categoria']??'',FILTER_VALIDATE_INT);
  $stock=filter_var($_POST['stock']??'',FILTER_VALIDATE_INT); $min=filter_var($_POST['minimo']??'',FILTER_VALIDATE_INT);
  $compra=filter_var($_POST['compra']??'',FILTER_VALIDATE_FLOAT); $venta=filter_var($_POST['venta']??'',FILTER_VALIDATE_FLOAT);
  if ($nombre==='' || strlen($nombre)>150 || strlen($descripcion)>255 || !$cat || $stock===false || $min===false || $stock<0 || $min<0 || $compra===false || $venta===false || $compra<0 || $venta<0) $error='Revisa los datos del producto.';
  else {try {
   $stmt=$conexion->prepare('SELECT id_categoria FROM categorias WHERE id_categoria=?');$stmt->bind_param('i',$cat);$stmt->execute();$valid=$stmt->get_result()->fetch_assoc();$stmt->close();
   if (!$valid) throw new Exception('Categoría inválida.');
   $stmt=$conexion->prepare('INSERT INTO inventario (id_categoria,nombre,descripcion,precio_compra,precio_venta,existencias,stock_minimo,estado) VALUES (?,?,?,?,?,?,?,1)');
   $stmt->bind_param('issddii',$cat,$nombre,$descripcion,$compra,$venta,$stock,$min);$stmt->execute();$stmt->close();
   header('Location: productos.php?ok=1');exit;
  } catch(Throwable $ex) {$error='No se pudo registrar el producto.';}}
 }
}
if (isset($_GET['ok'])) $mensaje='Producto agregado correctamente.';
$categorias=[];$productos=[];
try {$r=$conexion->query('SELECT id_categoria,nombre FROM categorias ORDER BY nombre');while($x=$r->fetch_assoc())$categorias[]=$x;
$r=$conexion->query('SELECT i.nombre,i.existencias,i.precio_venta,c.nombre AS categoria FROM inventario i LEFT JOIN categorias c ON c.id_categoria=i.id_categoria WHERE i.estado=1 ORDER BY i.id_producto DESC LIMIT 100');while($x=$r->fetch_assoc())$productos[]=$x;
} catch(Throwable $ex) {$error='No se pudieron cargar los datos.';}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Productos | San Sebastián</title><link rel="stylesheet" href="css/dashboard.css"><link rel="stylesheet" href="css/operaciones.css"></head><body><div class="dashboard"><aside class="sidebar">

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
                <a href="productos.php" class="active">
                    ➕
                    <span>Productos</span>
                </a>

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

        </aside><main class="main-content"><header class="topbar"><div><h1>Productos</h1></div><div class="user-info"><strong><?=esc($_SESSION['usuario']??'')?></strong></div></header><h1>➕ Productos</h1><p>Registra nuevos artículos en el inventario.</p>
<?php if($error): ?><p class="alerta"><?=esc($error)?></p><?php endif; ?><?php if($mensaje): ?><p class="exito"><?=esc($mensaje)?></p><?php endif; ?>
<section class="mod-panel"><h2>Nuevo producto</h2><form method="post" class="mod-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf_operaciones'])?>">
<label>Nombre<input name="nombre" maxlength="150" required></label><label>Descripción<input name="descripcion" maxlength="255"></label>
<label>Categoría<select name="categoria" required><option value="">Seleccionar</option><?php foreach($categorias as $c): ?><option value="<?= (int)$c['id_categoria'] ?>"><?=esc($c['nombre'])?></option><?php endforeach; ?></select></label>
<label>Existencias<input type="number" min="0" name="stock" required></label><label>Stock mínimo<input type="number" min="0" name="minimo" value="5" required></label>
<label>Precio de compra ($)<input type="number" min="0" step="0.01" name="compra" required></label><label>Precio de venta ($)<input type="number" min="0" step="0.01" name="venta" required></label>
<button type="submit">Guardar producto</button></form></section><section class="mod-panel"><h2>Últimos productos activos</h2><div class="table-wrap"><table><thead><tr><th>Producto</th><th>Categoría</th><th>Stock</th><th>Precio</th></tr></thead><tbody><?php foreach($productos as $p): ?><tr><td><?=esc($p['nombre'])?></td><td><?=esc($p['categoria'])?></td><td><?= (int)$p['existencias'] ?></td><td>$<?=number_format((float)$p['precio_venta'],2)?></td></tr><?php endforeach; ?></tbody></table></div></section></main></div></body></html>