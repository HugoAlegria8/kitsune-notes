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
        <img src="<?= $this->asset('assets/img/mascotas/kitsune.svg') ?>" alt="Kitsune, el zorrito mascota de la tienda" width="110" height="110">
        <div>
            <h1>Sobre Kitsune Notes</h1>
            <p>Una papelería pequeñita con muchas ganas de llenar tu escritorio de cosas monas.</p>
        </div>
    </div>

    <p style="font-size:1.05rem">
        Kitsune Notes importa material de escritorio y organización de Japón y Corea del Sur y
        lo reúne en cuatro colecciones con personaje propio. Buscamos papel que aguante la pluma,
        bolis que escriban finito y washi que se despegue sin romper la hoja… y que además te
        saquen una sonrisa cada vez que abres la agenda.
    </p>

    <h2 class="titulo-deco">De dónde viene el nombre</h2>
    <p>
        En el folclore japonés, el <em>kitsune</em> es un zorro listo y longevo. Nos pareció la
        mascota perfecta para una papelería: curioso, con memoria larga y con cara de tener
        siempre un cuaderno a mano.
    </p>

    <h2 class="titulo-deco">Nuestras colecciones</h2>
    <ul>
        <?php foreach ($designLines as $line): ?>
            <li>
                <a href="<?= $this->url('/coleccion/' . $line['slug']) ?>"><?= $this->e($line['name']) ?></a>,
                el <?= $this->e($line['mascot']) ?>: <?= $this->e(mb_strtolower($line['tagline'])) ?>.
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="alerta alerta--info" style="margin-top:2rem">
        <span class="alerta__icono" aria-hidden="true"><span>i</span></span>
        <div>
            Kitsune Notes es una <strong>empresa ficticia</strong> creada como caso de estudio
            para la asignatura Soluciones Informáticas para la Empresa.
            <a href="<?= $this->url('/aviso-academico') ?>">Leer el aviso académico completo</a>.
        </div>
    </div>
</div>
