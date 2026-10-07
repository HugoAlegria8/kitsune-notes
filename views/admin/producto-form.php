<?php
/**
 * Alta y edición de productos.
 *
 * @var \KitsuneNotes\Core\View $this
 * @var array<string, mixed>|null   $product   null en el alta
 * @var array<string, mixed>        $old       valores del formulario
 * @var array<string, list<string>> $errors
 * @var list<array<string, mixed>>  $categories
 * @var list<array<string, mixed>>  $designLines
 * @var list<string>                $origins
 * @var list<string>                $images
 * @var array{allowed:bool, reason:string, sales:int, events:int}|null $deletion
 */
$esNuevo = $product === null;
$value   = static fn (string $campo, string $defecto = ''): string => (string) ($old[$campo] ?? $defecto);
$error   = static fn (string $campo): ?string => $errors[$campo][0] ?? null;
$marcado = static fn (string $campo): bool => isset($old[$campo]) && $old[$campo] !== '' && $old[$campo] !== '0';

$accion = $esNuevo
    ? $this->url('/admin/productos')
    : $this->url('/admin/productos/' . $product['id']);

$imagenActual = $value('imagen_actual');
$vista        = $imagenActual !== '' ? $imagenActual : 'assets/img/mascotas/kitsune.svg';

$invalido = static fn (string $campo, string $id = ''): string => isset($errors[$campo])
    ? 'aria-invalid="true" aria-describedby="err-' . ($id ?: $campo) . '"'
    : '';
