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
if (!$admin){http_response_code(403);exit('Acceso restringido a administradores.');}
$page='usuarios';$error='';$ok=$_GET['ok']??'';
if ($_SERVER['REQUEST_METHOD']==='POST'){
 check_token();$accion=$_POST['accion']??'';
 try {
  if($accion==='crear'){
   $nombre=trim($_POST['usuario']??'');$pass=$_POST['contrasena']??'';$nuevoRol=$_POST['rol']??'consultador';
   if(!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/',$nombre) || strlen($pass)<8 || !in_array($nuevoRol,['administrador','consultador'],true))throw new Exception('Verifica el usuario (3-50 caracteres), la contraseña (mínimo 8) y el rol.');
   $hash=password_hash($pass,PASSWORD_DEFAULT);$stmt=$conexion->prepare('INSERT INTO administracion (usuario,contrasena,rol,correo,estado) VALUES (?,?,?,0,1)');$stmt->bind_param('sss',$nombre,$hash,$nuevoRol);$stmt->execute();$stmt->close();go('usuarios.php?ok=creado');
  } elseif($accion==='estado' || $accion==='rol'){
   $id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);if(!$id)throw new Exception('Usuario inválido.');
   if((int)$id===(int)$_SESSION['id_usuario'])throw new Exception('No puedes desactivar ni cambiar tu propio rol desde aquí.');
   if($accion==='estado'){$estado=($_POST['estado']??'')==='1'?1:0;$stmt=$conexion->prepare('UPDATE administracion SET estado=? WHERE id_usuario=?');$stmt->bind_param('ii',$estado,$id);}
   else{$nuevoRol=$_POST['rol']??'';if(!in_array($nuevoRol,['administrador','consultador'],true))throw new Exception('Rol inválido.');$stmt=$conexion->prepare('UPDATE administracion SET rol=? WHERE id_usuario=?');$stmt->bind_param('si',$nuevoRol,$id);}
   $stmt->execute();$stmt->close();go('usuarios.php?ok=actualizado');
  } else throw new Exception('Operación no permitida.');
 } catch(Throwable $e){$error=$e instanceof mysqli_sql_exception?'No se pudo guardar; comprueba que el nombre no esté repetido.':$e->getMessage();}
}
$usuarios=$conexion->query('SELECT id_usuario,usuario,rol,estado FROM administracion ORDER BY id_usuario')->fetch_all(MYSQLI_ASSOC);
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Usuarios | San Sebastián</title><link rel="stylesheet" href="css/dashboard.css"><link rel="stylesheet" href="css/gestion.css"></head><body><div class="dashboard"><aside class="sidebar"><div class="brand"><div class="brand-logo">📚</div><div><h2>San Sebastián</h2><span>Sistema de Inventario</span></div></div>
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
</nav><div class="sidebar-bottom"><a class="logout" href="logout.php">🚪 <span>Cerrar sesión</span></a></div></aside><main class="main-content"><header class="topbar"><div><h1>Usuarios</h1><p>Administra los accesos y roles del sistema.</p></div><div class="user-info"><div class="user-avatar"><?= e(mb_substr($usuario,0,1)) ?></div><div><strong><?= e($usuario) ?></strong><span><?= e($rol) ?></span></div></div></header><?php if($error): ?><p class="gestion-alerta"><?= e($error) ?></p><?php endif; ?><?php if($ok): ?><p class="gestion-exito">Operación realizada correctamente.</p><?php endif; ?>
<section class="gestion-panel"><h2>Crear usuario</h2><form method="post" class="gestion-filtros"><input type="hidden" name="csrf_token" value="<?= e(token()) ?>"><input type="hidden" name="accion" value="crear"><label>Usuario<input name="usuario" maxlength="50" minlength="3" required placeholder="Nuevo usuario"></label><label>Contraseña<input type="password" name="contrasena" minlength="8" required autocomplete="new-password"></label><label>Rol<select name="rol"><option value="consultador">Consultador</option><option value="administrador">Administrador</option></select></label><button class="gestion-btn">Crear usuario</button></form><p class="gestion-muted">Contraseñas almacenadas con hash seguro. No se muestran contraseñas existentes.</p></section>
<section class="gestion-panel"><h2>Usuarios registrados</h2><div class="gestion-scroll"><table class="gestion-tabla"><thead><tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php foreach($usuarios as $u): ?><tr><td><?= (int)$u['id_usuario'] ?></td><td><?= e($u['usuario']) ?></td><td><?= e($u['rol']) ?></td><td><?= (int)$u['estado']===1?'Activo':'Inactivo' ?></td><td><div class="gestion-acciones"><?php if((int)$u['id_usuario']===(int)$_SESSION['id_usuario']): ?>Tu cuenta<?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(token()) ?>"><input type="hidden" name="accion" value="rol"><input type="hidden" name="id" value="<?= (int)$u['id_usuario'] ?>"><select name="rol"><option value="consultador" <?= $u['rol']==='consultador'?'selected':'' ?>>Consultador</option><option value="administrador" <?= $u['rol']==='administrador'?'selected':'' ?>>Administrador</option></select><button class="gestion-btn pequena">Guardar rol</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= e(token()) ?>"><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$u['id_usuario'] ?>"><input type="hidden" name="estado" value="<?= (int)$u['estado']===1?0:1 ?>"><button class="gestion-btn pequena secundario" onclick="return confirm('¿Confirmas este cambio de acceso?')"><?= (int)$u['estado']===1?'Desactivar':'Activar' ?></button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div></section></main></div></body></html>