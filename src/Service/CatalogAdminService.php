<?php

declare(strict_types=1);

namespace KitsuneNotes\Service;

use KitsuneNotes\Core\Validator;
use KitsuneNotes\Repository\CategoryRepository;
use KitsuneNotes\Repository\DesignLineRepository;
use KitsuneNotes\Repository\ProductRepository;
use RuntimeException;

/**
 * Mantenimiento del catálogo desde el back-office.
 *
 * Reglas de negocio que aplica:
 *   * SKU y slug únicos; si se dejan vacíos se generan solos.
 *   * El precio anterior (tachado) debe ser mayor que el precio actual.
 *   * Imagen: una ilustración existente o una subida PNG, JPEG o WebP de
 *     hasta 2 MB, verificada por contenido y guardada con nombre aleatorio.
 *   * Borrado: solo se elimina físicamente un producto que nunca se ha
 *     vendido. Si aparece en algún pedido se retira del catálogo, porque
 *     las líneas de pedido lo referencian y el histórico debe conservarse.
 *   * Cada operación emite un evento product.* con el operador y, en las
 *     modificaciones, el detalle de los campos cambiados.
 */
final class CatalogAdminService
{
    public const ORIGINS = ['Japón', 'Corea del Sur'];

    private const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;

    private const ALLOWED_MIME = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /** Prefijo de SKU por categoría. */
    private const SKU_PREFIX = [
        'cuadernos'         => 'KN-CUA',
        'escritura'         => 'KN-ESC',
        'washi-y-pegatinas' => 'KN-WAS',
        'organizacion'      => 'KN-ORG',
    ];

