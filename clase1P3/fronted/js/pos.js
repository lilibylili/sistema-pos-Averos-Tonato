const inputScanner = document.getElementById('input-scanner'); // input del lector de codigo de barras
const cuerpoCarrito = document.getElementById('cuerpo-carrito'); //cuerpo de la tabla del carrito
const resultadosProducto = document.getElementById('resultados-producto'); // contenedor donde se pintan los resultados en vivo del producto

let timeoutBusquedaProducto = null; // para no disparar una peticion por cada tecla que el usuario escribe
let scannerCamara = null; // instancia del lector de camara (html5-qrcode)

const inputCliente = document.getElementById('input-cliente'); // input del buscador de cliente
const resultadosCliente = document.getElementById('resultados-cliente'); // contenedor donde se pintan los resultados de la busqueda
const clienteSeleccionadoDiv = document.getElementById('cliente-seleccionado'); // aviso del cliente que quedo asignado a la venta
const formNuevoCliente = document.getElementById('form-nuevo-cliente'); // formulario que aparece cuando no hay coincidencias
const nuevoClienteCedula = document.getElementById('nuevo-cliente-cedula');
const nuevoClienteNombre = document.getElementById('nuevo-cliente-nombre');
const nuevoClienteTelefono = document.getElementById('nuevo-cliente-telefono');
const nuevoClienteCorreo = document.getElementById('nuevo-cliente-correo');

//mientras el cajero escribe, vamos filtrando caracteres invalidos en tiempo real (no letras en cedula/telefono, no numeros en nombre)
nuevoClienteCedula.addEventListener('input', () => nuevoClienteCedula.value = soloNumeros(nuevoClienteCedula.value));
nuevoClienteTelefono.addEventListener('input', () => nuevoClienteTelefono.value = soloNumeros(nuevoClienteTelefono.value));
nuevoClienteNombre.addEventListener('input', () => nuevoClienteNombre.value = soloLetras(nuevoClienteNombre.value));

const CEDULA_CONSUMIDOR_FINAL = '9999999999'; // cedula fija del cliente generico creado en la BD

const resumenSubtotal = document.getElementById('resumen-subtotal'); // texto donde se muestra el subtotal
const resumenIva = document.getElementById('resumen-iva'); // texto donde se muestra el iva
const resumenTotal = document.getElementById('resumen-total'); // texto donde se muestra el total a pagar

const inputPago = document.getElementById('input-pago'); // input donde el cajero escribe con cuanto paga el cliente
const resumenVuelto = document.getElementById('resumen-vuelto'); // texto donde se muestra el cambio calculado

const IVA_PORCENTAJE = 0.15; // 15% de IVA fijo
let totalActual = 0; // guarda el total de la venta en numero (sin el simbolo $) para poder calcular el vuelto

// El carrito se maneja como un arreglo en memoria mientras dura la venta
let carrito = []; // aqui se guardan los productos que se van agregando al carrito de compras

let clienteActual = null; // si es null la venta se factura a "Consumidor Final", caso contrario guarda {id, cedula, nombre}
let timeoutBusquedaCliente = null; // para no disparar una peticion por cada tecla que el usuario escribe

// Boton hamburguesa que muestra u oculta el panel lateral verde (sidebar)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar'); // el panel verde de la izquierda
    const content = document.getElementById('content'); // el contenido principal de la pagina

    sidebar.classList.toggle('oculto'); // le agrega o le quita la clase que lo desplaza fuera de pantalla
    content.classList.toggle('expandido'); // el contenido ocupa todo el ancho cuando el sidebar esta oculto

    enfocarInput(); // regresamos el foco al lector de codigo de barras despues de dar clic en el boton
}

