<?php
declare(strict_types=1);

//validaciones.php - Reglas de validacion para datos ecuatorianos (cedula y telefono)
//Es el equivalente en PHP de fronted/js/validaciones.js
//IMPORTANTE: nunca confiamos solo en la validacion de JavaScript (el usuario podria desactivarla o mandar
//la peticion directamente), por eso se vuelve a validar aqui en el servidor antes de guardar en la BD

//Valida que una cedula ecuatoriana sea valida, usando el algoritmo oficial del digito verificador
function validarCedulaEcuador(string $cedula): bool
{
    //debe tener exactamente 10 digitos
    if (!preg_match('/^\d{10}$/', $cedula)) {
        return false;
    }

    $digitos = array_map('intval', str_split($cedula));

    //los primeros 2 digitos son el codigo de provincia, debe estar entre 01 y 24
    $provincia = (int)substr($cedula, 0, 2);
    if ($provincia < 1 || $provincia > 24) {
        return false;
    }

    //el tercer digito debe ser menor a 6 para personas naturales
    if ($digitos[2] > 5) {
        return false;
    }

    //algoritmo del digito verificador (modulo 10)
    $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
    $suma = 0;
    for ($i = 0; $i < 9; $i++) {
        $valor = $digitos[$i] * $coeficientes[$i];
        if ($valor >= 10) {
            $valor -= 9;
        }
        $suma += $valor;
    }

    $decena = ceil($suma / 10) * 10;
    $digitoVerificador = (int)($decena - $suma);
    if ($digitoVerificador === 10) {
        $digitoVerificador = 0;
    }

    return $digitoVerificador === $digitos[9];
}

//Valida que un telefono cumpla el formato ecuatoriano (celular 10 digitos empezando en 09, o convencional 9 digitos)
function validarTelefonoEcuador(string $telefono): bool
{
    $esCelular = preg_match('/^09\d{8}$/', $telefono);
    $esConvencional = preg_match('/^0[2-7]\d{7}$/', $telefono);
    return (bool)($esCelular || $esConvencional);
}

//Valida que un nombre solo contenga letras y espacios (nada de numeros ni caracteres especiales)
function validarSoloLetras(string $texto): bool
{
    return (bool)preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', trim($texto)) && trim($texto) !== '';
}