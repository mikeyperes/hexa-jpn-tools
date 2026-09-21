<?php

namespace Hexa\PluginCore\LogFiles;

final class ErrorLogClassifier {
    public static function level( string $line ): string {
        if ( preg_match( '/\b(fatal\s+error|fatal\s*:|php\s+fatal|syntax\s+error|parse\s+error)/i', $line ) ) {
            return 'fatal';
        }

        if ( preg_match( '/\b(warning\s*:|php\s+warning)/i', $line ) ) {
            return 'warning';
        }

        if ( preg_match( '/\b(notice\s*:|deprecated\s*:|php\s+notice|php\s+deprecated)/i', $line ) ) {
            return 'notice';
        }

        return 'info';
    }
}
