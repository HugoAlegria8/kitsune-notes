<?php
/**
 * Listado de productos del back-office.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $products
 * @var array<string, string>      $filters
 * @var list<array<string, mixed>> $categories
 * @var list<array<string, mixed>> $designLines
 * @var array{activos:int, retirados:int} $totals
 */
?>
<div class="admin__cabecera">
    <div>
        <h1>Productos</h1>
        <p><?= $totals['activos'] ?> a la venta · <?= $totals['retirados'] ?> retirados del catálogo</p>
    </div>
    <a class="btn btn--primario" href="<?= $this->url('/admin/productos/nuevo') ?>">+ Nuevo producto</a>
</div>

<form class="filtros-linea" method="get" action="<?= $this->url('/admin/productos') ?>">
    <label class="solo-lectores" for="q">Buscar</label>
    <input type="search" name="q" id="q" placeholder="Nombre o SKU" value="<?= $this->e($filters['q']) ?>">

    <label class="solo-lectores" for="estado">Estado</label>
    <select name="estado" id="estado">
        <option value="">Todos</option>
        <option value="activos" <?= $filters['estado'] === 'activos' ? 'selected' : '' ?>>A la venta</option>
        <option value="retirados" <?= $filters['estado'] === 'retirados' ? 'selected' : '' ?>>Retirados</option>
    </select>

    <label class="solo-lectores" for="categoria">Categoría</label>
    <select name="categoria" id="categoria">
        <option value="">Todas las categorías</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?= $this->e($category['slug']) ?>" <?= $filters['categoria'] === $category['slug'] ? 'selected' : '' ?>>
                <?= $this->e($category['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="solo-lectores" for="coleccion">Colección</label>
    <select name="coleccion" id="coleccion">
        <option value="">Todas las colecciones</option>
        <?php foreach ($designLines as $line): ?>
            <option value="<?= $this->e($line['slug']) ?>" <?= $filters['coleccion'] === $line['slug'] ? 'selected' : '' ?>>
                <?= $this->e($line['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button class="btn btn--primario btn--pequeno" type="submit">Filtrar</button>
    <?php if (array_filter($filters)): ?>
        <a class="btn btn--secundario btn--pequeno" href="<?= $this->url('/admin/productos') ?>">Limpiar</a>
    <?php endif; ?>
</form>

<div class="panel">
    <div class="panel__cuerpo panel__cuerpo--sin-relleno">
        <div class="tabla-envoltorio">
            <table class="tabla">
                <caption class="solo-lectores">Productos del catálogo</caption>
                <thead>
                <tr>
                    <th scope="col"><span class="solo-lectores">Imagen</span></th>
                    <th scope="col">Producto</th>
                    <th scope="col">Colección</th>
                    <th scope="col" class="num">Precio</th>
                    <th scope="col" class="num">Stock</th>
                    <th scope="col" class="num">Vendidas</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="solo-lectores">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <?php $activo = (int) $product['is_active'] === 1; ?>
                    <tr class="<?= $activo ? '' : 'fila-retirada' ?>">
                        <td><img class="miniatura" src="<?= $this->asset($product['image_path']) ?>" alt="" width="58" height="44" loading="lazy"></td>
                        <td>
                            <strong><?= $this->e($product['name']) ?></strong><br>
                            <span style="font-size:.8rem; color:var(--frambuesa-tenue)">
                                <?= $this->e($product['sku']) ?> · <?= $this->e($product['category_name']) ?>
                            </span>
                            <?php /* Sin nombre en inglés, la tienda en inglés lo muestra en español. */ ?>
                            <?php if (trim((string) ($product['name_en'] ?? '')) === ''): ?>
                                <br><span class="insignia insignia--warning" title="En la tienda en inglés se muestra en español">sin traducir</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $this->partial('partials/coleccion-chip', [
                                'slug'      => $product['design_line_slug'],
                                'name'      => $product['design_line_name'],
                                'principal' => $product['design_line_color'],
                                'suave'     => $product['design_line_soft'],
                                'enlace'    => false,
                            ]) ?>
                        </td>
                        <td class="num"><?= $this->money((int) $product['price_cents']) ?></td>
                        <td class="num">
                            <?= (int) $product['stock'] ?>
                            <?php if ((int) $product['stock'] <= 10): ?>
                                <br><span class="insignia insignia--warning">bajo</span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= (int) $product['units_sold'] ?></td>
                        <td>
                            <?php if ($activo): ?>
                                <span class="insignia insignia--success">A la venta</span>
                            <?php else: ?>
                                <span class="insignia insignia--muted">Retirado</span>
                            <?php endif; ?>
                            <?php if ((int) $product['is_featured'] === 1): ?>
                                <br><span class="insignia insignia--info" style="margin-top:.3rem">Destacado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="acciones-fila">
                                <a class="btn btn--secundario btn--pequeno"
                                   href="<?= $this->url('/admin/productos/' . $product['id'] . '/editar') ?>">Editar</a>
                                <form method="post"
                                      action="<?= $this->url('/admin/productos/' . $product['id'] . ($activo ? '/retirar' : '/reactivar')) ?>">
                                    <?= $this->csrf() ?>
                                    <input type="hidden" name="volver" value="listado">
                                    <button class="btn btn--<?= $activo ? 'secundario' : 'lila' ?> btn--pequeno" type="submit">
                                        <?= $activo ? 'Retirar' : 'Publicar' ?><span class="solo-lectores"> <?= $this->e($product['name']) ?></span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($products === []): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; color:var(--frambuesa-suave); padding:2rem">
                            No hay productos que coincidan con el filtro.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alerta alerta--info">
    <span class="alerta__icono" aria-hidden="true"><span>i</span></span>
    <div>
        <strong>Retirar o eliminar.</strong> Retirar oculta el producto de la tienda pero conserva sus
        pedidos y eventos, y se puede deshacer. Eliminar lo borra para siempre y solo se permite si
        nunca se ha vendido; la opción está en la pantalla de edición de cada producto.
    </div>
</div>
