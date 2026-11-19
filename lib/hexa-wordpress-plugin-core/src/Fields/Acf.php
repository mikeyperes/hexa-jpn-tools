<?php

namespace Hexa\PluginCore\Fields;

/** Compatibility status for callers of the former field-engine selector. */
final class Acf {
    /** Reports whether ACF owns the Core field engine. */
    public static function active(): bool {
        return false;
    }

    /** Core field reads are safe before field registration. */
    public static function ready(): bool {
        return true;
    }

    /** Returns the field engine selected by Core. */
    public static function mode(): string {
        return 'native';
    }
}
