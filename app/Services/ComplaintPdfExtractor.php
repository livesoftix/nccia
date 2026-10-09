<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ComplaintPdfExtractor
{
    /**
     * @return array{ok: bool, data: array<string, mixed>, used_ocr: bool, page_count: ?int, error: ?string}
     */
    public function extract(string $absolutePdfPath, string $originalFilename, ?string $ocrText = null): array
    {
        if ($ocrText !== null && trim($ocrText) !== '') {
            $data = $this->parseVerificationReportText($ocrText, $originalFilename);
            $data['used_ocr'] = true;

            return [
                'ok'         => $this->hasMinimumFields($data),
                'data'       => $data,
                'used_ocr'   => true,
                'page_count' => 1,
                'error'      => $this->hasMinimumFields($data) ? null : 'OCR text parsed but CNIC/name not found.',
            ];
        }

        $python = $this->pythonBinary();
        $script = base_path('scripts/nccia_pdf_extract.py');
        $pythonError = null;

        if (!is_file($script)) {
            return ['ok' => false, 'data' => [], 'used_ocr' => false, 'page_count' => null, 'error' => 'PDF extract script missing'];
        }

        if (!$python) {
            $pythonError = 'Python not found. Server par install karein: python3, pymupdf, tesseract. .env mein PDF_EXTRACT_PYTHON=/usr/bin/python3';
        } else {
            $result = Process::timeout(180)
                ->env($this->pythonProcessEnv($python))
                ->run([
                    $python,
                    $script,
                    $absolutePdfPath,
                    (string) (int) env('PDF_OCR_MAX_PAGES', 3),
                ]);

            if ($result->successful()) {
                $decoded = json_decode(trim($result->output()), true);
                if (is_array($decoded) && empty($decoded['error'])) {
                    $decoded['inquiry_no'] = $decoded['inquiry_no']
                        ?? $this->inquiryRefFromFilename($originalFilename);

                    if ($this->hasMinimumFields($decoded)) {
                        return [
                            'ok'         => true,
                            'data'       => $decoded,
                            'used_ocr'   => (bool) ($decoded['used_ocr'] ?? false),
                            'page_count' => isset($decoded['page_count']) ? (int) $decoded['page_count'] : null,
                            'error'      => null,
                        ];
                    }

                    $preview = Str::limit((string) ($decoded['raw_text_preview'] ?? ''), 180);
                    $pythonError = 'OCR/text se CNIC ya naam nahi mila.'
                        . ((empty($decoded['used_ocr'])) ? ' OCR nahi chala (tesseract missing?).' : ' OCR chala lekin fields weak hain.')
                        . ($preview !== '' ? ' Preview: ' . $preview : '');
                } elseif (is_array($decoded) && !empty($decoded['error'])) {
                    $pythonError = 'Python extract error: ' . $decoded['error'];
                    Log::warning('PDF extract script error', ['error' => $decoded['error'], 'file' => $originalFilename]);
                } else {
                    $pythonError = 'Python extract ne JSON nahi diya: ' . Str::limit($result->output() . ' ' . $result->errorOutput(), 400);
                }
            } else {
                $pythonError = 'Python extract failed (' . $python . '): '
                    . Str::limit(trim($result->errorOutput() . "\n" . $result->output()), 500);
                Log::warning('PDF extract process failed', [
                    'file'   => $originalFilename,
                    'stderr' => Str::limit($result->errorOutput(), 500),
                ]);
            }
        }

        $fallback = $this->extractWithPhpPatterns($absolutePdfPath, $originalFilename);

        if ($this->hasMinimumFields($fallback)) {
            return [
                'ok'         => true,
                'data'       => $fallback,
                'used_ocr'   => false,
                'page_count' => null,
                'error'      => null,
            ];
        }

        $node = $this->extractWithNodeOcr($absolutePdfPath, $originalFilename);
        if ($this->hasMinimumFields($node['data'] ?? [])) {
            return $node;
        }
        if (!empty($node['error'])) {
            $pythonError = trim(($pythonError ? $pythonError . ' | ' : '') . $node['error']);
        }

        return [
            'ok'         => false,
            'data'       => $fallback,
            'used_ocr'   => false,
            'page_count' => null,
            'error'      => $pythonError ?: 'Could not extract PDF text. Disk quota conda ke liye kam hai — bash scripts/setup-ocr-lite.sh chalao (Node tesseract.js).',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractWithPhpPatterns(string $absolutePdfPath, string $originalFilename): array
    {
        $text = $this->readPdfText($absolutePdfPath);
        if ($text === '') {
            return $this->filenameOnlyPayload($originalFilename);
        }

        return $this->parseVerificationReportText($text, $originalFilename);
    }

    private function readPdfText(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return '';
        }

        $chunks = [];
        if (preg_match_all('/\(([^()\\\\]*(?:\\\\.|[^()\\\\])*)?\)/s', $content, $matches)) {
            foreach ($matches[1] as $part) {
                $part = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $part);
                $chunks[] = $part;
            }
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $chunks)) ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function parseVerificationReportText(string $text, string $originalFilename): array
    {
        $text = $this->normalizeOcrText($text);

        $clean = function (?string $val): ?string {
            if ($val === null) return null;
            $val = trim($val);
            $val = preg_replace('/^[\s\.\:\-\,\;]+|[\s\.\:\-\,\;]+$/u', '', $val);
            $val = trim(preg_replace('/\s+/', ' ', $val) ?? '');
            return $val !== '' ? $val : null;
        };

        $pick = function (array $patterns) use ($text, $clean): ?string {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text, $m)) {
                    $val = $clean($m[1] ?? '');
                    if ($val !== null && $val !== '') {
                        return $val;
                    }
                }
            }

            return null;
        };

        $trackingNo = $pick([
            '/Tracking\s*No[\s\.\:\-]+([A-Z0-9][A-Z0-9\-\/]+)/i',
            '/VERIFICATION\s*REPORT[\s\S]{0,100}?No[\s\.\:\-]+([A-Z0-9][A-Z0-9\-\/]+)/i',
            '/\b(CCW-[A-Z0-9\-\/]+)\b/i',
            '/No[\s\.\:\-]+(CCW-[A-Z0-9\-\/]+)/i',
            '/(?:Tracking|Ref)[\s\.\:\-]+(\d{4,8}\/\d{2,4})/i',
        ]);

        $verificationDateRaw = $pick([
            '/Verification\s*Date[\s\.\:\-]+([\d\-\/\.]{8,12})/i',
            '/Date\s*of\s*Verification[\s\.\:\-]+([\d\-\/\.]{8,12})/i',
        ]);

        $assignmentDateRaw = $pick([
            '/Assignment\s*Date[\s\.\:\-]+([\d\-\/\.]{8,12})/i',
            '/Date\s*of\s*Assignment[\s\.\:\-]+([\d\-\/\.]{8,12})/i',
        ]);

        $fullName = $pick([
            '/COMPLAINANT\s*DETAILS[\s\S]{0,80}?Name[\s\.\:\-]+(.+?)(?:Gender|CNIC|Occupation|Contact|Mobile|$)/is',
            '/Name[\s\.\:\-]+(.+?)(?:Gender|CNIC|Occupation|Contact|Mobile|$)/is',
            '/Name\s*:?\s*(.+?)\s+Gender\s*:/is',
            '/(?:^|\s)Name[\s\.\:\-]+([A-Za-z][A-Za-z\s\/\.]+?)\s+Gender/is',
        ]);
        if (!$fullName) {
            $fullName = $this->findNameFromSoPattern($text);
        }

        [$victimName, $fatherName] = $this->splitNameFather($fullName);
        $cnicRaw = $pick([
            '/CNIC\s*No[\s\.\:\-]+([\d\-]{13,17})/i',
            '/CNIC\s*No[\s\.\:\-]+([\d]{13})/i',
            '/CNIC[\s\.\:\-]+([\d\-]{13,17})/i',
            '/CNIC[\s\.\:\-]+([\d]{13})/i',
        ]) ?? $this->findCnicInText($text);

        $phoneRaw = $pick([
            '/Mobile\s*Number[\s\.\:\-]+([\d\-\s]+)/i',
            '/Contact\s*Details[\s\S]{0,40}?Mobile\s*Number[\s\.\:\-]+([\d\-\s]+)/i',
            '/Contact\s*Details[\s\.\:\-]+([\d\-\s]+)/i',
            '/Contact\s*(?:No\.?|Number)?[\s\.\:\-]+(0?3\d{9})/i',
            '/\b(0?3\d{2}[\-\s]?\d{7})\b/',
        ]);

        $gender = strtolower($pick(['/Gender[\s\.\:\-]+(Male|Female|Other)/i']) ?? '') ?: null;
        if (!$gender && preg_match('/\bMale\b/i', $text)) {
            $gender = 'male';
        } elseif (!$gender && preg_match('/\bFemale\b/i', $text)) {
            $gender = 'female';
        }

        $occupation = $pick([
            '/Occupation[\s\.\:\-]+(.+?)(?:Contact|Mobile|Address|Email|Details|Addresses|$)/is',
        ]);

        $address = $pick([
            '/Current\s*Address[\s\.\:\-]+(.+?)(?:Permanent|BRIEF|Brief|RECOMMEND|Online|$)/is',
            '/Addresses[\s\.\:\-]+Current\s*Address[\s\.\:\-]+(.+?)(?:Permanent|BRIEF|Brief|Online|$)/is',
        ]);

        $permanentAddress = $pick([
            '/Permanent\s*Address[\s\.\:\-]+(.+?)(?:BRIEF|Brief|RECOMMEND|Online|Contact|$)/is',
        ]);

        $email = $pick([
            '/E-?mail\s*(?:Address)?[\s\.\:\-]+([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i',
            '/\b([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})\b/i',
        ]);

        $crimeCategory = $pick([
            '/BRIEF\s*DESCRIPTION\s*&\s*RECOMMENDATIONS?\s*[\n\r]+([^\n\r]+)/is',
            '/BRIEF\s*DESCRIPTION\s*OF\s*(?:THE\s*)?CASE[\s\.\:\-]+(.+?)(?:accuse|Accuse|During|Online|City|$)/is',
            '/Crime\s*Categor(?:y|ies)[\s\.\:\-]+(.+?)(?:accuse|City|Amount|$)/is',
            '/(Online\s+Job\s+Frauds)/i',
        ]);

        $crimeDescription = $pick([
            '/(accuse[d]?\s+defrauded.+?)(?:City|Amount|0f Occurrence|RECOMMEND|\n\s*\*|$)/is',
            '/BRIEF\s*DESCRIPTION[\s\S]{0,80}?((?:During|The|accuse).+?)(?:City\s*of|Amount\s*Involved|RECOMMEND|$)/is',
        ]);

        $city = $pick([
            '/City\s*of\s*Occurrence[\s\.\:\-]+(.+?)(?:Complaint|Amount|RECOMMEND|BRIEF|$)/is',
            '/0f\s*Occurrence[\s\.\:\-]+(.+?)(?:Complaint|Amount|working|RECOMMEND|BRIEF|$)/is',
        ]);

        $amountRaw = $pick([
            '/Amount\s*Involved[\s\.\:\-]+(?:Rs\.?\s*)?([\d,\.]+)/i',
            '/defrauded\s+Rs\.?\s*([\d,\.]+)/i',
            '/Rs\.?\s*([\d,]{4,}(?:\.\d+)?)/i',
        ]);

        $recommendationShort = $pick([
            '/(?:^|\n)\s*RECOMMENDATIONS?\s*[\n\r]+\s*([A-Za-z\s]{5,80})/is',
            '/RECOMMENDATIONS?[\s\.\:\-]+(Permission[^\n\r]+)/i',
            '/RECOMMENDATIONS?[\s\.\:\-]+([A-Za-z\s]{5,80})/is',
            '/(Permission\s+to\s+Register\s+Enquiry)/i',
        ]);

        $recommendationFull = $pick([
            '/(During\s+the\s+course\s+of\s+verification.+?converted\s+into\s+a\s+regular\s+Enquiry[\.]?)/is',
            '/Justification[\s\.\:\-]+(.+?)(?:Reporting Officer|Assistant Sub|Signature|$)/is',
        ]);

        $reportingOfficer = $pick([
            '/([A-Z\s]{3,30})\s*[\n\r]+\s*(?:Assistant Sub Inspector|Sub Inspector|Inspector|ASI|SI|ASP|DSP)/i',
            '/(?:Assistant Sub Inspector|Sub Inspector|Inspector|ASI|SI|ASP|DSP)[\s\.\:\-]+([A-Za-z\s\.]{2,40})/i',
            '/Reporting\s*Officer[\s\.\:\-]+([A-Za-z\s\.]{2,40})/i',
        ]);

        $inquiryNo = $pick([
            '/\b(E\s*[\/\-]\s*\d+\s*[\/\-]\s*\d+)\b/i',
            '/Inquiry\s*No[\s\.\:\-]+(E[\/\-]?\s*\d+\s*[\/\-]\s*\d+)/i',
            '/Inquiry\s*No[\s\.\:\-]+([A-Z0-9\/\-]+)/i',
        ]) ?? $this->inquiryRefFromFilename($originalFilename);

        $accusedList = $this->extractAccusedListFromText($text);
        $primaryAccused = $accusedList[0] ?? [];

        $accusedName = $primaryAccused['name'] ?? $pick([
            '/Accuse[d]?\s*(?:Name|Person)?[\s\.\:\-]+(.+?)(?:CNIC|Mobile|Address|City|Amount|$)/is',
            '/against\s+(?:the\s+)?accuse[d]?\s+([A-Za-z][A-Za-z\s\.\/]{2,60})/i',
        ]);
        $accusedCnic = $primaryAccused['cnic'] ?? $this->normalizeCnic($pick([
            '/Accuse[d]?[^\n]{0,40}CNIC\s*(?:No\.?)?[\s\.\:\-]+([\d\-]+)/i',
        ]));
        $accusedPhone = $primaryAccused['mobile_no'] ?? $primaryAccused['phone'] ?? $this->normalizePhone($pick([
            '/Accuse[d]?[^\n]{0,60}(?:Mobile|Phone|Contact)\s*(?:No\.?|Number)?[\s\.\:\-]+([\d\-]+)/i',
            '/واٹس\s*ایپ\s*نمبر[\s\:\-]+(0?3\d{9})/u',
        ]));

        $data = array_filter([
            'document_type'        => 'verification_report',
            'tracking_no'          => $trackingNo,
            'verification_date'    => $this->normalizeDate($verificationDateRaw),
            'assignment_date'      => $this->normalizeDate($assignmentDateRaw),
            'victim_name'          => $victimName,
            'victim_father_name'   => $fatherName,
            'victim_gender'        => $gender,
            'victim_cnic'          => $this->normalizeCnic($cnicRaw),
            'victim_occupation'    => $clean($occupation),
            'victim_email'         => $email,
            'victim_phone'         => $this->normalizePhone($phoneRaw),
            'victim_address'       => $clean($address),
            'victim_permanent_address' => $clean($permanentAddress),
            'crime_category'       => $clean($crimeCategory),
            'crime_description'    => $clean($crimeDescription),
            'accused_name'         => $clean($accusedName),
            'accused_cnic'         => $accusedCnic,
            'accused_phone'        => $accusedPhone,
            'accused'              => !empty($accusedList) ? $accusedList : null,
            'initial_accused'      => !empty($accusedList) ? $accusedList : null,
            'city'                 => $clean($city),
            'amount_involved'      => $this->normalizeAmount($amountRaw),
            'recommendation_short' => $clean($recommendationShort),
            'recommendation_full'  => $clean($recommendationFull),
            'reporting_officer'    => $clean($reportingOfficer),
            'recommendation'       => $this->mapRecommendation($text),
            'inquiry_no'           => $inquiryNo,
            'source_filename'      => $originalFilename,
            'raw_text_preview'     => Str::limit($text, 8000),
        ], fn ($v) => $v !== null && $v !== '');

        if (!empty($data['inquiry_no'])) {
            $data['inquiry_no'] = preg_replace('/\s+/', '', (string) $data['inquiry_no']);
            $data['inquiry_no'] = preg_replace('/^E[\-\/]?(\d+)[\-\/](\d+)$/i', 'E/$1/$2', $data['inquiry_no']);
        }

        if (!empty($data['accused_name'])) {
            $accusedNameClean = trim((string) $data['accused_name']);
            if (preg_match('/^(defraud|during|the\s|online|city|amount|brief)/i', $accusedNameClean)
                || str_word_count($accusedNameClean) > 8) {
                unset($data['accused_name']);
            }
        }

        $score = 0;
        foreach (['tracking_no', 'victim_name', 'victim_cnic', 'victim_phone', 'crime_category', 'city', 'amount_involved', 'recommendation_short'] as $k) {
            if (!empty($data[$k])) {
                $score++;
            }
        }
        $data['confidence_score'] = $score;

        return $data;
    }

    public function hasMinimumFields(array $data): bool
    {
        return !empty($data['victim_cnic'])
            && !empty($data['victim_name'])
            && ($data['victim_cnic'] ?? '') !== '00000-0000000-0';
    }

    /**
     * @return array<string, mixed>
     */
    private function filenameOnlyPayload(string $originalFilename): array
    {
        return array_filter([
            'document_type'   => 'verification_report',
            'inquiry_no'      => $this->inquiryRefFromFilename($originalFilename),
            'source_filename' => $originalFilename,
            'confidence_score'=> 0,
        ]);
    }

    public function inquiryRefFromFilename(string $filename): ?string
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        if (preg_match('/^(\d+)-(\d+)$/', $stem, $m)) {
            return "E/{$m[1]}/{$m[2]}";
        }
        if (preg_match('/^E[\/\-]?(\d+)[\/\-](\d+)$/i', $stem, $m)) {
            return "E/{$m[1]}/{$m[2]}";
        }

        return null;
    }

    public function pythonBinary(): ?string
    {
        $home = rtrim((string) (getenv('HOME') ?: '/home/realerp'), '/');
        $configured = trim((string) env('PDF_EXTRACT_PYTHON', ''));
        $candidates = array_values(array_unique(array_filter([
            $configured,
            $home . '/miniconda3/bin/python',
            $home . '/miniconda3/bin/python3',
            $home . '/miniforge3/bin/python',
            $home . '/ocr-env/bin/python',
            '/opt/alt/python311/bin/python3',
            '/opt/alt/python310/bin/python3',
            '/opt/alt/python39/bin/python3',
            '/opt/alt/python38/bin/python3',
            'python3.12',
            'python3.11',
            'python3.10',
            'python3.9',
            'python3.8',
            'python3',
            'python',
            'py',
        ])));

        $best = null;
        $bestScore = -1.0;
        foreach ($candidates as $bin) {
            if (!$this->pythonWorks($bin)) {
                continue;
            }
            $score = $this->pythonVersionScore($bin);
            if ($score > $bestScore) {
                $best = $bin;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @return array<string, string>
     */
    public function pythonProcessEnv(?string $python = null): array
    {
        $python = $python ?: $this->pythonBinary();
        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
        if ($python) {
            $dir = dirname($python);
            if ($dir !== '' && $dir !== '.') {
                $path = $dir . PATH_SEPARATOR . $path;
            }
        }

        $env = ['PATH' => $path];
        $home = rtrim((string) (getenv('HOME') ?: ''), '/');
        foreach ([
            $python ? dirname($python) . '/../share/tessdata' : null,
            $home . '/miniconda3/share/tessdata',
            $home . '/ocr-env/share/tessdata',
        ] as $td) {
            if ($td && is_dir($td)) {
                $resolved = realpath($td);
                $env['TESSDATA_PREFIX'] = $resolved !== false ? $resolved : $td;
                break;
            }
        }

        return $env;
    }

    private function pythonWorks(string $bin): bool
    {
        try {
            return Process::timeout(10)->run([$bin, '--version'])->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function pythonVersionScore(string $bin): float
    {
        try {
            $code = <<<'PY'
import sys
score = sys.version_info[0] * 10 + sys.version_info[1]
try:
    import fitz
    score += 500
except:
    try:
        import pymupdf
        score += 500
    except:
        pass
try:
    import pytesseract
    score += 100
except:
    pass
print(score)
PY;
            $r = Process::timeout(10)->run([$bin, '-c', $code]);
            if (!$r->successful()) {
                return 0;
            }

            return (float) trim($r->output());
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function normalizeCnic(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $raw);
        if (strlen($digits) !== 13) {
            return trim($raw) ?: null;
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5, 7) . '-' . substr($digits, 12, 1);
    }

    private function normalizePhone(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $raw);
        if (str_starts_with($digits, '92') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        return $digits ?: null;
    }

    private function normalizeAmount(?string $raw): ?float
    {
        if (!$raw) {
            return null;
        }
        $num = (float) preg_replace('/[^\d.]/', '', $raw);

        return $num > 0 ? $num : null;
    }

    private function normalizeDate(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }
        $raw = trim($raw);
        $raw = preg_replace('/^[\s\.\:\-]+|[\s\.\:\-]+$/', '', $raw);
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd-m-y', 'd/m/y', 'd.m.Y', 'd.m.y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $raw);
            if ($dt) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function extractAccusedListFromText(string $text): array
    {
        $accused = [];
        $seen = [];

        // 1. Match bank transaction slip patterns: "To SOHAIL ABBAS PK69MUCB... MCB Paid Rs. 1,400,000"
        if (preg_match_all('/To\s+([A-Z\s]{3,35})\s+(PK\d{2}[A-Z0-9]{16,24}|\d{10,24})\s+(?:Bank\s+)?([A-Za-z\s]{3,25})?(?:[\s\S]*?(?:Paid|Debited|Amount)[\s\S]*?(?:Rs\.?|PKR)\s*([\d,]+))?/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = trim(preg_replace('/\s+/', ' ', $m[1]));
                $acc = trim($m[2]);
                $bank = trim($m[3] ?? '');
                $amount = !empty($m[4]) ? trim($m[4]) : null;

                if (preg_match('/^(abid\s+hussain|customer|service|status|bank|from|amount)/i', $name)) {
                    continue;
                }

                $key = strtolower($name . '_' . $acc);
                if (isset($seen[$key])) {
                    if ($amount && !empty($seen[$key]['amount'])) {
                        $seen[$key]['amount'] .= ', Rs. ' . $amount;
                    }
                    continue;
                }

                $entry = [
                    'name'        => $name,
                    'father_name' => '',
                    'cnic'        => '',
                    'mobile_no'   => '',
                    'phone'       => '',
                    'email'       => '',
                    'nationality' => 'Pakistani',
                    'bank_account'=> $acc,
                    'bank_name'   => $bank,
                    'description' => trim("Bank: {$bank} | Acc/IBAN: {$acc}" . ($amount ? " | Amount: Rs. {$amount}" : '')),
                ];
                $seen[$key] = $entry;
                $accused[] = $entry;
            }
        }

        // 2. Match customer service bank accounts: "Name=SOHAIL ABBAS Account=165072981005161 IBAN=PK69... MCB bank"
        if (preg_match_all('/Name[\s\=]+([A-Z\s]{3,35})\s+Account[\s\=]+(\d+)(?:\s+IBAN[\s\=]+(PK\d{2}[A-Z0-9]+))?(?:\s+([A-Za-z\s]+)bank)?/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = trim(preg_replace('/\s+/', ' ', $m[1]));
                $acc = trim($m[2]);
                $iban = trim($m[3] ?? '');
                $bank = trim($m[4] ?? '');

                $key = strtolower($name . '_' . ($iban ?: $acc));
                if (!isset($seen[$key])) {
                    $entry = [
                        'name'        => $name,
                        'father_name' => '',
                        'cnic'        => '',
                        'mobile_no'   => '',
                        'phone'       => '',
                        'email'       => '',
                        'nationality' => 'Pakistani',
                        'bank_account'=> $iban ?: $acc,
                        'bank_name'   => $bank,
                        'description' => trim("Bank: {$bank} | Acc: {$acc}" . ($iban ? " | IBAN: {$iban}" : '')),
                    ];
                    $seen[$key] = $entry;
                    $accused[] = $entry;
                }
            }
        }

        // 3. Match WhatsApp and platform profile names
        if (preg_match('/(?:Sailkot\s+Hira|Hira\s+Rehman)[^\d]{0,20}(\+?92[\s\-]?\d{10}|03\d{9})/i', $text, $m)) {
            $phone = $this->normalizePhone($m[1]);
            $key = 'hira_' . $phone;
            if (!isset($seen[$key])) {
                $entry = [
                    'name'        => 'Hira Rehman / Sailkot Hira',
                    'father_name' => '',
                    'cnic'        => '',
                    'mobile_no'   => $phone,
                    'phone'       => $phone,
                    'email'       => '',
                    'nationality' => 'Pakistani',
                    'description' => 'WhatsApp / Fraud App Operator (mallbyvip.com / BestBuy)',
                ];
                $seen[$key] = $entry;
                $accused[] = $entry;
            }
        }

        return $accused;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitNameFather(?string $fullName): array
    {
        if (!$fullName) {
            return [null, null];
        }
        if (preg_match('/^(.+?)\s+S\/O\s+(.+)$/i', $fullName, $m)) {
            return [trim($m[1]), trim($m[2])];
        }
        if (preg_match('/^(.+?)\s+D\/O\s+(.+)$/i', $fullName, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [trim($fullName), null];
    }

    private function normalizeOcrText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/CN1C/i', 'CNIC', $text) ?? $text;
        $text = preg_replace('/\bNarne\b/i', 'Name', $text) ?? $text;
        $text = preg_replace('/\bNarn e\b/i', 'Name', $text) ?? $text;
        $text = preg_replace('/Trackin\s*g/i', 'Tracking', $text) ?? $text;
        $text = preg_replace('/Occurren[oc]e/i', 'Occurrence', $text) ?? $text;
        $text = preg_replace('/Recommenda[tl]ions?/i', 'RECOMMENDATIONS', $text) ?? $text;
        // Keep newlines — collapsing them drops multi-line address / brief description
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function ocrToDigits(string $raw): string
    {
        return strtr($raw, [
            'O' => '0', 'o' => '0', 'Q' => '0',
            'I' => '1', 'l' => '1', '|' => '1',
            'S' => '5', 's' => '5',
            'B' => '8', 'Z' => '2',
        ]);
    }

    private function findNameFromSoPattern(string $text): ?string
    {
        if (preg_match('/([A-Za-z][A-Za-z\s\.]{2,40}?)\s+S\s*\/\s*O\s+([A-Za-z][A-Za-z\s\.]{2,40})/i', $text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1] . ' S/O ' . $m[2]) ?? '');
        }

        return null;
    }

    private function findCnicInText(string $text): ?string
    {
        if (preg_match('/CNIC[^\dOIlS]{0,30}(\d{13})/is', $text, $m)) {
            return $m[1];
        }

        if (preg_match('/CNIC[^\dOIlS]{0,30}([\dOIlS\-\s]{13,17})/is', $text, $m)) {
            $digits = $this->ocrToDigits(preg_replace('/\D/', '', $m[1]) ?? '');
            if (strlen($digits) === 13) {
                return $digits;
            }
        }

        if (preg_match('/\b(\d{5})[\s\-]?(\d{7})[\s\-]?(\d)\b/', $text, $m)) {
            return $m[1] . $m[2] . $m[3];
        }

        if (preg_match('/\b([\dOIlS]{5})[\s\-]?([\dOIlS]{7})[\s\-]?([\dOIlS])\b/', $text, $m)) {
            $digits = $this->ocrToDigits($m[1] . $m[2] . $m[3]);
            if (strlen($digits) === 13) {
                return $digits;
            }
        }

        return null;
    }

    private function mapRecommendation(string $text): ?string
    {
        $lower = strtolower($text);
        if (str_contains($lower, 'register enq') || str_contains($lower, 'register enquiry') || str_contains($lower, 'registration of enquiry') || str_contains($lower, 'permission to register')) {
            return 'enquiry_registration';
        }
        if (str_contains($lower, 'closure') || str_contains($lower, 'close')) {
            return 'closure';
        }
        if (str_contains($lower, 'merge')) {
            return 'merge';
        }
        if (str_contains($lower, 'transfer')) {
            return 'transfer';
        }

        return null;
    }

    /**
     * Lightweight OCR using Node tesseract.js (no conda / no sudo).
     *
     * @return array{ok: bool, data: array<string, mixed>, used_ocr: bool, page_count: ?int, error: ?string}
     */
    private function extractWithNodeOcr(string $absolutePdfPath, string $originalFilename): array
    {
        $node = $this->nodeBinary();
        $script = base_path('scripts/ocr-png.cjs');
        $nodeModules = rtrim((string) (getenv('HOME') ?: '/home/realerp'), '/') . '/nccia-ocr/node_modules';
        if (env('PDF_OCR_NODE_MODULES')) {
            $nodeModules = (string) env('PDF_OCR_NODE_MODULES');
        }

        if (!$node) {
            return ['ok' => false, 'data' => [], 'used_ocr' => false, 'page_count' => null, 'error' => 'Node.js nahi mila. cPanel → Setup Node.js, phir bash scripts/setup-ocr-lite.sh'];
        }
        if (!is_file($script)) {
            return ['ok' => false, 'data' => [], 'used_ocr' => false, 'page_count' => null, 'error' => 'scripts/ocr-png.cjs missing'];
        }
        if (!is_dir($nodeModules . '/tesseract.js')) {
            return ['ok' => false, 'data' => [], 'used_ocr' => false, 'page_count' => null, 'error' => 'tesseract.js missing. Run: bash scripts/setup-ocr-lite.sh'];
        }

        $maxPages = max(1, min(3, (int) env('PDF_OCR_MAX_PAGES', 2)));
        $chunks = [];
        for ($page = 0; $page < $maxPages; $page++) {
            $png = $this->renderPdfPageToPngFile($absolutePdfPath, $page);
            if (!$png) {
                break;
            }
            try {
                $result = Process::timeout(120)
                    ->env(['NODE_PATH' => $nodeModules, 'PATH' => dirname($node) . PATH_SEPARATOR . (getenv('PATH') ?: '/usr/bin')])
                    ->run([$node, $script, $png]);
                if ($result->successful()) {
                    $chunks[] = $result->output();
                }
            } finally {
                @unlink($png);
            }
        }

        $text = trim(implode("\n\n", $chunks));
        if ($text === '') {
            return ['ok' => false, 'data' => [], 'used_ocr' => false, 'page_count' => null, 'error' => 'Node OCR ne text nahi diya (PDF page render/Imagick/gs check karein).'];
        }

        $data = $this->parseVerificationReportText($text, $originalFilename);

        return [
            'ok'         => $this->hasMinimumFields($data),
            'data'       => $data,
            'used_ocr'   => true,
            'page_count' => count($chunks),
            'error'      => $this->hasMinimumFields($data) ? null : 'Node OCR chala lekin CNIC/name nahi mila. Preview: ' . Str::limit($text, 180),
        ];
    }

    public function nodeBinary(): ?string
    {
        $configured = trim((string) env('PDF_OCR_NODE', ''));
        $home = rtrim((string) (getenv('HOME') ?: '/home/realerp'), '/');
        foreach (array_filter([
            $configured,
            '/opt/cpanel/ea-nodejs20/bin/node',
            '/opt/cpanel/ea-nodejs18/bin/node',
            '/opt/cpanel/ea-nodejs16/bin/node',
            $home . '/nodevenv/node/bin/node',
            'node',
        ]) as $bin) {
            try {
                if (Process::timeout(8)->run([$bin, '-v'])->successful()) {
                    return $bin;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    private function renderPdfPageToPngFile(string $pdfPath, int $page): ?string
    {
        $out = sys_get_temp_dir() . '/nccia-ocr-' . uniqid('', true) . '.png';

        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick();
                $im->setResolution(200, 200);
                $im->readImage($pdfPath . '[' . $page . ']');
                $im->setImageBackgroundColor(new \ImagickPixel('white'));
                if (defined('\Imagick::ALPHACHANNEL_REMOVE')) {
                    $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                }
                $im->setImageFormat('png');
                $im->writeImage($out);
                $im->clear();
                if (is_file($out) && filesize($out) > 200) {
                    return $out;
                }
            } catch (\Throwable $e) {
                Log::warning('Imagick render failed', ['error' => $e->getMessage()]);
            }
        }

        $pageNum = $page + 1;
        foreach (
            [
                ['gs', ['-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=png16m', '-r200', '-dFirstPage=' . $pageNum, '-dLastPage=' . $pageNum, '-sOutputFile=' . $out, $pdfPath]],
                ['ghostscript', ['-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=png16m', '-r200', '-dFirstPage=' . $pageNum, '-dLastPage=' . $pageNum, '-sOutputFile=' . $out, $pdfPath]],
                ['pdftoppm', ['-png', '-f', (string) $pageNum, '-l', (string) $pageNum, '-r', '200', '-singlefile', $pdfPath, preg_replace('/\.png$/', '', $out)]],
                ['mutool', ['draw', '-o', $out, '-r', '200', $pdfPath, (string) $pageNum]],
            ] as [$bin, $args]
        ) {
            try {
                $cmd = $this->findBinaryOnPath($bin);
                if (!$cmd) {
                    continue;
                }
                $r = Process::timeout(60)->run(array_merge([$cmd], $args));
                $png = $out;
                if (!is_file($png)) {
                    $png = preg_replace('/\.png$/', '', $out) . '.png';
                }
                if (($r->successful() || is_file($png)) && is_file($png) && filesize($png) > 200) {
                    return $png;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return is_file($out) && filesize($out) > 200 ? $out : null;
    }

    private function findBinaryOnPath(string $name): ?string
    {
        foreach (['/usr/bin/' . $name, '/usr/local/bin/' . $name] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        try {
            $r = Process::timeout(5)->run(['bash', '-lc', 'command -v ' . escapeshellarg($name)]);
            $path = trim($r->output());
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
