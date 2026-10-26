<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php");
    exit;
}

require_once "config/conexion.php";
require_once __DIR__ . "/consultas_netas.php";

$usuario = $_SESSION["usuario"];
$rol = $_SESSION["rol"];

/* ==============================
   DATOS DEL DASHBOARD
============================== */

/* Total de productos */
$sql_productos = "SELECT COUNT(*) AS total FROM inventario";
$resultado_productos = $conexion->query($sql_productos);
$total_productos = $resultado_productos->fetch_assoc()["total"];

/* Productos con stock bajo */
$sql_stock = "SELECT COUNT(*) AS total
              FROM inventario
              WHERE existencias <= stock_minimo
              AND estado = 1";

$resultado_stock = $conexion->query($sql_stock);
$stock_bajo = $resultado_stock->fetch_assoc()["total"];

/* Total de ventas */
$sql_ventas = "SELECT COALESCE(SUM(total_neto), 0) AS total
               FROM {$ss_ventas_netas} AS netas";

$resultado_ventas = $conexion->query($sql_ventas);
$total_ventas = $resultado_ventas->fetch_assoc()["total"];

/* Total de pérdidas */
$sql_perdidas = "SELECT COALESCE(SUM(valor_perdida), 0) AS total
                 FROM perdidas";

$resultado_perdidas = $conexion->query($sql_perdidas);
$total_perdidas = $resultado_perdidas->fetch_assoc()["total"];

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Librería San Sebastián</title>

    <link rel="stylesheet" href="css/dashboard.css">

</head>

<body>

    <div class="dashboard">

        <!-- ==============================
             MENÚ LATERAL
        =============================== -->

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

                <a href="dashboard.php" class="active">
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

                <a href="devoluciones.php">
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


        <!-- ==============================
             CONTENIDO
        =============================== -->

        <main class="main-content">

            <header class="topbar">

                <div>

                    <h1>Dashboard</h1>

                    <p>
                        Bienvenido, <?= htmlspecialchars($usuario) ?>
                    </p>

                </div>

                <div class="user-info">

                    <div class="user-avatar">
                        <?= strtoupper(substr($usuario, 0, 1)) ?>
                    </div>

                    <div>

                        <strong>
                            <?= htmlspecialchars($usuario) ?>
                        </strong>

                        <span>
                            <?= htmlspecialchars($rol) ?>
                        </span>

                    </div>

                </div>

            </header>


            <!-- ==============================
                 TARJETAS
            =============================== -->

            <section class="cards">

                <article class="card">

                    <div class="card-icon blue">
                        📦
                    </div>

                    <div>

                        <span>Productos</span>

                        <strong>
                            <?= $total_productos ?>
                        </strong>

                    </div>

                </article>


                <article class="card">

                    <div class="card-icon yellow">
                        ⚠️
                    </div>

                    <div>

                        <span>Stock bajo</span>

                        <strong>
                            <?= $stock_bajo ?>
                        </strong>

                    </div>

                </article>


                <article class="card">

                    <div class="card-icon green">
                        💰
                    </div>

                    <div>

                        <span>Ventas</span>

                        <strong>
                            $<?= number_format($total_ventas, 2) ?>
                        </strong>

                    </div>

                </article>


                <article class="card">

                    <div class="card-icon red">
                        📉
                    </div>

                    <div>

                        <span>Pérdidas</span>

                        <strong>
                            $<?= number_format($total_perdidas, 2) ?>
                        </strong>

                    </div>

                </article>

            </section>


            <!-- ==============================
                 CONTENIDO INFERIOR
            =============================== -->

            <section class="dashboard-grid">

                <div class="panel">

                    <div class="panel-header">

                        <h2>Estado del inventario</h2>

                        <a href="inventario.php">
                            Ver inventario
                        </a>

                    </div>

                    <?php if ($stock_bajo > 0): ?>

                        <div class="alert">

                            <span class="alert-icon">
                                ⚠️
                            </span>

                            <div>

                                <strong>
                                    Productos necesitan reposición
                                </strong>

                                <p>
                                    Hay <?= $stock_bajo ?>
                                    producto(s) con existencias bajas.
                                </p>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="success">

                            <span>
                                ✅
                            </span>

                            <div>

                                <strong>
                                    Inventario en buen estado
                                </strong>

                                <p>
                                    No hay productos por debajo del stock mínimo.
                                </p>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>


                <div class="panel">

                    <div class="panel-header">

                        <h2>Acciones rápidas</h2>

                    </div>

                    <div class="quick-actions">
                        <a href="productos.php" aria-label="Ir a Productos para agregar un artículo"><span aria-hidden="true">➕</span><span class="quick-action-text"><strong>Productos</strong></span></a>
                        <a href="ventas.php" aria-label="Ir al registro de ventas"><span aria-hidden="true">🛒</span><span class="quick-action-text"><strong>Ventas</strong></span></a>
                        <a href="inventario.php" aria-label="Consultar inventario"><span aria-hidden="true">📦</span><span class="quick-action-text"><strong>Inventario</strong></span></a>
                        <a href="compras.php" aria-label="Ir al apartado de compras"><span aria-hidden="true">📥</span><span class="quick-action-text"><strong>Compras</strong></span></a>
                        <a href="analisis.php" aria-label="Ir a análisis"><span aria-hidden="true">📊</span><span class="quick-action-text"><strong>Análisis</strong></span></a>
                        <a href="devoluciones.php" aria-label="Ir a devoluciones"><span aria-hidden="true">↩️</span><span class="quick-action-text"><strong>Devoluciones</strong></span></a>
                        <a href="reportes.php" aria-label="Ir a reportes"><span aria-hidden="true">📄</span><span class="quick-action-text"><strong>Reportes</strong></span></a>
                    </div>

                </div>

            </section>

        </main>

    </div>

</body>

</html>