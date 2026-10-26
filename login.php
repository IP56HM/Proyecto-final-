<?php

session_start();

require_once "config/conexion.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usuario = trim($_POST["usuario"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";

    if ($usuario === "" || $contrasena === "") {

        $error = "Debe completar todos los campos.";

    } else {

        $sql = "SELECT id_usuario, usuario, contrasena, rol, estado
                FROM administracion
                WHERE usuario = ?
                LIMIT 1";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $usuario);
        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {

            $datos = $resultado->fetch_assoc();

            if (
                $datos["estado"] == 1 &&
                password_verify($contrasena, $datos["contrasena"])
            ) {

                $_SESSION["id_usuario"] = $datos["id_usuario"];
                $_SESSION["usuario"] = $datos["usuario"];
                $_SESSION["rol"] = $datos["rol"];

                header("Location: dashboard.php");
                exit;

            } else {

                $error = "Usuario o contraseña incorrectos.";

            }

        } else {

            $error = "Usuario o contraseña incorrectos.";

        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | Librería San Sebastián</title>

    <link rel="stylesheet" href="css/login.css">
    <script src="js/login.js"></script>

</head>

<body>

    <main class="login-container">

        <section class="login-card">

            <div class="logo">
                📚
            </div>

            <h1>Librería San Sebastián</h1>

            <p class="subtitle">
                Sistema de Inventario
            </p>

            <?php if ($error !== ""): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <form action="login.php" method="POST">

                <div class="input-group">

                    <label for="usuario">
                        Usuario
                    </label>

                    <input type="text" id="usuario" name="usuario" placeholder="Ingrese su usuario" required
                        autocomplete="username">

                </div>

                <div class="input-group">

                    <label for="contrasena">
                        Contraseña
                    </label>

                    <input type="password" id="contrasena" name="contrasena" placeholder="Ingrese su contraseña"
                        required autocomplete="current-password">

                </div>

                <button type="submit">
                    Iniciar sesión
                </button>

            </form>
            <p>
                ¿No tienes una cuenta?
                <a href="#" onclick="irAPagina('registro.php'); return false;">
                    Registrarse
                </a>
            </p>

        </section>

    </main>

</body>

</html>