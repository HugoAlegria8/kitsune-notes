<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Service\EventRecorder;

/**
 * Catálogo: listado filtrable, categorías, colecciones y ficha de producto.
 *
 * Las «colecciones» son las líneas de diseño del enunciado: un eje
 * transversal a la categoría, representado por un personaje.
 */
final class CatalogController extends Controller
{
    /** @param array<string, string> $args */
    public function index(Request $request, array $args = []): Response
    {
        $filters = [
            'categoria' => $request->query('categoria', '') ?? '',
            'coleccion' => $request->query('coleccion', '') ?? '',
            'q'         => $request->query('q', '') ?? '',
            'orden'     => $request->query('orden', '') ?? '',
        ];

        return $this->view('catalog/index', [
            'title'       => $this->catalogTitle($filters),
            'products'    => $this->app->products()->search($filters),
            'filters'     => $filters,
            'categories'  => $this->app->categories()->all(),
            'designLines' => $this->app->designLines()->all(),
        ]);
    }

    /** @param array<string, string> $args */
    public function category(Request $request, array $args = []): Response
    {
        $category = $this->app->categories()->findBySlug($args['slug'] ?? '');

        if ($category === null) {
            return $this->notFound($this->t('Esa categoría no existe en el catálogo.'));
        }

        return $this->view('catalog/index', [
            'title'       => (string) $category['name'],
            'heading'     => (string) $category['name'],
            'intro'       => (string) $category['description'],
            'products'    => $this->app->products()->search(['categoria' => $category['slug']]),
            'filters'     => ['categoria' => (string) $category['slug'], 'coleccion' => '', 'q' => '', 'orden' => ''],
            'categories'  => $this->app->categories()->all(),
            'designLines' => $this->app->designLines()->all(),
        ]);
    }

    /** @param array<string, string> $args */
    public function designLine(Request $request, array $args = []): Response
    {
        $line = $this->app->designLines()->findBySlug($args['slug'] ?? '');

        if ($line === null) {
            return $this->notFound($this->t('Esa colección no existe.'));
        }

        return $this->view('catalog/index', [
            'title'       => $this->t('Colección {nombre}', ['nombre' => $line['name']]),
            'heading'     => $this->t('Colección {nombre}', ['nombre' => $line['name']]),
            'intro'       => (string) $line['description'],
            'collection'  => $line,
            'products'    => $this->app->products()->search(['coleccion' => $line['slug']]),
            'filters'     => ['categoria' => '', 'coleccion' => (string) $line['slug'], 'q' => '', 'orden' => ''],
            'categories'  => $this->app->categories()->all(),
            'designLines' => $this->app->designLines()->all(),
        ]);
    }

    /** @param array<string, string> $args */
    public function product(Request $request, array $args = []): Response
    {
        $product = $this->app->products()->findBySlug($args['slug'] ?? '');

        if ($product === null) {
            return $this->notFound($this->t('Ese producto ya no está disponible.'));
        }

        // ---- Instrumentación: product.viewed --------------------------
        $this->app->events()->record(
            EventRecorder::PRODUCT_VIEWED,
            [
                'sku'          => $product['sku'],
                'nombre'       => $product['name'],
                'categoria'    => $product['category_slug'],
                'coleccion'    => $product['design_line_slug'],
                // Precio tal como lo ve el cliente (en la moneda de su idioma)
                // y su original en euros, para poder comparar entre monedas.
                'precio_cents' => (int) $product['price_cents'],
                'moneda'       => (string) $product['currency'],
                'precio_eur_cents' => (int) $product['price_base_cents'],
                'idioma'       => $this->app->translator()->locale(),
                'stock'        => (int) $product['stock'],
                'origen'       => $request->query('origen', 'directo'),
            ],
            ['product_id' => (int) $product['id']]
        );

        $specs = json_decode((string) $product['specs_json'], true);

        return $this->view('catalog/product', [
            'title'    => (string) $product['name'],
            'product'  => $product,
            'specs'    => is_array($specs) ? $specs : [],
            'related'  => $this->app->products()->relatedTo($product, 3),
            'maxUnits' => min($this->app->cart()->maxUnitsPerLine(), max(1, (int) $product['stock'])),
        ]);
    }

    /** @param array<string, string> $filters */
    private function catalogTitle(array $filters): string
    {
        if (($filters['q'] ?? '') !== '') {
            return $this->t('Resultados para «{busqueda}»', ['busqueda' => $filters['q']]);
        }

        return $this->t('Todo el catálogo');
    }
}
