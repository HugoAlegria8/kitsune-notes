<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;

final class HomeController extends Controller
{
    /** @param array<string, string> $args */
    public function index(Request $request, array $args = []): Response
    {
        $cookieName = (string) $this->app->config('privacy.cookie_notice_name');

        return $this->view('catalog/home', [
            'title'        => $this->t('Papelería kawaii de Japón y Corea'),
            'featured'     => $this->app->products()->featured(4),
            'categories'   => $this->app->categories()->all(),
            'designLines'  => $this->app->designLines()->all(),
            // El aviso de cookies se muestra hasta que el visitante lo cierra.
            'cookieNotice' => $request->cookie($cookieName) === '1' ? null : [
                'name' => $cookieName,
                'days' => (int) $this->app->config('privacy.cookie_notice_days', 180),
            ],
        ]);
    }
}
