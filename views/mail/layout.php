<?php
/**
 * Marco común de los correos: cabecera con la marca, aviso de prototipo y pie.
 *
 * Todo el CSS va en línea y el diseño usa tablas porque los clientes de
 * correo ignoran las hojas de estilo externas y no admiten grid ni flexbox.
 * Los colores son los de la web («fresa y nata») y cumplen contraste AA.
 *
 * Idioma: el Notifier pinta cada correo con el idioma del pedido (o de la
 * solicitud de soporte) ya activo, así que aquí basta con usar t().
 *
 * @var \KitsuneNotes\Core\View $this
 * @var string                   $content
 * @var string                   $subject
 * @var string                   $preheader
 * @var array<string, mixed>     $company
 * @var bool                   $realDelivery true si el mensaje se va a entregar por SMTP (cambia el aviso de prototipo)
 */
?>
<!doctype html>
<html lang="<?= $this->e($this->app()->translator()->info('html') ?: 'es') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<base target="_blank">
<title><?= $this->e($subject) ?></title>
</head>
<body style="margin:0; padding:0; background:#FFF5F8; color:#5A2340; font-family:'Trebuchet MS','Arial Rounded MT Bold',Verdana,Arial,sans-serif; font-size:16px; line-height:1.6;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#FFF5F8;"><?= $this->e($preheader ?? '') ?></div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFF5F8;">
<tr>
<td align="center" style="padding:24px 12px 32px;">

    <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:640px;">
        <tr>
            <td align="center" style="padding:0 8px 14px; font-size:12px; line-height:1.5; color:#8A4A6A; font-weight:bold;">
                <?= !empty($realDelivery)
                    ? $this->t('Prototipo académico sin actividad comercial real · pedido, pago y factura ficticios')
                    : $this->t('Prototipo académico · correo de prueba: no se ha entregado a ningún buzón real') ?>
            </td>
        </tr>

        <tr>
            <td style="background:#FFFFFF; border:3px solid #5A2340; border-radius:24px; overflow:hidden;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="background:#FFD6E7; padding:20px 28px; border-bottom:3px solid #5A2340;">
                            <span style="font-size:26px; line-height:1.2; font-weight:bold; color:#5A2340;">Kitsune Notes</span>
                            <span style="font-size:22px; color:#D6336C;">&#9825;</span><br>
                            <span style="font-size:13px; color:#8A4A6A; font-weight:bold;"><?= $this->t('papelería kawaii de Japón y Corea') ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;"><?= $content ?></td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td align="center" style="padding:18px 12px 0; font-size:12px; line-height:1.6; color:#8A4A6A;">
                <strong><?= $this->e($company['name'] ?? '') ?></strong> · <?= $this->t('NIF {nif}', ['nif' => $company['tax_id'] ?? '']) ?><br>
                <?= $this->e($company['address'] ?? '') ?>, <?= $this->e($company['postal_code'] ?? '') ?>
                <?= $this->e($company['city'] ?? '') ?><br>
                <?php /* El aviso viene de la configuración (en español): se traduce al imprimirlo. */ ?>
                <?= $this->t((string) ($company['fictional'] ?? '')) ?><br>
                <?= $this->t('Recibes este mensaje porque se ha realizado una operación de prueba con esta dirección.') ?>
            </td>
        </tr>
    </table>

</td>
</tr>
</table>
</body>
</html>
