<?php

declare(strict_types=1);

namespace KitsuneNotes\Core;

/**
 * Traductor de la interfaz.
 *
 * Criterio: el español es el idioma original y sus textos son la CLAVE de
 * traducción (como en gettext). Las plantillas y los controladores siguen
 * escribiendo el texto en español, envuelto en t('…'); para otro idioma se
 * busca ese mismo texto en los catálogos de lang/<idioma>/*.php. Así:
 *
 *  - En español no cambia nada: t() devuelve el texto tal cual.
 *  - Un texto sin traducir no rompe la página: sale en español y queda
 *    anotado para detectarlo (tools/comprobar_traducciones.php).
 *
 * Los valores variables se pasan aparte con marcadores {nombre}, de modo
 * que cada idioma puede colocarlos donde le convenga en la frase.
 */
final class Translator
{
    private string $locale;

    /** @var array<string, array<string, string>> catálogos ya leídos, por idioma */
    private array $catalogs = [];

    /** @var array<string, array<string, true>> textos pedidos y no encontrados, por idioma */
    private array $missing = [];

    /**
     * @param array<string, array<string, mixed>> $locales idiomas admitidos (config «i18n.locales»)
     */
    public function __construct(
        private readonly string $langPath,
        private readonly array $locales,
        private readonly string $default = 'es',
    ) {
        $this->locale = $default;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function defaultLocale(): string
    {
        return $this->default;
    }

    /** ¿Está activo el idioma original (español)? */
    public function isDefault(): bool
    {
        return $this->locale === $this->default;
    }

    public function supports(string $locale): bool
    {
        return $locale !== '' && array_key_exists($locale, $this->locales);
    }

    /** Cambia el idioma activo. Un idioma no admitido se ignora. */
    public function setLocale(string $locale): void
    {
        if ($this->supports($locale)) {
            $this->locale = $locale;
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function locales(): array
    {
        return $this->locales;
    }

    /**
     * Dato de configuración de un idioma: «label», «short», «html» o «currency».
     * Sin $locale se refiere al idioma activo.
     */
    public function info(string $key, ?string $locale = null): string
    {
        return (string) ($this->locales[$locale ?? $this->locale][$key] ?? '');
    }

    /**
     * Traduce un texto escrito en español al idioma activo (o al indicado)
     * y sustituye los marcadores {clave} por los valores de $params.
     *
     * @param array<string, scalar|null> $params
     */
    public function get(string $text, array $params = [], ?string $locale = null): string
    {
        $locale ??= $this->locale;

        if ($locale !== $this->default) {
            $catalog = $this->catalog($locale);

            if (array_key_exists($text, $catalog)) {
                $text = $catalog[$text];
            } elseif ($text !== '') {
                $this->missing[$locale][$text] = true;
            }
        }

        return $params === [] ? $text : self::interpolate($text, $params);
    }

    /**
     * Singular o plural según $count (español e inglés comparten la regla:
     * singular solo con 1). El marcador {n} vale siempre $count.
     *
     * @param array<string, scalar|null> $params
     */
    public function choice(string $singular, string $plural, int $count, array $params = [], ?string $locale = null): string
    {
        return $this->get($count === 1 ? $singular : $plural, $params + ['n' => $count], $locale);
    }

    /** ¿Existe traducción de ese texto en el idioma activo (o en el indicado)? */
    public function has(string $text, ?string $locale = null): bool
    {
        $locale ??= $this->locale;

        return $locale === $this->default || array_key_exists($text, $this->catalog($locale));
    }

    /**
     * Ejecuta $callback con otro idioma activo y restaura después el anterior.
     * Lo usan los correos y las facturas, que se redactan en el idioma del
     * pedido aunque quien provoque el envío esté viendo la web en otro.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function runIn(string $locale, callable $callback): mixed
    {
        $previous = $this->locale;
        $this->setLocale($locale);

        try {
            return $callback();
        } finally {
            $this->locale = $previous;
        }
    }

    /**
     * Textos que se han pedido durante la petición y no tienen traducción.
     *
     * @return array<string, list<string>> por idioma
     */
    public function missing(): array
    {
        return array_map(static fn (array $texts): array => array_keys($texts), $this->missing);
    }

    /** @param array<string, scalar|null> $params */
    public static function interpolate(string $text, array $params): string
    {
        $replacements = [];

        foreach ($params as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr($text, $replacements);
    }

    /**
     * Catálogo completo de un idioma: la unión de todos los ficheros de
     * lang/<idioma>/, leídos en orden alfabético. Cada fichero devuelve un
     * array «texto en español» => «traducción».
     *
     * @return array<string, string>
     */
    public function catalog(string $locale): array
    {
        if (isset($this->catalogs[$locale])) {
            return $this->catalogs[$locale];
        }

        $catalog = [];

        if ($this->supports($locale)) {
            $files = glob($this->langPath . '/' . $locale . '/*.php') ?: [];
            sort($files);

            foreach ($files as $file) {
                $entries = require $file;

                if (is_array($entries)) {
                    $catalog = array_replace($catalog, $entries);
                }
            }
        }

        return $this->catalogs[$locale] = $catalog;
    }
}
