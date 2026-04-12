/**
 * Archivo de cálculo de facturas en cliente
 * Módulo de Facturación - Versión 3.2
 *
 * Este archivo contiene las funciones de cálculo que se ejecutan
 * en el navegador para proporcionar feedback instantáneo al usuario.
 */

const FacturacionCalculos = (function() {
    'use strict';

    // Configuración
    const LIMITE_SIMPLIFICADA   = 400;
    const LIMITE_RECAPITULATIVA = 3000;

    /** Redondear a 2 decimales */
    function round2(num) {
        return Math.round(num * 100) / 100;
    }

    /** Calcular importe bruto de una línea */
    function calcularImporteBruto(cantidad, precio) {
        return round2(cantidad * precio);
    }

    /** Calcular descuento de una línea */
    function calcularDescuentoLinea(importeBruto, porcentajeDescuento) {
        return round2(importeBruto * (porcentajeDescuento / 100));
    }

    /** Calcular base imponible de una línea */
    function calcularBaseImponible(importeBruto, descuentoLinea) {
        return round2(importeBruto - descuentoLinea);
    }

    /** Calcular cuota de IVA */
    function calcularCuotaIVA(baseImponible, porcentajeIVA) {
        return round2(baseImponible * (porcentajeIVA / 100));
    }

    /** Calcular cuota de Recargo de Equivalencia */
    function calcularCuotaRE(baseImponible, porcentajeRE) {
        if (porcentajeRE <= 0) return 0;
        return round2(baseImponible * (porcentajeRE / 100));
    }

    /** Calcular totales de una línea individual */
    function calcularLinea(cantidad, precio, descuentoLinea, tipoIVA, porcentajeRE) {
        const importeBruto  = calcularImporteBruto(cantidad, precio);
        const descuento     = calcularDescuentoLinea(importeBruto, descuentoLinea);
        const baseImponible = calcularBaseImponible(importeBruto, descuento);
        const cuotaIVA      = calcularCuotaIVA(baseImponible, tipoIVA.iva);
        const cuotaRE       = calcularCuotaRE(baseImponible, porcentajeRE);

        return {
            importeBruto,
            descuento,
            baseImponible,
            cuotaIVA,
            cuotaRE,
            total: round2(baseImponible + cuotaIVA + cuotaRE)
        };
    }

    /** Calcular descuentos generales en cascada */
    function calcularDescuentosGenerales(subtotal, dtoEspecial, dtoComercial, dtoPP) {
        const importeDtoEspecial  = round2(subtotal * (dtoEspecial / 100));
        const trasEspecial        = subtotal - importeDtoEspecial;

        const importeDtoComercial = round2(trasEspecial * (dtoComercial / 100));
        const trasComercial       = trasEspecial - importeDtoComercial;

        const importeDtoPP        = round2(trasComercial * (dtoPP / 100));
        const baseGlobal          = trasComercial - importeDtoPP;

        const factor = subtotal > 0 ? baseGlobal / subtotal : 0;

        return { importeDtoEspecial, importeDtoComercial, importeDtoPP, baseGlobal, factor };
    }

    /** Calcular totales de factura */
    function calcularFactura(lineas, descuentos, porcentajeRE) {
        // 1. Subtotal bruto y neto (tras descuento de línea)
        let subtotal    = 0;
        let netSubtotal = 0;

        // Si el cliente tiene RE propio, tiene prioridad sobre el del tipo IVA
        const reCliente = Number(porcentajeRE) || 0;

        const lineasCalculadas = lineas.map(linea => {
            const tipoIVASafe = linea.tipoIVA ?? { codigo: 'EX', iva: 0, re: 0 };
            const reEfectivo  = reCliente > 0 ? reCliente : Number(tipoIVASafe.re || 0);
            const resultado   = calcularLinea(
                linea.cantidad,
                linea.precio,
                linea.descuento,
                tipoIVASafe,
                reEfectivo
            );
            subtotal    += resultado.importeBruto;
            netSubtotal += resultado.baseImponible;
            return { ...linea, ...resultado, tipoIVA: { ...tipoIVASafe, re: reEfectivo } };
        });

        // 2. Descuentos generales sobre el neto
        const {
            importeDtoEspecial,
            importeDtoComercial,
            importeDtoPP,
            baseGlobal,
            factor
        } = calcularDescuentosGenerales(
            netSubtotal,
            descuentos.especial,
            descuentos.comercial,
            descuentos.pp
        );

        // 3. Agrupar por tipo de IVA + RE y aplicar factor
        const basesAgrupadas = {};
        lineasCalculadas.forEach(linea => {
            if (!linea.tipoIVA || linea.tipoIVA.iva === 0) return;

            const reEfectivo = Number(linea.tipoIVA.re || 0);
            const key        = linea.tipoIVA.codigo + '_' + reEfectivo;

            if (!basesAgrupadas[key]) {
                basesAgrupadas[key] = { tipoIVA: linea.tipoIVA, reEfectivo, base: 0, cuotaIVA: 0, cuotaRE: 0 };
            }
            basesAgrupadas[key].base += linea.baseImponible * factor;
        });

        // 4. Calcular cuotas por grupo
        const cuotasIVA = [];
        let totalIVA    = 0;
        let totalRE     = 0;

        Object.keys(basesAgrupadas).forEach(key => {
            const grupo    = basesAgrupadas[key];
            const cuotaIVA = calcularCuotaIVA(grupo.base, grupo.tipoIVA.iva);
            const cuotaRE  = calcularCuotaRE(grupo.base, grupo.reEfectivo);

            grupo.cuotaIVA = cuotaIVA;
            grupo.cuotaRE  = cuotaRE;
            totalIVA      += cuotaIVA;
            totalRE       += cuotaRE;

            cuotasIVA.push({
                codigo:        key,
                tipoIVA:       grupo.tipoIVA.codigo,
                baseImponible: round2(grupo.base),
                porcentajeIVA: grupo.tipoIVA.iva,
                cuotaIVA:      round2(cuotaIVA),
                porcentajeRE:  grupo.reEfectivo,
                cuotaRE:       round2(cuotaRE)
            });
        });

        // 5. Totales finales
        const baseImponible = round2(baseGlobal);
        const total         = round2(baseImponible + totalIVA + totalRE);

        // 6. Validaciones
        const errores = [];
        const tipoDoc = descuentos.tipoDocumento || 'FACTURA';

        if (tipoDoc === 'RECAPITULATIVA' && total > LIMITE_RECAPITULATIVA) {
            errores.push(`El total de una factura recapitulativa no puede superar ${LIMITE_RECAPITULATIVA}€`);
        }

        return {
            lineas: lineasCalculadas,
            subtotal:    round2(subtotal),
            netSubtotal: round2(netSubtotal),
            descuentos: {
                especial:           descuentos.especial,
                importeDtoEspecial,
                importeDtoLineas:   round2(subtotal - netSubtotal),
                comercial:          descuentos.comercial,
                importeDtoComercial,
                pp:                 descuentos.pp,
                importeDtoPP
            },
            baseImponible,
            cuotasIVA,
            importeIVA: round2(totalIVA),
            importeRE:  round2(totalRE),
            total,
            errores,
            reAplica: Object.values(basesAgrupadas).some(g => g.reEfectivo > 0)
        };
    }

    /** Formatear importe para visualización */
    function formatearImporte(importe) {
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'EUR'
        }).format(importe);
    }

    /** Validar línea de factura */
    function validarLinea(linea) {
        const errores = [];
        if (!linea.cantidad || linea.cantidad <= 0) errores.push('La cantidad debe ser mayor que 0');
        if (linea.precio < 0)                       errores.push('El precio no puede ser negativo');
        if (!linea.idArticulo)                       errores.push('El artículo es obligatorio');
        if (!linea.tipoIVA || !linea.tipoIVA.codigo) errores.push('El tipo de IVA es obligatorio');
        return errores;
    }

    return {
        calcularLinea,
        calcularFactura,
        formatearImporte,
        validarLinea,
        LIMITE_SIMPLIFICADA,
        LIMITE_RECAPITULATIVA
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = FacturacionCalculos;
}
