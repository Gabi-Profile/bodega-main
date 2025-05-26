<?php
include 'db.php';
session_start();

$editando = false;
$componente_edit = null;
$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10, 20, 30, 40, 50]) ? $cantidad_por_pagina : 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $cantidad_por_pagina;

$consulta = "SELECT * FROM componentes ORDER BY fecha_ingreso DESC LIMIT $cantidad_por_pagina OFFSET $offset";
$resultado = mysqli_query($conn, $consulta);
$personas_dentro = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
$total_resultado = mysqli_query($conn, "SELECT COUNT(*) AS total FROM componentes");
$total_filas = mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas = ceil($total_filas / $cantidad_por_pagina);

function obtenerValoresEnum($conn, $tabla, $columna) {
    $query = "SHOW COLUMNS FROM $tabla LIKE '$columna'";
    $resultado = mysqli_query($conn, $query);
    $fila = mysqli_fetch_assoc($resultado);
    
    if (preg_match("/^enum\(\'(.*)\'\)$/", $fila['Type'], $matches)) {
        $valores = explode("','", $matches[1]);
        return $valores;
    }
    return [];
}
$enum_especialidades = obtenerValoresEnum($conn, 'componentes', 'especialidad');
$enum_formatos = obtenerValoresEnum($conn, 'componentes', 'formato');
$enum_ubicaciones = obtenerValoresEnum($conn, 'componentes', 'ubicacion');