    /** Campos cuyo cambio se detalla en el evento product.updated. */
    private const TRACKED = [
        'name' => 'nombre', 'sku' => 'sku', 'slug' => 'slug', 'category_id' => 'categoria',
        'design_line_id' => 'coleccion', 'brand' => 'marca', 'origin' => 'origen',
        'summary' => 'resumen', 'description' => 'descripcion', 'specs_json' => 'especificaciones',
        'price_cents' => 'precio_cents', 'compare_at_cents' => 'precio_anterior_cents',
        'stock' => 'stock', 'weight_grams' => 'peso_gramos', 'image_path' => 'imagen',
        'is_active' => 'activo', 'is_featured' => 'destacado',
    ];

    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly DesignLineRepository $designLines,
        private readonly EventRecorder $events,
        private readonly string $publicDir,
    ) {
    }

    // -----------------------------------------------------------------
    // Operaciones
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $upload
     *
     * @return array{ok:bool, errors:array<string, list<string>>, product:?array<string, mixed>}
     */
    public function create(array $input, ?array $upload, string $operator): array
    {
        $result = $this->validate($input, $upload, null);

        if ($result['errors'] !== []) {
            return ['ok' => false, 'errors' => $result['errors'], 'product' => null];
        }

        $data = $result['data'];
        $data['image_path'] = $this->resolveImage($input, $upload, null, (int) $data['design_line_id']);

        $id      = $this->products->insert($data);
        $product = $this->products->findById($id);

        $this->events->record(EventRecorder::PRODUCT_CREATED, [
            'sku'          => $data['sku'],
            'nombre'       => $data['name'],
            'categoria'    => $product['category_slug'] ?? null,
            'coleccion'    => $product['design_line_slug'] ?? null,
            'precio_cents' => (int) $data['price_cents'],
            'stock'        => (int) $data['stock'],
            'activo'       => (bool) $data['is_active'],
            'operador'     => $operator,
        ], ['product_id' => $id, 'actor_type' => 'personal']);

        return ['ok' => true, 'errors' => [], 'product' => $product];
    }

    /**
     * @param array<string, mixed>      $product producto actual
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $upload
     *
     * @return array{ok:bool, errors:array<string, list<string>>, changes:array<string, array{antes:mixed, despues:mixed}>}
     */
    public function update(array $product, array $input, ?array $upload, string $operator): array
    {
        $id     = (int) $product['id'];
        $result = $this->validate($input, $upload, $id);

        if ($result['errors'] !== []) {
            return ['ok' => false, 'errors' => $result['errors'], 'changes' => []];
        }

        $data = $result['data'];
        $data['image_path'] = $this->resolveImage($input, $upload, (string) $product['image_path'], (int) $data['design_line_id']);

        $changes = [];
        foreach (self::TRACKED as $column => $label) {
            $before = $product[$column] ?? null;
            $after  = $data[$column] ?? null;

            if ((string) $before !== (string) $after) {
                $changes[$label] = ['antes' => $before, 'despues' => $after];
            }
        }

        if ($changes === []) {
            return ['ok' => true, 'errors' => [], 'changes' => []];
        }

        $this->products->update($id, $data);
        $this->removeOrphanUpload((string) $product['image_path'], (string) $data['image_path']);

        $this->events->record(EventRecorder::PRODUCT_UPDATED, [
            'sku'      => $data['sku'],
            'nombre'   => $data['name'],
            'cambios'  => $changes,
            'operador' => $operator,
        ], ['product_id' => $id, 'actor_type' => 'personal']);

        return ['ok' => true, 'errors' => [], 'changes' => $changes];
    }

    /** Retira el producto de la tienda sin borrarlo. */
    public function archive(array $product, string $operator): void
    {
        $this->products->update((int) $product['id'], ['is_active' => 0]);

        $this->events->record(EventRecorder::PRODUCT_ARCHIVED, [
            'sku'      => $product['sku'],
            'nombre'   => $product['name'],
            'ventas'   => $this->products->salesLineCount((int) $product['id']),
            'operador' => $operator,
        ], ['product_id' => (int) $product['id'], 'actor_type' => 'personal']);
    }

    /** Vuelve a publicar un producto retirado. */
    public function restore(array $product, string $operator): void
    {
        $this->products->update((int) $product['id'], ['is_active' => 1]);

        $this->events->record(EventRecorder::PRODUCT_RESTORED, [
            'sku'      => $product['sku'],
            'nombre'   => $product['name'],
            'operador' => $operator,
        ], ['product_id' => (int) $product['id'], 'actor_type' => 'personal']);
    }

    /**
     * Borrado definitivo, solo si el producto no aparece en ningún pedido.
     *
     * @return array{ok:bool, reason:string}
     */
    public function delete(array $product, string $operator): array
    {
        $check = $this->deletionCheck($product);

        if (!$check['allowed']) {
            return ['ok' => false, 'reason' => $check['reason']];
        }

        $this->products->delete((int) $product['id']);
        $this->removeOrphanUpload((string) $product['image_path'], '');

        // El evento conserva el identificador y los datos básicos: la tabla
        // de eventos no tiene claves ajenas, así que sobrevive al borrado.
        $this->events->record(EventRecorder::PRODUCT_DELETED, [
            'sku'             => $product['sku'],
            'nombre'          => $product['name'],
            'eventos_previos' => $this->products->eventCount((int) $product['id']),
            'operador'        => $operator,
        ], ['product_id' => (int) $product['id'], 'actor_type' => 'personal']);

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * Indica si un producto puede borrarse y, si no, por qué.
     *
     * @return array{allowed:bool, reason:string, sales:int, events:int}
     */
    public function deletionCheck(array $product): array
    {
        $sales  = $this->products->salesLineCount((int) $product['id']);
        $events = $this->products->eventCount((int) $product['id']);

        if ($sales > 0) {
            return [
                'allowed' => false,
                'reason'  => sprintf(
                    'Aparece en %d %s de pedido: borrarlo rompería el histórico de ventas. Retíralo del catálogo en su lugar.',
                    $sales,
                    $sales === 1 ? 'línea' : 'líneas'
                ),
                'sales'   => $sales,
                'events'  => $events,
            ];
        }

        return ['allowed' => true, 'reason' => '', 'sales' => 0, 'events' => $events];
    }

    // -----------------------------------------------------------------
    // Validación y normalización
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $upload
     *
     * @return array{errors:array<string, list<string>>, data:array<string, mixed>}
     */
    private function validate(array $input, ?array $upload, ?int $exceptId): array
    {
        $validator = new Validator(
            $input,
            [
                'nombre'          => 'requerido|min:3|max:120',
                'sku'             => 'max:24|sku',
                'slug'            => 'max:120|slug',
                'categoria_id'    => 'requerido|entero',
                'coleccion_id'    => 'requerido|entero',
                'marca'           => 'requerido|min:2|max:80',
                'origen'          => 'requerido|en:' . implode(',', self::ORIGINS),
                'resumen'         => 'requerido|min:20|max:300',
                'descripcion'     => 'requerido|min:40|max:3000',
                'especificaciones'=> 'max:2000',
                'precio'          => 'requerido|importe',
                'precio_anterior' => 'importe',
                'stock'           => 'requerido|entre:0,99999',
                'peso'            => 'requerido|entre:1,50000',
            ],
            [
                'nombre'          => 'Nombre',
                'sku'             => 'SKU',
                'slug'            => 'Dirección web (slug)',
                'categoria_id'    => 'Categoría',
                'coleccion_id'    => 'Colección',
                'marca'           => 'Marca',
                'origen'          => 'Origen',
                'resumen'         => 'Resumen',
                'descripcion'     => 'Descripción',
                'especificaciones'=> 'Ficha técnica',
                'precio'          => 'Precio',
                'precio_anterior' => 'Precio anterior',
                'stock'           => 'Stock',
                'peso'            => 'Peso',
            ]
        );

        $errors = $validator->errors();

        // --- Relaciones con datos maestros ----------------------------
        $category = $this->findCategory((int) ($input['categoria_id'] ?? 0));
        $line     = $this->findDesignLine((int) ($input['coleccion_id'] ?? 0));

        if (!isset($errors['categoria_id']) && $category === null) {
            $errors['categoria_id'][] = 'La categoría seleccionada no existe.';
        }

        if (!isset($errors['coleccion_id']) && $line === null) {
            $errors['coleccion_id'][] = 'La colección seleccionada no existe.';
        }

        // --- Precios ----------------------------------------------------
        $price   = Validator::cents((string) ($input['precio'] ?? ''));
        $compare = trim((string) ($input['precio_anterior'] ?? '')) === ''
            ? null
            : Validator::cents((string) $input['precio_anterior']);

        if (!isset($errors['precio']) && ($price === null || $price <= 0)) {
            $errors['precio'][] = 'El precio debe ser mayor que 0 €.';
        }

        if (!isset($errors['precio_anterior']) && !isset($errors['precio'])
            && $compare !== null && $price !== null && $compare <= $price) {
            $errors['precio_anterior'][] = 'El precio anterior debe ser mayor que el actual para mostrarse tachado.';
        }

        // --- SKU y slug únicos -----------------------------------------
        $name = trim((string) ($input['nombre'] ?? ''));
        $sku  = strtoupper(trim((string) ($input['sku'] ?? '')));
        $slug = trim((string) ($input['slug'] ?? ''));

        if ($sku === '' && $category !== null) {
            $sku = $this->products->nextSku(self::SKU_PREFIX[$category['slug']] ?? 'KN-PRD');
        }

        if (!isset($errors['sku']) && $sku !== '' && $this->products->skuExists($sku, $exceptId)) {
            $errors['sku'][] = "Ya existe otro producto con el SKU {$sku}.";
        }

        if ($slug === '') {
            $slug = $this->uniqueSlug($name, $exceptId);
        } elseif (!isset($errors['slug']) && $this->products->slugExists($slug, $exceptId)) {
            $errors['slug'][] = 'Esa dirección web ya la usa otro producto.';
        }

        // --- Ficha técnica «Clave: valor» -------------------------------
        $specs = $this->parseSpecs((string) ($input['especificaciones'] ?? ''), $errors);

        // --- Imagen -----------------------------------------------------
        if ($upload !== null) {
            $uploadError = $this->uploadError($upload);
            if ($uploadError !== null) {
                $errors['imagen_subida'][] = $uploadError;
            }
        }

        $chosen = (string) ($input['imagen_actual'] ?? '');
        if ($upload === null && $chosen !== '' && !in_array($chosen, $this->availableImages(), true)) {
            $errors['imagen_actual'][] = 'La imagen elegida no está disponible.';
        }

        return [
            'errors' => $errors,
            'data'   => [
                'sku'              => $sku,
                'slug'             => $slug,
                'name'             => $name,
                'category_id'      => (int) ($input['categoria_id'] ?? 0),
                'design_line_id'   => (int) ($input['coleccion_id'] ?? 0),
                'brand'            => trim((string) ($input['marca'] ?? '')),
                'origin'           => (string) ($input['origen'] ?? ''),
                'summary'          => trim((string) ($input['resumen'] ?? '')),
                'description'      => trim((string) ($input['descripcion'] ?? '')),
                'specs_json'       => (string) json_encode($specs, JSON_UNESCAPED_UNICODE),
                'price_cents'      => (int) $price,
                'compare_at_cents' => $compare,
                'stock'            => (int) ($input['stock'] ?? 0),
                'weight_grams'     => (int) ($input['peso'] ?? 0),
                'is_active'        => isset($input['activo']) ? 1 : 0,
                'is_featured'      => isset($input['destacado']) ? 1 : 0,
            ],
        ];
    }

    /**
     * @param array<string, list<string>> $errors
     * @return array<string, string>
     */
    private function parseSpecs(string $text, array &$errors): array
    {
        $specs = [];
        $lines = array_filter(array_map('trim', preg_split('/\R/', $text) ?: []));

        foreach ($lines as $number => $line) {
            if (!str_contains($line, ':')) {
                $errors['especificaciones'][] = 'Cada línea de la ficha técnica debe tener el formato «Clave: valor».';
                break;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));

            if ($key === '' || $value === '') {
                $errors['especificaciones'][] = 'Hay una línea de la ficha técnica sin clave o sin valor.';
                break;
            }

            $specs[mb_substr($key, 0, 40)] = mb_substr($value, 0, 160);
        }

        if (count($specs) > 20) {
            $errors['especificaciones'][] = 'La ficha técnica admite como máximo 20 líneas.';
        }

        return $specs;
    }

    private function uniqueSlug(string $name, ?int $exceptId): string
    {
        $base = $this->slugify($name) ?: 'producto';
        $slug = $base;
        $n    = 2;

        while ($this->products->slugExists($slug, $exceptId)) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    public function slugify(string $text): string
    {
        $text = strtr(mb_strtolower($text), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c', 'ō' => 'o', 'ū' => 'u',
        ]);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim(mb_substr($text, 0, 100), '-');
    }

    // -----------------------------------------------------------------
    // Imágenes
    // -----------------------------------------------------------------

    /**
     * Ilustraciones del proyecto y subidas previas que se pueden elegir.
     *
     * @return list<string> rutas relativas a public/
     */
    public function availableImages(): array
    {
        $images = [];

        foreach (['assets/img/products/*.svg', 'assets/img/mascotas/*.svg',
                  'uploads/productos/*.png', 'uploads/productos/*.jpg', 'uploads/productos/*.webp'] as $pattern) {
            foreach (glob($this->publicDir . '/' . $pattern) ?: [] as $file) {
                $images[] = substr($file, strlen($this->publicDir) + 1);
            }
        }

        sort($images);

        return $images;
    }

    /** @param array<string, mixed>|null $upload */
    private function resolveImage(array $input, ?array $upload, ?string $current, int $designLineId): string
    {
        if ($upload !== null) {
            return $this->storeUpload($upload);
        }

        $chosen = (string) ($input['imagen_actual'] ?? '');

        if ($chosen !== '' && in_array($chosen, $this->availableImages(), true)) {
            return $chosen;
        }

        if ($current !== null && $current !== '') {
            return $current;
        }

        // Sin imagen: se usa el retrato de la mascota de la colección.
        $line = $this->findDesignLine($designLineId);

        return 'assets/img/mascotas/' . ($line['slug'] ?? 'kitsune') . '.svg';
    }

    /** @param array<string, mixed> $upload */
    private function uploadError(array $upload): ?string
    {
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            return match ($upload['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño máximo permitido.',
                UPLOAD_ERR_PARTIAL => 'La imagen solo se ha subido parcialmente. Vuelve a intentarlo.',
                default            => 'No se ha podido subir la imagen.',
            };
        }

        if ($upload['size'] > self::MAX_UPLOAD_BYTES) {
            return 'La imagen no puede superar los 2 MB.';
        }

        if (!is_uploaded_file($upload['tmp_name']) && PHP_SAPI !== 'cli') {
            return 'El fichero recibido no es una subida válida.';
        }

        // El tipo se comprueba por contenido, no por la extensión ni por lo
        // que declara el navegador, que el cliente puede falsear.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']) ?: '';

        if (!isset(self::ALLOWED_MIME[$mime])) {
            return 'Formato no admitido: sube una imagen PNG, JPEG o WebP.';
        }

        $size = @getimagesize($upload['tmp_name']);

        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] > 4000 || $size[1] > 4000) {
            return 'La imagen no es válida o supera los 4000 × 4000 píxeles.';
        }

        return null;
    }

    /** @param array<string, mixed> $upload */
    private function storeUpload(array $upload): string
    {
        $mime      = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']) ?: '';
        $extension = self::ALLOWED_MIME[$mime] ?? throw new RuntimeException('Formato de imagen no admitido.');
        $directory = $this->publicDir . '/uploads/productos';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se puede crear la carpeta de imágenes subidas.');
        }

        // Nombre aleatorio: nunca se reutiliza el nombre enviado por el cliente.
        $name   = bin2hex(random_bytes(12)) . '.' . $extension;
        $target = $directory . '/' . $name;
        $moved  = PHP_SAPI === 'cli'
            ? copy($upload['tmp_name'], $target)
            : move_uploaded_file($upload['tmp_name'], $target);

        if (!$moved) {
            throw new RuntimeException('No se ha podido guardar la imagen subida.');
        }

        return 'uploads/productos/' . $name;
    }

    /** Borra una imagen subida que ya no usa ningún producto. */
    private function removeOrphanUpload(string $old, string $new): void
    {
        if ($old === $new || !str_starts_with($old, 'uploads/productos/')) {
            return;
        }

        foreach ($this->products->adminList() as $product) {
            if ($product['image_path'] === $old) {
                return;
            }
        }

        $file = $this->publicDir . '/' . $old;

        if (is_file($file)) {
            @unlink($file);
        }
    }

    // -----------------------------------------------------------------

    /** @return array<string, mixed>|null */
    private function findCategory(int $id): ?array
    {
        foreach ($this->categories->all() as $category) {
            if ((int) $category['id'] === $id) {
                return $category;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function findDesignLine(int $id): ?array
    {
        foreach ($this->designLines->all() as $line) {
            if ((int) $line['id'] === $id) {
                return $line;
            }
        }

        return null;
    }
}
