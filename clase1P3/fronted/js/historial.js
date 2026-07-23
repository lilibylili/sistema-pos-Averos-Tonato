// Boton hamburguesa que muestra u oculta el panel izquierdo
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar'); // el panel verde de la izquierda
    const content = document.getElementById('content'); // el contenido principal de la pagina

    sidebar.classList.toggle('oculto'); // le agrega o le quita la clase que lo desplaza fuera de pantalla
    content.classList.toggle('expandido'); // el contenido ocupa todo el ancho cuando el sidebar esta oculto
}

const tarjetaTotalVendido = document.getElementById('tarjeta-total-vendido'); // la tarjeta que muestra el total vendido
const tarjetaCantidadFacturas = document.getElementById('tarjeta-cantidad-facturas'); // la tarjeta que muestra la cantidad de facturas
const tarjetaTicketPromedio = document.getElementById('tarjeta-ticket-promedio'); // la tarjeta que muestra el ticket promedio
const tablaHistorialBody = document.getElementById('tabla-historial-body'); // el tbody de la tabla principal donde se pintan las filas de facturas

const filtroFechaInicio = document.getElementById('filtro-fecha-inicio'); // el input de fecha de inicio del filtro
const filtroFechaFin = document.getElementById('filtro-fecha-fin'); // el input de fecha de fin del filtro
const filtroCliente = document.getElementById('filtro-cliente'); // el input de cliente del filtro (buscador en vivo) 
const filtroFactura = document.getElementById('filtro-factura'); // el input de numero de factura del filtro (buscador exacto, no en vivo)
const resultadosFiltroCliente = document.getElementById('resultados-filtro-cliente');

let timeoutBusquedaFiltroCliente = null; // para no disparar una peticion por cada tecla que el usuario escribe

// Buscador en vivo del filtro de cliente: igual que el de pos.php, se dispara mientras el usuario escribe
filtroCliente.addEventListener('input', function () {
    const texto = filtroCliente.value.trim();

    clearTimeout(timeoutBusquedaFiltroCliente); // cancelamos la busqueda anterior que estaba esperando

    if (texto === '') {
        resultadosFiltroCliente.innerHTML = '';
        cargarHistorial(); // si borraron el filtro, recalculamos sin ese filtro
        return;
    }

    //esperamos 300ms desde la ultima tecla presionada para recien buscar en el backend
    timeoutBusquedaFiltroCliente = setTimeout(() => buscarClientesFiltro(texto), 300);
});

// Buscar clientes en el backend por cedula o nombre
async function buscarClientesFiltro(texto) {
    try {
        const respuesta = await fetch(`backend/api_clientes.php?search=${encodeURIComponent(texto)}`);
        const clientes = await respuesta.json();

        renderizarResultadosFiltroCliente(clientes);
    } catch (error) {
        console.error('Error al buscar clientes para el filtro:', error);
    }
}

// Pintar la lista de sugerencias debajo del input de cliente del filtro
function renderizarResultadosFiltroCliente(clientes) {
    if (clientes.length === 0) {
        resultadosFiltroCliente.innerHTML = `<div class="list-group-item text-muted">Sin coincidencias</div>`;
        return;
    }

    resultadosFiltroCliente.innerHTML = clientes.map(cliente => `
        <button type="button" class="list-group-item list-group-item-action"
            onclick="seleccionarClienteFiltro('${cliente.cedula}', '${cliente.nombre_completo.replace(/'/g, "\\'")}')">
            ${cliente.nombre_completo} <small class="text-muted">(${cliente.cedula})</small>
        </button>
    `).join('');
}

// Al elegir un cliente de la lista se llena el input con su cedula asi el filtro queda exacto y se recalculan los totales y la tabla
function seleccionarClienteFiltro(cedula, nombre) {
    filtroCliente.value = cedula;
    resultadosFiltroCliente.innerHTML = '';
    cargarHistorial();
}

// Traer del backend los 3 totalizadores aplicando los filtros que esten llenos y pintarlos en las tarjetas
async function cargarResumen() {
    try {
        //armamos los parametros de la URL solo con los filtros que el usuario haya llenado
        const parametros = new URLSearchParams({ accion: 'resumen' });

        if (filtroFechaInicio.value !== '') parametros.append('fecha_inicio', filtroFechaInicio.value); // si el input de fecha de inicio no esta vacio, agregamos ese filtro
        if (filtroFechaFin.value !== '') parametros.append('fecha_fin', filtroFechaFin.value); // si el input de fecha de fin no esta vacio, agregamos ese filtro
        if (filtroCliente.value.trim() !== '') parametros.append('cliente', filtroCliente.value.trim()); // si el input de cliente no esta vacio, agregamos ese filtro
        if (filtroFactura.value.trim() !== '') parametros.append('factura', filtroFactura.value.trim()); // si el input de numero de factura no esta vacio, agregamos ese filtro

        const respuesta = await fetch(`backend/api_historial.php?${parametros.toString()}`); // hacemos la peticion al backend con los filtros aplicados
        const resumen = await respuesta.json(); // parseamos la respuesta como JSON

        tarjetaTotalVendido.textContent = `$${resumen.total_vendido.toFixed(2)}`; // pintamos el total vendido en la tarjeta correspondiente
        tarjetaCantidadFacturas.textContent = resumen.cantidad_facturas; // pintamos la cantidad de facturas en la tarjeta correspondiente
        tarjetaTicketPromedio.textContent = `$${resumen.ticket_promedio.toFixed(2)}`; // pintamos el ticket promedio en la tarjeta correspondiente
    } catch (error) {
        console.error('Error al cargar el resumen del historial:', error);
    }
}