if (isset($_POST['agregar'])) {
    $nombre = $_POST["insumo"];
    $codigo = $_POST["codigo"];
    $stock = $_POST["stock"];
    $especialidad = $_POST["especialidad"];
    $formato = $_POST["formato"];
    $ubicacion = $_POST["ubicacion"];
    $fecha_ingreso = date('Y-m-d H:i:s');

    // Verificar si el insumo ya existe por su código
    $consulta_existente = "SELECT * FROM componentes WHERE codigo = '$codigo'";
    $resultado_existente = mysqli_query($conn, $consulta_existente);

    if (mysqli_num_rows($resultado_existente) > 0) {
        // Si existe, actualizar el insumo
        $update = "UPDATE componentes SET 
                    insumo = '$nombre', 
                    stock = stock + $stock, 
                    especialidad = '$especialidad', 
                    formato = '$formato', 
                    ubicacion = '$ubicacion', 
                    fecha_ingreso = '$fecha_ingreso'
                   WHERE codigo = '$codigo'";
        mysqli_query($conn, $update);
        $mensaje = "Insumo actualizado correctamente.";
    } else {
        // Si no existe, agregar uno nuevo
        $insert = "INSERT INTO componentes (codigo, insumo, stock, especialidad, formato, ubicacion, fecha_ingreso) 
                   VALUES ('$codigo', '$nombre', '$stock', '$especialidad', '$formato', '$ubicacion', '$fecha_ingreso')";
        mysqli_query($conn, $insert);
        $mensaje = "Insumo agregado correctamente.";
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?mensaje=" . urlencode($mensaje));
    exit();
}


if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    mysqli_query($conn, "DELETE FROM componentes WHERE id = $id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['editar'])) {
    $editando = true;
    $id = $_GET['editar'];
    $resultado = mysqli_query($conn, "SELECT * FROM componentes WHERE id = $id");
    $componente_edit = mysqli_fetch_assoc($resultado);
}

if (isset($_POST['guardar_cambios'])) {
    $id = $_POST["id"];
    $nombre = $_POST["insumo"];
    $codigo = $_POST["codigo"];
    $cantidad = $_POST["stock"];
    $especialidad = $_POST["especialidad"];
    $formato = $_POST["formato"];
    $ubicacion = $_POST["ubicacion"];
    $fecha_ingreso = date('Y-m-d H:i:s');

    $update = "UPDATE componentes SET 
                codigo = '$codigo',
                insumo = '$nombre',
                stock = '$cantidad',
                especialidad = '$especialidad',
                formato = '$formato',
                ubicacion = '$ubicacion',
                fecha_ingreso = '$fecha_ingreso'
               WHERE id = $id";
    mysqli_query($conn, $update);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$sql = "SELECT * FROM componentes";
$result = $conn->query($sql);
$solicitudes_result = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $solicitudes_result[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administracion de insumos del Hospital Clinico Félix Bulnes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Agregar insumos medicos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
        </div>
    </div>
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
    <div class="container">
        <div id="mensaje-container">
            <?php if (isset($mensaje)) echo $mensaje; ?>
        </div>
        <h2><?= $editando ? 'Editar Insumos' : 'Agregar Insumos' ?></h2>
        <button type="button" onclick="toggleExcelForm()">📂 Importar desde Excel</button>
        <div id="excelFormContainer" style="display: none; margin-top: 10px;">
            <form action="importar_excel.php" method="post" enctype="multipart/form-data">
                <label for="archivo_excel">Subir Excel:</label>
                <input type="file" name="archivo_excel" accept=".xlsx, .xls">
                <button type="submit">Importar</button>
                <button type="button" onclick="toggleExcelForm()">Cancelar</button>
            </form>
        </div>
        <button type="button" onclick="abrirEscaner()">📷 Escanear Código</button>
        <div id="escaneo-container" style="display:none;">
            <div id="lector" style="width: 100%; max-width: 400px; margin: 10px auto;"></div>
            <button type="button" onclick="cerrarEscaner()">❌ Finalizar Escaneo</button>
        </div>

        <form action="" method="post">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= $componente_edit['id'] ?>">
            <?php endif; ?>
            <input type="text" name="codigo" placeholder="Código Ad" required
                value="<?= $editando ? $componente_edit['codigo'] : '' ?>" onblur="buscarComponente(this.value)">
            <input type="text" name="insumo" placeholder="Insumo" required
                value="<?= $editando ? $componente_edit['insumo'] : '' ?>">
                <select name="especialidad" required>
                    <option value="">Seleccione especialidad</option>
                    <?php foreach ($enum_especialidades as $valor): ?>
                        <option value="<?= $valor ?>" <?= $editando && $componente_edit['especialidad'] == $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($valor)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="formato" required>
                    <option value="">Seleccione formato</option>
                    <?php foreach ($enum_formatos as $valor): ?>
                        <option value="<?= $valor ?>" <?= $editando && $componente_edit['formato'] == $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($valor)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="ubicacion" required>
                    <option value="">Seleccione ubicación</option>
                    <?php foreach ($enum_ubicaciones as $valor): ?>
                        <option value="<?= $valor ?>" <?= $editando && $componente_edit['ubicacion'] == $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($valor)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <input type="number" name="stock" placeholder="Cantidad" required
                value="<?= $editando ? $componente_edit['stock'] : '' ?>">
            <?php if ($editando): ?>
                <button type="submit" name="guardar_cambios">Guardar Cambios</button>
                <a href="<?= $_SERVER['PHP_SELF'] ?>">Cancelar</a>
            <?php else: ?>
                <button type="submit" name="agregar">Agregar Insumos</button>
            <?php endif; ?>
        </form>
        <?php if (!empty($personas_dentro)): ?>
            <h2>Lista de Insumos</h2>
            <table>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Stock</th>
                <th>Especialidad</th>
                <th>Formato</th>
                <th>Ubicacion</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
            <?php foreach ($personas_dentro as $componente): ?>
                <tr>
                    <td><?= htmlspecialchars($componente['codigo']) ?></td>
                    <td><?= htmlspecialchars($componente['insumo']) ?></td>
                    <td><?= htmlspecialchars($componente['stock']) ?></td>
                    <td><?= htmlspecialchars($componente['especialidad']) ?></td>
                    <td><?= htmlspecialchars($componente['formato']) ?></td>
                    <td><?= htmlspecialchars($componente['ubicacion']) ?></td>
                    <td><?= date('d-m-y H:i', strtotime($componente['fecha_ingreso'])) ?></td>
                    <td>
                        <a href="?editar=<?= $componente['id'] ?>" class="btn">Editar</a>
                        <a href="?eliminar=<?= $componente['id'] ?>" class="btn" onclick="return confirm('¿Estás seguro de eliminar este componente?');">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </table>
            <form method="GET" style="margin-bottom: 10px;">
                <label for="cantidad">Mostrar:</label>
                <select name="cantidad" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 30, 40, 50] as $cantidad): ?>
                        <option value="<?= $cantidad ?>" <?= $cantidad_por_pagina == $cantidad ? 'selected' : '' ?>><?= $cantidad ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="pagina" value="1">
            </form>
            <div class="pagination-container">
                <?php
                $rango_visible = 5;
                $inicio = max(1, $pagina_actual - floor($rango_visible / 2));
                $fin = min($total_paginas, $inicio + $rango_visible - 1);

                if ($inicio > 1) {
                    echo '<a href="?pagina=1&cantidad=' . $cantidad_por_pagina . '">1</a>';
                    if ($inicio > 2) echo '<span>...</span>';
                }

                for ($i = $inicio; $i <= $fin; $i++) {
                    $active = $pagina_actual == $i ? 'active' : '';
                    echo '<a href="?pagina=' . $i . '&cantidad=' . $cantidad_por_pagina . '" class="' . $active . '">' . $i . '</a>';
                }

                if ($fin < $total_paginas) {
                    if ($fin < $total_paginas - 1) echo '<span>...</span>';
                    echo '<a href="?pagina=' . $total_paginas . '&cantidad=' . $cantidad_por_pagina . '">' . $total_paginas . '</a>';
                }

                if ($pagina_actual > 1) {
                    echo '<a href="?pagina=' . ($pagina_actual - 1) . '&cantidad=' . $cantidad_por_pagina . '">Anterior</a>';
                }

                if ($pagina_actual < $total_paginas) {
                    echo '<a href="?pagina=' . ($pagina_actual + 1) . '&cantidad=' . $cantidad_por_pagina . '">Siguiente</a>';
                }
                ?>
            </div>
        <?php endif; ?>
                <?php if (isset($_GET['importado'])): ?>
            <div id="success-msg">¡Archivo importado correctamente!</div>
        <?php endif; ?>
    </div>
<script>
    function toggleAccountInfo() {
        const info = document.getElementById('accountInfo');
        info.style.display = info.style.display === 'none' ? 'block' : 'none';
    }

    function buscarComponente(codigo) {
        // Usamos Fetch para obtener el componente por su código
        fetch("buscar_componente.php?codigo=" + encodeURIComponent(codigo))
            .then(response => response.json())
            .then(data => {
                if (data.encontrado) {
                    // Rellenamos los campos con los valores del insumo encontrado
                    document.querySelector('input[name="insumo"]').value = data.insumo;
                    document.querySelector('input[name="codigo"]').value = data.codigo;
                    document.querySelector('select[name="especialidad"]').value = data.especialidad;
                    document.querySelector('select[name="formato"]').value = data.formato;
                    document.querySelector('select[name="ubicacion"]').value = data.ubicacion;
                    document.querySelector('input[name="stock"]').value = data.stock;
                    alert("Componente detectado: " + data.insumo);
                } else {
                    alert("Componente no encontrado para el código: " + codigo);
                }
            })
            .catch(error => {
                alert("Error al buscar el componente: " + error);
            });
    }

    let html5QrCode;

    function abrirEscaner() {
        document.getElementById("escaneo-container").style.display = "block";
        html5QrCode = new Html5Qrcode("lector");
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };

        html5QrCode.start(
            { facingMode: "environment" },
            config,
            (decodedText, decodedResult) => {
                html5QrCode.stop().then(() => {
                    document.getElementById("escaneo-container").style.display = "none";
                });
                buscarComponente(decodedText); // Llamada con el código decodificado
            },
            errorMessage => {
                // console.warn(errorMessage);
            }
        ).catch(err => {
            alert("Error al iniciar cámara: " + err);
        });
    }

    function cerrarEscaner() {
        if (html5QrCode) {
            html5QrCode.stop().then(() => {
                document.getElementById("escaneo-container").style.display = "none";
                html5QrCode.clear();
            }).catch(err => {
                alert("No se pudo detener el escáner: " + err);
            });
        }
    }

    function buscarComponente(codigo) {
    if (codigo.trim() === "") return;

    fetch("buscar_componente.php?codigo=" + encodeURIComponent(codigo))
        .then(response => response.json())
        .then(data => {
            if (data.encontrado) {
                // Rellenar los campos con los datos del insumo encontrado
                document.querySelector('input[name="insumo"]').value = data.insumo;
                document.querySelector('input[name="stock"]').value = data.stock;
                document.querySelector('select[name="especialidad"]').value = data.especialidad;
                document.querySelector('select[name="formato"]').value = data.formato;
                document.querySelector('select[name="ubicacion"]').value = data.ubicacion;
                alert("Insumo detectado: " + data.insumo);
            } else {
                alert("El insumo no se encuentra en la base de datos.");
            }
        })
        .catch(error => {
            alert("Error al buscar el insumo: " + error);
        });
    }
    function toggleExcelForm() {
        const container = document.getElementById("excelFormContainer");
        container.style.display = container.style.display === "none" ? "block" : "none";
    }

    // Evento para capturar la tecla 'n' y completar los campos con el insumo con código '0140010367'
    document.addEventListener('keydown', function(event) {
        if (event.key === 'n') {
            buscarComponente('0140010367');  // Código específico al presionar 'n'
        }
    });
</script>

</body>
</html>

<?php
$conn->close();
?>