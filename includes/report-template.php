<?php

function report_template_text_node(DOMDocument $document, DOMElement $paragraph, string $text): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $properties = null;
    $runProperties = null;
    foreach (iterator_to_array($paragraph->childNodes) as $child) {
        if ($child instanceof DOMElement && $child->localName === 'pPr') {
            $properties = $child;
            continue;
        }
        if (!$runProperties && $child instanceof DOMElement && $child->localName === 'r') {
            foreach ($child->childNodes as $runChild) {
                if ($runChild instanceof DOMElement && $runChild->localName === 'rPr') {
                    $runProperties = $runChild->cloneNode(true);
                    break;
                }
            }
        }
        $paragraph->removeChild($child);
    }
    $run = $document->createElementNS($namespace, 'w:r');
    if ($runProperties) {
        $run->appendChild($runProperties);
    }
    $textParts = preg_split('/\R/', $text) ?: [''];
    foreach ($textParts as $index => $part) {
        if ($index > 0) {
            $run->appendChild($document->createElementNS($namespace, 'w:br'));
        }
        $textNode = $document->createElementNS($namespace, 'w:t');
        if ($part === '' || $part[0] === ' ' || substr($part, -1) === ' ') {
            $textNode->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        }
        $textNode->appendChild($document->createTextNode($part));
        $run->appendChild($textNode);
    }
    if ($properties) {
        $paragraph->appendChild($run);
    } else {
        $paragraph->appendChild($run);
    }
}

function report_template_paragraph(DOMDocument $document, DOMElement $body, int $index, string $text): void
{
    $node = $body->childNodes->item($index);
    if ($node instanceof DOMElement && $node->localName === 'p') {
        report_template_text_node($document, $node, $text);
    }
}

function report_template_table_rows(DOMDocument $document, DOMElement $table, array $rows): void
{
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $tableRows = [];
    foreach ($table->childNodes as $child) {
        if ($child instanceof DOMElement && $child->localName === 'tr') {
            $tableRows[] = $child;
        }
    }
    if (!$tableRows) {
        return;
    }
    foreach (array_slice($tableRows, 1) as $row) {
        $table->removeChild($row);
    }
    $templateRow = $tableRows[1] ?? $tableRows[0];
    foreach ($rows as $values) {
        $row = $templateRow->cloneNode(true);
        $cells = [];
        foreach ($row->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'tc') {
                $cells[] = $child;
            }
        }
        foreach ($cells as $cellIndex => $cell) {
            $paragraph = null;
            foreach ($cell->childNodes as $child) {
                if ($child instanceof DOMElement && $child->localName === 'p') {
                    $paragraph = $child;
                    break;
                }
            }
            if ($paragraph) {
                report_template_text_node($document, $paragraph, (string) ($values[$cellIndex] ?? ''));
            }
        }
        $table->appendChild($row);
    }
}

