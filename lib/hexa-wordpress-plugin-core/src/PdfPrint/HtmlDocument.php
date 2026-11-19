<?php
namespace Hexa\PluginCore\PdfPrint;
/** Trusted host HTML only. Hosts own authorization, content escaping and item markup. */
final class HtmlDocument {
    public function __construct(private PageSize $size) {}
    public function html(array $items, string $css = '', bool $controls = false): string {
        if (!$items || count($items) > 500) { throw new \InvalidArgumentException('Supply 1 to 500 printable items.'); }
        $style = $this->size->css() . '.hexa-print-page{box-sizing:border-box;width:100%;page-break-after:always}.hexa-print-page:last-child{page-break-after:auto}@media print{.hexa-print-controls{display:none}}' . $css;
        $body = $controls ? '<div class="hexa-print-controls"><button type="button" onclick="window.print()">Print / Save as PDF</button><p>Use actual size (100%), no margins, and disable headers and footers.</p></div>' : '';
        foreach ($items as $item) { $body .= '<section class="hexa-print-page">' . $item . '</section>'; }
        return '<!doctype html><html><head><meta charset="utf-8"><title>Print document</title><style>' . $style . '</style></head><body>' . $body . '</body></html>';
    }
    /** Dompdf is supplied by the consuming host through Composer; Core never downloads code. */
    public function pdf(array $items, string $css = ''): string {
        if (!class_exists(\Dompdf\Dompdf::class)) { throw new \RuntimeException('PDF rendering requires dompdf/dompdf in the host dependency bundle.'); }
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('chroot', []);
        $options->set('defaultFont', 'Helvetica');
        $pdf = new \Dompdf\Dompdf($options);
        $pdf->setPaper($this->size->points());
        $pdf->loadHtml($this->html($items, $css), 'UTF-8');
        $pdf->render();
        return $pdf->output();
    }
}
