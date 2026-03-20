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
        // El operador ternario ?: retorna el primer valor si la condicion es true, si no el segundo
        $items[] = [
            'nombre'   => $prod ? $prod['nombre'] : '(eliminado)',
            'cantidad' => $row['cantidad'],
            'subtotal' => $row['subtotal'],
        ];
    }

    // Retornar la cabecera de la venta y sus items como un solo objeto JSON
    echo json_encode(['venta' => $venta, 'items' => $items]);
    // exit detiene el script para que no continue renderizando HTML
    exit;
}

// ── Eliminar venta ────────────────────────────────────────────────────────────
$mensaje = null;
$error   = null;

// Se verifica tanto el metodo POST como que la accion sea 'eliminar'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {

    // (int) convierte a entero para evitar inyecciones
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

// ── Filtros ───────────────────────────────────────────────────────────────────
// trim() limpia espacios; ?? '' retorna cadena vacia si el parametro no existe en $_GET
// Los filtros se leen de $_GET (no $_POST) para que queden visibles en la URL
// Esto permite que al recargar la pagina el filtro se mantenga activo
$filtro_tipo  = trim($_GET['filtro_tipo']  ?? ''); // 'dia', 'mes' o 'year'
$filtro_valor = trim($_GET['filtro_valor'] ?? ''); // el valor ingresado por el usuario

// ── Cargar ventas (con o sin filtro) ─────────────────────────────────────────
// !empty() retorna true si la variable tiene valor y no es cadena vacia
// Si ambos parametros tienen valor se aplica el filtro; si no se traen todas las ventas
if (!empty($filtro_tipo) && !empty($filtro_valor)) {
    // QUERYFILTRO() construye el WHERE correcto segun el tipo:
    // 'dia'  → WHERE DATE(fecha) = 'YYYY-MM-DD'
    // 'mes'  → WHERE DATE_FORMAT(fecha, '%Y-%m') = 'YYYY-MM'
    // 'year' → WHERE YEAR(fecha) = YYYY
    $result_sales = $SQL->QUERYFILTRO($filtro_tipo, $filtro_valor);
} else {
    // GETSALES() ejecuta: SELECT * FROM venta ORDER BY id_venta DESC
    $result_sales = $SQL->GETSALES();
}

// Convertir el objeto mysqli_result a un arreglo PHP
// Necesario para poder usar count() y foreach directamente en el HTML
$sales_arr = [];
while ($row = $result_sales->fetch_assoc()) {
    $sales_arr[] = $row;
}

