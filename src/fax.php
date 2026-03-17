<?php
// Verifica que el usuario tenga sesion activa; si no, redirige al login
require_once 'auth.php';

// Carga la clase mySQLConexion con todos sus metodos CRUD
require_once "mySQLi.php";

// Instancia la conexion a la base de datos
$SQL = new mySQLConexion();

// ── Endpoint JSON para productos ──────────────────────────────────────────────
// isset() verifica si $_GET['action'] existe antes de leerlo
// Este bloque actua como un mini-endpoint: cuando el JS hace fetch('?action=get_productos')
// el mismo archivo responde con JSON en lugar de renderizar HTML
if (isset($_GET['action']) && $_GET['action'] === 'get_productos') {
    // header() envia una cabecera HTTP; 'Content-Type: application/json' le indica
    // al navegador que la respuesta es JSON y no HTML
    header('Content-Type: application/json');

    // SELECT() ejecuta: SELECT * FROM `producto`
    // Retorna un objeto mysqli_result con todos los productos
    $result    = $SQL->SELECT('producto');
    $productos = [];

    // fetch_assoc() obtiene cada fila como arreglo asociativo [columna => valor]
    // El while itera hasta que no haya mas filas (retorna null)
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }

    // json_encode() convierte el arreglo PHP a una cadena JSON
    // que el JS podra parsear con res.json()
    echo json_encode($productos);

    // exit detiene el script para que no continue renderizando HTML
    exit;
}

// ── Procesar POST: guardar venta ──────────────────────────────────────────────
$mensaje = null;
$error   = null;

