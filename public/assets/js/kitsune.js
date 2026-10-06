/**
 * Kitsune Notes · mejoras progresivas del lado del cliente.
 *
 * Criterio: la tienda funciona completamente sin JavaScript. Este
 * fichero solo añade comodidades; ninguna regla de negocio ni ningún
 * cálculo de importe se ejecuta en el navegador, porque el cliente no
 * es una fuente de datos fiable.
 */
(function () {
    'use strict';

    /** Agrupa el número de tarjeta de prueba en bloques de cuatro dígitos. */
    function formatearTarjeta(input) {
        input.addEventListener('input', function () {
            var digitos = input.value.replace(/\D/g, '').slice(0, 19);
            var bloques = digitos.match(/.{1,4}/g);

            input.value = bloques ? bloques.join(' ') : '';
        });
    }

    /** Inserta la barra de la fecha de caducidad (MM/AA). */
    function formatearCaducidad(input) {
        input.addEventListener('input', function () {
            var digitos = input.value.replace(/\D/g, '').slice(0, 4);

            input.value = digitos.length > 2
                ? digitos.slice(0, 2) + '/' + digitos.slice(2)
                : digitos;
        });
    }

    document.querySelectorAll('[data-formato="tarjeta"]').forEach(formatearTarjeta);
    document.querySelectorAll('[data-formato="caducidad"]').forEach(formatearCaducidad);

    /**
     * Evita envíos duplicados del formulario de pago: el pedido se
     * generaría dos veces si el usuario pulsa el botón repetidamente.
     */
    document.querySelectorAll('form[action$="/pago"]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var boton = form.querySelector('button[type="submit"]');

            if (boton) {
                boton.setAttribute('aria-disabled', 'true');
                boton.textContent = 'Procesando el pago simulado…';
                window.setTimeout(function () {
                    boton.disabled = true;
                }, 0);
            }
        });
    });

    /** Factura: el botón abre el cuadro de impresión (permite «Guardar como PDF»). */
    document.querySelectorAll('[data-imprimir]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            window.print();
        });
    });

    /**
     * Back-office: vista previa de la imagen del producto al elegir una
     * ilustración o al seleccionar un fichero para subir.
     */
    var vista = document.getElementById('vista-imagen');

    if (vista) {
        document.querySelectorAll('input[data-vista]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (radio.checked) {
                    vista.src = radio.getAttribute('data-vista');
                }
            });
        });

        var subida = document.getElementById('imagen_subida');

        if (subida && window.URL && URL.createObjectURL) {
            subida.addEventListener('change', function () {
                if (subida.files && subida.files[0]) {
                    vista.src = URL.createObjectURL(subida.files[0]);
                }
            });
        }
    }

    /**
     * Aviso de cookies de la portada. Sin JavaScript, el botón «Entendido» envía
     * un formulario y es el servidor quien guarda la cookie; con JavaScript se
     * guarda la misma cookie sin recargar y el aviso se cierra al momento.
     *
     * La tienda solo usa cookies técnicas, así que no hay nada que aceptar o
     * rechazar: el aviso es informativo.
     */
    document.querySelectorAll('[data-aviso-cookies]').forEach(function (aviso) {
        var nombre = aviso.getAttribute('data-cookie');
        var dias = parseInt(aviso.getAttribute('data-dias'), 10) || 180;
        var boton = aviso.querySelector('[data-cookies-cerrar]');

        function hayCookie() {
            return document.cookie.split('; ').indexOf(nombre + '=1') !== -1;
        }

        // Copia de reserva para cuando el navegador no admite cookies (por ejemplo,
        // al abrir la vista previa estática desde un fichero local).
        function hayCopia() {
            try {
                return window.localStorage.getItem(nombre) === '1';
            } catch (error) {
                return false;
            }
        }

        if (!nombre || !boton) {
            return;
        }

        if (hayCookie() || hayCopia()) {
            aviso.parentNode.removeChild(aviso);

            return;
        }

        boton.addEventListener('click', function (evento) {
            evento.preventDefault();

            var seguro = window.location.protocol === 'https:' ? '; Secure' : '';

            document.cookie = nombre + '=1; Max-Age=' + (dias * 86400) + '; Path=/; SameSite=Lax' + seguro;

            if (!hayCookie()) {
                try {
                    window.localStorage.setItem(nombre, '1');
                } catch (error) {
                    /* sin almacenamiento: el aviso volverá en la próxima visita */
                }
            }

            // El botón va a desaparecer: el foco pasa al bloque que viene justo después
            // (el pie), de modo que quien navega con teclado sigue desde donde estaba.
            var destino = aviso.nextElementSibling;

            if (!destino || destino.tagName === 'SCRIPT') {
                destino = document.getElementById('contenido');
            }

            if (destino) {
                destino.setAttribute('tabindex', '-1');
                destino.focus({ preventScroll: true });
            }

            aviso.classList.add('aviso-cookies--cerrando');
            window.setTimeout(function () {
                if (aviso.parentNode) {
                    aviso.parentNode.removeChild(aviso);
                }
            }, 220);
        });
    });
}());