// Convierte el numero de factura en formato 001-001-000000001
function formatearNumeroFactura(id) {
    return '001-001-' + String(id).padStart(9, '0');
}

// Convierte la fecha que manda MySQL a un formato legible dd/mm/yyyy hh:mm
function formatearFechaHora(fechaMysql) { 
    const fecha = new Date(fechaMysql.replace(' ', 'T')); // convertimos la fecha de MySQL a un objeto Date
    if (isNaN(fecha.getTime())) return fechaMysql; // por si acaso no rompemos la pantalla

    const dia = String(fecha.getDate()).padStart(2, '0'); // obtenemos el dia y le agregamos un 0 a la izquierda si es menor a 10
    const mes = String(fecha.getMonth() + 1).padStart(2, '0'); // obtenemos el mes (0-11) y le agregamos un 0 a la izquierda si es menor a 10
    const anio = fecha.getFullYear(); // obtenemos el año completo (4 digitos)
    const horas = String(fecha.getHours()).padStart(2, '0'); // obtenemos las horas y le agregamos un 0 a la izquierda si es menor a 10
    const minutos = String(fecha.getMinutes()).padStart(2, '0'); // obtenemos los minutos y le agregamos un 0 a la izquierda si es menor a 10

    return `${dia}/${mes}/${anio} ${horas}:${minutos}`; // devolvemos la fecha en formato dd/mm/yyyy hh:mm
}

// Devuelve el HTML del badge de color segun el estado de la factura ("Pagada" verde, "Anulada" rojo)
function renderizarBadgeEstado(estado) {
    const clase = estado === 'Pagada' ? 'badge-pagada' : 'badge-anulada';
    return `<span class="badge-estado ${clase}">${estado}</span>`;
}

// Traer del backend las filas de la tabla principal (aplicando los mismos filtros) y pintarlas
async function cargarTabla() {
    try {
        const parametros = new URLSearchParams({ accion: 'listado' }); // armamos los parametros de la URL solo con los filtros que el usuario haya llenado

        if (filtroFechaInicio.value !== '') parametros.append('fecha_inicio', filtroFechaInicio.value); // si el input de fecha de inicio no esta vacio, agregamos ese filtro
        if (filtroFechaFin.value !== '') parametros.append('fecha_fin', filtroFechaFin.value); // si el input de fecha de fin no esta vacio, agregamos ese filtro
        if (filtroCliente.value.trim() !== '') parametros.append('cliente', filtroCliente.value.trim()); // si el input de cliente no esta vacio, agregamos ese filtro
        if (filtroFactura.value.trim() !== '') parametros.append('factura', filtroFactura.value.trim()); // si el input de numero de factura no esta vacio, agregamos ese filtro

        const respuesta = await fetch(`backend/api_historial.php?${parametros.toString()}`); // hacemos la peticion al backend con los filtros aplicados
        const facturas = await respuesta.json(); // parseamos la respuesta como JSON

        renderizarTabla(facturas); // pintamos las filas de la tabla principal con los datos recibidos del backend
    } catch (error) {
        console.error('Error al cargar la tabla del historial:', error);
    }
}

