<?php

declare(strict_types=1);

namespace KitsuneNotes\Controller;

use KitsuneNotes\Core\Request;
use KitsuneNotes\Core\Response;
use KitsuneNotes\Service\CatalogAdminService;

/**
 * Back-office: mantenimiento del catálogo de productos.
 *
 * El controlador solo coordina: la validación, la gestión de imágenes,
 * la regla de borrado y la emisión de eventos viven en
 * CatalogAdminService, de modo que se pueden explicar y probar por
 * separado de la interfaz.
 */
final class AdminProductController extends Controller
{
    /** @param array<string, string> $args */
    public function index(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $filters = [
            'q'         => $request->query('q', '') ?? '',
            'estado'    => $request->query('estado', '') ?? '',
            'categoria' => $request->query('categoria', '') ?? '',
            'coleccion' => $request->query('coleccion', '') ?? '',
        ];

        $products = $this->app->products()->adminList($filters);

        return $this->view('admin/productos', [
            'title'       => 'Productos',
            'products'    => $products,
            'filters'     => $filters,
            'categories'  => $this->app->categories()->all(),
            'designLines' => $this->app->designLines()->all(),
            'totals'      => [
                'activos'   => count(array_filter($products, static fn (array $p): bool => (int) $p['is_active'] === 1)),
                'retirados' => count(array_filter($products, static fn (array $p): bool => (int) $p['is_active'] === 0)),
            ],
        ], 'layout/admin');
    }

    /** @param array<string, string> $args */
    public function create(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        return $this->form(null, [
            'activo'  => '1',
            'origen'  => 'Japón',
            'stock'   => '0',
            'peso'    => '100',
        ], []);
    }

    /** @param array<string, string> $args */
    public function store(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        if (!$this->csrfValid($request)) {
            $this->flash('error', 'La sesión ha caducado. Vuelve a intentarlo.');

            return $this->redirect('/admin/productos/nuevo');
        }

        $result = $this->app->catalogAdmin()->create(
            $request->all(),
            $request->file('imagen_subida'),
            $this->operator()
        );

        if (!$result['ok']) {
            return $this->form(null, $request->all(), $result['errors'], 422);
        }

        $product = $result['product'];
        $this->flash('exito', '«' . $product['name'] . '» se ha dado de alta con el SKU ' . $product['sku'] . '.');

        return $this->redirect('/admin/productos/' . $product['id'] . '/editar');
    }

    /** @param array<string, string> $args */
    public function edit(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $product = $this->app->products()->findById((int) ($args['id'] ?? 0));

        if ($product === null) {
            return $this->notFound('Ese producto no existe o ya se eliminó.');
        }

        return $this->form($product, $this->inputFromProduct($product), []);
    }

    /** @param array<string, string> $args */
    public function update(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $product = $this->app->products()->findById((int) ($args['id'] ?? 0));

        if ($product === null) {
            return $this->notFound('Ese producto no existe o ya se eliminó.');
        }

        if (!$this->csrfValid($request)) {
            $this->flash('error', 'La sesión ha caducado. Vuelve a intentarlo.');

            return $this->redirect('/admin/productos/' . $product['id'] . '/editar');
        }

        $result = $this->app->catalogAdmin()->update(
            $product,
            $request->all(),
            $request->file('imagen_subida'),
            $this->operator()
        );

        if (!$result['ok']) {
            return $this->form($product, $request->all(), $result['errors'], 422);
        }

        if ($result['changes'] === []) {
            $this->flash('info', 'No había cambios que guardar.');
        } else {
            $this->flash('exito', sprintf(
                'Cambios guardados (%s). Se ha registrado el evento product.updated.',
                implode(', ', array_keys($result['changes']))
            ));
        }

        return $this->redirect('/admin/productos/' . $product['id'] . '/editar');
    }

    /** Retira el producto de la tienda (reversible). */
    public function archive(Request $request, array $args = []): Response
    {
        return $this->changeVisibility($request, $args, false);
    }

    /** Vuelve a publicar un producto retirado. */
    public function restore(Request $request, array $args = []): Response
    {
        return $this->changeVisibility($request, $args, true);
    }

    /** @param array<string, string> $args */
    public function destroy(Request $request, array $args = []): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $product = $this->app->products()->findById((int) ($args['id'] ?? 0));

        if ($product === null) {
            return $this->redirect('/admin/productos');
        }

        if (!$this->csrfValid($request)) {
            $this->flash('error', 'La sesión ha caducado. Vuelve a intentarlo.');

            return $this->redirect('/admin/productos/' . $product['id'] . '/editar');
        }

