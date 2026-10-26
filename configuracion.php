<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { header('Location: login.php'); exit; }
require_once 'config/conexion.php';
$rol = $_SESSION['rol'] ?? 'consultador';
$usuario = $_SESSION['usuario'] ?? '';
$admin = $rol === 'administrador';
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function token() { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(24)); return $_SESSION['csrf_token']; }
function check_token() { if (!hash_equals(token(), (string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); exit('Solicitud no válida.'); } }
function go($where){ header('Location: '.$where); exit; }
if(!$admin){http_response_code(403);exit('Acceso restringido a administradores.');}
$page='configuracion';$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_token();$accion=$_POST['accion']??'';
 try {
 if($accion==='perfil'){
  $nombre=trim($_POST['usuario']??'');if(!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/',$nombre))throw new Exception('Nombre de usuario inválido (3-50 caracteres).');
  $id=(int)$_SESSION['id_usuario'];$stmt=$conexion->prepare('UPDATE administracion SET usuario=? WHERE id_usuario=?');$stmt->bind_param('si',$nombre,$id);$stmt->execute();$stmt->close();$_SESSION['usuario']=$nombre;$usuario=$nombre;$ok='Nombre de usuario actualizado.';
 } elseif($accion==='clave'){
  $actual=$_POST['actual']??'';$nueva=$_POST['nueva']??'';$repetir=$_POST['repetir']??'';
  if(strlen($nueva)<8 || $nueva!==$repetir)throw new Exception('La nueva contraseña debe tener al menos 8 caracteres y coincidir en ambos campos.');
  $id=(int)$_SESSION['id_usuario'];$stmt=$conexion->prepare('SELECT contrasena FROM administracion WHERE id_usuario=?');$stmt->bind_param('i',$id);$stmt->execute();$u=$stmt->get_result()->fetch_assoc();$stmt->close();
  if(!$u || !password_verify($actual,$u['contrasena']))throw new Exception('La contraseña actual no es correcta.');
  $hash=password_hash($nueva,PASSWORD_DEFAULT);$stmt=$conexion->prepare('UPDATE administracion SET contrasena=? WHERE id_usuario=?');$stmt->bind_param('si',$hash,$id);$stmt->execute();$stmt->close();session_regenerate_id(true);$ok='Contraseña actualizada.';
 } else throw new Exception('Acción desconocida.');
 }catch(Throwable $e){$error=$e instanceof mysqli_sql_exception?'No se pudo guardar el cambio.':$e->getMessage();}
}
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Configuración | San Sebastián</title><link rel="stylesheet" href="css/dashboard.css"><link rel="stylesheet" href="css/gestion.css"></head><body><div class="dashboard"><aside class="sidebar"><div class="brand"><div class="brand-logo">📚</div><div><h2>San Sebastián</h2><span>Sistema de Inventario</span></div></div>
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
</nav><div class="sidebar-bottom"><a class="logout" href="logout.php">🚪 <span>Cerrar sesión</span></a></div></aside><main class="main-content"><header class="topbar"><div><h1>Configuración</h1><p>Ajustes seguros de tu cuenta administradora.</p></div><div class="user-info"><div class="user-avatar"><?= e(mb_substr($usuario,0,1)) ?></div><div><strong><?= e($usuario) ?></strong><span><?= e($rol) ?></span></div></div></header><?php if($error): ?><p class="gestion-alerta"><?= e($error) ?></p><?php endif; ?><?php if($ok): ?><p class="gestion-exito"><?= e($ok) ?></p><?php endif; ?><div class="gestion-stats"><section class="gestion-panel"><h2>Nombre de usuario</h2><form method="post" class="gestion-form"><input type="hidden" name="csrf_token" value="<?= e(token()) ?>"><input type="hidden" name="accion" value="perfil"><label>Usuario<input name="usuario" value="<?= e($usuario) ?>" required minlength="3" maxlength="50"></label><button class="gestion-btn">Guardar cambio</button></form></section><section class="gestion-panel"><h2>Seguridad de la cuenta</h2><form method="post" class="gestion-form"><input type="hidden" name="csrf_token" value="<?= e(token()) ?>"><input type="hidden" name="accion" value="clave"><label>Contraseña actual<input type="password" name="actual" required autocomplete="current-password"></label><label>Nueva contraseña<input type="password" name="nueva" minlength="8" required autocomplete="new-password"></label><label>Confirmar contraseña<input type="password" name="repetir" minlength="8" required autocomplete="new-password"></label><button class="gestion-btn">Actualizar contraseña</button></form></section></div><section class="gestion-panel"><h2>Información del sistema</h2><p>Plataforma: Librería San Sebastián · Base de datos: MySQL · Perfil actual: <?= e($rol) ?></p><p class="gestion-muted">La configuración de conexión a la base de datos se mantiene exclusivamente en el servidor. Esta página no cambia credenciales del hosting.</p></section></main></div></body></html>