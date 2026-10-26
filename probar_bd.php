<?php
$servidor = "sql306.infinityfree.com";
$usuario = "if0_42372113";
$contrasena = "11Raphinha";
$base_datos = "if0_42372113_isa";
$puerto = 3306;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion = new mysqli(
        $servidor,
        $usuario,
        $contrasena,
        $base_datos,
        $puerto
    );

    $conexion->set_charset("utf8mb4");

    echo "CONEXION EXITOSA CON MI BASE DE DATOS";

    $conexion->close();

} catch (mysqli_sql_exception $e) {
    error_log("Error MySQL: " . $e->getMessage());

    echo "ERROR DE CONEXION";
}
?>