?>
<div class="admin__cabecera">
    <div>
        <p style="margin:0 0 .2rem; font-size:.88rem">
            <a href="<?= $this->url('/admin/productos') ?>">← Volver a productos</a>
        </p>
        <h1><?= $esNuevo ? 'Nuevo producto' : $this->e($product['name']) ?></h1>
        <?php if (!$esNuevo): ?>
            <p>
                <?= $this->e($product['sku']) ?> ·
                <?php if ((int) $product['is_active'] === 1): ?>
                    <a href="<?= $this->url('/producto/' . $product['slug']) ?>" target="_blank" rel="noopener">Ver en la tienda ↗</a>
                <?php else: ?>
                    retirado del catálogo
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p>Los campos con * son obligatorios. El SKU y la dirección web se generan solos si los dejas vacíos.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="alerta alerta--error" role="alert">
        <span class="alerta__icono" aria-hidden="true"><span>!</span></span>
        <div>
            <strong>No se ha guardado: revisa estos campos</strong>
            <ul style="margin:.4rem 0 0; padding-left:1.1rem">
                <?php foreach ($errors as $mensajes): ?>
                    <li><?= $this->e($mensajes[0]) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="formulario-producto">
    <form id="form-producto" method="post" action="<?= $accion ?>" enctype="multipart/form-data" novalidate>
        <?= $this->csrf() ?>

        <section class="panel">
            <div class="panel__cabecera"><h2>Datos básicos</h2></div>
            <div class="panel__cuerpo">
                <div class="campo">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" required maxlength="120"
                           value="<?= $this->e($value('nombre')) ?>" <?= $invalido('nombre') ?>>
                    <?php if ($error('nombre')): ?><p class="campo__error" id="err-nombre"><?= $this->e($error('nombre')) ?></p><?php endif; ?>
                </div>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="categoria_id">Categoría *</label>
                        <select id="categoria_id" name="categoria_id" required <?= $invalido('categoria_id') ?>>
                            <option value="">Elige una categoría</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= $value('categoria_id') === (string) $category['id'] ? 'selected' : '' ?>>
                                    <?= $this->e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($error('categoria_id')): ?><p class="campo__error" id="err-categoria_id"><?= $this->e($error('categoria_id')) ?></p><?php endif; ?>
                    </div>

                    <div class="campo">
                        <label for="coleccion_id">Colección (línea de diseño) *</label>
                        <select id="coleccion_id" name="coleccion_id" required <?= $invalido('coleccion_id') ?>>
                            <option value="">Elige una colección</option>
                            <?php foreach ($designLines as $line): ?>
                                <option value="<?= (int) $line['id'] ?>" <?= $value('coleccion_id') === (string) $line['id'] ? 'selected' : '' ?>>
                                    <?= $this->e($line['name']) ?> · <?= $this->e($line['mascot']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($error('coleccion_id')): ?><p class="campo__error" id="err-coleccion_id"><?= $this->e($error('coleccion_id')) ?></p><?php endif; ?>
                    </div>
                </div>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="marca">Marca *</label>
                        <input type="text" id="marca" name="marca" required maxlength="80"
                               value="<?= $this->e($value('marca')) ?>" <?= $invalido('marca') ?>>
                        <?php if ($error('marca')): ?><p class="campo__error" id="err-marca"><?= $this->e($error('marca')) ?></p><?php endif; ?>
                    </div>

                    <div class="campo">
                        <label for="origen">Origen *</label>
                        <select id="origen" name="origen" required <?= $invalido('origen') ?>>
                            <?php foreach ($origins as $origin): ?>
                                <option value="<?= $this->e($origin) ?>" <?= $value('origen') === $origin ? 'selected' : '' ?>><?= $this->e($origin) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="sku">SKU</label>
                        <input type="text" id="sku" name="sku" maxlength="24" placeholder="Automático: KN-CUA-004…"
                               value="<?= $this->e($value('sku')) ?>" <?= $invalido('sku') ?>>
                        <?php if ($error('sku')): ?><p class="campo__error" id="err-sku"><?= $this->e($error('sku')) ?></p><?php endif; ?>
                    </div>

                    <div class="campo">
                        <label for="slug">Dirección web (slug)</label>
                        <input type="text" id="slug" name="slug" maxlength="120" placeholder="Automática a partir del nombre"
                               value="<?= $this->e($value('slug')) ?>" <?= $invalido('slug') ?>>
                        <?php if ($error('slug')): ?>
                            <p class="campo__error" id="err-slug"><?= $this->e($error('slug')) ?></p>
                        <?php else: ?>
                            <p class="pista">Aparece en la URL: /producto/<em>slug</em></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel__cabecera"><h2>Precio y existencias</h2></div>
            <div class="panel__cuerpo">
                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="precio">Precio con IVA (€) *</label>
                        <input type="text" id="precio" name="precio" required inputmode="decimal" placeholder="12,90"
                               value="<?= $this->e($value('precio')) ?>" <?= $invalido('precio') ?>>
                        <?php if ($error('precio')): ?><p class="campo__error" id="err-precio"><?= $this->e($error('precio')) ?></p><?php endif; ?>
                    </div>

                    <div class="campo">
                        <label for="precio_anterior">Precio anterior tachado (€)</label>
                        <input type="text" id="precio_anterior" name="precio_anterior" inputmode="decimal" placeholder="Opcional"
                               value="<?= $this->e($value('precio_anterior')) ?>" <?= $invalido('precio_anterior') ?>>
                        <?php if ($error('precio_anterior')): ?>
                            <p class="campo__error" id="err-precio_anterior"><?= $this->e($error('precio_anterior')) ?></p>
                        <?php else: ?>
                            <p class="pista">Si lo rellenas, la ficha muestra la etiqueta «¡Oferta!».</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rejilla-campos">
                    <div class="campo">
                        <label for="stock">Stock (unidades) *</label>
                        <input type="number" id="stock" name="stock" required min="0" max="99999" step="1"
                               value="<?= $this->e($value('stock')) ?>" <?= $invalido('stock') ?>>
                        <?php if ($error('stock')): ?><p class="campo__error" id="err-stock"><?= $this->e($error('stock')) ?></p><?php endif; ?>
                    </div>

                    <div class="campo">
                        <label for="peso">Peso (gramos) *</label>
                        <input type="number" id="peso" name="peso" required min="1" max="50000" step="1"
                               value="<?= $this->e($value('peso')) ?>" <?= $invalido('peso') ?>>
                        <?php if ($error('peso')): ?><p class="campo__error" id="err-peso"><?= $this->e($error('peso')) ?></p><?php endif; ?>
                    </div>
                </div>
                <p class="pista" style="margin:0; font-size:.84rem; color:var(--frambuesa-tenue)">
                    El IVA aplicado es el general del 21 %: la tienda desglosa base y cuota a partir del precio.
                    El precio se escribe siempre en euros; en la versión en inglés se muestra en libras con el
                    tipo de cambio de la configuración
                    (<?= $this->e($this->app()->currency()->rateLabel('GBP')) ?>).
                </p>
            </div>
        </section>

        <section class="panel">
            <div class="panel__cabecera"><h2>Textos de la ficha</h2></div>
            <div class="panel__cuerpo">
                <div class="campo">
                    <label for="resumen">Resumen *</label>
                    <textarea id="resumen" name="resumen" required maxlength="300" style="min-height:80px"
                              <?= $invalido('resumen') ?>><?= $this->e($value('resumen')) ?></textarea>
                    <?php if ($error('resumen')): ?>
                        <p class="campo__error" id="err-resumen"><?= $this->e($error('resumen')) ?></p>
                    <?php else: ?>
                        <p class="pista">Una o dos frases: se ve en las tarjetas del catálogo.</p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label for="descripcion">Descripción *</label>
                    <textarea id="descripcion" name="descripcion" required maxlength="3000" style="min-height:160px"
                              <?= $invalido('descripcion') ?>><?= $this->e($value('descripcion')) ?></textarea>
                    <?php if ($error('descripcion')): ?><p class="campo__error" id="err-descripcion"><?= $this->e($error('descripcion')) ?></p><?php endif; ?>
                </div>

                <div class="campo" style="margin-bottom:0">
                    <label for="especificaciones">Ficha técnica</label>
                    <textarea id="especificaciones" name="especificaciones" maxlength="2000"
                              placeholder="Formato: A5 (148 × 210 mm)&#10;Páginas: 192&#10;Gramaje: 100 g/m²"
                              <?= $invalido('especificaciones') ?>><?= $this->e($value('especificaciones')) ?></textarea>
                    <?php if ($error('especificaciones')): ?>
                        <p class="campo__error" id="err-especificaciones"><?= $this->e($error('especificaciones')) ?></p>
                    <?php else: ?>
                        <p class="pista">Una característica por línea con el formato «Clave: valor».</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php /* Versión en inglés de los textos. Es opcional: lo que quede vacío se muestra en
                 español en la tienda en inglés, así que un producto nuevo nunca se queda sin nombre. */ ?>
        <section class="panel">
            <div class="panel__cabecera"><h2>Versión en inglés <span style="font-weight:500; font-size:.85rem">(opcional)</span></h2></div>
            <div class="panel__cuerpo">
                <p class="pista" style="margin:0 0 1rem">
                    Estos textos son los que ve quien pulsa el botón EN de la tienda. Lo que dejes vacío
                    se mostrará en español. El país de origen se traduce solo.
                </p>

                <div class="campo">
                    <label for="nombre_en">Nombre en inglés</label>
                    <input type="text" id="nombre_en" name="nombre_en" maxlength="120" lang="en"
                           value="<?= $this->e($value('nombre_en')) ?>" <?= $invalido('nombre_en') ?>>
                    <?php if ($error('nombre_en')): ?><p class="campo__error" id="err-nombre_en"><?= $this->e($error('nombre_en')) ?></p><?php endif; ?>
                </div>

                <div class="campo">
                    <label for="resumen_en">Resumen en inglés</label>
                    <textarea id="resumen_en" name="resumen_en" maxlength="300" style="min-height:80px" lang="en"
                              <?= $invalido('resumen_en') ?>><?= $this->e($value('resumen_en')) ?></textarea>
                    <?php if ($error('resumen_en')): ?><p class="campo__error" id="err-resumen_en"><?= $this->e($error('resumen_en')) ?></p><?php endif; ?>
                </div>

                <div class="campo">
                    <label for="descripcion_en">Descripción en inglés</label>
                    <textarea id="descripcion_en" name="descripcion_en" maxlength="3000" style="min-height:160px" lang="en"
                              <?= $invalido('descripcion_en') ?>><?= $this->e($value('descripcion_en')) ?></textarea>
                    <?php if ($error('descripcion_en')): ?><p class="campo__error" id="err-descripcion_en"><?= $this->e($error('descripcion_en')) ?></p><?php endif; ?>
                </div>

                <div class="campo" style="margin-bottom:0">
                    <label for="especificaciones_en">Ficha técnica en inglés</label>
                    <textarea id="especificaciones_en" name="especificaciones_en" maxlength="2000" lang="en"
                              placeholder="Format: A5 (148 × 210 mm)&#10;Pages: 192&#10;Paper weight: 100 gsm"
                              <?= $invalido('especificaciones_en') ?>><?= $this->e($value('especificaciones_en')) ?></textarea>
                    <?php if ($error('especificaciones_en')): ?>
                        <p class="campo__error" id="err-especificaciones_en"><?= $this->e($error('especificaciones_en')) ?></p>
                    <?php else: ?>
                        <p class="pista">Mismo formato que la ficha en español: «Clave: valor», una por línea.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </form>

    <aside class="formulario-producto__lateral" aria-label="Imagen, visibilidad y acciones">
        <section class="panel" style="margin:0">
            <div class="panel__cabecera"><h2>Imagen</h2></div>
            <div class="panel__cuerpo">
                <div class="vista-imagen" style="margin-bottom:1rem">
                    <img id="vista-imagen" src="<?= $this->asset($vista) ?>" alt="Vista previa de la imagen del producto" width="400" height="300">
                </div>

                <fieldset style="border:0; padding:0; margin:0 0 1rem">
                    <legend class="etiqueta">Elegir una ilustración</legend>
                    <div class="selector-imagenes">
                        <?php foreach ($images as $i => $image): ?>
                            <label title="<?= $this->e(basename($image)) ?>">
                                <input type="radio" name="imagen_actual" value="<?= $this->e($image) ?>" form="form-producto"
                                       data-vista="<?= $this->asset($image) ?>"
                                       <?= $imagenActual === $image ? 'checked' : '' ?>>
                                <img src="<?= $this->asset($image) ?>" alt="<?= $this->e(basename($image)) ?>" width="78" height="58" loading="lazy">
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($error('imagen_actual')): ?><p class="campo__error" id="err-imagen_actual"><?= $this->e($error('imagen_actual')) ?></p><?php endif; ?>
                    <?php if ($esNuevo): ?>
                        <p class="pista" style="font-size:.82rem; color:var(--frambuesa-tenue)">Si no eliges ninguna, se usa la mascota de la colección.</p>
                    <?php endif; ?>
                </fieldset>

                <div class="campo" style="margin:0">
                    <label for="imagen_subida">…o subir una imagen propia</label>
                    <input type="file" id="imagen_subida" name="imagen_subida" form="form-producto"
                           accept="image/png,image/jpeg,image/webp" <?= $invalido('imagen_subida') ?>>
                    <?php if ($error('imagen_subida')): ?>
                        <p class="campo__error" id="err-imagen_subida"><?= $this->e($error('imagen_subida')) ?></p>
                    <?php else: ?>
                        <p class="pista">PNG, JPEG o WebP de hasta 2 MB. Se comprueba el contenido real del fichero.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="panel" style="margin:0">
            <div class="panel__cabecera"><h2>Publicación</h2></div>
            <div class="panel__cuerpo">
                <div class="campo-casilla" style="margin-bottom:.7rem">
                    <input type="checkbox" id="activo" name="activo" value="1" form="form-producto" <?= $marcado('activo') ? 'checked' : '' ?>>
                    <label for="activo">A la venta en la tienda</label>
                </div>
                <div class="campo-casilla" style="margin-bottom:1.1rem">
                    <input type="checkbox" id="destacado" name="destacado" value="1" form="form-producto" <?= $marcado('destacado') ? 'checked' : '' ?>>
                    <label for="destacado">Destacado en la portada</label>
                </div>
                <button class="btn btn--primario btn--bloque" type="submit" form="form-producto">
                    <?= $esNuevo ? 'Dar de alta el producto' : 'Guardar cambios' ?>
                </button>
                <p class="pista" style="font-size:.8rem; color:var(--frambuesa-tenue); margin:.7rem 0 0">
                    Se registrará el evento <code><?= $esNuevo ? 'product.created' : 'product.updated' ?></code> con tu usuario.
                </p>
            </div>
        </section>

        <?php if (!$esNuevo && $deletion !== null): ?>
            <section class="zona-peligro" aria-labelledby="titulo-peligro">
                <h2 id="titulo-peligro">Retirar o eliminar</h2>

                <p class="historial-producto" style="margin:0 0 .9rem">
                    <span class="insignia insignia--neutral"><?= $deletion['sales'] ?> <?= $deletion['sales'] === 1 ? 'línea' : 'líneas' ?> de pedido</span>
                    <span class="insignia insignia--neutral"><?= $deletion['events'] ?> <?= $deletion['events'] === 1 ? 'evento' : 'eventos' ?> de clientes</span>
                </p>

                <form method="post" action="<?= $this->url('/admin/productos/' . $product['id'] . ((int) $product['is_active'] === 1 ? '/retirar' : '/reactivar')) ?>" style="margin-bottom:1rem">
                    <?= $this->csrf() ?>
                    <button class="btn btn--secundario btn--bloque" type="submit">
                        <?= (int) $product['is_active'] === 1 ? 'Retirar del catálogo' : 'Volver a publicar' ?>
                    </button>
                </form>

                <?php if ($deletion['allowed']): ?>
                    <form method="post" action="<?= $this->url('/admin/productos/' . $product['id'] . '/eliminar') ?>">
                        <?= $this->csrf() ?>
                        <div class="campo-casilla" style="margin-bottom:.7rem">
                            <input type="checkbox" id="confirmar" name="confirmar" value="1" required>
                            <label for="confirmar" style="font-weight:700">Sí, eliminar este producto para siempre</label>
                        </div>
                        <button class="btn btn--peligro btn--bloque" type="submit">Eliminar definitivamente</button>
                    </form>
                    <p style="font-size:.82rem; margin:.7rem 0 0; font-weight:600">
                        Nunca se ha vendido, así que se puede borrar. Sus eventos se conservan con el SKU.
                    </p>
                <?php else: ?>
                    <p style="font-size:.86rem; margin:0; font-weight:600"><?= $this->e($deletion['reason']) ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </aside>
</div>
