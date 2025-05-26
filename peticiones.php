<?php
session_start();
include 'db.php';

// Filtros desde GET
$nombre_usuario_filtro = isset($_GET['codigo']) ? $conn->real_escape_string($_GET['codigo']) : '';

// Paginación
$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10, 20, 30, 40, 50]) ? $cantidad_por_pagina : 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $cantidad_por_pagina;

// Consulta base con filtros
$sql_base = "FROM cirugias WHERE 1";
if (!empty($nombre_usuario_filtro)) {
    $sql_base .= " AND (cod_cirugia LIKE '%$nombre_usuario_filtro%' OR cirugia LIKE '%$nombre_usuario_filtro%')";
}
$sql_total = "SELECT COUNT(*) as total " . $sql_base;
$sql_final = "SELECT * " . $sql_base . " ORDER BY id DESC LIMIT $cantidad_por_pagina OFFSET $offset";


// Consulta total para paginación
$total_resultado = mysqli_query($conn, $sql_total);
$total_filas = mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas = ceil($total_filas / $cantidad_por_pagina);

// Consulta final con paginación
$resultado = mysqli_query($conn, $sql_final);
$personas_dentro = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

// Autocompletado
if (isset($_GET['query'])) {
    $query = $conn->real_escape_string($_GET['query']);
    $sql = "SELECT cod_cirugia, cirugia FROM cirugias 
            WHERE cod_cirugia LIKE '%$query%' OR cirugia LIKE '%$query%' 
            LIMIT 10";
    $result = $conn->query($sql);
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['codigo'] . " - " . $row['insumo'];
    }
    header('Content-Type: application/json');
    echo json_encode($suggestions);
    exit();
}

// Insertar el movimiento en la tabla salidas
$sql_insert_salida = "INSERT INTO salidas (id_componente, cantidad, fecha_salida, realizado_por)
                      VALUES (?, ?, NOW(), ?)";
$stmt = $conn->prepare($sql_insert_salida);
$stmt->bind_param("iis", $id_insumo, $cantidad_reservada, $nombre_usuario);
$stmt->execute();

// Luego descontar del stock en componentes
$sql_update_stock = "UPDATE componentes SET stock = stock - ? WHERE id = ?";
$stmt2 = $conn->prepare($sql_update_stock);
$stmt2->bind_param("ii", $cantidad_reservada, $id_insumo);
$stmt2->execute();


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administración de Insumos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Gestion de insumos medicos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
            <button type="button" class="volver-btn" onclick="window.location.href='eleccion.php'">Volver</button>
        </div>
    </div>
</head>
<body>
    <div class="container">
        <div class="filters">
            <form method="GET" action="">
                <label for="codigo">Insumo:</label>
                <div class="input-sugerencias-wrapper">
                    <input type="text" id="codigo" name="codigo" autocomplete="off"
                        placeholder="Escribe el insumo para buscar..."
                        value="<?php echo htmlspecialchars($nombre_usuario_filtro); ?>">
                    <div id="sugerencias" class="sugerencias-box"></div>
                </div>
                <div class="botones-filtros">
                    <button type="submit">Filtrar</button>
                    <button type="button" class="limpiar-filtros-btn" onclick="window.location='peticiones.php'">Limpiar Filtros</button>
                </div>
            </form>
        </div>
        <?php if (!empty($personas_dentro)): ?>
            <h2>Lista de Insumos</h2>
            <table>
                <tr>
                    <th>Código de Cirugía</th>
                    <th>Cirugía</th>
                    <th>Pabellón</th>
                    <th>Cirujano</th>
                    <th>Equipo</th>
                    <th>Paciente</th>
                    <th>Insumos</th>
                </tr>
                <?php foreach ($personas_dentro as $cirugia): ?>
                <tr>
                    <td><?= htmlspecialchars($cirugia['cod_cirugia']) ?></td>
                    <td><?= htmlspecialchars($cirugia['cirugia']) ?></td>
                    <td><?= htmlspecialchars($cirugia['pabellon']) ?></td>
                    <td><?= htmlspecialchars($cirugia['cirujano']) ?></td>
                    <td><?= htmlspecialchars($cirugia['equipo']) ?></td>
                    <td><?= htmlspecialchars($cirugia['nombre_paciente']) ?></td>
                    <td><?= htmlspecialchars($cirugia['insumos']) ?></td>
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
        <?php else: ?>
            <p>No se encontraron resultados para tu búsqueda.</p>
        <?php endif; ?>
    </div>

    <script>
        div.addEventListener("click", () => {
            input.value = item.split(" - ")[0];
            sugerenciasBox.innerHTML = "";
            sugerenciasBox.style.display = "none";
        });

        document.addEventListener("DOMContentLoaded", function() {
            const input = document.getElementById("codigo");
            const sugerenciasBox = document.getElementById("sugerencias");

            input.addEventListener("input", function() {
                const query = input.value;

                if (query.length < 2) {
                    sugerenciasBox.innerHTML = "";
                    sugerenciasBox.style.display = "none";
                    return;
                }

                fetch(`gestion_cirugias.php?query=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        sugerenciasBox.innerHTML = "";
                        if (data.length === 0) {
                            sugerenciasBox.style.display = "none";
                            return;
                        }

                        data.forEach(item => {
                            const div = document.createElement("div");
                            div.textContent = item;
                            div.addEventListener("click", () => {
                                input.value = item.split(" - ")[0];
                                sugerenciasBox.innerHTML = "";
                                sugerenciasBox.style.display = "none";
                            });
                            sugerenciasBox.appendChild(div);
                        });
                        sugerenciasBox.style.display = "block";
                });
            });

            document.addEventListener("click", function(e) {
                if (!sugerenciasBox.contains(e.target) && e.target !== input) {
                    sugerenciasBox.style.display = "none";
                }
            });
        });

        function toggleAccountInfo() {
            const info = document.getElementById('accountInfo');
            info.style.display = info.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