// ── Anos disponibles para el selector de año ─────────────────────────────────
// GETYEARS() ejecuta: SELECT DISTINCT YEAR(fecha) as anio FROM venta ORDER BY anio DESC
// Solo retorna los anos que tienen al menos una venta registrada
$res_years = $SQL->GETYEARS();
$years_arr = [];
while ($y = $res_years->fetch_assoc()) {
    $years_arr[] = $y['anio'];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Facturacion - Panes Bea</title>
    <link rel="stylesheet" href="style.css">
    <style>
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

        .btn-detail {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }

        .btn-detail:hover {
            background: #e2e8f0;
        }

        /* Panel contenedor de los controles de filtro */
        .filtros {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 1.2rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
        }

        /* Cada grupo de label + control (select o input) */
        .filtro-grupo {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filtro-grupo label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .filtro-grupo select,
        .filtro-grupo input {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #1e293b;
            background: #f8fafc;
            outline: none;
            font-family: Arial, Helvetica, sans-serif;
            transition: border-color 0.2s;
            min-width: 140px;
        }

        .filtro-grupo select:focus,
        .filtro-grupo input:focus {
            border-color: #94a3b8;
            background: #fff;
        }

        /* Enlace para resetear el filtro y volver a ver todas las ventas */
        .btn-limpiar {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: Arial, Helvetica, sans-serif;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-limpiar:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        /* Etiqueta que muestra cuantos resultados encontro el filtro */
        .resultados-label {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .resultados-label span {
            font-weight: 700;
            color: #1e293b;
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

                    <!-- Formulario de filtros -->
                    <!-- method="GET" envia los valores como parametros en la URL
                         lo que permite que el filtro persista al recargar la pagina -->
                    <form method="GET" action="">
                        <div class="filtros">

                            <!-- Select: tipo de filtro -->
                            <!-- onchange dispara actualizarCampoFiltro() para mostrar
                                 el campo de valor correcto segun el tipo elegido -->
                            <div class="filtro-grupo">
                                <label>Filtrar por</label>
                                <select name="filtro_tipo" id="filtro_tipo" onchange="actualizarCampoFiltro()">
                                    <option value="">Sin filtro</option>
                                    <!-- El operador ternario imprime 'selected' si este tipo coincide
                                         con el filtro activo, para mantener la seleccion al recargar -->
                                    <option value="dia" <?= $filtro_tipo === 'dia'  ? 'selected' : '' ?>>Dia</option>
                                    <option value="mes" <?= $filtro_tipo === 'mes'  ? 'selected' : '' ?>>Mes</option>
                                    <option value="year" <?= $filtro_tipo === 'year' ? 'selected' : '' ?>>Año</option>
                                </select>
                            </div>

                            <!-- Campo para filtrar por dia exacto -->
                            <!-- type="date" muestra selector nativo del navegador y envia YYYY-MM-DD -->
                            <!-- No tiene name porque el valor se copia al input hidden antes del submit -->
                            <!-- style="display:none" lo oculta; JS lo muestra segun el tipo elegido -->
                            <div class="filtro-grupo" id="campo-dia" style="display:none;">
                                <label>Fecha</label>
                                <input type="date" id="input-dia"
                                    value="<?= ($filtro_tipo === 'dia') ? htmlspecialchars($filtro_valor) : '' ?>">
                            </div>

                            <!-- Campo para filtrar por mes -->
                            <!-- type="month" muestra selector de mes+año y envia YYYY-MM -->
                            <div class="filtro-grupo" id="campo-mes" style="display:none;">
                                <label>Mes</label>
                                <input type="month" id="input-mes"
                                    value="<?= ($filtro_tipo === 'mes') ? htmlspecialchars($filtro_valor) : '' ?>">
                            </div>

                            <!-- Campo para filtrar por año -->
                            <!-- Solo lista los anos que tienen ventas (obtenidos con GETYEARS()) -->
                            <!-- == compara sin distinguir tipo para que '2026' == 2026 funcione -->
                            <div class="filtro-grupo" id="campo-year" style="display:none;">
                                <label>Año</label>
                                <select id="input-year">
                                    <option value="">Selecciona</option>
                                    <?php foreach ($years_arr as $y): ?>
                                        <option value="<?= $y ?>" <?= ($filtro_tipo === 'year' && $filtro_valor == $y) ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Input hidden: unico campo con name="filtro_valor"
                                 JS lo llena con el valor del campo visible antes del submit
                                 Resuelve el problema de multiples campos con el mismo name:
                                 si los 3 campos visibles tuvieran name="filtro_valor", el navegador
                                 enviaria el primero del DOM aunque estuviera oculto y sin valor,
                                 haciendo que PHP recibiera cadena vacia y no aplicara el filtro -->
                            <input type="hidden" name="filtro_valor" id="filtro_valor_hidden">

                            <!-- onclick llama a prepararFiltro() para copiar el valor al hidden
                                 antes de que el formulario se envie -->
                            <div class="filtro-grupo">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary" onclick="prepararFiltro()">Filtrar</button>
                            </div>

                            <!-- Solo aparece cuando hay un filtro activo
                                 Al hacer click redirige sin parametros GET, mostrando todas las ventas -->
                            <?php if (!empty($filtro_tipo)): ?>
                                <div class="filtro-grupo">
                                    <label>&nbsp;</label>
                                    <a href="editfax.php" class="btn-limpiar">Limpiar filtro</a>
                                </div>
                            <?php endif; ?>

                        </div>
                    </form>

                    <!-- Contador de resultados -->
                    <!-- count() retorna la cantidad de elementos del arreglo $sales_arr -->
                    <p class="resultados-label">
                        <?php if (!empty($filtro_tipo)): ?>
                            Se encontraron <span><?= count($sales_arr) ?></span> ventas con el filtro aplicado.
                        <?php else: ?>
                            Mostrando <span>todas</span> las ventas.
                        <?php endif; ?>
                    </p>

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
                                <?php if (empty($sales_arr)): ?>
                                    <!-- empty() retorna true si el arreglo no tiene elementos -->
                                    <tr>
                                        <td colspan="4" style="text-align:center; color:#94a3b8; padding:2rem;">
                                            No se encontraron ventas con ese filtro.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    // foreach itera sobre el arreglo de ventas ya filtrado y convertido
                                    foreach ($sales_arr as $row): ?>
                                        <tr>
                                            <td>#<?= $row['id_venta'] ?></td>
                                            <td><?= $row['fecha'] ?></td>
                                            <!-- number_format() formatea el total con 2 decimales -->
                                            <td>$<?= number_format($row['total'], 2) ?></td>
                                            <td>
                                                <div class="acciones">
                                                    <!-- Se pasan id, fecha y total al JS como argumentos
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
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
            <!-- id="modal-meta" muestra la fecha de la venta seleccionada -->
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
        <!-- id="input-id-venta" recibe el ID desde JS justo antes del submit -->
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

        // Restaurar el campo visible si hay un filtro activo en la URL al cargar la pagina
        actualizarCampoFiltro();
    });

    // ── Filtros ───────────────────────────────────────────────────────────────

    // Copia el valor del campo visible al input hidden antes de enviar el formulario
    // Problema que resuelve: si los 3 campos (dia, mes, year) tuvieran name="filtro_valor",
    // el navegador enviaria el primero del DOM aunque estuviera oculto y sin valor,
    // haciendo que PHP recibiera cadena vacia y no aplicara ningun filtro
    function prepararFiltro() {
        const tipo = document.getElementById('filtro_tipo').value;
        let valor = '';

        // Leer el valor del campo visible segun el tipo seleccionado
        if (tipo === 'dia') valor = document.getElementById('input-dia').value;
        if (tipo === 'mes') valor = document.getElementById('input-mes').value;
        if (tipo === 'year') valor = document.getElementById('input-year').value;

        // .value asigna el valor leido al input hidden que si tiene name y sera enviado
        document.getElementById('filtro_valor_hidden').value = valor;
    }

    // Muestra u oculta el campo de valor correcto segun el tipo de filtro seleccionado
    // Se llama al cambiar el select (onchange) y al cargar la pagina (DOMContentLoaded)
    function actualizarCampoFiltro() {
        const tipo = document.getElementById('filtro_tipo').value;

        // Ocultar todos los campos de valor
        // style.display = 'none' esconde el elemento sin eliminarlo del DOM
        document.getElementById('campo-dia').style.display = 'none';
        document.getElementById('campo-mes').style.display = 'none';
        document.getElementById('campo-year').style.display = 'none';

        // Mostrar unicamente el campo que corresponde al tipo seleccionado
        // style.display = 'block' hace el elemento visible
        if (tipo === 'dia') document.getElementById('campo-dia').style.display = 'block';
        if (tipo === 'mes') document.getElementById('campo-mes').style.display = 'block';
        if (tipo === 'year') document.getElementById('campo-year').style.display = 'block';
    }

    // ── Modal detalle ─────────────────────────────────────────────────────────

    // Abre el modal y carga el detalle de la venta mediante fetch al endpoint JSON
    // Los parametros id, fecha y total vienen del onclick generado por PHP
    function verDetalle(id, fecha, total) {

        // textContent asigna texto plano de forma segura (sin parsear HTML)
        document.getElementById('modal-titulo').textContent = `Venta #${id}`;
        document.getElementById('modal-meta').textContent = `Fecha: ${fecha}`;

        // innerHTML permite insertar HTML como string; aqui muestra un indicador de carga
        document.getElementById('modal-body').innerHTML = '<tr><td colspan="3" style="color:#94a3b8;padding:16px">Cargando...</td></tr>';
        document.getElementById('modal-total').textContent = '';

        // classList.add() agrega la clase 'active' para mostrar el modal via CSS
        document.getElementById('modal-overlay').classList.add('active');

        // fetch() realiza una peticion GET asincrona al endpoint del mismo archivo
        // Se pasa el id como parametro de URL para que PHP lo reciba en $_GET['id']
        fetch(`?action=get_detalle&id=${id}`)
            // res.json() parsea la respuesta JSON a un objeto JavaScript
            .then(res => res.json())
            .then(data => {
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

                // El operador || retorna el lado derecho si el izquierdo es falsy (vacio)
                document.getElementById('modal-body').innerHTML = rows || '<tr><td colspan="3" style="color:#94a3b8">Sin productos</td></tr>';
                // parseFloat() convierte el total de string a decimal para formatearlo con toFixed()
                document.getElementById('modal-total').textContent = `Total: $${parseFloat(data.venta.total).toFixed(2)}`;
            })
            // .catch() captura errores de red o de parseo JSON
            .catch(() => {
                document.getElementById('modal-body').innerHTML =
                    '<tr><td colspan="3" style="color:#dc2626">Error al cargar el detalle.</td></tr>';
            });
    }

    // Cierra el modal removiendo la clase 'active'
    function cerrarModal() {
        document.getElementById('modal-overlay').classList.remove('active');
    }

    // Cierra el modal si el usuario hace click en el fondo oscuro
    document.getElementById('modal-overlay').addEventListener('click', function(e) {
        // e.target es el elemento exacto donde se hizo click
        // 'this' es el overlay; si coinciden el click fue en el fondo y no en el modal
        if (e.target === this) cerrarModal();
    });

    // ── Confirmacion de eliminacion ───────────────────────────────────────────

    // Variable global para recordar que venta se va a eliminar
    let idParaEliminar = null;

    // Abre el dialogo con el numero de la venta a eliminar
    function confirmarEliminar(id) {
        idParaEliminar = id;
        // textContent actualiza el span de forma segura sin parsear HTML
        // Los template literals permiten interpolacion de variables con ${}
        document.getElementById('confirm-label').textContent = `Venta #${id}`;
        document.getElementById('confirm-overlay').classList.add('active');
    }

    // Cierra el dialogo y limpia el ID guardado
    function cerrarConfirm() {
        idParaEliminar = null;
        document.getElementById('confirm-overlay').classList.remove('active');
    }

    // Asigna el ID al formulario oculto y lo envia via POST
    function ejecutarEliminar() {
        if (!idParaEliminar) return;
        // Asignar el ID al input hidden antes de enviar el formulario
        document.getElementById('input-id-venta').value = idParaEliminar;
        // .submit() envia el formulario sin necesidad de un boton submit
        document.getElementById('form-eliminar').submit();
    }

    // Cerrar el dialogo si se hace click fuera del cuadro
    document.getElementById('confirm-overlay').addEventListener('click', function(e) {
        if (e.target === this) cerrarConfirm();
    });
</script>

</html>