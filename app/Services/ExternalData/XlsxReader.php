<?php

namespace App\Services\ExternalData;

/**
 * Minimal .xlsx reader (shared and inline strings) using ZipArchive and
 * SimpleXML so the sync commands need no extra Composer dependency.
 */
class XlsxReader
{
    private \ZipArchive $zip;

    /** @var list<string> */
    private array $shared = [];

    /** @var array<string, string> sheet name => zip entry */
    private array $sheets = [];

    public function __construct(string $path)
    {
        $this->zip = new \ZipArchive;
        if ($this->zip->open($path) !== true) {
            throw new \RuntimeException("Cannot open workbook {$path}");
        }
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        if (($ss = $this->zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $xml = new \SimpleXMLElement($ss);
            $xml->registerXPathNamespace('m', $ns);
            foreach ($xml->xpath('//m:si') as $si) {
                $si->registerXPathNamespace('m', $ns);
                $this->shared[] = implode('', array_map('strval', $si->xpath('.//m:t')));
            }
        }
        $wb = new \SimpleXMLElement((string) $this->zip->getFromName('xl/workbook.xml'));
        $rels = new \SimpleXMLElement((string) $this->zip->getFromName('xl/_rels/workbook.xml.rels'));
        $targets = [];
        foreach ($rels->Relationship as $rel) {
            $targets[(string) $rel['Id']] = ltrim(str_replace('/xl/', '', (string) $rel['Target']), '/');
        }
        $wb->registerXPathNamespace('m', $ns);
        foreach ($wb->xpath('//m:sheets/m:sheet') as $sheet) {
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $this->sheets[(string) $sheet['name']] = 'xl/'.($targets[$rid] ?? '');
        }
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_keys($this->sheets);
    }

    /**
     * Rows as zero-indexed column arrays (A => 0). Empty cells are ''.
     *
     * @return list<list<string>>
     */
    public function rows(string $sheetName): array
    {
        $entry = $this->sheets[$sheetName] ?? throw new \RuntimeException("Sheet {$sheetName} not found");
        $xml = new \SimpleXMLElement((string) $this->zip->getFromName($entry));
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $xml->registerXPathNamespace('m', $ns);
        $rows = [];
        foreach ($xml->xpath('//m:sheetData/m:row') as $row) {
            $cells = [];
            $max = -1;
            foreach ($row->c as $c) {
                $ref = preg_replace('/\d/', '', (string) $c['r']);
                $idx = 0;
                foreach (str_split($ref) as $ch) {
                    $idx = $idx * 26 + (ord($ch) - 64);
                }
                $idx--;
                // Excel stops at column XFD (16,384). A reference past it, or a malformed
                // one, would otherwise make the padding loop below allocate without bound.
                if ($idx < 0 || $idx >= 16384) {
                    continue;
                }
                $type = (string) $c['t'];
                if ($type === 'inlineStr') {
                    $c->registerXPathNamespace('m', $ns);
                    $value = implode('', array_map('strval', $c->xpath('.//m:t')));
                } elseif ($type === 's') {
                    $value = $this->shared[(int) $c->v] ?? '';
                } else {
                    $value = (string) $c->v;
                }
                $cells[$idx] = $value;
                $max = max($max, $idx);
            }
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[] = $line;
        }

        return $rows;
    }

    public static function excelDate(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }
        if (is_numeric($value)) {
            return (new \DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d');
        }

        return '';
    }
}
