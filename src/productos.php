<?php
// Verifica que el usuario tenga sesion activa; si no, redirige al login
require_once 'auth.php';

// Carga la clase mySQLConexion con todos sus metodos CRUD
require_once 'mySQLi.php';

// Instancia la conexion a la base de datos
$SQL = new mySQLConexion();

$mensaje = null;
$error   = null;

// Solo se procesa logica de escritura si el formulario fue enviado por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ?? '' retorna el valor de $_POST['action'] o '' si no existe
    $action = $_POST['action'] ?? '';

    // ── Agregar producto ──────────────────────────────────────────────────────
    if ($action === 'agregar_producto') {

        // trim() elimina espacios en blanco al inicio y al final del valor recibido
        $nombre = trim($_POST['nombre'] ?? '');
        // (float) convierte el valor a decimal para manejar precios con centavos
        $precio = (float) ($_POST['precio'] ?? 0);

        if ($nombre === '' || $precio <= 0) {
            $error = "El nombre y un precio mayor a 0 son obligatorios.";
        } else {
            try {
                // number_format() formatea el precio con exactamente 2 decimales
                // El tercer argumento '.' define el separador decimal
                // El cuarto argumento '' elimina el separador de miles
                // Esto garantiza que MySQL reciba el formato correcto para DECIMAL(5,2)
                $SQL->INSERT(
                    'producto',
                    ['nombre', 'precio'],
                    [$nombre, number_format($precio, 2, '.', '')]
                );
                $mensaje = "Producto \"$nombre\" agregado correctamente.";
            } catch (Exception $e) {
                // error_log() escribe el error en el log del servidor sin mostrarlo al usuario
                error_log($e->getMessage());
                // htmlspecialchars() escapa caracteres especiales para prevenir XSS
                $error = "Error al agregar: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Editar producto ───────────────────────────────────────────────────────
    elseif ($action === 'editar_producto') {

        // (int) convierte a entero para evitar inyecciones con valores no numericos
        $id     = (int)   ($_POST['id_producto'] ?? 0);
        $nombre = trim($_POST['nombre']          ?? '');
        $precio = (float) ($_POST['precio']      ?? 0);

        if ($id <= 0 || $nombre === '' || $precio <= 0) {
            $error = "Todos los campos son obligatorios y el precio debe ser mayor a 0.";
        } else {
            try {
                // UPDATE() ejecuta: UPDATE `producto` SET nombre=?, precio=? WHERE id_producto = $id
                $SQL->UPDATE(
                    'producto',
                    "id_producto = $id",
                    ['nombre', 'precio'],
                    [$nombre, number_format($precio, 2, '.', '')]
                );
                $mensaje = "Producto actualizado correctamente.";
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = "Error al editar: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Eliminar producto ─────────────────────────────────────────────────────
    elseif ($action === 'eliminar_producto') {

        $id = (int) ($_POST['id_producto'] ?? 0);

        if ($id <= 0) {
            $error = "ID de producto invalido.";
        } else {
            try {
                // Verificar si el producto esta referenciado en algun detalle de venta
                // SINGLESELECT() ejecuta: SELECT * FROM `detalle_venta` WHERE `id_producto` = $id
                $r_uso = $SQL->SINGLESELECT('detalle_venta', 'id_producto', $id);

                // num_rows retorna la cantidad de filas en el resultado de la consulta
                // Si es mayor a 0, el producto ya fue usado en ventas y no se puede eliminar
                if ($r_uso->num_rows > 0) {
                    $error = "No se puede eliminar este producto porque ya esta registrado en una o mas ventas.";
                } else {
                    // DELETE() ejecuta: DELETE FROM `producto` WHERE `id_producto` = $id
                    $SQL->DELETE('producto', 'id_producto', $id);
                    $mensaje = "Producto eliminado correctamente.";
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = "Error al eliminar: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// ── Cargar productos ──────────────────────────────────────────────────────────
// SELECT() ejecuta: SELECT * FROM `producto`
// Retorna un objeto mysqli_result con todos los productos
$productos = $SQL->SELECT('producto');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Panes Bea</title>
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
                    <img src="ppp.png" style="width:3rem;" alt="">
                </section>
            </div>

            <div class="content">
                <h3 class="tittl1">Productos</h3>

                <div class="boxfax">

                    <?php if ($mensaje): ?>
                        <!-- htmlspecialchars() escapa el mensaje para prevenir XSS -->
                        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="section-header">
                        <h2 class="table-title" style="margin:0;">Lista de productos</h2>
                        <button class="btn btn-primary" onclick="abrirAgregar()">+ Agregar producto</button>
                    </div>

                    <div class="tabletemplate-full">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Precio</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // fetch_assoc() obtiene cada fila como arreglo asociativo [columna => valor]
                                // El while itera hasta que no haya mas filas (retorna null)
                                while ($row = $productos->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?= $row['id_producto'] ?></td>
                                        <!-- htmlspecialchars() escapa el nombre para prevenir XSS -->
                                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                                        <!-- number_format() formatea el precio con 2 decimales -->
                                        <td>$<?= number_format($row['precio'], 2) ?></td>
                                        <td>
                                            <div class="acciones">
                                                <!-- Se pasan id, nombre y precio al JS como argumentos del onclick
                                                     ENT_QUOTES escapa comillas simples y dobles para no romper el atributo -->
                                                <button class="btn btn-edit"
                                                    onclick="abrirEditar(
                                                        <?= $row['id_producto'] ?>,
                                                        '<?= htmlspecialchars($row['nombre'], ENT_QUOTES) ?>',
                                                        '<?= $row['precio'] ?>'
                                                    )">Editar</button>
                                                <button class="btn btn-delete"
                                                    onclick="confirmarEliminar(<?= $row['id_producto'] ?>, '<?= htmlspecialchars($row['nombre'], ENT_QUOTES) ?>')">Eliminar</button>
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

    <!-- ── Modal agregar producto ── -->
    <div class="modal-overlay" id="modal-agregar">
        <div class="modal">
            <div class="modal-header">
                <h2>Agregar Producto</h2>
                <button class="modal-close" onclick="cerrarModal('modal-agregar')">&#10005;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="agregar_producto">
                <div class="field">
                    <label>Nombre</label>
                    <!-- autofocus coloca el cursor en este campo al abrir el modal -->
                    <input type="text" name="nombre" required autofocus>
                </div>
                <div class="field">
                    <label>Precio ($)</label>
                    <!-- step="0.01" permite ingresar decimales; min="0.01" impide precios en 0 o negativos -->
                    <input type="number" name="precio" step="0.01" min="0.01" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modal-agregar')">Cancelar</button>
                    <button type="submit" class="btn-save">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Modal editar producto ── -->
    <div class="modal-overlay" id="modal-editar">
        <div class="modal">
            <div class="modal-header">
                <h2>Editar Producto</h2>
                <button class="modal-close" onclick="cerrarModal('modal-editar')">&#10005;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="editar_producto">
                <!-- input hidden transporta el ID del producto a editar sin mostrarlo al usuario -->
                <input type="hidden" name="id_producto" id="edit-id">
                <div class="field">
                    <label>Nombre</label>
                    <!-- id="edit-nombre" permite que JS precargue el valor actual al abrir el modal -->
                    <input type="text" name="nombre" id="edit-nombre" required>
                </div>
                <div class="field">
                    <label>Precio ($)</label>
                    <input type="number" name="precio" id="edit-precio" step="0.01" min="0.01" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modal-editar')">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Dialogo de confirmacion de eliminacion ── -->
    <div class="confirm-overlay" id="confirm-overlay">
        <div class="confirm-box">
            <p>Eliminar <span id="confirm-label"></span>?<br>Esta accion no se puede deshacer.</p>
            <div class="confirm-actions">
                <button class="btn-confirm-cancel" onclick="cerrarConfirm()">Cancelar</button>
                <button class="btn-confirm-delete" onclick="ejecutarEliminar()">Si, eliminar</button>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para enviar la eliminacion via POST
         HTML nativo no soporta metodo DELETE, por eso se simula con POST + campo action -->
    <form id="form-del-producto" class="hidden-form" method="POST" action="">
        <input type="hidden" name="action" value="eliminar_producto">
        <!-- id="del-id-producto" recibe el ID desde JS justo antes del submit -->
        <input type="hidden" name="id_producto" id="del-id-producto">
    </form>

</body>

<script>
    // DOMContentLoaded garantiza que todos los elementos del DOM ya existen antes de manipularlos
    document.addEventListener("DOMContentLoaded", () => {
        // getElementById() retorna el elemento HTML con el id indicado
        const btn = document.getElementById("toggleBtn");
        const sidebar = document.getElementById("sidebar");
        const content = document.getElementById("content");

        // addEventListener() registra una funcion que se ejecuta al hacer click en el boton
        btn.addEventListener("click", () => {
            // classList.toggle() agrega la clase si no existe, la elimina si ya esta
            sidebar.classList.toggle("hidden");
            content.classList.toggle("full");
        });
    });

    // ── Modales ───────────────────────────────────────────────────────────────

    // Cierra el modal con el id recibido removiendo la clase 'active'
    function cerrarModal(id) {
        // classList.remove() elimina la clase indicada del elemento
        document.getElementById(id).classList.remove('active');
    }

    // querySelectorAll() retorna todos los elementos que coincidan con el selector CSS
    // Se itera sobre cada modal para cerrarlos si se hace click en el fondo oscuro
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            // e.target es el elemento donde se hizo click
            // 'this' es el overlay; si coinciden, el click fue en el fondo y no en el contenido
            if (e.target === this) this.classList.remove('active');
        });
    });

    // Abre el modal de agregar producto
    function abrirAgregar() {
        document.getElementById('modal-agregar').classList.add('active');
    }

    // Abre el modal de editar producto y precarga los campos con los datos actuales
    // Los parametros llegan desde los atributos onclick generados por PHP
    function abrirEditar(id, nombre, precio) {
        // .value asigna el valor al campo del formulario dentro del modal
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-nombre').value = nombre;
        document.getElementById('edit-precio').value = precio;
        document.getElementById('modal-editar').classList.add('active');
    }

    // ── Confirmacion de eliminacion ───────────────────────────────────────────

    // Variable global para recordar el ID del producto mientras el dialogo esta abierto
    let confirmId = null;

    // Abre el dialogo de confirmacion con el nombre del producto a eliminar
    function confirmarEliminar(id, nombre) {
        confirmId = id;
        // textContent asigna texto plano de forma segura (sin parsear HTML)
        document.getElementById('confirm-label').textContent = `"${nombre}"`;
        document.getElementById('confirm-overlay').classList.add('active');
    }

    // Cierra el dialogo y limpia el ID guardado
    function cerrarConfirm() {
        confirmId = null;
        document.getElementById('confirm-overlay').classList.remove('active');
    }

    // Asigna el ID al formulario oculto y lo envia via POST para eliminar el producto
    function ejecutarEliminar() {
        if (!confirmId) return;
        // Asignar el ID al input hidden antes de enviar el formulario
        document.getElementById('del-id-producto').value = confirmId;
        // .submit() envia el formulario sin necesidad de un boton submit
        document.getElementById('form-del-producto').submit();
    }

    // Cerrar el dialogo de confirmacion si se hace click fuera del cuadro
    document.getElementById('confirm-overlay').addEventListener('click', function(e) {
        if (e.target === this) cerrarConfirm();
    });
</script>

</html>