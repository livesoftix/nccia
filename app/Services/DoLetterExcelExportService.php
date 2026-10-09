<?php

namespace App\Services;

use App\Models\DoLetter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DoLetterExcelExportService
{
    public function download(DoLetter $letter): StreamedResponse
    {
        if (!class_exists(IOFactory::class)) {
            abort(503, 'Excel export requires phpoffice/phpspreadsheet. Run: composer install --no-dev');
        }

        $template = resource_path('templates/admin-reports/do-letter-template.xlsx');
        abort_unless(is_file($template), 500, 'D.O. Letter Excel template is missing on the server.');

        $p = $letter->payload ?? [];
        $monthShort = $this->monthShortLabel($letter->report_month);
        $circle = strtoupper($letter->circle?->name ?? 'LAHORE');

        $spreadsheet = IOFactory::load($template);

        $this->fillSheet1($spreadsheet->getSheetByName('P-1'), $p, $monthShort, $circle);
        $this->fillSheet2($spreadsheet->getSheetByName('P-2'), $p, $monthShort);
        $this->fillSheet3($spreadsheet->getSheetByName('P-3'), $p, $letter->month_label);
        $this->fillSheet4($spreadsheet, $p, $monthShort);

        $writer = new Xlsx($spreadsheet);
        $filename = 'DO-Letter-' . str_replace(' ', '-', $letter->month_label) . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function fillSheet1($sheet, array $p, string $monthShort, string $circle): void
    {
        $sheet->setCellValue('A1', "D.O. LETTER FOR THE MONTH OF {$monthShort}\n(CCRC-{$circle})");

        $cases = $p['cases'] ?? [];
        $sheet->setCellValue('A3', "At the beginning of {$monthShort}");
        $sheet->setCellValue('A4', $cases['at_beginning'] ?? 0);
        $sheet->setCellValue('B4', $cases['added'] ?? 0);
        $sheet->setCellValue('C4', $cases['total'] ?? 0);
        $sheet->setCellValue('D4', $cases['interim_challan'] ?? 0);
        $sheet->setCellValue('E4', $cases['final_challan'] ?? 0);
        $sheet->setCellValue('F4', $cases['discharge'] ?? 0);
        $sheet->setCellValue('G4', $cases['transferred'] ?? 0);
        $sheet->setCellValue('H4', $cases['merged'] ?? 0);
        $sheet->setCellValue('I4', $cases['pending'] ?? 0);

        $cfr = $p['cases_cfr_pendency'] ?? [];
        $sheet->setCellValue('A7', $cfr['total_cfrs'] ?? 0);
        $sheet->setCellValue('B7', $cfr['pending_ad_dd_legal'] ?? 0);
        $sheet->setCellValue('C7', $cfr['pending_forensic'] ?? 0);
        $sheet->setCellValue('D7', $cfr['pending_addl_director'] ?? 0);
        $sheet->setCellValue('E7', $cfr['pending_dd_office'] ?? 0);
        $sheet->setCellValue('F7', $cfr['pending_dir_ccw_isb'] ?? 0);
        $sheet->setCellValue('G7', $cfr['pending_dir_ops'] ?? 0);
        $sheet->setCellValue('H7', $cfr['pending_investigation'] ?? 0);
        $sheet->setCellValue('I7', $cfr['total_pending'] ?? 0);

        $enq = $p['enquiries'] ?? [];
        $sheet->setCellValue('A9', "At the beginning of {$monthShort}");
        $sheet->setCellValue('A10', $enq['at_beginning'] ?? 0);
        $sheet->setCellValue('B10', $enq['added'] ?? 0);
        $sheet->setCellValue('C10', $enq['total'] ?? 0);
        $sheet->setCellValue('D10', $enq['converted_case'] ?? 0);
        $sheet->setCellValue('E10', $enq['converted_qalandra'] ?? 0);
        $sheet->setCellValue('F10', $enq['closed'] ?? 0);
        $sheet->setCellValue('G10', $enq['transferred'] ?? 0);
        $sheet->setCellValue('H10', $enq['merged'] ?? 0);
        $sheet->setCellValue('I10', $enq['pending'] ?? 0);

        $ecfr = $p['enquiries_cfr_pendency'] ?? [];
        $sheet->setCellValue('A13', $ecfr['total_cfrs'] ?? 0);
        $sheet->setCellValue('B13', $ecfr['pending_ad_dd_legal'] ?? 0);
        $sheet->setCellValue('C13', $ecfr['pending_forensic'] ?? 0);
        $sheet->setCellValue('D13', $ecfr['pending_addl_director'] ?? 0);
        $sheet->setCellValue('E13', $ecfr['pending_dd_law'] ?? 0);
        $sheet->setCellValue('F13', $ecfr['pending_dir_ccw_isb'] ?? 0);
        $sheet->setCellValue('G13', $ecfr['pending_dy_director_ccrc'] ?? 0);
        $sheet->setCellValue('H13', $ecfr['pending_probe'] ?? 0);
        $sheet->setCellValue('I13', $ecfr['total_pending'] ?? 0);

        $ver = $p['verifications'] ?? [];
        $sheet->setCellValue('A15', "At the beginning of {$monthShort}");
        $sheet->setCellValue('A16', $ver['at_beginning'] ?? 0);
        $sheet->setCellValue('B16', $ver['added'] ?? 0);
        $sheet->setCellValue('C16', $ver['total'] ?? 0);
        $sheet->setCellValue('D16', $ver['converted_enquiries'] ?? 0);
        $sheet->setCellValue('E16', $ver['converted_case'] ?? 0);
        $sheet->setCellValue('F16', $ver['closed'] ?? 0);
        $sheet->setCellValue('G16', $ver['transferred'] ?? 0);
        $sheet->setCellValue('H16', $ver['merged'] ?? 0);
        $sheet->setCellValue('I16', $ver['pending'] ?? 0);

        $comp = $p['complaints'] ?? [];
        $sheet->setCellValue('A18', "At the beginning of {$monthShort}");
        $sheet->setCellValue('A19', $comp['at_beginning'] ?? 0);
        $sheet->setCellValue('B19', $comp['added'] ?? 0);
        $sheet->setCellValue('C19', ($comp['at_beginning'] ?? 0) + ($comp['added'] ?? 0));
        $sheet->setCellValue('D19', $comp['converted_verification'] ?? 0);
        $sheet->setCellValue('E19', $comp['invalid'] ?? 0);
        $sheet->setCellValue('F19', $comp['closed'] ?? 0);
        $sheet->setCellValue('G19', $comp['transferred'] ?? 0);
        $sheet->setCellValue('H19', $comp['merged'] ?? 0);
        $sheet->setCellValue('I19', $comp['pending'] ?? 0);

        $aml = $p['aml'] ?? [];
        $sheet->setCellValue('A22', $aml['cases_registered'] ?? 0);
        $sheet->setCellValue('B22', $aml['amla_added'] ?? 0);
        $sheet->setCellValue('D22', $aml['accused_arrested'] ?? 0);
        $sheet->setCellValue('E22', $aml['under_investigation'] ?? 0);
        $sheet->setCellValue('G22', $aml['under_trial'] ?? 0);
        $sheet->setCellValue('H22', $aml['total_conviction'] ?? 0);
        $sheet->setCellValue('I22', $aml['acquittals'] ?? 0);
    }

    private function fillSheet2($sheet, array $p, string $monthShort): void
    {
        $raid = $p['raid'] ?? [];
        $sheet->setCellValue('A3', $raid['total_raid'] ?? 0);
        $sheet->setCellValue('B3', $raid['cases_after_raid'] ?? 0);
        $sheet->setCellValue('C3', $raid['raid_in_cases'] ?? 0);
        $sheet->setCellValue('D3', $raid['enquiries_registered'] ?? 0);
        $sheet->setCellValue('E3', $raid['raid_in_enquiries'] ?? 0);
        $sheet->setCellValue('F3', $raid['cases_challaned'] ?? 0);
        $sheet->setCellValue('G3', $raid['pending_end_month'] ?? 0);

        $pos = $p['pos'] ?? [];
        $sheet->setCellValue('A5', "At the beginning of {$monthShort}");
        $sheet->setCellValue('A6', $pos['previous'] ?? 0);
        $sheet->setCellValue('B6', $pos['added'] ?? 0);
        $sheet->setCellValue('C6', ($pos['previous'] ?? 0) + ($pos['added'] ?? 0));
        $sheet->setCellValue('D6', $pos['arrested'] ?? 0);
        $sheet->setCellValue('E6', $pos['deleted'] ?? 0);
        $sheet->setCellValue('F6', $pos['pending'] ?? 0);

        $cas = $p['cas'] ?? [];
        $sheet->setCellValue('A8', "At the beginning of {$monthShort}");
        $sheet->setCellValue('A9', $cas['previous'] ?? 0);
        $sheet->setCellValue('B9', $cas['added'] ?? 0);
        $sheet->setCellValue('C9', ($cas['previous'] ?? 0) + ($cas['added'] ?? 0));
        $sheet->setCellValue('D9', $cas['arrested'] ?? 0);
        $sheet->setCellValue('E9', $cas['deleted'] ?? 0);
        $sheet->setCellValue('F9', $cas['pending'] ?? 0);

        $court = $p['court_work'] ?? [];
        $sheet->setCellValue('A11', "At the beginning of {$monthShort}");
        $sheet->setCellValue('A12', $court['at_beginning'] ?? 0);
        $sheet->setCellValue('B12', $court['added'] ?? 0);
        $sheet->setCellValue('C12', ($court['at_beginning'] ?? 0) + ($court['added'] ?? 0));
        $sheet->setCellValue('D12', $court['convicted'] ?? 0);
        $sheet->setCellValue('E12', $court['acquittal'] ?? 0);
        $sheet->setCellValue('F12', $court['cc_to_record'] ?? 0);
        $sheet->setCellValue('G12', $court['pending'] ?? 0);

        $convictions = $p['convictions'] ?? [];
        foreach ($convictions as $i => $row) {
            $r = 15 + $i;
            if ($r > 21) {
                break;
            }
            $sheet->setCellValue('A' . $r, $i + 1);
            $sheet->setCellValue('B' . $r, $row['fir_and_section'] ?? '');
            $sheet->setCellValue('C' . $r, $row['accused_name'] ?? '');
            $sheet->setCellValue('D' . $r, $row['court_name'] ?? '');
            $sheet->setCellValue('E' . $r, $row['order'] ?? '');
            $sheet->setCellValue('F' . $r, $row['dated'] ?? '');
        }

        $quality = $p['conviction_quality'] ?? [];
        $qualityRows = [
            23 => 'less_than_4_months',
            24 => '4_to_6_months',
            25 => '6_months_to_1_year',
            26 => '1_to_2_years',
            27 => '2_to_4_years',
            28 => '4_to_5_years',
            29 => 'more',
            30 => 'only_fine',
        ];
        foreach ($qualityRows as $rowNum => $key) {
            $sheet->setCellValue('B' . $rowNum, $quality[$key] ?? 0);
        }
        $sheet->setCellValue('B31', $quality['total'] ?? 0);

        $recoveries = $p['recoveries'] ?? [];
        foreach ($recoveries as $i => $row) {
            $r = 35 + $i;
            if ($r > 49) {
                break;
            }
            $sheet->setCellValue('A' . $r, $i + 1);
            $sheet->setCellValue('B' . $r, $row['enquiry_no_dated'] ?? '');
            $sheet->setCellValue('C' . $r, $row['eo_name'] ?? '');
            $sheet->setCellValue('D' . $r, $row['amount_recovered'] ?? '');
            $sheet->setCellValue('E' . $r, $row['deleted'] ?? '');
            $sheet->setCellValue('F' . $r, $row['kind_fine'] ?? '');
            $sheet->setCellValue('G' . $r, $row['kind_cash'] ?? '');
        }
    }

    private function fillSheet3($sheet, array $p, ?string $monthLabel): void
    {
        $sheet->setCellValue('B1', 'Accused Arrested in ' . ($monthLabel ?? ''));
        $rows = $p['accused_arrested'] ?? [];
        foreach ($rows as $i => $row) {
            $r = 3 + $i;
            if ($r > 50) {
                break;
            }
            $sheet->setCellValue('A' . $r, $i + 1);
            $sheet->setCellValue('B' . $r, $row['fir_no'] ?? '');
            $sheet->setCellValue('C' . $r, $row['accused_name'] ?? '');
            $sheet->setCellValue('D' . $r, $row['arrest_date'] ?? '');
            $sheet->setCellValue('E' . $r, $row['address'] ?? '');
        }
    }

    /**
     * P-4 is not part of the official template, so the sheet is created here.
     * Pages P-1..P-3 stay exactly as prescribed.
     */
    private function fillSheet4($spreadsheet, array $p, string $monthShort): void
    {
        $rows = $p['complaints_registered'] ?? [];
        if (empty($rows)) {
            return;
        }

        $sheet = $spreadsheet->getSheetByName('P-4') ?? $spreadsheet->createSheet();
        $sheet->setTitle('P-4');

        $headers = ['S.No', 'Tracking No', 'Diary No', 'Dated', 'Complainant', 'CNIC', 'Contact No',
            'Offence Type', 'Laws', 'Amount Involved', 'Received Via', 'Priority', 'Operator',
            'Scrutiny Result', 'Status', 'Final Status'];
        $widths = [6, 14, 12, 11, 22, 15, 13, 18, 18, 12, 13, 10, 14, 20, 12, 14];

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $lastRow = count($rows) + 2;
        $borders = [
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF64748B']],
            ],
        ];

        $sheet->setCellValue('A1', "COMPLAINTS REGISTERED IN {$monthShort}");
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        foreach ($headers as $i => $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '2', $h);
        }
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray($borders + [
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(30);

        foreach ($rows as $i => $row) {
            $r = $i + 3;
            $sheet->setCellValue('A' . $r, $i + 1);
            foreach ([
                $row['tracking_no'] ?? '', $row['diary_no'] ?? '', $row['dated'] ?? '',
                $row['complainant'] ?? '', $row['cnic'] ?? '', $row['contact_no'] ?? '',
                $row['offence_type'] ?? '', $row['laws'] ?? '', $row['amount_involved'] ?? '',
                $row['received_via'] ?? '', $row['priority_type'] ?? '', $row['operator_name'] ?? '',
                $row['scrutiny_result'] ?? '', $row['status'] ?? '', $row['final_status'] ?? '',
            ] as $c => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + 2) . $r, $value);
            }
        }
        $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray($borders + [
            'font' => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);

        foreach ($widths as $i => $w) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($w);
        }

        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(2, 2);
        $sheet->getPageSetup()->setOrientation('landscape');
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getSheetView()->setZoomScale(90);
    }

    private function monthShortLabel($date): string
    {
        return strtoupper(\Illuminate\Support\Carbon::parse($date)->format('M-y'));
    }
}
