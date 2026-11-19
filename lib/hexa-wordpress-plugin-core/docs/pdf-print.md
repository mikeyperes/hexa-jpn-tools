# PDF / print
Namespace: `Hexa\PluginCore\PdfPrint`. Folder: `src/PdfPrint`.

`PageSize` accepts inches (0.25 to 48), rejects non-finite values and converts each axis independently to PDF points (72/inch). `HtmlDocument` renders 1 to 500 trusted HTML items, one per physical page, to browser print or PDF. PDF uses host-supplied Dompdf with remote retrieval, PHP and JavaScript disabled. This is a CSS 2.1 HTML renderer, not a browser JavaScript execution engine.

Hosts own authentication, nonce/capability checks, escaping, content, copies, resource budgets, response headers and private delivery. Never accept arbitrary untrusted HTML or CSS through a public endpoint. Images should be trusted data URIs. Hosts bundle Dompdf and its license with Composer and load its autoloader.

Example: `$document = new HtmlDocument(new PageSize(2.25, 1.25)); $bytes = $document->pdf(['<h1>Example</h1>']);`. No WordPress or host-specific knowledge is needed.

Test: `tests/pdf-print-test.php`, with the consuming host autoloader, verifies dimensions, bounds, multiple pages and PDF MediaBox.