        if ($request->input('confirmar') !== '1') {
            $this->flash('aviso', 'Marca la casilla de confirmación para eliminar el producto definitivamente.');

            return $this->redirect('/admin/productos/' . $product['id'] . '/editar');
        }

        $result = $this->app->catalogAdmin()->delete($product, $this->operator());

        if (!$result['ok']) {
            $this->flash('error', 'No se puede eliminar «' . $product['name'] . '». ' . $result['reason']);

            return $this->redirect('/admin/productos/' . $product['id'] . '/editar');
        }

        $this->flash('exito', '«' . $product['name'] . '» se ha eliminado definitivamente del catálogo.');

        return $this->redirect('/admin/productos');
    }

    // -----------------------------------------------------------------

    /** @param array<string, string> $args */
    private function changeVisibility(Request $request, array $args, bool $publish): Response
    {
        if (($guard = $this->guardStaff()) !== null) {
            return $guard;
        }

        $product = $this->app->products()->findById((int) ($args['id'] ?? 0));

        if ($product === null || !$this->csrfValid($request)) {
            return $this->redirect('/admin/productos');
        }

        if ($publish) {
            $this->app->catalogAdmin()->restore($product, $this->operator());
            $this->flash('exito', '«' . $product['name'] . '» vuelve a estar a la venta.');
        } else {
            $this->app->catalogAdmin()->archive($product, $this->operator());
            $this->flash('exito', '«' . $product['name'] . '» se ha retirado del catálogo. Sus pedidos y eventos se conservan.');
        }

        $back = $request->input('volver') === 'listado'
            ? '/admin/productos'
            : '/admin/productos/' . $product['id'] . '/editar';

        return $this->redirect($back);
    }

    /**
     * @param array<string, mixed>|null   $product
     * @param array<string, mixed>        $old
     * @param array<string, list<string>> $errors
     */
    private function form(?array $product, array $old, array $errors, int $status = 200): Response
    {
        $service = $this->app->catalogAdmin();

        return $this->view('admin/producto-form', [
            'title'       => $product === null ? 'Nuevo producto' : 'Editar ' . $product['name'],
            'product'     => $product,
            'old'         => $old,
            'errors'      => $errors,
            'categories'  => $this->app->categories()->all(),
            'designLines' => $this->app->designLines()->all(),
            'origins'     => CatalogAdminService::ORIGINS,
            'images'      => $service->availableImages(),
            'deletion'    => $product !== null ? $service->deletionCheck($product) : null,
        ], 'layout/admin', $status);
    }

    /**
     * Convierte un producto en los valores del formulario.
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function inputFromProduct(array $product): array
    {
        // Ficha técnica guardada en JSON → texto «Clave: valor», una por línea.
        $specLines = static function (mixed $json): string {
            $specs = json_decode((string) $json, true);
            $lines = [];

            foreach (is_array($specs) ? $specs : [] as $key => $value) {
                $lines[] = $key . ': ' . $value;
            }

            return implode("\n", $lines);
        };

        $euros = static fn (?int $cents): string => $cents === null
            ? ''
            : number_format($cents / 100, 2, ',', '');

        $input = [
            'nombre'           => $product['name'],
            'sku'              => $product['sku'],
            'slug'             => $product['slug'],
            'categoria_id'     => (string) $product['category_id'],
            'coleccion_id'     => (string) $product['design_line_id'],
            'marca'            => $product['brand'],
            'origen'           => $product['origin'],
            'resumen'          => $product['summary'],
            'descripcion'      => $product['description'],
            'especificaciones' => $specLines($product['specs_json']),
            // Versión en inglés (puede estar vacía)
            'nombre_en'           => (string) ($product['name_en'] ?? ''),
            'resumen_en'          => (string) ($product['summary_en'] ?? ''),
            'descripcion_en'      => (string) ($product['description_en'] ?? ''),
            'especificaciones_en' => $specLines($product['specs_json_en'] ?? ''),
            'precio'           => $euros((int) $product['price_cents']),
            'precio_anterior'  => $euros($product['compare_at_cents'] !== null ? (int) $product['compare_at_cents'] : null),
            'stock'            => (string) $product['stock'],
            'peso'             => (string) $product['weight_grams'],
            'imagen_actual'    => $product['image_path'],
        ];

        if ((int) $product['is_active'] === 1) {
            $input['activo'] = '1';
        }

        if ((int) $product['is_featured'] === 1) {
            $input['destacado'] = '1';
        }

        return $input;
    }
}
