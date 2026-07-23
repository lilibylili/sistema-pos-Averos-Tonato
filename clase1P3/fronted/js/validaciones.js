// Dejar solo digitos en un texto (para los inputs de cedula y telefono, mientras el usuario escribe)
function soloNumeros(texto) {
    return texto.replace(/[^0-9]/g, '');
}

// Dejar solo letras y espacios (incluyendo tildes y la ñ) en un texto (para el input de nombre)
function soloLetras(texto) {
    return texto.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
}

// Valida que una cedula ecuatoriana sea valida, usando el algoritmo oficial del digito verificador
function validarCedulaEcuador(cedula) {
    //debe tener exactamente 10 digitos
    if (!/^\d{10}$/.test(cedula)) return false;

    const digitos = cedula.split('').map(Number);

    //los primeros 2 digitos son el codigo de provincia, debe estar entre 01 y 24
    const provincia = parseInt(cedula.substring(0, 2), 10);
    if (provincia < 1 || provincia > 24) return false;

    //el tercer digito debe ser menor a 6 para personas naturales
    if (digitos[2] > 5) return false;

    //algoritmo del digito verificador (modulo 10):
    //se multiplican los primeros 9 digitos alternando por 2 y 1, y si el resultado es 10 o mas se le resta 9
    const coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
    let suma = 0;
    for (let i = 0; i < 9; i++) {
        let valor = digitos[i] * coeficientes[i];
        if (valor >= 10) valor -= 9;
        suma += valor;
    }

    //la decena superior menos la suma nos da el digito verificador esperado
    const decena = Math.ceil(suma / 10) * 10;
    let digitoVerificador = decena - suma;
    if (digitoVerificador === 10) digitoVerificador = 0;

    //comparamos con el ultimo digito real de la cedula (posicion 9)
    return digitoVerificador === digitos[9];
}

// Valida que un telefono cumpla el formato ecuatoriano:
// celular: 09 + 8 digitos (10 digitos en total, ej. 0991234567)
// convencional: 0 + codigo de provincia (2 al 7) + 7 digitos (9 digitos en total, ej. 022345678)
function validarTelefonoEcuador(telefono) {
    const esCelular = /^09\d{8}$/.test(telefono);
    const esConvencional = /^0[2-7]\d{7}$/.test(telefono);
    return esCelular || esConvencional;
}