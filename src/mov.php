<?php
// Verifica que el usuario tenga sesion activa; si no, redirige al login
require_once 'auth.php';

// Carga la clase mySQLConexion con todos sus metodos CRUD
require_once "mySQLi.php";

// Instancia la conexion a la base de datos
$SQL = new mySQLConexion();

$mensaje = null;
$error   = null;

// Solo se procesa logica de escritura si el formulario fue enviado por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ?? '' retorna el valor de $_POST['action'] o '' si no existe
    $action = $_POST['action'] ?? '';

    // ── Agregar movimiento ────────────────────────────────────────────────────
    if ($action === 'agregar_movimiento') {

        // (int) convierte a entero para evitar inyecciones con valores no numericos
        $id_ingrediente = (int) ($_POST['id_ingrediente'] ?? 0);
        // trim() elimina espacios en blanco al inicio y al final del valor recibido
        $tipo           = trim($_POST['tipo']            ?? '');
        $cantidad       = (int) ($_POST['cantidad']      ?? 0);

        // in_array() verifica que el valor de $tipo exista dentro del arreglo permitido
        // Esto impide que se inserten valores arbitrarios en la columna 'tipo'
        if ($id_ingrediente <= 0 || !in_array($tipo, ['entrada', 'salida']) || $cantidad <= 0) {
            $error = "Todos los campos son obligatorios y el tipo debe ser entrada o salida.";
        } else {
            try {
                // INSERT() ejecuta: INSERT INTO `movimiento` (id_ingrediente, tipo, cantidad) VALUES (...)
                $SQL->INSERT(
                    'movimiento',
                    ['id_ingrediente', 'tipo', 'cantidad'],
                    [$id_ingrediente, $tipo, $cantidad]
                );
                $mensaje = "Movimiento registrado correctamente.";
            } catch (Exception $e) {
                // error_log() escribe el error en el log del servidor sin mostrarlo al usuario
                error_log($e->getMessage());
                // htmlspecialchars() escapa caracteres especiales para prevenir XSS
                $error = "Error al registrar: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Editar movimiento ─────────────────────────────────────────────────────
    elseif ($action === 'editar_movimiento') {

        $id_movimiento  = (int) ($_POST['id_movimiento']  ?? 0);
        $id_ingrediente = (int) ($_POST['id_ingrediente'] ?? 0);
        $tipo           = trim($_POST['tipo']             ?? '');
        $cantidad       = (int) ($_POST['cantidad']       ?? 0);

        if ($id_movimiento <= 0 || $id_ingrediente <= 0 || !in_array($tipo, ['entrada', 'salida']) || $cantidad <= 0) {
            $error = "Todos los campos son obligatorios y el tipo debe ser entrada o salida.";
        } else {
            try {
                // UPDATE() ejecuta: UPDATE `movimiento` SET ... WHERE id_movimientio = $id_movimiento
                // Nota: 'id_movimientio' tiene un typo en la DB original; se respeta para compatibilidad
                $SQL->UPDATE(
                    'movimiento',
                    "id_movimientio = $id_movimiento",
                    ['id_ingrediente', 'tipo', 'cantidad'],
                    [$id_ingrediente, $tipo, $cantidad]
                );
                $mensaje = "Movimiento actualizado correctamente.";
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = "Error al editar: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Eliminar movimiento ───────────────────────────────────────────────────
    elseif ($action === 'eliminar_movimiento') {

        $id_movimiento = (int) ($_POST['id_movimiento'] ?? 0);

        if ($id_movimiento <= 0) {
            $error = "ID de movimiento invalido.";
        } else {
            try {
                // DELETE() ejecuta: DELETE FROM `movimiento` WHERE `id_movimientio` = $id_movimiento
                $SQL->DELETE('movimiento', 'id_movimientio', $id_movimiento);
                $mensaje = "Movimiento eliminado correctamente.";
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = "Error al eliminar: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// ── Cargar movimientos con nombre de ingrediente ──────────────────────────────
// SELECT() ejecuta: SELECT * FROM `movimiento`
// Retorna un objeto mysqli_result con todos los movimientos
$r_movimientos = $SQL->SELECT('movimiento');
$movimientos   = [];

while ($m = $r_movimientos->fetch_assoc()) {
    // Para cada movimiento, buscar el nombre del ingrediente relacionado por su ID
    // SINGLESELECT() ejecuta: SELECT * FROM `ingrediente` WHERE `id_ingrediente` = $id
    $r_ing = $SQL->SINGLESELECT('ingrediente', 'id_ingrediente', $m['id_ingrediente']);
    // fetch_assoc() obtiene la fila como arreglo asociativo [columna => valor]
    $ing   = $r_ing->fetch_assoc();

    // Si el ingrediente fue eliminado, mostrar '(eliminado)' en su lugar
    // El operador ternario ?: evalua: si $ing existe usa $ing['nombre'], si no usa '(eliminado)'
    $m['nombre_ingrediente'] = $ing ? $ing['nombre'] : '(eliminado)';
    $movimientos[] = $m;
}

// ── Ingredientes para el select ───────────────────────────────────────────────
// Se carga en un arreglo separado porque el resultado anterior ya fue consumido por fetch_assoc()
$r_ings = $SQL->SELECT('ingrediente');
$ings   = [];
while ($i = $r_ings->fetch_assoc()) {
    $ings[] = $i;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos - Panes Bea</title>
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
                <h3 class="tittl1">Movimientos</h3>

                <div class="boxfax">

                    <?php if ($mensaje): ?>
                        <!-- htmlspecialchars() escapa el mensaje para prevenir XSS -->
                        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="section-header">
                        <h2 class="table-title" style="margin:0;">Registro de movimientos</h2>
                        <button class="btn btn-primary" onclick="abrirAgregar()">+ Agregar movimiento</button>
                    </div>

                    <div class="tabletemplate-full">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Ingrediente</th>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // empty() retorna true si el arreglo no tiene elementos
                                // Se muestra un mensaje cuando no hay movimientos registrados
                                if (empty($movimientos)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; color:#94a3b8; padding:2rem;">
                                            No hay movimientos registrados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    // foreach itera sobre el arreglo $movimientos ya enriquecido con nombres
                                    foreach ($movimientos as $m): ?>
                                        <tr>
                                            <!-- id_movimientio conserva el typo de la columna en la DB -->
                                            <td>#<?= $m['id_movimientio'] ?></td>
                                            <!-- htmlspecialchars() escapa el nombre del ingrediente para prevenir XSS -->
                                            <td><?= htmlspecialchars($m['nombre_ingrediente']) ?></td>
                                            <td>
                                                <!-- badge-<?= $m['tipo'] ?> genera dinamicamente la clase CSS
                                                     badge-entrada (verde) o badge-salida (rojo) segun el tipo -->
                                                <span class="badge-tipo badge-<?= $m['tipo'] ?>">
                                                    <?= htmlspecialchars($m['tipo']) ?>
                                                </span>
                                            </td>
                                            <td><?= $m['cantidad'] ?></td>
                                            <td>
                                                <div class="acciones">
                                                    <!-- Se pasan id, idIng, tipo y cantidad como argumentos al JS
                                                         para precargar los campos del modal de edicion -->
                                                    <button class="btn btn-edit"
                                                        onclick="abrirEditar(
                                                            <?= $m['id_movimientio'] ?>,
                                                            <?= $m['id_ingrediente'] ?>,
                                                            '<?= $m['tipo'] ?>',
                                                            <?= $m['cantidad'] ?>
                                                        )">Editar</button>
                                                    <button class="btn btn-delete"
                                                        onclick="confirmarEliminar(<?= $m['id_movimientio'] ?>)">Eliminar</button>
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

    <!-- ── Modal agregar movimiento ── -->
    <div class="modal-overlay" id="modal-agregar">
        <div class="modal">
            <div class="modal-header">
                <h2>Agregar Movimiento</h2>
                <button class="modal-close" onclick="cerrarModal('modal-agregar')">&#10005;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="agregar_movimiento">
                <div class="field">
                    <label>Ingrediente</label>
                    <select name="id_ingrediente" required>
                        <option value="" disabled selected>Selecciona</option>
                        <?php
                        // foreach genera las opciones del select con los ingredientes disponibles
                        foreach ($ings as $i): ?>
                            <option value="<?= $i['id_ingrediente'] ?>">
                                <?= htmlspecialchars($i['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Tipo</label>
                    <!-- class="tipo-select" aplica colores a las opciones via CSS -->
                    <select name="tipo" class="tipo-select" required>
                        <option value="" disabled selected>Selecciona</option>
                        <option value="entrada">Entrada</option>
                        <option value="salida">Salida</option>
                    </select>
                </div>
                <div class="field">
                    <label>Cantidad</label>
                    <input type="number" name="cantidad" min="1" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modal-agregar')">Cancelar</button>
                    <button type="submit" class="btn-save">Registrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Modal editar movimiento ── -->
    <div class="modal-overlay" id="modal-editar">
        <div class="modal">
            <div class="modal-header">
                <h2>Editar Movimiento</h2>
                <button class="modal-close" onclick="cerrarModal('modal-editar')">&#10005;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="editar_movimiento">
                <!-- input hidden transporta el ID del movimiento a editar sin mostrarlo al usuario -->
                <input type="hidden" name="id_movimiento" id="edit-id">
                <div class="field">
                    <label>Ingrediente</label>
                    <!-- id="edit-ingrediente" permite que JS precargue el ingrediente seleccionado -->
                    <select name="id_ingrediente" id="edit-ingrediente" required>
                        <option value="" disabled>Selecciona</option>
                        <?php foreach ($ings as $i): ?>
                            <option value="<?= $i['id_ingrediente'] ?>">
                                <?= htmlspecialchars($i['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Tipo</label>
                    <!-- id="edit-tipo" permite que JS asigne el tipo actual al abrir el modal -->
                    <select name="tipo" id="edit-tipo" class="tipo-select" required>
                        <option value="entrada">Entrada</option>
                        <option value="salida">Salida</option>
                    </select>
                </div>
                <div class="field">
                    <label>Cantidad</label>
                    <input type="number" name="cantidad" id="edit-cantidad" min="1" required>
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
    <form id="form-del-mov" class="hidden-form" method="POST" action="">
        <input type="hidden" name="action" value="eliminar_movimiento">
        <!-- id="del-id-mov" recibe el ID desde JS justo antes del submit -->
        <input type="hidden" name="id_movimiento" id="del-id-mov">
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

    // ── Modales ───────────────────────────────────────────────────────────────

    // Cierra el modal con el id recibido removiendo la clase 'active'
    function cerrarModal(id) {
        // classList.remove() elimina la clase indicada del elemento
        document.getElementById(id).classList.remove('active');
    }

    // querySelectorAll() retorna todos los elementos que coincidan con el selector CSS
    // Se cierra cada modal si el usuario hace click en el fondo oscuro
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            // e.target es el elemento exacto donde se hizo click
            // 'this' es el overlay; si coinciden el click fue en el fondo y no en el contenido
            if (e.target === this) this.classList.remove('active');
        });
    });

    // Abre el modal de agregar movimiento
    function abrirAgregar() {
        document.getElementById('modal-agregar').classList.add('active');
    }

    // Abre el modal de editar y precarga los campos con los datos del movimiento seleccionado
    // Los parametros llegan desde los atributos onclick generados por PHP
    function abrirEditar(id, idIng, tipo, cantidad) {
        // .value asigna el valor al campo o select del formulario dentro del modal
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-ingrediente').value = idIng;
        document.getElementById('edit-tipo').value = tipo;
        document.getElementById('edit-cantidad').value = cantidad;
        document.getElementById('modal-editar').classList.add('active');
    }

    // ── Confirmacion de eliminacion ───────────────────────────────────────────

    // Variable global para recordar el ID del movimiento mientras el dialogo esta abierto
    let confirmId = null;

    // Abre el dialogo de confirmacion con el numero del movimiento a eliminar
    function confirmarEliminar(id) {
        confirmId = id;
        // textContent asigna texto plano de forma segura (sin parsear HTML)
        // Los template literals permiten interpolacion de variables con ${}
        document.getElementById('confirm-label').textContent = `Movimiento #${id}`;
        document.getElementById('confirm-overlay').classList.add('active');
    }

    // Cierra el dialogo y limpia el ID guardado
    function cerrarConfirm() {
        confirmId = null;
        document.getElementById('confirm-overlay').classList.remove('active');
    }

    // Asigna el ID al formulario oculto y lo envia via POST para eliminar el movimiento
    function ejecutarEliminar() {
        if (!confirmId) return;
        // Asignar el ID al input hidden antes de enviar el formulario
        document.getElementById('del-id-mov').value = confirmId;
        // .submit() envia el formulario sin necesidad de un boton submit
        document.getElementById('form-del-mov').submit();
    }

    // Cerrar el dialogo de confirmacion si se hace click fuera del cuadro
    document.getElementById('confirm-overlay').addEventListener('click', function(e) {
        if (e.target === this) cerrarConfirm();
    });
</script>

</html>