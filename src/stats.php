<?php
// Verifica que el usuario tenga sesión activa; si no, redirige al login
require_once 'auth.php';

// Carga la clase mySQLConexion con todos sus métodos CRUD y de consulta
require_once "mySQLi.php";

// Instancia la conexión a la base de datos
$SQL = new mySQLConexion();

// Retorna el total en dinero de las ventas del día actual
$daily = $SQL->DAILYSTATS();

// Retorna el total en dinero de las ventas del mes actual
$monthly = $SQL->MONTHLYSTATS();

// Retorna la cantidad de ventas realizadas hoy
$dailySales = $SQL->DAILYSALES();

// TOPPRODUCT() ejecuta un JOIN entre detalle_venta y producto
// agrupando por producto y ordenando por cantidad vendida DESC LIMIT 1
// Retorna un arreglo asociativo con el nombre y total vendido del producto más popular
$topProduct = $SQL->TOPPRODUCT();

// Retorna un objeto mysqli_result con todos los registros de ventas
$sales = $SQL->GETSALES();

// Retorna un objeto mysqli_result con el inventario ordenado de menor a mayor stock
$inventory = $SQL->GETINVENTORY();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panes Bea</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main>
        <!-- Sidebar de navegación; inicia oculto con la clase "hidden" -->
        <div class="left hidden" id="sidebar">
            <img src="pfp.webp" alt="">
            <a href="stats.php">Inicio</a>
            <a href="fax.php">Facturar</a>
            <a href="editfax.php">Historial de facturacion</a>
            <a href="productos.php">Productos</a>
            <a href="box.php">Inventario</a>
            <a href="mov.php">Movimientos</a>
            <a href="logout.php" class="logout-btn">Cerrar sesion</a>
        </div>

        <!-- Contenedor principal del contenido -->
        <div class="right" id="content">

            <!-- Barra superior con el botón de toggle y el título -->
            <div class="topnav">
                <button id="toggleBtn">&#9776;</button>
                <section>
                    <h1>PanesBea</h1>
                    <img src="ppp.png" style="width: 3rem;" alt="">
                </section>
            </div>

            <div class="content">
                <h3 class="tittl1">Dashboard</h3>

                <!-- Tarjetas de estadísticas -->
                <div class="stats">

                    <!-- Tarjeta: total vendido hoy -->
                    <article class="s">
                        <img src="icon1.png" alt="" class="img1">
                        <section>
                            <h3>Ventas de hoy</h3>
                            <!-- echo imprime el valor retornado por DAILYSTATS() -->
                            <h4>$<?php echo $daily ?></h4>
                        </section>
                    </article>

                    <!-- Tarjeta: total vendido en el mes -->
                    <article class="s">
                        <img src="icon2.png" alt="" class="img2">
                        <section>
                            <h3>Ventas del mes</h3>
                            <!-- echo imprime el valor retornado por MONTHLYSTATS() -->
                            <h4>$<?php echo $monthly ?></h4>
                        </section>
                    </article>

                    <!-- Tarjeta: producto más vendido -->
                    <article class="s">
                        <img src="icon3.webp" alt="" class="img2">
                        <section>
                            <h3>Producto mas vendido</h3>
                            <!-- $topProduct es un arreglo asociativo; ['nombre'] accede al campo nombre -->
                            <h4><?php echo $topProduct['nombre']; ?></h4>
                        </section>
                    </article>

                </div>

                <!-- Tablas del dashboard -->
                <div class="tables">

                    <!-- Tabla: historial de ventas -->
                    <div class="table2">
                        <h2 class="table-title">Registro de ventas</h2>
                        <div class="tabletemplate">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID Venta</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // fetch_assoc() obtiene la siguiente fila del resultado
                                    // como un arreglo asociativo [columna => valor]
                                    // El while itera hasta que no haya más filas (retorna null)
                                    while ($row = $sales->fetch_assoc()) { ?>
                                        <tr>
                                            <!-- $row['id_venta'] accede al campo id_venta de la fila actual -->
                                            <td><?php echo $row['id_venta']; ?></td>
                                            <td><?php echo $row['fecha']; ?></td>
                                            <td>$<?php echo $row['total']; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tabla: inventario de ingredientes -->
                    <div class="table2">
                        <h2 class="table-title">Inventario actual</h2>
                        <div class="tabletemplate">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Ingrediente</th>
                                        <th>Stock</th>
                                        <th>Unidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // fetch_assoc() itera sobre los resultados de GETINVENTORY()
                                    // cada $row contiene nombre, stock y unidad_medida del ingrediente
                                    while ($row = $inventory->fetch_assoc()) { ?>
                                        <tr>
                                            <td><?php echo $row['nombre']; ?></td>
                                            <td><?php echo $row['stock']; ?></td>
                                            <td><?php echo $row['unidad_medida']; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>
</body>

<script>
    // DOMContentLoaded se dispara cuando el HTML fue completamente cargado y analizado,
    // sin esperar imágenes ni hojas de estilo. Garantiza que los elementos ya existen al correr el script.
    document.addEventListener("DOMContentLoaded", () => {

        // getElementById() busca y retorna el elemento HTML que tenga el id indicado
        const btn = document.getElementById("toggleBtn");
        const sidebar = document.getElementById("sidebar");
        const content = document.getElementById("content");

        // addEventListener() registra una función que se ejecuta cuando ocurre el evento indicado
        // En este caso escucha el evento "click" sobre el botón de toggle
        btn.addEventListener("click", () => {

            // classList.toggle() agrega la clase si no existe, o la elimina si ya está presente
            // Alternar "hidden" en el sidebar lo muestra u oculta mediante CSS (translateX)
            sidebar.classList.toggle("hidden");

            // Alternar "full" en el contenido ajusta el margin-left para ocupar el espacio del sidebar
            content.classList.toggle("full");
        });
    });
</script>

</html>