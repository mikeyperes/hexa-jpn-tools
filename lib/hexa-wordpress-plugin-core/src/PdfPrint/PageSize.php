<?php
namespace Hexa\PluginCore\PdfPrint;
/** Physical page dimensions, in inches, independent of orientation heuristics. */
final class PageSize {
    public function __construct(public readonly float $width, public readonly float $height) {
        foreach ([$width, $height] as $value) {
            if (!is_finite($value) || $value < 0.25 || $value > 48) {
                throw new \InvalidArgumentException('Page dimensions must be between 0.25 and 48 inches.');
            }
        }
    }
    public function points(): array { return [0.0, 0.0, $this->width * 72, $this->height * 72]; }
    public function css(): string { return sprintf('@page{size:%.6fin %.6fin;margin:0}html,body{margin:0;padding:0}', $this->width, $this->height); }
}