// Abrir la camara del dispositivo para escanear un codigo de barras (usa la libreria html5-qrcode)
function abrirCamara() {
    document.getElementById('overlay-camara').classList.remove('d-none');

    scannerCamara = new Html5Qrcode('lector-camara');

    scannerCamara.start(
        { facingMode: 'environment' }, // preferimos la camara trasera si el dispositivo tiene varias (celulares)
        {
            fps: 10, // cuadros por segundo
            qrbox: 220, // tamaño del recuadro de enfoque
            //le decimos a la libreria exactamente que formatos de codigo de barras debe reconocer,
            //asi no importa si el codigo se genero en Code-128, Code-93, EAN, etc.
            formatsToSupport: [
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_93,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.CODABAR
            ]
        },
        (codigoDetectado) => {
            // se detecto un codigo: lo procesamos exactamente igual que si lo hubieran escrito y presionado Enter
            buscarProducto(codigoDetectado);
            cerrarCamara();
        },
        () => { /* se ignoran los fotogramas donde todavia no se alcanza a leer ningun codigo */ }
    ).catch((error) => {
        alert('No se pudo acceder a la cámara: ' + error);
        cerrarCamara();
    });
}

// Cerrar el lector de camara y apagarla
function cerrarCamara() {
    document.getElementById('overlay-camara').classList.add('d-none');

    if (scannerCamara) {
        scannerCamara.stop()
            .then(() => scannerCamara.clear())
            .catch(() => {}); // si ya estaba detenida no pasa nada
        scannerCamara = null;
    }

    enfocarInput();
}

// Buscador en vivo de cliente: se dispara cada vez que el usuario escribe en el input
// se usa un pequeño retraso (debounce) para no mandar una peticion por cada tecla presionada
inputCliente.addEventListener('input', function () {
    const texto = inputCliente.value.trim();

    clearTimeout(timeoutBusquedaCliente); // cancelamos la busqueda anterior que estaba esperando

    //si el input quedo vacio limpiamos los resultados y no buscamos nada
    if (texto === '') {
        resultadosCliente.innerHTML = '';
        return;
    }

    //esperamos 300ms desde la ultima tecla presionada para recien buscar en el backend
    timeoutBusquedaCliente = setTimeout(() => buscarClientes(texto), 300);
});

// Buscar clientes en el backend por cedula o nombre
async function buscarClientes(texto) {
    try {
        const respuesta = await fetch(`backend/api_clientes.php?search=${encodeURIComponent(texto)}`); // pedimos al backend los clientes que coincidan
        const clientes = await respuesta.json(); // convertimos la respuesta en un arreglo de clientes

        renderizarResultadosCliente(clientes);
    } catch (error) {
        console.error('Error al buscar el cliente:', error); // mostramos el error en la consola del navegador
    }
}

// Pintar la lista de resultados debajo del buscador de cliente
function renderizarResultadosCliente(clientes) {
    //si no hay coincidencias, ocultamos la lista y mostramos el formulario para registrar un cliente nuevo
    if (clientes.length === 0) {
        resultadosCliente.innerHTML = '';
        formNuevoCliente.classList.remove('d-none');
        nuevoClienteCedula.value = inputCliente.value.trim(); // precargamos con lo que el cajero ya escribio, por si era la cedula
        return;
    }

    formNuevoCliente.classList.add('d-none'); // si hay coincidencias ocultamos el formulario de registro

    resultadosCliente.innerHTML = clientes.map(cliente => `
        <button type="button" class="list-group-item list-group-item-action"
            onclick="seleccionarCliente(${cliente.id}, '${cliente.cedula}', '${cliente.nombre_completo.replace(/'/g, "\\'")}', ${Number(cliente.es_cliente_fiel) === 1})">
            ${cliente.nombre_completo} <small class="text-muted">(${cliente.cedula})</small>
            ${Number(cliente.es_cliente_fiel) === 1 ? ' <span class="badge-cliente-fiel-mini">🏆</span>' : ''}
        </button>
    `).join('');
}

