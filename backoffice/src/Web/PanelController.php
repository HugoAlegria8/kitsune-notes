<?php

declare(strict_types=1);

namespace KitsuneGestion\Web;

use KitsuneGestion\Core\Request;
use KitsuneGestion\Core\Response;

/** Cuadro de mando operativo. */
final class PanelController extends WebController
{
    public function index(Request $request, array $args): Response
    {
        if (($redireccion = $this->exigirSesion()) !== null) {
            return $redireccion;
        }

        return $this->vista('panel', [
            'titulo'   => 'Cuadro de mando',
            'metricas' => $this->app->metricas()->todas(),
        ]);
    }
}
