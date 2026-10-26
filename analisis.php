<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php");
    exit;
}

require_once "config/conexion.php";
require_once __DIR__ . '/consultas_netas.php';

$usuario = $_SESSION["usuario"];
$rol = $_SESSION["rol"];

/* ==================================================
   CONFIGURACIÓN DE TABLAS Y COLUMNAS
   Si algún nombre no coincide con tu base de datos,
   SOLO cambia el texto de la derecha aquí.
================================================== */

$C = [
    "ventas"     => ["tabla" => "ventas",         "id" => "id_venta",    "fecha" => "fecha", "total" => "total"],
    "compras"    => ["tabla" => "compras",        "id" => "id_compra",   "fecha" => "fecha", "total" => "total"],
    "perdidas"   => ["tabla" => "perdidas",       "fecha" => "fecha",    "valor" => "valor_perdida"],
    "detalle_v"  => ["tabla" => "detalle_ventas", "id_venta" => "id_venta", "id_prod" => "id_producto", "cantidad" => "cantidad", "subtotal" => "subtotal"],
    "inventario" => ["tabla" => "inventario",     "id" => "id_producto", "nombre" => "nombre", "id_cat" => "id_categoria", "existencias" => "existencias", "minimo" => "stock_minimo", "estado" => "estado"],
    "categorias" => ["tabla" => "categorias",     "id" => "id_categoria", "nombre" => "nombre"],
];

/* ==================================================
   FUNCIÓN PARA CONSULTAR SIN QUE LA PÁGINA SE CAIGA
   Si una consulta falla, devuelve null y guarda el aviso.
================================================== */

$avisos = [];

function consultar($conexion, $seccion, $sql, $tipos = "", $params = [])
{
    global $avisos;

    try {

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            throw new Exception($conexion->error);
        }

        if ($tipos !== "") {
            $stmt->bind_param($tipos, ...$params);
        }

        $stmt->execute();

        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        return $filas;

    } catch (Throwable $e) {

        $avisos[$seccion] = $e->getMessage();

        return null;
    }
}

/* ==================================================
   PERIODO SELECCIONADO
================================================== */

$rango = $_GET["rango"] ?? "30";
$hasta = new DateTime("today");
$desde = new DateTime("today");

if (in_array($rango, ["7", "30", "90", "365"], true)) {

    $desde->modify("-" . ((int)$rango - 1) . " days");

} elseif ($rango === "mes") {

    $desde->modify("first day of this month");

} elseif ($rango === "personalizado") {

    $d = DateTime::createFromFormat("Y-m-d", $_GET["desde"] ?? "");
    $h = DateTime::createFromFormat("Y-m-d", $_GET["hasta"] ?? "");

    if ($d && $h) {

        $desde = $d;
        $hasta = $h;

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

    } else {

        $rango = "30";
        $desde->modify("-29 days");
    }

} else {

    $rango = "30";
    $desde->modify("-29 days");
}

/* Límite de seguridad: máximo 3 años */
$maximo = (clone $hasta)->modify("-1095 days");

if ($desde < $maximo) {
    $desde = $maximo;
}

$dias = $desde->diff($hasta)->days + 1;
$agrupar_por_dia = $dias <= 62;

$f_desde = $desde->format("Y-m-d") . " 00:00:00";
$f_hasta = $hasta->format("Y-m-d") . " 23:59:59";

$formato_sql = $agrupar_por_dia ? "%Y-%m-%d" : "%Y-%m";

/* ==================================================
   SERIES (ventas, compras, pérdidas)
================================================== */

function serie($conexion, $seccion, $tabla, $col_fecha, $col_valor, $formato_sql, $f_desde, $f_hasta)
{
    $sql = "SELECT DATE_FORMAT(`$col_fecha`, '$formato_sql') AS periodo,
                   COALESCE(SUM(`$col_valor`), 0) AS total
            FROM `$tabla`
            WHERE `$col_fecha` BETWEEN ? AND ?
            GROUP BY periodo";

    $filas = consultar($conexion, $seccion, $sql, "ss", [$f_desde, $f_hasta]);

    if ($filas === null) {
        return null;
    }

    $datos = [];

    foreach ($filas as $f) {
        $datos[$f["periodo"]] = (float)$f["total"];
    }

    return $datos;
}

