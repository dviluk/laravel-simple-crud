<?php

namespace Dviluk\LaravelSimpleCrud\Utils;

use Dompdf\Dompdf;
use Dompdf\Options;

class HTMLToPDF
{
    public const DEFAULT_NAME = 'document.pdf';

    /**
     * Recibe como parametro el HTML.
     * 
     * @param mixed $html 
     * @return void 
     * @throws \Dompdf\Exception 
     */
    static public function fromHTML($html, $filename = 'document.pdf', $orientation = 'portrait')
    {
        $pdfOptions = new Options;
        $pdfOptions->setIsRemoteEnabled(true);
        $pdfOptions->setDefaultPaperOrientation($orientation);

        $pdf = new Dompdf($pdfOptions);

        // return $html;
        ini_set("allow_url_fopen", 1);

        // FIX: Elimina la ultima pagina en blanco
        $html = preg_replace('/>\s+</', "><", $html);

        $pdf->loadHtml($html);

        $pdf->render();

        $font = $pdf->getFontMetrics()->getFont("sans-serif", "bold");

        if ($orientation === 'portrait') {
            $pdf->getCanvas()->page_text(560, 740, "{PAGE_NUM} / {PAGE_COUNT}", $font, 10, array(0, 0, 0));
        } else {
            $pdf->getCanvas()->page_text(740, 560, "{PAGE_NUM} / {PAGE_COUNT}", $font, 10, array(0, 0, 0));
        }

        $pdf->stream($filename);
    }

    /**
     * Permite imprimir una vista blade php.
     * 
     * @param string $view 
     * @param mixed $data 
     * @return void 
     * @throws \Illuminate\Contracts\Container\BindingResolutionException 
     * @throws \Throwable 
     * @throws \Dompdf\Exception 
     */
    static function fromView(string $view, array $data, array $options = [])
    {
        $filename = $options['filename'] ?? self::DEFAULT_NAME;
        $orientation = $options['orientation'] ?? null;

        $htmlMode = request()->htmlMode ?? false;

        $viewObject = view($view)->with($data);

        if ($htmlMode) {
            return view($view)->with($data);
        }

        self::fromHTML($viewObject->render(), $filename, $orientation);
    }
}