// Registrar un cliente nuevo desde el formulario rapido y asignarlo de una vez a la venta actual
async function registrarClienteNuevo() {
    const cedula = nuevoClienteCedula.value.trim();
    const nombre = nuevoClienteNombre.value.trim();
    const telefono = nuevoClienteTelefono.value.trim();
    const correo = nuevoClienteCorreo.value.trim();

    //validamos que al menos la cedula, el nombre y el telefono esten llenos antes de mandar la peticion
    if (cedula === '' || nombre === '' || telefono === '') {
        alert('La cédula, el nombre completo y el teléfono son obligatorios');
        return;
    }

    //validamos el formato exacto de la cedula ecuatoriana (usando el algoritmo del digito verificador)
    if (!validarCedulaEcuador(cedula)) {
        alert('La cédula ingresada no es válida. Verifique que sea una cédula ecuatoriana correcta.');
        return;
    }

    //validamos el formato del telefono ecuatoriano (celular 09XXXXXXXX o convencional 0XXXXXXXX)
    if (!validarTelefonoEcuador(telefono)) {
        alert('El teléfono no es válido. Use el formato celular (0991234567) o convencional (022345678).');
        return;
    }

    try {
        const respuesta = await fetch('backend/api_clientes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cedula: cedula, nombre_completo: nombre, telefono: telefono, correo: correo })
        });
        const resultado = await respuesta.json();

        if (resultado.estado === 'ok') {
            seleccionarCliente(resultado.id, resultado.cedula, resultado.nombre_completo); // lo asignamos de una vez a la venta
            formNuevoCliente.classList.add('d-none');
            nuevoClienteCedula.value = '';
            nuevoClienteNombre.value = '';
            nuevoClienteTelefono.value = '';
            nuevoClienteCorreo.value = '';
        } else {
            alert('No se pudo registrar el cliente: ' + resultado.message);
        }
    } catch (error) {
        console.error('Error al registrar el cliente:', error);
        alert('Error al conectar con el servidor');
    }
}

// Asignar el cliente elegido de la lista a la venta actual
function seleccionarCliente(id, cedula, nombre, esClienteFiel = false) {
    clienteActual = { id: id, cedula: cedula, nombre: nombre }; // guardamos el cliente que va a ir en la factura

    clienteSeleccionadoDiv.innerHTML = `👤 ${nombre} <small class="text-muted">(${cedula})</small>`;
    inputCliente.value = ''; // limpiamos el buscador
    resultadosCliente.innerHTML = ''; // ocultamos la lista de resultados
    formNuevoCliente.classList.add('d-none'); // por si estaba visible el formulario de registro

    //si el cliente es el Cliente Fiel del Año, mostramos su insignia y habilitamos el campo de descuento manual;
    //si no lo es, ocultamos el panel y reseteamos el descuento a 0 para que no se quede aplicado por error
    const panelClienteFiel = document.getElementById('panel-cliente-fiel');
    if (esClienteFiel) {
        panelClienteFiel.classList.remove('d-none');
    } else {
        panelClienteFiel.classList.add('d-none');
        document.getElementById('input-descuento').value = '';
    }
    calcularTotales(); // recalculamos porque el descuento pudo haber cambiado al cambiar de cliente

    enfocarInput(); // regresamos el foco al lector de codigo de barras para seguir escaneando productos
}

// Boton rapido para facturar como Consumidor Final
// como cliente_id es obligatorio en la tabla ventas, buscamos el id real del cliente generico "Consumidor Final" en la BD
async function usarConsumidorFinal() {
    try {
        const respuesta = await fetch(`backend/api_clientes.php?search=${CEDULA_CONSUMIDOR_FINAL}`);
        const clientes = await respuesta.json();
        const consumidorFinal = clientes.find(c => c.cedula === CEDULA_CONSUMIDOR_FINAL);

        if (consumidorFinal) {
            seleccionarCliente(consumidorFinal.id, consumidorFinal.cedula, 'Consumidor Final');
        } else {
            //si todavia no se ha creado el registro en la BD, avisamos al cajero
            alert('El cliente genérico "Consumidor Final" aún no existe en la base de datos. Debe crearlo primero con el script SQL.');
        }
    } catch (error) {
        console.error('Error al buscar Consumidor Final:', error);
        alert('Error al conectar con el servidor');
    }
}

// Mantener el foco siempre en el input del lector de codigo (el foco hace referencia a donde se escriben los caracteres que envia la pistola lectora)
function enfocarInput() {
    inputScanner.focus();
}

// si el usuario hace clic en cualquier parte que no sea un boton o un input,
// regresamos el foco automaticamente al lector de codigo de barras
document.addEventListener('click', function (e) {
    if (!e.target.closest('button') && !e.target.closest('input')) {
        enfocarInput();
    }
});

enfocarInput();
usarConsumidorFinal(); // al cargar la pagina, la venta arranca asignada a Consumidor Final por defecto
inputScanner.addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const codigo = inputScanner.value.trim();

        if (codigo !== '') {
            buscarProducto(codigo);
        }
        inputScanner.value = '';
        resultadosProducto.innerHTML = ''; // al confirmar con Enter tambien ocultamos la lista de sugerencias
    }
});