$filas_ventas_netas = consultar($conexion, "Ventas", "SELECT DATE_FORMAT(fecha, '$formato_sql') AS periodo, COALESCE(SUM(total_neto),0) AS total FROM $ss_ventas_netas netas WHERE fecha BETWEEN ? AND ? GROUP BY periodo", "ss", [$f_desde, $f_hasta]);
$s_ventas = $filas_ventas_netas === null ? null : [];
if ($s_ventas !== null) { foreach ($filas_ventas_netas as $fila_neta) { $s_ventas[$fila_neta['periodo']] = (float)$fila_neta['total']; } }
$s_compras = serie($conexion, "Compras", $C["compras"]["tabla"], $C["compras"]["fecha"], $C["compras"]["total"], $formato_sql, $f_desde, $f_hasta);
$s_perdidas = serie($conexion, "Pérdidas", $C["perdidas"]["tabla"], $C["perdidas"]["fecha"], $C["perdidas"]["valor"], $formato_sql, $f_desde, $f_hasta);

/* Etiquetas continuas (rellena con 0 los días/meses sin movimiento) */

$etiquetas = [];
$claves = [];

$cursor = clone $desde;

if (!$agrupar_por_dia) {
    $cursor->modify("first day of this month");
}

while ($cursor <= $hasta) {

    if ($agrupar_por_dia) {
        $claves[] = $cursor->format("Y-m-d");
        $etiquetas[] = $cursor->format("d/m");
        $cursor->modify("+1 day");
    } else {
        $claves[] = $cursor->format("Y-m");
        $etiquetas[] = $cursor->format("m/Y");
        $cursor->modify("+1 month");
    }
}

function alinear($serie, $claves)
{
    $r = [];

    foreach ($claves as $k) {
        $r[] = round($serie[$k] ?? 0, 2);
    }

    return $r;
}

$datos_ventas = alinear($s_ventas ?? [], $claves);
$datos_compras = alinear($s_compras ?? [], $claves);
$datos_perdidas = alinear($s_perdidas ?? [], $claves);

$total_ventas = array_sum($datos_ventas);
$total_compras = array_sum($datos_compras);
$total_perdidas = array_sum($datos_perdidas);
$balance = $total_ventas - $total_compras - $total_perdidas;

/* ==================================================
   EXPORTAR A CSV
================================================== */

if (isset($_GET["exportar"])) {

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=analisis_" . date("Ymd") . ".csv");

    $out = fopen("php://output", "w");

    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ["Periodo", "Ventas", "Compras", "Perdidas"]);

    foreach ($claves as $i => $k) {
        fputcsv($out, [$k, $datos_ventas[$i], $datos_compras[$i], $datos_perdidas[$i]]);
    }

    fputcsv($out, ["TOTAL", $total_ventas, $total_compras, $total_perdidas]);

    fclose($out);
    exit;
}

/* ==================================================
   PRODUCTOS MÁS VENDIDOS
================================================== */

$dv = $C["detalle_v"];
$v = $C["ventas"];
$inv = $C["inventario"];
$cat = $C["categorias"];

$top_productos = consultar(
    $conexion,
    "Productos más vendidos",
    "SELECT i.`{$inv['nombre']}` AS nombre,
            SUM(d.`cantidad_neta`) AS cantidad,
            SUM(d.`subtotal_neto`) AS monto
     FROM {$ss_detalle_neto} d
     INNER JOIN `{$v['tabla']}` v ON v.`{$v['id']}` = d.`{$dv['id_venta']}`
     INNER JOIN `{$inv['tabla']}` i ON i.`{$inv['id']}` = d.`{$dv['id_prod']}`
     WHERE v.`{$v['fecha']}` BETWEEN ? AND ?
     GROUP BY i.`{$inv['id']}`, i.`{$inv['nombre']}`
     ORDER BY cantidad DESC
     LIMIT 5",
    "ss",
    [$f_desde, $f_hasta]
);

/* ==================================================
   VENTAS POR CATEGORÍA
================================================== */

$por_categoria = consultar(
    $conexion,
    "Ventas por categoría",
    "SELECT c.`{$cat['nombre']}` AS nombre,
            SUM(d.`subtotal_neto`) AS monto
     FROM {$ss_detalle_neto} d
     INNER JOIN `{$v['tabla']}` v ON v.`{$v['id']}` = d.`{$dv['id_venta']}`
     INNER JOIN `{$inv['tabla']}` i ON i.`{$inv['id']}` = d.`{$dv['id_prod']}`
     INNER JOIN `{$cat['tabla']}` c ON c.`{$cat['id']}` = i.`{$inv['id_cat']}`
     WHERE v.`{$v['fecha']}` BETWEEN ? AND ?
     GROUP BY c.`{$cat['id']}`, c.`{$cat['nombre']}`
     ORDER BY monto DESC
     LIMIT 6",
    "ss",
    [$f_desde, $f_hasta]
);

/* ==================================================
   STOCK BAJO (no depende del periodo)
================================================== */

