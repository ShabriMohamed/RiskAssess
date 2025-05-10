<?php
// processes/counsellor_generate_report.php
require_once '../config.php';
require_once '../vendor/autoload.php'; // For TCPDF
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['assessment_id']) || !is_numeric($_GET['assessment_id'])) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid assessment ID']);
    exit;
}

$assessment_id = intval($_GET['assessment_id']);
$counsellor_user_id = $_SESSION['user_id'];

// Get counsellor name
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $counsellor_user_id);
$stmt->execute();
$counsellor_name = $stmt->get_result()->fetch_assoc()['name'];

// Get assessment details
$stmt = $conn->prepare("
    SELECT ra.*, u.name as client_name, u.email as client_email, u.telephone as client_phone
    FROM risk_assessments ra
    JOIN users u ON ra.client_id = u.id
    WHERE ra.id = ?
");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Assessment not found']);
    exit;
}

$assessment = $result->fetch_assoc();

// Parse questionnaire data and key factors
$questionnaire_data = json_decode($assessment['questionnaire_data'], true);
$key_factors = json_decode($assessment['key_factors'], true) ?: [];

// Create custom PDF class with header and footer
class MYPDF extends TCPDF {
    public function Header() {
        // Logo
        $image_file = '../assets/img/logo.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, 10, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Set font
        $this->SetFont('helvetica', 'B', 20);
        
        // Title
        $this->SetTextColor(79, 140, 255);
        $this->Cell(0, 15, 'RiskAssess', 0, false, 'R', 0, '', 0, false, 'M', 'M');
        
        // Subtitle
        $this->Ln(10);
        $this->SetFont('helvetica', 'I', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Substance Use Risk Assessment Report', 0, false, 'R', 0, '', 0, false, 'M', 'M');
        
        // Line
        $this->Line(10, 25, $this->getPageWidth() - 10, 25, array('width' => 0.5, 'color' => array(79, 140, 255)));
    }

    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, 'Generated on ' . date('F j, Y'), 0, false, 'R', 0, '', 0, false, 'T', 'M');
        $this->Line(10, $this->getPageHeight() - 15, $this->getPageWidth() - 10, $this->getPageHeight() - 15, array('width' => 0.5, 'color' => array(200, 200, 200)));
    }
}

// Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('RiskAssess Platform');
$pdf->SetAuthor($counsellor_name);
$pdf->SetTitle('Risk Assessment Report');
$pdf->SetSubject('Client Risk Assessment');
$pdf->SetKeywords('risk assessment, substance use, counselling');

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, 30, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Client Information Section
$pdf->SetFillColor(240, 240, 250);
$pdf->SetTextColor(45, 55, 72);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'CLIENT INFORMATION', 0, 1, 'L', 0);
$pdf->SetFont('helvetica', '', 12);

$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.1);

// Client info table
$pdf->SetFillColor(249, 250, 251);
$pdf->Cell(40, 10, 'Name:', 1, 0, 'L', 1);
$pdf->Cell(0, 10, $assessment['client_name'], 1, 1, 'L', 0);

$pdf->Cell(40, 10, 'Email:', 1, 0, 'L', 1);
$pdf->Cell(0, 10, $assessment['client_email'], 1, 1, 'L', 0);

$pdf->Cell(40, 10, 'Phone:', 1, 0, 'L', 1);
$pdf->Cell(0, 10, $assessment['client_phone'] ?: 'Not provided', 1, 1, 'L', 0);

$pdf->Cell(40, 10, 'Assessment Date:', 1, 0, 'L', 1);
$pdf->Cell(0, 10, date('F j, Y', strtotime($assessment['assessment_date'])), 1, 1, 'L', 0);

$pdf->Ln(10);

// Risk Assessment Results Section
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'RISK ASSESSMENT RESULTS', 0, 1, 'L', 0);
$pdf->SetFont('helvetica', '', 12);

// Risk level with color coding
$pdf->Cell(40, 10, 'Risk Level:', 1, 0, 'L', 1);

// Set color based on risk level
switch($assessment['risk_level']) {
    case 'High':
        $pdf->SetTextColor(220, 38, 38);
        break;
    case 'Moderate':
        $pdf->SetTextColor(217, 119, 6);
        break;
    case 'Low':
        $pdf->SetTextColor(5, 150, 105);
        break;
    default:
        $pdf->SetTextColor(45, 55, 72);
}

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, $assessment['risk_level'], 1, 1, 'L', 0);
$pdf->SetTextColor(45, 55, 72);
$pdf->SetFont('helvetica', '', 12);

$pdf->Ln(10);

// Key Risk Factors Section
if (!empty($key_factors)) {
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'KEY RISK FACTORS', 0, 1, 'L', 0);
    $pdf->SetFont('helvetica', '', 12);
    
    $pdf->SetFillColor(249, 250, 251);
    foreach ($key_factors as $index => $factor) {
        $fill = $index % 2 == 0 ? 1 : 0;
        $pdf->Cell(10, 10, ($index + 1) . '.', 1, 0, 'C', $fill);
        $pdf->Cell(0, 10, $factor, 1, 1, 'L', $fill);
    }
    
    $pdf->Ln(10);
}

// Recommendations Section
if (!empty($assessment['recommendations'])) {
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'RECOMMENDATIONS', 0, 1, 'L', 0);
    $pdf->SetFont('helvetica', '', 12);
    
    // Set a light blue background for recommendations
    $pdf->SetFillColor(240, 249, 255);
    $pdf->SetDrawColor(59, 130, 246);
    $pdf->SetLineWidth(0.5);
    
    $pdf->MultiCell(0, 10, $assessment['recommendations'], 1, 'L', 1);
    
    $pdf->Ln(10);
}

// Counsellor Notes Section
if (!empty($assessment['notes'])) {
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'COUNSELLOR NOTES', 0, 1, 'L', 0);
    $pdf->SetFont('helvetica', '', 12);
    
    $pdf->SetFillColor(250, 250, 250);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.1);
    
    $pdf->MultiCell(0, 10, $assessment['notes'], 1, 'L', 1);
    
    $pdf->Ln(10);
}

// Add a confidentiality notice
$pdf->SetFont('helvetica', 'I', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 10, 'CONFIDENTIALITY NOTICE: This document contains confidential information intended only for the use of the individual or entity named above. If you are not the intended recipient, you are hereby notified that any disclosure, copying, distribution, or use of the information contained in this document is strictly prohibited.', 0, 'L', 0);

// Log the action
$details = json_encode([
    'assessment_id' => $assessment_id,
    'timestamp' => date('Y-m-d H:i:s')
]);

$stmt = $conn->prepare("
    INSERT INTO audit_log (user_id, action, table_name, record_id, details)
    VALUES (?, 'Downloaded assessment report', 'risk_assessments', ?, ?)
");
$stmt->bind_param("iis", $counsellor_user_id, $assessment_id, $details);
$stmt->execute();

// Output the PDF
$pdf->Output('RiskAssess_Report_' . $assessment_id . '.pdf', 'D');
exit;
?>