// Filtro en vivo: mientras el usuario escribe (letra por letra) se va mostrando el listado que coincide,
// igual que el buscador de cliente. Sirve tanto si escribe el codigo como si escribe el nombre del producto
inputScanner.addEventListener('input', function () {
    const texto = inputScanner.value.trim();

    clearTimeout(timeoutBusquedaProducto); // cancelamos la busqueda anterior que estaba esperando

    if (texto === '') {
        resultadosProducto.innerHTML = '';
        return;
    }

    //esperamos 300ms desde la ultima tecla presionada para recien buscar en el backend
    timeoutBusquedaProducto = setTimeout(() => buscarProductosEnVivo(texto), 300);
});

// Buscar productos en el backend por codigo o nombre, para ir mostrando el listado mientras se escribe
async function buscarProductosEnVivo(texto) {
    try {
        const respuesta = await fetch(`backend/api_productos.php?search=${encodeURIComponent(texto)}`);
        const productos = await respuesta.json();

        renderizarResultadosProducto(productos);
    } catch (error) {
        console.error('Error al buscar productos:', error);
    }
}

// Pintar la lista de sugerencias debajo del buscador de producto
function renderizarResultadosProducto(productos) {
    if (productos.length === 0) {
        resultadosProducto.innerHTML = `<div class="list-group-item text-muted">Sin coincidencias</div>`;
        return;
    }

    resultadosProducto.innerHTML = productos.map(producto => `
        <button type="button" class="list-group-item list-group-item-action"
            onclick='seleccionarProductoDeLista(${JSON.stringify(producto).replace(/'/g, "&#39;")})'>
            ${producto.nombre_producto} <small class="text-muted">(${producto.codigo_barras}) - $${parseFloat(producto.precio_actual).toFixed(2)}</small>
        </button>
    `).join('');
}

// Cuando el usuario hace clic en un producto de la lista de sugerencias, se agrega directo al carrito
function seleccionarProductoDeLista(producto) {
    agregarAlCarrito(producto);
    inputScanner.value = '';
    resultadosProducto.innerHTML = '';
    enfocarInput();
}

// Buscar el producto en el backend a partir del codigo leido
async function buscarProducto(codigo) { // funcion asincrona para buscar el producto en el backend
    try {
        const respuesta = await fetch(`backend/api_productos.php?search=${encodeURIComponent(codigo)}`); // hacemos la peticion al backend para buscar el producto por codigo de barras
        const productos = await respuesta.json(); // convertimos la respuesta en un arreglo de productos

        // buscamos la coincidencia exacta por codigo de barras
        const producto = productos.find(p => p.codigo_barras === codigo);


        // Si encontramos el producto, lo agregamos al carrito, si no, mostramos un mensaje de error
        if (producto) {
            agregarAlCarrito(producto); 
        } else {
            alert('Producto no encontrado con el código: ' + codigo);
        }

        //se toma otra vez el foco en el input del lector de codigo de barras para que pueda seguir leyendo codigos
    } catch (error) {
        console.error('Error al buscar el producto:', error); // mostramos el error en la consola del navegador
        alert('Error al conectar con el servidor');
    } finally {
        enfocarInput();
    }
}

// Agregar producto al carrito y si ya existe se le suma a la cantidad
function agregarAlCarrito(producto) {
    const existente = carrito.find(item => item.id === producto.id); // buscamos si el producto ya existe en el carrito por su id

    //si el producto ya esta en el carrito
    if (existente) {
        existente.cantidad++; //le sumamos 1 a la cantidad 
    
    //caso contrario de que ell producto no este en el carrito lo agregamos con cantidad 1
    } else {
        carrito.push({
            id: producto.id,
            codigo: producto.codigo_barras,
            nombre: producto.nombre_producto,
            precio: parseFloat(producto.precio_actual),
            cantidad: 1
        });
    }

    renderizarCarrito(); // renderizamos el carrito para que se vea reflejado el cambio en la interfaz
}

// Botones de + y - de cada fila del carrito