// Pinta las filas de la tabla principal de facturas
function renderizarTabla(facturas) {
    if (!Array.isArray(facturas) || facturas.length === 0) { // si no es un array o esta vacio, mostramos un mensaje de "no hay resultados"
        tablaHistorialBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-4">No se encontraron facturas con los filtros aplicados</td>
            </tr>
        `;
        return;
    }

    // TABLA DE FACTURAS 
    //para cada factura recibida del backend, generamos una fila de la tabla con sus datos y botones de accion
    tablaHistorialBody.innerHTML = facturas.map(factura => `
        <tr>
            <td>${formatearNumeroFactura(factura.id)}</td>
            <td>${formatearFechaHora(factura.fecha_emision)}</td>
            <td>${factura.cliente_nombre}</td>
            <td>${factura.vendedor_usuario}</td>
            <td class="text-end">$${parseFloat(factura.total_factura).toFixed(2)}</td>
            <td class="text-center">${renderizarBadgeEstado(factura.estado)}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-secondary" title="Ver Detalles" onclick="verDetalles(${factura.id})">👁️</button>
                <button class="btn btn-sm btn-outline-secondary" title="Re-imprimir" onclick="reimprimirFactura(${factura.id})">🖨️</button>
                ${factura.estado === 'Pagada'
                    ? `<button class="btn btn-sm btn-outline-danger" title="Anular Factura" onclick="anularFactura(${factura.id})">🗑️</button>`
                    : ''}
            </td>
        </tr>
    `).join('');
}

// Refresca a la vez los totalizadores y la tabla (para no repetir la llamada en cada evento)
function cargarHistorial() {
    cargarResumen();
    cargarTabla();
}

// Boton "Filtrar": vuelve a cargar el resumen y la tabla con lo que el usuario haya escrito/seleccionado
function aplicarFiltros() {
    cargarHistorial();
}

// Boton de X que limpia todos los filtros y vuelve a cargar el resumen y la tabla general
function limpiarFiltros() {
    filtroFechaInicio.value = '';
    filtroFechaFin.value = '';
    filtroCliente.value = '';
    filtroFactura.value = '';
    cargarHistorial();
}

// las fechas son selectores (calendario), asi que apenas el usuario elige una fecha ya recalculamos todo,
// sin necesidad de que le de clic al boton "Filtrar"
filtroFechaInicio.addEventListener('change', cargarHistorial);
filtroFechaFin.addEventListener('change', cargarHistorial);

// al cargar la pagina se muestra las tarjetas y la tabla de una vez
cargarHistorial();

// Boton Ver Detalles el cual abre un modal con los productos de esa factura
const modalDetalleFactura = new bootstrap.Modal(document.getElementById('modalDetalleFactura')); // inicializamos el modal de bootstrap para poder abrirlo y cerrarlo desde JS
const modalDetalleTitulo = document.getElementById('modalDetalleTitulo'); // el titulo del modal que cambia segun la factura que se esta viendo
const modalDetalleCuerpo = document.getElementById('modalDetalleCuerpo');// el tbody de la tabla dentro del modal donde se pintan los productos de la factura

async function verDetalles(ventaId) { // recibe el id de la venta y hace una peticion al backend para traer los productos de esa venta
    try {
        const respuesta = await fetch(`backend/api_historial.php?accion=detalle&venta_id=${ventaId}`); // hacemos la peticion al backend para traer los productos de esa venta
        const items = await respuesta.json(); // parseamos la respuesta como JSON (un array de productos)

        modalDetalleTitulo.textContent = `Detalle de Factura ${formatearNumeroFactura(ventaId)}`;

        if (!Array.isArray(items) || items.length === 0) {
            modalDetalleCuerpo.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Sin productos registrados</td></tr>`;
        } else {
            modalDetalleCuerpo.innerHTML = items.map(item => {
                const subtotal = item.precio_congelado * item.cantidad;
                return `
                    <tr>
                        <td>${item.nombre_producto}<br><small class="text-muted">${item.codigo_barras}</small></td>
                        <td class="text-center">${item.cantidad}</td>
                        <td class="text-end">$${parseFloat(item.precio_congelado).toFixed(2)}</td>
                        <td class="text-end">$${subtotal.toFixed(2)}</td>
                    </tr>`;
            }).join('');
        }

        modalDetalleFactura.show();
    } catch (error) {
        console.error('Error al cargar el detalle de la factura:', error);
        alert('No se pudo cargar el detalle de la factura');
    }
}

// Boton de IMPRIMIR el cual abre una nueva ventana con la factura en PDF para imprimirla o guardarla
function reimprimirFactura(ventaId) {
    window.open(`backend/generar_factura.php?venta_id=${ventaId}`, '_blank');
}

// Boton para anular la factura el cual hace una peticion al backend para anular la venta y devolver el stock al inventario
async function anularFactura(ventaId) {
    //pedimos confirmacion porque es una accion critica (afecta el inventario y los totalizadores)
    const confirmado = confirm(`¿Está seguro de anular la factura ${formatearNumeroFactura(ventaId)}?\n\nEsta acción devolverá el stock de los productos al inventario.`);
    if (!confirmado) return;

    try {
        const respuesta = await fetch('backend/anular_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venta_id: ventaId })
        });
        const resultado = await respuesta.json();

        if (resultado.estado === 'ok') {
            alert('Factura anulada correctamente. El stock fue devuelto al inventario.');
            cargarHistorial(); // refrescamos la tabla y las tarjetas para reflejar el cambio
        } else {
            alert('No se pudo anular la factura: ' + resultado.message);
        }
    } catch (error) {
        console.error('Error al anular la factura:', error);
        alert('Error al conectar con el servidor');
    }
}