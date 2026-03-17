<?php
// Verifica que el usuario tenga sesion activa; si no, redirige al login
require_once 'auth.php';

// Carga la clase mySQLConexion con todos sus metodos CRUD
require_once "mySQLi.php";

// Instancia la conexion a la base de datos
$SQL = new mySQLConexion();

$mensaje = null;
$error   = null;

// $_SERVER['REQUEST_METHOD'] contiene el metodo HTTP de la peticion (GET, POST, etc.)
// Solo se procesa logica de escritura si el formulario fue enviado por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ?? '' retorna el valor de $_POST['action'] o '' si no existe
    $action = $_POST['action'] ?? '';

    // ── Agregar ingrediente ───────────────────────────────────────────────────
    if ($action === 'agregar_ingrediente') {

        // trim() elimina espacios en blanco al inicio y al final del valor recibido
        $nombre        = trim($_POST['nombre']        ?? '');
        // (int) convierte el valor a entero; evita que se inserten letras en campos numericos
        $stock         = (int) ($_POST['stock']        ?? 0);
        $unidad_medida = trim($_POST['unidad_medida'] ?? '');

        if ($nombre === '' || $unidad_medida === '') {
            $error = "El nombre y la unidad de medida son obligatorios.";
        } else {
            try {
                // INSERT() ejecuta: INSERT INTO `ingrediente` (nombre, stock, unidad_medida) VALUES (...)
                $SQL->INSERT(
                    'ingrediente',
                    ['nombre', 'stock', 'unidad_medida'],
                    [$nombre, $stock, $unidad_medida]
                );
                $mensaje = "Ingrediente \"$nombre\" agregado correctamente.";
            } catch (Exception $e) {
                // error_log() escribe el error en el log del servidor sin mostrarlo al usuario
                error_log($e->getMessage());
                // getMessage() retorna el mensaje de texto de la excepcion capturada
                // htmlspecialchars() escapa caracteres especiales para prevenir XSS
                $error = "Error al agregar: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Editar ingrediente ────────────────────────────────────────────────────
    elseif ($action === 'editar_ingrediente') {

        $id            = (int) ($_POST['id_ingrediente'] ?? 0);
        $nombre        = trim($_POST['nombre']           ?? '');
        $stock         = (int) ($_POST['stock']          ?? 0);
        $unidad_medida = trim($_POST['unidad_medida']    ?? '');

        if ($id <= 0 || $nombre === '' || $unidad_medida === '') {
            $error = "Todos los campos del ingrediente son obligatorios.";
        } else {
            try {
                // UPDATE() ejecuta: UPDATE `ingrediente` SET nombre=?, stock=?, unidad_medida=? WHERE id_ingrediente = $id
                $SQL->UPDATE(
                    'ingrediente',
                    "id_ingrediente = $id",
                    ['nombre', 'stock', 'unidad_medida'],
                    [$nombre, $stock, $unidad_medida]
                );
                $mensaje = "Ingrediente actualizado correctamente.";
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = "Error al editar: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Eliminar ingrediente ──────────────────────────────────────────────────
    elseif ($action === 'eliminar_ingrediente') {

        $id = (int) ($_POST['id_ingrediente'] ?? 0);

        if ($id <= 0) {
            $error = "ID de ingrediente invalido.";
        } else {
            try {
                // Se eliminan primero los registros relacionados para respetar las FK
                // DELETE() ejecuta: DELETE FROM `tabla` WHERE `columna` = $id
                $SQL->DELETE('compra_ingrediente', 'id_ingrediente', $id);
                $SQL->DELETE('movimiento',         'id_ingrediente', $id);
                $SQL->DELETE('ingrediente',        'id_ingrediente', $id);
                $mensaje = "Ingrediente eliminado correctamente.";
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = "Error al eliminar: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Agregar compra ────────────────────────────────────────────────────────
    elseif ($action === 'agregar_compra') {

        $id_ingrediente = (int)   ($_POST['id_ingrediente'] ?? 0);
        $cantidad       = (int)   ($_POST['cantidad']       ?? 0);
        // (float) convierte el valor a numero decimal para manejar precios con centavos
        $costo          = (float) ($_POST['costo']          ?? 0);
        $fecha          = trim($_POST['fecha']              ?? '');

        if ($id_ingrediente <= 0 || $cantidad <= 0 || $costo <= 0 || $fecha === '') {
            $error = "Todos los campos de la compra son obligatorios.";
        } else {
            try {
                // Insertar el registro de compra en la tabla compra_ingrediente
                $SQL->INSERT(
                    'compra_ingrediente',
                    ['id_ingrediente', 'fecha', 'cantidad', 'costo'],
                    [$id_ingrediente, $fecha, $cantidad, $costo]
                );

                // SINGLESELECT() ejecuta: SELECT * FROM `ingrediente` WHERE `id_ingrediente` = $id
                // Retorna un objeto mysqli_result con la fila del ingrediente seleccionado
                $r_ing = $SQL->SINGLESELECT('ingrediente', 'id_ingrediente', $id_ingrediente);
                // fetch_assoc() obtiene la fila como arreglo asociativo [columna => valor]
                $ing   = $r_ing->fetch_assoc();

                // Calcular el nuevo stock sumando la cantidad comprada al stock actual
                // ?? 0 evita errores si 'stock' llegara a ser null
                $newStock = ($ing['stock'] ?? 0) + $cantidad;

                // Actualizar el stock del ingrediente con el nuevo valor calculado
                $SQL->UPDATE(
                    'ingrediente',
                    "id_ingrediente = $id_ingrediente",
                    ['stock'],
                    [$newStock]
                );

                // Registrar un movimiento de tipo 'entrada' por la compra realizada
                $SQL->INSERT(
                    'movimiento',
                    ['id_ingrediente', 'tipo', 'cantidad'],
                    [$id_ingrediente, 'entrada', $cantidad]
                );

                $mensaje = "Compra registrada y stock actualizado.";
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = "Error al registrar compra: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Eliminar compra ───────────────────────────────────────────────────────
    elseif ($action === 'eliminar_compra') {

        $id_compra = (int) ($_POST['id_compra'] ?? 0);

        if ($id_compra <= 0) {
            $error = "ID de compra invalido.";
        } else {
            try {
                // Eliminar unicamente el registro de compra; el stock no se revierte
                $SQL->DELETE('compra_ingrediente', 'id_compra', $id_compra);
                $mensaje = "Compra eliminada. El stock no fue modificado.";
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = "Error al eliminar compra: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// ── Cargar datos ──────────────────────────────────────────────────────────────

// SELECT() ejecuta: SELECT * FROM `ingrediente`
// Retorna un objeto mysqli_result con todos los ingredientes
$ingredientes = $SQL->SELECT('ingrediente');

// Obtener todas las compras y enriquecerlas con el nombre del ingrediente correspondiente
$r_compras = $SQL->SELECT('compra_ingrediente');
$compras   = [];
while ($c = $r_compras->fetch_assoc()) {
    // Para cada compra, buscar el ingrediente relacionado por su ID
    $r_ing = $SQL->SINGLESELECT('ingrediente', 'id_ingrediente', $c['id_ingrediente']);
    $ing   = $r_ing->fetch_assoc();
    // Si el ingrediente fue eliminado, mostrar '(eliminado)' en su lugar
    $c['nombre_ingrediente'] = $ing ? $ing['nombre'] : '(eliminado)';
    // Agregar la compra enriquecida al arreglo
    $compras[] = $c;
}

// usort() ordena el arreglo $compras usando una funcion de comparacion personalizada
// strcmp() compara dos strings alfabeticamente; al invertir $a y $b se ordena DESC
// fn($a, $b) es una funcion flecha (arrow function) de PHP 7.4+
usort($compras, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

// Cargar lista de ingredientes separada para los selects de los modales
// (los resultados anteriores ya fueron consumidos por fetch_assoc())
$r_ings_select = $SQL->SELECT('ingrediente');
$ings_select   = [];
while ($i = $r_ings_select->fetch_assoc()) {
    $ings_select[] = $i;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - Panes Bea</title>
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

            <div class="content" style="margin-bottom: 1rem;">
                <h3 class="tittl1">Inventario</h3>

                <div class="boxfax">

                    <?php if ($mensaje): ?>
                        <!-- htmlspecialchars() previene XSS al escapar el mensaje antes de imprimirlo -->
                        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <!-- ── Tabla ingredientes ── -->
                    <div class="inv-section" style="margin-top: 1rem;">
                        <div class="section-header">
                            <h2 class="table-title" style="margin:0;">Ingredientes</h2>
                            <button class="btn btn-primary" onclick="abrirAgregarIngrediente()">+ Agregar ingrediente</button>
                        </div>
                        <div class="tabletemplate-full">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Ingrediente</th>
                                        <th>Stock</th>
                                        <th>Unidad</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $ingredientes->fetch_assoc()): ?>
                                        <?php
                                        // (int) convierte el stock a entero para comparaciones numericas
                                        $s = (int) $row['stock'];

                                        // Determinar el badge de color segun el nivel de stock
                                        if ($s <= 0) {
                                            $badge = 'badge-out'; // rojo: sin stock
                                            $label = 'Sin stock';
                                        } elseif ($s <= 10) {
                                            $badge = 'badge-low'; // amarillo: stock bajo
                                            $label = $s;
                                        } else {
                                            $badge = 'badge-ok';  // verde: stock normal
                                            $label = $s;
                                        }
                                        ?>
                                        <tr>
                                            <!-- htmlspecialchars() escapa el nombre para prevenir XSS -->
                                            <td><?= htmlspecialchars($row['nombre']) ?></td>
                                            <td><span class="badge-stock <?= $badge ?>"><?= $label ?></span></td>
                                            <td><?= htmlspecialchars($row['unidad_medida']) ?></td>
                                            <td>
                                                <div class="acciones">
                                                    <!-- Se pasan los datos del ingrediente como argumentos al JS
                                                         ENT_QUOTES escapa tanto comillas simples como dobles
                                                         para que no rompan los atributos onclick -->
                                                    <button class="btn btn-edit"
                                                        onclick="abrirEditarIngrediente(
                                                            <?= $row['id_ingrediente'] ?>,
                                                            '<?= htmlspecialchars($row['nombre'], ENT_QUOTES) ?>',
                                                            <?= $row['stock'] ?>,
                                                            '<?= htmlspecialchars($row['unidad_medida'], ENT_QUOTES) ?>'
                                                        )">Editar</button>
                                                    <button class="btn btn-delete"
                                                        onclick="confirmarEliminar('ingrediente', <?= $row['id_ingrediente'] ?>, '<?= htmlspecialchars($row['nombre'], ENT_QUOTES) ?>')">Eliminar</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── Tabla compras ── -->
                    <div class="inv-section">
                        <div class="section-header">
                            <h2 class="table-title" style="margin:0;">Compras de ingredientes</h2>
                            <button class="btn btn-primary" onclick="abrirAgregarCompra()">+ Agregar compra</button>
                        </div>
                        <div class="tabletemplate-full">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Ingrediente</th>
                                        <th>Fecha</th>
                                        <th>Cantidad</th>
                                        <th>Costo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // foreach itera sobre el arreglo $compras ya enriquecido y ordenado
                                    foreach ($compras as $c): ?>
                                        <tr>
                                            <td>#<?= $c['id_compra'] ?></td>
                                            <td><?= htmlspecialchars($c['nombre_ingrediente']) ?></td>
                                            <td><?= $c['fecha'] ?></td>
                                            <td><?= $c['cantidad'] ?></td>
                                            <!-- number_format() formatea el numero con 2 decimales -->
                                            <td>$<?= number_format($c['costo'], 2) ?></td>
                                            <td>
                                                <button class="btn btn-delete"
                                                    onclick="confirmarEliminar('compra', <?= $c['id_compra'] ?>, 'Compra #<?= $c['id_compra'] ?>')">Eliminar</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- ── Modal agregar ingrediente ── -->
    <div class="modal-overlay" id="modal-agregar-ing">
        <div class="modal">
            <div class="modal-header">
                <h2>Agregar Ingrediente</h2>
                <button class="modal-close" onclick="cerrarModal('modal-agregar-ing')">&#10005;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="agregar_ingrediente">
                <div class="field">
                    <label>Nombre</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="field">
                    <label>Stock inicial</label>
                    <input type="number" name="stock" min="0" value="0" required>
                </div>
                <div class="field">
                    <label>Unidad de medida</label>
                    <input type="text" name="unidad_medida" placeholder="kg, g, L, unidad..." required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modal-agregar-ing')">Cancelar</button>
                    <button type="submit" class="btn-save">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Modal editar ingrediente ── -->
    <div class="modal-overlay" id="modal-editar">
        <div class="modal">
            <div class="modal-header">
                <h2>Editar Ingrediente</h2>
                <button class="modal-close" onclick="cerrarModal('modal-editar')">&#10005;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="editar_ingrediente">
                <!-- input hidden transporta el ID del ingrediente a editar sin mostrarlo al usuario -->
                <input type="hidden" name="id_ingrediente" id="edit-id">
                <div class="field">
                    <label>Nombre</label>
                    <input type="text" name="nombre" id="edit-nombre" required>
                </div>
                <div class="field">
                    <label>Stock actual</label>
                    <input type="number" name="stock" id="edit-stock" min="0" required>
                </div>
                <div class="field">
                    <label>Unidad de medida</label>
                    <input type="text" name="unidad_medida" id="edit-unidad" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modal-editar')">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Modal agregar compra ── -->
    <div class="modal-overlay" id="modal-compra">
        <div class="modal">
            <div class="modal-header">
                <h2>Agregar Compra</h2>
                <button class="modal-close" onclick="cerrarModal('modal-compra')">&#10005;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="agregar_compra">
                <div class="field">
                    <label>Ingrediente</label>
                    <select name="id_ingrediente" required>
                        <option value="" disabled selected>Selecciona</option>
                        <?php
                        // foreach itera sobre $ings_select para generar las opciones del select
                        foreach ($ings_select as $i): ?>
                            <option value="<?= $i['id_ingrediente'] ?>">
                                <?= htmlspecialchars($i['nombre']) ?> (stock: <?= $i['stock'] ?> <?= htmlspecialchars($i['unidad_medida']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Fecha de compra</label>
                    <!-- id="compra-fecha" permite que el JS asigne la fecha de hoy por defecto -->
                    <input type="date" name="fecha" id="compra-fecha" required>
                </div>
                <div class="field">
                    <label>Cantidad comprada</label>
                    <input type="number" name="cantidad" min="1" required>
                </div>
                <div class="field">
                    <label>Costo total ($)</label>
                    <input type="number" name="costo" step="0.01" min="0.01" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modal-compra')">Cancelar</button>
                    <button type="submit" class="btn-save">Registrar compra</button>
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

    <!-- Formularios ocultos usados para enviar DELETE via POST -->
    <!-- Se usan porque HTML no soporta metodo DELETE en formularios nativos -->
    <form id="form-del-ingrediente" class="hidden-form" method="POST" action="">
        <input type="hidden" name="action" value="eliminar_ingrediente">
        <input type="hidden" name="id_ingrediente" id="del-id-ingrediente">
    </form>
    <form id="form-del-compra" class="hidden-form" method="POST" action="">
        <input type="hidden" name="action" value="eliminar_compra">
        <input type="hidden" name="id_compra" id="del-id-compra">
    </form>

</body>

<script>
    // DOMContentLoaded se dispara cuando el HTML fue completamente parseado
    // garantiza que todos los elementos del DOM ya existen antes de manipularlos
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

        // new Date() crea un objeto con la fecha y hora actuales
        // toISOString() retorna la fecha en formato "YYYY-MM-DDTHH:mm:ss.sssZ"
        // split('T')[0] toma solo la parte de la fecha "YYYY-MM-DD"
        // Se asigna como valor por defecto al campo de fecha de compra
        document.getElementById('compra-fecha').value = new Date().toISOString().split('T')[0];
    });

    // ── Modales ───────────────────────────────────────────────────────────────

    // Cierra el modal con el id recibido removiendo la clase 'active'
    function cerrarModal(id) {
        // classList.remove() elimina la clase indicada del elemento
        document.getElementById(id).classList.remove('active');
    }

    // querySelectorAll() retorna todos los elementos que coincidan con el selector CSS
    // Se itera sobre cada modal para cerrarlos al hacer click fuera del contenido
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            // e.target es el elemento exacto donde se hizo click
            // 'this' es el overlay; si son iguales, el click fue en el fondo oscuro
            if (e.target === this) this.classList.remove('active');
        });
    });

    // Abre el modal de agregar ingrediente agregando la clase 'active'
    function abrirAgregarIngrediente() {
        document.getElementById('modal-agregar-ing').classList.add('active');
    }

    // Abre el modal de editar ingrediente y precarga los campos con los datos actuales
    // Los parametros llegan desde los atributos onclick generados por PHP
    function abrirEditarIngrediente(id, nombre, stock, unidad) {
        // .value asigna el valor al campo del formulario dentro del modal
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-nombre').value = nombre;
        document.getElementById('edit-stock').value = stock;
        document.getElementById('edit-unidad').value = unidad;
        document.getElementById('modal-editar').classList.add('active');
    }

    // Abre el modal de agregar compra
    function abrirAgregarCompra() {
        document.getElementById('modal-compra').classList.add('active');
    }

    // ── Confirmacion de eliminacion ───────────────────────────────────────────

    // Variables globales para recordar que se va a eliminar mientras el dialogo esta abierto
    let confirmTipo = null; // 'ingrediente' o 'compra'
    let confirmId = null; // ID del registro a eliminar

    // Abre el dialogo de confirmacion con el label del elemento a eliminar
    // textContent asigna texto plano al elemento (sin parsear HTML, mas seguro que innerHTML)
    function confirmarEliminar(tipo, id, label) {
        confirmTipo = tipo;
        confirmId = id;
        document.getElementById('confirm-label').textContent = label;
        document.getElementById('confirm-overlay').classList.add('active');
    }

    // Cierra el dialogo y limpia las variables de confirmacion
    function cerrarConfirm() {
        confirmTipo = null;
        confirmId = null;
        document.getElementById('confirm-overlay').classList.remove('active');
    }

    // Ejecuta la eliminacion segun el tipo guardado en confirmTipo
    function ejecutarEliminar() {
        if (!confirmTipo || !confirmId) return;

        if (confirmTipo === 'ingrediente') {
            // Asigna el ID al input hidden del formulario correspondiente y lo envia
            // .submit() envia el formulario via POST sin necesidad de un boton submit
            document.getElementById('del-id-ingrediente').value = confirmId;
            document.getElementById('form-del-ingrediente').submit();
        } else if (confirmTipo === 'compra') {
            document.getElementById('del-id-compra').value = confirmId;
            document.getElementById('form-del-compra').submit();
        }
    }

    // Cierra el dialogo de confirmacion si se hace click fuera del cuadro
    document.getElementById('confirm-overlay').addEventListener('click', function(e) {
        if (e.target === this) cerrarConfirm();
    });
</script>

</html>