// Sumar cantidad de un producto en el carrito
function sumarCantidad(id) {
    const item = carrito.find(p => p.id === id); // buscamos el producto en el carrito por su id
    if (item) item.cantidad++; //SI el producto existe le sumamos 1 a la cantidad
    renderizarCarrito(); // renderizamos el carrito para que se vea reflejado el cambio en la interfaz
}

// Restar cantidad de un producto en el carrito y si la cantidad llega a 0 se elimina del carrito
function restarCantidad(id) {
    const item = carrito.find(p => p.id === id); // buscamos el producto en el carrito por su id

    //si el producto existe le restamos 1 a la cantidad y si la cantidad llega a 0 se elimina del carrito
    if (item) {
        item.cantidad--;
        if (item.cantidad <= 0) {
            quitarProducto(id); // eliminamos el producto del carrito si la cantidad es 0 o menor
            return; // salimos de la funcion para no renderizar el carrito dos veces
        }
    }
    renderizarCarrito();
}

// Quitar un producto del carrito por su id
function quitarProducto(id) {
    carrito = carrito.filter(p => p.id !== id); // filtramos el carrito para eliminar el producto con el id especificado
    renderizarCarrito();
}

// Pintar la tabla del carrito con el subtotal por linea 
function renderizarCarrito() {
    if (carrito.length === 0) {
        cuerpoCarrito.innerHTML = `
            <tr id="fila-vacio">
                <td colspan="5" class="text-center text-muted py-4">El carrito está vacío. Escanee un producto para comenzar.</td>
            </tr>`;
        calcularTotales(); // con el carrito vacio los totales se van en 0
        return;
    }

    cuerpoCarrito.innerHTML = carrito.map(item => {
        const subtotal = (item.precio * item.cantidad).toFixed(2);
        return `
            <tr>
                <td>${item.nombre}<br><small class="text-muted">${item.codigo}</small></td>
                <td>$${item.precio.toFixed(2)}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger btn-cantidad" onclick="restarCantidad(${item.id})">-</button>
                    <span class="mx-2">${item.cantidad}</span>
                    <button class="btn btn-sm btn-outline-success btn-cantidad" onclick="sumarCantidad(${item.id})">+</button>
                </td>
                <td>$${subtotal}</td>
                <td><button class="btn btn-sm btn-outline-secondary" onclick="quitarProducto(${item.id})">🗑️</button></td>
            </tr>`;
    }).join('');

    calcularTotales(); // cada vez que se repinta el carrito recalculamos el subtotal, iva y total
}

// Calcular el subtotal (suma de precio*cantidad de todas las lineas), el descuento, el iva y el total, y pintarlos en el panel derecho
function calcularTotales() {
    const subtotalBruto = carrito.reduce((acumulado, item) => acumulado + (item.precio * item.cantidad), 0); // sumamos el subtotal de cada linea del carrito

    //el descuento solo aplica si el panel de Cliente Fiel esta visible (es decir, si el cliente actual es el Cliente Fiel del Año)
    const panelClienteFiel = document.getElementById('panel-cliente-fiel');
    const inputDescuento = document.getElementById('input-descuento');
    const filaResumenDescuento = document.getElementById('fila-resumen-descuento');
    const resumenDescuento = document.getElementById('resumen-descuento');

    let porcentajeDescuento = 0;
    if (panelClienteFiel && !panelClienteFiel.classList.contains('d-none')) {
        porcentajeDescuento = parseFloat(inputDescuento.value) || 0;
        if (porcentajeDescuento < 0) porcentajeDescuento = 0;
        if (porcentajeDescuento > 100) porcentajeDescuento = 100;
    }

    const montoDescuento = subtotalBruto * (porcentajeDescuento / 100); // el descuento se aplica sobre el subtotal, antes del IVA
    const subtotal = subtotalBruto - montoDescuento;

    //mostramos u ocultamos la fila de descuento en el resumen, solo cuando si hay algo que descontar
    if (montoDescuento > 0) {
        filaResumenDescuento.classList.remove('d-none');
        resumenDescuento.textContent = `-$${montoDescuento.toFixed(2)}`;
    } else {
        filaResumenDescuento.classList.add('d-none');
    }

    const iva = subtotal * IVA_PORCENTAJE; // el iva es el 15% del subtotal ya con el descuento aplicado
    const total = subtotal + iva; // el total a pagar es el subtotal mas el iva

    totalActual = total; // guardamos el total en numero para poder usarlo despues en el calculo del vuelto

    resumenSubtotal.textContent = `$${subtotalBruto.toFixed(2)}`;
    resumenIva.textContent = `$${iva.toFixed(2)}`;
    resumenTotal.textContent = `$${total.toFixed(2)}`;

    calcularVuelto(); // como el total cambio, recalculamos el vuelto con lo que ya haya escrito el cajero en "Paga con"
}