// Solo se procesa logica de escritura si el formulario fue enviado por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ?? [] retorna el valor del array o un array vacio si no existe en $_POST
    $productos_ids = $_POST['producto_id'] ?? [];
    $cantidades    = $_POST['cantidad']    ?? [];

    // empty() retorna true si el arreglo esta vacio o no tiene elementos
    if (empty($productos_ids)) {
        $error = "Debes agregar al menos un producto.";

        // count() retorna la cantidad de elementos de un arreglo
        // Se verifica que haya la misma cantidad de productos y cantidades
    } elseif (count($productos_ids) !== count($cantidades)) {
        $error = "Los datos de productos y cantidades no coinciden.";
    } else {
        try {
            $items = [];
            $total = 0.0;

            // foreach itera sobre cada producto_id enviado desde el formulario
            // $index es la posicion actual, usada para obtener la cantidad correspondiente
            foreach ($productos_ids as $index => $id_producto) {

                // (int) convierte a entero para evitar inyecciones con valores no numericos
                $id_producto = (int) $id_producto;
                $cantidad    = (int) $cantidades[$index];

                if ($id_producto <= 0 || $cantidad <= 0) {
                    // throw lanza una excepcion que interrumpe el flujo y salta al catch
                    throw new Exception("Producto o cantidad invalidos en la fila " . ($index + 1));
                }

                // SINGLESELECT() ejecuta: SELECT * FROM `producto` WHERE `id_producto` = $id
                $result   = $SQL->SINGLESELECT('producto', 'id_producto', $id_producto);
                $producto = $result->fetch_assoc();

                if (!$producto) {
                    throw new Exception("El producto con ID $id_producto no existe.");
                }

                // round() redondea el resultado a 2 decimales para evitar errores de punto flotante
                $subtotal = round($producto['precio'] * $cantidad, 2);
                $total   += $subtotal;

                // Guardar los datos de esta fila para insertarlos despues de crear la venta
                $items[] = [
                    'id_producto' => $id_producto,
                    'cantidad'    => $cantidad,
                    'subtotal'    => $subtotal,
                ];
            }

            $total = round($total, 2);

            // date() retorna la fecha actual en el formato indicado
            // 'Y-m-d' produce el formato requerido por MySQL para columnas DATE
            $fecha = date('Y-m-d');

            // Insertar el registro principal de la venta con su fecha y total
            $SQL->INSERT('venta', ['fecha', 'total'], [$fecha, $total]);

            // GETSALES() ejecuta: SELECT * FROM venta ORDER BY fecha DESC
            // Se obtiene la primera fila (la mas reciente) para conseguir el id_venta recien insertado
            $result_venta = $SQL->GETSALES();
            $ultima_venta = $result_venta->fetch_assoc();
            $id_venta     = $ultima_venta['id_venta'];

            // Insertar cada fila del detalle de venta con el id_venta obtenido
            foreach ($items as $item) {
                $SQL->INSERT(
                    'detalle_venta',
                    ['id_venta', 'id_producto', 'cantidad', 'subtotal'],
                    [$id_venta, $item['id_producto'], $item['cantidad'], $item['subtotal']]
                );
            }

            // number_format() formatea el total con 2 decimales para mostrarlo al usuario
            $mensaje = "Venta registrada correctamente. Total: $" . number_format($total, 2);
        } catch (Exception $e) {
            // error_log() escribe el error en el log del servidor sin mostrarlo al usuario
            error_log($e->getMessage());
            // htmlspecialchars() escapa caracteres especiales para prevenir XSS
            $error = "Error al registrar la venta: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturar - Panes Bea</title>
    <link rel="stylesheet" href="style.css">
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
                <h3 class="tittl1">Registrar Venta</h3>

                <div class="boxfax">

                    <?php if ($mensaje): ?>
                        <!-- htmlspecialchars() escapa el mensaje antes de imprimirlo para prevenir XSS -->
                        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form action="" method="POST" id="form-venta">

                        <h2 class="table-title">Productos</h2>

                        <div class="tabletemplate">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Precio unitario</th>
                                        <th>Cantidad</th>
                                        <th>Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <!-- tbody vacio; las filas se generan dinamicamente con JS -->
                                <tbody id="cuerpo-detalle"></tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="3" style="text-align:right;">TOTAL</td>
                                        <!-- id="total-display" permite que JS actualice el total en tiempo real -->
                                        <td id="total-display">$0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="btn-row" style="margin-top: 1rem;">
                            <button type="button" class="btn btn-outline" onclick="agregarFila()">+ Agregar producto</button>
                            <button type="submit" class="btn btn-primary">Registrar Venta</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </main>
</body>

<script>
    // ── Toggle del sidebar ────────────────────────────────────────────────────
    // DOMContentLoaded garantiza que el DOM ya cargo antes de buscar elementos
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

    // ── Carga de productos ────────────────────────────────────────────────────

    // Arreglo global donde se almacenan los productos recibidos del servidor
    let productos = [];

    // fetch() realiza una peticion HTTP asincrona al servidor
    // Al llamar al mismo archivo con ?action=get_productos, el PHP responde con JSON
    fetch('?action=get_productos')
        // .then() encadena la respuesta; res.json() parsea el cuerpo JSON a un objeto JS
        .then(res => res.json())
        .then(data => {
            productos = data;
            // Una vez cargados los productos, agregar la primera fila automaticamente
            agregarFila();
        })
        // .catch() captura cualquier error de red o de parseo
        .catch(() => alert('Error al cargar los productos.'));

    // Construye y retorna un elemento <select> con todos los productos disponibles
    function buildSelect() {
        // createElement() crea un nuevo elemento HTML en memoria (sin insertarlo aun al DOM)
        const select = document.createElement('select');
        select.name = 'producto_id[]'; // [] permite enviar multiples valores con el mismo nombre
        select.required = true;
        select.onchange = function() {
            actualizarFila(this); // 'this' referencia al select que disparo el evento
            recalcularTotal();
        };

        // Opcion por defecto deshabilitada para forzar una seleccion valida
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.text = 'Selecciona';
        defaultOpt.disabled = true;
        defaultOpt.selected = true;
        // appendChild() inserta el elemento como ultimo hijo del nodo padre
        select.appendChild(defaultOpt);

        // forEach() itera sobre cada producto del arreglo global
        productos.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id_producto;
            opt.text = p.nombre;
            // dataset.precio almacena el precio como atributo data-precio del elemento
            // Permite acceder al precio sin hacer otra peticion al servidor
            opt.dataset.precio = p.precio;
            select.appendChild(opt);
        });

        return select;
    }

    // Agrega una nueva fila de producto a la tabla del formulario
    function agregarFila() {
        if (productos.length === 0) {
            alert('Espera a que carguen los productos.');
            return;
        }

        // getElementById() obtiene el tbody donde se insertan las filas
        const tbody = document.getElementById('cuerpo-detalle');

        // Crear la fila y sus celdas como elementos del DOM
        const fila = document.createElement('tr');
        const tdProducto = document.createElement('td');
        const tdPrecio = document.createElement('td');
        const tdCantidad = document.createElement('td');
        const tdSubtotal = document.createElement('td');
        const tdAccion = document.createElement('td');

        // Celda de producto: contiene el select generado por buildSelect()
        tdProducto.appendChild(buildSelect());

        // Celda de precio unitario: solo lectura, se actualiza al cambiar el select
        const inputPrecio = document.createElement('input');
        inputPrecio.type = 'text';
        inputPrecio.readOnly = true; // readOnly impide edicion manual por el usuario
        inputPrecio.value = '0.00';
        inputPrecio.className = 'precio-unitario';
        tdPrecio.appendChild(inputPrecio);

        // Celda de cantidad: el usuario ingresa cuantas unidades quiere
        const inputCantidad = document.createElement('input');
        inputCantidad.type = 'number';
        inputCantidad.name = 'cantidad[]'; // [] envia multiples valores al POST
        inputCantidad.min = '1';
        inputCantidad.value = '1';
        inputCantidad.required = true;
        // oninput se dispara cada vez que el usuario cambia el valor del campo
        inputCantidad.oninput = recalcularTotal;
        tdCantidad.appendChild(inputCantidad);

        // Celda de subtotal: solo lectura, calculado automaticamente
        const inputSubtotal = document.createElement('input');
        inputSubtotal.type = 'text';
        inputSubtotal.readOnly = true;
        inputSubtotal.value = '0.00';
        inputSubtotal.className = 'subtotal';
        tdSubtotal.appendChild(inputSubtotal);

        // Boton para eliminar la fila de la tabla
        const btnEliminar = document.createElement('button');
        btnEliminar.type = 'button';
        btnEliminar.textContent = 'X';
        btnEliminar.className = 'btn-danger';
        btnEliminar.onclick = function() {
            // fila.remove() elimina el elemento <tr> del DOM completamente
            fila.remove();
            recalcularTotal();
        };
        tdAccion.appendChild(btnEliminar);

        // append() inserta multiples hijos en orden de una sola vez
        fila.append(tdProducto, tdPrecio, tdCantidad, tdSubtotal, tdAccion);
        tbody.appendChild(fila);
    }

    // Actualiza el precio unitario y el subtotal de una fila al cambiar el select
    function actualizarFila(select) {
        // closest() sube por el DOM buscando el ancestro mas cercano que coincida con el selector
        const fila = select.closest('tr');
        // selectedIndex retorna el indice de la opcion actualmente seleccionada
        const opt = select.options[select.selectedIndex];
        // parseFloat() convierte el string del precio a numero decimal
        // || 0 evita NaN si el valor fuera invalido
        const precio = parseFloat(opt.dataset.precio) || 0;
        // parseInt() convierte el valor de la cantidad a entero
        const cantidad = parseInt(fila.querySelector('input[name="cantidad[]"]').value) || 1;

        // querySelector() busca el primer elemento que coincida con el selector dentro de fila
        fila.querySelector('.precio-unitario').value = precio.toFixed(2);
        // toFixed(2) redondea el numero y lo convierte a string con exactamente 2 decimales
        fila.querySelector('.subtotal').value = (precio * cantidad).toFixed(2);
    }

    // Recalcula todos los subtotales y el total general de la tabla
    function recalcularTotal() {
        let total = 0;

        // querySelectorAll() retorna todos los <tr> del tbody
        document.querySelectorAll('#cuerpo-detalle tr').forEach(fila => {
            const select = fila.querySelector('select');
            const opt = select ? select.options[select.selectedIndex] : null;
            const precio = opt ? (parseFloat(opt.dataset.precio) || 0) : 0;
            const cantidad = parseInt(fila.querySelector('input[name="cantidad[]"]').value) || 0;
            const subtotal = precio * cantidad;

            fila.querySelector('.subtotal').value = subtotal.toFixed(2);
            total += subtotal;
        });

        // textContent actualiza el texto del elemento que muestra el total
        // toFixed(2) asegura que siempre se muestren 2 decimales
        document.getElementById('total-display').textContent = '$' + total.toFixed(2);
    }

    // Validacion del formulario antes de enviarlo al servidor
    document.getElementById('form-venta').addEventListener('submit', function(e) {
        const filas = document.querySelectorAll('#cuerpo-detalle tr');

        if (filas.length === 0) {
            // e.preventDefault() cancela el envio del formulario
            e.preventDefault();
            alert('Debes agregar al menos un producto.');
            return;
        }

        let valido = true;
        filas.forEach(fila => {
            const select = fila.querySelector('select');
            const cantidad = fila.querySelector('input[name="cantidad[]"]');
            // Si algun select no tiene valor o la cantidad es menor a 1, la venta no es valida
            if (!select.value || parseInt(cantidad.value) < 1) valido = false;
        });

        if (!valido) {
            e.preventDefault();
            alert('Verifica que todos los productos tengan cantidad valida.');
        }
    });
</script>

</html>