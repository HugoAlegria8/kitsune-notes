<?php
/**
 * Página de transparencia del prototipo.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, array<string, string>> $testCards
 * @var array<string, string>                $events
 * @var string                               $sessionCookie nombre de la cookie de sesión
 * @var string                               $noticeCookie  nombre de la cookie del aviso de cookies
 * @var int                                  $noticeDays    días que se recuerda haber leído el aviso
 */
?>
<div style="max-width:780px; margin-inline:auto">
    <h1>Aviso: esto es un prototipo académico</h1>

    <div class="alerta alerta--aviso">
        <span class="alerta__icono" aria-hidden="true"><span>!</span></span>
        <div>
            <strong>Kitsune Notes no es una tienda real.</strong>
            Es un prototipo desarrollado para la asignatura <em>Soluciones Informáticas para la
            Empresa</em> del Grado en Ingeniería Informática (UCAM). No existe actividad comercial,
            no se cobra dinero y no se envía ningún producto.
        </div>
    </div>

    <h2>Qué es ficticio</h2>
    <ul>
        <li><strong>La empresa y las marcas.</strong> Kitsune Notes y los fabricantes que aparecen en las fichas son nombres inventados.</li>
        <li><strong>Los productos y los precios.</strong> El catálogo es realista pero no corresponde a artículos en venta.</li>
        <li><strong>Los pagos.</strong> Un simulador interno decide el resultado a partir de un listado cerrado de tarjetas de prueba. No hay conexión con ninguna pasarela real.</li>
        <li><strong>Los envíos.</strong> Ningún pedido se prepara ni se entrega; los estados se cambian manualmente desde el back-office para demostrar el ciclo de vida.</li>
        <li><strong>Las facturas y los correos.</strong> La factura lleva el sello «de prueba» y un NIF inventado. Los correos se guardan en un buzón de pruebas que solo consulta el personal del back-office; por defecto no sale ninguno de la aplicación, y si el equipo activa el envío real solo se envía a las direcciones que ha autorizado expresamente.</li>
        <li><strong>Los clientes.</strong> Las cuentas de ejemplo usan el dominio reservado <code>.test</code>, que no puede recibir correo.</li>
    </ul>

    <h2>Cómo tratamos los datos</h2>
    <ul>
        <li>No pedimos ni queremos datos personales reales: los formularios están pensados para rellenarse con datos de prueba.</li>
        <li>Del número de tarjeta introducido <strong>solo se guardan los cuatro últimos dígitos</strong>. El número completo y el CVV nunca se escriben en la base de datos, en los eventos ni en los ficheros de registro.</li>
        <li>En los eventos de negocio la dirección IP se guarda <strong>seudonimizada</strong> mediante una función resumen con sal, nunca en claro.</li>
        <li>No se utilizan cookies de terceros ni herramientas de analítica externas: la instrumentación es propia y se queda en el servidor.</li>
    </ul>

    <h2 id="cookies">Cookies</h2>
    <p>
        La tienda solo usa <strong>cookies propias y técnicas</strong>, necesarias para que funcione.
        No hay cookies de publicidad, de analítica ni de terceros; por eso el aviso de la portada es
        informativo y no pide elegir nada.
    </p>
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
            <tr>
                <th scope="col">Cookie</th>
                <th scope="col">Para qué sirve</th>
                <th scope="col">Duración</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code><?= $this->e($sessionCookie) ?></code></td>
                <td>
                    Mantiene tu sesión: el carrito, los mensajes entre pantallas y el código que protege
                    los formularios. La cookie solo contiene un identificador aleatorio; los datos se
                    guardan en el servidor.
                </td>
                <td>Hasta que cierres el navegador</td>
            </tr>
            <tr>
                <td><code><?= $this->e($noticeCookie) ?></code></td>
                <td>Recuerda que has cerrado el aviso de cookies de la portada.</td>
                <td><?= (int) $noticeDays ?> días</td>
            </tr>
            </tbody>
        </table>
    </div>
    <p>
        Puedes borrarlas cuando quieras desde los ajustes de tu navegador. La tienda seguirá
        funcionando, pero el carrito empezará de cero y el aviso de la portada volverá a aparecer.
    </p>

    <h2>Tarjetas de prueba</h2>
    <p>Si quieres recorrer el flujo de compra, usa una de estas tarjetas ficticias:</p>
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
            <tr>
                <th scope="col">Número</th>
                <th scope="col">Resultado</th>
                <th scope="col">Descripción</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($testCards as $number => $card): ?>
                <tr>
                    <td><code><?= $this->e($number) ?></code></td>
                    <td>
                        <span class="insignia insignia--<?= $card['status'] === 'autorizado' ? 'success' : 'error' ?>">
                            <?= $this->e($card['status']) ?>
                        </span>
                    </td>
                    <td><?= $this->e($card['description']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Eventos que registra la tienda</h2>
    <p>
        Cada acción relevante del proceso de compra emite un evento de negocio. Estos eventos son
        la materia prima con la que otros sistemas de la organización podrán trabajar más adelante.
    </p>
    <div class="tabla-envoltorio">
        <table class="tabla">
            <thead>
            <tr>
                <th scope="col">Evento</th>
                <th scope="col">Cuándo se emite</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($events as $name => $description): ?>
                <tr>
                    <td><span class="evento-nombre"><?= $this->e($name) ?></span></td>
                    <td><?= $this->e($description) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Uso de imágenes y personajes</h2>
    <p>
        Las ilustraciones de producto y los cuatro personajes de las colecciones (Kitsune, Neko,
        Tokki y Gom) son vectores originales generados por un script del propio proyecto
        (<code>tools/generar_ilustraciones.py</code>). No se utilizan fotografías, logotipos ni
        personajes de marcas reales.
    </p>
</div>
