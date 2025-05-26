<?php
// Siempre al inicio del archivo
//session_start();
//include 'db.php'; // Asegura que $conn esté disponible

function obtenerInsumosBajoStock($umbral = 5) {
    global $conn;
    
    // Verificar que la conexión existe y es MySQLi
    if (!($conn instanceof mysqli)) {
        throw new Exception("Conexión a la base de datos no válida");
    }
    
    $query = "SELECT id, codigo, insumo, stock, ubicacion 
              FROM componentes 
              WHERE stock <= ?";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $umbral);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
