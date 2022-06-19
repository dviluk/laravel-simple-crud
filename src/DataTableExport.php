<?php

namespace Dviluk\LaravelSimpleCrud;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataTableExport implements FromArray, WithMapping, WithHeadings, ShouldAutoSize, WithTitle, WithStyles
{
    private $items;
    private $columns;
    private $labels;
    private $name;
    private $mapColumns;

    public function  __construct(array $items, array $columns, array $labels, string $name, array $mapColumns = [])
    {
        $this->items = $items;
        $this->columns = $columns;
        $this->labels = $labels;
        $this->name = $name;
        $this->mapColumns = $mapColumns;
    }

    public function styles(Worksheet $sheet)
    {
        $columns = count($this->columns);
        $sheet->mergeCellsByColumnAndRow(1, 1, $columns, 1);

        $sheet->getStyleByColumnAndRow(1, 1, $columns, 2)->getFont()->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    public function array(): array
    {
        return $this->items;
    }

    public function map($row): array
    {
        $item = [];

        foreach ($this->columns as $col) {
            if (array_key_exists($col, $this->mapColumns)) {
                $item[] = $row[$this->mapColumns[$col]];
            } else {
                $item[] = $row[$col];
            }
        }

        return $item;
    }

    public function headings(): array
    {
        $labels = [];

        foreach ($this->columns as $col) {
            $label = $this->labels[$col] ?? $col;

            $labels[] = __('labels.' . $label);
        }

        return [
            [$this->name,],
            $labels,
        ];
    }

    public function title(): string
    {
        return $this->name;
    }
}