// Cada vez que el cajero cambia el porcentaje de descuento, recalculamos todo
document.getElementById('input-descuento').addEventListener('input', calcularTotales);

// Calcular el cambio (vuelto) cada vez que el cajero escribe en el input de pago
inputPago.addEventListener('input', calcularVuelto);

function calcularVuelto() {
    const pago = parseFloat(inputPago.value) || 0; // si el input esta vacio o no es un numero, tomamos 0
    const vuelto = pago - totalActual; // el vuelto es lo que paga el cliente menos el total a pagar

    resumenVuelto.textContent = `$${vuelto.toFixed(2)}`;

    //si el vuelto es negativo significa que el cliente todavia no ha pagado lo suficiente, lo marcamos en rojo
    if (vuelto < 0) {
        resumenVuelto.classList.remove('text-success');
        resumenVuelto.classList.add('text-danger');
    } else {
        resumenVuelto.classList.remove('text-danger');
        resumenVuelto.classList.add('text-success');
    }
}

const btnProcesarVenta = document.getElementById('btn-procesar-venta'); // el boton verde grande

// Procesar la venta: valida todo, manda el carrito completo al backend, y si sale bien limpia la pantalla para la siguiente venta
async function procesarVenta() {
    //no se puede vender un carrito vacio
    if (carrito.length === 0) {
        alert('El carrito está vacío, no hay nada que vender');
        return;
    }

    //no se puede procesar la venta sin un cliente asignado (por si acaso, aunque siempre deberia haber uno por defecto)
    if (!clienteActual) {
        alert('Debe asignar un cliente a la venta (o usar Consumidor Final)');
        return;
    }

    const pago = parseFloat(inputPago.value) || 0; // lo que escribio el cajero en "Paga con"

    //no se puede procesar la venta si el cliente no ha pagado lo suficiente
    if (pago < totalActual) {
        alert('El monto pagado es menor al total a pagar');
        return;
    }

    //armamos el carrito en el formato que espera el backend
    const items = carrito.map(item => ({
        producto_id: item.id,
        cantidad: item.cantidad,
        precio: item.precio
    }));

    //deshabilitamos el boton mientras se procesa para que el cajero no le de doble clic
    btnProcesarVenta.disabled = true;
    btnProcesarVenta.textContent = 'Procesando...';

    try {
        const respuesta = await fetch('backend/procesar_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cliente_id: clienteActual.id,
                total: totalActual,
                items: items
            })
        });
        const resultado = await respuesta.json();

        if (resultado.estado === 'ok') {
            //abrimos la factura en PDF en una pestaña nueva
            window.open(`backend/generar_factura.php?venta_id=${resultado.venta_id}`, '_blank');

            //si el cliente gano el bono de puntos por superar los $2000 en el año, avisamos al cajero
            if (resultado.puntos_ganados > 0) {
                alert(`🎉 ¡El cliente superó los $2000 en compras este año y ganó ${resultado.puntos_ganados} puntos!`);
            }

            //reiniciamos todo para la siguiente venta
            carrito = [];
            renderizarCarrito();
            inputPago.value = '';
            document.getElementById('input-descuento').value = ''; // reseteamos el descuento aplicado
            calcularVuelto();
            usarConsumidorFinal(); // la siguiente venta vuelve a arrancar en Consumidor Final
        } else {
            alert('No se pudo procesar la venta: ' + resultado.message);
        }
    } catch (error) {
        console.error('Error al procesar la venta:', error);
        alert('Error al conectar con el servidor');
    } finally {
        btnProcesarVenta.disabled = false;
        btnProcesarVenta.textContent = '✅ Procesar Venta';
        enfocarInput();
    }
}