<?php
session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php");
    exit;
}

require_once "config/conexion.php";

$usuario = isset($_SESSION["usuario"]) ? $_SESSION["usuario"] : "Usuario";
$rol = isset($_SESSION["rol"]) ? $_SESSION["rol"] : "consultador";
$id_categoria = isset($_GET["categoria"]) ? (int) $_GET["categoria"] : 0;

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$error = "";
$categorias = array();
$categoria_actual = null;
$productos = array();
$total_productos = 0;

/* Categorías con la cantidad de productos activos */
$sql_categorias = "SELECT c.id_categoria, c.nombre, c.descripcion,
                   COUNT(i.id_producto) AS total_productos
                   FROM categorias c
                   LEFT JOIN inventario i
                     ON i.id_categoria = c.id_categoria AND i.estado = 1
                   GROUP BY c.id_categoria, c.nombre, c.descripcion
                   ORDER BY c.nombre ASC";
$res_categorias = $conexion->query($sql_categorias);

if ($res_categorias) {
    while ($fila = $res_categorias->fetch_assoc()) {
        $categorias[] = $fila;
    }
} else {
    $error = "No se pudieron cargar las categorías. Verifica la tabla categorias y la conexión de la base de datos.";
}

$res_total = $conexion->query("SELECT COUNT(*) AS total FROM inventario WHERE estado = 1");
if ($res_total) {
    $fila_total = $res_total->fetch_assoc();
    $total_productos = (int)$fila_total["total"];
}

