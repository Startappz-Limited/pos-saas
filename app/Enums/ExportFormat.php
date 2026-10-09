<?php

namespace App\Enums;

enum ExportFormat: string
{
    case PDF = 'pdf';
    case EXCEL = 'excel';
    case CSV = 'csv';
    case JSON = 'json';

    public function label(): string
    {
        return match ($this) {
            self::PDF => 'PDF Document',
            self::EXCEL => 'Excel Spreadsheet',
            self::CSV => 'CSV File',
            self::JSON => 'JSON Data',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::PDF => 'pdf',
            self::EXCEL => 'xlsx',
            self::CSV => 'csv',
            self::JSON => 'json',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::PDF => 'application/pdf',
            self::EXCEL => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::CSV => 'text/csv',
            self::JSON => 'application/json',
        };
    }
}