$stock_bajo = consultar(
    $conexion,
    "Stock bajo",
    "SELECT `{$inv['nombre']}` AS nombre,
            `{$inv['existencias']}` AS existencias,
            `{$inv['minimo']}` AS minimo
     FROM `{$inv['tabla']}`
     WHERE `{$inv['existencias']}` <= `{$inv['minimo']}`
     AND `{$inv['estado']}` = 1
     ORDER BY (`{$inv['existencias']}` - `{$inv['minimo']}`) ASC
     LIMIT 8"
);

/* Valor máximo para dibujar las barras proporcionales */

$max_top = 0;

foreach ($top_productos ?? [] as $p) {
    $max_top = max($max_top, (float)$p["cantidad"]);
}

$max_cat = 0;

foreach ($por_categoria ?? [] as $c) {
    $max_cat = max($max_cat, (float)$c["monto"]);
}

$grafico = [
    "etiquetas" => $etiquetas,
    "ventas"    => $datos_ventas,
    "compras"   => $datos_compras,
    "perdidas"  => $datos_perdidas,
];

$parametros_csv = $_GET;
$parametros_csv["exportar"] = 1;

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Análisis | Librería San Sebastián</title>

    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/analisis.css">

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

                <a href="analisis.php" class="active">
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

                    <h1>Análisis</h1>

                    <p>
                        Del <?= $desde->format("d/m/Y") ?>
                        al <?= $hasta->format("d/m/Y") ?>
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
                 FILTRO DE PERIODO
            =============================== -->

            <form class="filtro-periodo" method="GET" action="analisis.php">

                <label for="rango">Periodo</label>

                <select name="rango" id="rango">
                    <option value="7" <?= $rango === "7" ? "selected" : "" ?>>Últimos 7 días</option>
                    <option value="30" <?= $rango === "30" ? "selected" : "" ?>>Últimos 30 días</option>
                    <option value="90" <?= $rango === "90" ? "selected" : "" ?>>Últimos 90 días</option>
                    <option value="mes" <?= $rango === "mes" ? "selected" : "" ?>>Este mes</option>
                    <option value="365" <?= $rango === "365" ? "selected" : "" ?>>Último año</option>
                    <option value="personalizado" <?= $rango === "personalizado" ? "selected" : "" ?>>Personalizado</option>
                </select>

                <span class="fechas" id="fechas" <?= $rango === "personalizado" ? "" : "hidden" ?>>

                    <input type="date" name="desde" value="<?= $desde->format('Y-m-d') ?>" aria-label="Desde">
                    <input type="date" name="hasta" value="<?= $hasta->format('Y-m-d') ?>" aria-label="Hasta">

                </span>

                <button type="submit">Aplicar</button>

                <a class="exportar" href="analisis.php?<?= htmlspecialchars(http_build_query($parametros_csv)) ?>">
                    Exportar CSV
                </a>

            </form>


            <?php if (!empty($avisos)): ?>

                <div class="aviso-datos">

                    <strong>Algunas secciones no se pudieron cargar.</strong>

                    <?php if ($rol === "administrador"): ?>

                        <ul>
                            <?php foreach ($avisos as $seccion => $mensaje): ?>
                                <li>
                                    <?= htmlspecialchars($seccion) ?>:
                                    <?= htmlspecialchars($mensaje) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <p>Revisa los nombres de tablas y columnas al inicio de analisis.php.</p>

                    <?php else: ?>

                        <p>Avisa al administrador del sistema.</p>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


            <!-- ==============================
                 TARJETAS
            =============================== -->

            <section class="cards">

                <article class="card">

                    <div class="card-icon green">
                        💰
                    </div>

                    <div>
                        <span>Ventas</span>
                        <strong>$<?= number_format($total_ventas, 2) ?></strong>
                    </div>

                </article>

                <article class="card">

                    <div class="card-icon blue">
                        📥
                    </div>

                    <div>
                        <span>Compras</span>
                        <strong>$<?= number_format($total_compras, 2) ?></strong>
                    </div>

                </article>

                <article class="card">

                    <div class="card-icon red">
                        📉
                    </div>

                    <div>
                        <span>Pérdidas</span>
                        <strong>$<?= number_format($total_perdidas, 2) ?></strong>
                    </div>

                </article>

                <article class="card">

                    <div class="card-icon yellow">
                        ⚖️
                    </div>

                    <div>
                        <span>Balance</span>
                        <strong class="<?= $balance < 0 ? 'negativo' : '' ?>">
                            <?= $balance < 0 ? "-" : "" ?>$<?= number_format(abs($balance), 2) ?>
                        </strong>
                    </div>

                </article>

            </section>

            <p class="nota-balance">
                Balance = ventas − compras − pérdidas del periodo.
            </p>


            <!-- ==============================
                 GRÁFICO
            =============================== -->

            <section class="panel">

                <div class="panel-header">

                    <h2>Ventas, compras y pérdidas</h2>

                    <span class="agrupado">
                        <?= $agrupar_por_dia ? "Por día" : "Por mes" ?>
                    </span>

                </div>

                <?php if ($total_ventas + $total_compras + $total_perdidas == 0): ?>

                    <p class="vacio">
                        No hay movimientos en este periodo. Prueba con un rango más amplio.
                    </p>

                <?php else: ?>

                    <div class="grafico-caja">
                        <canvas id="grafico" aria-label="Gráfico de ventas, compras y pérdidas" role="img"></canvas>
                    </div>

                <?php endif; ?>

            </section>


            <!-- ==============================
                 DETALLES
            =============================== -->

            <section class="analisis-grid">

                <div class="panel">

                    <div class="panel-header">
                        <h2>Más vendidos</h2>
                    </div>

                    <?php if ($top_productos === null): ?>

                        <p class="vacio">No disponible.</p>

                    <?php elseif (count($top_productos) === 0): ?>

                        <p class="vacio">Sin ventas en este periodo.</p>

                    <?php else: ?>

                        <ul class="ranking">

                            <?php foreach ($top_productos as $p): ?>

                                <li>

                                    <div class="ranking-texto">
                                        <span><?= htmlspecialchars($p["nombre"]) ?></span>
                                        <strong><?= (int)$p["cantidad"] ?> uds.</strong>
                                    </div>

                                    <div class="barra">
                                        <div style="width: <?= $max_top > 0 ? round(($p['cantidad'] / $max_top) * 100) : 0 ?>%"></div>
                                    </div>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>


                <div class="panel">

                    <div class="panel-header">
                        <h2>Ventas por categoría</h2>
                    </div>

                    <?php if ($por_categoria === null): ?>

                        <p class="vacio">No disponible.</p>

                    <?php elseif (count($por_categoria) === 0): ?>

                        <p class="vacio">Sin ventas en este periodo.</p>

                    <?php else: ?>

                        <ul class="ranking">

                            <?php foreach ($por_categoria as $c): ?>

                                <li>

                                    <div class="ranking-texto">
                                        <span><?= htmlspecialchars($c["nombre"]) ?></span>
                                        <strong>$<?= number_format($c["monto"], 2) ?></strong>
                                    </div>

                                    <div class="barra alt">
                                        <div style="width: <?= $max_cat > 0 ? round(($c['monto'] / $max_cat) * 100) : 0 ?>%"></div>
                                    </div>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>


                <div class="panel">

                    <div class="panel-header">
                        <h2>Stock bajo</h2>
                        <a href="compras.php">Ir a comprar</a>
                    </div>

                    <?php if ($stock_bajo === null): ?>

                        <p class="vacio">No disponible.</p>

                    <?php elseif (count($stock_bajo) === 0): ?>

                        <p class="vacio">Todos los productos están sobre su stock mínimo.</p>

                    <?php else: ?>

                        <table class="tabla-stock">

                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Hay</th>
                                    <th>Mínimo</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($stock_bajo as $s): ?>

                                    <tr>
                                        <td><?= htmlspecialchars($s["nombre"]) ?></td>
                                        <td class="<?= (int)$s['existencias'] === 0 ? 'agotado' : '' ?>">
                                            <?= (int)$s["existencias"] ?>
                                        </td>
                                        <td><?= (int)$s["minimo"] ?></td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php endif; ?>

                </div>

            </section>

        </main>

    </div>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

    <script>
        /* Mostrar u ocultar las fechas personalizadas */
        const selectorRango = document.getElementById("rango");
        const cajaFechas = document.getElementById("fechas");

        selectorRango.addEventListener("change", function () {
            cajaFechas.hidden = selectorRango.value !== "personalizado";
        });

        /* Gráfico */
        const datos = <?= json_encode($grafico, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const lienzo = document.getElementById("grafico");

        if (lienzo && typeof Chart !== "undefined") {

            new Chart(lienzo, {
                type: "bar",
                data: {
                    labels: datos.etiquetas,
                    datasets: [
                        { label: "Ventas", data: datos.ventas, backgroundColor: "#22a06b" },
                        { label: "Compras", data: datos.compras, backgroundColor: "#3b82f6" },
                        { label: "Pérdidas", data: datos.perdidas, backgroundColor: "#ef4444" }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: function (v) { return "$" + v; } }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (c) {
                                    return c.dataset.label + ": $" + c.parsed.y.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });

        } else if (lienzo) {

            lienzo.outerHTML = "<p class='vacio'>No se pudo cargar el gráfico. Revisa tu conexión a internet.</p>";
        }
    </script>

</body>

</html>
