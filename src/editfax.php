<?php
// Verifica que el usuario tenga sesion activa; si no, redirige al login
require_once 'auth.php';

// Carga la clase mySQLConexion con todos sus metodos CRUD
require_once "mySQLi.php";

// Instancia la conexion a la base de datos
$SQL = new mySQLConexion();

// ── Endpoint JSON: detalle de una venta ───────────────────────────────────────
// isset() verifica si $_GET['action'] existe antes de leerlo
// Este bloque responde con JSON cuando el JS hace fetch('?action=get_detalle&id=X')
if (isset($_GET['action']) && $_GET['action'] === 'get_detalle') {

    // header() indica al navegador que la respuesta es JSON y no HTML
    header('Content-Type: application/json');

    // (int) convierte el valor a entero para evitar inyecciones
    // ?? 0 retorna 0 si el parametro no existe en $_GET
    $id_venta = (int) ($_GET['id'] ?? 0);

    if ($id_venta <= 0) {
        // json_encode() convierte el arreglo PHP a string JSON para enviarlo al cliente
        echo json_encode(['error' => 'ID invalido']);
        exit;
    }

    // SINGLESELECT() ejecuta: SELECT * FROM `venta` WHERE `id_venta` = $id_venta
    $r_venta = $SQL->SINGLESELECT('venta', 'id_venta', $id_venta);
    // fetch_assoc() obtiene la fila como arreglo asociativo [columna => valor]
    $venta   = $r_venta->fetch_assoc();

    if (!$venta) {
        echo json_encode(['error' => 'Venta no encontrada']);
        exit;
    }

    // Obtener todas las filas del detalle que corresponden a esta venta
    $r_detalle = $SQL->SINGLESELECT('detalle_venta', 'id_venta', $id_venta);
    $items     = [];

    while ($row = $r_detalle->fetch_assoc()) {
        // Para cada detalle, buscar el nombre del producto por su ID
        $r_prod = $SQL->SINGLESELECT('producto', 'id_producto', $row['id_producto']);
        $prod   = $r_prod->fetch_assoc();

        // Si el producto fue eliminado, mostrar '(eliminado)' en su lugar
        $items[] = [
            'nombre'   => $prod ? $prod['nombre'] : '(eliminado)',
            'cantidad' => $row['cantidad'],
            'subtotal' => $row['subtotal'],
        ];
    }

    // Retornar la cabecera de la venta y sus items como JSON
    echo json_encode(['venta' => $venta, 'items' => $items]);
    exit;
}

// ── Eliminar venta ────────────────────────────────────────────────────────────
$mensaje = null;
$error   = null;

