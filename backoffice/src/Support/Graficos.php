<?php

declare(strict_types=1);

namespace KitsuneGestion\Support;

/**
 * Gráficos de barras en SVG generados en el servidor: sin librerías ni
 * peticiones externas. Cada gráfico lleva título y descripción accesibles,
 * y la página muestra además los datos en una tabla.
 */
final class Graficos
{
    /**
     * Barras verticales (por ejemplo, pedidos por día).
     *
     * @param array<string, int|float> $datos etiqueta => valor
     */
    public static function barras(array $datos, string $titulo, string $descripcion): string
    {
        $ancho   = 640;
        $alto    = 230;
        $margenX = 34;
        $margenY = 28;
        $base    = $alto - 44;
        $maximo  = max(1, ...array_values($datos ?: [0]));
        $n       = max(1, count($datos));
        $hueco   = ($ancho - $margenX - 10) / $n;
        $barra   = max(4, $hueco * 0.62);
        $svg     = self::abrir($ancho, $alto, $titulo, $descripcion);

        // Líneas guía: 0, la mitad y el máximo.
        foreach ([0, 0.5, 1] as $fraccion) {
            $y = $base - ($base - $margenY) * $fraccion;
            $svg .= sprintf('<line class="grafico__guia" x1="%d" x2="%d" y1="%.1f" y2="%.1f"/>', $margenX, $ancho - 6, $y, $y);
            $svg .= sprintf('<text class="grafico__eje" x="%d" y="%.1f" text-anchor="end">%s</text>', $margenX - 6, $y + 4, self::numero($maximo * $fraccion));
        }

        $i = 0;

        foreach ($datos as $etiqueta => $valor) {
            $h = ($base - $margenY) * ($valor / $maximo);
            $x = $margenX + $hueco * $i + ($hueco - $barra) / 2;
            $svg .= sprintf(
                '<rect class="grafico__barra" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3"><title>%s: %s</title></rect>',
                $x, $base - $h, $barra, max(0.5, $h), self::e((string) $etiqueta), self::numero($valor)
            );

            if ($valor > 0) {
                $svg .= sprintf('<text class="grafico__valor" x="%.1f" y="%.1f" text-anchor="middle">%s</text>', $x + $barra / 2, $base - $h - 5, self::numero($valor));
            }

            $svg .= sprintf('<text class="grafico__eje" x="%.1f" y="%d" text-anchor="middle">%s</text>', $x + $barra / 2, $base + 16, self::e(self::corta((string) $etiqueta)));
            $i++;
        }

        return $svg . '</svg>';
    }

    /**
     * Barras horizontales (rankings y repartos).
     *
     * @param array<string, int|float> $datos etiqueta => valor
     */
    public static function horizontal(array $datos, string $titulo, string $descripcion): string
    {
        $fila    = 30;
        $ancho   = 640;
        $alto    = max(60, 18 + $fila * count($datos));
        $etiqueta = 230;
        $maximo  = max(1, ...array_values($datos ?: [0]));
        $svg     = self::abrir($ancho, $alto, $titulo, $descripcion);
        $i       = 0;

        foreach ($datos as $nombre => $valor) {
            $y = 10 + $fila * $i;
            $w = ($ancho - $etiqueta - 60) * ($valor / $maximo);
            $svg .= sprintf('<text class="grafico__eje" x="%d" y="%d" text-anchor="end">%s</text>', $etiqueta - 8, $y + 15, self::e(mb_strimwidth((string) $nombre, 0, 34, '…')));
            $svg .= sprintf('<rect class="grafico__barra grafico__barra--h" x="%d" y="%d" width="%.1f" height="18" rx="3"><title>%s: %s</title></rect>', $etiqueta, $y + 2, max(0.5, $w), self::e((string) $nombre), self::numero($valor));
            $svg .= sprintf('<text class="grafico__valor" x="%.1f" y="%d">%s</text>', $etiqueta + $w + 6, $y + 15, self::numero($valor));
            $i++;
        }

        if ($datos === []) {
            $svg .= '<text class="grafico__eje" x="20" y="34">Todavía no hay datos.</text>';
        }

        return $svg . '</svg>';
    }

    private static function abrir(int $ancho, int $alto, string $titulo, string $descripcion): string
    {
        $id = 'g' . substr(md5($titulo . $descripcion), 0, 8);

        return sprintf(
            '<svg class="grafico" viewBox="0 0 %d %d" role="img" aria-labelledby="%s-t %s-d" xmlns="http://www.w3.org/2000/svg"><title id="%s-t">%s</title><desc id="%s-d">%s</desc>',
            $ancho, $alto, $id, $id, $id, self::e($titulo), $id, self::e($descripcion)
        );
    }

    private static function numero(int|float $valor): string
    {
        return is_float($valor) && floor($valor) !== $valor
            ? number_format($valor, 1, ',', '.')
            : number_format((float) $valor, 0, ',', '.');
    }

    private static function corta(string $etiqueta): string
    {
        // Las fechas AAAA-MM-DD se muestran como DD/MM.
        if (preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $etiqueta, $m) === 1) {
            return $m[2] . '/' . $m[1];
        }

        return mb_strimwidth($etiqueta, 0, 14, '…');
    }

    private static function e(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
