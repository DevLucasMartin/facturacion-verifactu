/**
 * FacturacionCalculos - Motor de cálculo fiscal español
 * Compatible con IVA, RE (Recargo de Equivalencia) y descuentos en cascada.
 */
const FacturacionCalculos = (() => {

    /**
     * Calcula los totales de una factura.
     *
     * @param {Array}  lineas      Cada elemento: {cantidad, precio, descuento, tipoIVA: {iva, re}}
     * @param {Object} descuentos  {especial, comercial, pp, tipoDocumento}
     * @param {number} rePorcentaje  % RE del cliente (0 si no aplica)
     * @returns {Object}
     */
    function calcularFactura(lineas, descuentos = {}, rePorcentaje = 0) {
        const errores = [];

        // 1. Subtotal bruto (sin ningún descuento)
        let subtotal = 0;
        let importeDtoLineas = 0;

        const grupos = {}; // agrupado por código de tipo IVA

        for (const l of lineas) {
            const cantidad  = parseFloat(l.cantidad)  || 0;
            const precio    = parseFloat(l.precio)    || 0;
            const dto       = parseFloat(l.descuento) || 0;
            const ivaRate   = parseFloat(l.tipoIVA?.iva ?? 21);
            const reRate    = parseFloat(l.tipoIVA?.re  ?? 0);
            const codigoIVA = l.tipoIVA?.codigo || 'GEN';

            const importeBruto = cantidad * precio;
            const importeDtoLin = importeBruto * (dto / 100);
            const baseLinea    = importeBruto - importeDtoLin;

            subtotal        += importeBruto;
            importeDtoLineas += importeDtoLin;

            if (!grupos[codigoIVA]) {
                grupos[codigoIVA] = { base: 0, iva: ivaRate, re: reRate };
            }
            grupos[codigoIVA].base += baseLinea;
        }

        // 2. Base antes de descuentos globales
        const baseTrasDtoLineas = subtotal - importeDtoLineas;

        // 3. Descuentos globales en cascada
        const dtoEsp  = parseFloat(descuentos.especial  || 0);
        const dtoCom  = parseFloat(descuentos.comercial || 0);
        const dtoPP   = parseFloat(descuentos.pp        || 0);

        const factorGlobal = (1 - dtoEsp / 100) * (1 - dtoCom / 100) * (1 - dtoPP / 100);

        const importeDtoEspecial  = baseTrasDtoLineas * (dtoEsp / 100);
        const baseTrasDtoEsp      = baseTrasDtoLineas - importeDtoEspecial;
        const importeDtoComercial = baseTrasDtoEsp   * (dtoCom / 100);
        const baseTrasDtoCom      = baseTrasDtoEsp   - importeDtoComercial;
        const importeDtoPP        = baseTrasDtoCom   * (dtoPP  / 100);

        // 4. Aplicar factor global a cada grupo IVA
        let baseImponible = 0;
        let importeIVA    = 0;
        let importeRE     = 0;

        for (const g of Object.values(grupos)) {
            const baseGrupo = g.base * factorGlobal;
            baseImponible  += baseGrupo;
            importeIVA     += baseGrupo * (g.iva / 100);
            if (rePorcentaje > 0) {
                importeRE  += baseGrupo * (rePorcentaje / 100);
            } else {
                importeRE  += baseGrupo * (g.re / 100);
            }
        }

        // 5. Validaciones básicas
        if (descuentos.tipoDocumento === 'SIMPLIFICADA' && baseImponible > 400) {
            // Aviso (no bloquea, el JS lo controla según destinatario)
        }

        const total = round2(baseImponible + importeIVA + importeRE);

        return {
            subtotal:    round2(subtotal),
            baseImponible: round2(baseImponible),
            importeIVA:  round2(importeIVA),
            importeRE:   round2(importeRE),
            total,
            errores,
            descuentos: {
                importeDtoLineas:   round2(importeDtoLineas),
                importeDtoEspecial: round2(importeDtoEspecial),
                importeDtoComercial:round2(importeDtoComercial),
                importeDtoPP:       round2(importeDtoPP),
            },
        };
    }

    function round2(v) {
        return Math.round((parseFloat(v) || 0) * 100) / 100;
    }

    return { calcularFactura };

})();