if ($id_categoria > 0) {
    $stmt = $conexion->prepare("SELECT id_categoria, nombre, descripcion FROM categorias WHERE id_categoria = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_categoria);
        $stmt->execute();
        $res = $stmt->get_result();
        $categoria_actual = $res->fetch_assoc();
        $stmt->close();
    }

    if ($categoria_actual) {
        $stmt = $conexion->prepare("SELECT id_producto, nombre, descripcion, precio_compra, precio_venta, existencias, stock_minimo
                                    FROM inventario
                                    WHERE id_categoria = ? AND estado = 1
                                    ORDER BY nombre ASC");
        if ($stmt) {
            $stmt->bind_param("i", $id_categoria);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($fila = $res->fetch_assoc()) {
                $productos[] = $fila;
            }
            $stmt->close();
        } else {
            $error = "No se pudieron consultar los productos de esta categoría.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario | Librería San Sebastián</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .inv-content{display:flex;flex-direction:column;gap:22px}
        .inv-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}
        .inv-heading h2{margin:0 0 7px;color:#1e293b;font-size:23px}
        .inv-heading p{margin:0;color:#64748b;font-size:14px}
        .inv-total{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px}
        .inv-total-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:10px;background:#e8f0ff;font-size:23px}
        .inv-total span{display:block;color:#64748b;font-size:12px}.inv-total strong{display:block;margin-top:3px;font-size:22px;color:#1e293b}
        .inv-breadcrumb{display:flex;gap:9px;align-items:center;color:#64748b;font-size:14px}.inv-breadcrumb a{color:#2563eb;text-decoration:none;font-weight:600}
        .inv-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
        .inv-category{display:flex;flex-direction:column;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:22px;text-decoration:none;color:#1e293b;transition:.2s}
        .inv-category:hover{border-color:#93b4f5;transform:translateY(-2px)}
        .inv-icon{width:50px;height:50px;display:grid;place-items:center;background:#e8f0ff;border-radius:12px;font-size:25px;margin-bottom:16px}
        .inv-category h3{font-size:17px;margin:0 0 9px;text-transform:capitalize}.inv-category p{color:#64748b;font-size:13px;line-height:1.6;margin:0 0 20px;flex:1}
        .inv-cat-foot{display:flex;justify-content:space-between;gap:8px;align-items:center;border-top:1px solid #edf0f5;padding-top:14px;font-size:12px;color:#475569}.inv-open{color:#2563eb;font-weight:600}
        .inv-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden}
        .inv-panel-head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;padding:20px 22px;border-bottom:1px solid #e2e8f0}
        .inv-panel-head h3{margin:0 0 6px;color:#1e293b;font-size:18px}.inv-panel-head p{margin:0;color:#64748b;font-size:13px}
        .inv-back{display:inline-block;padding:10px 14px;border:1px solid #dbe3ef;border-radius:9px;color:#334155;text-decoration:none;font-size:13px;font-weight:600;background:#fff}
        .inv-table-wrap{width:100%;overflow-x:auto}.inv-table{width:100%;border-collapse:collapse;min-width:760px}.inv-table th{background:#f8fafc;color:#475569;font-size:12px;text-align:left;padding:14px 16px;white-space:nowrap}.inv-table td{padding:14px 16px;border-top:1px solid #edf0f5;color:#334155;font-size:13px}.inv-table tbody tr:hover{background:#f8fbff}.inv-product{font-weight:600;color:#1e293b}
        .inv-badge{display:inline-block;padding:5px 9px;border-radius:6px;background:#dcfce7;color:#166534;font-size:12px;font-weight:600}.inv-badge.low{background:#fee2e2;color:#b91c1c}
        .inv-empty{text-align:center;padding:42px 20px;color:#64748b}.inv-empty .big{font-size:36px;margin-bottom:10px}.inv-empty h3{color:#1e293b;margin:0 0 8px}.inv-empty p{font-size:14px;line-height:1.7;margin:0}.inv-error{padding:14px 16px;border-radius:10px;background:#fff1f2;border:1px solid #fecdd3;color:#9f1239;font-size:14px}
        @media(max-width:600px){.inv-grid{grid-template-columns:1fr}.inv-heading h2{font-size:20px}.inv-panel-head{padding:16px}}
    </style>
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <div class="brand"><div class="brand-logo">📚</div><div><h2>San Sebastián</h2><span>Sistema de Inventario</span></div></div>
        <nav class="menu">
            <a href="dashboard.php">🏠 <span>Inicio</span></a>
            <a href="inventario.php" class="active">📦 <span>Inventario</span></a>
<a href="productos.php">➕ <span>Productos</span></a>
            <a href="ventas.php">🛒 <span>Ventas</span></a>
            <a href="compras.php">📥 <span>Compras</span></a>
            <a href="analisis.php">📊 <span>Análisis</span></a>

            <a href="devoluciones.php">
                ↩️
                <span>Devoluciones</span>
            </a>
            <a href="reportes.php">📄 <span>Reportes</span></a>
            <?php if ($rol === "administrador"): ?>
                <a href="usuarios.php">👥 <span>Usuarios</span></a>
                <a href="configuracion.php">⚙️ <span>Configuración</span></a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-bottom"><a href="logout.php" class="logout">🚪 <span>Cerrar sesión</span></a></div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div><h1>Inventario</h1><p>Bienvenido, <?= e($usuario) ?></p></div>
            <div class="user-info"><div class="user-avatar"><?= e(strtoupper(substr($usuario, 0, 1))) ?></div><div><strong><?= e($usuario) ?></strong><span><?= e($rol) ?></span></div></div>
        </header>

        <div class="inv-content">
            <section class="inv-heading">
                <div>
                    <h2><?= $categoria_actual ? 'Productos de ' . e($categoria_actual['nombre']) : 'Categorías de productos' ?></h2>
                    <p>Organiza y consulta los productos de tu librería por categoría.</p>
                </div>
                <div class="inv-total"><div class="inv-total-icon">📦</div><div><span>Productos activos</span><strong><?= $total_productos ?></strong></div></div>
            </section>

            <?php if ($error !== ''): ?><div class="inv-error"><?= e($error) ?></div><?php endif; ?>

            <?php if ($id_categoria > 0 && $categoria_actual): ?>
                <div class="inv-breadcrumb"><a href="inventario.php">Inventario</a><span>›</span><span><?= e($categoria_actual['nombre']) ?></span></div>
                <section class="inv-panel">
                    <div class="inv-panel-head"><div><h3>📁 <?= e($categoria_actual['nombre']) ?></h3><p><?= e($categoria_actual['descripcion']) ?></p></div><a href="inventario.php" class="inv-back">← Volver a categorías</a></div>
                    <?php if (count($productos) > 0): ?>
                        <div class="inv-table-wrap"><table class="inv-table"><thead><tr><th>ID</th><th>Producto</th><th>Descripción</th><th>Precio compra</th><th>Precio venta</th><th>Existencias</th><th>Estado</th></tr></thead><tbody>
                        <?php foreach ($productos as $p): $bajo = (int)$p['existencias'] <= (int)$p['stock_minimo']; ?>
                            <tr><td><?= (int)$p['id_producto'] ?></td><td class="inv-product"><?= e($p['nombre']) ?></td><td><?= e($p['descripcion']) ?></td><td>$<?= number_format((float)$p['precio_compra'], 2) ?></td><td>$<?= number_format((float)$p['precio_venta'], 2) ?></td><td><?= (int)$p['existencias'] ?></td><td><span class="inv-badge <?= $bajo ? 'low' : '' ?>"><?= $bajo ? 'Stock bajo' : 'Disponible' ?></span></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    <?php else: ?><div class="inv-empty"><div class="big">📦</div><h3>No hay productos registrados</h3><p>Esta categoría no tiene productos activos asociados.</p></div><?php endif; ?>
                </section>
            <?php elseif ($id_categoria > 0): ?>
                <section class="inv-panel"><div class="inv-empty"><div class="big">⚠️</div><h3>Categoría no encontrada</h3><p>La categoría seleccionada no existe.</p><br><a href="inventario.php" class="inv-back">Volver a categorías</a></div></section>
            <?php elseif (count($categorias) > 0): ?>
                <section class="inv-grid">
                    <?php foreach ($categorias as $c): ?>
                        <a class="inv-category" href="inventario.php?categoria=<?= (int)$c['id_categoria'] ?>">
                            <div class="inv-icon">📁</div><h3><?= e($c['nombre']) ?></h3>
                            <p><?= trim((string)$c['descripcion']) !== '' ? e($c['descripcion']) : 'Consulta los productos de esta categoría.' ?></p>
                            <div class="inv-cat-foot"><span><?= (int)$c['total_productos'] ?> <?= (int)$c['total_productos'] === 1 ? 'producto' : 'productos' ?></span><span class="inv-open">Ver productos →</span></div>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php else: ?>
                <section class="inv-panel"><div class="inv-empty"><div class="big">📂</div><h3>No hay categorías registradas</h3><p>La consulta no devolvió categorías. Comprueba que la web esté conectada a la misma base de datos donde las registraste.</p></div></section>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
