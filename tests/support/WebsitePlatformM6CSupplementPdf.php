<?php
declare(strict_types=1);

/** Small valid uncompressed one-page PDF, with actual xref offsets. No external assets. */
final class M6CSupplementPdf
{
    public static function bytes():string
    {
        $content="BT /F1 12 Tf 36 72 Td (Synthetic approved price list) Tj ET\n";
        $objects=['<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 240 120] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream'];
        $pdf="%PDF-1.4\n";$offsets=[0];
        foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";
        foreach(array_slice($offsets,1) as $offset)$pdf.=sprintf("%010d 00000 n \n",$offset);
        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
    public static function section(int $asset):array
    {
        return ['component_key'=>'pricing_list','implementation_version'=>'1.0.0','variant_key'=>'link',
            'configuration_schema_version'=>1,'configuration'=>['label'=>'Approved prices','description'=>'Synthetic public document',
                'document_usage_key'=>'approved_prices'],'section_key'=>'approved_prices','sort_order'=>30,
            'assets'=>[['asset_id'=>$asset,'usage_key'=>'approved_prices']]];
    }
    public static function verify(string $root,array $manifest,string $pdf,callable $check):void
    {
        $relative='public/assets/'.hash('sha256',$pdf).'.pdf';
        $entries=array_values(array_filter($manifest['files'],static fn($e)=>$e['relative_path']===$relative));
        $check(count($entries)===1&&$entries[0]['mime_type']==='application/pdf'
            &&$entries[0]['byte_size']===strlen($pdf)&&$entries[0]['sha256']===hash('sha256',$pdf),'pdf_manifest');
        $check(file_get_contents($root.'/'.$relative)===$pdf,'pdf_exact_bytes');
        $html=file_get_contents($root.'/public/index.html');
        $check(str_contains($html,'<a href="/'.substr($relative,7).'">Approved prices</a>')
            &&!str_contains($html,'<img src="/'.substr($relative,7).'"'),'pdf_document_link_not_image');
    }
}
