<?php

namespace App\Http\Controllers\PDF;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use FPDF;

class BlankPA extends Controller
{
    public function generatepa()
    {
        // Long Bond Paper
        $pdf = new FPDF('P', 'mm', [215.9, 330.2]);

        $pdf->AddPage();

        $logo = public_path('assets/sc_logo.png');
        $footer = public_path('assets/footer.png');

        $pdf->Image(
            public_path('assets/watermark.png'),
            7,
            57,
            200,
            200.2
        );

        $pdf->Image($logo, 50, 10, 25, 25);
        $pdf->setFont('Arial', '', 12);
        $pdf->SetXY(105, 10);
        $pdf->Cell(0, 9, 'Republic of the Philippines', 0, 1);

        $pdf->setFont('Arial', 'B', 12);
        $pdf->SetXY(80, 15);
        $pdf->Cell(0, 10, 'TRICYCLE FRANCHISING & REGULATORY OFFICE', 0, 1);

        $pdf->setFont('Arial', '', 12);
        $pdf->SetXY(111, 20);
        $pdf->Cell(0, 9, 'City Hall Compound', 0, 1);

        $pdf->setFont('Arial', '', 12);
        $pdf->SetXY(119, 25);
        $pdf->Cell(0, 9, 'Surigao City', 0, 1);

        $pdf->setFont('Arial', '', 12);
        $pdf->SetXY(20, 40);
        $pdf->Cell(0, 9, 'TTFO-SP, SC', 0, 1);

        $pdf->Rect(20, 48, 175, 13);

        $pdf->setFont('Arial', 'B', 20);
        $pdf->SetXY(58, 51);
        $pdf->Cell(0, 9, 'PROVISIONAL AUTHORITY', 0, 1);

        $pdf->SetFillColor(0, 70, 3);
        $pdf->Rect(20, 62, 176, 12, 'F');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetXY(23, 64);
        $pdf->Cell(0, 9, "APPLICATION FOR A MOTORIZED TRICYCLE OPERATOR'S PERMIT (MTOP)", 0, 1);

        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetXY(19, 77);
        $pdf->Cell(0, 9, "HON. PABLO YVES L. DUMLAO II", 0, 1);

        $pdf->SetFont('Arial', '', 13);
        $pdf->SetXY(19, 83);
        $pdf->Cell(0, 9, "City Mayor", 0, 1);

        $pdf->SetFont('Arial', '', 13);
        $pdf->SetXY(19, 88);
        $pdf->Cell(0, 9, "Surigao City", 0, 1);

        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetXY(19, 100);
        $pdf->Cell(0, 9, "Sir:", 0, 1);

        $leftMargin = 19;
        $rightMargin = 20;
        $indent = 18;
        $lineHeight = 6;
        $fontSize = 12;

        $pdf->SetLeftMargin($leftMargin);
        $pdf->SetRightMargin($rightMargin);

        $rightLimit = $pdf->GetPageWidth() - $rightMargin;

        // First line starts with indentation
        $x = $leftMargin + $indent;
        $y = 108;

        $parts = [
            [
                'text' => "I/We have the honor to apply for a ",
                'style' => ''
            ],
            [
                'text' => "MOTORIZED TRICYCLE OPERATOR'S PERMIT (MTOP).",
                'style' => 'B'
            ]
        ];

        foreach ($parts as $part) {

            $pdf->SetFont('Arial', $part['style'], $fontSize);

            // Process each character
            $characters = str_split($part['text']);

            foreach ($characters as $char) {

                $charWidth = $pdf->GetStringWidth($char);

                // Wrap only when right boundary is reached
                if ($x + $charWidth > $rightLimit) {
                    $x = $leftMargin;
                    $y += $lineHeight;
                }

                // Skip spaces at beginning of new line
                if ($char === ' ' && $x == $leftMargin) {
                    continue;
                }

                // Draw character at exact position
                $pdf->Text(
                    $x,
                    $y + 4.5,
                    $char
                );

                $x += $charWidth;
            }
        }

        // Position cursor after paragraph
        $pdf->SetXY($leftMargin, $y + $lineHeight);

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect(20, 122, 55, 10);
        $pdf->Rect(75, 122, 57, 10);
        $pdf->Rect(132, 122, 20, 10);
        $pdf->Rect(152, 122, 44, 10);

        
        $franchise_number = "";
        $date = "";

        $pdf->SetFont('Arial', '', 13);
        $pdf->SetXY(22, 123);
        $pdf->Cell(0, 9, "Franchise Number:", 0, 1);

        $pdf->SetXY(77, 123);
        $pdf->Cell(0, 9, $franchise_number, 0, 1);

        $pdf->SetXY(133, 123);
        $pdf->Cell(0, 9, "Date:", 0, 1);

        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetXY(161, 123);
        $pdf->Cell(0, 9, $date, 0, 1);

                
        $franchise_owner = "";

        $pdf->Rect(20, 133, 55, 10);
        $pdf->Rect(75, 133, 121, 10);

        $pdf->SetXY(22, 134);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, "Name of Applicant:", 0, 1);
        $pdf->SetXY(77, 134);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 9, $franchise_owner, 0, 1);

        $owner_address = "";

        $pdf->Rect(20, 144, 55, 10);
        $pdf->Rect(75, 144, 121, 10);

        $pdf->SetXY(22, 145);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, "Complete Address:", 0, 1);

        $pdf->SetXY(77, 145);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 9, $owner_address, 0, 1);


        $pdf->SetFillColor(0, 70, 3);
        $pdf->Rect(20, 155, 176, 12, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetXY(76, 157);
        $pdf->Cell(0, 9, "DESCRIPTION OF UNITS", 0, 1);

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(0, 0, 0);

                
        $pdf->Rect(20, 168, 55, 10);
        $pdf->Rect(75, 168, 121, 10);

        $pdf->SetTextColor(0, 0, 0);

        $plate_number = '';
        $pdf->SetXY(22, 169);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, "Plate Number:", 0, 1);

        $pdf->SetXY(77, 169);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 9, $plate_number, 0, 1);

        $pdf->Rect(20, 179, 55, 10);
        $pdf->Rect(75, 179, 121, 10);

        $liner = '';
        $pdf->SetXY(22, 180);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, "Route of Operation:", 0, 1);

        $pdf->SetXY(77, 180);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 9, $liner, 0, 1);

        $pdf->Rect(20, 190, 55, 10);
        $pdf->Rect(75, 190, 121, 10);

        $model = '';
        $pdf->SetXY(22, 191);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, "Make/Model:", 0, 1);

        $pdf->SetXY(77, 191);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 9, $model, 0, 1);

        $pdf->Rect(20, 201, 55, 10);
        $pdf->Rect(75, 201, 121, 10);

        $motor_number = '';
        $pdf->SetXY(22, 202);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, "Engine Number:", 0, 1);

        $pdf->SetXY(77, 202);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 9, $liner, 0, 1);

        $pdf->Rect(20, 212, 55, 10);
        $pdf->Rect(75, 212, 121, 10);

        $chassis = '';
        $pdf->SetXY(22, 213);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, "Chassis Number:", 0, 1);

        $pdf->SetXY(77, 213);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 9, $chassis, 0, 1);

        $pdf->Rect(20, 223, 55, 10);
        $pdf->Rect(75, 223, 121, 10);

        $pdf->SetXY(22, 224);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, "Body Number:", 0, 1);

        $pdf->SetXY(77, 224);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 9, $franchise_number, 0, 1);

        $pdf->Rect(20, 234, 55, 10);
        $pdf->Rect(75, 234, 121, 10);

        $purpose = '';
        $pdf->SetXY(22, 235);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, "Purpose:", 0, 1);

        $pdf->SetXY(77, 235);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 9, $purpose, 0, 1);


        $pdf->SetDrawColor(0, 0, 0); // Black
        $pdf->SetLineWidth(0.3);    // Thickness in mm

        $pdf->Line(120, 260, 195, 260);
        
        $pdf->SetXY(134, 259);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, 'Signature of Applicant', 0, 1);

        $pdf->SetXY(85, 265);
        $pdf->SetFont('Arial', '', 13);
        $pdf->Cell(0, 9, 'Approve', 0, 1);

        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetXY(105, 280);
        $pdf->Cell(0, 9, 'ATTY. LEX JOSHUA ANDREW U. SILVOSA', 0, 1);
        $pdf->Line(105, 287, 201, 287);
        $pdf->SetXY(133, 286);
        $pdf->Cell(0, 9, 'TTFO HEAD (OIC)', 0, 1);

        $pdf->SetFont('Arial', '', 11);
        $pdf->SetXY(15, 300);
        $pdf->Cell(0, 9, "**This provisional authority shall be valid only pending approval of the MTOP from Sangguniang Panglungsod.", 0, 1);

        $pdf->Image($footer, 0, 311, 220, 19);

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="application.pdf"',
        ]);
    }
}
