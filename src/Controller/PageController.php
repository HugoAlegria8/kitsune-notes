<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Service\EventRecorder;
use KitsuneNotes\Service\PaymentSimulator;

final class PageController extends Controller
{
    /** @param array<string, string> $args */
    public function about(Request $request, array $args = []): Response
    {
        return $this->view('page/sobre', [
            'title'       => 'Sobre Kitsune Notes',
            'designLines' => $this->app->designLines()->all(),
        ]);
    }

    /**
     * Las cuatro colecciones (líneas de diseño) con sus personajes.
     *
     * @param array<string, string> $args
     */
    public function collections(Request $request, array $args = []): Response
    {
        return $this->view('page/colecciones', [
            'title'       => 'Colecciones',
            'designLines' => $this->app->designLines()->all(),
        ]);
    }

    /**
     * Página de transparencia del prototipo: explica que la tienda es un
     * trabajo académico, qué datos se tratan y cómo se simula el pago.
     *
     * @param array<string, string> $args
     */
    public function academic(Request $request, array $args = []): Response
    {
        return $this->view('page/aviso-academico', [
            'title'         => 'Aviso: prototipo académico',
            'testCards'     => PaymentSimulator::testCards(),
            'events'        => EventRecorder::CATALOG,
            'sessionCookie' => session_name(),
            'noticeCookie'  => (string) $this->app->config('privacy.cookie_notice_name'),
            'noticeDays'    => (int) $this->app->config('privacy.cookie_notice_days', 180),
        ]);
    }

    /**
     * Botón «Entendido» del aviso de cookies cuando el navegador no ejecuta
     * JavaScript (con JavaScript se guarda sin recargar, desde kitsune.js):
     * anota en una cookie que el aviso ya se ha leído y vuelve a la portada.
     * Con un token CSRF no válido no guarda nada y el aviso seguirá visible.
     *
     * @param array<string, string> $args
     */
    public function acknowledgeCookies(Request $request, array $args = []): Response
    {
        $response = $this->redirect('/');

        if (!$this->csrfValid($request)) {
            return $response;
        }

        $days = max(1, (int) $this->app->config('privacy.cookie_notice_days', 180));

        return $response->withCookie((string) $this->app->config('privacy.cookie_notice_name'), '1', [
            'expires'  => time() + $days * 86400,
            'path'     => '/',
            'secure'   => $request->isSecure(),
            'httponly' => false,   // el script del aviso también la lee
            'samesite' => 'Lax',
        ]);
    }

    /** @param array<string, string> $args */
    public function shipping(Request $request, array $args = []): Response
    {
        return $this->view('page/envios', [
            'title'           => 'Envíos y devoluciones (simulado)',
            'shippingMethods' => $this->app->pricing()->shippingMethods(),
            'giftwrapCents'   => $this->app->pricing()->giftwrapCents(),
            'coupons'         => $this->app->pricing()->activeCoupons(),
        ]);
    }
}