// Se verifica tanto el metodo POST como que la accion sea 'eliminar'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {

    $id_venta = (int) ($_POST['id_venta'] ?? 0);

    if ($id_venta <= 0) {
        $error = "ID de venta invalido.";
    } else {
        try {
            // Se eliminan primero los detalles porque tienen FK hacia venta
            // Si se borrara la venta primero, la DB lanzaria un error de integridad referencial
            $SQL->DELETE('detalle_venta', 'id_venta', $id_venta);
            $SQL->DELETE('venta',         'id_venta', $id_venta);
            $mensaje = "Venta #$id_venta eliminada correctamente.";
        } catch (Exception $e) {
            // error_log() escribe el error en el log del servidor sin mostrarlo al usuario
            error_log($e->getMessage());
            // htmlspecialchars() escapa caracteres especiales para prevenir XSS
            $error = "Error al eliminar: " . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Cargar todas las ventas ───────────────────────────────────────────────────
// GETSALES() ejecuta: SELECT * FROM venta ORDER BY fecha DESC
// Retorna un objeto mysqli_result con todas las ventas ordenadas de la mas reciente
$sales = $SQL->GETSALES();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Facturacion - Panes Bea</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos del modal de detalle de venta */
        .modal-meta {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 1rem;
        }

        .modal-total {
            margin-top: 1rem;
            text-align: right;
            font-weight: 700;
            font-size: 15px;
            color: #1e293b;
        }

        /* Boton de ver detalle; estilo similar a btn-edit */
        .btn-detail {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }

        .btn-detail:hover {
            background: #e2e8f0;
        }
    </style>
</head>

<body>
    <main>
        <!-- Sidebar de navegacion; inicia oculto con la clase "hidden" -->
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

        <div class="right" id="content">
            <div class="topnav">
                <button id="toggleBtn">&#9776;</button>
                <section>
                    <h1>PanesBea</h1>
                    <img src="ppp.png" style="width: 3rem;" alt="">
                </section>
            </div>

            <div class="content">
                <h3 class="tittl1">Historial de Facturacion</h3>

                <div class="boxfax">

                    <?php if ($mensaje): ?>
                        <!-- htmlspecialchars() escapa el mensaje para prevenir XSS -->
                        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="tabletemplate-full">
                        <table>
                            <thead>
                                <tr>
                                    <th># Venta</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // fetch_assoc() itera sobre cada venta del resultado de GETSALES()
                                while ($row = $sales->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?= $row['id_venta'] ?></td>
                                        <td><?= $row['fecha'] ?></td>
                                        <!-- number_format() formatea el total con 2 decimales -->
                                        <td>$<?= number_format($row['total'], 2) ?></td>
                                        <td>
                                            <div class="acciones">
                                                <!-- Se pasan id, fecha y total como argumentos al JS
                                                     para mostrarlos en el modal sin otra peticion -->
                                                <button
                                                    class="btn btn-detail"
                                                    onclick="verDetalle(<?= $row['id_venta'] ?>, '<?= $row['fecha'] ?>', '<?= $row['total'] ?>')">
                                                    Ver detalle
                                                </button>
                                                <button
                                                    class="btn btn-delete"
                                                    onclick="confirmarEliminar(<?= $row['id_venta'] ?>)">
                                                    Eliminar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- ── Modal detalle de venta ── -->
    <div class="modal-overlay" id="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <!-- id="modal-titulo" permite que JS actualice el titulo con el numero de venta -->
                <h2 id="modal-titulo">Detalle de Venta</h2>
                <button class="modal-close" onclick="cerrarModal()">&#10005;</button>
            </div>
            <!-- id="modal-meta" muestra la fecha de la venta -->
            <p class="modal-meta" id="modal-meta"></p>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <!-- id="modal-body" es donde JS inserta las filas del detalle dinamicamente -->
                <tbody id="modal-body"></tbody>
            </table>
            <!-- id="modal-total" muestra el total de la venta al pie del modal -->
            <div class="modal-total" id="modal-total"></div>
        </div>
    </div>

    <!-- ── Dialogo de confirmacion de eliminacion ── -->
    <div class="confirm-overlay" id="confirm-overlay">
        <div class="confirm-box">
            <p>Seguro que deseas eliminar la <span id="confirm-label"></span>?<br>Esta accion no se puede deshacer.</p>
            <div class="confirm-actions">
                <button class="btn-confirm-cancel" onclick="cerrarConfirm()">Cancelar</button>
                <button class="btn-confirm-delete" onclick="ejecutarEliminar()">Si, eliminar</button>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para enviar la eliminacion via POST
         HTML nativo no soporta metodo DELETE, por eso se simula con POST + campo action -->
    <form id="form-eliminar" method="POST" action="" style="display:none;">
        <input type="hidden" name="action" value="eliminar">
        <!-- id="input-id-venta" recibe el ID de la venta a eliminar desde JS antes del submit -->
        <input type="hidden" name="id_venta" id="input-id-venta">
    </form>

</body>

<script>
    // ── Toggle del sidebar ────────────────────────────────────────────────────
    // DOMContentLoaded garantiza que todos los elementos del DOM ya existen
    document.addEventListener("DOMContentLoaded", () => {
        const btn = document.getElementById("toggleBtn");
        const sidebar = document.getElementById("sidebar");
        const content = document.getElementById("content");

        btn.addEventListener("click", () => {
            // classList.toggle() agrega la clase si no existe, la elimina si ya esta
            sidebar.classList.toggle("hidden");
            content.classList.toggle("full");
        });
    });

    // ── Modal detalle ─────────────────────────────────────────────────────────

    // Abre el modal y carga el detalle de la venta mediante fetch al endpoint JSON
    // Los parametros id, fecha y total vienen del onclick generado por PHP
    function verDetalle(id, fecha, total) {

        // Actualizar el titulo y la fecha del modal antes de abrir
        // textContent asigna texto plano de forma segura (sin parsear HTML)
        document.getElementById('modal-titulo').textContent = `Venta #${id}`;
        document.getElementById('modal-meta').textContent = `Fecha: ${fecha}`;

        // Mostrar indicador de carga mientras llega la respuesta del servidor
        // innerHTML permite insertar HTML directamente como string
        document.getElementById('modal-body').innerHTML = '<tr><td colspan="3" style="color:#94a3b8;padding:16px">Cargando...</td></tr>';
        document.getElementById('modal-total').textContent = '';

        // classList.add() agrega la clase 'active' para mostrar el modal mediante CSS
        document.getElementById('modal-overlay').classList.add('active');

        // fetch() realiza una peticion GET asincrona al endpoint del mismo archivo
        // Se pasa el id como parametro de URL para que PHP lo reciba en $_GET['id']
        fetch(`?action=get_detalle&id=${id}`)
            // res.json() parsea la respuesta JSON a un objeto JavaScript
            .then(res => res.json())
            .then(data => {
                // Si el servidor retorno un error, mostrarlo en el modal y salir
                if (data.error) {
                    document.getElementById('modal-body').innerHTML =
                        `<tr><td colspan="3" style="color:#dc2626">${data.error}</td></tr>`;
                    return;
                }

                // Construir las filas del detalle como string HTML
                // Los template literals (``) permiten interpolacion de variables con ${}
                let rows = '';
                // forEach() itera sobre cada item del arreglo retornado por el servidor
                data.items.forEach(item => {
                    rows += `<tr>
                        <td>${item.nombre}</td>
                        <td>${item.cantidad}</td>
                        <td>$${parseFloat(item.subtotal).toFixed(2)}</td>
                    </tr>`;
                });

                // Insertar las filas en el tbody del modal; si no hay items mostrar mensaje
                document.getElementById('modal-body').innerHTML = rows || '<tr><td colspan="3" style="color:#94a3b8">Sin productos</td></tr>';
                // parseFloat() convierte el total de string a decimal para formatearlo con toFixed()
                document.getElementById('modal-total').textContent = `Total: $${parseFloat(data.venta.total).toFixed(2)}`;
            })
            // .catch() captura errores de red o de parseo
            .catch(() => {
                document.getElementById('modal-body').innerHTML =
                    '<tr><td colspan="3" style="color:#dc2626">Error al cargar el detalle.</td></tr>';
            });
    }

    // Cierra el modal de detalle removiendo la clase 'active'
    function cerrarModal() {
        document.getElementById('modal-overlay').classList.remove('active');
    }

    // Cierra el modal si el usuario hace click en el fondo oscuro (fuera del contenido)
    document.getElementById('modal-overlay').addEventListener('click', function(e) {
        // e.target es el elemento exacto donde se hizo click
        // 'this' es el overlay; si coinciden el click fue en el fondo y no en el modal
        if (e.target === this) cerrarModal();
    });

    // ── Confirmacion de eliminacion ───────────────────────────────────────────

    // Variable global para recordar que venta se va a eliminar mientras el dialogo esta abierto
    let idParaEliminar = null;

    // Abre el dialogo de confirmacion con el ID de la venta a eliminar
    function confirmarEliminar(id) {
        idParaEliminar = id;
        // textContent actualiza el span con el numero de venta de forma segura
        document.getElementById('confirm-label').textContent = `Venta #${id}`;
        document.getElementById('confirm-overlay').classList.add('active');
    }

    // Cierra el dialogo y limpia el ID guardado
    function cerrarConfirm() {
        idParaEliminar = null;
        document.getElementById('confirm-overlay').classList.remove('active');
    }

    // Asigna el ID al formulario oculto y lo envia via POST para eliminar la venta
    function ejecutarEliminar() {
        if (!idParaEliminar) return;
        // Asignar el ID al input hidden antes de enviar el formulario
        document.getElementById('input-id-venta').value = idParaEliminar;
        // .submit() envia el formulario sin necesidad de un boton submit
        document.getElementById('form-eliminar').submit();
    }

    // Cerrar el dialogo de confirmacion si se hace click fuera del cuadro
    document.getElementById('confirm-overlay').addEventListener('click', function(e) {
        if (e.target === this) cerrarConfirm();
    });
</script>

</html>