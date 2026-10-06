<?php
/**
 * Página «Sobre Kitsune Notes».
 *
 * @var \KitsuneNotes\Core\View $this
 * @var list<array<string, mixed>> $designLines
 */
?>
<div style="max-width:820px; margin-inline:auto">
    <div class="cabecera-coleccion">
        <img src="<?= $this->asset('assets/img/mascotas/kitsune.svg') ?>" alt="<?= $this->t('Kitsune, el zorrito mascota de la tienda') ?>" width="110" height="110">
        <div>
            <h1><?= $this->t('Sobre Kitsune Notes') ?></h1>
            <p><?= $this->t('Una papelería pequeñita con muchas ganas de llenar tu escritorio de cosas monas.') ?></p>
        </div>
    </div>

    <p style="font-size:1.05rem">
        <?= $this->t('Kitsune Notes importa material de escritorio y organización de Japón y Corea del Sur y lo reúne en cuatro colecciones con personaje propio. Buscamos papel que aguante la pluma, bolis que escriban finito y washi que se despegue sin romper la hoja… y que además te saquen una sonrisa cada vez que abres la agenda.') ?>
    </p>

    <h2 class="titulo-deco"><?= $this->t('De dónde viene el nombre') ?></h2>
    <p>
        <?= $this->th('En el folclore japonés, el <em>kitsune</em> es un zorro listo y longevo. Nos pareció la mascota perfecta para una papelería: curioso, con memoria larga y con cara de tener siempre un cuaderno a mano.') ?>
    </p>

    <h2 class="titulo-deco"><?= $this->t('Nuestras colecciones') ?></h2>
    <ul>
        <?php foreach ($designLines as $line): ?>
            <li>
                <?= $this->th('<a href="{url}">{nombre}</a>, el {mascota}: {lema}.', [
                    'url'     => $this->url('/coleccion/' . $line['slug']),
                    'nombre'  => $line['name'],
                    'mascota' => $line['mascot'],
                    'lema'    => mb_strtolower($line['tagline']),
                ]) ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="alerta alerta--info" style="margin-top:2rem">
        <span class="alerta__icono" aria-hidden="true"><span>i</span></span>
        <div>
            <?= $this->th('Kitsune Notes es una <strong>empresa ficticia</strong> creada como caso de estudio para la asignatura Soluciones Informáticas para la Empresa. <a href="{url}">Leer el aviso académico completo</a>.', ['url' => $this->url('/aviso-academico')]) ?>
        </div>
    </div>
</div>