function report_template_docx(array $case, array $study, array $familyRows, array $planRows): string
{
    $template = __DIR__ . '/../reference/SCSR-Format (1).docx';
    if (!is_file($template)) {
        throw new RuntimeException('The original report template is missing.');
    }
    $temporaryDocx = tempnam(sys_get_temp_dir(), 'swassist-report-') . '.docx';
    copy($template, $temporaryDocx);
    $archive = new ZipArchive();
    if ($archive->open($temporaryDocx) !== true) {
        throw new RuntimeException('The original report template could not be opened.');
    }
    $document = new DOMDocument();
    $document->preserveWhiteSpace = true;
    $document->loadXML($archive->getFromName('word/document.xml'));
    $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $body = $document->getElementsByTagNameNS($namespace, 'body')->item(0);
    $date = !empty($case['updated_at']) ? date('F j, Y', strtotime($case['updated_at'])) : date('F j, Y');
    $fields = [
        7 => 'Date: ' . $date,
        11 => "Name\t: " . ($case['client_name'] ?? ''),
        12 => "Age\t: " . ($case['age'] ?? ''),
        13 => "Sex\t: " . ($case['sex'] ?? ''),
        14 => "Civil Status\t: " . ($case['civil_status'] ?? ''),
        15 => "Religious affiliation\t: " . ($case['religious_affiliation'] ?? ''),
        16 => "Date of Birth\t: " . (!empty($case['date_of_birth']) ? date('F j, Y', strtotime($case['date_of_birth'])) : ''),
        17 => "Place of Birth\t: " . ($case['place_of_birth'] ?? ''),
        18 => "Highest Educational Attainment\t: " . ($case['education'] ?? ''),
        19 => "Occupation\t: " . ($case['occupation'] ?? ''),
        20 => "Monthly Income\t: " . (($case['monthly_income'] ?? '') !== '' ? number_format((float) $case['monthly_income'], 2) : ''),
        21 => "Present address\t: " . ($case['present_address'] ?? ''),
        22 => "Home address\t: " . ($case['home_address'] ?? ''),
        28 => (string) ($study['presenting_problem'] ?? ''),
        29 => '',
        33 => (string) ($study['background_client'] ?? ''),
        34 => '', 35 => '', 36 => '',
        39 => (string) ($study['background_family'] ?? ''),
        40 => '', 41 => '', 42 => '', 43 => '', 44 => '', 45 => '',
        48 => (string) ($study['background_environment'] ?? ''),
        49 => '', 50 => '', 51 => '', 52 => '', 53 => '',
        57 => (string) ($study['assessment'] ?? ''),
        58 => '', 59 => '', 60 => '', 61 => '', 62 => '', 63 => '',
        67 => (string) ($study['goal'] ?? ''),
        77 => (string) ($study['prepared_signature'] ?: ($study['prepared_by'] ?? '')),
        83 => (string) ($study['noted_signature'] ?: ($study['noted_by'] ?? '')),
    ];
    foreach ($fields as $index => $value) {
        report_template_paragraph($document, $body, $index, $value);
    }
    $tables = $body->getElementsByTagNameNS($namespace, 'tbl');
    if ($tables->length >= 2) {
        $familyValues = [];
        foreach ($familyRows as $row) {
            $familyValues[] = [
                $row['name'] ?? '', $row['relationship'] ?? '', $row['age'] ?? '',
                !empty($row['birthday']) ? date('F j, Y', strtotime($row['birthday'])) : '',
                $row['education'] ?? '', $row['occupation'] ?? '', $row['civil_status'] ?? '',
                ($row['monthly_income'] ?? '') !== '' && $row['monthly_income'] !== null ? number_format((float) $row['monthly_income'], 2) : '',
            ];
        }
        report_template_table_rows($document, $tables->item(0), $familyValues);
        $planValues = [];
        foreach ($planRows as $row) {
            $planValues[] = [$row['problems'] ?? '', $row['objectives'] ?? '', $row['activities'] ?? '', $row['responsible_person'] ?? '', $row['time_frame'] ?? '', $row['expected_output'] ?? ''];
        }
        report_template_table_rows($document, $tables->item(1), $planValues);
    }
    $archive->addFromString('word/document.xml', $document->saveXML());
    $archive->close();
    return $temporaryDocx;
}

function report_template_pdf(string $docx): string
{
    $directory = sys_get_temp_dir() . '/swassist-report-pdf-' . bin2hex(random_bytes(4));
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('The PDF conversion directory could not be created.');
    }
    $profile = $directory . '/profile';
    if (!mkdir($profile, 0700, true) && !is_dir($profile)) {
        throw new RuntimeException('The PDF conversion profile could not be created.');
    }
    $command = 'soffice -env:UserInstallation=' . escapeshellarg('file://' . $profile) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($directory) . ' ' . escapeshellarg($docx) . ' 2>&1';
    exec($command, $output, $status);
    $pdf = $directory . '/' . pathinfo($docx, PATHINFO_FILENAME) . '.pdf';
    if ($status !== 0 || !is_file($pdf)) {
        throw new RuntimeException('PDF conversion is unavailable: ' . implode(' ', $output));
    }
    return $pdf;
}
