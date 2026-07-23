// clientes.js - Logica de la pantalla Gestion de Clientes

const inputBuscarCliente = document.getElementById('input-buscar-cliente');
const cuerpoTablaClientes = document.getElementById('cuerpo-tabla-clientes');

let timeoutBusquedaClientes = null; // para no disparar una peticion por cada tecla que el usuario escribe

// Buscador en vivo: igual que en pos.php, se dispara mientras el usuario escribe (con debounce de 300ms)
inputBuscarCliente.addEventListener('input', function () {
    clearTimeout(timeoutBusquedaClientes);
    timeoutBusquedaClientes = setTimeout(cargarClientes, 300);
});

// Traer del backend la lista de clientes (filtrada si hay texto en el buscador, completa si esta vacio)
async function cargarClientes() {
    try {
        const texto = inputBuscarCliente.value.trim();
        const respuesta = await fetch(`backend/api_clientes.php?search=${encodeURIComponent(texto)}`);
        const clientes = await respuesta.json();

        renderizarTablaClientes(clientes);
    } catch (error) {
        console.error('Error al cargar los clientes:', error);
    }
}

// Pinta las filas de la tabla de clientes
function renderizarTablaClientes(clientes) {
    if (!Array.isArray(clientes) || clientes.length === 0) {
        cuerpoTablaClientes.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-4">No se encontraron clientes</td>
            </tr>`;
        return;
    }

    cuerpoTablaClientes.innerHTML = clientes.map(cliente => {
        //la BD devuelve 1/0 para el booleano es_cliente_fiel, lo convertimos a true/false de JS
        const esClienteFiel = Number(cliente.es_cliente_fiel) === 1 && parseFloat(cliente.total_anio) > 0;

        return `
            <tr>
                <td>${cliente.cedula}</td>
                <td>${cliente.nombre_completo}</td>
                <td>${cliente.telefono ?? '-'}</td>
                <td>${cliente.correo ?? '-'}</td>
                <td class="text-end">$${parseFloat(cliente.total_anio).toFixed(2)}</td>
                <td class="text-center"><span class="badge-puntos">${cliente.puntos_acumulados} pts</span></td>
                <td class="text-center">${esClienteFiel ? '<span class="badge-cliente-fiel">🏆 Cliente Fiel del Año</span>' : ''}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-secondary" title="Ver historial de compras"
                        onclick="verHistorialCliente('${cliente.cedula}', '${cliente.nombre_completo.replace(/'/g, "\\'")}')">
                        🧾 Historial
                    </button>
                </td>
            </tr>`;
    }).join('');
}

// al cargar la pagina, mostramos la lista completa de clientes de una vez
cargarClientes();

// ---------------------------------------------------------
// Modal: historial de facturas de un cliente especifico
// Reutilizamos el mismo endpoint que ya construimos en historial.php (backend/api_historial.php),
// filtrando por la cedula del cliente
// ---------------------------------------------------------
const modalHistorialCliente = new bootstrap.Modal(document.getElementById('modalHistorialCliente'));
const modalHistorialTitulo = document.getElementById('modalHistorialTitulo');
const modalHistorialCuerpo = document.getElementById('modalHistorialCuerpo');

// Convierte el numero de factura al formato ecuatoriano 001-001-000000001 (igual que en historial.js)
function formatearNumeroFacturaCliente(id) {
    return '001-001-' + String(id).padStart(9, '0');
}

// Convierte la fecha de MySQL a un formato legible dd/mm/yyyy hh:mm (igual que en historial.js)
function formatearFechaHoraCliente(fechaMysql) {
    const fecha = new Date(fechaMysql.replace(' ', 'T'));
    if (isNaN(fecha.getTime())) return fechaMysql;

    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const anio = fecha.getFullYear();
    const horas = String(fecha.getHours()).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');

    return `${dia}/${mes}/${anio} ${horas}:${minutos}`;
}

async function verHistorialCliente(cedula, nombre) {
    try {
        //reutilizamos api_historial.php, filtrando por la cedula de este cliente en especifico
        const respuesta = await fetch(`backend/api_historial.php?accion=listado&cliente=${encodeURIComponent(cedula)}`);
        const facturas = await respuesta.json();

        modalHistorialTitulo.textContent = `Historial de Compras — ${nombre}`;

        if (!Array.isArray(facturas) || facturas.length === 0) {
            modalHistorialCuerpo.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">Este cliente aún no tiene compras registradas</td></tr>`;
        } else {
            modalHistorialCuerpo.innerHTML = facturas.map(factura => {
                const claseBadge = factura.estado === 'Pagada' ? 'badge-pagada' : 'badge-anulada';
                return `
                    <tr>
                        <td>${formatearNumeroFacturaCliente(factura.id)}</td>
                        <td>${formatearFechaHoraCliente(factura.fecha_emision)}</td>
                        <td class="text-end">$${parseFloat(factura.total_factura).toFixed(2)}</td>
                        <td class="text-center"><span class="badge-estado ${claseBadge}">${factura.estado}</span></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-secondary" onclick="verDetalleFacturaCliente(${factura.id})">👁️</button>
                        </td>
                    </tr>`;
            }).join('');
        }

        modalHistorialCliente.show();
    } catch (error) {
        console.error('Error al cargar el historial del cliente:', error);
        alert('No se pudo cargar el historial de compras');
    }
}

// ---------------------------------------------------------
// Modal: detalle de productos de una factura (mismo endpoint accion=detalle que usa historial.php)
// ---------------------------------------------------------
const modalDetalleFacturaCliente = new bootstrap.Modal(document.getElementById('modalDetalleFacturaCliente'));
const modalDetalleFacturaClienteTitulo = document.getElementById('modalDetalleFacturaClienteTitulo');
const modalDetalleFacturaClienteCuerpo = document.getElementById('modalDetalleFacturaClienteCuerpo');

async function verDetalleFacturaCliente(ventaId) {
    try {
        const respuesta = await fetch(`backend/api_historial.php?accion=detalle&venta_id=${ventaId}`);
        const items = await respuesta.json();

        modalDetalleFacturaClienteTitulo.textContent = `Detalle de Factura ${formatearNumeroFacturaCliente(ventaId)}`;

        if (!Array.isArray(items) || items.length === 0) {
            modalDetalleFacturaClienteCuerpo.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Sin productos registrados</td></tr>`;
        } else {
            modalDetalleFacturaClienteCuerpo.innerHTML = items.map(item => {
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

        modalDetalleFacturaCliente.show();
    } catch (error) {
        console.error('Error al cargar el detalle de la factura:', error);
        alert('No se pudo cargar el detalle de la factura');
    }
}