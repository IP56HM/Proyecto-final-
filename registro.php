<?php

require_once "config/conexion.php";

$error = "";
$exito = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usuario = trim($_POST["usuario"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";
    $confirmar = $_POST["confirmar"] ?? "";

    // =====================================
    // VALIDAR CAMPOS VACÍOS
    // =====================================

    if ($usuario === "" || $contrasena === "" || $confirmar === "") {

        $error = "Debe completar todos los campos.";

    }

    // =====================================
    // VALIDAR USUARIO
    // =====================================

    elseif (!preg_match("/^[A-Za-z0-9_]{3,50}$/", $usuario)) {

        $error = "El usuario debe tener entre 3 y 50 caracteres y solo puede contener letras, números y guion bajo.";

    }

    // =====================================
    // VALIDAR CONTRASEÑA
    // =====================================

    elseif (!preg_match("/^(?=.*[A-Z])[A-Za-z0-9]{7,}$/", $contrasena)) {

        $error = "La contraseña debe tener mínimo 7 caracteres, al menos una mayúscula y no puede contener caracteres especiales.";

    }

    // =====================================
    // CONFIRMAR CONTRASEÑA
    // =====================================

    elseif ($contrasena !== $confirmar) {

        $error = "Las contraseñas no coinciden.";

    }

    else {

        // =====================================
        // COMPROBAR SI EL USUARIO YA EXISTE
        // =====================================

        $sql = "SELECT id_usuario
                FROM administracion
                WHERE usuario = ?
                LIMIT 1";

        $stmt = $conexion->prepare($sql);

        $stmt->bind_param("s", $usuario);

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {

            $error = "El usuario ya existe.";

        } else {

            // =====================================
            // CIFRAR CONTRASEÑA
            // =====================================

            $contrasenaHash = password_hash(
                $contrasena,
                PASSWORD_DEFAULT
            );

            // =====================================
            // CREAR USUARIO
            // =====================================

            $rol = "consultador";
            $estado = 1;

            $sql = "INSERT INTO administracion
                    (usuario, contrasena, rol, correo, estado)
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $conexion->prepare($sql);

            /*
             * Tu BD actualmente tiene correo como INT.
             * Por eso usamos 0 temporalmente.
             */

            $correo = 0;

            $stmt->bind_param(
                "sssii",
                $usuario,
                $contrasenaHash,
                $rol,
                $correo,
                $estado
            );

            if ($stmt->execute()) {

                $exito = "Usuario registrado correctamente.";

            } else {

                $error = "No se pudo registrar el usuario.";

            }

        }

        $stmt->close();
    }
}

?>